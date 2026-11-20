<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AssessmentComponent;
use App\Models\Board;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\GradeRange;
use App\Models\GradingScheme;
use App\Models\ReportCard;
use App\Models\ReportCardTemplate;
use App\Models\ResultCorrection;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\BoardsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExaminationAndResultManagementTest extends TestCase
{
    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $mathSubject;
    protected Subject $scienceSubject;
    protected ClassSubject $classMathSubject;
    protected ClassSubject $classScienceSubject;
    protected GradingScheme $gradingScheme;
    protected ExaminationType $examType;
    protected User $adminUser;
    protected User $principalUser;
    protected User $mathTeacherUser;
    protected Employee $mathTeacherEmployee;
    protected User $otherTeacherUser;
    protected Employee $otherTeacherEmployee;
    protected Student $student1;
    protected Student $student2;
    protected StudentEnrollment $enrollment1;
    protected StudentEnrollment $enrollment2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(BoardsSeeder::class);

        // 1. Create Tenant, School, Branch
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Delhi Public Academy',
            'slug' => 'dpa-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@dpa.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Delhi Public World School',
            'code' => 'DPA-01',
            'status' => 'active',
        ]);

        $this->branch = SchoolBranch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Main Campus',
            'branch_code' => 'MC',
            'is_active' => true,
        ]);

        // 2. Academic Session
        $this->session = AcademicSession::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2026-2027',
            'code' => 'AY2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        // 3. Class & Section
        $board = Board::first();
        $this->class = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board?->id,
            'name' => 'Grade 10',
            'code' => 'G10-' . Str::random(4),
            'order_index' => 10,
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
            'max_capacity' => 40,
            'is_active' => true,
        ]);

        // 4. Subjects
        $this->mathSubject = Subject::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Mathematics',
            'code' => 'MATH101',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->scienceSubject = Subject::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Science',
            'code' => 'SCI101',
            'type' => 'both',
            'is_active' => true,
        ]);

        $this->classMathSubject = ClassSubject::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'academic_session_id' => $this->session->id,
            'is_elective' => false,
            'max_weekly_periods' => 6,
        ]);

        $this->classScienceSubject = ClassSubject::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->scienceSubject->id,
            'academic_session_id' => $this->session->id,
            'is_elective' => false,
            'max_weekly_periods' => 6,
        ]);

        // 5. Department & Designation for Staff
        $dept = EmployeeDepartment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Academics',
            'code' => 'ACAD',
            'is_active' => true,
        ]);

        $desig = EmployeeDesignation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Subject Teacher',
            'code' => 'TGT',
            'level' => 2,
            'is_active' => true,
        ]);

        // 6. Admin User
        $this->adminUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Exam',
            'last_name' => 'Controller',
            'email' => 'admin-' . Str::random(8) . '@dpa.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_default' => true,
        ]);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);

        // 7. Principal User
        $this->principalUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'School',
            'last_name' => 'Principal',
            'email' => 'principal-' . Str::random(8) . '@dpa.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->principalUser->id,
            'role' => 'principal',
            'is_default' => true,
        ]);
        $this->principalUser->assignRole('principal', $this->tenant->id);

        // 8. Math Teacher (Assigned to Grade 10 Math)
        $this->mathTeacherUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ramanujan',
            'last_name' => 'Sharma',
            'email' => 'math-' . Str::random(8) . '@dpa.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->mathTeacherUser->id,
            'role' => 'teacher',
            'is_default' => true,
        ]);
        $this->mathTeacherUser->assignRole('teacher', $this->tenant->id);

        $this->mathTeacherEmployee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->mathTeacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-M-' . Str::random(4),
            'first_name' => 'Ramanujan',
            'last_name' => 'Sharma',
            'gender' => 'male',
            'date_of_birth' => '1988-01-15',
            'email' => $this->mathTeacherUser->email,
            'phone' => $this->mathTeacherUser->phone,
            'date_of_joining' => '2024-06-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);

        // Assign Math Teacher to Grade 10 Math
        TeacherSubject::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_subject_id' => $this->classMathSubject->id,
            'employee_id' => $this->mathTeacherEmployee->id,
            'is_primary' => true,
        ]);

        // 9. Other Teacher (NOT assigned to Math)
        $this->otherTeacherUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'History',
            'last_name' => 'Teacher',
            'email' => 'history-' . Str::random(8) . '@dpa.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->otherTeacherUser->id,
            'role' => 'teacher',
            'is_default' => true,
        ]);
        $this->otherTeacherUser->assignRole('teacher', $this->tenant->id);

        $this->otherTeacherEmployee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->otherTeacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-H-' . Str::random(4),
            'first_name' => 'History',
            'last_name' => 'Teacher',
            'gender' => 'female',
            'date_of_birth' => '1990-05-20',
            'email' => $this->otherTeacherUser->email,
            'phone' => $this->otherTeacherUser->phone,
            'date_of_joining' => '2024-06-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);

        // 10. Context Resolution
        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setSchool($this->school);
        app(TenantContext::class)->setBranch($this->branch);

        // 11. Students & Enrollments
        $userS1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ishaan',
            'last_name' => 'Awasthi',
            'email' => 'ishaan-' . Str::random(6) . '@dpa.edu',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        $this->student1 = Student::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $userS1->id,
            'admission_number' => 'STU-1001',
            'first_name' => 'Ishaan',
            'last_name' => 'Awasthi',
            'admission_date' => '2026-04-01',
            'date_of_birth' => '2011-03-20',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->enrollment1 = StudentEnrollment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student1->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '101',
            'enrollment_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $userS2 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ananya',
            'last_name' => 'Roy',
            'email' => 'ananya-' . Str::random(6) . '@dpa.edu',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        $this->student2 = Student::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $userS2->id,
            'admission_number' => 'STU-1002',
            'first_name' => 'Ananya',
            'last_name' => 'Roy',
            'admission_date' => '2026-04-01',
            'date_of_birth' => '2011-08-11',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $this->enrollment2 = StudentEnrollment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student2->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '102',
            'enrollment_date' => '2026-04-01',
            'status' => 'active',
        ]);

        // 12. Create Default Grading Scheme (CBSE 9-Point Scale)
        $this->gradingScheme = GradingScheme::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'CBSE 9-Point Scale',
            'scheme_type' => 'letter',
            'description' => 'Standard secondary assessment scale',
        ]);

        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'A1', 'min_percentage' => 91.00, 'max_percentage' => 100.00, 'grade_point' => 10.0, 'description' => 'Outstanding']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'A2', 'min_percentage' => 81.00, 'max_percentage' => 90.99, 'grade_point' => 9.0, 'description' => 'Excellent']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'B1', 'min_percentage' => 71.00, 'max_percentage' => 80.99, 'grade_point' => 8.0, 'description' => 'Very Good']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'B2', 'min_percentage' => 61.00, 'max_percentage' => 70.99, 'grade_point' => 7.0, 'description' => 'Good']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'C1', 'min_percentage' => 51.00, 'max_percentage' => 60.99, 'grade_point' => 6.0, 'description' => 'Above Average']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'C2', 'min_percentage' => 41.00, 'max_percentage' => 50.99, 'grade_point' => 5.0, 'description' => 'Average']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'D', 'min_percentage' => 33.00, 'max_percentage' => 40.99, 'grade_point' => 4.0, 'description' => 'Marginal / Pass']);
        GradeRange::create(['grading_scheme_id' => $this->gradingScheme->id, 'grade_name' => 'E', 'min_percentage' => 0.00, 'max_percentage' => 32.99, 'grade_point' => 0.0, 'description' => 'Needs Improvement']);

        // 13. Create Examination Type
        $this->examType = ExaminationType::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Term 1 Half-Yearly Exam',
            'weightage_percent' => 50.00,
            'is_active' => true,
        ]);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    protected function actingAsPrincipal(): self
    {
        return $this->actingAs($this->principalUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    protected function actingAsMathTeacher(): self
    {
        return $this->actingAs($this->mathTeacherUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    protected function actingAsOtherTeacher(): self
    {
        return $this->actingAs($this->otherTeacherUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    #[Test]
    public function it_can_create_configurable_grading_scheme_with_ranges()
    {
        $payload = [
            'name' => 'ICSE Percentage Scale',
            'scheme_type' => 'percentage',
            'description' => 'ICSE Board Assessment Scale',
            'ranges' => [
                ['grade_name' => 'Distinction', 'min_percentage' => 85.00, 'max_percentage' => 100.00, 'grade_point' => 4.0, 'description' => 'Honors'],
                ['grade_name' => 'First Division', 'min_percentage' => 60.00, 'max_percentage' => 84.99, 'grade_point' => 3.0, 'description' => 'First Class'],
                ['grade_name' => 'Pass', 'min_percentage' => 35.00, 'max_percentage' => 59.99, 'grade_point' => 2.0, 'description' => 'Satisfactory'],
                ['grade_name' => 'Fail', 'min_percentage' => 0.00, 'max_percentage' => 34.99, 'grade_point' => 0.0, 'description' => 'Unsuccessful'],
            ],
        ];

        $response = $this->actingAsAdmin()->postJson('/api/v1/examinations/grading-schemes', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'ICSE Percentage Scale')
            ->assertJsonCount(4, 'data.ranges');

        $this->assertDatabaseHas('grading_schemes', [
            'tenant_id' => $this->tenant->id,
            'name' => 'ICSE Percentage Scale',
        ]);
    }

    #[Test]
    public function it_can_create_examination_session_and_schedule_subjects_with_components()
    {
        // 1. Create Examination
        $examRes = $this->actingAsAdmin()->postJson('/api/v1/examinations', [
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Half-Yearly Assessment 2026',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-28',
        ]);

        $examRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Half-Yearly Assessment 2026');

        $examId = $examRes->json('data.id');

        // 2. Schedule Science Exam with Theory and Practical Components
        $schedRes = $this->actingAsAdmin()->postJson('/api/v1/examinations/schedules', [
            'examination_id' => $examId,
            'class_id' => $this->class->id,
            'subject_id' => $this->scienceSubject->id,
            'exam_date' => '2026-09-18',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'room_number' => 'Hall-A',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
            'max_theory_marks' => 80.00,
            'max_practical_marks' => 20.00,
            'components' => [
                ['component_name' => 'Theory Written', 'max_marks' => 80.00, 'pass_marks' => 26.00],
                ['component_name' => 'Lab Practical & Viva', 'max_marks' => 20.00, 'pass_marks' => 7.00],
            ],
        ]);

        $schedRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_number', 'Hall-A')
            ->assertJsonCount(2, 'data.components');

        $this->assertDatabaseHas('exam_schedules', [
            'examination_id' => $examId,
            'subject_id' => $this->scienceSubject->id,
            'max_marks' => 100.00,
        ]);

        $this->assertDatabaseHas('assessment_components', [
            'component_name' => 'Theory Written',
            'max_marks' => 80.00,
        ]);
    }

    #[Test]
    public function it_allows_assigned_subject_teacher_to_enter_marks_and_blocks_unauthorized_teacher()
    {
        // 1. Create Examination & Schedule for Math
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Math Periodic Test 1',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-15',
        ]);

        $schedule = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'exam_date' => '2026-07-12',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'room_number' => 'Room 201',
            'max_marks' => 50.00,
            'pass_marks' => 17.00,
        ]);

        // 2. Unauthorized Teacher Attempt (History Teacher trying to enter Math marks)
        $unauthRes = $this->actingAsOtherTeacher()->postJson("/api/v1/examinations/schedules/{$schedule->id}/marks", [
            'marks' => [
                [
                    'enrollment_id' => $this->enrollment1->id,
                    'marks_obtained' => 45.00,
                ],
            ],
        ]);

        $unauthRes->assertStatus(403);

        // 3. Authorized Math Teacher Attempt (Assigned to Grade 10 Math)
        $authRes = $this->actingAsMathTeacher()->postJson("/api/v1/examinations/schedules/{$schedule->id}/marks", [
            'marks' => [
                [
                    'enrollment_id' => $this->enrollment1->id,
                    'marks_obtained' => 48.00,
                    'remarks' => 'Outstanding conceptual understanding',
                ],
                [
                    'enrollment_id' => $this->enrollment2->id,
                    'marks_obtained' => 42.00,
                    'remarks' => 'Good effort',
                ],
            ],
        ]);

        $authRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('student_marks', [
            'exam_schedule_id' => $schedule->id,
            'student_id' => $this->student1->id,
            'marks_obtained' => 48.00,
            'entered_by' => $this->mathTeacherUser->id,
        ]);
    }

    #[Test]
    public function it_validates_marks_boundary_and_supports_absentee_marking()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Math Test',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-15',
        ]);

        $schedule = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'exam_date' => '2026-07-12',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 50.00,
            'pass_marks' => 17.00,
        ]);

        // Attempt marks exceeding max marks: 55 > 50
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\Examination\MarksEntryService::class)->enterStudentMarks(
            $schedule,
            [
                ['enrollment_id' => $this->enrollment1->id, 'marks_obtained' => 55.00],
            ],
            $this->adminUser
        );
    }

    #[Test]
    public function it_supports_bulk_csv_marks_template_and_import()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Science Unit Test',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
        ]);

        $schedule = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->scienceSubject->id,
            'exam_date' => '2026-08-02',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
        ]);

        // 1. Download CSV template
        $templateRes = $this->actingAsAdmin()->get("/api/v1/examinations/schedules/{$schedule->id}/template");
        $templateRes->assertStatus(200);
        $this->assertStringContainsString('enrollment_id,student_id,admission_number', $templateRes->getContent());

        // 2. Import CSV data
        $csvContent = "enrollment_id,student_id,admission_number,student_name,roll_number,marks_obtained,is_absent,remarks\n" .
            "{$this->enrollment1->id},{$this->student1->id},STU-1001,Ishaan Awasthi,101,92,0,Well done\n" .
            "{$this->enrollment2->id},{$this->student2->id},STU-1002,Ananya Roy,102,88,0,Very good\n";

        $importRes = $this->actingAsAdmin()->postJson("/api/v1/examinations/schedules/{$schedule->id}/import", [
            'csv_content' => $csvContent,
        ]);

        $importRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('student_marks', [
            'exam_schedule_id' => $schedule->id,
            'student_id' => $this->student1->id,
            'marks_obtained' => 92.00,
        ]);

        $this->assertDatabaseHas('student_marks', [
            'exam_schedule_id' => $schedule->id,
            'student_id' => $this->student2->id,
            'marks_obtained' => 88.00,
        ]);
    }

    #[Test]
    public function it_manages_marks_correction_requests_with_audit_approval_workflow()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Math Exam',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-15',
        ]);

        $schedule = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'exam_date' => '2026-07-12',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
        ]);

        $mark = StudentMark::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'exam_schedule_id' => $schedule->id,
            'student_id' => $this->student1->id,
            'enrollment_id' => $this->enrollment1->id,
            'marks_obtained' => 65.00,
            'entered_by' => $this->mathTeacherUser->id,
        ]);

        // 1. Teacher requests marks correction
        $reqRes = $this->actingAsMathTeacher()->postJson("/api/v1/examinations/marks/{$mark->id}/correction", [
            'new_marks' => 75.00,
            'reason' => 'Totaling error on Question 4 discovered during paper review',
        ]);

        $reqRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $correctionId = $reqRes->json('data.id');

        // 2. Principal reviews & approves correction
        $reviewRes = $this->actingAsPrincipal()->postJson("/api/v1/examinations/corrections/{$correctionId}/review", [
            'approve' => true,
        ]);

        $reviewRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        // Verify StudentMark was updated to 75.00
        $mark->refresh();
        $this->assertEquals(75.00, (float) $mark->marks_obtained);
    }

    #[Test]
    public function it_calculates_class_results_assigns_grades_ranks_and_identifies_failures()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Term 1 Grand Examination',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
        ]);

        // Schedule Math (100) and Science (100) -> Total 200
        $schedMath = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'exam_date' => '2026-09-02',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
        ]);

        $schedSci = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->scienceSubject->id,
            'exam_date' => '2026-09-04',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
        ]);

        // Student 1 (Ishaan): Math 95, Science 90 -> Total 185/200 (92.5%) -> Grade A1, Rank 1
        StudentMark::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'exam_schedule_id' => $schedMath->id, 'student_id' => $this->student1->id, 'enrollment_id' => $this->enrollment1->id, 'marks_obtained' => 95.00]);
        StudentMark::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'exam_schedule_id' => $schedSci->id, 'student_id' => $this->student1->id, 'enrollment_id' => $this->enrollment1->id, 'marks_obtained' => 90.00]);

        // Student 2 (Ananya): Math 80, Science 82 -> Total 162/200 (81.0%) -> Grade A2, Rank 2
        StudentMark::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'exam_schedule_id' => $schedMath->id, 'student_id' => $this->student2->id, 'enrollment_id' => $this->enrollment2->id, 'marks_obtained' => 80.00]);
        StudentMark::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'exam_schedule_id' => $schedSci->id, 'student_id' => $this->student2->id, 'enrollment_id' => $this->enrollment2->id, 'marks_obtained' => 82.00]);

        // Trigger Result Calculation
        $calcRes = $this->actingAsAdmin()->postJson("/api/v1/examinations/{$exam->id}/classes/{$this->class->id}/calculate");

        $calcRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        // Check Student 1: Rank 1, 92.5%, Grade A1, passed
        $res1 = ExamResult::where('student_id', $this->student1->id)->first();
        $this->assertEquals(1, $res1->rank);
        $this->assertEquals(92.50, (float) $res1->percentage);
        $this->assertEquals('A1', $res1->grade);
        $this->assertEquals('passed', $res1->result_status);

        // Check Student 2: Rank 2, 81.0%, Grade A2, passed
        $res2 = ExamResult::where('student_id', $this->student2->id)->first();
        $this->assertEquals(2, $res2->rank);
        $this->assertEquals(81.00, (float) $res2->percentage);
        $this->assertEquals('A2', $res2->grade);
        $this->assertEquals('passed', $res2->result_status);
    }

    #[Test]
    public function it_publishes_results_with_parent_and_student_portal_visibility()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Annual Exam',
            'start_date' => '2027-02-15',
            'end_date' => '2027-02-28',
        ]);

        $pubRes = $this->actingAsPrincipal()->postJson("/api/v1/examinations/{$exam->id}/publish", [
            'class_id' => $this->class->id,
            'allow_parent_view' => true,
            'allow_student_view' => true,
        ]);

        $pubRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.allow_parent_view', true)
            ->assertJsonPath('data.allow_student_view', true);

        $exam->refresh();
        $this->assertTrue($exam->is_published);
        $this->assertNotNull($exam->published_at);
    }

    #[Test]
    public function it_generates_comprehensive_student_report_card_with_configurable_ranking_toggle()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Annual Board Assessment',
            'start_date' => '2027-02-15',
            'end_date' => '2027-02-28',
        ]);

        $sched = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'exam_date' => '2027-02-16',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
        ]);

        StudentMark::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'exam_schedule_id' => $sched->id,
            'student_id' => $this->student1->id,
            'enrollment_id' => $this->enrollment1->id,
            'marks_obtained' => 96.00,
        ]);

        ExamResult::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'examination_id' => $exam->id,
            'student_id' => $this->student1->id,
            'enrollment_id' => $this->enrollment1->id,
            'total_marks' => 100.00,
            'marks_obtained' => 96.00,
            'percentage' => 96.00,
            'grade' => 'A1',
            'grade_point' => 10.0,
            'rank' => 1,
            'result_status' => 'passed',
        ]);

        // 1. Report Card WITH Ranking Displayed (show_ranking: true)
        $rcRes1 = $this->actingAsAdmin()->getJson("/api/v1/examinations/{$exam->id}/students/{$this->student1->id}/report-card?show_ranking=1");

        $rcRes1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.rank', 1)
            ->assertJsonPath('data.summary.grade', 'A1')
            ->assertJsonPath('data.summary.marks_obtained', 96)
            ->assertJsonCount(1, 'data.subjects');

        // 2. Report Card WITHOUT Ranking Displayed (show_ranking: false) - e.g. for non-competitive policy
        $rcRes2 = $this->actingAsAdmin()->getJson("/api/v1/examinations/{$exam->id}/students/{$this->student1->id}/report-card?show_ranking=0");

        $rcRes2->assertStatus(200)
            ->assertJsonPath('data.summary.rank', null);
    }

    #[Test]
    public function it_generates_class_analytics_toppers_and_subject_performance_metrics()
    {
        $exam = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Term Assessment',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
        ]);

        $sched = ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'examination_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->mathSubject->id,
            'exam_date' => '2026-10-02',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'max_marks' => 100.00,
            'pass_marks' => 33.00,
        ]);

        StudentMark::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'exam_schedule_id' => $sched->id, 'student_id' => $this->student1->id, 'enrollment_id' => $this->enrollment1->id, 'marks_obtained' => 98.00]);
        StudentMark::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'exam_schedule_id' => $sched->id, 'student_id' => $this->student2->id, 'enrollment_id' => $this->enrollment2->id, 'marks_obtained' => 76.00]);

        ExamResult::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'examination_id' => $exam->id, 'student_id' => $this->student1->id, 'enrollment_id' => $this->enrollment1->id, 'total_marks' => 100, 'marks_obtained' => 98, 'percentage' => 98.00, 'grade' => 'A1', 'rank' => 1, 'result_status' => 'passed']);
        ExamResult::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'examination_id' => $exam->id, 'student_id' => $this->student2->id, 'enrollment_id' => $this->enrollment2->id, 'total_marks' => 100, 'marks_obtained' => 76, 'percentage' => 76.00, 'grade' => 'B1', 'rank' => 2, 'result_status' => 'passed']);

        // 1. Performance Summary
        $summaryRes = $this->actingAsAdmin()->getJson("/api/v1/examinations/{$exam->id}/classes/{$this->class->id}/performance");
        $summaryRes->assertStatus(200)
            ->assertJsonPath('data.total_appeared', 2)
            ->assertJsonPath('data.total_passed', 2)
            ->assertJsonPath('data.pass_percentage', 100)
            ->assertJsonPath('data.class_average', 87);

        // 2. Toppers Report
        $toppersRes = $this->actingAsAdmin()->getJson("/api/v1/examinations/{$exam->id}/classes/{$this->class->id}/toppers?limit=1");
        $toppersRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ishaan Awasthi')
            ->assertJsonPath('data.0.rank', 1);

        // 3. Subject-wise realization
        $subjRes = $this->actingAsAdmin()->getJson("/api/v1/examinations/{$exam->id}/classes/{$this->class->id}/subject-wise");
        $subjRes->assertStatus(200)
            ->assertJsonPath('data.0.subject_name', 'Mathematics')
            ->assertJsonPath('data.0.highest_marks', 98)
            ->assertJsonPath('data.0.average_marks', 87);
    }

    #[Test]
    public function it_enforces_strict_tenant_isolation_on_examinations_and_results()
    {
        // Create Tenant B
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oakridge Academy',
            'slug' => 'oakridge-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@oakridge.edu',
            'contact_phone' => '+919876543299',
            'status' => 'active',
        ]);

        $schoolB = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Oakridge Campus',
            'code' => 'OAK-01',
            'status' => 'active',
        ]);

        $branchB = SchoolBranch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'school_id' => $schoolB->id,
            'name' => 'North Campus',
            'branch_code' => 'NC',
            'is_active' => true,
        ]);

        $userB = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Oakridge',
            'last_name' => 'Admin',
            'email' => 'admin-' . Str::random(8) . '@oakridge.edu',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenantB->id,
            'school_id' => $schoolB->id,
            'branch_id' => $branchB->id,
            'user_id' => $userB->id,
            'role' => 'school_admin',
            'is_default' => true,
        ]);
        $userB->assignRole('school_admin', $tenantB->id);

        // Tenant A creates an examination
        $examA = Examination::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'exam_type_id' => $this->examType->id,
            'grading_scheme_id' => $this->gradingScheme->id,
            'title' => 'Confidential Exam A',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
        ]);

        // Tenant B attempts to access Tenant A's examination
        $response = $this->actingAs($userB, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $tenantB->id,
                'X-Branch-ID' => $branchB->id,
            ])
            ->getJson("/api/v1/examinations/{$examA->id}");

        $response->assertStatus(404);
    }
}
