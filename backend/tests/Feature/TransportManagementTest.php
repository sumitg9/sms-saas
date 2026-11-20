<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransportAllocation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TransportDriver;
use App\Models\TransportFuelLog;
use App\Models\TransportGpsTelemetry;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\TransportVehicleMaintenance;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransportManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;
    protected User $driverUser;
    protected Employee $driverEmployee;
    protected User $studentUser;
    protected Student $student;
    protected StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Greenwood International',
            'slug' => 'greenwood-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@greenwood.edu',
            'contact_phone' => '+919876541111',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Greenwood Campus',
            'code' => 'GW-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Transport Wing',
            'branch_code' => 'TW-' . rand(100, 999),
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
            'code' => 'AY-TRN-' . rand(10, 99),
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Class 8',
            'code' => 'CLS-8-' . rand(100, 999),
            'order_index' => 8,
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section B',
            'code' => '8-B-' . rand(100, 999),
            'capacity' => 40,
        ]);

        Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);

        $this->adminUser = User::factory()->create([
            'first_name' => 'Transport',
            'last_name' => 'Manager',
            'email' => 'trn.admin.' . Str::random(8) . '@greenwood.edu',
            'phone' => '+9191' . rand(10000000, 99999999),
        ]);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // Driver user and employee
        $dept = EmployeeDepartment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Operations',
            'code' => 'OPS-' . rand(100, 999),
        ]);

        $desig = EmployeeDesignation::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Bus Driver',
            'code' => 'DRV-' . rand(100, 999),
        ]);

        $this->driverUser = User::factory()->create([
            'first_name' => 'Rajesh',
            'last_name' => 'Kumar',
            'email' => 'rajesh.drv.' . Str::random(8) . '@greenwood.edu',
            'phone' => '+9192' . rand(10000000, 99999999),
        ]);

        $this->driverEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->driverUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'DRV-EMP-' . rand(1000, 9999),
            'first_name' => 'Rajesh',
            'last_name' => 'Kumar',
            'gender' => 'male',
            'date_of_birth' => '1980-04-12',
            'date_of_joining' => '2022-01-10',
            'email' => 'rajesh.' . Str::random(8) . '@greenwood.edu',
            'phone' => '+9193' . rand(10000000, 99999999),
            'status' => 'active',
        ]);

        // Student
        $this->studentUser = User::factory()->create([
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'email' => 'priya.trn.' . Str::random(8) . '@greenwood.edu',
            'phone' => '+9194' . rand(10000000, 99999999),
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->studentUser->id,
            'admission_number' => 'ADM-TRN-' . rand(1000, 9999),
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'date_of_birth' => '2012-08-20',
            'gender' => 'female',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $this->enrollment = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '22',
            'status' => 'enrolled',
        ]);
    }

    public function test_can_register_vehicle_with_documents(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/operations/transport/vehicles', [
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-01-AB-' . rand(1000, 9999),
            'vehicle_model' => 'Tata Starbus 40-Seater',
            'capacity' => 40,
            'fitness_certificate_expiry' => Carbon::today()->addMonths(6)->toDateString(),
            'insurance_expiry' => Carbon::today()->addMonths(9)->toDateString(),
            'gps_device_id' => 'GPS-TRK-9001',
            'is_active' => true,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.capacity', 40)
            ->assertJsonPath('data.gps_device_id', 'GPS-TRK-9001');

        $this->assertDatabaseHas('transport_vehicles', [
            'gps_device_id' => 'GPS-TRK-9001',
            'capacity' => 40,
        ]);
    }

    public function test_can_register_driver(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/operations/transport/drivers', [
            'employee_id' => $this->driverEmployee->id,
            'license_number' => 'DL-KA-2022-' . rand(10000, 99999),
            'license_expiry' => Carbon::today()->addYears(3)->toDateString(),
            'phone' => '+919988776655',
            'is_active' => true,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('transport_drivers', [
            'employee_id' => $this->driverEmployee->id,
            'phone' => '+919988776655',
        ]);
    }

    public function test_can_create_route_with_stops_and_fares(): void
    {
        Sanctum::actingAs($this->adminUser);

        $veh = TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-02-CD-' . rand(1000, 9999),
            'vehicle_model' => 'Ashok Leyland Falcon',
            'capacity' => 35,
            'is_active' => true,
        ]);

        $drv = TransportDriver::create([
            'employee_id' => $this->driverEmployee->id,
            'license_number' => 'DL-KA-5566',
            'license_expiry' => Carbon::today()->addYears(2)->toDateString(),
            'phone' => '+919876543210',
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/operations/transport/routes', [
            'branch_id' => $this->branch->id,
            'route_name' => 'Route 101 - North City',
            'vehicle_id' => $veh->id,
            'driver_id' => $drv->id,
            'start_point' => 'Central Bus Station',
            'end_point' => 'Greenwood Campus',
            'fare' => 1200.00,
            'stops' => [
                [
                    'stop_name' => 'Sector 4 Junction',
                    'pickup_time' => '07:15:00',
                    'drop_time' => '15:45:00',
                    'fare_amount' => 1000.00,
                    'stop_order' => 1,
                ],
                [
                    'stop_name' => 'Maple Gardens Gate',
                    'pickup_time' => '07:30:00',
                    'drop_time' => '15:30:00',
                    'fare_amount' => 1200.00,
                    'stop_order' => 2,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.route_name', 'Route 101 - North City')
            ->assertJsonCount(2, 'data.stops');

        $this->assertDatabaseHas('transport_routes', [
            'route_name' => 'Route 101 - North City',
            'start_point' => 'Central Bus Station',
        ]);
    }

    public function test_can_add_stop_to_existing_route(): void
    {
        Sanctum::actingAs($this->adminUser);

        $route = TransportRoute::create([
            'branch_id' => $this->branch->id,
            'route_name' => 'Route 202 - South Suburb',
            'start_point' => 'South Depot',
            'end_point' => 'School Main Gate',
            'fare' => 1500.00,
        ]);

        $res = $this->postJson("/api/v1/operations/transport/routes/{$route->id}/stops", [
            'stop_name' => 'Oakridge Crossing',
            'pickup_time' => '07:20:00',
            'drop_time' => '15:40:00',
            'fare_amount' => 1400.00,
            'stop_order' => 1,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stop_name', 'Oakridge Crossing');

        $this->assertDatabaseHas('transport_stops', [
            'route_id' => $route->id,
            'stop_name' => 'Oakridge Crossing',
        ]);
    }

    public function test_can_allocate_student_to_route(): void
    {
        Sanctum::actingAs($this->adminUser);

        $veh = TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-03-EF-' . rand(1000, 9999),
            'vehicle_model' => 'Eicher Starline',
            'capacity' => 20,
        ]);

        $route = TransportRoute::create([
            'branch_id' => $this->branch->id,
            'route_name' => 'Route 303 - East Line',
            'vehicle_id' => $veh->id,
            'start_point' => 'East Terminal',
            'end_point' => 'School',
            'fare' => 1100.00,
        ]);

        $stop = TransportStop::create([
            'route_id' => $route->id,
            'stop_name' => 'East Park Circle',
            'pickup_time' => '07:25:00',
            'drop_time' => '15:35:00',
            'fare_amount' => 1100.00,
            'stop_order' => 1,
        ]);

        $res = $this->postJson('/api/v1/operations/transport/allocate', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'route_id' => $route->id,
            'stop_id' => $stop->id,
            'academic_session_id' => $this->session->id,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('student_transport_allocations', [
            'student_id' => $this->student->id,
            'route_id' => $route->id,
            'stop_id' => $stop->id,
            'status' => 'active',
        ]);
    }

    public function test_enforces_vehicle_capacity_limit_on_allocation(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Vehicle with capacity of 1
        $veh = TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-04-GH-' . rand(1000, 9999),
            'vehicle_model' => 'Force Traveller Mini',
            'capacity' => 1,
        ]);

        $route = TransportRoute::create([
            'branch_id' => $this->branch->id,
            'route_name' => 'Route Mini',
            'vehicle_id' => $veh->id,
            'start_point' => 'A',
            'end_point' => 'B',
            'fare' => 500.00,
        ]);

        $stop = TransportStop::create([
            'route_id' => $route->id,
            'stop_name' => 'Stop 1',
            'pickup_time' => '07:00:00',
            'drop_time' => '15:00:00',
            'stop_order' => 1,
        ]);

        // First student allocated
        StudentTransportAllocation::create([
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'route_id' => $route->id,
            'stop_id' => $stop->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        // Create second student
        $studentUser2 = User::factory()->create([
            'first_name' => 'Rohan',
            'last_name' => 'Verma',
            'email' => 'rohan.' . Str::random(8) . '@greenwood.edu',
            'phone' => '+9195' . rand(10000000, 99999999),
        ]);
        $student2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $studentUser2->id,
            'admission_number' => 'ADM-TRN-' . rand(1000, 9999),
            'first_name' => 'Rohan',
            'last_name' => 'Verma',
            'date_of_birth' => '2012-04-10',
            'gender' => 'male',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);
        $enrollment2 = StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student2->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '23',
            'status' => 'enrolled',
        ]);

        // Try allocating second student (capacity is 1)
        $res = $this->postJson('/api/v1/operations/transport/allocate', [
            'student_id' => $student2->id,
            'enrollment_id' => $enrollment2->id,
            'route_id' => $route->id,
            'stop_id' => $stop->id,
            'academic_session_id' => $this->session->id,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('capacity limit', $res->json('message'));
    }

    public function test_can_log_maintenance_and_fuel_expenses(): void
    {
        Sanctum::actingAs($this->adminUser);

        $veh = TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-05-IJ-' . rand(1000, 9999),
            'vehicle_model' => 'Tata Starbus',
            'capacity' => 45,
        ]);

        // 1. Maintenance
        $maintRes = $this->postJson("/api/v1/operations/transport/vehicles/{$veh->id}/maintenance", [
            'maintenance_type' => 'brake_repair',
            'service_date' => Carbon::today()->toDateString(),
            'odometer_reading' => 45200,
            'cost' => 8500.00,
            'service_center' => 'Tata Authorized Service Hub',
            'invoice_number' => 'INV-TAT-9988',
            'notes' => 'Replaced front brake pads and fluid flushed.',
        ]);

        $maintRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cost', '8500.00');

        $this->assertDatabaseHas('transport_vehicle_maintenances', [
            'vehicle_id' => $veh->id,
            'maintenance_type' => 'brake_repair',
            'cost' => 8500.00,
        ]);

        // 2. Fuel Log
        $fuelRes = $this->postJson("/api/v1/operations/transport/vehicles/{$veh->id}/fuel", [
            'fuel_date' => Carbon::today()->toDateString(),
            'fuel_type' => 'diesel',
            'quantity_litres' => 50.0,
            'cost_per_litre' => 92.50,
            'odometer_reading' => 45350,
            'receipt_number' => 'FUEL-PMP-4455',
        ]);

        $fuelRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_cost', '4625.00');

        $this->assertDatabaseHas('transport_fuel_logs', [
            'vehicle_id' => $veh->id,
            'quantity_litres' => 50.00,
            'total_cost' => 4625.00,
        ]);
    }

    public function test_can_record_daily_transport_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        $route = TransportRoute::create([
            'branch_id' => $this->branch->id,
            'route_name' => 'Route Att-1',
            'start_point' => 'Depot',
            'end_point' => 'Campus',
            'fare' => 800.00,
        ]);

        $res = $this->postJson("/api/v1/operations/transport/routes/{$route->id}/attendance", [
            'attendance_date' => Carbon::today()->toDateString(),
            'trip_type' => 'pickup',
            'records' => [
                [
                    'student_id' => $this->student->id,
                    'status' => 'present',
                    'remarks' => 'Boarded on time',
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('transport_attendances', [
            'route_id' => $route->id,
            'student_id' => $this->student->id,
            'trip_type' => 'pickup',
            'status' => 'present',
        ]);
    }

    public function test_can_ingest_gps_telemetry_coordinates(): void
    {
        Sanctum::actingAs($this->adminUser);

        $veh = TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-06-KL-' . rand(1000, 9999),
            'vehicle_model' => 'Tata Marcopolo',
            'capacity' => 30,
            'gps_device_id' => 'GPS-HW-5501',
        ]);

        $res = $this->postJson("/api/v1/operations/transport/vehicles/{$veh->id}/telemetry", [
            'latitude' => 12.9715987,
            'longitude' => 77.5945627,
            'speed_kph' => 38.5,
            'heading' => 180.0,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.speed_kph', '38.50');

        $this->assertDatabaseHas('transport_gps_telemetries', [
            'vehicle_id' => $veh->id,
            'speed_kph' => 38.50,
        ]);
    }

    public function test_fleet_reports_and_compliance_alerts(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Vehicle with expired insurance
        TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-07-MN-' . rand(1000, 9999),
            'vehicle_model' => 'Force Traveller',
            'capacity' => 25,
            'fitness_certificate_expiry' => Carbon::today()->addMonths(4)->toDateString(),
            'insurance_expiry' => Carbon::today()->subDays(5)->toDateString(), // expired
        ]);

        $res = $this->getJson("/api/v1/operations/transport/reports?branch_id={$this->branch->id}");

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.compliance_alerts.insurance_expired_count', 1);
    }

    public function test_tenant_isolation_prevents_cross_tenant_transport_access(): void
    {
        Sanctum::actingAs($this->adminUser);

        $veh = TransportVehicle::create([
            'branch_id' => $this->branch->id,
            'registration_number' => 'KA-99-ZZ-' . rand(1000, 9999),
            'vehicle_model' => 'Secret Bus',
            'capacity' => 50,
        ]);

        // Second tenant
        $tenant2 = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Hilltop Academy',
            'slug' => 'hilltop-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@hilltop.edu',
            'contact_phone' => '+919876542222',
            'status' => 'active',
        ]);

        $user2 = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'TransportManager',
            'email' => 'other.trn.' . Str::random(8) . '@hilltop.edu',
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

        $listRes = $this->getJson('/api/v1/operations/transport/vehicles');
        $regNumbers = collect($listRes->json('data'))->pluck('registration_number');
        $this->assertFalse($regNumbers->contains($veh->registration_number));

        $showRes = $this->getJson("/api/v1/operations/transport/vehicles/{$veh->id}");
        $showRes->assertStatus(404);
    }
}

