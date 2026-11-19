<?php

use App\Jobs\TestTenantJob;
use App\Models\AcademicSession;
use App\Models\ClassTeacher;
use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\FileUpload;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\TeacherSubject;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Reset tenant context before each test
    app(TenantContext::class)->reset();
});

test('School A cannot access School B students via Eloquent and API endpoints', function () {
    // 1. Setup Tenant A and Tenant B
    $tenantA = Tenant::factory()->create(['name' => 'Greenwood High']);
    $schoolA = School::create(['tenant_id' => $tenantA->id, 'name' => 'Greenwood Main', 'code' => 'GW-1', 'is_active' => true]);
    $branchA = SchoolBranch::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'name' => 'Campus A', 'branch_code' => 'GWA', 'is_active' => true]);

    $tenantB = Tenant::factory()->create(['name' => 'Silverline Academy']);
    $schoolB = School::create(['tenant_id' => $tenantB->id, 'name' => 'Silverline Main', 'code' => 'SL-1', 'is_active' => true]);
    $branchB = SchoolBranch::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'name' => 'Campus B', 'branch_code' => 'SLB', 'is_active' => true]);

    // 2. Create Students for both
    $studentA = Student::create([
        'tenant_id' => $tenantA->id,
        'school_id' => $schoolA->id,
        'branch_id' => $branchA->id,
        'admission_number' => 'ADM-A-101',
        'admission_date' => '2026-04-01',
        'first_name' => 'Alice',
        'last_name' => 'Green',
        'date_of_birth' => '2016-01-15',
        'gender' => 'female',
        'status' => 'active',
    ]);

    $studentB = Student::create([
        'tenant_id' => $tenantB->id,
        'school_id' => $schoolB->id,
        'branch_id' => $branchB->id,
        'admission_number' => 'ADM-B-201',
        'admission_date' => '2026-04-01',
        'first_name' => 'Bob',
        'last_name' => 'Silver',
        'date_of_birth' => '2016-03-20',
        'gender' => 'male',
        'status' => 'active',
    ]);

    // 3. User belonging only to School A
    $userA = User::factory()->create();
    TenantMembership::create([
        'tenant_id' => $tenantA->id,
        'school_id' => $schoolA->id,
        'branch_id' => $branchA->id,
        'user_id' => $userA->id,
        'is_active' => true,
    ]);

    $adminRole = Role::firstOrCreate(['name' => 'school_admin'], ['display_name' => 'School Admin', 'is_system_defined' => true]);
    UserRole::create(['user_id' => $userA->id, 'role_id' => $adminRole->id, 'tenant_id' => $tenantA->id, 'branch_id' => $branchA->id]);

    // 4. Authenticate as User A and query API
    Sanctum::actingAs($userA);

    $response = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson('/api/v1/students');

    $response->assertOk()
        ->assertJsonFragment(['first_name' => 'Alice'])
        ->assertJsonMissing(['first_name' => 'Bob']);

    // Attempt direct IDOR access to School B's student
    $idorResponse = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson("/api/v1/students/{$studentB->id}");

    // TenantScope prevents route model binding from finding School B student in School A's context
    $idorResponse->assertStatus(404);
});

