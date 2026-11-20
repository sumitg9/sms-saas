<?php

namespace App\Services\Portal;

use App\Models\Announcement;
use App\Models\BookIssue;
use App\Models\ExamSchedule;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LearningResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class StudentPortalService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Resolve the student model for the authenticated user.
     */
    public function resolveStudent(User $user, ?string $studentId = null): Student
    {
        if ($studentId && $user->hasAnyRole(['platform_admin', 'school_owner', 'school_admin', 'principal', 'teacher'])) {
            return Student::with(['activeEnrollment.schoolClass', 'activeEnrollment.section'])->findOrFail($studentId);
        }

        $student = Student::with(['activeEnrollment.schoolClass', 'activeEnrollment.section'])
            ->where('user_id', $user->id)
            ->first();

        if (!$student) {
            // Also allow student_id parameter if user has student role and student belongs to user
            if ($studentId) {
                $student = Student::with(['activeEnrollment.schoolClass', 'activeEnrollment.section'])
                    ->where('id', $studentId)
                    ->first();
            }
        }

        if (!$student) {
            throw new AuthorizationException('No active student profile found for this account.');
        }

        return $student;
    }

    /**
     * Comprehensive Student Dashboard Overview.
     */
    public function getDashboard(User $user, ?string $studentId = null): array
    {
        $student = $this->resolveStudent($user, $studentId);
        $enrollment = $student->activeEnrollment;
        $classId = $enrollment?->class_id;
        $sectionId = $enrollment?->section_id;

        // 1. Attendance Statistics
        $attendanceQuery = StudentAttendance::where('student_id', $student->id);
        $totalDays = (clone $attendanceQuery)->count();
        $presentDays = (clone $attendanceQuery)->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentDays = (clone $attendanceQuery)->where('status', 'absent')->count();
        $attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 100.0;

        // 2. Active Homework
        $homeworkQuery = Homework::with(['subject', 'teacher.user'])
            ->where('is_active', true);

        if ($classId) {
            $homeworkQuery->where('class_id', $classId);
        }
        if ($sectionId) {
            $homeworkQuery->where(function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId)->orWhereNull('section_id');
            });
        }
        $homeworkList = $homeworkQuery->orderBy('due_date', 'asc')->limit(5)->get();

        // Check submission status for each homework
        $submissions = HomeworkSubmission::where('student_id', $student->id)
            ->whereIn('homework_id', $homeworkList->pluck('id'))
            ->get()
            ->keyBy('homework_id');

        $activeHomework = $homeworkList->map(function ($hw) use ($submissions) {
            $sub = $submissions->get($hw->id);
            return [
                'id' => $hw->id,
                'title' => $hw->title,
                'subject_name' => $hw->subject?->name,
                'due_date' => $hw->due_date->format('Y-m-d'),
                'max_marks' => $hw->max_marks ? (float) $hw->max_marks : null,
                'is_submitted' => $sub !== null,
                'submission_status' => $sub?->status ?? 'pending',
                'marks_obtained' => $sub?->marks_obtained ? (float) $sub->marks_obtained : null,
            ];
        });

        // 3. Upcoming Examination Schedules
        $upcomingExams = [];
        if ($classId) {
            $upcomingExams = ExamSchedule::with(['examination', 'subject'])
                ->where('class_id', $classId)
                ->where('exam_date', '>=', Carbon::today())
                ->orderBy('exam_date', 'asc')
                ->limit(5)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'examination_title' => $s->examination?->title,
                    'subject_name' => $s->subject?->name,
                    'exam_date' => $s->exam_date->format('Y-m-d'),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'room_number' => $s->room_number,
                    'max_marks' => (float) $s->max_marks,
                ]);
        }

        // 4. Issued Library Books
        $libraryIssues = BookIssue::with(['copy.book'])
            ->where('member_type', 'student')
            ->where('member_id', $student->id)
            ->where('status', 'issued')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->copy?->book?->title,
                'author' => $i->copy?->book?->author,
                'issue_date' => $i->issue_date->format('Y-m-d'),
                'due_date' => $i->due_date->format('Y-m-d'),
                'is_overdue' => Carbon::now()->isAfter($i->due_date),
            ]);

        // 5. Recent Announcements
        $announcements = Announcement::where('is_published', true)
            ->whereIn('target_audience', ['all', 'students'])
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'student' => [
                'id' => $student->id,
                'name' => "{$student->first_name} {$student->last_name}",
                'admission_number' => $student->admission_number,
                'class_name' => $enrollment?->schoolClass?->name ?? 'Unassigned',
                'section_name' => $enrollment?->section?->name ?? 'Unassigned',
                'roll_number' => $enrollment?->roll_number,
                'photo_url' => $student->photo_url,
            ],
            'attendance_summary' => [
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'attendance_percentage' => $attendancePercentage,
            ],
            'active_homework' => $activeHomework,
            'upcoming_exams' => $upcomingExams,
            'library_books' => $libraryIssues,
            'announcements' => $announcements,
        ];
    }

    /**
     * Get Student Timetable.
     */
    public function getTimetable(User $user, ?string $studentId = null): array
    {
        $student = $this->resolveStudent($user, $studentId);
        $enrollment = $student->activeEnrollment;
        if (!$enrollment) {
            return [];
        }

        return DB::table('timetable_entries')
            ->join('timetable_periods', 'timetable_entries.period_id', '=', 'timetable_periods.id')
            ->join('subjects', 'timetable_entries.subject_id', '=', 'subjects.id')
            ->leftJoin('employees', 'timetable_entries.employee_id', '=', 'employees.id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->where('timetable_entries.class_id', $enrollment->class_id)
            ->where('timetable_entries.section_id', $enrollment->section_id)
            ->select(
                'timetable_entries.id',
                'timetable_entries.day_of_week',
                'timetable_entries.room_number',
                'timetable_periods.period_number',
                'timetable_periods.period_name',
                'timetable_periods.start_time',
                'timetable_periods.end_time',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'users.name as teacher_name'
            )
            ->orderBy('timetable_entries.day_of_week')
            ->orderBy('timetable_periods.period_number')
            ->get()
            ->toArray();
    }

    /**
     * Get Student Homework & Submissions.
     */
    public function getHomeworkList(User $user, ?string $studentId = null): array
    {
        $student = $this->resolveStudent($user, $studentId);
        $enrollment = $student->activeEnrollment;

        $query = Homework::with(['subject', 'teacher.user'])
            ->where('is_active', true);

        if ($enrollment) {
            $query->where('class_id', $enrollment->class_id);
            if ($enrollment->section_id) {
                $query->where(function ($q) use ($enrollment) {
                    $q->where('section_id', $enrollment->section_id)->orWhereNull('section_id');
                });
            }
        }

        $homeworkItems = $query->orderBy('due_date', 'desc')->get();
        $submissions = HomeworkSubmission::where('student_id', $student->id)
            ->whereIn('homework_id', $homeworkItems->pluck('id'))
            ->get()
            ->keyBy('homework_id');

        return $homeworkItems->map(function ($hw) use ($submissions) {
            $sub = $submissions->get($hw->id);
            return [
                'homework' => $hw,
                'submission' => $sub,
                'is_submitted' => $sub !== null,
                'is_late' => $sub ? $sub->is_late : Carbon::now()->isAfter(Carbon::parse($hw->due_date)->endOfDay()),
            ];
        })->toArray();
    }

    /**
     * Get Student Learning Resources.
     */
    public function getLearningResources(User $user, ?string $studentId = null): array
    {
        $student = $this->resolveStudent($user, $studentId);
        $enrollment = $student->activeEnrollment;

        $query = LearningResource::with(['subject', 'uploader'])
            ->where('is_published', true);

        if ($enrollment) {
            $query->where('class_id', $enrollment->class_id);
            if ($enrollment->section_id) {
                $query->where(function ($q) use ($enrollment) {
                    $q->where('section_id', $enrollment->section_id)->orWhereNull('section_id');
                });
            }
        }

        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    /**
     * Get Student Published Report Cards.
     */
    public function getReportCards(User $user, ?string $studentId = null): array
    {
        $student = $this->resolveStudent($user, $studentId);

        return ReportCard::with(['examination', 'academicSession'])
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}

