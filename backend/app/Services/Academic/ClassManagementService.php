<?php

namespace App\Services\Academic;

use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassManagementService
{
    /**
     * Create a new class ensuring uniqueness within the branch.
     */
    public function createClass(array $data, string $tenantId, string $schoolId, string $branchId): SchoolClass
    {
        $code = strtoupper(trim($data['code']));
        $name = trim($data['name']);

        $this->ensureUniqueClass($branchId, $name, $code);

        $orderIndex = $data['order_index'] ?? (SchoolClass::where('branch_id', $branchId)->max('order_index') + 1);

        return SchoolClass::create([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'class_level_id' => $data['class_level_id'] ?? null,
            'board_id' => $data['board_id'] ?? null,
            'name' => $name,
            'code' => $code,
            'order_index' => $orderIndex,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing class.
     */
    public function updateClass(SchoolClass $class, array $data): SchoolClass
    {
        $name = isset($data['name']) ? trim($data['name']) : $class->name;
        $code = isset($data['code']) ? strtoupper(trim($data['code'])) : $class->code;

        $this->ensureUniqueClass($class->branch_id, $name, $code, $class->id);

        $class->update($data);
        return $class->fresh();
    }

    /**
     * Reorder classes within a branch.
     */
    public function reorderClasses(string $branchId, array $orderMapping): void
    {
        DB::transaction(function () use ($branchId, $orderMapping) {
            foreach ($orderMapping as $classId => $orderIndex) {
                SchoolClass::where('branch_id', $branchId)
                    ->where('id', $classId)
                    ->update(['order_index' => (int) $orderIndex]);
            }
        });
    }

    /**
     * Create a section within a class ensuring unique name in that class.
     */
    public function createSection(SchoolClass $class, array $data): Section
    {
        $name = strtoupper(trim($data['name']));

        $exists = Section::where('class_id', $class->id)
            ->whereRaw('UPPER(name) = ?', [$name])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ["Section '{$name}' already exists in {$class->name}."],
            ]);
        }

        return Section::create([
            'tenant_id' => $class->tenant_id,
            'branch_id' => $class->branch_id,
            'class_id' => $class->id,
            'name' => $name,
            'room_number' => $data['room_number'] ?? null,
            'max_capacity' => $data['max_capacity'] ?? 40,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a section within a class.
     */
    public function updateSection(Section $section, array $data): Section
    {
        if (isset($data['name'])) {
            $name = strtoupper(trim($data['name']));
            $exists = Section::where('class_id', $section->class_id)
                ->where('id', '!=', $section->id)
                ->whereRaw('UPPER(name) = ?', [$name])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => ["Section '{$name}' already exists in this class."],
                ]);
            }
            $data['name'] = $name;
        }

        $section->update($data);
        return $section->fresh();
    }

    /**
     * Assign or replace a section's class teacher for an academic session.
     */
    public function assignClassTeacher(
        string $classId,
        string $sectionId,
        string $employeeId,
        string $academicSessionId,
        string $tenantId,
        string $branchId,
        bool $isPrimary = true
    ): ClassTeacher {
        return DB::transaction(function () use (
            $classId,
            $sectionId,
            $employeeId,
            $academicSessionId,
            $tenantId,
            $branchId,
            $isPrimary
        ) {
            if ($isPrimary) {
                // Remove or demote any existing primary teacher for this section in the session
                ClassTeacher::where('section_id', $sectionId)
                    ->where('academic_session_id', $academicSessionId)
                    ->delete();
            }

            return ClassTeacher::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'employee_id' => $employeeId,
                'academic_session_id' => $academicSessionId,
                'is_primary' => $isPrimary,
            ]);
        });
    }

    /**
     * Ensure unique class name and code within branch.
     */
    protected function ensureUniqueClass(string $branchId, string $name, string $code, ?string $excludeId = null): void
    {
        $nameQuery = SchoolClass::where('branch_id', $branchId)
            ->whereRaw('UPPER(name) = ?', [strtoupper($name)]);

        $codeQuery = SchoolClass::where('branch_id', $branchId)
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)]);

        if ($excludeId) {
            $nameQuery->where('id', '!=', $excludeId);
            $codeQuery->where('id', '!=', $excludeId);
        }

        if ($nameQuery->exists()) {
            throw ValidationException::withMessages([
                'name' => ["A class named '{$name}' already exists in this branch."],
            ]);
        }

        if ($codeQuery->exists()) {
            throw ValidationException::withMessages([
                'code' => ["A class with code '{$code}' already exists in this branch."],
            ]);
        }
    }
}

