<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAttendanceController extends Controller
{
    /**
     * List attendance records with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentAttendance::with(['student', 'schoolClass', 'section']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->query('section_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->query('date'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->query('start_date'), $request->query('end_date')]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $records = $query->orderByDesc('date')->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
            ],
        ]);
    }

    /**
     * Get daily section roster with attendance status for marking.
     */
    public function roster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid',
            'class_id' => 'required|uuid',
            'section_id' => 'required|uuid',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        // Retrieve all enrolled students in section
        $enrollments = StudentEnrollment::where('branch_id', $validated['branch_id'])
            ->where('class_id', $validated['class_id'])
            ->where('section_id', $validated['section_id'])
            ->where('status', 'active')
            ->with('student')
            ->orderBy('roll_number')
            ->get();

        // Retrieve existing marked attendance for this date
        $existing = StudentAttendance::where('branch_id', $validated['branch_id'])
            ->where('class_id', $validated['class_id'])
            ->where('section_id', $validated['section_id'])
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        $roster = $enrollments->map(function ($enrollment) use ($existing) {
            $att = $existing->get($enrollment->student_id);
            return [
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'roll_number' => $enrollment->roll_number,
                'student_name' => $enrollment->student ? ($enrollment->student->first_name . ' ' . $enrollment->student->last_name) : 'Student',
                'admission_number' => $enrollment->student?->admission_number,
                'status' => $att ? $att->status : 'present', // Default to present
                'remarks' => $att?->remarks,
                'is_marked' => (bool) $att,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'total_students' => $roster->count(),
                'marked_count' => $existing->count(),
                'roster' => $roster,
            ],
        ]);
    }

    /**
     * Bulk mark or update daily attendance for a section.
     */
    public function markBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid',
            'class_id' => 'required|uuid',
            'section_id' => 'required|uuid',
            'academic_session_id' => 'nullable|uuid',
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|uuid',
            'records.*.enrollment_id' => 'required|uuid',
            'records.*.status' => 'required|string|in:present,absent,late,half_day,excused',
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $sessionId = $validated['academic_session_id'] ?? null;
        $markedBy = $request->user()?->id;

        $savedCount = 0;

        DB::transaction(function () use ($validated, $date, $sessionId, $markedBy, &$savedCount) {
            foreach ($validated['records'] as $record) {
                StudentAttendance::updateOrCreate(
                    [
                        'branch_id' => $validated['branch_id'],
                        'student_id' => $record['student_id'],
                        'date' => $date,
                        'subject_id' => null, // null for general daily attendance
                    ],
                    [
                        'enrollment_id' => $record['enrollment_id'],
                        'academic_session_id' => $sessionId,
                        'class_id' => $validated['class_id'],
                        'section_id' => $validated['section_id'],
                        'status' => $record['status'],
                        'remarks' => $record['remarks'] ?? null,
                        'marked_by' => $markedBy,
                    ]
                );
                $savedCount++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Successfully recorded attendance for {$savedCount} students.",
            'marked_count' => $savedCount,
        ]);
    }

    /**
     * Get aggregate attendance summary for a specific student.
     */
    public function studentSummary(string $studentId): JsonResponse
    {
        $attendance = StudentAttendance::where('student_id', $studentId)->get();

        $totalDays = $attendance->count();
        $present = $attendance->where('status', 'present')->count();
        $absent = $attendance->where('status', 'absent')->count();
        $late = $attendance->where('status', 'late')->count();
        $excused = $attendance->where('status', 'excused')->count();

        $percentage = $totalDays > 0 ? round(($present / $totalDays) * 100, 1) : 100.0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_working_days' => $totalDays,
                'present_days' => $present,
                'absent_days' => $absent,
                'late_days' => $late,
                'excused_days' => $excused,
                'attendance_percentage' => $percentage,
            ],
        ]);
    }
}

