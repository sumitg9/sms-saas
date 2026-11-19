<?php

namespace App\Services\Academic;

use App\Models\AcademicCalendar;
use App\Models\AcademicEvent;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AcademicCalendarService
{
    /**
     * Create an academic calendar header.
     */
    public function createCalendar(array $data, string $tenantId, string $branchId): AcademicCalendar
    {
        return AcademicCalendar::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_session_id' => $data['academic_session_id'],
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Add an event or holiday to the calendar.
     */
    public function addEvent(AcademicCalendar $calendar, array $data): AcademicEvent
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        if ($startDate->isAfter($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['The event end date cannot be earlier than the start date.'],
            ]);
        }

        $eventType = $data['event_type'] ?? 'event';
        $isHoliday = $data['is_holiday'] ?? ($eventType === 'holiday' || $eventType === 'vacation');

        return AcademicEvent::create([
            'tenant_id' => $calendar->tenant_id,
            'branch_id' => $calendar->branch_id,
            'calendar_id' => $calendar->id,
            'title' => trim($data['title']),
            'event_type' => $eventType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_holiday' => $isHoliday,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update an existing event.
     */
    public function updateEvent(AcademicEvent $event, array $data): AcademicEvent
    {
        $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : $event->start_date;
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : $event->end_date;

        if ($startDate->isAfter($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['The event end date cannot be earlier than the start date.'],
            ]);
        }

        $event->update($data);
        return $event->fresh();
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(AcademicEvent $event): bool
    {
        return (bool) $event->delete();
    }

    /**
     * Get summary metrics for the calendar.
     */
    public function getCalendarSummary(AcademicCalendar $calendar): array
    {
        $events = $calendar->events()->get();

        $holidayCount = $events->where('is_holiday', true)->count();
        $examCount = $events->where('event_type', 'exam')->count();
        $vacationCount = $events->where('event_type', 'vacation')->count();

        return [
            'total_events' => $events->count(),
            'holidays' => $holidayCount,
            'examinations' => $examCount,
            'vacations' => $vacationCount,
            'events' => $events,
        ];
    }
}

