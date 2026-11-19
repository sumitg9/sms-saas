<?php

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('all 133 required database tables exist in the public PostgreSQL schema', function () {
    $expectedTables = [
        'academic_calendars', 'academic_events', 'academic_sessions', 'announcement_acknowledgements',
        'announcements', 'assessment_components', 'attendance_corrections', 'attendance_sessions',
        'audit_logs', 'boards', 'book_copies', 'book_issues', 'book_reservations',
        'cache', 'cache_locks', 'class_levels', 'class_subjects', 'class_teachers', 'classes',
        'clinic_visits', 'coupons', 'employee_attendance', 'employee_contracts', 'employee_departments',
        'employee_designations', 'employee_documents', 'employee_leaves', 'employees',
        'event_participants', 'exam_results', 'exam_schedules', 'exam_subjects', 'examination_types',
        'examinations', 'expense_categories', 'expenses', 'export_jobs', 'failed_jobs',
        'fee_categories', 'fee_concessions', 'fee_fines', 'fee_invoice_items', 'fee_invoices',
        'fee_payment_allocations', 'fee_payments', 'fee_refunds', 'fee_structure_items',
        'fee_structures', 'file_uploads', 'financial_accounts', 'grade_ranges', 'grading_schemes',
        'guardians', 'hostel_allocations', 'hostel_buildings', 'hostel_rooms', 'import_jobs',
        'inventory_categories', 'inventory_issues', 'inventory_items', 'inventory_purchases',
        'inventory_vendors', 'job_batches', 'jobs', 'leave_applications', 'library_books',
        'login_histories', 'medical_conditions', 'message_conversations', 'messages', 'migrations',
        'notification_logs', 'notification_templates', 'notifications', 'password_reset_tokens',
        'payment_transactions', 'payment_webhook_events', 'permissions', 'personal_access_tokens',
        'plan_features', 'report_card_templates', 'report_cards', 'result_corrections',
        'result_publications', 'role_permissions', 'roles', 'school_branches', 'school_events',
        'schools', 'sections', 'sessions', 'staff_attendance', 'student_admissions',
        'student_assessments', 'student_attendance', 'student_documents', 'student_enrollments',
        'student_fee_assignments', 'student_gate_passes', 'student_guardians', 'student_marks',
        'student_medical_records', 'student_promotions', 'student_transfers',
        'student_transport_allocations', 'students', 'subject_groups', 'subjects',
        'subscription_invoices', 'subscription_payments', 'subscription_plans', 'subscriptions',
        'support_tickets', 'system_settings', 'teacher_subjects', 'tenant_domains',
        'tenant_memberships', 'tenant_settings', 'tenants', 'ticket_messages', 'timetable_entries',
        'timetable_periods', 'timetables', 'transport_drivers', 'transport_routes', 'transport_stops',
        'transport_vehicles', 'usage_records', 'user_roles', 'user_sessions', 'users',
        'visitor_passes', 'visitors',
    ];

    expect(count($expectedTables))->toBe(133);

    foreach ($expectedTables as $tableName) {
        expect(Schema::hasTable($tableName))->toBeTrue("Table {$tableName} does not exist in the database.");
    }
});

