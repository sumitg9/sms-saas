<?php

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();

    $this->tenant = Tenant::create([
        'name' => 'Attendance School ' . uniqid(),
        'slug' => 'att-school-' . uniqid(),
        'status' => 'active',
        'contact_email' => 'admin@att-school.test',
        'contact_phone' => '+919876543210',
    ]);

    $this->school = School::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SCH-ATT',
        'name' => 'Attendance Test School',
        'is_active' => true,
    ]);

    $this->branch = SchoolBranch::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_code' => 'BR-ATT',
        'name' => 'Main Branch',
        'is_active' => true,
    ]);

    $this->session = AcademicSession::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_active' => true,
        'status' => 'active',
    ]);

    $this->class = SchoolClass::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'name' => 'Grade 10',
        'code' => 'G10',
        'numeric_order' => 10,
    ]);

    $this->section = Section::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'class_id' => $this->class->id,
        'name' => 'A',
        'capacity' => 40,
    ]);

    $this->user = User::factory()->create(['email' => 'att-user-' . uniqid() . '@example.com']);
    TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'roles' => ['school_admin'],
        'is_active' => true,
    ]);

    // Create 2 test students
    $this->student1 = Student::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'admission_number' => 'ADM-001',
        'admission_date' => '2026-04-01',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'date_of_birth' => '2010-05-15',
        'gender' => 'male',
        'status' => 'enrolled',
    ]);

    $this->student2 = Student::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'admission_number' => 'ADM-002',
        'admission_date' => '2026-04-01',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'date_of_birth' => '2010-08-20',
        'gender' => 'female',
        'status' => 'enrolled',
    ]);

    $this->enrollment1 = StudentEnrollment::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'student_id' => $this->student1->id,
        'class_id' => $this->class->id,
        'section_id' => $this->section->id,
        'academic_session_id' => $this->session->id,
        'roll_number' => '101',
        'enrollment_date' => '2026-04-01',
        'status' => 'active',
    ]);

    $this->enrollment2 = StudentEnrollment::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'student_id' => $this->student2->id,
        'class_id' => $this->class->id,
        'section_id' => $this->section->id,
        'academic_session_id' => $this->session->id,
        'roll_number' => '102',
        'enrollment_date' => '2026-04-01',
        'status' => 'active',
    ]);
});

test('fetches attendance roster with enrolled students', function () {
    Sanctum::actingAs($this->user);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson("/api/v1/attendance/roster?branch_id={$this->branch->id}&class_id={$this->class->id}&section_id={$this->section->id}&date=2026-11-20");

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.total_students', 2);
});

test('batch marks student attendance without duplicate rows', function () {
    Sanctum::actingAs($this->user);

    $payload = [
        'branch_id' => $this->branch->id,
        'class_id' => $this->class->id,
        'section_id' => $this->section->id,
        'date' => '2026-11-20',
        'records' => [
            [
                'student_id' => $this->student1->id,
                'enrollment_id' => $this->enrollment1->id,
                'status' => 'present',
                'remarks' => 'On time',
            ],
            [
                'student_id' => $this->student2->id,
                'enrollment_id' => $this->enrollment2->id,
                'status' => 'absent',
                'remarks' => 'Fever',
            ],
        ],
    ];

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/attendance/mark', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'marked_count' => 2,
        ]);

    $this->assertDatabaseHas('student_attendance', [
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student1->id,
        'status' => 'present',
    ]);

    // Updating attendance on the same day should upsert without increasing row count
    $payload['records'][1]['status'] = 'present';
    $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/attendance/mark', $payload);

    $count = StudentAttendance::where('student_id', $this->student2->id)
        ->where('date', '2026-11-20')
        ->count();

    expect($count)->toBe(1);
});

test('computes student attendance summary correctly', function () {
    Sanctum::actingAs($this->user);

    // Mark 3 days
    StudentAttendance::create([
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student1->id,
        'enrollment_id' => $this->enrollment1->id,
        'branch_id' => $this->branch->id,
        'class_id' => $this->class->id,
        'section_id' => $this->section->id,
        'date' => '2026-11-18',
        'status' => 'present',
    ]);
    StudentAttendance::create([
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student1->id,
        'enrollment_id' => $this->enrollment1->id,
        'branch_id' => $this->branch->id,
        'class_id' => $this->class->id,
        'section_id' => $this->section->id,
        'date' => '2026-11-19',
        'status' => 'present',
    ]);
    StudentAttendance::create([
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student1->id,
        'enrollment_id' => $this->enrollment1->id,
        'branch_id' => $this->branch->id,
        'class_id' => $this->class->id,
        'section_id' => $this->section->id,
        'date' => '2026-11-20',
        'status' => 'absent',
    ]);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson("/api/v1/attendance/student/{$this->student1->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'total_working_days' => 3,
                'present_days' => 2,
                'absent_days' => 1,
            ],
        ]);
});
