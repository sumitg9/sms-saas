<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Board;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LearningResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LmsAndPortalsTest extends TestCase
{
    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class10;
    protected Section $sectionA;
    protected Subject $mathSubject;
    protected Subject $scienceSubject;
    protected User $adminUser;
    protected User $mathTeacherUser;
    protected Employee $mathTeacherEmployee;
    protected User $otherTeacherUser;
    protected Employee $otherTeacherEmployee;
    protected User $studentUser1;
    protected Student $student1;
    protected StudentEnrollment $enrollment1;
    protected User $parentUser;
    protected Guardian $guardian;
    protected User $unrelatedParentUser;
    protected Guardian $unrelatedGuardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\BoardsSeeder::class);

        // 1. Create Tenant & Branch
        $this->tenant = Tenant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Stellar International Academy',
            'slug' => 'stellar-' . \Illuminate\Support\Str::random(5),
            'contact_email' => 'admin-' . \Illuminate\Support\Str::random(5) . '@stellar.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Stellar High School',
            'code' => 'STLR-' . rand(100, 999),
            'status' => 'active',
        ]);

        $this->branch = SchoolBranch::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Main Campus',
            'branch_code' => 'MAIN-' . rand(100, 999),
            'is_main_branch' => true,
            'is_active' => true,
        ]);

        // Set context
        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($this->tenant);
        $tenantContext->setSchool($this->school);
        $tenantContext->setBranch($this->branch);

        // 2. Academic structure
        $this->session = AcademicSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2026-2027',
            'code' => 'AY2026-27-' . rand(10, 99),
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $board = Board::first();

        $this->class10 = SchoolClass::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Class 10',
            'code' => 'CLS-10-' . rand(100, 999),
            'board_id' => $board?->id,
            'order_index' => 10,
            'is_active' => true,
        ]);

        $this->sectionA = Section::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class10->id,
            'name' => 'Section A',
            'code' => '10-A-' . rand(100, 999),
            'capacity' => 40,
        ]);

        $this->mathSubject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Mathematics',
            'code' => 'MATH-10-' . rand(100, 999),
            'type' => 'theory',
        ]);

        $this->scienceSubject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Science',
            'code' => 'SCI-10-' . rand(100, 999),
            'type' => 'theory',
        ]);

        $mathClassSubject = ClassSubject::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class10->id,
            'subject_id' => $this->mathSubject->id,
            'academic_session_id' => $this->session->id,
            'is_elective' => false,
        ]);

        // 3. Department & Designation for Staff
        $dept = EmployeeDepartment::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Academics',
            'code' => 'ACAD-' . rand(10, 99),
            'is_active' => true,
        ]);

        $desig = EmployeeDesignation::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Subject Teacher',
            'code' => 'TGT-' . rand(10, 99),
            'level' => 2,
            'is_active' => true,
        ]);

        // 4. Users & Roles
        $adminRole = Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher'], ['guard_name' => 'web', 'display_name' => 'Teacher']);
        $studentRole = Role::firstOrCreate(['name' => 'student'], ['guard_name' => 'web', 'display_name' => 'Student']);
        $parentRole = Role::firstOrCreate(['name' => 'parent'], ['guard_name' => 'web', 'display_name' => 'Parent']);

        $this->adminUser = User::factory()->create(['first_name' => 'Principal', 'last_name' => 'Admin']);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // Math Teacher
        $mathEmail = 'euler-' . \Illuminate\Support\Str::random(6) . '@school.com';
        $this->mathTeacherUser = User::factory()->create(['first_name' => 'Leonhard', 'last_name' => 'Euler', 'email' => $mathEmail]);
        $this->mathTeacherUser->assignRole('teacher', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->mathTeacherUser->id,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->mathTeacherEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->mathTeacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-M-' . rand(1000, 9999),
            'first_name' => 'Leonhard',
            'last_name' => 'Euler',
            'gender' => 'male',
            'date_of_birth' => '1985-04-15',
            'email' => $mathEmail,
            'phone' => '+91987654' . rand(1000, 9999),
            'date_of_joining' => '2020-06-01',
            'employment_status' => 'active',
        ]);

        TeacherSubject::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->mathTeacherEmployee->id,
            'class_subject_id' => $mathClassSubject->id,
        ]);

        // Other Teacher
        $otherEmail = 'history-' . \Illuminate\Support\Str::random(6) . '@school.com';
        $this->otherTeacherUser = User::factory()->create(['first_name' => 'Herodotus', 'last_name' => 'Teacher', 'email' => $otherEmail]);
        $this->otherTeacherUser->assignRole('teacher', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->otherTeacherUser->id,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->otherTeacherEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->otherTeacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-H-' . rand(1000, 9999),
            'first_name' => 'Herodotus',
            'last_name' => 'Teacher',
            'gender' => 'male',
            'date_of_birth' => '1988-08-20',
            'email' => $otherEmail,
            'phone' => '+91987655' . rand(1000, 9999),
            'date_of_joining' => '2021-06-01',
            'employment_status' => 'active',
        ]);

        // Student 1
        $studentEmail = 'gauss-' . \Illuminate\Support\Str::random(6) . '@student.com';
        $this->studentUser1 = User::factory()->create(['first_name' => 'Carl', 'last_name' => 'Gauss', 'email' => $studentEmail]);
        $this->studentUser1->assignRole('student', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->studentUser1->id,
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->student1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->studentUser1->id,
            'admission_number' => 'ADM-STLR-' . rand(1000, 9999),
            'first_name' => 'Carl',
            'last_name' => 'Gauss',
            'date_of_birth' => '2010-04-30',
            'admission_date' => '2024-04-01',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->enrollment1 = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student1->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => '01',
            'status' => 'enrolled',
        ]);

        // Parent
        $parentEmail = 'parent-' . \Illuminate\Support\Str::random(6) . '@family.com';
        $this->parentUser = User::factory()->create(['first_name' => 'Gebhard', 'last_name' => 'Gauss', 'email' => $parentEmail]);
        $this->parentUser->assignRole('parent', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->parentUser->id,
            'role' => 'parent',
            'is_active' => true,
        ]);

        $this->guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->parentUser->id,
            'first_name' => 'Gebhard',
            'last_name' => 'Gauss',
            'relation_type' => 'father',
            'phone' => '+91998877' . rand(1000, 9999),
            'email' => $parentEmail,
        ]);

        DB::table('student_guardians')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian->id,
            'is_primary' => true,
            'has_pickup_rights' => true,
            'receives_invoices' => true,
            'emergency_contact_priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Unrelated Parent (for IDOR attack testing)
        $unrelatedEmail = 'unrelated-' . \Illuminate\Support\Str::random(6) . '@family.com';
        $this->unrelatedParentUser = User::factory()->create(['first_name' => 'Unrelated', 'last_name' => 'Parent', 'email' => $unrelatedEmail]);
        $this->unrelatedParentUser->assignRole('parent', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->unrelatedParentUser->id,
            'role' => 'parent',
            'is_active' => true,
        ]);

        $this->unrelatedGuardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->unrelatedParentUser->id,
            'first_name' => 'Unrelated',
            'last_name' => 'Guardian',
            'relation_type' => 'father',
            'phone' => '+91911122' . rand(1000, 9999),
            'email' => $unrelatedEmail,
        ]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->unrelatedParentUser->id,
            'role' => 'parent',
            'is_active' => true,
        ]);

        $this->unrelatedGuardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->unrelatedParentUser->id,
            'first_name' => 'Unrelated',
            'last_name' => 'Guardian',
            'relation_type' => 'father',
            'phone' => '+919111222333',
            'email' => 'unrelated@family.com',
        ]);
    }

    /**
     * Test 1: Teacher creates homework assigned to class/section/subject with deadline and attachments.
     */
    public function test_teacher_creates_homework_and_unauthorized_teacher_is_blocked(): void
    {
        // 1. Math Teacher can create homework for Class 10 Math
        $response = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/lms/homework', [
                'class_id' => $this->class10->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->mathSubject->id,
                'title' => 'Quadratic Equations Exercise 4.2',
                'description' => 'Complete problems 1 to 15 showing intermediate steps.',
                'assigned_date' => Carbon::today()->format('Y-m-d'),
                'due_date' => Carbon::tomorrow()->format('Y-m-d'),
                'max_marks' => 20,
                'attachments' => [
                    ['name' => 'exercise_4_2.pdf', 'url' => 'https://storage.school.com/docs/ex42.pdf'],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('homework', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Quadratic Equations Exercise 4.2',
            'class_id' => $this->class10->id,
            'subject_id' => $this->mathSubject->id,
        ]);

        // 2. Unauthorized Teacher (History teacher) is blocked from assigning Math homework
        $unauthResponse = $this->actingAs($this->otherTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/lms/homework', [
                'class_id' => $this->class10->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->mathSubject->id,
                'title' => 'Unauthorized Math Assignment',
                'description' => 'Should be rejected',
                'assigned_date' => Carbon::today()->format('Y-m-d'),
                'due_date' => Carbon::tomorrow()->format('Y-m-d'),
            ]);

        $unauthResponse->assertStatus(403);
    }

    /**
     * Test 2: Student assignment submission and late submission tracking.
     */
    public function test_student_assignment_submission_and_late_tracking(): void
    {
        // 1. Create on-time homework
        $onTimeHw = Homework::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathSubject->id,
            'teacher_id' => $this->mathTeacherEmployee->id,
            'title' => 'Geometry Proofs',
            'description' => 'Submit solutions for theorem 10.1',
            'assigned_date' => Carbon::yesterday()->format('Y-m-d'),
            'due_date' => Carbon::tomorrow()->format('Y-m-d'),
            'max_marks' => 25,
        ]);

        $submitResponse = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/homework/{$onTimeHw->id}/submit", [
                'submission_text' => 'Solved all geometry problems on attached scanned sheet.',
                'attachments' => [
                    ['name' => 'gauss_solutions.pdf', 'url' => 'https://storage.school.com/subs/gauss.pdf'],
                ],
            ]);

        $submitResponse->assertStatus(201);
        $this->assertDatabaseHas('homework_submissions', [
            'homework_id' => $onTimeHw->id,
            'student_id' => $this->student1->id,
            'is_late' => false,
            'status' => 'submitted',
        ]);

        // 2. Create past due homework to test late flag
        $pastHw = Homework::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathSubject->id,
            'teacher_id' => $this->mathTeacherEmployee->id,
            'title' => 'Late Trigonometry Homework',
            'description' => 'Past due homework',
            'assigned_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'due_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'max_marks' => 10,
        ]);

        $lateResponse = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/homework/{$pastHw->id}/submit", [
                'submission_text' => 'Late submission with apologies.',
            ]);

        $lateResponse->assertStatus(201);
        $this->assertDatabaseHas('homework_submissions', [
            'homework_id' => $pastHw->id,
            'student_id' => $this->student1->id,
            'is_late' => true,
        ]);
    }

    /**
     * Test 3: Teacher evaluation, feedback, and resubmission workflow.
     */
    public function test_teacher_evaluation_and_resubmission_lifecycle(): void
    {
        $hw = Homework::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathSubject->id,
            'teacher_id' => $this->mathTeacherEmployee->id,
            'title' => 'Calculus Basics',
            'description' => 'Derivatives intro',
            'assigned_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::tomorrow()->format('Y-m-d'),
            'max_marks' => 30,
        ]);

        $submission = HomeworkSubmission::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'homework_id' => $hw->id,
            'student_id' => $this->student1->id,
            'submission_text' => 'First attempt at problem 4',
            'status' => 'submitted',
        ]);

        // 1. Teacher requests resubmission
        $resubRequest = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/submissions/{$submission->id}/resubmit-request", [
                'feedback' => 'Incomplete chain rule derivation in step 3. Please correct and resubmit.',
            ]);

        $resubRequest->assertStatus(200);
        $this->assertDatabaseHas('homework_submissions', [
            'id' => $submission->id,
            'status' => 'resubmission_requested',
        ]);

        // 2. Student resubmits
        $studentResub = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/homework/{$hw->id}/submit", [
                'submission_text' => 'Revised solution with corrected chain rule.',
            ]);

        $studentResub->assertStatus(201);
        $this->assertEquals(1, $submission->fresh()->resubmission_count);

        // 3. Teacher evaluates and grades resubmission
        $gradeResponse = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/submissions/{$submission->id}/grade", [
                'marks_obtained' => 28.5,
                'feedback' => 'Flawless proof! Full credit restored.',
            ]);

        $gradeResponse->assertStatus(200);
        $this->assertDatabaseHas('homework_submissions', [
            'id' => $submission->id,
            'status' => 'graded',
            'marks_obtained' => 28.5,
        ]);
    }

    /**
     * Test 4: Learning resources with documents, video links, notes, and class-filtered access.
     */
    public function test_learning_resources_upload_and_class_access(): void
    {
        $response = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/lms/resources', [
                'class_id' => $this->class10->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->mathSubject->id,
                'title' => 'Trigonometry Reference Notes & Video Lecture',
                'description' => 'Comprehensive formula sheet and derivation walkthrough video.',
                'resource_type' => 'video',
                'video_url' => 'https://www.youtube.com/watch?v=sample-trig',
                'file_url' => 'https://storage.school.com/notes/trig_handbook.pdf',
                'is_published' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_resources', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Trigonometry Reference Notes & Video Lecture',
            'class_id' => $this->class10->id,
            'subject_id' => $this->mathSubject->id,
        ]);

        // Student fetches learning resources for their class
        $studentRes = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/lms/resources?class_id=' . $this->class10->id);

        $studentRes->assertStatus(200);
        $studentRes->assertJsonFragment(['title' => 'Trigonometry Reference Notes & Video Lecture']);
    }

    /**
     * Test 5: Online Quiz creation, questions, and automatic scoring engine.
     */
    public function test_online_quiz_creation_and_automatic_scoring(): void
    {
        // 1. Create quiz with MCQ, True/False, and Short Answer
        $quizResponse = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/lms/quizzes', [
                'class_id' => $this->class10->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->mathSubject->id,
                'title' => 'Algebra & Number Theory Quick Quiz',
                'duration_minutes' => 15,
                'pass_marks' => 3.0,
                'is_published' => true,
                'questions' => [
                    [
                        'question_text' => 'What is the value of 2^3 + 3^2?',
                        'question_type' => 'multiple_choice',
                        'marks' => 2.0,
                        'options' => [
                            ['key' => 'A', 'text' => '17'],
                            ['key' => 'B', 'text' => '15'],
                            ['key' => 'C', 'text' => '25'],
                        ],
                        'correct_answer' => 'A',
                    ],
                    [
                        'question_text' => 'Is 17 a prime number?',
                        'question_type' => 'true_false',
                        'marks' => 1.0,
                        'correct_answer' => 'true',
                    ],
                    [
                        'question_text' => 'State the square root of 144.',
                        'question_type' => 'short_answer',
                        'marks' => 2.0,
                        'correct_answer' => '12',
                    ],
                ],
            ]);

        $quizResponse->assertStatus(201);
        $quizId = $quizResponse->json('data.id');

        $this->assertDatabaseHas('quizzes', [
            'id' => $quizId,
            'total_marks' => 5.0,
            'pass_marks' => 3.0,
        ]);

        // 2. Student starts quiz attempt
        $startResponse = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/quizzes/{$quizId}/attempts");

        $startResponse->assertStatus(201);
        $attemptId = $startResponse->json('data.id');

        // 3. Student submits answers (All 3 correct)
        $q1 = QuizQuestion::where('quiz_id', $quizId)->where('question_type', 'multiple_choice')->first();
        $q2 = QuizQuestion::where('quiz_id', $quizId)->where('question_type', 'true_false')->first();
        $q3 = QuizQuestion::where('quiz_id', $quizId)->where('question_type', 'short_answer')->first();

        $submitResponse = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/lms/quiz-attempts/{$attemptId}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer' => 'A'],
                    ['question_id' => $q2->id, 'answer' => 'true'],
                    ['question_id' => $q3->id, 'answer' => '12'],
                ],
            ]);

        $submitResponse->assertStatus(200);
        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $attemptId,
            'score' => 5.0,
            'percentage' => 100.0,
            'is_passed' => true,
            'status' => 'completed',
        ]);

        // 4. Check quiz analytics report
        $reportResponse = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/lms/quizzes/{$quizId}/report");

        $reportResponse->assertStatus(200);
        $this->assertEquals(1, $reportResponse->json('data.summary.total_attempts'));
        $this->assertEquals(100.0, $reportResponse->json('data.summary.pass_percentage'));
    }

    /**
     * Test 6: Student Portal Dashboard delivers attendance, timetable, homework, and resources.
     */
    public function test_student_portal_dashboard_and_modules(): void
    {
        // Seed attendance record for student
        StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student1->id,
            'enrollment_id' => $this->enrollment1->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sectionA->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/portal/student/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'student' => ['id', 'name', 'admission_number', 'class_name'],
                'attendance_summary' => ['total_days', 'present_days', 'attendance_percentage'],
                'active_homework',
                'upcoming_exams',
                'library_books',
                'announcements',
            ],
        ]);

        $this->assertEquals('Carl Gauss', $response->json('data.student.name'));
        $this->assertEquals(1, $response->json('data.attendance_summary.present_days'));
    }

    /**
     * Test 7: Parent Portal displays linked children and STRICTLY BLOCKS unauthorized child access (IDOR guard).
     */
    public function test_parent_portal_linked_children_and_blocks_unauthorized_access(): void
    {
        // 1. Authorized parent can list their linked children
        $childrenRes = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/portal/parent/children');

        $childrenRes->assertStatus(200);
        $this->assertCount(1, $childrenRes->json('data'));
        $this->assertEquals($this->student1->id, $childrenRes->json('data.0.id'));

        // 2. Authorized parent can view their child's dashboard
        $childDashRes = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/portal/parent/children/{$this->student1->id}/dashboard");

        $childDashRes->assertStatus(200);
        $childDashRes->assertJsonFragment(['name' => 'Carl Gauss']);

        // 3. Unrelated parent CANNOT access this child (IDOR protection)
        $attackRes = $this->actingAs($this->unrelatedParentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/portal/parent/children/{$this->student1->id}/dashboard");

        $attackRes->assertStatus(403);
    }

    /**
     * Test 8: Parent can submit child leave application and send teacher message.
     */
    public function test_parent_can_submit_leave_and_send_communication(): void
    {
        // 1. Submit leave application for child
        $leaveResponse = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/portal/parent/children/{$this->student1->id}/leave", [
                'start_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
                'end_date' => Carbon::today()->addDays(4)->format('Y-m-d'),
                'leave_type' => 'medical',
                'reason' => 'Recovery following wisdom tooth extraction.',
            ]);

        $leaveResponse->assertStatus(201);
        $this->assertDatabaseHas('leave_applications', [
            'tenant_id' => $this->tenant->id,
            'applicant_type' => 'student',
            'applicant_id' => $this->student1->id,
            'leave_type' => 'medical',
            'status' => 'pending',
        ]);

        // 2. Send message / support ticket regarding child
        $msgResponse = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/portal/parent/children/{$this->student1->id}/communicate", [
                'subject' => 'Clarification on advanced math syllabus',
                'message' => 'Dear teacher, would like to inquire regarding Olympiad prep sessions.',
                'category' => 'academic',
                'priority' => 'medium',
            ]);

        $msgResponse->assertStatus(201);
        $this->assertDatabaseHas('support_tickets', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->parentUser->id,
            'category' => 'academic',
        ]);
    }

    /**
     * Test 9: Teacher Portal Dashboard delivers assigned classes, stats, and student performance.
     */
    public function test_teacher_portal_dashboard_and_performance(): void
    {
        $dashResponse = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/portal/teacher/dashboard');

        $dashResponse->assertStatus(200);
        $dashResponse->assertJsonStructure([
            'success',
            'data' => [
                'teacher' => ['id', 'name', 'employee_code'],
                'stats' => ['assigned_classes_count', 'active_homework_count', 'pending_evaluations_count'],
                'assignments',
            ],
        ]);

        $perfResponse = $this->actingAs($this->mathTeacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/portal/teacher/performance/{$this->class10->id}/{$this->mathSubject->id}");

        $perfResponse->assertStatus(200);
        $perfResponse->assertJsonStructure([
            'success',
            'data' => [
                'class_id',
                'subject_id',
                'total_students',
                'students',
            ],
        ]);
        $this->assertEquals(1, $perfResponse->json('data.total_students'));
    }

    /**
     * Test 10: Strict Tenant Isolation on LMS and Portal records.
     */
    public function test_tenant_isolation_on_lms_records(): void
    {
        $tenantB = Tenant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Apex Academy',
            'slug' => 'apex-' . \Illuminate\Support\Str::random(5),
            'contact_email' => 'admin-' . \Illuminate\Support\Str::random(5) . '@apex.edu',
            'contact_phone' => '+919876543299',
            'status' => 'active',
        ]);

        $hw = Homework::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class10->id,
            'subject_id' => $this->mathSubject->id,
            'teacher_id' => $this->mathTeacherEmployee->id,
            'title' => 'Confidential School A Homework',
            'description' => 'Should not be seen by School B',
            'assigned_date' => Carbon::today()->format('Y-m-d'),
            'due_date' => Carbon::tomorrow()->format('Y-m-d'),
        ]);

        // 1. Attempt to switch tenant header without membership -> 403 Forbidden
        $crossTenantResponse = $this->actingAs($this->adminUser)
            ->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson("/api/v1/lms/homework/{$hw->id}");

        $crossTenantResponse->assertStatus(403);

        // 2. Legitimate Admin of Tenant B cannot see School A homework -> 404 Not Found
        $adminUserB = User::factory()->create(['first_name' => 'Apex', 'last_name' => 'Admin']);
        $adminUserB->assignRole('school_admin', $tenantB->id);
        TenantMembership::create([
            'tenant_id' => $tenantB->id,
            'user_id' => $adminUserB->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        $responseB = $this->actingAs($adminUserB)
            ->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson("/api/v1/lms/homework/{$hw->id}");

        $responseB->assertStatus(404);
    }
}
