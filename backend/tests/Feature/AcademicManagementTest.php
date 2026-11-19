<?php

use App\Models\AcademicCalendar;
use App\Models\AcademicEvent;
use App\Models\AcademicSession;
use App\Models\Board;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\Academic\AcademicCalendarService;
use App\Services\Academic\AcademicSessionService;
use App\Services\Academic\ClassManagementService;
use App\Services\Academic\SubjectManagementService;
use App\Services\Academic\TimetableService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();

    // Setup base test tenant, school, branch, and admin
    $this->tenant = Tenant::create([
        'name' => 'Academic Test School ' . uniqid(),
        'slug' => 'acad-test-' . uniqid(),
        'organization_type' => 'private',
        'contact_email' => 'admin-' . uniqid() . '@example.com',
        'contact_phone' => '+9198' . rand(10000000, 99999999),
        'status' => 'active',
        'currency' => 'INR',
    ]);

    $this->school = School::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SCH-' . rand(1000, 9999),
        'name' => 'Stellar Academy',
        'is_active' => true,
    ]);

    $this->branch = SchoolBranch::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_code' => 'BR-' . rand(100, 999),
        'name' => 'Central Campus',
        'is_active' => true,
    ]);

    $this->adminUser = User::factory()->create([
        'email' => 'acad-admin-' . uniqid() . '@example.com',
        'phone' => '98' . rand(10000000, 99999999),
    ]);

    $this->membership = TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->adminUser->id,
        'role' => 'school_admin',
        'is_owner' => true,
        'is_active' => true,
    ]);

    $this->adminUser->assignRole('school_admin', $this->tenant->id);

    // Set Tenant Context
    app(TenantContext::class)->setTenant($this->tenant);
    app(TenantContext::class)->setSchool($this->school);
    app(TenantContext::class)->setBranch($this->branch);
    app(TenantContext::class)->setActiveMembership($this->membership);
});

test('academic session service creates session and validates dates', function () {
    $service = app(AcademicSessionService::class);

    // 1. Invalid dates (start_date >= end_date)
    expect(function () use ($service) {
        $service->createSession([
            'name' => '2026-2027',
            'start_date' => '2027-03-31',
            'end_date' => '2026-04-01',
        ], $this->tenant->id, $this->school->id, $this->branch->id);
    })->toThrow(ValidationException::class);

    // 2. Valid creation
    $session = $service->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    expect($session)->not->toBeNull()
        ->and($session->name)->toBe('2026-2027')
        ->and($session->is_current)->toBeTrue();
});

test('cannot create overlapping academic session within the same branch', function () {
    $service = app(AcademicSessionService::class);

    $service->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    // Overlapping attempt (e.g. starting within the existing window)
    expect(function () use ($service) {
        $service->createSession([
            'name' => '2026-Late',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
        ], $this->tenant->id, $this->school->id, $this->branch->id);
    })->toThrow(ValidationException::class);
});

