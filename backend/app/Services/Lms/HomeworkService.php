<?php

namespace App\Services\Lms;

use App\Models\Employee;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class HomeworkService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Validate that the teacher is authorized to assign homework for this class and subject.
     */
    public function validateTeacherAccess(User $user, string $classId, string $subjectId): ?Employee
    {
        $employee = Employee::where('user_id', $user->id)->first();

        if ($user->hasAnyRole(['platform_admin', 'school_owner', 'school_admin', 'principal'])) {
            return $employee;
        }

        if (!$employee) {
            throw new AuthorizationException('User has no active staff or teacher profile.');
        }

        $isAssigned = TeacherSubject::where('employee_id', $employee->id)
            ->whereHas('classSubject', function ($query) use ($classId, $subjectId) {
                $query->where('class_id', $classId)
                    ->where('subject_id', $subjectId);
            })
            ->exists();

        if (!$isAssigned) {
            throw new AuthorizationException('You are not assigned as a teacher for this class and subject.');
        }

        return $employee;
    }

    /**
     * Create homework assignment.
     */
    public function createHomework(array $data, User $user): Homework
    {
        $classId = $data['class_id'];
        $subjectId = $data['subject_id'];

        $employee = $this->validateTeacherAccess($user, $classId, $subjectId);
        $teacherId = $data['teacher_id'] ?? $employee?->id;

        if (!$teacherId) {
            $fallbackEmployee = Employee::where('branch_id', $this->tenantContext->getBranchId())->first();
            $teacherId = $fallbackEmployee?->id;
        }

        if (!$teacherId) {
            throw new InvalidArgumentException('A valid teacher or employee profile is required to assign homework.');
        }

        if (strtotime($data['due_date']) < strtotime($data['assigned_date'])) {
            throw new InvalidArgumentException('Due date cannot be earlier than assigned date.');
        }

        return Homework::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $this->tenantContext->getBranchId(),
            'class_id' => $classId,
            'section_id' => $data['section_id'] ?? null,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'title' => $data['title'],
            'description' => $data['description'],
            'assigned_date' => $data['assigned_date'],
            'due_date' => $data['due_date'],
            'max_marks' => $data['max_marks'] ?? null,
            'attachments' => $data['attachments'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update existing homework.
     */
    public function updateHomework(string $id, array $data, User $user): Homework
    {
        $homework = Homework::findOrFail($id);

        $this->validateTeacherAccess($user, $homework->class_id, $homework->subject_id);

        if (isset($data['due_date']) && isset($data['assigned_date'])) {
            if (strtotime($data['due_date']) < strtotime($data['assigned_date'])) {
                throw new InvalidArgumentException('Due date cannot be earlier than assigned date.');
            }
        }

        $homework->update(array_intersect_key($data, array_flip([
            'title', 'description', 'assigned_date', 'due_date', 'max_marks', 'attachments', 'is_active', 'section_id'
        ])));

        return $homework->fresh(['schoolClass', 'section', 'subject', 'teacher']);
    }

    /**
     * Delete homework.
     */
    public function deleteHomework(string $id, User $user): bool
    {
        $homework = Homework::findOrFail($id);
        $this->validateTeacherAccess($user, $homework->class_id, $homework->subject_id);
        return (bool) $homework->delete();
    }

    /**
     * List homework with filters.
     */
    public function listHomework(array $filters = []): Collection
    {
        $query = Homework::with(['schoolClass', 'section', 'subject', 'teacher.user'])
            ->withCount('submissions')
            ->orderBy('due_date', 'desc');

        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['section_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('section_id', $filters['section_id'])
                  ->orWhereNull('section_id');
            });
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    /**
     * Get single homework with relations.
     */
    public function getHomework(string $id): Homework
    {
        return Homework::with(['schoolClass', 'section', 'subject', 'teacher.user', 'submissions.student'])
            ->findOrFail($id);
    }
}

