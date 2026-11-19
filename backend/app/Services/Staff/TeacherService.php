<?php

namespace App\Services\Staff;

use App\Models\AcademicSession;
use App\Models\ClassTeacher;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\StaffAttendance;
use App\Models\TeacherSubject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\TimetableSubstitution;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TeacherService
{
    /**
     * Retrieve teachers for the branch with department and assignment counts.
     */
    public function getTeachers(array $filters = []): Collection
    {
        $branchId = $filters['branch_id'] ?? app(TenantContext::class)->getBranchId();

        $query = Employee::where('branch_id', $branchId)
            ->whereHas('designation', function (Builder $q) {
                $q->where('title', 'ILIKE', '%teacher%')
                    ->orWhere('title', 'ILIKE', '%faculty%')
                    ->orWhere('title', 'ILIKE', '%instructor%')
                    ->orWhere('code', 'ILIKE', '%tch%');
            })
            ->with(['department', 'designation', 'classTeachers.schoolClass', 'classTeachers.section']);

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'active');
        }

        return $query->get()->map(function (Employee $teacher) {
            return [
                'id' => $teacher->id,
                'employee_code' => $teacher->employee_code,
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'gender' => $teacher->gender,
                'department' => $teacher->department?->name,
                'designation' => $teacher->designation?->title,
                'status' => $teacher->status,
                'date_of_joining' => $teacher->date_of_joining?->format('Y-m-d'),
                'is_class_teacher' => $teacher->classTeachers->isNotEmpty(),
                'class_teacher_sections' => $teacher->classTeachers->map(fn ($ct) => [
                    'class_name' => $ct->schoolClass?->name,
                    'section_name' => $ct->section?->name,
                ]),
            ];
        });
    }

    /**
     * Compute teaching workload metrics for a teacher.
     */
    public function calculateWorkload(Employee $teacher, ?string $academicSessionId = null): array
    {
        $branchId = $teacher->branch_id;

        // Resolve active academic session if null
        if (!$academicSessionId) {
            $session = AcademicSession::where('branch_id', $branchId)->where('is_current', true)->first();
            $academicSessionId = $session?->id;
        }

        // Get all timetable entries where this teacher is assigned
        $entries = TimetableEntry::where('employee_id', $teacher->id)
            ->whereHas('timetable', function (Builder $q) use ($academicSessionId) {
                if ($academicSessionId) {
                    $q->where('academic_session_id', $academicSessionId);
                }
                $q->where('is_active', true);
            })
            ->with(['subject', 'schoolClass', 'section', 'period'])
            ->get();

        $totalTeachingPeriods = $entries->count();

        // Breakdown by subject
        $subjectBreakdown = $entries->groupBy('subject_id')->map(function ($items) {
            $first = $items->first();
            return [
                'subject_id' => $first->subject_id,
                'subject_name' => $first->subject?->name ?? 'Unknown',
                'subject_code' => $first->subject?->code ?? '',
                'periods_per_week' => $items->count(),
            ];
        })->values();

        // Breakdown by class & section
        $classBreakdown = $entries->groupBy(fn ($item) => "{$item->class_id}_{$item->section_id}")->map(function ($items) {
            $first = $items->first();
            return [
                'class_name' => $first->schoolClass?->name ?? '',
                'section_name' => $first->section?->name ?? '',
                'periods_per_week' => $items->count(),
            ];
        })->values();

        // Daily distribution (1=Mon ... 6=Sat)
        $dailyDistribution = [
            1 => ['day' => 'Monday', 'periods' => 0],
            2 => ['day' => 'Tuesday', 'periods' => 0],
            3 => ['day' => 'Wednesday', 'periods' => 0],
            4 => ['day' => 'Thursday', 'periods' => 0],
            5 => ['day' => 'Friday', 'periods' => 0],
            6 => ['day' => 'Saturday', 'periods' => 0],
        ];

        foreach ($entries as $entry) {
            if (isset($dailyDistribution[$entry->day_of_week])) {
                $dailyDistribution[$entry->day_of_week]['periods']++;
            }
        }

        // Standard school workload capacity (e.g. 8 periods * 5 or 6 days = 40 periods)
        $weeklyCapacity = 40;
        $freePeriods = max(0, $weeklyCapacity - $totalTeachingPeriods);

        // Check if assigned as Class Teacher
        $classTeacherAssignments = ClassTeacher::where('employee_id', $teacher->id)
            ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
            ->with(['schoolClass', 'section'])
            ->get();

        return [
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->full_name,
            'total_weekly_periods' => $totalTeachingPeriods,
            'weekly_capacity' => $weeklyCapacity,
            'free_periods_per_week' => $freePeriods,
            'utilization_rate' => $weeklyCapacity > 0 ? round(($totalTeachingPeriods / $weeklyCapacity) * 100, 1) : 0,
            'subjects' => $subjectBreakdown,
            'classes' => $classBreakdown,
            'daily_distribution' => array_values($dailyDistribution),
            'class_teacher_in_charge' => $classTeacherAssignments->map(fn ($cta) => [
                'class_name' => $cta->schoolClass?->name,
                'section_name' => $cta->section?->name,
                'is_primary' => $cta->is_primary,
            ]),
        ];
    }

    /**
     * Retrieve weekly timetable grid for a teacher.
     */
    public function getTimetable(Employee $teacher, ?string $academicSessionId = null): array
    {
        $branchId = $teacher->branch_id;

        if (!$academicSessionId) {
            $session = AcademicSession::where('branch_id', $branchId)->where('is_current', true)->first();
            $academicSessionId = $session?->id;
        }

        $entries = TimetableEntry::where('employee_id', $teacher->id)
            ->whereHas('timetable', function (Builder $q) use ($academicSessionId) {
                if ($academicSessionId) {
                    $q->where('academic_session_id', $academicSessionId);
                }
                $q->where('is_active', true);
            })
            ->with(['subject', 'schoolClass', 'section', 'period'])
            ->get();

        $grid = [];
        for ($day = 1; $day <= 6; $day++) {
            $grid[$day] = [];
        }

        foreach ($entries as $entry) {
            $grid[$entry->day_of_week][] = [
                'timetable_entry_id' => $entry->id,
                'period_id' => $entry->period_id,
                'period_name' => $entry->period?->period_name ?? $entry->period?->name,
                'period_number' => $entry->period?->period_number,
                'start_time' => $entry->period?->start_time,
                'end_time' => $entry->period?->end_time,
                'subject_name' => $entry->subject?->name,
                'class_name' => $entry->schoolClass?->name,
                'section_name' => $entry->section?->name,
                'room_number' => $entry->room_number,
            ];
        }

        return [
            'teacher' => [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'employee_code' => $teacher->employee_code,
            ],
            'schedule' => $grid,
        ];
    }

    /**
     * Allocate substitute teacher with strict conflict validation.
     */
    public function assignSubstitute(array $data): TimetableSubstitution
    {
        $entry = TimetableEntry::with(['timetable', 'period'])->findOrFail($data['timetable_entry_id']);
        $date = Carbon::parse($data['date']);
        $dayOfWeek = $date->dayOfWeekIso; // 1 = Monday, 7 = Sunday

        if ($entry->day_of_week !== $dayOfWeek) {
            throw ValidationException::withMessages([
                'date' => "The selected date does not match the timetable entry scheduled day of week ({$entry->day_of_week}).",
            ]);
        }

        $substituteId = $data['substitute_employee_id'];
        $originalId = $entry->employee_id;

        if ($substituteId === $originalId) {
            throw ValidationException::withMessages([
                'substitute_employee_id' => 'Substitute teacher cannot be the same as the original teacher.',
            ]);
        }

        // 1. Conflict Check: Does substitute teacher already have their own scheduled class at this period?
        $hasOwnClass = TimetableEntry::where('employee_id', $substituteId)
            ->where('period_id', $entry->period_id)
            ->where('day_of_week', $dayOfWeek)
            ->exists();

        if ($hasOwnClass) {
            throw ValidationException::withMessages([
                'substitute_employee_id' => 'The substitute teacher already has a scheduled class during this period.',
            ]);
        }

        // 2. Conflict Check: Is substitute teacher already assigned as a substitute for another entry on this date & period?
        $hasAnotherSubstitution = TimetableSubstitution::where('substitute_employee_id', $substituteId)
            ->where('date', $date->toDateString())
            ->where('status', 'approved')
            ->whereHas('timetableEntry', fn ($q) => $q->where('period_id', $entry->period_id))
            ->exists();

        if ($hasAnotherSubstitution) {
            throw ValidationException::withMessages([
                'substitute_employee_id' => 'The substitute teacher is already covering another class during this period on this date.',
            ]);
        }

        return TimetableSubstitution::create([
            'tenant_id' => $entry->tenant_id,
            'branch_id' => $entry->branch_id,
            'timetable_entry_id' => $entry->id,
            'original_employee_id' => $originalId,
            'substitute_employee_id' => $substituteId,
            'date' => $date->toDateString(),
            'reason' => $data['reason'] ?? 'Leave substitution',
            'status' => 'approved',
        ]);
    }

    /**
     * Get attendance summary for teacher over specified date range / month.
     */
    public function getAttendanceSummary(Employee $teacher, ?string $month = null): array
    {
        $startDate = $month ? Carbon::parse($month)->startOfMonth() : Carbon::now()->startOfMonth();
        $endDate = $month ? Carbon::parse($month)->endOfMonth() : Carbon::now()->endOfMonth();

        $records = StaffAttendance::where('employee_id', $teacher->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        return [
            'total_days' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'half_day' => $records->where('status', 'half_day')->count(),
            'on_leave' => $records->where('status', 'on_leave')->count(),
            'recent_logs' => $records->sortByDesc('date')->take(10)->values(),
        ];
    }
}
