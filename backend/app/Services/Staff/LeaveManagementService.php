<?php

namespace App\Services\Staff;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveManagementService
{
    /**
     * List leave types configured for the branch.
     */
    public function getLeaveTypes(?string $branchId = null): Collection
    {
        $branchId = $branchId ?? app(TenantContext::class)->getBranchId();

        return LeaveType::where('branch_id', $branchId)->orderBy('name')->get();
    }

    /**
     * Create a new leave type.
     */
    public function createLeaveType(array $data): LeaveType
    {
        $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->getTenantId();
        $branchId = $data['branch_id'] ?? app(TenantContext::class)->getBranchId();

        return LeaveType::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'annual_quota' => $data['annual_quota'] ?? 12.0,
            'is_paid' => $data['is_paid'] ?? true,
            'is_carry_forward' => $data['is_carry_forward'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Get leave quotas and remaining balances for an employee.
     */
    public function getEmployeeBalances(Employee $employee, ?string $academicSessionId = null): Collection
    {
        if (!$academicSessionId) {
            $session = AcademicSession::where('branch_id', $employee->branch_id)
                ->where('is_current', true)
                ->first();
            $academicSessionId = $session?->id;
        }

        return EmployeeLeave::where('employee_id', $employee->id)
            ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
            ->get();
    }

    /**
     * Submit a staff leave application.
     */
    public function applyLeave(array $data): LeaveApplication
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date cannot be prior to start date.',
            ]);
        }

        // Compute requested working days (excluding Sundays)
        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = 0;
        foreach ($period as $date) {
            if (!$date->isSunday()) {
                $totalDays += 1;
            }
        }
        $totalDays = max(1.0, (float) ($data['total_days'] ?? $totalDays));

        // Check active session and leave balance
        $currentSession = AcademicSession::where('branch_id', $employee->branch_id)
            ->where('is_current', true)
            ->first();

        $leaveBalance = null;
        if ($currentSession) {
            $leaveBalance = EmployeeLeave::where('employee_id', $employee->id)
                ->where('academic_session_id', $currentSession->id)
                ->where('leave_type', strtoupper($data['leave_type']))
                ->first();

            if ($leaveBalance && $leaveBalance->balance_days < $totalDays) {
                throw ValidationException::withMessages([
                    'total_days' => "Insufficient leave balance. Requested {$totalDays} days, available balance is {$leaveBalance->balance_days} days.",
                ]);
            }
        }

        return LeaveApplication::create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'applicant_type' => 'employee',
            'applicant_id' => $employee->id,
            'leave_type' => strtoupper($data['leave_type']),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'medical_certificate_path' => $data['medical_certificate_path'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * Review (Approve or Reject) a leave application.
     */
    public function reviewApplication(LeaveApplication $application, string $status, ?string $remarks, string $approverId): LeaveApplication
    {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException("Invalid review status: {$status}");
        }

        return DB::transaction(function () use ($application, $status, $remarks, $approverId) {
            $application->update([
                'status' => $status,
                'approved_by' => $approverId,
            ]);

            // If approved and applicant is employee, deduct balance and mark attendance
            if ($status === 'approved' && $application->applicant_type === 'employee') {
                $employee = Employee::find($application->applicant_id);

                if ($employee) {
                    $currentSession = AcademicSession::where('branch_id', $application->branch_id)
                        ->where('is_current', true)
                        ->first();

                    if ($currentSession) {
                        $leaveRecord = EmployeeLeave::where('employee_id', $employee->id)
                            ->where('academic_session_id', $currentSession->id)
                            ->where('leave_type', $application->leave_type)
                            ->first();

                        if ($leaveRecord) {
                            $leaveRecord->increment('used_days', $application->total_days);
                            $leaveRecord->decrement('balance_days', $application->total_days);
                        }
                    }

                    // Populate attendance entries as 'on_leave' for non-Sundays in range
                    $startDate = Carbon::parse($application->start_date);
                    $endDate = Carbon::parse($application->end_date);
                    $period = CarbonPeriod::create($startDate, $endDate);

                    foreach ($period as $date) {
                        if (!$date->isSunday()) {
                            EmployeeAttendance::updateOrCreate(
                                [
                                    'tenant_id' => $application->tenant_id,
                                    'branch_id' => $application->branch_id,
                                    'employee_id' => $employee->id,
                                    'date' => $date->toDateString(),
                                ],
                                [
                                    'status' => 'on_leave',
                                    'source' => 'manual',
                                    'remarks' => "Approved {$application->leave_type} Leave",
                                ]
                            );
                        }
                    }
                }
            }

            return $application->load('approver');
        });
    }

    /**
     * List leave applications with pagination and filters.
     */
    public function listApplications(array $filters = []): LengthAwarePaginator
    {
        $branchId = $filters['branch_id'] ?? app(TenantContext::class)->getBranchId();

        $query = LeaveApplication::where('branch_id', $branchId)
            ->with(['approver'])
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['applicant_type'])) {
            $query->where('applicant_type', $filters['applicant_type']);
        }

        if (!empty($filters['leave_type'])) {
            $query->where('leave_type', strtoupper($filters['leave_type']));
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