test('School A cannot access School B fee invoices', function () {
    $tenantA = Tenant::factory()->create();
    $schoolA = School::create(['tenant_id' => $tenantA->id, 'name' => 'School A', 'code' => 'SA', 'is_active' => true]);
    $branchA = SchoolBranch::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'name' => 'Branch A', 'branch_code' => 'BA', 'is_active' => true]);
    $sessionA = AcademicSession::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'branch_id' => $branchA->id, 'name' => '2026-A', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);
    $classA = SchoolClass::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'branch_id' => $branchA->id, 'name' => 'Grade 1', 'code' => 'G1A']);
    $sectionA = Section::create(['tenant_id' => $tenantA->id, 'branch_id' => $branchA->id, 'class_id' => $classA->id, 'name' => 'A']);
    $studentA = Student::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'branch_id' => $branchA->id, 'admission_number' => 'ADM-A-1', 'admission_date' => '2026-04-01', 'first_name' => 'Sam', 'last_name' => 'A', 'date_of_birth' => '2015-01-01', 'gender' => 'male']);
    $enrollmentA = StudentEnrollment::create(['tenant_id' => $tenantA->id, 'branch_id' => $branchA->id, 'student_id' => $studentA->id, 'academic_session_id' => $sessionA->id, 'class_id' => $classA->id, 'section_id' => $sectionA->id, 'enrollment_date' => '2026-04-01', 'status' => 'enrolled']);

    $invoiceA = FeeInvoice::create([
        'tenant_id' => $tenantA->id,
        'branch_id' => $branchA->id,
        'student_id' => $studentA->id,
        'enrollment_id' => $enrollmentA->id,
        'academic_session_id' => $sessionA->id,
        'invoice_number' => 'INV-A-001',
        'subtotal' => 5000,
        'total_amount' => 5000,
        'paid_amount' => 0,
        'balance_amount' => 5000,
        'due_date' => '2026-05-01',
        'status' => 'unpaid',
    ]);

    $tenantB = Tenant::factory()->create();
    $schoolB = School::create(['tenant_id' => $tenantB->id, 'name' => 'School B', 'code' => 'SB', 'is_active' => true]);
    $branchB = SchoolBranch::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'name' => 'Branch B', 'branch_code' => 'BB', 'is_active' => true]);
    $sessionB = AcademicSession::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'branch_id' => $branchB->id, 'name' => '2026-B', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);
    $classB = SchoolClass::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'branch_id' => $branchB->id, 'name' => 'Grade 1', 'code' => 'G1B']);
    $sectionB = Section::create(['tenant_id' => $tenantB->id, 'branch_id' => $branchB->id, 'class_id' => $classB->id, 'name' => 'B']);
    $studentB = Student::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'branch_id' => $branchB->id, 'admission_number' => 'ADM-B-1', 'admission_date' => '2026-04-01', 'first_name' => 'Tom', 'last_name' => 'B', 'date_of_birth' => '2015-01-01', 'gender' => 'male']);
    $enrollmentB = StudentEnrollment::create(['tenant_id' => $tenantB->id, 'branch_id' => $branchB->id, 'student_id' => $studentB->id, 'academic_session_id' => $sessionB->id, 'class_id' => $classB->id, 'section_id' => $sectionB->id, 'enrollment_date' => '2026-04-01', 'status' => 'enrolled']);

    $invoiceB = FeeInvoice::create([
        'tenant_id' => $tenantB->id,
        'branch_id' => $branchB->id,
        'student_id' => $studentB->id,
        'enrollment_id' => $enrollmentB->id,
        'academic_session_id' => $sessionB->id,
        'invoice_number' => 'INV-B-001',
        'subtotal' => 8000,
        'total_amount' => 8000,
        'paid_amount' => 0,
        'balance_amount' => 8000,
        'due_date' => '2026-05-01',
        'status' => 'unpaid',
    ]);

    $userA = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'branch_id' => $branchA->id, 'user_id' => $userA->id, 'is_active' => true]);
    $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant', 'is_system_defined' => true]);
    UserRole::create(['user_id' => $userA->id, 'role_id' => $accountantRole->id, 'tenant_id' => $tenantA->id, 'branch_id' => $branchA->id]);

    Sanctum::actingAs($userA);

    $response = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson('/api/v1/invoices');

    $response->assertOk()
        ->assertJsonFragment(['invoice_number' => 'INV-A-001'])
        ->assertJsonMissing(['invoice_number' => 'INV-B-001']);

    $idorInvoice = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson("/api/v1/invoices/{$invoiceB->id}");

    $idorInvoice->assertStatus(404);
});

test('School A cannot access School B documents or files', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'is_active' => true]);

    $fileA = FileUpload::create([
        'tenant_id' => $tenantA->id,
        'user_id' => $userA->id,
        'bucket_name' => 'documents',
        'file_path' => 'tenants/a/annual_report.pdf',
        'original_name' => 'annual_report.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'is_private' => true,
    ]);

    $fileB = FileUpload::create([
        'tenant_id' => $tenantB->id,
        'user_id' => null,
        'bucket_name' => 'documents',
        'file_path' => 'tenants/b/confidential_audit.pdf',
        'original_name' => 'confidential_audit.pdf',
        'file_size' => 2048,
        'mime_type' => 'application/pdf',
        'is_private' => true,
    ]);

    Sanctum::actingAs($userA);

    // Can access own tenant file
    $okResponse = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson("/api/v1/documents/{$fileA->id}");
    $okResponse->assertOk()->assertJsonFragment(['original_name' => 'annual_report.pdf']);

    // Cannot access School B's confidential document
    $forbiddenResponse = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson("/api/v1/documents/{$fileB->id}");
    $forbiddenResponse->assertStatus(404);
});

