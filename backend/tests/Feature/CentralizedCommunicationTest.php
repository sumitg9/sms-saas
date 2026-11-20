<?php

namespace Tests\Feature;

use App\Contracts\Communication\SmsProviderInterface;
use App\Contracts\Communication\WhatsAppProviderInterface;
use App\Jobs\Communication\ProcessBulkNotificationJob;
use App\Jobs\Communication\RetryFailedNotificationJob;
use App\Jobs\Communication\SendNotificationJob;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\CommunicationPreference;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Guardian;
use App\Models\Message;
use App\Models\MessageConversation;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\PtmMeeting;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Communication\CommunicationProviderManager;
use App\Services\Communication\Providers\LogSmsProvider;
use App\Services\Communication\Providers\LogWhatsAppProvider;
use App\Services\Communication\TemplateRenderer;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CentralizedCommunicationTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class10;
    protected Section $sectionA;
    protected User $adminUser;
    protected User $teacherUser;
    protected Employee $teacherEmployee;
    protected User $parentUser;
    protected Guardian $guardian;
    protected User $studentUser;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tenant & Hierarchy
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oakridge International Academy',
            'slug' => 'oakridge-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@oakridge.edu',
            'contact_phone' => '+919876540001',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oakridge Main Campus',
            'code' => 'OAK-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Senior Wing',
            'branch_code' => 'SW-' . rand(100, 999),
            'is_main_branch' => true,
        ]);

        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($this->tenant);
        $tenantContext->setSchool($this->school);
        $tenantContext->setBranch($this->branch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2026-2027',
            'code' => 'AY2026-27-' . rand(10, 99),
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class10 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Class 10',
            'code' => 'CLS-10-' . rand(100, 999),
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

        // 2. Roles
        $adminRole = Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher'], ['guard_name' => 'web', 'display_name' => 'Teacher']);
        $parentRole = Role::firstOrCreate(['name' => 'parent'], ['guard_name' => 'web', 'display_name' => 'Parent']);
        $studentRole = Role::firstOrCreate(['name' => 'student'], ['guard_name' => 'web', 'display_name' => 'Student']);

        // 3. Admin User
        $this->adminUser = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Principal']);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // 4. Teacher User & Employee
        $dept = EmployeeDepartment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sciences',
            'code' => 'SCI-' . rand(100, 999),
        ]);
        $desig = EmployeeDesignation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Senior Faculty',
            'code' => 'SF-' . rand(100, 999),
        ]);

        $this->teacherUser = User::factory()->create(['first_name' => 'Albert', 'last_name' => 'Einstein', 'phone' => '+919876543210']);
        $this->teacherUser->assignRole('teacher', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->teacherUser->id,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->teacherEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->teacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-T-' . rand(1000, 9999),
            'first_name' => 'Albert',
            'last_name' => 'Einstein',
            'gender' => 'male',
            'date_of_birth' => '1980-03-14',
            'email' => 'albert-' . Str::random(5) . '@oakridge.edu',
            'phone' => '+919876543210',
            'date_of_joining' => '2019-06-01',
            'employment_status' => 'active',
        ]);

        // 5. Parent & Guardian
        $this->parentUser = User::factory()->create(['first_name' => 'Sanjay', 'last_name' => 'Sharma', 'phone' => '+919876541111']);
        $this->parentUser->assignRole('parent', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->parentUser->id,
            'role' => 'parent',
            'is_active' => true,
        ]);

        $this->guardian = Guardian::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->parentUser->id,
            'first_name' => 'Sanjay',
            'last_name' => 'Sharma',
            'relation_type' => 'father',
            'email' => 'sanjay-' . Str::random(5) . '@parent.com',
            'phone' => '+919876541111',
        ]);

        // 6. Student & Enrollment
        $this->studentUser = User::factory()->create(['first_name' => 'Aarav', 'last_name' => 'Sharma', 'phone' => '+919876542222']);
        $this->studentUser->assignRole('student', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->studentUser->id,
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->studentUser->id,
            'admission_number' => 'ADM-OAK-' . rand(1000, 9999),
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'gender' => 'male',
            'date_of_birth' => '2010-08-15',
            'admission_date' => '2024-04-01',
            'status' => 'active',
        ]);

        \Illuminate\Support\Facades\DB::table('student_guardians')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'guardian_id' => $this->guardian->id,
            'is_primary' => true,
        ]);

        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => '14',
            'enrollment_date' => '2026-04-01',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Template rendering with placeholders and school branding.
     */
    public function test_template_rendering_with_placeholders_and_branding(): void
    {
        $renderer = new TemplateRenderer();

        $template = "Dear Parent, your ward {{student_name}} of {{class_name}} is absent today ({{date}}).";
        $variables = [
            'student_name' => 'Aarav Sharma',
            'class_name' => 'Class 10 - A',
            'date' => '2026-11-20',
        ];
        $branding = [
            'school_name' => 'Oakridge International',
            'header' => '*** OAKRIDGE OFFICIAL NOTICE ***',
            'footer' => 'Contact: office@oakridge.edu | helpline: 1800-OAK',
        ];

        $rendered = $renderer->render($template, $variables, $branding);

        $this->assertStringContainsString('*** OAKRIDGE OFFICIAL NOTICE ***', $rendered);
        $this->assertStringContainsString('Aarav Sharma', $rendered);
        $this->assertStringContainsString('Class 10 - A', $rendered);
        $this->assertStringContainsString('2026-11-20', $rendered);
        $this->assertStringContainsString('helpline: 1800-OAK', $rendered);
    }

    /**
     * Test 2: Provider Abstraction allows hot-swapping providers.
     */
    public function test_provider_abstraction_for_sms_and_whatsapp(): void
    {
        $manager = app(CommunicationProviderManager::class);

        // Verify default providers
        $this->assertInstanceOf(SmsProviderInterface::class, $manager->sms());
        $this->assertInstanceOf(WhatsAppProviderInterface::class, $manager->whatsapp());

        // Test sending through SMS provider abstraction
        $smsResult = $manager->sms()->send('+919876541111', 'Test SMS Message');
        $this->assertTrue($smsResult['success']);
        $this->assertNotNull($smsResult['message_id']);

        // Test sending through WhatsApp provider abstraction
        $waResult = $manager->whatsapp()->send('+919876541111', 'Test WhatsApp Message');
        $this->assertTrue($waResult['success']);
        $this->assertNotNull($waResult['message_id']);

        // Swap with a custom mock provider to verify zero business logic rewriting
        $customMock = new class implements SmsProviderInterface {
            public function send(string $to, string $message, array $options = []): array {
                return ['success' => true, 'message_id' => 'CUSTOM-SMS-999', 'error' => null];
            }
        };

        $manager->setSmsProvider($customMock);
        $customResult = $manager->sms()->send('+919876541111', 'Swapped Provider Message');
        $this->assertEquals('CUSTOM-SMS-999', $customResult['message_id']);
    }

    /**
     * Test 3: Notifications use Laravel Queues and do not block HTTP requests.
     */
    public function test_notification_dispatches_queue_job_asynchronously(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/communication/notifications/send', [
                'channel' => 'sms',
                'recipient' => '+919876541111',
                'message' => 'School fee reminder: Term 2 fees due by Dec 10.',
                'priority' => 'normal',
            ]);

        $response->assertStatus(202);
        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->channel === 'sms' && $job->recipient === '+919876541111';
        });

        // Test bulk notification queues ProcessBulkNotificationJob
        $bulkResponse = $this->actingAs($this->adminUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/communication/notifications/bulk', [
                'channels' => ['sms', 'whatsapp'],
                'target_audience' => 'class',
                'target_parameters' => [
                    'class_id' => $this->class10->id,
                    'section_id' => $this->sectionA->id,
                ],
                'message' => 'Class 10 Science Exhibition rehearsal tomorrow at 9 AM.',
            ]);

        $bulkResponse->assertStatus(202);
        Queue::assertPushed(ProcessBulkNotificationJob::class, function ($job) {
            return $job->targetAudience === 'class' && in_array('whatsapp', $job->channels);
        });
    }

    /**
     * Test 4: Communication preferences respect opt-out and DND, but emergency overrides.
     */
    public function test_communication_preferences_and_emergency_override(): void
    {
        // Set parent preference: SMS disabled
        CommunicationPreference::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->parentUser->id,
            'sms_enabled' => false,
            'whatsapp_enabled' => true,
            'email_enabled' => true,
            'emergency_override' => true,
        ]);

        $manager = app(CommunicationProviderManager::class);

        // 1. Regular notification on SMS should be suppressed due to opt-out
        $normalJob = new SendNotificationJob(
            $this->tenant->id,
            'sms',
            '+919876541111',
            'Regular school update',
            null,
            ['priority' => 'normal'],
            'KEY-' . Str::random(8),
            $this->parentUser->id
        );
        $normalJob->handle($manager);

        $this->assertDatabaseHas('notification_logs', [
            'tenant_id' => $this->tenant->id,
            'recipient' => '+919876541111',
            'status' => 'suppressed_opt_out',
        ]);

        // 2. Emergency notification must override opt-out!
        $emergencyJob = new SendNotificationJob(
            $this->tenant->id,
            'sms',
            '+919876541111',
            'EMERGENCY: Sudden heavy rainfall. Early dispersal at 1 PM.',
            null,
            ['priority' => 'emergency'],
            'KEY-' . Str::random(8),
            $this->parentUser->id
        );
        $emergencyJob->handle($manager);

        $this->assertDatabaseHas('notification_logs', [
            'tenant_id' => $this->tenant->id,
            'recipient' => '+919876541111',
            'status' => 'delivered',
            'message_content' => 'EMERGENCY: Sudden heavy rainfall. Early dispersal at 1 PM.',
        ]);
    }

    /**
     * Test 5: Duplicate delivery prevention via idempotency key.
     */
    public function test_duplicate_delivery_prevention_via_idempotency_key(): void
    {
        $manager = app(CommunicationProviderManager::class);
        $idempotencyKey = 'IDEM-KEY-' . Str::random(10);

        // First dispatch -> delivered and logged
        $job1 = new SendNotificationJob(
            $this->tenant->id,
            'sms',
            '+919876541111',
            'Idempotent Fee Receipt Confirmation',
            null,
            [],
            $idempotencyKey,
            $this->parentUser->id
        );
        $job1->handle($manager);

        $this->assertEquals(1, NotificationLog::where('idempotency_key', $idempotencyKey)->count());

        // Second dispatch with same idempotency key -> skipped
        $job2 = new SendNotificationJob(
            $this->tenant->id,
            'sms',
            '+919876541111',
            'Idempotent Fee Receipt Confirmation',
            null,
            [],
            $idempotencyKey,
            $this->parentUser->id
        );
        $job2->handle($manager);

        // Should still only have 1 log entry
        $this->assertEquals(1, NotificationLog::where('idempotency_key', $idempotencyKey)->count());
    }

    /**
     * Test 6: Failed notification logging and safe retry mechanism.
     */
    public function test_failed_notification_tracking_and_retry_mechanism(): void
    {
        $manager = app(CommunicationProviderManager::class);

        // Trigger failure with simulated failure option
        $job = new SendNotificationJob(
            $this->tenant->id,
            'sms',
            '+91987654999999', // Triggers simulated failure in LogSmsProvider
            'Message to failing recipient',
            null,
            ['simulate_failure' => true],
            'FAIL-KEY-' . Str::random(8),
            $this->parentUser->id
        );
        $job->handle($manager);

        $failedLog = NotificationLog::where('recipient', '+91987654999999')->first();
        $this->assertNotNull($failedLog);
        $this->assertEquals('failed', $failedLog->status);
        $this->assertNotNull($failedLog->error_message);

        // Now test retry mechanism
        $retryResponse = $this->actingAs($this->adminUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/communication/notifications/logs/{$failedLog->id}/retry");

        $retryResponse->assertStatus(200);

        // Execute RetryFailedNotificationJob directly with corrected recipient
        $failedLog->update(['recipient' => '+919876541111']); // Valid recipient
        $retryJob = new RetryFailedNotificationJob($failedLog->id);
        $retryJob->handle($manager);

        $this->assertEquals('delivered', $failedLog->fresh()->status);
        $this->assertEquals(2, $failedLog->fresh()->retry_count);
    }

    /**
     * Test 7: Announcements module with class-wise targeting and parent acknowledgement.
     */
    public function test_announcement_creation_and_parent_acknowledgement(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/communication/announcements', [
                'title' => 'Class 10 CBSE Board Registration Deadline',
                'content' => 'All parents must review subject codes and submit registration fees by next Friday.',
                'target_audience' => 'parents',
                'class_id' => $this->class10->id,
                'section_id' => $this->sectionA->id,
                'priority' => 'urgent',
                'requires_acknowledgement' => true,
                'attachments' => [
                    ['name' => 'Registration_Instructions.pdf', 'url' => 'https://oakridge.edu/circulars/reg.pdf'],
                ],
            ]);

        $response->assertStatus(201);
        $announcementId = $response->json('data.id');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcementId,
            'title' => 'Class 10 CBSE Board Registration Deadline',
            'requires_acknowledgement' => true,
        ]);

        // Parent acknowledges announcement
        $ackResponse = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/communication/announcements/{$announcementId}/acknowledge");

        $ackResponse->assertStatus(200);
        $this->assertDatabaseHas('announcement_acknowledgements', [
            'announcement_id' => $announcementId,
            'user_id' => $this->parentUser->id,
        ]);
    }

    /**
     * Test 8: Parent-Teacher Meeting (PTM) scheduling, requests, and feedback lifecycle.
     */
    public function test_ptm_meeting_scheduling_request_and_feedback(): void
    {
        // 1. Teacher schedules meeting with parent
        $scheduleResponse = $this->actingAs($this->teacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/communication/meetings/schedule', [
                'teacher_id' => $this->teacherEmployee->id,
                'guardian_id' => $this->guardian->id,
                'student_id' => $this->student->id,
                'title' => 'Mathematics Progress Discussion - Q2',
                'meeting_type' => 'in_person',
                'meeting_date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '14:30',
                'end_time' => '15:00',
                'room_number' => 'Staff Room 204',
                'teacher_notes' => 'Review performance on trigonometric identities.',
            ]);

        $scheduleResponse->assertStatus(201);
        $meetingId = $scheduleResponse->json('data.id');

        // 2. Parent requests meeting
        $requestResponse = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/communication/meetings/request', [
                'teacher_id' => $this->teacherEmployee->id,
                'student_id' => $this->student->id,
                'meeting_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'parent_notes' => 'Discuss science fair project supervision.',
            ]);

        $requestResponse->assertStatus(201);

        // 3. Complete meeting and record feedback & 5-star rating
        $feedbackResponse = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/communication/meetings/{$meetingId}/feedback", [
                'feedback' => 'Very constructive session. Teacher explained algebra concepts clearly.',
                'rating' => 5,
            ]);

        $feedbackResponse->assertStatus(200);
        $this->assertDatabaseHas('ptm_meetings', [
            'id' => $meetingId,
            'status' => 'completed',
            'rating' => 5,
        ]);
    }

    /**
     * Test 9: Direct messaging and conversations between parent and teacher.
     */
    public function test_direct_messaging_conversation_between_parent_and_teacher(): void
    {
        // Teacher starts conversation with parent
        $convResponse = $this->actingAs($this->teacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/communication/conversations', [
                'recipient_id' => $this->parentUser->id,
                'title' => 'Discussion regarding Aarav Sharma homework',
                'initial_message' => 'Good afternoon Mr. Sharma, please encourage Aarav to submit problem set 4.',
            ]);

        $convResponse->assertStatus(201);
        $conversationId = $convResponse->json('data.id');

        // Parent replies
        $replyResponse = $this->actingAs($this->parentUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/communication/conversations/{$conversationId}/messages", [
                'content' => 'Thank you Mr. Einstein. He has completed it and will turn it in tomorrow morning.',
            ]);

        $replyResponse->assertStatus(201);

        // Teacher reads messages
        $messagesResponse = $this->actingAs($this->teacherUser)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/communication/conversations/{$conversationId}/messages");

        $messagesResponse->assertStatus(200);
        $this->assertCount(2, $messagesResponse->json('data'));
    }

    /**
     * Test 10: Strict Tenant Isolation on Communication Records.
     */
    public function test_tenant_isolation_on_communication_records(): void
    {
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Stellar Academy',
            'slug' => 'stellar-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@stellar.edu',
            'contact_phone' => '+919876549900',
            'status' => 'active',
        ]);

        $announcement = Announcement::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'title' => 'Confidential School A Circular',
            'content' => 'School A private information',
            'target_audience' => 'all',
            'priority' => 'normal',
            'published_at' => now(),
            'author_id' => $this->adminUser->id,
            'is_published' => true,
        ]);

        // Attempt by user from Tenant A to access under Tenant B header -> 403 Forbidden
        $response403 = $this->actingAs($this->adminUser)
            ->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson("/api/v1/communication/announcements/{$announcement->id}");

        $response403->assertStatus(403);

        // Legitimate user of Tenant B cannot see School A announcement -> 404 Not Found
        $adminB = User::factory()->create(['first_name' => 'Stellar', 'last_name' => 'Admin']);
        $adminB->assignRole('school_admin', $tenantB->id);
        TenantMembership::create([
            'tenant_id' => $tenantB->id,
            'user_id' => $adminB->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        $responseB = $this->actingAs($adminB)
            ->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson("/api/v1/communication/announcements/{$announcement->id}");

        $responseB->assertStatus(404);
    }
}
