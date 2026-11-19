<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Board;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAdmission;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentPromotion;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\BoardsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(BoardsSeeder::class);

        // 1. Create Tenant, School, Branch
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Apex Global Academy',
            'slug' => 'apex-global-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@apex.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex High School',
            'code' => 'APEX-01',
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

        // 2. Create Academic Session
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

        // 3. Create Class & Section
        $board = Board::where('code', 'CBSE')->first();

        $this->class = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board->id,
            'name' => 'Grade 10',
            'code' => 'G10',
            'order_index' => 10,
        ]);

        $this->section = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'max_capacity' => 40,
        ]);

        // 4. Create Admin User with school_admin role
        $this->adminUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'School',
            'last_name' => 'Administrator',
            'email' => 'admin@apex.edu',
            'password' => bcrypt('AdminPassword123!'),
            'is_active' => true,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_default' => true,
        ]);

        $this->adminUser->assignRole('school_admin', $this->tenant->id);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setSchool($this->school);
        app(TenantContext::class)->setBranch($this->branch);
        app(TenantContext::class)->setActiveMembership($membership);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    public function test_admission_application_lifecycle_submission_review_and_waitlist(): void
    {
        // 1. Submit Application
        $response = $this->actingAsAdmin()->postJson('/api/v1/admissions', [
            'applicant_first_name' => 'Rohan',
            'applicant_last_name' => 'Verma',
            'applied_class_id' => $this->class->id,
            'academic_session_id' => $this->session->id,
            'date_of_birth' => '2012-05-14',
            'gender' => 'male',
            'parent_name' => 'Vikram Verma',
            'parent_phone' => '+919876500001',
            'parent_email' => 'vikram.verma@example.com',
            'previous_school' => 'St. Xavier School',
        ]);

        $response->assertStatus(201);
        $admissionId = $response->json('data.id');
        $this->assertNotNull($admissionId);
        $this->assertStringStartsWith('ADM-', $response->json('data.application_number'));
        $this->assertEquals('applied', $response->json('data.status'));

        // 2. Review application -> move to under_review
        $reviewRes = $this->actingAsAdmin()->patchJson("/api/v1/admissions/{$admissionId}/status", [
            'status' => 'under_review',
            'remarks' => 'Documents verified, entrance test scheduled',
        ]);
        $reviewRes->assertStatus(200);
        $this->assertEquals('under_review', $reviewRes->json('data.status'));

        // 3. Move to waitlist with waitlist number
        $waitlistRes = $this->actingAsAdmin()->patchJson("/api/v1/admissions/{$admissionId}/status", [
            'status' => 'waitlisted',
            'waitlist_number' => 3,
            'remarks' => 'Section capacity reached, waitlisted at rank 3',
        ]);
        $waitlistRes->assertStatus(200);
        $this->assertEquals('waitlisted', $waitlistRes->json('data.status'));
        $this->assertEquals(3, $waitlistRes->json('data.inquiry_details.waitlist_number'));

        // 4. Verify waitlist listing endpoint
        $listRes = $this->actingAsAdmin()->getJson('/api/v1/admissions/waitlist');
        $listRes->assertStatus(200);
        $this->assertCount(1, $listRes->json('data'));
    }

    public function test_confirm_admission_creates_student_profile_guardian_and_enrollment(): void
    {
        // Create an application first
        $admission = StudentAdmission::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'application_number' => 'ADM-2026-9999',
            'applicant_first_name' => 'Siddharth',
            'applicant_last_name' => 'Mehta',
            'applied_class_id' => $this->class->id,
            'date_of_application' => '2026-04-05',
            'status' => 'approved',
            'inquiry_details' => [
                'date_of_birth' => '2012-08-20',
                'gender' => 'male',
                'parent_name' => 'Rajesh Mehta',
                'parent_phone' => '+919876500002',
                'parent_email' => 'rajesh.mehta@example.com',
                'parent_relation' => 'father',
                'address' => '12 MG Road, Bangalore',
            ],
        ]);

        // Confirm Admission
        $confirmRes = $this->actingAsAdmin()->postJson("/api/v1/admissions/{$admission->id}/confirm", [
            'section_id' => $this->section->id,
            'roll_number' => '10A-01',
            'blood_group' => 'B+',
        ]);

        $confirmRes->assertStatus(201);
        $studentId = $confirmRes->json('data.student.id');
        $this->assertNotNull($studentId);

        // Verify Student demographic record
        $student = Student::find($studentId);
        $this->assertNotNull($student);
        $this->assertEquals('Siddharth', $student->first_name);
        $this->assertEquals('Mehta', $student->last_name);
        $this->assertStringStartsWith('STU-', $student->admission_number);
        $this->assertEquals('active', $student->status);

        // Verify Guardian created and linked with rights
        $this->assertCount(1, $student->guardians);
        $guardian = $student->guardians->first();
        $this->assertEquals('+919876500002', $guardian->phone);
        $this->assertTrue((bool) $guardian->pivot->is_primary);
        $this->assertTrue((bool) $guardian->pivot->has_pickup_rights);

        // Verify StudentEnrollment created for the active session
        $this->assertCount(1, $student->enrollments);
        $enrollment = $student->enrollments->first();
        $this->assertEquals($this->class->id, $enrollment->class_id);
        $this->assertEquals($this->section->id, $enrollment->section_id);
        $this->assertEquals('10A-01', $enrollment->roll_number);
        $this->assertEquals('enrolled', $enrollment->status);

        // Verify Admission record marked enrolled
        $admission->refresh();
        $this->assertEquals('enrolled', $admission->status);
        $this->assertEquals($student->id, $admission->student_id);
    }

    public function test_student_360_profile_and_id_card_generation_with_qr_code(): void
    {
        // Direct Student Creation
        $createRes = $this->actingAsAdmin()->postJson('/api/v1/students', [
            'first_name' => 'Ananya',
            'last_name' => 'Deshmukh',
            'date_of_birth' => '2011-11-10',
            'gender' => 'female',
            'blood_group' => 'O+',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '10A-02',
            'guardian_name' => 'Sunil Deshmukh',
            'guardian_phone' => '+919876500003',
            'guardian_relation' => 'father',
            'allergies' => ['Peanuts', 'Pollen'],
            'chronic_conditions' => 'Mild Asthma',
        ]);

        $createRes->assertStatus(201);
        $studentId = $createRes->json('data.id');

        // Retrieve Student 360 profile
        $showRes = $this->actingAsAdmin()->getJson("/api/v1/students/{$studentId}");
        $showRes->assertStatus(200);
        $this->assertEquals('Ananya Deshmukh', $showRes->json('data.full_name'));
        $this->assertEquals('Grade 10', $showRes->json('data.current_enrollment.school_class.name'));
        $this->assertEquals('A', $showRes->json('data.current_enrollment.section.name'));
        $this->assertEquals('Mild Asthma', $showRes->json('data.medical_record.chronic_conditions'));

        // Generate ID Card with QR Verification
        $idCardRes = $this->actingAsAdmin()->getJson("/api/v1/students/{$studentId}/id-card");
        $idCardRes->assertStatus(200);
        $this->assertEquals('Ananya Deshmukh', $idCardRes->json('data.id_card.full_name'));
        $this->assertEquals('Grade 10', $idCardRes->json('data.id_card.class_name'));
        $this->assertNotEmpty($idCardRes->json('data.qr_verification.qr_data_string'));
        $this->assertNotEmpty($idCardRes->json('data.qr_verification.qr_encoded'));
        $this->assertStringContainsString("/verify/student/{$studentId}", $idCardRes->json('data.qr_verification.verification_url'));
    }

    public function test_one_parent_account_supports_multiple_children_and_blocks_unrelated_children(): void
    {
        // 1. Create Parent User and Guardian record
        $parentUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ramesh',
            'last_name' => 'Patil',
            'email' => 'ramesh.patil@example.com',
            'password' => bcrypt('ParentPassword123!'),
            'phone' => '+919876511111',
            'is_active' => true,
        ]);

        $parentUser->assignRole('parent', $this->tenant->id);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $parentUser->id,
            'role' => 'parent',
            'is_default' => true,
        ]);

        $guardian = Guardian::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $parentUser->id,
            'first_name' => 'Ramesh',
            'last_name' => 'Patil',
            'relation_type' => 'father',
            'phone' => '+919876511111',
            'email' => 'ramesh.patil@example.com',
        ]);

        // 2. Create Child 1 (Aarav) and link to Ramesh
        $child1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-0001',
            'admission_date' => '2026-04-01',
            'first_name' => 'Aarav',
            'last_name' => 'Patil',
            'gender' => 'male',
            'date_of_birth' => '2014-06-12',
            'status' => 'active',
        ]);
        StudentGuardian::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $child1->id,
            'guardian_id' => $guardian->id,
            'is_primary' => true,
        ]);
        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $child1->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'status' => 'enrolled',
        ]);

        // 3. Create Child 2 (Diya) and link to Ramesh (SIBLING)
        $child2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-0002',
            'admission_date' => '2026-04-01',
            'first_name' => 'Diya',
            'last_name' => 'Patil',
            'gender' => 'female',
            'date_of_birth' => '2016-09-18',
            'status' => 'active',
        ]);
        StudentGuardian::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $child2->id,
            'guardian_id' => $guardian->id,
            'is_primary' => true,
        ]);
        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $child2->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'status' => 'enrolled',
        ]);

        // 4. Create Unrelated Student (Kabir) linked to someone else
        $unrelatedChild = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-0003',
            'admission_date' => '2026-04-01',
            'first_name' => 'Kabir',
            'last_name' => 'Kapoor',
            'gender' => 'male',
            'date_of_birth' => '2014-01-01',
            'status' => 'active',
        ]);

        // 5. Test Parent calls /api/v1/guardians/my-children
        $parentHeaders = [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ];
        $myChildrenRes = $this->actingAs($parentUser, 'sanctum')
            ->withHeaders($parentHeaders)
            ->getJson('/api/v1/guardians/my-children');

        $myChildrenRes->assertStatus(200);
        $childrenData = $myChildrenRes->json('data');
        $this->assertCount(2, $childrenData, 'Parent must see both linked siblings');
        $names = collect($childrenData)->pluck('first_name')->all();
        $this->assertContains('Aarav', $names);
        $this->assertContains('Diya', $names);
        $this->assertNotContains('Kabir', $names);

        // 6. Test Policy: Parent can access own children, but is FORBIDDEN from unrelated child
        $child1Res = $this->actingAs($parentUser, 'sanctum')->withHeaders($parentHeaders)->getJson("/api/v1/students/{$child1->id}");
        $child1Res->assertStatus(200);

        $unrelatedRes = $this->actingAs($parentUser, 'sanctum')->withHeaders($parentHeaders)->getJson("/api/v1/students/{$unrelatedChild->id}");
        $unrelatedRes->assertStatus(403);
    }

    public function test_academic_progression_preserves_historical_enrollment_records(): void
    {
        // Create Next Academic Session 2027-2028
        $nextSession = AcademicSession::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2027-2028',
            'code' => 'AY2027-28',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'is_current' => false,
        ]);

        // Create Grade 11 Class & Section
        $board = Board::where('code', 'CBSE')->first();
        $grade11 = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board->id,
            'name' => 'Grade 11',
            'code' => 'G11',
            'order_index' => 11,
        ]);
        $secB = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $grade11->id,
            'name' => 'A',
            'max_capacity' => 40,
        ]);

        // Create Student with Grade 10 enrollment
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-0099',
            'admission_date' => '2026-04-01',
            'first_name' => 'Kavya',
            'last_name' => 'Iyer',
            'gender' => 'female',
            'date_of_birth' => '2010-03-15',
            'status' => 'active',
        ]);

        $prevEnrollment = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '10A-15',
            'enrollment_date' => '2026-04-01',
            'status' => 'enrolled',
        ]);

        // Promote Student to Grade 11 in next session
        $promoteRes = $this->actingAsAdmin()->postJson("/api/v1/students/{$student->id}/promote", [
            'to_session_id' => $nextSession->id,
            'to_class_id' => $grade11->id,
            'to_section_id' => $secB->id,
            'roll_number' => '11A-01',
            'status' => 'promoted',
            'remarks' => 'Passed Grade 10 CBSE Board with Distinction (94%)',
        ]);

        $promoteRes->assertStatus(200);

        // Verify Historical Integrity:
        // 1. Previous enrollment is NOT deleted or overwritten; marked 'promoted'
        $prevEnrollment->refresh();
        $this->assertEquals('promoted', $prevEnrollment->status);
        $this->assertEquals($this->class->id, $prevEnrollment->class_id);

        // 2. New enrollment exists for 2027-2028 session
        $this->assertCount(2, $student->enrollments);
        $newEnrollment = $student->enrollments()->where('academic_session_id', $nextSession->id)->first();
        $this->assertNotNull($newEnrollment);
        $this->assertEquals($grade11->id, $newEnrollment->class_id);
        $this->assertEquals($secB->id, $newEnrollment->section_id);
        $this->assertEquals('11A-01', $newEnrollment->roll_number);

        // 3. Promotion audit trail recorded
        $promotionAudit = StudentPromotion::where('student_id', $student->id)->first();
        $this->assertNotNull($promotionAudit);
        $this->assertEquals($this->session->id, $promotionAudit->from_session_id);
        $this->assertEquals($nextSession->id, $promotionAudit->to_session_id);
        $this->assertEquals('promoted', $promotionAudit->promotion_status);

        // 4. Retrieve complete timeline
        $timelineRes = $this->actingAsAdmin()->getJson("/api/v1/students/{$student->id}/timeline");
        $timelineRes->assertStatus(200);
        $this->assertCount(2, $timelineRes->json('data.enrollment_history'));
        $this->assertCount(1, $timelineRes->json('data.promotion_history'));
    }

    public function test_student_withdrawal_readmission_and_transfer_certificate(): void
    {
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-0050',
            'admission_date' => '2026-04-01',
            'first_name' => 'Manish',
            'last_name' => 'Tiwari',
            'gender' => 'male',
            'date_of_birth' => '2013-02-10',
            'status' => 'active',
        ]);

        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'status' => 'enrolled',
        ]);

        // 1. Withdraw student
        $withdrawRes = $this->actingAsAdmin()->postJson("/api/v1/students/{$student->id}/withdraw", [
            'exit_date' => '2026-09-30',
            'reason' => 'Family relocation to Pune',
        ]);
        $withdrawRes->assertStatus(200);
        $student->refresh();
        $this->assertEquals('withdrawn', $student->status);

        // 2. Re-admit student
        $reAdmitRes = $this->actingAsAdmin()->postJson("/api/v1/students/{$student->id}/re-admit", [
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '10A-45',
        ]);
        $reAdmitRes->assertStatus(200);
        $student->refresh();
        $this->assertEquals('active', $student->status);

        // 3. Issue Transfer Certificate (TC)
        $tcRes = $this->actingAsAdmin()->postJson("/api/v1/students/{$student->id}/transfer", [
            'application_date' => '2026-10-15',
            'issue_date' => '2026-10-20',
            'destination_school' => 'Delhi Public School Pune',
            'reason_for_leaving' => 'Parent transfer order',
            'clearance_status' => [
                'library' => true,
                'fees' => true,
                'laboratory' => true,
            ],
        ]);

        $tcRes->assertStatus(201);
        $this->assertStringStartsWith('TC-', $tcRes->json('data.tc_number'));
        $student->refresh();
        $this->assertEquals('transferred', $student->status);
    }

    public function test_bulk_import_system_template_preview_validation_and_processing(): void
    {
        // 1. Download template
        $templateRes = $this->actingAsAdmin()->getJson('/api/v1/students/import/template');
        $templateRes->assertStatus(200);
        $this->assertArrayHasKey('first_name', $templateRes->json('data.columns'));
        $this->assertArrayHasKey('admission_number', $templateRes->json('data.columns'));

        // 2. Preview with valid row and invalid duplicate row
        $existingStudent = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-EXISTING-01',
            'admission_date' => '2026-04-01',
            'first_name' => 'Existing',
            'last_name' => 'Student',
            'gender' => 'male',
            'date_of_birth' => '2015-01-01',
            'status' => 'active',
        ]);

        $rows = [
            // Valid row
            [
                'first_name' => 'Pooja',
                'middle_name' => '',
                'last_name' => 'Nair',
                'admission_number' => 'STU-NEW-001',
                'admission_date' => '2026-04-01',
                'date_of_birth' => '2014-04-12',
                'gender' => 'female',
                'blood_group' => 'A+',
                'class_name' => 'Grade 10',
                'section_name' => 'A',
                'roll_number' => '10A-30',
                'guardian_name' => 'Suresh Nair',
                'guardian_phone' => '+919876599991',
                'guardian_relation' => 'father',
                'current_address' => 'Kochi, Kerala',
                'emergency_contact_phone' => '+919876599991',
            ],
            // Invalid row: duplicate admission number
            [
                'first_name' => 'Duplicate',
                'last_name' => 'Test',
                'admission_number' => 'STU-EXISTING-01', // Collides with existing!
                'date_of_birth' => '2014-05-15',
                'gender' => 'male',
                'class_name' => 'Grade 10',
                'section_name' => 'A',
                'guardian_name' => 'Test Guardian',
                'guardian_phone' => '+919876599992',
            ],
        ];

        $previewRes = $this->actingAsAdmin()->postJson('/api/v1/students/import/preview', [
            'rows' => $rows,
            'academic_session_id' => $this->session->id,
        ]);

        $previewRes->assertStatus(200);
        $this->assertEquals(2, $previewRes->json('data.total_rows'));
        $this->assertEquals(1, $previewRes->json('data.valid_rows'));
        $this->assertEquals(1, $previewRes->json('data.invalid_rows'));
        $this->assertNotEmpty($previewRes->json('data.errors'));

        // 3. Process Import with only valid row
        $validOnly = [$rows[0]];
        $importRes = $this->actingAsAdmin()->postJson('/api/v1/students/import', [
            'rows' => $validOnly,
            'academic_session_id' => $this->session->id,
        ]);

        $importRes->assertStatus(201);
        $this->assertEquals(1, $importRes->json('data.processed_rows'));
        $this->assertEquals(0, $importRes->json('data.error_rows'));

        // Verify student exists in DB
        $imported = Student::where('admission_number', 'STU-NEW-001')->first();
        $this->assertNotNull($imported);
        $this->assertEquals('Pooja', $imported->first_name);
        $this->assertCount(1, $imported->guardians);
        $this->assertCount(1, $imported->enrollments);

        // 4. Test Export endpoint
        $exportRes = $this->actingAsAdmin()->getJson('/api/v1/students/export');
        $exportRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($exportRes->json('data')));
    }

    public function test_tenant_isolation_prevents_access_to_another_school_students(): void
    {
        // Create Tenant B
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Horizon Public School',
            'slug' => 'horizon-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@horizon.edu',
            'contact_phone' => '+919876543299',
            'status' => 'active',
        ]);
        $schoolB = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Horizon Secondary',
            'code' => 'HZ-01',
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

        // Student in Tenant B
        $studentB = Student::create([
            'tenant_id' => $tenantB->id,
            'school_id' => $schoolB->id,
            'branch_id' => $branchB->id,
            'admission_number' => 'STU-B-001',
            'admission_date' => '2026-04-01',
            'first_name' => 'TenantB',
            'last_name' => 'Student',
            'gender' => 'male',
            'date_of_birth' => '2015-05-05',
            'status' => 'active',
        ]);

        // TenantScope prevents route model binding from resolving Tenant B student in Tenant A context
        $res = $this->actingAsAdmin()->getJson("/api/v1/students/{$studentB->id}");
        $res->assertStatus(404);
    }
}
