<?php

namespace App\Services\Examination;

use App\Models\AssessmentComponent;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\ExamSchedule;
use App\Models\ExamSubject;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExaminationService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * List all examination types for current school.
     */
    public function listTypes(?string $schoolId = null): Collection
    {
        $schoolId = $schoolId ?? $this->tenantContext->getSchoolId();

        return ExaminationType::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create examination type.
     */
    public function createType(array $data): ExaminationType
    {
        $tenantId = $this->tenantContext->getTenantId();
        $schoolId = $data['school_id'] ?? $this->tenantContext->getSchoolId();

        return ExaminationType::create([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'name' => $data['name'],
            'weightage_percent' => round((float) ($data['weightage_percent'] ?? 100.00), 2),
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * List examinations with filters.
     */
    public function listExaminations(array $filters = []): Collection
    {
        $branchId = $this->tenantContext->getBranchId();

        return Examination::with(['examType', 'gradingScheme.ranges', 'academicSession'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(isset($filters['academic_session_id']), fn ($q) => $q->where('academic_session_id', $filters['academic_session_id']))
            ->when(isset($filters['exam_type_id']), fn ($q) => $q->where('exam_type_id', $filters['exam_type_id']))
            ->when(isset($filters['is_published']), fn ($q) => $q->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Get an examination by ID with relationships.
     */
    public function getExamination(string $id): Examination
    {
        return Examination::with([
            'examType',
            'gradingScheme.ranges',
            'academicSession',
            'schedules.schoolClass',
            'schedules.subject',
            'schedules.components',
            'publications.schoolClass',
        ])->findOrFail($id);
    }

    /**
     * Create master examination session.
     */
    public function createExamination(array $data): Examination
    {
        $tenantId = $this->tenantContext->getTenantId();
        $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();

        return Examination::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_session_id' => $data['academic_session_id'],
            'exam_type_id' => $data['exam_type_id'],
            'grading_scheme_id' => $data['grading_scheme_id'],
            'title' => $data['title'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_published' => false,
        ]);
    }

    /**
     * Update an examination.
     */
    public function updateExamination(string $id, array $data): Examination
    {
        $exam = Examination::findOrFail($id);
        $exam->update($data);
        return $exam->fresh(['examType', 'gradingScheme', 'academicSession']);
    }

    /**
     * Schedule a subject exam for a class with maximum & pass marks and components.
     */
    public function scheduleExam(array $data): ExamSchedule
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $this->tenantContext->getTenantId();
            $examId = $data['examination_id'];
            $classId = $data['class_id'];
            $subjectId = $data['subject_id'];

            // Check duplicate schedule
            $existing = ExamSchedule::where('examination_id', $examId)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException('Subject is already scheduled for this class in this examination.');
            }

            $maxMarks = round((float) $data['max_marks'], 2);
            $passMarks = round((float) $data['pass_marks'], 2);

            if ($passMarks > $maxMarks) {
                throw new InvalidArgumentException('Pass marks cannot exceed maximum marks.');
            }

            $schedule = ExamSchedule::create([
                'tenant_id' => $tenantId,
                'examination_id' => $examId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'exam_date' => $data['exam_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'room_number' => $data['room_number'] ?? null,
                'max_marks' => $maxMarks,
                'pass_marks' => $passMarks,
            ]);

            // Create assessment components if provided
            if (!empty($data['components']) && is_array($data['components'])) {
                foreach ($data['components'] as $component) {
                    AssessmentComponent::create([
                        'tenant_id' => $tenantId,
                        'exam_schedule_id' => $schedule->id,
                        'component_name' => $component['component_name'],
                        'max_marks' => round((float) $component['max_marks'], 2),
                        'pass_marks' => round((float) ($component['pass_marks'] ?? 0), 2),
                    ]);
                }
            }

            // Create or update ExamSubject theory/practical breakdown
            if (isset($data['max_theory_marks']) || isset($data['max_practical_marks'])) {
                ExamSubject::updateOrCreate(
                    [
                        'examination_id' => $examId,
                        'subject_id' => $subjectId,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'max_theory_marks' => round((float) ($data['max_theory_marks'] ?? $maxMarks), 2),
                        'max_practical_marks' => round((float) ($data['max_practical_marks'] ?? 0), 2),
                        'pass_percentage' => round((float) ($data['pass_percentage'] ?? 33.00), 2),
                    ]
                );
            }

            return $schedule->load(['schoolClass', 'subject', 'components']);
        });
    }

    /**
     * List schedules for an examination.
     */
    public function listSchedules(string $examinationId, ?string $classId = null): Collection
    {
        return ExamSchedule::with(['schoolClass', 'subject', 'components'])
            ->where('examination_id', $examinationId)
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get a single schedule with details and marks.
     */
    public function getSchedule(string $scheduleId): ExamSchedule
    {
        return ExamSchedule::with([
            'examination',
            'schoolClass',
            'subject',
            'components',
            'marks.student',
            'marks.assessments.component',
        ])->findOrFail($scheduleId);
    }
}