test('all business domain tables use UUID primary keys', function () {
    $tablesToCheck = [
        'tenants', 'schools', 'school_branches', 'subscription_plans', 'subscriptions',
        'users', 'roles', 'permissions', 'academic_sessions', 'classes', 'sections',
        'students', 'student_enrollments', 'guardians', 'employees', 'fee_structures',
        'fee_invoices', 'fee_payments', 'examinations', 'announcements',
    ];

    foreach ($tablesToCheck as $table) {
        $columnType = DB::selectOne("
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
              AND table_name = ? 
              AND column_name = 'id'
        ", [$table]);

        expect($columnType)->not->toBeNull("Table {$table} has no column named id.")
            ->and($columnType->data_type)->toBe('uuid', "Table {$table}.id is not of type uuid.");
    }
});

test('tenant isolation correctly scopes queries and prevents cross-tenant data leakage', function () {
    // 1. Create two independent tenants using factories
    $tenantA = Tenant::factory()->create([
        'name' => 'Oakridge International',
    ]);

    $tenantB = Tenant::factory()->create([
        'name' => 'St. Xavier High School',
    ]);

    // 2. Create schools and branches for each tenant
    $schoolA = School::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Oakridge Main Campus',
        'code' => 'OAK-' . rand(100, 999),
        'is_active' => true,
    ]);

    $branchA = SchoolBranch::create([
        'tenant_id' => $tenantA->id,
        'school_id' => $schoolA->id,
        'name' => 'Oakridge Primary Branch',
        'branch_code' => 'OB-' . rand(100, 999),
        'is_active' => true,
    ]);

    $schoolB = School::create([
        'tenant_id' => $tenantB->id,
        'name' => 'St Xavier Campus',
        'code' => 'STX-' . rand(100, 999),
        'is_active' => true,
    ]);

    $branchB = SchoolBranch::create([
        'tenant_id' => $tenantB->id,
        'school_id' => $schoolB->id,
        'name' => 'St Xavier City Branch',
        'branch_code' => 'XB-' . rand(100, 999),
        'is_active' => true,
    ]);

    $classA = SchoolClass::create([
        'tenant_id' => $tenantA->id,
        'school_id' => $schoolA->id,
        'branch_id' => $branchA->id,
        'name' => 'Grade 10 - Tenant A',
        'code' => 'G10A',
    ]);

    $classB = SchoolClass::create([
        'tenant_id' => $tenantB->id,
        'school_id' => $schoolB->id,
        'branch_id' => $branchB->id,
        'name' => 'Grade 10 - Tenant B',
        'code' => 'G10B',
    ]);

    // 3. Set context to Tenant A
    config(['app.current_tenant_id' => $tenantA->id]);

    $tenantAClasses = SchoolClass::all();
    expect($tenantAClasses->pluck('id'))->toContain($classA->id)
        ->and($tenantAClasses->pluck('id'))->not->toContain($classB->id);

    // 4. Set context to Tenant B
    config(['app.current_tenant_id' => $tenantB->id]);

    $tenantBClasses = SchoolClass::all();
    expect($tenantBClasses->pluck('id'))->toContain($classB->id)
        ->and($tenantBClasses->pluck('id'))->not->toContain($classA->id);

    // 5. Query without global scope (for platform admin)
    $allClasses = SchoolClass::withoutGlobalScopes()->get();
    expect($allClasses->pluck('id'))->toContain($classA->id)
        ->and($allClasses->pluck('id'))->toContain($classB->id);

    // Reset context
    config(['app.current_tenant_id' => null]);
});

test('students and student enrollments are distinct entities preserving progression history', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'DPS Academic Group',
    ]);

    $school = School::create([
        'tenant_id' => $tenant->id,
        'name' => 'DPS RK Puram',
        'code' => 'DPS-' . rand(100, 999),
        'is_active' => true,
    ]);

    $branch = SchoolBranch::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'name' => 'North Branch',
        'branch_code' => 'NB-' . rand(100, 999),
        'is_active' => true,
    ]);

    $session2025 = AcademicSession::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'name' => '2025-2026',
        'start_date' => '2025-04-01',
        'end_date' => '2026-03-31',
        'is_current' => false,
    ]);

    $session2026 = AcademicSession::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ]);

    $classGrade1 = SchoolClass::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'name' => 'Grade 1',
        'code' => 'G1-' . rand(100, 999),
    ]);

    $classGrade2 = SchoolClass::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'name' => 'Grade 2',
        'code' => 'G2-' . rand(100, 999),
    ]);

    $sectionA = Section::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'class_id' => $classGrade1->id,
        'name' => 'Section A',
    ]);

    $sectionB = Section::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'class_id' => $classGrade2->id,
        'name' => 'Section A',
    ]);

    // Student Master Record
    $student = Student::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'admission_number' => 'DPS-ADM-' . rand(10000, 99999),
        'admission_date' => '2025-04-01',
        'first_name' => 'Rohan',
        'last_name' => 'Verma',
        'date_of_birth' => '2018-05-12',
        'gender' => 'male',
        'status' => 'active',
    ]);

    // First Enrollment in 2025-2026 Grade 1
    $firstEnrollment = StudentEnrollment::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'student_id' => $student->id,
        'academic_session_id' => $session2025->id,
        'class_id' => $classGrade1->id,
        'section_id' => $sectionA->id,
        'roll_number' => '12',
        'enrollment_date' => '2025-04-05',
        'status' => 'enrolled',
    ]);

    expect($student->enrollments)->toHaveCount(1);

    // Promote Student to Grade 2 in 2026-2027
    $firstEnrollment->update(['status' => 'promoted']);

    $promotedEnrollment = StudentEnrollment::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'student_id' => $student->id,
        'academic_session_id' => $session2026->id,
        'class_id' => $classGrade2->id,
        'section_id' => $sectionB->id,
        'roll_number' => '15',
        'enrollment_date' => '2026-04-02',
        'status' => 'enrolled',
    ]);

    // Verify historical integrity: both records exist, neither overwritten
    $allEnrollments = StudentEnrollment::where('student_id', $student->id)->orderBy('enrollment_date')->get();
    expect($allEnrollments)->toHaveCount(2)
        ->and($allEnrollments->first()->class_id)->toBe($classGrade1->id)
        ->and($allEnrollments->first()->status)->toBe('promoted')
        ->and($allEnrollments->last()->class_id)->toBe($classGrade2->id)
        ->and($allEnrollments->last()->status)->toBe('enrolled');
});

