<?php

namespace App\Services\Examination;

use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\StudentMark;
use App\Services\Tenancy\TenantContext;

class ResultAnalyticsService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Class overall performance metrics and grade distribution.
     */
    public function getClassPerformanceSummary(string $examinationId, string $classId): array
    {
        $results = ExamResult::with('student')
            ->where('examination_id', $examinationId)
            ->whereHas('enrollment', fn ($q) => $q->where('class_id', $classId))
            ->get();

        $totalAppeared = $results->count();
        if ($totalAppeared === 0) {
            return [
                'total_appeared' => 0,
                'total_passed' => 0,
                'total_failed' => 0,
                'pass_percentage' => 0.00,
                'class_average' => 0.00,
                'highest_percentage' => 0.00,
                'lowest_percentage' => 0.00,
                'grade_distribution' => [],
            ];
        }

        $passed = $results->where('result_status', 'passed')->count();
        $failed = $totalAppeared - $passed;
        $passPercentage = round(($passed / $totalAppeared) * 100, 2);

        $averagePercentage = round((float) $results->avg('percentage'), 2);
        $highestPercentage = round((float) $results->max('percentage'), 2);
        $lowestPercentage = round((float) $results->min('percentage'), 2);

        $gradeDistribution = $results->groupBy('grade')->map(function ($items, $grade) use ($totalAppeared) {
            return [
                'grade' => $grade ?: 'Ungraded',
                'count' => $items->count(),
                'percentage' => round(($items->count() / $totalAppeared) * 100, 2),
            ];
        })->values();

        return [
            'total_appeared' => $totalAppeared,
            'total_passed' => $passed,
            'total_failed' => $failed,
            'pass_percentage' => $passPercentage,
            'class_average' => $averagePercentage,
            'highest_percentage' => $highestPercentage,
            'lowest_percentage' => $lowestPercentage,
            'grade_distribution' => $gradeDistribution,
        ];
    }

    /**
     * Top N ranked students in the class.
     */
    public function getToppersReport(string $examinationId, string $classId, int $limit = 3): array
    {
        return ExamResult::with(['student.user', 'enrollment.section'])
            ->where('examination_id', $examinationId)
            ->whereHas('enrollment', fn ($q) => $q->where('class_id', $classId))
            ->orderBy('rank', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($res) {
                return [
                    'rank' => $res->rank,
                    'student_id' => $res->student_id,
                    'name' => $res->student->first_name . ' ' . $res->student->last_name,
                    'admission_number' => $res->student->admission_number,
                    'section' => $res->enrollment?->section?->name ?? 'A',
                    'total_marks' => (float) $res->total_marks,
                    'marks_obtained' => (float) $res->marks_obtained,
                    'percentage' => (float) $res->percentage,
                    'grade' => $res->grade,
                ];
            })
            ->toArray();
    }

    /**
     * Subject-wise performance metrics for a class.
     */
    public function getSubjectWisePerformance(string $examinationId, string $classId): array
    {
        $schedules = ExamSchedule::with(['subject', 'marks'])
            ->where('examination_id', $examinationId)
            ->where('class_id', $classId)
            ->get();

        $metrics = [];

        foreach ($schedules as $sched) {
            $marks = $sched->marks;
            $appeared = $marks->where('is_absent', false)->count();
            $passed = $marks->filter(fn ($m) => !$m->is_absent && (float) $m->marks_obtained >= (float) $sched->pass_marks)->count();

            $avgMarks = $appeared > 0 ? round((float) $marks->where('is_absent', false)->avg('marks_obtained'), 2) : 0.00;
            $highestScore = $appeared > 0 ? (float) $marks->where('is_absent', false)->max('marks_obtained') : 0.00;
            $passPercent = $appeared > 0 ? round(($passed / $appeared) * 100, 2) : 0.00;

            $metrics[] = [
                'subject_id' => $sched->subject_id,
                'subject_name' => $sched->subject->name,
                'subject_code' => $sched->subject->code ?? '',
                'max_marks' => (float) $sched->max_marks,
                'pass_marks' => (float) $sched->pass_marks,
                'students_appeared' => $appeared,
                'students_passed' => $passed,
                'pass_percentage' => $passPercent,
                'average_marks' => $avgMarks,
                'highest_marks' => $highestScore,
            ];
        }

        return $metrics;
    }

    /**
     * Student's historical examination results comparison across sessions/terms.
     */
    public function getStudentHistoricalComparison(string $studentId): array
    {
        return ExamResult::with(['examination.examType', 'examination.academicSession'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($res) {
                return [
                    'examination_id' => $res->examination_id,
                    'title' => $res->examination->title,
                    'exam_type' => $res->examination->examType?->name ?? 'Standard',
                    'academic_session' => $res->examination->academicSession?->name ?? 'N/A',
                    'marks_obtained' => (float) $res->marks_obtained,
                    'total_marks' => (float) $res->total_marks,
                    'percentage' => (float) $res->percentage,
                    'grade' => $res->grade,
                    'rank' => $res->rank,
                    'result_status' => $res->result_status,
                ];
            })
            ->toArray();
    }
}