test('a teacher cannot access an unrelated school records or unassigned students', function () {
    $tenant = Tenant::factory()->create();
    $school = School::create(['tenant_id' => $tenant->id, 'name' => 'City Academy', 'code' => 'CA', 'is_active' => true]);
    $branch = SchoolBranch::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'name' => 'Main', 'branch_code' => 'MAIN', 'is_active' => true]);
    $session = AcademicSession::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'name' => '2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);

    $classMath = SchoolClass::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'name' => 'Grade 10', 'code' => 'G10']);
    $sectionAssigned = Section::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'class_id' => $classMath->id, 'name' => 'Section A']);
    $sectionUnassigned = Section::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'class_id' => $classMath->id, 'name' => 'Section B']);

    // Teacher setup
    $teacherUser = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'user_id' => $teacherUser->id, 'is_active' => true]);
    $teacherRole = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher', 'is_system_defined' => true]);
    UserRole::create(['user_id' => $teacherUser->id, 'role_id' => $teacherRole->id, 'tenant_id' => $tenant->id, 'branch_id' => $branch->id]);

    $dept = \App\Models\EmployeeDepartment::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'name' => 'Academics',
        'code' => 'ACAD',
    ]);

    $desig = \App\Models\EmployeeDesignation::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'department_id' => $dept->id,
        'title' => 'Senior Teacher',
        'code' => 'TCH',
    ]);

    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'user_id' => $teacherUser->id,
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employee_code' => 'EMP-TCH-001',
        'first_name' => 'Sarah',
        'last_name' => 'Connor',
        'gender' => 'female',
        'date_of_birth' => '1990-05-15',
        'email' => 'sarah.connor@example.com',
        'phone' => '9876543210',
        'date_of_joining' => '2024-06-01',
        'employment_status' => 'active',
    ]);

    // Assign teacher to Section A as Class Teacher
    ClassTeacher::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'employee_id' => $employee->id,
        'class_id' => $classMath->id,
        'section_id' => $sectionAssigned->id,
        'academic_session_id' => $session->id,
    ]);

    // Student in assigned Section A
    $assignedStudent = Student::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'admission_number' => 'ADM-101', 'admission_date' => '2026-04-01', 'first_name' => 'John', 'last_name' => 'Connor', 'date_of_birth' => '2010-02-01', 'gender' => 'male']);
    StudentEnrollment::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'student_id' => $assignedStudent->id, 'academic_session_id' => $session->id, 'class_id' => $classMath->id, 'section_id' => $sectionAssigned->id, 'enrollment_date' => '2026-04-01', 'status' => 'enrolled']);

    // Student in unassigned Section B
    $unassignedStudent = Student::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'admission_number' => 'ADM-102', 'admission_date' => '2026-04-01', 'first_name' => 'Kyle', 'last_name' => 'Reese', 'date_of_birth' => '2010-04-01', 'gender' => 'male']);
    StudentEnrollment::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'student_id' => $unassignedStudent->id, 'academic_session_id' => $session->id, 'class_id' => $classMath->id, 'section_id' => $sectionUnassigned->id, 'enrollment_date' => '2026-04-01', 'status' => 'enrolled']);

    // Set context to Tenant
    app(TenantContext::class)->setTenant($tenant);

    // Verify Teacher Policy directly
    expect(Gate::forUser($teacherUser)->allows('view', $assignedStudent))->toBeTrue()
        ->and(Gate::forUser($teacherUser)->allows('view', $unassignedStudent))->toBeFalse();
});