test('immutable financial tables do not support soft deleting and enforce separate invoices and payments', function () {
    // 1. Assert models do not use SoftDeletes
    $traits = class_uses_recursive(FeePayment::class);
    expect($traits)->not->toHaveKey('Illuminate\Database\Eloquent\SoftDeletes');

    $traitsAlloc = class_uses_recursive(FeePaymentAllocation::class);
    expect($traitsAlloc)->not->toHaveKey('Illuminate\Database\Eloquent\SoftDeletes');

    $traitsSubPayment = class_uses_recursive(SubscriptionPayment::class);
    expect($traitsSubPayment)->not->toHaveKey('Illuminate\Database\Eloquent\SoftDeletes');

    // 2. Assert deleted_at column is absent on financial transaction tables
    expect(Schema::hasColumn('fee_payments', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('fee_payment_allocations', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('payment_transactions', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('subscription_payments', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('audit_logs', 'deleted_at'))->toBeFalse();

    // 3. Test entity separation between FeeInvoice and FeePayment
    $tenant = Tenant::factory()->create([
        'name' => 'Finance Academy',
    ]);

    $school = School::create([
        'tenant_id' => $tenant->id,
        'name' => 'Finance School Campus',
        'code' => 'FSC-' . rand(100, 999),
        'is_active' => true,
    ]);

    $branch = SchoolBranch::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'name' => 'Main Wing',
        'branch_code' => 'MW-' . rand(100, 999),
        'is_active' => true,
    ]);

    $session = AcademicSession::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
        'is_current' => true,
    ]);

    $class = SchoolClass::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'name' => 'Grade 8',
        'code' => 'G8-' . rand(100, 999),
    ]);

    $section = Section::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'class_id' => $class->id,
        'name' => 'Section A',
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'branch_id' => $branch->id,
        'admission_number' => 'FIN-' . rand(1000, 9999),
        'admission_date' => '2026-04-01',
        'first_name' => 'Aarav',
        'last_name' => 'Patel',
        'date_of_birth' => '2015-08-20',
        'gender' => 'male',
        'status' => 'active',
    ]);

    $enrollment = StudentEnrollment::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '05',
        'enrollment_date' => '2026-04-01',
        'status' => 'enrolled',
    ]);

    // Create Fee Invoice
    $invoice = FeeInvoice::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'academic_session_id' => $session->id,
        'invoice_number' => 'INV-2026-' . rand(10000, 99999),
        'subtotal' => 15000.00,
        'total_amount' => 15000.00,
        'paid_amount' => 0.00,
        'balance_amount' => 15000.00,
        'due_date' => '2026-04-15',
        'status' => 'unpaid',
    ]);

    $invoiceItem = FeeInvoiceItem::create([
        'tenant_id' => $tenant->id,
        'fee_invoice_id' => $invoice->id,
        'fee_head_name' => 'Tuition Fee Q1',
        'amount' => 15000.00,
    ]);

    expect($invoice->items)->toHaveCount(1);

    // Create Fee Payment (separate entity from invoice)
    $payment = FeePayment::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'student_id' => $student->id,
        'receipt_number' => 'REC-2026-' . rand(10000, 99999),
        'payment_date' => '2026-04-05 10:30:00',
        'amount_paid' => 15000.00,
        'payment_method' => 'upi',
        'status' => 'successful',
    ]);

    // Allocate payment to invoice
    $allocation = FeePaymentAllocation::create([
        'tenant_id' => $tenant->id,
        'fee_payment_id' => $payment->id,
        'fee_invoice_id' => $invoice->id,
        'allocated_amount' => 15000.00,
    ]);

    // Update Invoice status
    $invoice->update([
        'paid_amount' => 15000.00,
        'balance_amount' => 0.00,
        'status' => 'paid',
    ]);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($payment->allocations)->toHaveCount(1)
        ->and($payment->allocations->first()->fee_invoice_id)->toBe($invoice->id);
});

test('foreign key cascade cleanly deletes subordinate records when parent tenant is deleted', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Cascade Test School',
    ]);

    $school = School::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cascade Campus',
        'code' => 'CAS-' . rand(100, 999),
        'is_active' => true,
    ]);

    $branch = SchoolBranch::create([
        'tenant_id' => $tenant->id,
        'school_id' => $school->id,
        'name' => 'Cascade Branch',
        'branch_code' => 'CB-' . rand(100, 999),
        'is_active' => true,
    ]);

    $schoolId = $school->id;
    $branchId = $branch->id;

    // Force delete tenant to test database foreign key ON DELETE CASCADE
    $tenant->forceDelete();

    // Verify cascade
    expect(School::find($schoolId))->toBeNull()
        ->and(SchoolBranch::find($branchId))->toBeNull();
});
