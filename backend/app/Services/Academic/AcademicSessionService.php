<?php

namespace App\Services\Academic;

use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicSessionService
{
    /**
     * Create an academic session with strict overlap checking.
     */
    public function createSession(array $data, string $tenantId, string $schoolId, string $branchId): AcademicSession
    {
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];

        if ($startDate >= $endDate) {
            throw ValidationException::withMessages([
                'end_date' => ['The end date must be after the start date.'],
            ]);
        }

        // Prevent invalid academic session overlaps within the branch
        $this->ensureNoDateOverlap($branchId, $startDate, $endDate);

        return DB::transaction(function () use ($data, $tenantId, $schoolId, $branchId) {
            $isCurrent = !empty($data['is_current']);

            if ($isCurrent) {
                AcademicSession::where('branch_id', $branchId)->update(['is_current' => false]);
            }

            return AcademicSession::create([
                'tenant_id' => $tenantId,
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'name' => trim($data['name']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => $isCurrent,
            ]);
        });
    }

    /**
     * Update an academic session.
     */
    public function updateSession(AcademicSession $session, array $data): AcademicSession
    {
        $startDate = $data['start_date'] ?? $session->start_date->format('Y-m-d');
        $endDate = $data['end_date'] ?? $session->end_date->format('Y-m-d');

        if ($startDate >= $endDate) {
            throw ValidationException::withMessages([
                'end_date' => ['The end date must be after the start date.'],
            ]);
        }

        if (isset($data['start_date']) || isset($data['end_date'])) {
            $this->ensureNoDateOverlap($session->branch_id, $startDate, $endDate, $session->id);
        }

        return DB::transaction(function () use ($session, $data) {
            if (!empty($data['is_current']) && !$session->is_current) {
                AcademicSession::where('branch_id', $session->branch_id)
                    ->where('id', '!=', $session->id)
                    ->update(['is_current' => false]);
            }

            $session->update($data);
            return $session->fresh();
        });
    }

    /**
     * Activate an academic session atomically.
     */
    public function activateSession(AcademicSession $session): AcademicSession
    {
        return DB::transaction(function () use ($session) {
            AcademicSession::where('branch_id', $session->branch_id)
                ->where('id', '!=', $session->id)
                ->update(['is_current' => false]);

            $session->update(['is_current' => true]);
            return $session->fresh();
        });
    }

    /**
     * Close an academic session (demote to historical).
     */
    public function closeSession(AcademicSession $session): AcademicSession
    {
        $session->update(['is_current' => false]);
        return $session->fresh();
    }

    /**
     * Get historical / closed academic sessions with statistics.
     */
    public function getHistoricalSessions(string $branchId): Collection
    {
        return AcademicSession::where('branch_id', $branchId)
            ->where('is_current', false)
            ->withCount(['enrollments', 'timetables', 'feeStructures', 'examinations'])
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Ensure no date range overlap exists within the same branch.
     */
    protected function ensureNoDateOverlap(string $branchId, string $startDate, string $endDate, ?string $excludeId = null): void
    {
        $query = AcademicSession::where('branch_id', $branchId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => ['The specified dates overlap with an existing academic session in this branch.'],
            ]);
        }
    }
}