test('a parent cannot access another parent child', function () {
    $tenant = Tenant::factory()->create();
    $school = School::create(['tenant_id' => $tenant->id, 'name' => 'Apex School', 'code' => 'APX', 'is_active' => true]);
    $branch = SchoolBranch::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'name' => 'Apex Branch', 'branch_code' => 'APX-B', 'is_active' => true]);

    $parentUser1 = User::factory()->create();
    $parentUser2 = User::factory()->create();

    TenantMembership::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'user_id' => $parentUser1->id, 'is_active' => true]);
    TenantMembership::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'user_id' => $parentUser2->id, 'is_active' => true]);

    $parentRole = Role::firstOrCreate(['name' => 'parent'], ['display_name' => 'Parent', 'is_system_defined' => true]);
    UserRole::create(['user_id' => $parentUser1->id, 'role_id' => $parentRole->id, 'tenant_id' => $tenant->id, 'branch_id' => $branch->id]);
    UserRole::create(['user_id' => $parentUser2->id, 'role_id' => $parentRole->id, 'tenant_id' => $tenant->id, 'branch_id' => $branch->id]);

    $guardian1 = Guardian::create(['tenant_id' => $tenant->id, 'user_id' => $parentUser1->id, 'first_name' => 'Parent', 'last_name' => 'One', 'relation_type' => 'father', 'phone' => '9999900001']);
    $guardian2 = Guardian::create(['tenant_id' => $tenant->id, 'user_id' => $parentUser2->id, 'first_name' => 'Parent', 'last_name' => 'Two', 'relation_type' => 'mother', 'phone' => '9999900002']);

    $child1 = Student::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'admission_number' => 'CHD-1', 'admission_date' => '2026-04-01', 'first_name' => 'Child', 'last_name' => 'One', 'date_of_birth' => '2016-01-01', 'gender' => 'male']);
    $child2 = Student::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'branch_id' => $branch->id, 'admission_number' => 'CHD-2', 'admission_date' => '2026-04-01', 'first_name' => 'Child', 'last_name' => 'Two', 'date_of_birth' => '2016-02-01', 'gender' => 'female']);

    StudentGuardian::create(['tenant_id' => $tenant->id, 'student_id' => $child1->id, 'guardian_id' => $guardian1->id, 'is_primary_contact' => true]);
    StudentGuardian::create(['tenant_id' => $tenant->id, 'student_id' => $child2->id, 'guardian_id' => $guardian2->id, 'is_primary_contact' => true]);

    app(TenantContext::class)->setTenant($tenant);

    // Parent 1 can access Child 1, but NEVER Child 2
    expect(Gate::forUser($parentUser1)->allows('view', $child1))->toBeTrue()
        ->and(Gate::forUser($parentUser1)->allows('view', $child2))->toBeFalse();

    // Parent 2 can access Child 2, but NEVER Child 1
    expect(Gate::forUser($parentUser2)->allows('view', $child2))->toBeTrue()
        ->and(Gate::forUser($parentUser2)->allows('view', $child1))->toBeFalse();
});

test('a user cannot switch to an unauthorized tenant via X-Tenant-ID header', function () {
    $tenantA = Tenant::factory()->create(['name' => 'Legitimate School']);
    $tenantB = Tenant::factory()->create(['name' => 'Target School to Attack']);

    $user = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'is_active' => true]);

    Sanctum::actingAs($user);

    // Legit access to Tenant A
    $allowedResponse = $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->getJson('/api/v1/tenancy/current');
    $allowedResponse->assertOk()->assertJsonFragment(['tenant_id' => $tenantA->id]);

    // Malicious switch attempt to Tenant B
    $blockedResponse = $this->withHeaders(['X-Tenant-ID' => $tenantB->id])
        ->getJson('/api/v1/tenancy/current');

    $blockedResponse->assertStatus(403)
        ->assertJsonFragment(['code' => 'FORBIDDEN_TENANT_ACCESS']);
});

