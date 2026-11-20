<?php

namespace App\Services\Examination;

use App\Models\GradeRange;
use App\Models\GradingScheme;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GradingSchemeService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * List all grading schemes for current school.
     */
    public function listSchemes(?string $schoolId = null): Collection
    {
        $schoolId = $schoolId ?? $this->tenantContext->getSchoolId();

        return GradingScheme::with('ranges')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a grading scheme by ID with ranges.
     */
    public function getScheme(string $id): GradingScheme
    {
        return GradingScheme::with('ranges')->findOrFail($id);
    }

    /**
     * Create a new grading scheme with grade ranges.
     */
    public function createScheme(array $data): GradingScheme
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $this->tenantContext->getTenantId();
            $schoolId = $data['school_id'] ?? $this->tenantContext->getSchoolId();

            $scheme = GradingScheme::create([
                'tenant_id' => $tenantId,
                'school_id' => $schoolId,
                'name' => $data['name'],
                'scheme_type' => $data['scheme_type'] ?? 'letter',
                'description' => $data['description'] ?? null,
            ]);

            $ranges = $data['ranges'] ?? [];
            foreach ($ranges as $range) {
                GradeRange::create([
                    'grading_scheme_id' => $scheme->id,
                    'grade_name' => $range['grade_name'],
                    'min_percentage' => round((float) $range['min_percentage'], 2),
                    'max_percentage' => round((float) $range['max_percentage'], 2),
                    'grade_point' => isset($range['grade_point']) ? round((float) $range['grade_point'], 2) : null,
                    'description' => $range['description'] ?? null,
                ]);
            }

            return $scheme->load('ranges');
        });
    }

    /**
     * Determine grade, grade point and description for a given percentage.
     */
    public function calculateGrade(GradingScheme $scheme, float $percentage): array
    {
        $percentage = round($percentage, 2);

        foreach ($scheme->ranges as $range) {
            $min = (float) $range->min_percentage;
            $max = (float) $range->max_percentage;

            if ($percentage >= $min && $percentage <= $max) {
                return [
                    'grade' => $range->grade_name,
                    'grade_point' => $range->grade_point !== null ? (float) $range->grade_point : null,
                    'description' => $range->description,
                ];
            }
        }

        // Fallback for edge or failing marks
        return [
            'grade' => 'F',
            'grade_point' => 0.00,
            'description' => 'Needs Improvement / Fail',
        ];
    }
}

