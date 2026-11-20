<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\HostelAllocation;
use App\Models\HostelAttendance;
use App\Models\HostelBuilding;
use App\Models\HostelComplaint;
use App\Models\HostelMessMenu;
use App\Models\HostelRoom;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostelManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;
    protected User $boyStudentUser;
    protected Student $boyStudent;
    protected StudentEnrollment $boyEnrollment;
    protected User $girlStudentUser;
    protected Student $girlStudent;
    protected StudentEnrollment $girlEnrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Doon Valley Residential Academy',
            'slug' => 'doon-valley-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@doonvalley.edu',
            'contact_phone' => '+919876544444',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Doon Valley Campus',
            'code' => 'DVA-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Boarding Wing',
            'branch_code' => 'BW-' . rand(100, 999),
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
            'code' => 'AY-HST-' . rand(10, 99),
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Class 11',
            'code' => 'CLS-11-' . rand(100, 999),
            'order_index' => 11,
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
            'code' => '11-A-' . rand(100, 999),
            'capacity' => 35,
        ]);

        Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);

        $this->adminUser = User::factory()->create([
            'first_name' => 'Hostel',
            'last_name' => 'WardenChief',
            'email' => 'warden.' . Str::random(8) . '@doonvalley.edu',
            'phone' => '+9191' . rand(10000000, 99999999),
        ]);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // Boy student
        $this->boyStudentUser = User::factory()->create([
            'first_name' => 'Kabir',
            'last_name' => 'Saxena',
            'email' => 'kabir.' . Str::random(8) . '@doonvalley.edu',
            'phone' => '+9192' . rand(10000000, 99999999),
        ]);
        $this->boyStudent = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->boyStudentUser->id,
            'admission_number' => 'ADM-HST-B-' . rand(1000, 9999),
            'first_name' => 'Kabir',
            'last_name' => 'Saxena',
            'date_of_birth' => '2010-09-12',
            'gender' => 'male',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);
        $this->boyEnrollment = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->boyStudent->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '10',
            'status' => 'enrolled',
        ]);

        // Girl student
        $this->girlStudentUser = User::factory()->create([
            'first_name' => 'Ananya',
            'last_name' => 'Singh',
            'email' => 'ananya.' . Str::random(8) . '@doonvalley.edu',
            'phone' => '+9193' . rand(10000000, 99999999),
        ]);
        $this->girlStudent = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->girlStudentUser->id,
            'admission_number' => 'ADM-HST-G-' . rand(1000, 9999),
            'first_name' => 'Ananya',
            'last_name' => 'Singh',
            'date_of_birth' => '2010-11-05',
            'gender' => 'female',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);
        $this->girlEnrollment = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->girlStudent->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '11',
            'status' => 'enrolled',
        ]);
    }

    public function test_can_create_building_and_rooms(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Building
        $bldRes = $this->postJson('/api/v1/operations/hostel/buildings', [
            'branch_id' => $this->branch->id,
            'name' => 'Tagore Boys Residence',
            'building_type' => 'boys',
            'warden_name' => 'Dr. R.K. Sharma',
            'warden_phone' => '+919876500112',
        ]);

        $bldRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.building_type', 'boys');

        $bldId = $bldRes->json('data.id');

        // 2. Room
        $roomRes = $this->postJson('/api/v1/operations/hostel/rooms', [
            'building_id' => $bldId,
            'room_number' => 'B-101',
            'room_type' => 'double',
            'capacity' => 2,
            'fee_per_session' => 45000.00,
        ]);

        $roomRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_number', 'B-101')
            ->assertJsonPath('data.capacity', 2)
            ->assertJsonPath('data.occupied_beds', 0);

        $this->assertDatabaseHas('hostel_rooms', [
            'room_number' => 'B-101',
            'capacity' => 2,
            'occupied_beds' => 0,
        ]);
    }

    public function test_can_allocate_student_to_room_and_sync_occupancy(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Nehru Boys House',
            'building_type' => 'boys',
        ]);

        $room = HostelRoom::create([
            'building_id' => $bld->id,
            'room_number' => 'N-201',
            'room_type' => 'double',
            'capacity' => 2,
            'occupied_beds' => 0,
            'fee_per_session' => 50000.00,
        ]);

        $allocRes = $this->postJson('/api/v1/operations/hostel/allocate', [
            'room_id' => $room->id,
            'student_id' => $this->boyStudent->id,
            'enrollment_id' => $this->boyEnrollment->id,
            'bed_number' => 'Bed-1',
            'allocation_date' => Carbon::today()->toDateString(),
        ]);

        $allocRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bed_number', 'Bed-1')
            ->assertJsonPath('data.status', 'active');

        // Verify room occupied beds updated from 0 to 1
        $this->assertEquals(1, $room->fresh()->occupied_beds);

        $this->assertDatabaseHas('hostel_allocations', [
            'room_id' => $room->id,
            'student_id' => $this->boyStudent->id,
            'status' => 'active',
        ]);
    }

    public function test_enforces_gender_compatibility_for_hostels(): void
    {
        Sanctum::actingAs($this->adminUser);

        $boysBld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Shivaji Boys Hostel',
            'building_type' => 'boys',
        ]);

        $room = HostelRoom::create([
            'building_id' => $boysBld->id,
            'room_number' => 'S-301',
            'room_type' => 'single',
            'capacity' => 1,
        ]);

        // Attempt to allocate female student to boys hostel
        $res = $this->postJson('/api/v1/operations/hostel/allocate', [
            'room_id' => $room->id,
            'student_id' => $this->girlStudent->id,
            'enrollment_id' => $this->girlEnrollment->id,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('Boys hostel', $res->json('message'));
    }

    public function test_enforces_room_capacity_limit(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sarojini Girls Hostel',
            'building_type' => 'girls',
        ]);

        // Room with capacity 1
        $room = HostelRoom::create([
            'building_id' => $bld->id,
            'room_number' => 'G-101',
            'room_type' => 'single',
            'capacity' => 1,
            'occupied_beds' => 0,
        ]);

        // Allocate first girl
        $this->postJson('/api/v1/operations/hostel/allocate', [
            'room_id' => $room->id,
            'student_id' => $this->girlStudent->id,
            'enrollment_id' => $this->girlEnrollment->id,
            'bed_number' => 'Bed-A',
        ])->assertStatus(201);

        // Create second girl student
        $girlUser2 = User::factory()->create([
            'first_name' => 'Meera',
            'last_name' => 'Nambiar',
            'email' => 'meera.' . Str::random(8) . '@doonvalley.edu',
            'phone' => '+9194' . rand(10000000, 99999999),
        ]);
        $girl2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $girlUser2->id,
            'admission_number' => 'ADM-HST-G2-' . rand(1000, 9999),
            'first_name' => 'Meera',
            'last_name' => 'Nambiar',
            'date_of_birth' => '2010-04-18',
            'gender' => 'female',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);
        $girlEnroll2 = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $girl2->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '12',
            'status' => 'enrolled',
        ]);

        // Try allocating second student into full room
        $overRes = $this->postJson('/api/v1/operations/hostel/allocate', [
            'room_id' => $room->id,
            'student_id' => $girl2->id,
            'enrollment_id' => $girlEnroll2->id,
            'bed_number' => 'Bed-B',
        ]);

        $overRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('full capacity', $overRes->json('message'));
    }

    public function test_can_vacate_student_from_hostel_room(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Ashoka Residence',
            'building_type' => 'boys',
        ]);

        $room = HostelRoom::create([
            'building_id' => $bld->id,
            'room_number' => 'A-404',
            'room_type' => 'double',
            'capacity' => 2,
            'occupied_beds' => 0,
        ]);

        $allocRes = $this->postJson('/api/v1/operations/hostel/allocate', [
            'room_id' => $room->id,
            'student_id' => $this->boyStudent->id,
            'enrollment_id' => $this->boyEnrollment->id,
        ]);
        $allocId = $allocRes->json('data.id');
        $this->assertEquals(1, $room->fresh()->occupied_beds);

        // Vacate student
        $vacRes = $this->postJson("/api/v1/operations/hostel/vacate/{$allocId}", [
            'vacating_date' => Carbon::today()->toDateString(),
        ]);

        $vacRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'vacated');

        // Verify occupied beds returned to 0
        $this->assertEquals(0, $room->fresh()->occupied_beds);
    }

    public function test_can_record_hostel_daily_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Vivekananda Wing',
            'building_type' => 'boys',
        ]);

        $attRes = $this->postJson("/api/v1/operations/hostel/buildings/{$bld->id}/attendance", [
            'attendance_date' => Carbon::today()->toDateString(),
            'records' => [
                [
                    'student_id' => $this->boyStudent->id,
                    'status' => 'present',
                    'remarks' => 'In room during 9PM roll call',
                ],
            ],
        ]);

        $attRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('hostel_attendances', [
            'building_id' => $bld->id,
            'student_id' => $this->boyStudent->id,
            'status' => 'present',
        ]);
    }

    public function test_can_set_and_retrieve_weekly_mess_menu(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Central Mess & Dining',
            'building_type' => 'co_ed',
        ]);

        $menuRes = $this->postJson("/api/v1/operations/hostel/buildings/{$bld->id}/mess-menu", [
            'day_of_week' => 'monday',
            'meal_type' => 'breakfast',
            'menu_items' => ['Idli', 'Sambar', 'Coconut Chutney', 'Boiled Eggs', 'Tea/Coffee'],
            'special_notes' => 'Gluten-free option available upon request',
        ]);

        $menuRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meal_type', 'breakfast');

        $getRes = $this->getJson("/api/v1/operations/hostel/buildings/{$bld->id}/mess-menu");
        $getRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('breakfast', $getRes->json('data.0.meal_type'));
    }

    public function test_can_file_and_resolve_hostel_complaints(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Patel Residence',
            'building_type' => 'boys',
        ]);

        $room = HostelRoom::create([
            'building_id' => $bld->id,
            'room_number' => 'P-102',
            'room_type' => 'double',
            'capacity' => 2,
        ]);

        // 1. File complaint
        $compRes = $this->postJson('/api/v1/operations/hostel/complaints', [
            'building_id' => $bld->id,
            'room_id' => $room->id,
            'student_id' => $this->boyStudent->id,
            'title' => 'Bathroom Tap Leaking',
            'description' => 'Water continuous dripping from cold water faucet',
            'category' => 'plumbing',
            'priority' => 'high',
        ]);

        $compRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $compId = $compRes->json('data.id');

        // 2. Resolve complaint
        $resRes = $this->postJson("/api/v1/operations/hostel/complaints/{$compId}/resolve", [
            'status' => 'resolved',
            'resolution_notes' => 'Plumber replaced rubber washer and tightened faucet fitting.',
        ]);

        $resRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('hostel_complaints', [
            'id' => $compId,
            'status' => 'resolved',
        ]);
    }

    public function test_hostel_reports_and_occupancy_analytics(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bose Block',
            'building_type' => 'boys',
        ]);

        $room = HostelRoom::create([
            'building_id' => $bld->id,
            'room_number' => 'B-301',
            'room_type' => 'double',
            'capacity' => 2,
            'occupied_beds' => 1,
        ]);

        $repRes = $this->getJson("/api/v1/operations/hostel/reports?branch_id={$this->branch->id}");

        $repRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_buildings', 1)
            ->assertJsonPath('data.total_capacity', 2)
            ->assertJsonPath('data.occupied_beds', 1)
            ->assertJsonPath('data.vacant_beds', 1);

        $this->assertEquals(50.0, $repRes->json('data.occupancy_percentage'));
    }

    public function test_tenant_isolation_prevents_cross_tenant_hostel_access(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bld = HostelBuilding::create([
            'branch_id' => $this->branch->id,
            'name' => 'Secret Doon Manor',
            'building_type' => 'boys',
        ]);

        $room = HostelRoom::create([
            'building_id' => $bld->id,
            'room_number' => 'SEC-99',
            'room_type' => 'single',
            'capacity' => 1,
        ]);

        // Second tenant
        $tenant2 = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Mayo College',
            'slug' => 'mayo-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@mayo.edu',
            'contact_phone' => '+919876555555',
            'status' => 'active',
        ]);

        $user2 = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'HostelAdmin',
            'email' => 'other.hst.' . Str::random(8) . '@mayo.edu',
            'phone' => '+9190' . rand(10000000, 99999999),
        ]);
        $user2->assignRole('school_admin', $tenant2->id);
        TenantMembership::create([
            'tenant_id' => $tenant2->id,
            'user_id' => $user2->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setTenant($tenant2);
        Sanctum::actingAs($user2);

        $listRes = $this->getJson('/api/v1/operations/hostel/buildings');
        $names = collect($listRes->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Secret Doon Manor'));

        $roomRes = $this->getJson("/api/v1/operations/hostel/rooms/{$room->id}");
        $roomRes->assertStatus(404);
    }
}