test('a background job cannot process another tenant records unintentionally', function () {
    $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $schoolA = School::create(['tenant_id' => $tenantA->id, 'name' => 'School A', 'code' => 'A', 'is_active' => true]);
    $branchA = SchoolBranch::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'name' => 'Branch A', 'branch_code' => 'BA', 'is_active' => true]);

    $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
    $schoolB = School::create(['tenant_id' => $tenantB->id, 'name' => 'School B', 'code' => 'B', 'is_active' => true]);
    $branchB = SchoolBranch::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'name' => 'Branch B', 'branch_code' => 'BB', 'is_active' => true]);

    $studentA = Student::create(['tenant_id' => $tenantA->id, 'school_id' => $schoolA->id, 'branch_id' => $branchA->id, 'admission_number' => 'JOB-A-1', 'admission_date' => '2026-04-01', 'first_name' => 'StudentA', 'last_name' => 'Job', 'date_of_birth' => '2016-01-01', 'gender' => 'male']);
    $studentB = Student::create(['tenant_id' => $tenantB->id, 'school_id' => $schoolB->id, 'branch_id' => $branchB->id, 'admission_number' => 'JOB-B-1', 'admission_date' => '2026-04-01', 'first_name' => 'StudentB', 'last_name' => 'Job', 'date_of_birth' => '2016-01-01', 'gender' => 'female']);

    // Set context to Tenant A and dispatch Job
    $context = app(TenantContext::class);
    $context->setTenant($tenantA);

    $job = new TestTenantJob();

    // Context changes in worker thread to Tenant B (simulating queue worker reusing processes)
    $context->setTenant($tenantB);

    // Execute job through its middleware
    $middleware = $job->middleware()[0];
    $middleware->handle($job, function ($j) use ($context) {
        $j->handle($context);
    });

    // The job was captured with Tenant A, so it must ONLY have processed Student A
    expect($job->executedTenantId)->toBe($tenantA->id)
        ->and($job->processedStudentIds)->toContain($studentA->id)
        ->and($job->processedStudentIds)->not->toContain($studentB->id);
});

test('subdomain resolution correctly identifies school tenant', function () {
    $slug = 'oakridge-' . uniqid();
    $tenant = Tenant::factory()->create([
        'slug' => $slug,
        'name' => 'Oakridge Campus',
        'status' => 'active',
    ]);

    $resolver = app(\App\Services\Tenancy\TenantResolver::class);

    // Mock request with subdomain
    $request = \Illuminate\Http\Request::create("http://{$slug}.erp.local/api/v1/health");

    $resolvedTenant = $resolver->resolve($request);

    expect($resolvedTenant)->not->toBeNull()
        ->and($resolvedTenant->id)->toBe($tenant->id)
        ->and($resolvedTenant->name)->toBe('Oakridge Campus');
});

test('suspended tenant requests are rejected with 403 TENANT_SUSPENDED', function () {
    $suspendedTenant = Tenant::factory()->create([
        'name' => 'Suspended School',
        'status' => 'suspended',
        'suspension_reason' => 'Non-payment of annual licensing fee.',
    ]);

    $user = User::factory()->create();
    TenantMembership::create([
        'tenant_id' => $suspendedTenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->withHeaders(['X-Tenant-ID' => $suspendedTenant->id])
        ->getJson('/api/v1/tenancy/current');

    $response->assertStatus(403)
        ->assertJsonFragment([
            'code' => 'TENANT_SUSPENDED',
            'message' => 'Non-payment of annual licensing fee.',
        ]);
});

test('platform super admin can access any tenant for support and audit', function () {
    $tenant = Tenant::factory()->create(['name' => 'Any School']);

    $superAdmin = User::factory()->create([
        'is_platform_admin' => true,
    ]);

    Sanctum::actingAs($superAdmin);

    // Super admin does NOT need tenant membership to access tenant for support
    $response = $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->getJson('/api/v1/tenancy/current');

    $response->assertOk()
        ->assertJsonFragment([
            'tenant_id' => $tenant->id,
            'is_super_admin' => true,
        ]);
});

test('TenantContext executeAsTenant properly restores previous context state', function () {
    $tenantA = Tenant::factory()->create(['name' => 'Tenant Alpha']);
    $tenantB = Tenant::factory()->create(['name' => 'Tenant Beta']);

    $context = app(TenantContext::class);
    $context->setTenant($tenantA);

    expect($context->getTenantId())->toBe($tenantA->id);

    $result = $context->executeAsTenant($tenantB, function () use ($context, $tenantB) {
        expect($context->getTenantId())->toBe($tenantB->id);
        return 'executed_in_b';
    });

    expect($result)->toBe('executed_in_b')
        ->and($context->getTenantId())->toBe($tenantA->id);
});
