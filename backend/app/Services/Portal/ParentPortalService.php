<?php

namespace App\Services\Portal;

use App\Models\Announcement;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LeaveApplication;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ParentPortalService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Resolve guardian for user.
     */
    public function resolveGuardian(User $user): Guardian
    {
        $guardian = Guardian::where('user_id', $user->id)->first();

        if (!$guardian) {
            // Also check by email if user_id wasn't set initially
            $guardian = Guardian::where('email', $user->email)
                ->where('tenant_id', $this->tenantContext->getTenantId())
                ->first();

            if ($guardian && !$guardian->user_id) {
                $guardian->update(['user_id' => $user->id]);
            }
        }

        if (!$guardian) {
            throw new AuthorizationException('No active parent or guardian profile found for this account.');
        }

        return $guardian;
    }

    /**
     * Retrieve all children linked to this guardian account.
     */
    public function getLinkedChildren(User $user): Collection
    {
        $guardian = $this->resolveGuardian($user);

        return $guardian->students()
            ->with(['activeEnrollment.schoolClass', 'activeEnrollment.section'])
            ->get();
    }

    /**
     * Strict Authorization Check: Validate that the child is linked to this guardian account.
     */
    public function validateChildAccess(User $user, string $studentId): Student
    {
        if ($user->hasAnyRole(['platform_admin', 'school_owner', 'school_admin', 'principal'])) {
            return Student::with(['activeEnrollment.schoolClass', 'activeEnrollment.section'])->findOrFail($studentId);
        }

        $guardian = $this->resolveGuardian($user);

        $isLinked = DB::table('student_guardians')
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $studentId)
            ->exists();

        if (!$isLinked) {
            throw new AuthorizationException('Unauthorized access: You can only view and manage children linked to your guardian profile.');
        }

        return Student::with(['activeEnrollment.schoolClass', 'activeEnrollment.section'])->findOrFail($studentId);
    }

    /**
     * Comprehensive Child Dashboard for Parent.
     */
    public function getChildDashboard(User $user, string $studentId): array
    {
        $child = $this->validateChildAccess($user, $studentId);
        $enrollment = $child->activeEnrollment;
        $classId = $enrollment?->class_id;
        $sectionId = $enrollment?->section_id;

        // 1. Attendance Summary
        $attQuery = StudentAttendance::where('student_id', $child->id);
        $totalDays = (clone $attQuery)->count();
        $presentDays = (clone $attQuery)->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentDays = (clone $attQuery)->where('status', 'absent')->count();
        $attRatio = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 100.0;

        // 2. Fee Summary
        $invoices = FeeInvoice::where('student_id', $child->id)->get();
        $totalFees = (float) $invoices->sum('total_amount');
        $paidFees = (float) $invoices->sum('paid_amount');
        $dueFees = (float) $invoices->sum('balance_amount');

        // 3. Child Homework
        $hwQuery = Homework::with('subject')->where('is_active', true);
        if ($classId) {
            $hwQuery->where('class_id', $classId);
        }
        if ($sectionId) {
            $hwQuery->where(function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId)->orWhereNull('section_id');
            });
        }
        $homeworkList = $hwQuery->orderBy('due_date', 'asc')->limit(5)->get();

        $submissions = HomeworkSubmission::where('student_id', $child->id)
            ->whereIn('homework_id', $homeworkList->pluck('id'))
            ->get()
            ->keyBy('homework_id');

        $homeworkStatus = $homeworkList->map(function ($hw) use ($submissions) {
            $sub = $submissions->get($hw->id);
            return [
                'id' => $hw->id,
                'title' => $hw->title,
                'subject' => $hw->subject?->name,
                'due_date' => $hw->due_date->format('Y-m-d'),
                'is_submitted' => $sub !== null,
                'status' => $sub?->status ?? 'pending',
                'marks' => $sub?->marks_obtained ? (float) $sub->marks_obtained : null,
            ];
        });

        // 4. Latest Published Report Cards
        $reportCards = ReportCard::with('examination')
            ->where('student_id', $child->id)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        // 5. School Notices / Announcements
        $notices = Announcement::where('is_published', true)
            ->whereIn('target_audience', ['all', 'parents'])
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'child' => [
                'id' => $child->id,
                'name' => "{$child->first_name} {$child->last_name}",
                'admission_number' => $child->admission_number,
                'class_name' => $enrollment?->schoolClass?->name ?? 'Unassigned',
                'section_name' => $enrollment?->section?->name ?? 'Unassigned',
                'photo_url' => $child->photo_url,
            ],
            'attendance' => [
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'percentage' => $attRatio,
            ],
            'financial' => [
                'total_fees' => $totalFees,
                'paid_fees' => $paidFees,
                'due_fees' => $dueFees,
                'status' => $dueFees <= 0 ? 'CLEARED' : 'DUE',
            ],
            'homework' => $homeworkStatus,
            'report_cards' => $reportCards,
            'notices' => $notices,
        ];
    }

    /**
     * Get Child Fee Invoices & Payment Ledger.
     */
    public function getChildFees(User $user, string $studentId): array
    {
        $child = $this->validateChildAccess($user, $studentId);

        $invoices = FeeInvoice::with(['structure', 'items'])
            ->where('student_id', $child->id)
            ->orderBy('due_date', 'desc')
            ->get();

        $payments = FeePayment::where('student_id', $child->id)
            ->orderBy('payment_date', 'desc')
            ->get();

        return [
            'invoices' => $invoices,
            'payments' => $payments,
            'summary' => [
                'total_invoiced' => (float) $invoices->sum('total_amount'),
                'total_paid' => (float) $invoices->sum('paid_amount'),
                'total_outstanding' => (float) $invoices->sum('balance_amount'),
            ],
        ];
    }

    /**
     * Get Child Timetable.
     */
    public function getChildTimetable(User $user, string $studentId): array
    {
        $child = $this->validateChildAccess($user, $studentId);
        $enrollment = $child->activeEnrollment;
        if (!$enrollment) return [];

        return DB::table('timetable_entries')
            ->join('timetable_periods', 'timetable_entries.period_id', '=', 'timetable_periods.id')
            ->join('subjects', 'timetable_entries.subject_id', '=', 'subjects.id')
            ->leftJoin('employees', 'timetable_entries.employee_id', '=', 'employees.id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->where('timetable_entries.class_id', $enrollment->class_id)
            ->where('timetable_entries.section_id', $enrollment->section_id)
            ->select(
                'timetable_entries.id',
                'timetable_entries.day_of_week',
                'timetable_entries.room_number',
                'timetable_periods.period_number',
                'timetable_periods.period_name',
                'timetable_periods.start_time',
                'timetable_periods.end_time',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'users.name as teacher_name'
            )
            ->orderBy('timetable_entries.day_of_week')
            ->orderBy('timetable_periods.period_number')
            ->get()
            ->toArray();
    }

    /**
     * Submit leave application for child.
     */
    public function submitChildLeave(User $user, string $studentId, array $data): LeaveApplication
    {
        $child = $this->validateChildAccess($user, $studentId);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        if ($endDate->isBefore($startDate)) {
            throw new InvalidArgumentException('End date cannot be earlier than start date.');
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;

        return LeaveApplication::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $child->branch_id,
            'applicant_type' => 'student',
            'applicant_id' => $child->id,
            'leave_type' => $data['leave_type'] ?? 'sick',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'medical_certificate_path' => $data['medical_certificate_path'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * Send communication / support ticket to teacher or school regarding child.
     */
    public function sendCommunication(User $user, string $studentId, array $data): SupportTicket
    {
        $child = $this->validateChildAccess($user, $studentId);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $child->branch_id,
            'ticket_number' => 'TCK-' . strtoupper(bin2hex(random_bytes(3))),
            'user_id' => $user->id,
            'category' => $data['category'] ?? 'academic',
            'subject' => "[{$child->first_name} {$child->last_name}] " . $data['subject'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $data['message'],
        ]);

        return $ticket;
    }
}

