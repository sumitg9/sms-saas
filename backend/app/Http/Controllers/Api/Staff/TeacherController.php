<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SubstituteTeacherRequest;
use App\Models\Employee;
use App\Services\Staff\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(
        protected TeacherService $teacherService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teachers = $this->teacherService->getTeachers($request->all());

        return response()->json([
            'success' => true,
            'data' => $teachers,
        ]);
    }

    public function show(Employee $teacher): JsonResponse
    {
        $teacher->load([
            'department',
            'designation',
            'teacherSubjects.classSubject.subject',
            'teacherSubjects.classSubject.schoolClass',
            'classTeachers.schoolClass',
            'classTeachers.section',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => $teacher,
                'assigned_subjects' => $teacher->teacherSubjects->map(fn ($ts) => [
                    'subject_name' => $ts->classSubject?->subject?->name,
                    'subject_code' => $ts->classSubject?->subject?->code,
                    'class_name' => $ts->classSubject?->schoolClass?->name,
                    'is_primary' => $ts->is_primary,
                ]),
                'class_teacher_in_charge' => $teacher->classTeachers->map(fn ($ct) => [
                    'class_name' => $ct->schoolClass?->name,
                    'section_name' => $ct->section?->name,
                    'is_primary' => $ct->is_primary,
                ]),
            ],
        ]);
    }

    public function workload(Request $request, Employee $teacher): JsonResponse
    {
        $sessionId = $request->query('academic_session_id');
        $workload = $this->teacherService->calculateWorkload($teacher, $sessionId);

        return response()->json([
            'success' => true,
            'data' => $workload,
        ]);
    }

    public function timetable(Request $request, Employee $teacher): JsonResponse
    {
        $sessionId = $request->query('academic_session_id');
        $timetable = $this->teacherService->getTimetable($teacher, $sessionId);

        return response()->json([
            'success' => true,
            'data' => $timetable,
        ]);
    }

    public function attendance(Request $request, Employee $teacher): JsonResponse
    {
        $month = $request->query('month');
        $summary = $this->teacherService->getAttendanceSummary($teacher, $month);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function assignSubstitute(SubstituteTeacherRequest $request): JsonResponse
    {
        $substitution = $this->teacherService->assignSubstitute($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Substitute teacher assigned successfully',
            'data' => $substitution,
        ], 201);
    }
}

