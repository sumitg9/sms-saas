<?php

namespace App\Services\Visitor;

use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGatePass;
use App\Models\Visitor;
use App\Models\VisitorPass;
use App\Models\VisitorStudentPickup;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VisitorService
{
    /**
     * Register or retrieve existing visitor by phone.
     */
    public function findOrCreateVisitor(array $data): Visitor
    {
        $visitor = Visitor::where('branch_id', $data['branch_id'])
            ->where('phone', trim($data['phone']))
            ->first();

        if ($visitor) {
            $visitor->update(array_filter([
                'full_name' => $data['full_name'] ?? $visitor->full_name,
                'national_id' => $data['national_id'] ?? $visitor->national_id,
                'photo_url' => $data['photo_url'] ?? $visitor->photo_url,
                'address' => $data['address'] ?? $visitor->address,
            ]));
            return $visitor;
        }

        return Visitor::create([
            'branch_id' => $data['branch_id'],
            'full_name' => trim($data['full_name']),
            'phone' => trim($data['phone']),
            'national_id' => $data['national_id'] ?? null,
            'photo_url' => $data['photo_url'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }

    /**
     * Check-in visitor and issue pass.
     */
    public function checkIn(array $data, ?string $verifiedByUserId = null): VisitorPass
    {
        return DB::transaction(function () use ($data, $verifiedByUserId) {
            $visitor = $this->findOrCreateVisitor($data);

            // Host employee
            $host = Employee::withoutGlobalScopes()->findOrFail($data['host_employee_id']);

            // Generate pass number
            $passSeq = rand(1000, 9999);
            $passNumber = 'VP-' . Carbon::today()->format('ymd') . '-' . $passSeq;

            $pass = VisitorPass::create([
                'branch_id' => $data['branch_id'],
                'visitor_id' => $visitor->id,
                'pass_number' => $passNumber,
                'host_employee_id' => $host->id,
                'purpose' => $data['purpose'],
                'check_in_time' => Carbon::now(),
                'badge_printed' => $data['badge_printed'] ?? false,
                'status' => 'checked_in',
            ]);

            // Handle student pickup if included
            if (!empty($data['student_pickup'])) {
                $pickupData = $data['student_pickup'];
                $student = Student::withoutGlobalScopes()->findOrFail($pickupData['student_id']);

                // Find guardian if available
                $guardianId = $pickupData['guardian_id'] ?? null;
                if (!$guardianId && !empty($pickupData['guardian_phone'])) {
                    $guardian = Guardian::withoutGlobalScopes()->where('phone', $pickupData['guardian_phone'])->first();
                    $guardianId = $guardian?->id;
                }

                VisitorStudentPickup::create([
                    'visitor_pass_id' => $pass->id,
                    'student_id' => $student->id,
                    'guardian_id' => $guardianId,
                    'authorization_status' => $pickupData['authorization_status'] ?? 'verified',
                    'relationship_to_student' => $pickupData['relationship_to_student'] ?? 'Guardian / Relative',
                    'parent_consent_confirmed' => $pickupData['parent_consent_confirmed'] ?? true,
                    'parent_contact_phone' => $pickupData['parent_contact_phone'] ?? null,
                    'verification_notes' => $pickupData['verification_notes'] ?? null,
                    'verified_by' => $verifiedByUserId,
                ]);
            }

            return $pass->fresh(['visitor', 'hostEmployee', 'pickup.student']);
        });
    }

    /**
     * Check-out visitor.
     */
    public function checkOut(string $passId): VisitorPass
    {
        $pass = VisitorPass::with('visitor')->findOrFail($passId);

        if ($pass->status === 'checked_out') {
            throw new InvalidArgumentException("Visitor pass '{$pass->pass_number}' is already checked out.");
        }

        $pass->update([
            'status' => 'checked_out',
            'check_out_time' => Carbon::now(),
        ]);

        return $pass->fresh(['visitor', 'hostEmployee']);
    }

    /**
     * Issue early departure student gate pass.
     */
    public function issueStudentGatePass(array $data, ?string $approvedByUserId = null): StudentGatePass
    {
        $student = Student::withoutGlobalScopes()->findOrFail($data['student_id']);
        $guardian = Guardian::withoutGlobalScopes()->findOrFail($data['guardian_id']);

        $passNumber = 'GP-' . Carbon::today()->format('ymd') . '-' . rand(1000, 9999);

        return StudentGatePass::create([
            'branch_id' => $data['branch_id'],
            'student_id' => $student->id,
            'pass_number' => $passNumber,
            'guardian_id' => $guardian->id,
            'reason' => $data['reason'],
            'approved_by' => $approvedByUserId,
            'issued_time' => Carbon::now(),
            'status' => 'approved',
        ])->fresh(['student', 'guardian', 'approver']);
    }

    /**
     * Mark student gate pass as exited.
     */
    public function recordStudentExit(string $gatePassId): StudentGatePass
    {
        $gatePass = StudentGatePass::findOrFail($gatePassId);

        if ($gatePass->status === 'exited') {
            throw new InvalidArgumentException("Student has already exited through the gate.");
        }

        $gatePass->update([
            'status' => 'exited',
            'actual_exit_time' => Carbon::now(),
        ]);

        return $gatePass->fresh(['student', 'guardian']);
    }

    /**
     * Get visitor history with all past visits.
     */
    public function getVisitorHistory(string $visitorId): array
    {
        $visitor = Visitor::with(['passes.hostEmployee', 'passes.pickup.student'])->findOrFail($visitorId);

        return [
            'visitor' => $visitor,
            'total_visits' => $visitor->passes->count(),
            'passes' => $visitor->passes,
        ];
    }

    /**
     * Front desk dashboard reports.
     */
    public function getVisitorReports(string $branchId): array
    {
        $today = Carbon::today();

        $totalToday = VisitorPass::where('branch_id', $branchId)
            ->whereDate('check_in_time', $today)
            ->count();

        $currentlyInside = VisitorPass::where('branch_id', $branchId)
            ->where('status', 'checked_in')
            ->count();

        $checkedOutToday = VisitorPass::where('branch_id', $branchId)
            ->whereDate('check_out_time', $today)
            ->where('status', 'checked_out')
            ->count();

        $studentPickupsToday = VisitorStudentPickup::whereHas('pass', function ($q) use ($branchId, $today) {
            $q->where('branch_id', $branchId)->whereDate('check_in_time', $today);
        })->count();

        $activeGatePasses = StudentGatePass::where('branch_id', $branchId)
            ->whereDate('issued_time', $today)
            ->where('status', 'approved')
            ->count();

        return [
            'total_visitors_today' => $totalToday,
            'currently_inside_campus' => $currentlyInside,
            'checked_out_today' => $checkedOutToday,
            'student_pickups_today' => $studentPickupsToday,
            'pending_gate_exits_today' => $activeGatePasses,
        ];
    }
}