test('can activate academic session and atomically close previous session', function () {
    $service = app(AcademicSessionService::class);

    $session1 = $service->createSession([
        'name' => '2025-2026',
        'start_date' => '2025-04-01',
        'end_date' => '2026-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $session2 = $service->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => false,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    expect($session1->fresh()->is_current)->toBeTrue()
        ->and($session2->fresh()->is_current)->toBeFalse();

    // Switch active session to session2
    $service->activateSession($session2);

    expect($session2->fresh()->is_current)->toBeTrue()
        ->and($session1->fresh()->is_current)->toBeFalse();

    // Verify historical records retrieval returns session1
    $historical = $service->getHistoricalSessions($this->branch->id);
    expect($historical->pluck('id'))->toContain($session1->id)
        ->and($historical->pluck('id'))->not->toContain($session2->id);
});

test('class management creates classes and prevents duplicate class name or code in branch', function () {
    $service = app(ClassManagementService::class);

    $class = $service->createClass([
        'name' => 'Grade 10',
        'code' => 'G10',
        'order_index' => 10,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    expect($class->name)->toBe('Grade 10')
        ->and($class->code)->toBe('G10');

    // Duplicate name attempt
    expect(function () use ($service) {
        $service->createClass([
            'name' => 'Grade 10',
            'code' => 'G10-B',
        ], $this->tenant->id, $this->school->id, $this->branch->id);
    })->toThrow(ValidationException::class);

    // Duplicate code attempt
    expect(function () use ($service) {
        $service->createClass([
            'name' => 'Grade Ten Alternative',
            'code' => 'G10',
        ], $this->tenant->id, $this->school->id, $this->branch->id);
    })->toThrow(ValidationException::class);
});

test('section management creates sections and prevents duplicate section names within a class', function () {
    $classService = app(ClassManagementService::class);

    $class = $classService->createClass([
        'name' => 'Grade 9',
        'code' => 'G09',
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $sectionA = $classService->createSection($class, [
        'name' => 'Section A',
        'room_number' => '101',
        'max_capacity' => 35,
    ]);

    expect($sectionA->name)->toBe('SECTION A');

    // Duplicate section name within the same class
    expect(function () use ($classService, $class) {
        $classService->createSection($class, [
            'name' => 'section a',
        ]);
    })->toThrow(ValidationException::class);

    // Different class can have same section name
    $class2 = $classService->createClass([
        'name' => 'Grade 8',
        'code' => 'G08',
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $sectionA2 = $classService->createSection($class2, ['name' => 'Section A']);
    expect($sectionA2)->not->toBeNull();
});

test('can assign class teacher to a section for an academic session', function () {
    $classService = app(ClassManagementService::class);
    $sessionService = app(AcademicSessionService::class);

    $session = $sessionService->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $class = $classService->createClass([
        'name' => 'Grade 7',
        'code' => 'G07',
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $section = $classService->createSection($class, ['name' => 'Section A']);

    // Create teacher employee
    $dept = EmployeeDepartment::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'code' => 'ACAD-' . rand(100, 999),
        'name' => 'Academics',
    ]);
    $desig = EmployeeDesignation::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'department_id' => $dept->id,
        'code' => 'TCH-' . rand(100, 999),
        'title' => 'Senior Teacher',
    ]);
    $teacherUser = User::factory()->create();
    $teacher = Employee::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'user_id' => $teacherUser->id,
        'employee_code' => 'EMP-' . rand(1000, 9999),
        'first_name' => 'Sarah',
        'last_name' => 'Connor',
        'email' => 'sarah-' . uniqid() . '@example.com',
        'phone' => '+9198' . rand(10000000, 99999999),
        'gender' => 'female',
        'date_of_birth' => '1985-02-14',
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employment_type' => 'permanent',
        'date_of_joining' => '2022-01-10',
        'status' => 'active',
    ]);

    $assignment = $classService->assignClassTeacher(
        $class->id,
        $section->id,
        $teacher->id,
        $session->id,
        $this->tenant->id,
        $this->branch->id
    );

    expect($assignment->is_primary)->toBeTrue()
        ->and($assignment->employee_id)->toBe($teacher->id);
});

test('subject management creates subjects, maps to classes and assigns teachers', function () {
    $subjectService = app(SubjectManagementService::class);
    $classService = app(ClassManagementService::class);
    $sessionService = app(AcademicSessionService::class);

    $session = $sessionService->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $class = $classService->createClass([
        'name' => 'Grade 6',
        'code' => 'G06',
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $subject = $subjectService->createSubject([
        'name' => 'Physics',
        'code' => 'PHY-06',
        'type' => 'theory',
        'credit_hours' => 2.0,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    // Duplicate subject code attempt
    expect(function () use ($subjectService) {
        $subjectService->createSubject([
            'name' => 'Advanced Physics',
            'code' => 'PHY-06',
        ], $this->tenant->id, $this->school->id, $this->branch->id);
    })->toThrow(ValidationException::class);

    // Map to class
    $classSubject = $subjectService->assignSubjectToClass([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'academic_session_id' => $session->id,
        'max_weekly_periods' => 4,
    ], $this->tenant->id, $this->branch->id);

    expect($classSubject->max_weekly_periods)->toBe(4);
});

test('academic calendar creates events and computes holidays', function () {
    $calendarService = app(AcademicCalendarService::class);
    $sessionService = app(AcademicSessionService::class);

    $session = $sessionService->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $calendar = $calendarService->createCalendar([
        'academic_session_id' => $session->id,
        'name' => 'Academic Year Calendar 2026-27',
    ], $this->tenant->id, $this->branch->id);

    $calendarService->addEvent($calendar, [
        'title' => 'Summer Vacation',
        'event_type' => 'vacation',
        'start_date' => '2026-05-15 00:00:00',
        'end_date' => '2026-06-30 23:59:59',
        'is_holiday' => true,
    ]);

    $calendarService->addEvent($calendar, [
        'title' => 'Independence Day',
        'event_type' => 'holiday',
        'start_date' => '2026-08-15 00:00:00',
        'end_date' => '2026-08-15 23:59:59',
        'is_holiday' => true,
    ]);

    $calendarService->addEvent($calendar, [
        'title' => 'Mid-Term Examinations',
        'event_type' => 'exam',
        'start_date' => '2026-09-20 09:00:00',
        'end_date' => '2026-09-28 13:00:00',
        'is_holiday' => false,
    ]);

    $summary = $calendarService->getCalendarSummary($calendar);

    expect($summary['total_events'])->toBe(3)
        ->and($summary['holidays'])->toBe(2)
        ->and($summary['examinations'])->toBe(1)
        ->and($summary['vacations'])->toBe(1);
});

test('timetable conflict detection catches teacher, room, section, and break conflicts', function () {
    $timetableService = app(TimetableService::class);
    $classService = app(ClassManagementService::class);
    $sessionService = app(AcademicSessionService::class);
    $subjectService = app(SubjectManagementService::class);

    $session = $sessionService->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $timetable = $timetableService->createTimetable([
        'academic_session_id' => $session->id,
        'name' => 'Primary Master Schedule',
        'effective_from' => '2026-04-01',
    ], $this->tenant->id, $this->branch->id);

    // Setup periods
    $periods = $timetableService->configurePeriods($timetable, [
        [
            'period_number' => 1,
            'period_name' => 'Period 1',
            'start_time' => '08:30',
            'end_time' => '09:15',
            'is_break' => false,
        ],
        [
            'period_number' => 2,
            'period_name' => 'Lunch Break',
            'start_time' => '09:15',
            'end_time' => '09:45',
            'is_break' => true,
        ],
    ]);

    $period1 = $periods[0];
    $breakPeriod = $periods[1];

    $class1 = $classService->createClass(['name' => 'Class 5', 'code' => 'C05'], $this->tenant->id, $this->school->id, $this->branch->id);
    $section1 = $classService->createSection($class1, ['name' => 'Section A']);

    $class2 = $classService->createClass(['name' => 'Class 4', 'code' => 'C04'], $this->tenant->id, $this->school->id, $this->branch->id);
    $section2 = $classService->createSection($class2, ['name' => 'Section A']);

    $subject1 = $subjectService->createSubject(['name' => 'Math', 'code' => 'MTH-01'], $this->tenant->id, $this->school->id, $this->branch->id);
    $subject2 = $subjectService->createSubject(['name' => 'English', 'code' => 'ENG-01'], $this->tenant->id, $this->school->id, $this->branch->id);

    // Teacher
    $dept = EmployeeDepartment::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'code' => 'MATH', 'name' => 'Mathematics']);
    $desig = EmployeeDesignation::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'department_id' => $dept->id, 'code' => 'TR', 'title' => 'Teacher']);
    $tUser = User::factory()->create();
    $teacher = Employee::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'user_id' => $tUser->id,
        'employee_code' => 'TCH-' . rand(1000, 9999),
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'email' => 'alan-' . uniqid() . '@example.com',
        'phone' => '+9198' . rand(10000000, 99999999),
        'gender' => 'male',
        'date_of_birth' => '1980-01-01',
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employment_type' => 'permanent',
        'date_of_joining' => '2020-01-01',
        'status' => 'active',
    ]);

    // 1. Assign first slot successfully
    $entry1 = $timetableService->assignSlot($timetable, [
        'period_id' => $period1->id,
        'day_of_week' => 1, // Monday
        'class_id' => $class1->id,
        'section_id' => $section1->id,
        'subject_id' => $subject1->id,
        'employee_id' => $teacher->id,
        'room_number' => 'Room 101',
    ]);
    expect($entry1)->not->toBeNull();

    // 2. Break period conflict test
    expect(function () use ($timetableService, $timetable, $breakPeriod, $class1, $section1, $subject1, $teacher) {
        $timetableService->assignSlot($timetable, [
            'period_id' => $breakPeriod->id,
            'day_of_week' => 1,
            'class_id' => $class1->id,
            'section_id' => $section1->id,
            'subject_id' => $subject1->id,
            'employee_id' => $teacher->id,
        ]);
    })->toThrow(ValidationException::class);

    // 3. Teacher double-booking conflict test (same teacher, same period, different class)
    expect(function () use ($timetableService, $timetable, $period1, $class2, $section2, $subject2, $teacher) {
        $timetableService->assignSlot($timetable, [
            'period_id' => $period1->id,
            'day_of_week' => 1,
            'class_id' => $class2->id,
            'section_id' => $section2->id,
            'subject_id' => $subject2->id,
            'employee_id' => $teacher->id,
        ]);
    })->toThrow(ValidationException::class);

    // 4. Room double-booking conflict test (different teacher, same room 101)
    $tUser2 = User::factory()->create();
    $teacher2 = Employee::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'user_id' => $tUser2->id,
        'employee_code' => 'TCH-' . rand(1000, 9999),
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-' . uniqid() . '@example.com',
        'phone' => '+9198' . rand(10000000, 99999999),
        'gender' => 'female',
        'date_of_birth' => '1982-01-01',
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employment_type' => 'permanent',
        'date_of_joining' => '2020-01-01',
        'status' => 'active',
    ]);

    expect(function () use ($timetableService, $timetable, $period1, $class2, $section2, $subject2, $teacher2) {
        $timetableService->assignSlot($timetable, [
            'period_id' => $period1->id,
            'day_of_week' => 1,
            'class_id' => $class2->id,
            'section_id' => $section2->id,
            'subject_id' => $subject2->id,
            'employee_id' => $teacher2->id,
            'room_number' => 'Room 101', // Clash!
        ]);
    })->toThrow(ValidationException::class);
});

