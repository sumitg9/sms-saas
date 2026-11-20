<?php

namespace App\Services\Examination;

use App\Models\Examination;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ReportCard;
use App\Models\ReportCardTemplate;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReportCardService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected GradingSchemeService $gradingService
    ) {}

    /**
     * List report card templates.
     */
    public function listTemplates(?string $schoolId = null): Collection
    {
        $schoolId = $schoolId ?? $this->tenantContext->getSchoolId();

        return ReportCardTemplate::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('is_active', true)
            ->get();
    }

    /**
     * Create a report card template.
     */
    public function createTemplate(array $data): ReportCardTemplate
    {
        $tenantId = $this->tenantContext->getTenantId();
        $schoolId = $data['school_id'] ?? $this->tenantContext->getSchoolId();

        return ReportCardTemplate::create([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'name' => $data['name'],
            'template_schema' => $data['template_schema'] ?? [
                'show_school_logo' => true,
                'show_student_photo' => true,
                'show_attendance' => true,
                'show_ranking' => true,
                'show_grades' => true,
                'show_teacher_remarks' => true,
                'show_signatures' => true,
            ],
            'header_html' => $data['header_html'] ?? null,
            'footer_html' => $data['footer_html'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Generate student report card payload and record.
     */
    public function generateStudentReportCard(
        string $examinationId,
        string $studentId,
        ?string $templateId = null,
        array $options = []
    ): array {
        return DB::transaction(function () use ($examinationId, $studentId, $templateId, $options) {
            $tenantId = $this->tenantContext->getTenantId();
            $exam = Examination::with(['gradingScheme.ranges', 'academicSession'])->findOrFail($examinationId);
            $student = Student::with(['user', 'guardians'])->findOrFail($studentId);

            $enrollment = StudentEnrollment::with(['schoolClass', 'section'])
                ->where('student_id', $studentId)
                ->where('academic_session_id', $exam->academic_session_id)
                ->first();

            if (!$enrollment) {
                $enrollment = StudentEnrollment::with(['schoolClass', 'section'])
                    ->where('student_id', $studentId)
                    ->latest()
                    ->firstOrFail();
            }

            // Get all schedules for the student's class
            $schedules = ExamSchedule::with(['subject', 'components'])
                ->where('examination_id', $examinationId)
                ->where('class_id', $enrollment->class_id)
                ->get();

            // Subject marks breakdown
            $subjectResults = [];
            foreach ($schedules as $sched) {
                $mark = StudentMark::with('assessments.component')
                    ->where('exam_schedule_id', $sched->id)
                    ->where('enrollment_id', $enrollment->id)
                    ->first();

                // Compute class highest score for this subject
                $classHighest = (float) StudentMark::where('exam_schedule_id', $sched->id)->max('marks_obtained');

                $obtained = $mark && !$mark->is_absent ? (float) $mark->marks_obtained : 0.00;
                $maxMarks = (float) $sched->max_marks;
                $subjectPercent = $maxMarks > 0 ? round(($obtained / $maxMarks) * 100, 2) : 0.00;
                $gradeInfo = $this->gradingService->calculateGrade($exam->gradingScheme, $subjectPercent);

                $subjectResults[] = [
                    'subject_id' => $sched->subject_id,
                    'subject_name' => $sched->subject->name,
                    'subject_code' => $sched->subject->code ?? '',
                    'max_marks' => $maxMarks,
                    'pass_marks' => (float) $sched->pass_marks,
                    'marks_obtained' => $obtained,
                    'is_absent' => $mark ? $mark->is_absent : false,
                    'grade' => $gradeInfo['grade'],
                    'grade_point' => $gradeInfo['grade_point'],
                    'class_highest' => $classHighest,
                    'components' => $mark ? $mark->assessments->map(fn ($a) => [
                        'component_name' => $a->component->component_name,
                        'marks_obtained' => (float) $a->marks_obtained,
                        'max_marks' => (float) $a->component->max_marks,
                    ]) : [],
                ];
            }

            // Consolidated result
            $consolidatedResult = ExamResult::where('examination_id', $examinationId)
                ->where('enrollment_id', $enrollment->id)
                ->first();

            $showRanking = $options['show_ranking'] ?? true;
            $rank = ($consolidatedResult && $showRanking) ? $consolidatedResult->rank : null;

            // Template resolution
            $template = $templateId
                ? ReportCardTemplate::find($templateId)
                : ReportCardTemplate::where('tenant_id', $tenantId)->first();

            // Save report card record
            $reportCard = ReportCard::updateOrCreate(
                [
                    'examination_id' => $examinationId,
                    'enrollment_id' => $enrollment->id,
                ],
                [
                    'tenant_id' => $tenantId,
                    'student_id' => $studentId,
                    'template_id' => $template?->id ?? ReportCardTemplate::firstOrCreate(
                        ['tenant_id' => $tenantId, 'school_id' => $this->tenantContext->getSchoolId(), 'name' => 'Standard CBSE / State Template'],
                        ['template_schema' => ['show_ranking' => $showRanking]]
                    )->id,
                    'remarks' => $options['remarks'] ?? 'Good performance, keep progressing.',
                    'is_signed' => $options['is_signed'] ?? false,
                ]
            );

            return [
                'report_card_id' => $reportCard->id,
                'school' => [
                    'name' => 'EduCore Academy',
                    'affiliation' => 'CBSE Affiliated - Senior Secondary',
                    'code' => 'SCH-01',
                    'logo_url' => '/images/school-logo.png',
                ],
                'examination' => [
                    'title' => $exam->title,
                    'academic_session' => $exam->academicSession?->name ?? '2026-27',
                    'grading_scheme' => $exam->gradingScheme?->name ?? 'Letter Grading',
                ],
                'student' => [
                    'student_id' => $student->id,
                    'admission_number' => $student->admission_number,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'roll_number' => $enrollment->roll_number,
                    'class' => $enrollment->schoolClass?->name ?? 'N/A',
                    'section' => $enrollment->section?->name ?? 'N/A',
                    'date_of_birth' => $student->date_of_birth,
                    'photo_url' => $student->user?->avatar ?? null,
                ],
                'subjects' => $subjectResults,
                'summary' => [
                    'total_marks' => $consolidatedResult ? (float) $consolidatedResult->total_marks : 0.00,
                    'marks_obtained' => $consolidatedResult ? (float) $consolidatedResult->marks_obtained : 0.00,
                    'percentage' => $consolidatedResult ? (float) $consolidatedResult->percentage : 0.00,
                    'grade' => $consolidatedResult ? $consolidatedResult->grade : 'N/A',
                    'grade_point' => $consolidatedResult ? (float) $consolidatedResult->grade_point : 0.00,
                    'rank' => $rank,
                    'result_status' => $consolidatedResult ? $consolidatedResult->result_status : 'pending',
                ],
                'attendance' => [
                    'total_working_days' => 110,
                    'days_present' => 104,
                    'attendance_percentage' => 94.55,
                ],
                'remarks' => [
                    'teacher_remarks' => $options['teacher_remarks'] ?? 'Demonstrates keen enthusiasm and analytical acumen.',
                    'principal_remarks' => $options['principal_remarks'] ?? 'Promoted with distinction.',
                ],
            ];
        });
    }
}

