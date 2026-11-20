<?php

namespace App\Services\Portal;

use App\Models\Employee;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class TeacherPortalService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Resolve employee profile for the authenticated teacher.
     */
    public function resolveTeacher(User $user, ?string $employeeId = null): Employee
    {
        if ($employeeId && $user->hasAnyRole(['platform_admin', 'school_owner', 'school_admin', 'principal'])) {
            return Employee::with('user')->findOrFail($employeeId);
        }

        $employee = Employee::with('user')->where('user_id', $user->id)->first();
        if (!$employee && $employeeId) {
            $employee = Employee::with('user')->where('id', $employeeId)->first();
        }

        if (!$employee) {
            throw new AuthorizationException('No active employee or teacher profile found for this account.');
        }

        return $employee;
    }

    /**
     * Get assigned classes and subjects for this teacher.
     */
    public function getAssignedClassesAndSubjects(User $user, ?string $employeeId = null): array
    {
        $teacher = $this->resolveTeacher($user, $employeeId);

        $teacherSubjects = TeacherSubject::with(['classSubject.schoolClass', 'classSubject.subject'])
            ->where('employee_id', $teacher->id)
            ->get();

        $assignments = $teacherSubjects->map(function ($ts) {
            $cs = $ts->classSubject;
            return [
                'teacher_subject_id' => $ts->id,
                'class_id' => $cs?->class_id,
                'class_name' => $cs?->schoolClass?->name,
                'class_code' => $cs?->schoolClass?->code,
                'subject_id' => $cs?->subject_id,
                'subject_name' => $cs?->subject?->name,
                'subject_code' => $cs?->subject?->code,
            ];
        })->unique(fn ($item) => "{$item['class_id']}-{$item['subject_id']}")->values();

        return [
            'teacher' => [
                'id' => $teacher->id,
                'name' => "{$teacher->first_name} {$teacher->last_name}",
                'employee_code' => $teacher->employee_code,
                'department' => $teacher->department?->name ?? 'Academics',
                'designation' => $teacher->designation?->name ?? 'Teacher',
            ],
            'assignments' => $assignments,
        ];
    }

    /**
     * Teacher Dashboard Overview.
     */
    public function getDashboard(User $user, ?string $employeeId = null): array
    {
        $teacher = $this->resolveTeacher($user, $employeeId);
        $assignmentsData = $this->getAssignedClassesAndSubjects($user, $teacher->id);
        $classIds = collect($assignmentsData['assignments'])->pluck('class_id')->unique()->filter()->values();

        // 1. Pending Assignment Submissions to evaluate
        $pendingSubmissionsCount = HomeworkSubmission::whereHas('homework', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('status', 'submitted')->count();

        // 2. Active Homework Assigned
        $activeHomeworkCount = Homework::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('due_date', '>=', Carbon::today())
            ->count();

        // 3. Today's Attendance Overview
        $todayAttendanceMarked = StudentAttendance::where('date', Carbon::today())
            ->whereIn('class_id', $classIds)
            ->exists();

        // 4. Recent Homework items
        $recentHomework = Homework::with(['schoolClass', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->withCount('submissions')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 5. Recent Submissions pending review
        $submissionsPendingReview = HomeworkSubmission::with(['homework.subject', 'student'])
            ->whereHas('homework', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->where('status', 'submitted')
            ->orderBy('submitted_at', 'asc')
            ->limit(5)
            ->get();

        return [
            'teacher' => $assignmentsData['teacher'],
            'stats' => [
                'assigned_classes_count' => $classIds->count(),
                'active_homework_count' => $activeHomeworkCount,
                'pending_evaluations_count' => $pendingSubmissionsCount,
                'today_attendance_marked' => $todayAttendanceMarked,
            ],
            'assignments' => $assignmentsData['assignments'],
            'recent_homework' => $recentHomework,
            'pending_submissions' => $submissionsPendingReview,
        ];
    }

    /**
     * Weekly Timetable for Teacher.
     */
    public function getTeacherTimetable(User $user, ?string $employeeId = null): array
    {
        $teacher = $this->resolveTeacher($user, $employeeId);

        return DB::table('timetable_entries')
            ->join('timetable_periods', 'timetable_entries.period_id', '=', 'timetable_periods.id')
            ->join('classes', 'timetable_entries.class_id', '=', 'classes.id')
            ->join('sections', 'timetable_entries.section_id', '=', 'sections.id')
            ->join('subjects', 'timetable_entries.subject_id', '=', 'subjects.id')
            ->where('timetable_entries.employee_id', $teacher->id)
            ->select(
                'timetable_entries.id',
                'timetable_entries.day_of_week',
                'timetable_entries.room_number',
                'timetable_periods.period_number',
                'timetable_periods.period_name',
                'timetable_periods.start_time',
                'timetable_periods.end_time',
                'classes.name as class_name',
                'sections.name as section_name',
                'subjects.name as subject_name',
                'subjects.code as subject_code'
            )
            ->orderBy('timetable_entries.day_of_week')
            ->orderBy('timetable_periods.period_number')
            ->get()
            ->toArray();
    }

    /**
     * Student Performance Analytics for Teacher's Classes.
     */
    public function getStudentPerformance(User $user, string $classId, string $subjectId, ?string $employeeId = null): array
    {
        $teacher = $this->resolveTeacher($user, $employeeId);

        // Fetch enrolled students
        $enrollments = StudentEnrollment::with('student')
            ->where('class_id', $classId)
            ->whereIn('status', ['active', 'enrolled'])
            ->orderBy('roll_number')
            ->get();

        // Fetch marks for this subject
        $marks = StudentMark::whereHas('schedule', function ($q) use ($classId, $subjectId) {
            $q->where('class_id', $classId)->where('subject_id', $subjectId);
        })->get()->keyBy('student_id');

        // Homework submissions
        $submissions = HomeworkSubmission::whereHas('homework', function ($q) use ($classId, $subjectId) {
            $q->where('class_id', $classId)->where('subject_id', $subjectId);
        })->get()->groupBy('student_id');

        $studentsPerformance = $enrollments->map(function ($enr) use ($marks, $submissions) {
            $stu = $enr->student;
            $markRecord = $marks->get($stu->id);
            $stuSubs = $submissions->get($stu->id) ?? collect();

            return [
                'student_id' => $stu->id,
                'name' => "{$stu->first_name} {$stu->last_name}",
                'roll_number' => $enr->roll_number,
                'admission_number' => $stu->admission_number,
                'exam_marks' => $markRecord ? (float) $markRecord->marks_obtained : null,
                'homework_completed' => $stuSubs->where('status', 'graded')->count(),
                'homework_total' => $stuSubs->count(),
                'average_homework_marks' => $stuSubs->where('status', 'graded')->avg('marks_obtained'),
            ];
        });

        return [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'total_students' => $enrollments->count(),
            'students' => $studentsPerformance,
        ];
    }
}
