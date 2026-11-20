<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Board;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeCategory;
use App\Models\FeeConcession;
use App\Models\FeeFine;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use App\Models\FeeStructure;
use App\Models\FinancialAccount;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\BoardsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeeAndFinancialManagementTest extends TestCase
{
    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;
    protected User $accountantUser;
    protected Student $student;
    protected StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(BoardsSeeder::class);

        // 1. Create Tenant, School, Branch
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'St. Xavier School',
            'slug' => 'st-xavier-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@stxavier.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'St. Xavier High School',
            'code' => 'STX-01',
            'status' => 'active',
        ]);

        $this->branch = SchoolBranch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'City Campus',
            'branch_code' => 'CC',
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

        // 4. Admin User
        $this->adminUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'Finance',
            'email' => 'admin-' . Str::random(8) . '@stxavier.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        $adminMembership = TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_default' => true,
        ]);

        $this->adminUser->assignRole('school_admin', $this->tenant->id);

        // 5. Accountant User
        $this->accountantUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Chief',
            'last_name' => 'Accountant',
            'email' => 'accountant-' . Str::random(8) . '@stxavier.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->accountantUser->id,
            'role' => 'accountant',
            'is_default' => true,
        ]);

        $this->accountantUser->assignRole('accountant', $this->tenant->id);

        // 6. Set Tenant Context
        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setSchool($this->school);
        app(TenantContext::class)->setBranch($this->branch);
        app(TenantContext::class)->setActiveMembership($adminMembership);

        // 7. Student and Enrollment
        $studentUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Aarav',
            'last_name' => 'Patel',
            'email' => 'aarav-' . Str::random(6) . '@student.stxavier.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('StudentPass123!'),
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $studentUser->id,
            'admission_number' => 'ADM-2026-001',
            'first_name' => 'Aarav',
            'last_name' => 'Patel',
            'admission_date' => '2026-04-01',
            'date_of_birth' => '2011-05-15',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->enrollment = StudentEnrollment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '101',
            'enrollment_date' => '2026-04-01',
            'status' => 'active',
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

    protected function actingAsAccountant(): self
    {
        return $this->actingAs($this->accountantUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    #[Test]
    public function it_can_create_fee_category()
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/fees/categories', [
            'name' => 'Tuition Fees',
            'description' => 'Standard academic tuition fees',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Tuition Fees');

        $this->assertDatabaseHas('fee_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Tuition Fees',
        ]);
    }

    #[Test]
    public function it_can_create_fee_structure_with_line_items()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Academic Fees',
            'is_active' => true,
        ]);

        $payload = [
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'class_id' => $this->class->id,
            'name' => 'Grade 10 Annual Comprehensive Fee',
            'frequency' => 'annual',
            'due_date' => '2026-06-30',
            'items' => [
                ['fee_head_name' => 'Tuition Fee', 'amount' => 50000.00, 'is_mandatory' => true],
                ['fee_head_name' => 'Computer Lab Fee', 'amount' => 5000.00, 'is_mandatory' => true],
                ['fee_head_name' => 'Sports & Activity Fee', 'amount' => 3000.00, 'is_mandatory' => true],
            ],
        ];

        $response = $this->actingAsAdmin()->postJson('/api/v1/fees/structures', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_amount', '58000.00')
            ->assertJsonCount(3, 'data.items');

        $this->assertDatabaseHas('fee_structures', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Grade 10 Annual Comprehensive Fee',
            'total_amount' => 58000.00,
        ]);

        $this->assertDatabaseHas('fee_structure_items', [
            'fee_head_name' => 'Tuition Fee',
            'amount' => 50000.00,
        ]);
    }

    #[Test]
    public function it_can_configure_fee_concessions()
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/fees/concessions', [
            'name' => 'Merit Scholarship 20%',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'description' => 'Top 5 percent academic scholarship',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Merit Scholarship 20%')
            ->assertJsonPath('data.discount_value', '20.00');

        $this->assertDatabaseHas('fee_concessions', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Merit Scholarship 20%',
            'discount_value' => 20.00,
        ]);
    }

    #[Test]
    public function it_assigns_fee_structure_to_student_and_generates_invoice_with_concession()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Composite Fee',
            'is_active' => true,
        ]);

        $structure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'class_id' => $this->class->id,
            'name' => 'Term 1 Fee',
            'frequency' => 'term',
            'total_amount' => 40000.00,
            'due_date' => '2026-07-15',
        ]);

        $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Term Tuition',
            'amount' => 40000.00,
        ]);

        $concession = FeeConcession::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sibling Discount 10%',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
        ]);

        $response = $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structure->id,
            'concession_id' => $concession->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gross_amount', '40000.00')
            ->assertJsonPath('data.discount_amount', '4000.00')
            ->assertJsonPath('data.net_amount', '36000.00');

        $this->assertDatabaseHas('fee_invoices', [
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'subtotal' => 40000.00,
            'discount_amount' => 4000.00,
            'total_amount' => 36000.00,
            'balance_amount' => 36000.00,
            'status' => 'unpaid',
        ]);
    }

    #[Test]
    public function it_collects_payment_generates_sequential_receipt_and_allocates_to_invoices()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Tuition',
        ]);

        $structure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'name' => 'Annual Fee',
            'frequency' => 'annual',
            'total_amount' => 50000.00,
            'due_date' => '2026-05-31',
        ]);

        $item1 = $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Tuition',
            'amount' => 30000.00,
        ]);

        $item2 = $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Laboratory',
            'amount' => 20000.00,
        ]);

        // Assign fee to generate invoice
        $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structure->id,
        ]);

        $invoice = FeeInvoice::where('student_id', $this->student->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(50000.00, (float) $invoice->balance_amount);

        // Collect payment of 30,000 via Cash
        $collectResponse = $this->actingAsAccountant()->postJson('/api/v1/fees/payments/collect', [
            'student_id' => $this->student->id,
            'amount_paid' => 30000.00,
            'payment_method' => 'cash',
            'notes' => 'Received from parent at counter',
            'allocations' => [
                [
                    'fee_invoice_id' => $invoice->id,
                    'allocated_amount' => 30000.00,
                ],
            ],
        ]);

        $collectResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount_paid', '30000.00')
            ->assertJsonPath('data.payment_method', 'cash');

        $receiptNumber = $collectResponse->json('data.receipt_number');
        $this->assertStringStartsWith('REC-2026-', $receiptNumber);

        // Verify Invoice status updated to partially_paid and balance is 20,000
        $invoice->refresh();
        $this->assertEquals('partially_paid', $invoice->status);
        $this->assertEquals(30000.00, (float) $invoice->paid_amount);
        $this->assertEquals(20000.00, (float) $invoice->balance_amount);

        // Verify allocation record
        $this->assertDatabaseHas('fee_payment_allocations', [
            'tenant_id' => $this->tenant->id,
            'fee_invoice_id' => $invoice->id,
            'allocated_amount' => 30000.00,
        ]);
    }

    #[Test]
    public function it_supports_multiple_partial_payments_and_completes_student_fee_ledger()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Quarterly Fee',
        ]);

        $structure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'name' => 'Q1 Fee',
            'frequency' => 'term',
            'total_amount' => 10000.00,
            'due_date' => '2026-05-15',
        ]);

        $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Quarter 1',
            'amount' => 10000.00,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structure->id,
        ]);

        $invoice = FeeInvoice::where('student_id', $this->student->id)->first();

        // 1st partial payment: 6,000
        $this->actingAsAccountant()->postJson('/api/v1/fees/payments/collect', [
            'student_id' => $this->student->id,
            'amount_paid' => 6000.00,
            'payment_method' => 'upi',
            'reference_number' => 'UPI/123456789012',
        ]);

        $invoice->refresh();
        $this->assertEquals('partially_paid', $invoice->status);
        $this->assertEquals(4000.00, (float) $invoice->balance_amount);

        // 2nd payment: 4,000 to fully clear
        $this->actingAsAccountant()->postJson('/api/v1/fees/payments/collect', [
            'student_id' => $this->student->id,
            'amount_paid' => 4000.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'NEFT-889900',
        ]);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.00, (float) $invoice->balance_amount);

        // Fetch Student Fee Ledger
        $ledgerResponse = $this->actingAsAdmin()->getJson("/api/v1/fees/ledger/{$this->student->id}");

        $ledgerResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_invoiced', 10000)
            ->assertJsonPath('data.summary.total_paid', 10000)
            ->assertJsonPath('data.summary.total_outstanding', 0)
            ->assertJsonCount(2, 'data.payments');
    }

    #[Test]
    public function it_initiates_razorpay_order_and_creates_payment_transaction_record()
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/fees/payments/razorpay/order', [
            'amount' => 15000.00,
            'student_id' => $this->student->id,
            'currency' => 'INR',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 15000)
            ->assertJsonPath('data.amount_in_paise', 1500000)
            ->assertJsonPath('data.currency', 'INR');

        $orderId = $response->json('data.order_id');
        $this->assertNotEmpty($orderId);

        $this->assertDatabaseHas('payment_transactions', [
            'tenant_id' => $this->tenant->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $orderId,
            'amount' => 15000.00,
            'status' => 'initiated',
        ]);
    }

    #[Test]
    public function it_verifies_valid_razorpay_signature_and_rejects_tampered_signature()
    {
        $orderId = 'order_test_abc123456';
        $paymentId = 'pay_test_xyz789012';
        $secret = config('services.razorpay.key_secret');

        // Create transaction
        PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $orderId,
            'amount' => 8000.00,
            'currency' => 'INR',
            'status' => 'initiated',
            'metadata' => ['student_id' => $this->student->id],
        ]);

        // 1. Valid Signature
        $validSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        $response = $this->actingAsAdmin()->postJson('/api/v1/fees/payments/razorpay/verify', [
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $validSignature,
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'online')
            ->assertJsonPath('data.reference_number', $paymentId);

        $this->assertDatabaseHas('payment_transactions', [
            'gateway_order_id' => $orderId,
            'gateway_payment_id' => $paymentId,
            'status' => 'captured',
        ]);

        // 2. Tampered / Invalid Signature
        $this->expectException(\InvalidArgumentException::class);
        $invalidSignature = 'tampered_fake_signature_hash';

        app(\App\Services\Finance\PaymentService::class)->verifyRazorpayPayment([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $invalidSignature,
            'student_id' => $this->student->id,
        ]);
    }

    #[Test]
    public function it_processes_razorpay_webhook_idempotently_and_prevents_duplicate_replay()
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        $eventId = 'evt_test_' . Str::random(10);
        $orderId = 'order_webhook_123';

        PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $orderId,
            'amount' => 5000.00,
            'currency' => 'INR',
            'status' => 'initiated',
            'metadata' => ['student_id' => $this->student->id],
        ]);

        $payload = [
            'event_id' => $eventId,
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_hook_999',
                        'order_id' => $orderId,
                        'amount' => 500000,
                        'notes' => [
                            'tenant_id' => $this->tenant->id,
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $webhookSecret);

        // 1st Webhook Ingestion
        $response1 = $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => $signature,
        ], $rawBody);

        $response1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => $eventId,
            'is_processed' => true,
        ]);

        $this->assertDatabaseHas('payment_transactions', [
            'gateway_order_id' => $orderId,
            'status' => 'captured',
        ]);

        // 2nd Webhook Ingestion (Replay / Duplicate)
        $response2 = $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => $signature,
        ], $rawBody);

        $response2->assertStatus(200)
            ->assertJsonPath('data.status', 'duplicate')
            ->assertJsonPath('data.message', 'Event already processed');
    }

    #[Test]
    public function it_applies_and_waives_late_fee_fines_accurately()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Exam Fee',
        ]);

        $structure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'name' => 'Board Exam Fee',
            'frequency' => 'one_time',
            'total_amount' => 2000.00,
            'due_date' => Carbon::yesterday()->toDateString(),
        ]);

        $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Board Registration',
            'amount' => 2000.00,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structure->id,
        ]);

        $invoice = FeeInvoice::where('student_id', $this->student->id)->first();

        // 1. Apply Late Fine: 500
        $fineResponse = $this->actingAsAdmin()->postJson("/api/v1/fees/invoices/{$invoice->id}/fine", [
            'fine_amount' => 500.00,
            'reason' => 'Late submission beyond deadline',
        ]);

        $fineResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fine_amount', '500.00');

        $fineId = $fineResponse->json('data.id');

        $invoice->refresh();
        $this->assertEquals(500.00, (float) $invoice->fine_amount);
        $this->assertEquals(2500.00, (float) $invoice->total_amount);
        $this->assertEquals(2500.00, (float) $invoice->balance_amount);

        // 2. Waive the late fine with reason
        $waiveResponse = $this->actingAsAdmin()->postJson("/api/v1/fees/fines/{$fineId}/waive", [
            'waiver_reason' => 'Approved by Principal on medical grounds',
        ]);

        $waiveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_waived', true)
            ->assertJsonPath('data.waiver_reason', 'Approved by Principal on medical grounds');

        $invoice->refresh();
        $this->assertEquals(0.00, (float) $invoice->fine_amount);
        $this->assertEquals(2000.00, (float) $invoice->total_amount);
        $this->assertEquals(2000.00, (float) $invoice->balance_amount);
    }

    #[Test]
    public function it_processes_refund_as_an_immutable_separate_transaction_and_restores_invoice_balance()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Activity Fee',
        ]);

        $structure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'name' => 'Educational Trip',
            'frequency' => 'one_time',
            'total_amount' => 5000.00,
            'due_date' => '2026-08-01',
        ]);

        $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Excursion',
            'amount' => 5000.00,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structure->id,
        ]);

        $invoice = FeeInvoice::where('student_id', $this->student->id)->first();

        // Pay in full: 5000
        $payment = app(\App\Services\Finance\PaymentService::class)->collectPayment([
            'student_id' => $this->student->id,
            'amount_paid' => 5000.00,
            'payment_method' => 'cash',
        ], $this->adminUser->id);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.00, (float) $invoice->balance_amount);

        // Process Refund: 2000
        $refundResponse = $this->actingAsAdmin()->postJson("/api/v1/fees/payments/{$payment->id}/refund", [
            'amount' => 2000.00,
            'reason' => 'Student opted out of trip excursion',
        ]);

        $refundResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '2000.00')
            ->assertJsonPath('data.status', 'processed');

        $refundNumber = $refundResponse->json('data.refund_number');
        $this->assertStringStartsWith('REF-2026-', $refundNumber);

        // IMMUTABLE CHECK: Payment record still exists with original amount_paid
        $this->assertDatabaseHas('fee_payments', [
            'id' => $payment->id,
            'amount_paid' => 5000.00,
        ]);

        // Invoice balance is restored by 2000 and status is partially_paid
        $invoice->refresh();
        $this->assertEquals(3000.00, (float) $invoice->paid_amount);
        $this->assertEquals(2000.00, (float) $invoice->balance_amount);
        $this->assertEquals('partially_paid', $invoice->status);
    }

    #[Test]
    public function it_records_school_expenses_and_updates_financial_account_balance()
    {
        // 1. Create Financial Account (Bank Ledger)
        $accountResponse = $this->actingAsAdmin()->postJson('/api/v1/finance/accounts', [
            'account_name' => 'HDFC Main Operating Account',
            'account_number' => '50100456789123',
            'bank_name' => 'HDFC Bank',
            'ifsc_code' => 'HDFC0001234',
            'opening_balance' => 100000.00,
        ]);

        $accountResponse->assertStatus(201);
        $accountId = $accountResponse->json('data.id');

        // 2. Create Expense Category
        $catResponse = $this->actingAsAdmin()->postJson('/api/v1/finance/expense-categories', [
            'name' => 'Electricity & Utilities',
            'code' => 'UTIL-01',
            'description' => 'Monthly electricity bills and campus utilities',
        ]);

        $catResponse->assertStatus(201);
        $categoryId = $catResponse->json('data.id');

        // 3. Record Expense
        $expenseResponse = $this->actingAsAdmin()->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $categoryId,
            'financial_account_id' => $accountId,
            'title' => 'April 2026 Electricity Bill',
            'amount' => 12500.00,
            'expense_date' => '2026-05-02',
            'payment_method' => 'bank_transfer',
            'vendor_name' => 'State Electricity Board',
            'notes' => 'Campus power grid consumption',
        ]);

        $expenseResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '12500.00');

        $voucherNumber = $expenseResponse->json('data.voucher_number');
        $this->assertStringStartsWith('VCH-2026-', $voucherNumber);

        // Account balance decreased: 100,000 - 12,500 = 87,500
        $account = FinancialAccount::find($accountId);
        $this->assertEquals(87500.00, (float) $account->current_balance);
    }

    #[Test]
    public function it_generates_accurate_financial_and_defaulters_reports()
    {
        $category = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Tuition',
        ]);

        $structure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $category->id,
            'name' => 'Term Fee',
            'frequency' => 'term',
            'total_amount' => 12000.00,
            'due_date' => Carbon::today()->subDays(20)->toDateString(), // 20 days overdue!
        ]);

        $structure->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Tuition',
            'amount' => 12000.00,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structure->id,
        ]);

        // Record a 2000 payment today
        $this->actingAsAccountant()->postJson('/api/v1/fees/payments/collect', [
            'student_id' => $this->student->id,
            'amount_paid' => 2000.00,
            'payment_method' => 'cash',
        ]);

        // 1. Daily Collection Report
        $dailyRes = $this->actingAsAdmin()->getJson('/api/v1/finance/reports/daily?date=' . Carbon::today()->toDateString());
        $dailyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_amount', 2000)
            ->assertJsonPath('data.total_transactions', 1);

        // 2. Defaulters Report (> 15 days overdue)
        $defaultersRes = $this->actingAsAdmin()->getJson('/api/v1/finance/reports/defaulters?min_days=15');
        $defaultersRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_defaulters', 1)
            ->assertJsonPath('data.defaulters.0.student_id', $this->student->id)
            ->assertJsonPath('data.defaulters.0.total_overdue_amount', 10000);

        // 3. Class-wise Report
        $classRes = $this->actingAsAdmin()->getJson('/api/v1/finance/reports/class-wise');
        $classRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.class_name', 'Grade 10')
            ->assertJsonPath('data.0.total_invoiced', 12000)
            ->assertJsonPath('data.0.total_collected', 2000)
            ->assertJsonPath('data.0.total_outstanding', 10000);
    }

    #[Test]
    public function it_enforces_strict_tenant_isolation_on_fee_invoices_and_payments()
    {
        // Create Tenant B
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Greenwood High',
            'slug' => 'greenwood-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@greenwood.edu',
            'contact_phone' => '+919876543299',
            'status' => 'active',
        ]);

        $schoolB = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Greenwood Campus',
            'code' => 'GWD-01',
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
            'first_name' => 'Greenwood',
            'last_name' => 'Admin',
            'email' => 'admin-' . Str::random(8) . '@greenwood.edu',
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

        // Tenant A creates an invoice
        $categoryA = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Confidential Fee A',
        ]);

        $structureA = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'fee_category_id' => $categoryA->id,
            'name' => 'Exclusive A Fee',
            'frequency' => 'annual',
            'total_amount' => 75000.00,
            'due_date' => '2026-09-30',
        ]);

        $structureA->items()->create([
            'tenant_id' => $this->tenant->id,
            'fee_head_name' => 'Tuition A',
            'amount' => 75000.00,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/fees/assignments', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'fee_structure_id' => $structureA->id,
        ]);

        $invoiceA = FeeInvoice::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($invoiceA);

        // Tenant B attempts to fetch Tenant A's invoice directly by ID
        $response = $this->actingAs($userB, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $tenantB->id,
                'X-Branch-ID' => $branchB->id,
            ])
            ->getJson("/api/v1/fees/invoices/{$invoiceA->id}");

        // TenantScope must block Tenant B from viewing Tenant A's invoice
        $response->assertStatus(404);
    }
}
