<?php

namespace App\Services\Academic;

use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\TimetableSubstitution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    /**
     * Create a master timetable schedule.
     */
    public function createTimetable(array $data, string $tenantId, string $branchId): Timetable
    {
        return Timetable::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_session_id' => $data['academic_session_id'],
            'name' => trim($data['name']),
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Configure periods (and breaks) for a timetable.
     */
    public function configurePeriods(Timetable $timetable, array $periods): array
    {
        return DB::transaction(function () use ($timetable, $periods) {
            $createdPeriods = [];

            foreach ($periods as $pData) {
                $startTime = $pData['start_time'];
                $endTime = $pData['end_time'];

                if ($startTime >= $endTime) {
                    throw ValidationException::withMessages([
                        'periods' => ["Period '{$pData['period_name']}' has invalid start/end times."],
                    ]);
                }

                $period = TimetablePeriod::updateOrCreate(
                    [
                        'timetable_id' => $timetable->id,
                        'period_number' => (int) $pData['period_number'],
                    ],
                    [
                        'tenant_id' => $timetable->tenant_id,
                        'branch_id' => $timetable->branch_id,
                        'period_name' => trim($pData['period_name']),
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'is_break' => !empty($pData['is_break']),
                    ]
                );

                $createdPeriods[] = $period;
            }

            return $createdPeriods;
        });
    }

    /**
     * Conflict Detection Engine
     * Verifies that neither the teacher, section, nor room are double-booked.
     */
    public function detectSlotConflicts(array $slotData, ?string $excludeEntryId = null): array
    {
        $timetableId = $slotData['timetable_id'];
        $periodId = $slotData['period_id'];
        $dayOfWeek = (int) $slotData['day_of_week'];
        $employeeId = $slotData['employee_id'];
        $sectionId = $slotData['section_id'];
        $roomNumber = !empty($slotData['room_number']) ? trim($slotData['room_number']) : null;

        $conflicts = [];

        // 1. Check if period is a break
        $period = TimetablePeriod::find($periodId);
        if ($period && $period->is_break) {
            $conflicts[] = "Cannot schedule an academic slot during a break period ({$period->period_name}).";
        }

        // 2. Check Teacher Double-Booking
        $teacherClash = TimetableEntry::with(['schoolClass', 'section', 'period'])
            ->where('timetable_id', $timetableId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->where('employee_id', $employeeId);

        if ($excludeEntryId) {
            $teacherClash->where('id', '!=', $excludeEntryId);
        }

        $clashingTeacherEntry = $teacherClash->first();
        if ($clashingTeacherEntry) {
            $className = $clashingTeacherEntry->schoolClass?->name ?? 'Class';
            $sectionName = $clashingTeacherEntry->section?->name ?? 'Section';
            $periodName = $clashingTeacherEntry->period?->period_name ?? "Period {$periodId}";
            $conflicts[] = "Teacher is already scheduled to teach {$className} ({$sectionName}) during {$periodName}.";
        }

        // 3. Check Section Double-Booking
        $sectionClash = TimetableEntry::with(['subject', 'period'])
            ->where('timetable_id', $timetableId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->where('section_id', $sectionId);

        if ($excludeEntryId) {
            $sectionClash->where('id', '!=', $excludeEntryId);
        }

        $clashingSectionEntry = $sectionClash->first();
        if ($clashingSectionEntry) {
            $subjectName = $clashingSectionEntry->subject?->name ?? 'Subject';
            $conflicts[] = "Section already has {$subjectName} scheduled during this period.";
        }

        // 4. Check Room Double-Booking
        if ($roomNumber) {
            $roomClash = TimetableEntry::with(['schoolClass', 'section'])
                ->where('timetable_id', $timetableId)
                ->where('day_of_week', $dayOfWeek)
                ->where('period_id', $periodId)
                ->whereRaw('UPPER(room_number) = ?', [strtoupper($roomNumber)]);

            if ($excludeEntryId) {
                $roomClash->where('id', '!=', $excludeEntryId);
            }

            $clashingRoomEntry = $roomClash->first();
            if ($clashingRoomEntry) {
                $className = $clashingRoomEntry->schoolClass?->name ?? 'Class';
                $sectionName = $clashingRoomEntry->section?->name ?? 'Section';
                $conflicts[] = "Room {$roomNumber} is already occupied by {$className} ({$sectionName}) in this period.";
            }
        }

        return [
            'has_conflict' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Assign a timetable slot enforcing conflict resolution.
     */
    public function assignSlot(Timetable $timetable, array $data, ?string $excludeEntryId = null): TimetableEntry
    {
        $slotData = array_merge($data, ['timetable_id' => $timetable->id]);

        $conflictResult = $this->detectSlotConflicts($slotData, $excludeEntryId);

        if ($conflictResult['has_conflict']) {
            throw ValidationException::withMessages([
                'timetable_conflict' => $conflictResult['conflicts'],
            ]);
        }

        return TimetableEntry::updateOrCreate(
            [
                'timetable_id' => $timetable->id,
                'period_id' => $data['period_id'],
                'section_id' => $data['section_id'],
                'day_of_week' => (int) $data['day_of_week'],
            ],
            [
                'tenant_id' => $timetable->tenant_id,
                'branch_id' => $timetable->branch_id,
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'employee_id' => $data['employee_id'],
                'room_number' => !empty($data['room_number']) ? trim($data['room_number']) : null,
            ]
        );
    }

    /**
     * Assign a substitute teacher with availability and conflict validation.
     */
    public function assignSubstitute(
        TimetableEntry $entry,
        string $substituteEmployeeId,
        string $date,
        ?string $reason = null
    ): TimetableSubstitution {
        if ($entry->employee_id === $substituteEmployeeId) {
            throw ValidationException::withMessages([
                'substitute_employee_id' => ['Substitute teacher cannot be the original assigned teacher.'],
            ]);
        }

        $carbonDate = Carbon::parse($date);
        $dayOfWeek = (int) $carbonDate->dayOfWeekIso; // 1 = Mon, 7 = Sun

        // 1. Check if substitute teacher is already busy with their own class during this period
        $hasOwnClass = TimetableEntry::where('timetable_id', $entry->timetable_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $entry->period_id)
            ->where('employee_id', $substituteEmployeeId)
            ->exists();

        if ($hasOwnClass) {
            throw ValidationException::withMessages([
                'substitute_employee_id' => ['The selected substitute teacher is already teaching their own class in this period.'],
            ]);
        }

        // 2. Check if substitute teacher is already assigned as a substitute for another class on this date & period
        $hasOtherSub = TimetableSubstitution::where('date', $date)
            ->where('substitute_employee_id', $substituteEmployeeId)
            ->where('status', 'approved')
            ->whereHas('entry', function ($q) use ($entry) {
                $q->where('period_id', $entry->period_id);
            })
            ->exists();

        if ($hasOtherSub) {
            throw ValidationException::withMessages([
                'substitute_employee_id' => ['The substitute teacher is already filling in for another class during this period on this date.'],
            ]);
        }

        return TimetableSubstitution::updateOrCreate(
            [
                'timetable_entry_id' => $entry->id,
                'date' => $date,
            ],
            [
                'tenant_id' => $entry->tenant_id,
                'branch_id' => $entry->branch_id,
                'original_employee_id' => $entry->employee_id,
                'substitute_employee_id' => $substituteEmployeeId,
                'reason' => $reason,
                'status' => 'approved',
            ]
        );
    }
}

