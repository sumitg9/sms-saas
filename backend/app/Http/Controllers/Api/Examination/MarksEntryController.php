<?php

namespace App\Http\Controllers\Api\Examination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\MarksCorrectionRequest;
use App\Http\Requests\Examination\MarksEntryRequest;
use App\Models\ExamSchedule;
use App\Models\ResultCorrection;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Services\Examination\MarksEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarksEntryController extends Controller
{
    public function __construct(
        protected MarksEntryService $marksService
    ) {}

    public function enterMarks(MarksEntryRequest $request, string $scheduleId): JsonResponse
    {
        $schedule = ExamSchedule::findOrFail($scheduleId);

        $savedMarks = $this->marksService->enterStudentMarks(
            $schedule,
            $request->input('marks'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Student marks recorded successfully.',
            'data' => $savedMarks,
        ]);
    }

    public function requestCorrection(MarksCorrectionRequest $request, string $markId): JsonResponse
    {
        $correction = $this->marksService->requestCorrection(
            $markId,
            (float) $request->input('new_marks'),
            $request->input('reason'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Marks correction request submitted for administrative review.',
            'data' => $correction,
        ], 201);
    }

    public function reviewCorrection(Request $request, string $correctionId): JsonResponse
    {
        $request->validate([
            'approve' => ['required', 'boolean'],
        ]);

        $correction = $this->marksService->reviewCorrection(
            $correctionId,
            $request->boolean('approve'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Correction review recorded successfully.',
            'data' => $correction,
        ]);
    }

    public function downloadCsvTemplate(string $scheduleId): Response
    {
        $schedule = ExamSchedule::findOrFail($scheduleId);
        $csv = $this->marksService->generateMarksCsvTemplate($schedule);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"marks_template_{$scheduleId}.csv\"",
        ]);
    }

    public function importCsv(Request $request, string $scheduleId): JsonResponse
    {
        $request->validate([
            'csv_content' => ['required', 'string'],
        ]);

        $schedule = ExamSchedule::findOrFail($scheduleId);

        $savedMarks = $this->marksService->importMarksFromCsv(
            $schedule,
            $request->input('csv_content'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Marks successfully imported from CSV.',
            'data' => $savedMarks,
        ]);
    }

    public function getScheduleMarks(string $scheduleId): JsonResponse
    {
        $schedule = ExamSchedule::with(['examination', 'schoolClass', 'subject', 'components'])->findOrFail($scheduleId);

        $enrollments = StudentEnrollment::with('student')
            ->where('class_id', $schedule->class_id)
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->get();

        $existingMarks = StudentMark::with('assessments')
            ->where('exam_schedule_id', $schedule->id)
            ->get()
            ->keyBy('enrollment_id');

        $studentsData = $enrollments->map(function ($enrollment) use ($existingMarks, $schedule) {
            $student = $enrollment->student;
            $markRecord = $existingMarks->get($enrollment->id);

            return [
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
                'roll_number' => $enrollment->roll_number,
                'mark_id' => $markRecord?->id,
                'marks_obtained' => $markRecord ? (float) $markRecord->marks_obtained : null,
                'is_absent' => $markRecord ? (bool) $markRecord->is_absent : false,
                'remarks' => $markRecord?->remarks,
                'components' => $schedule->components->map(function ($comp) use ($markRecord) {
                    $assessment = $markRecord?->assessments->firstWhere('component_id', $comp->id);
                    return [
                        'component_id' => $comp->id,
                        'component_name' => $comp->component_name,
                        'max_marks' => (float) $comp->max_marks,
                        'marks_obtained' => $assessment ? (float) $assessment->marks_obtained : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => $schedule,
                'students' => $studentsData,
            ],
        ]);
    }

    public function listCorrections(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $query = ResultCorrection::with([
            'studentMark.schedule.examination',
            'studentMark.schedule.subject',
            'studentMark.schedule.schoolClass',
            'studentMark.student',
            'requester',
            'approver',
        ])->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $corrections = $query->limit(100)->get();

        return response()->json([
            'success' => true,
            'data' => $corrections,
        ]);
    }
}