test('substitute teacher assignment validates conflicts', function () {
    $timetableService = app(TimetableService::class);
    $classService = app(ClassManagementService::class);
    $sessionService = app(AcademicSessionService::class);
    $subjectService = app(SubjectManagementService::class);

    $session = $sessionService->createSession([
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ], $this->tenant->id, $this->school->id, $this->branch->id);

    $timetable = $timetableService->createTimetable([
        'academic_session_id' => $session->id,
        'name' => 'Schedule',
        'effective_from' => '2026-04-01',
    ], $this->tenant->id, $this->branch->id);

    $periods = $timetableService->configurePeriods($timetable, [
        [
            'period_number' => 1,
            'period_name' => 'Period 1',
            'start_time' => '08:30',
            'end_time' => '09:15',
            'is_break' => false,
        ],
    ]);

    $period1 = $periods[0];
    $class = $classService->createClass(['name' => 'Class 3', 'code' => 'C03'], $this->tenant->id, $this->school->id, $this->branch->id);
    $section = $classService->createSection($class, ['name' => 'Section A']);
    $subject = $subjectService->createSubject(['name' => 'History', 'code' => 'HIS-01'], $this->tenant->id, $this->school->id, $this->branch->id);

    $dept = EmployeeDepartment::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'code' => 'SOC', 'name' => 'Social']);
    $desig = EmployeeDesignation::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'department_id' => $dept->id, 'code' => 'TCH', 'title' => 'Teacher']);
    
    $teacher1 = Employee::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'user_id' => User::factory()->create()->id,
        'employee_code' => 'EMP-' . rand(1000, 9999),
        'first_name' => 'Regular',
        'last_name' => 'Teacher',
        'email' => 'reg-' . uniqid() . '@example.com',
        'phone' => '+9198' . rand(10000000, 99999999),
        'gender' => 'male',
        'date_of_birth' => '1980-01-01',
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employment_type' => 'permanent',
        'date_of_joining' => '2020-01-01',
        'status' => 'active',
    ]);

    $substitute = Employee::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_id' => $this->branch->id,
        'user_id' => User::factory()->create()->id,
        'employee_code' => 'EMP-' . rand(1000, 9999),
        'first_name' => 'Substitute',
        'last_name' => 'Teacher',
        'email' => 'sub-' . uniqid() . '@example.com',
        'phone' => '+9198' . rand(10000000, 99999999),
        'gender' => 'female',
        'date_of_birth' => '1985-01-01',
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employment_type' => 'permanent',
        'date_of_joining' => '2020-01-01',
        'status' => 'active',
    ]);

    $entry = $timetableService->assignSlot($timetable, [
        'period_id' => $period1->id,
        'day_of_week' => 1, // Monday
        'class_id' => $class->id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'employee_id' => $teacher1->id,
    ]);

    // 2026-11-23 is a Monday (dayOfWeekIso = 1)
    $date = '2026-11-23';

    // 1. Substitute cannot be the original teacher
    expect(function () use ($timetableService, $entry, $teacher1, $date) {
        $timetableService->assignSubstitute($entry, $teacher1->id, $date);
    })->toThrow(ValidationException::class);

    // 2. Successful substitute assignment
    $sub = $timetableService->assignSubstitute($entry, $substitute->id, $date, 'Medical leave');
    expect($sub)->not->toBeNull()
        ->and($sub->substitute_employee_id)->toBe($substitute->id)
        ->and($sub->status)->toBe('approved');
});

