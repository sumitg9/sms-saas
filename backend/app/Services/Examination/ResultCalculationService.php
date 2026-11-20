<?php

namespace App\Services\Examination;

use App\Models\Examination;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ResultPublication;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResultCalculationService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected GradingSchemeService $gradingService
    ) {}

    /**
     * Calculate and compile consolidated exam results and ranks for a class.
     */
    public function calculateClassResults(string $examinationId, string $classId): Collection
    {
        return DB::transaction(function () use ($examinationId, $classId) {
            $tenantId = $this->tenantContext->getTenantId();
            $exam = Examination::with('gradingScheme.ranges')->findOrFail($examinationId);
            $gradingScheme = $exam->gradingScheme;

            // Get all schedules for this class in this exam
            $schedules = ExamSchedule::where('examination_id', $examinationId)
                ->where('class_id', $classId)
                ->get();

            if ($schedules->isEmpty()) {
                throw new InvalidArgumentException('No exam schedules found for this class and examination.');
            }

            $totalMaxMarks = (float) $schedules->sum('max_marks');
            if ($totalMaxMarks <= 0) {
                throw new InvalidArgumentException('Total maximum marks cannot be zero.');
            }

            // Get all active students enrolled in this class
            $enrollments = StudentEnrollment::with('student')
                ->where('class_id', $classId)
                ->where('status', 'active')
                ->get();

            $results = [];

            foreach ($enrollments as $enrollment) {
                $studentId = $enrollment->student_id;
                $studentTotalObtained = 0.00;
                $hasFailedAnySubject = false;

                foreach ($schedules as $schedule) {
                    $mark = StudentMark::where('exam_schedule_id', $schedule->id)
                        ->where('enrollment_id', $enrollment->id)
                        ->first();

                    if (!$mark || $mark->is_absent) {
                        $hasFailedAnySubject = true;
                    } else {
                        $score = (float) $mark->marks_obtained;
                        $studentTotalObtained += $score;
                        if ($score < (float) $schedule->pass_marks) {
                            $hasFailedAnySubject = true;
                        }
                    }
                }

                $percentage = round(($studentTotalObtained / $totalMaxMarks) * 100, 2);

                // Calculate grade and grade point via grading engine
                $gradeInfo = $this->gradingService->calculateGrade($gradingScheme, $percentage);

                $resultStatus = $hasFailedAnySubject ? 'failed' : 'passed';

                $result = ExamResult::updateOrCreate(
                    [
                        'examination_id' => $examinationId,
                        'enrollment_id' => $enrollment->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'branch_id' => $exam->branch_id,
                        'student_id' => $studentId,
                        'total_marks' => $totalMaxMarks,
                        'marks_obtained' => round($studentTotalObtained, 2),
                        'percentage' => $percentage,
                        'grade' => $gradeInfo['grade'],
                        'grade_point' => $gradeInfo['grade_point'],
                        'result_status' => $resultStatus,
                    ]
                );

                $results[] = $result;
            }

            // Compute Class Rankings based on descending marks obtained / percentage
            usort($results, function ($a, $b) {
                return $b->marks_obtained <=> $a->marks_obtained;
            });

            $currentRank = 1;
            foreach ($results as $index => $res) {
                // Determine rank (handle ties if same percentage)
                if ($index > 0 && $res->marks_obtained == $results[$index - 1]->marks_obtained) {
                    $res->rank = $results[$index - 1]->rank;
                } else {
                    $res->rank = $currentRank;
                }
                $res->save();
                $currentRank++;
            }

            return ExamResult::with(['student', 'enrollment.schoolClass', 'enrollment.section'])
                ->where('examination_id', $examinationId)
                ->whereHas('enrollment', fn ($q) => $q->where('class_id', $classId))
                ->orderBy('rank', 'asc')
                ->get();
        });
    }

    /**
     * Publish results for a class.
     */
    public function publishResults(string $examinationId, string $classId, User $publisher, array $options = []): ResultPublication
    {
        return DB::transaction(function () use ($examinationId, $classId, $publisher, $options) {
            $tenantId = $this->tenantContext->getTenantId();
            $exam = Examination::findOrFail($examinationId);

            $publication = ResultPublication::updateOrCreate(
                [
                    'examination_id' => $examinationId,
                    'class_id' => $classId,
                ],
                [
                    'tenant_id' => $tenantId,
                    'published_by' => $publisher->id,
                    'published_at' => Carbon::now(),
                    'allow_parent_view' => $options['allow_parent_view'] ?? true,
                    'allow_student_view' => $options['allow_student_view'] ?? true,
                ]
            );

            // Mark master exam as published
            $exam->update([
                'is_published' => true,
                'published_at' => Carbon::now(),
            ]);

            return $publication->load(['examination', 'schoolClass', 'publisher']);
        });
    }
}