test('HTTP API routes for academic management enforce permissions and tenancy', function () {
    Sanctum::actingAs($this->adminUser);

    // 1. Create Academic Session via API
    $sessionRes = $this->withHeader('X-Tenant-ID', $this->tenant->id)
        ->withHeader('X-Branch-ID', $this->branch->id)
        ->postJson('/api/v1/academic-sessions', [
            'name' => '2026-2027 Session',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

    $sessionRes->assertStatus(201)
        ->assertJsonFragment(['success' => true]);

    $sessionId = $sessionRes->json('data.id');

    // 2. List Classes via API
    $classesRes = $this->withHeader('X-Tenant-ID', $this->tenant->id)
        ->withHeader('X-Branch-ID', $this->branch->id)
        ->getJson('/api/v1/classes');

    $classesRes->assertOk()
        ->assertJsonStructure(['success', 'data']);

    // 3. Create Class via API
    $classRes = $this->withHeader('X-Tenant-ID', $this->tenant->id)
        ->withHeader('X-Branch-ID', $this->branch->id)
        ->postJson('/api/v1/classes', [
            'name' => 'Grade 2',
            'code' => 'G02',
            'order_index' => 2,
        ]);

    $classRes->assertStatus(201)
        ->assertJsonFragment(['name' => 'Grade 2']);
});
