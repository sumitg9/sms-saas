<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryStockTransaction;
use App\Models\InventoryVendor;
use App\Models\School;
use App\Models\SchoolBranch;
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

class InventoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected User $adminUser;
    protected EmployeeDepartment $department;
    protected Employee $employee;
    protected User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'St. Xavier Global Academy',
            'slug' => 'st-xavier-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@stxavier.edu',
            'contact_phone' => '+919876543333',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'St. Xavier Campus',
            'code' => 'STX-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Stores & Inventory Wing',
            'branch_code' => 'IW-' . rand(100, 999),
            'is_main_branch' => true,
        ]);

        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($this->tenant);
        $tenantContext->setSchool($this->school);
        $tenantContext->setBranch($this->branch);

        Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);

        $this->adminUser = User::factory()->create([
            'first_name' => 'Inventory',
            'last_name' => 'Officer',
            'email' => 'inv.admin.' . Str::random(8) . '@stxavier.edu',
            'phone' => '+9191' . rand(10000000, 99999999),
        ]);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        $this->department = EmployeeDepartment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Science Laboratory',
            'code' => 'LAB-' . rand(100, 999),
        ]);

        $desig = EmployeeDesignation::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'title' => 'Lab In-Charge',
            'code' => 'LIC-' . rand(100, 999),
        ]);

        $this->employeeUser = User::factory()->create([
            'first_name' => 'Kavita',
            'last_name' => 'Nair',
            'email' => 'kavita.emp.' . Str::random(8) . '@stxavier.edu',
            'phone' => '+9192' . rand(10000000, 99999999),
        ]);

        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->employeeUser->id,
            'department_id' => $this->department->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-INV-' . rand(1000, 9999),
            'first_name' => 'Kavita',
            'last_name' => 'Nair',
            'gender' => 'female',
            'date_of_birth' => '1988-03-25',
            'date_of_joining' => '2021-08-15',
            'email' => 'kavita.' . Str::random(8) . '@stxavier.edu',
            'phone' => '+9193' . rand(10000000, 99999999),
            'status' => 'active',
        ]);
    }

    public function test_can_create_category_and_items_with_initial_stock(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Category
        $catRes = $this->postJson('/api/v1/operations/inventory/categories', [
            'branch_id' => $this->branch->id,
            'name' => 'Stationery',
            'code' => 'STAT',
        ]);

        $catRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'STAT');

        $catId = $catRes->json('data.id');

        // 2. Item with initial stock
        $itemRes = $this->postJson('/api/v1/operations/inventory/items', [
            'branch_id' => $this->branch->id,
            'category_id' => $catId,
            'item_name' => 'A4 Paper Reams',
            'item_code' => 'ITM-PAP-A4',
            'unit_of_measure' => 'box',
            'min_stock_alert' => 10,
            'initial_stock' => 50,
            'unit_cost' => 250.00,
        ]);

        $itemRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_stock', 50)
            ->assertJsonPath('data.min_stock_alert', 10);

        $itemId = $itemRes->json('data.id');

        // Verify initial transaction
        $this->assertDatabaseHas('inventory_stock_transactions', [
            'item_id' => $itemId,
            'transaction_type' => 'adjustment',
            'quantity' => 50,
            'balance_after' => 50,
        ]);
    }

    public function test_can_register_vendor(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/operations/inventory/vendors', [
            'name' => 'National Educational Supplies Ltd',
            'contact_person' => 'Sunil Gupta',
            'phone' => '+919876512345',
            'email' => 'sales@nesupplies.com',
            'address' => '42 Industrial Area, Phase II',
            'gst_number' => '29AAACN1234F1Z8',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gst_number', '29AAACN1234F1Z8');

        $this->assertDatabaseHas('inventory_vendors', [
            'name' => 'National Educational Supplies Ltd',
            'gst_number' => '29AAACN1234F1Z8',
        ]);
    }

    public function test_can_receive_purchase_and_update_stock_ledger(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Lab Equipment',
            'code' => 'LAB-EQ',
        ]);

        $item = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Glass Test Tubes (Set of 20)',
            'item_code' => 'TUB-20',
            'unit_of_measure' => 'box',
            'min_stock_alert' => 5,
            'current_stock' => 10,
        ]);

        $vendor = InventoryVendor::create([
            'name' => 'Borosil Scientific Works',
            'phone' => '+919822334455',
        ]);

        $res = $this->postJson('/api/v1/operations/inventory/purchases', [
            'branch_id' => $this->branch->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-BSL-2026-09',
            'purchase_date' => Carbon::today()->toDateString(),
            'total_amount' => 7500.00,
            'payment_status' => 'paid',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 25,
                    'unit_cost' => 300.00,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice_number', 'INV-BSL-2026-09');

        // Verify stock updated from 10 to 35
        $this->assertEquals(35, $item->fresh()->current_stock);

        $this->assertDatabaseHas('inventory_stock_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'purchase',
            'quantity' => 25,
            'balance_after' => 35,
        ]);
    }

    public function test_can_issue_stock_to_department_and_verify_decrement(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sports Equipment',
            'code' => 'SPT',
        ]);

        $item = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Football Size 5',
            'item_code' => 'FB-5',
            'unit_of_measure' => 'pcs',
            'min_stock_alert' => 2,
            'current_stock' => 15,
        ]);

        $res = $this->postJson('/api/v1/operations/inventory/issue', [
            'item_id' => $item->id,
            'issued_to_type' => 'department',
            'issued_to_id' => $this->department->id,
            'quantity' => 4,
            'issue_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 4);

        $this->assertEquals(11, $item->fresh()->current_stock);

        $this->assertDatabaseHas('inventory_stock_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'issue',
            'quantity' => -4,
            'balance_after' => 11,
        ]);
    }

    public function test_cannot_issue_more_stock_than_available(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Electronics',
            'code' => 'ELEC',
        ]);

        $item = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'HDMI Cable 5M',
            'item_code' => 'CAB-HDMI-5M',
            'unit_of_measure' => 'pcs',
            'min_stock_alert' => 1,
            'current_stock' => 2,
        ]);

        $res = $this->postJson('/api/v1/operations/inventory/issue', [
            'item_id' => $item->id,
            'issued_to_type' => 'employee',
            'issued_to_id' => $this->employee->id,
            'quantity' => 5, // exceeds 2
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('Insufficient stock', $res->json('message'));
    }

    public function test_can_return_issued_stock_to_inventory(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'AV Equipment',
            'code' => 'AV',
        ]);

        $item = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Wireless Presenter Clicker',
            'item_code' => 'CLK-WL',
            'unit_of_measure' => 'pcs',
            'current_stock' => 5,
        ]);

        // Issue 2 clickers
        $issueRes = $this->postJson('/api/v1/operations/inventory/issue', [
            'item_id' => $item->id,
            'issued_to_type' => 'employee',
            'issued_to_id' => $this->employee->id,
            'quantity' => 2,
        ]);
        $issueId = $issueRes->json('data.id');
        $this->assertEquals(3, $item->fresh()->current_stock);

        // Return 2 clickers back
        $returnRes = $this->postJson("/api/v1/operations/inventory/return/{$issueId}", [
            'return_quantity' => 2,
        ]);

        $returnRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'returned');

        $this->assertEquals(5, $item->fresh()->current_stock);

        $this->assertDatabaseHas('inventory_stock_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'return',
            'quantity' => 2,
            'balance_after' => 5,
        ]);
    }

    public function test_can_report_damaged_stock(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Glassware',
            'code' => 'GLS',
        ]);

        $item = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Conical Flasks 250ml',
            'item_code' => 'FLK-250',
            'unit_of_measure' => 'pcs',
            'current_stock' => 20,
        ]);

        $res = $this->postJson("/api/v1/operations/inventory/items/{$item->id}/damaged", [
            'quantity' => 3,
            'remarks' => 'Broken during lab earthquake drill',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_stock', 17);

        $this->assertDatabaseHas('inventory_stock_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'damaged',
            'quantity' => -3,
            'balance_after' => 17,
        ]);
    }

    public function test_low_stock_alerts_and_purchase_reports(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'First Aid',
            'code' => 'MED',
        ]);

        // Item 1: Low stock (current <= min_stock_alert)
        InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Antiseptic Solution 500ml',
            'item_code' => 'DET-500',
            'min_stock_alert' => 5,
            'current_stock' => 2, // low
        ]);

        // Item 2: Ample stock
        InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Bandages Pack of 100',
            'item_code' => 'BND-100',
            'min_stock_alert' => 2,
            'current_stock' => 20,
        ]);

        // 1. Check Low Stock Endpoint
        $lowRes = $this->getJson("/api/v1/operations/inventory/low-stock?branch_id={$this->branch->id}");
        $lowRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $lowCodes = collect($lowRes->json('data'))->pluck('item_code');
        $this->assertTrue($lowCodes->contains('DET-500'));
        $this->assertFalse($lowCodes->contains('BND-100'));

        // 2. Check Reports Endpoint
        $repRes = $this->getJson("/api/v1/operations/inventory/reports?branch_id={$this->branch->id}");
        $repRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.low_stock_alerts_count', 1);
    }

    public function test_tenant_isolation_prevents_cross_tenant_inventory_access(): void
    {
        Sanctum::actingAs($this->adminUser);

        $cat = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Secret Vault Category',
            'code' => 'SEC-CAT',
        ]);

        $item = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $cat->id,
            'item_name' => 'Secret St. Xavier Asset',
            'item_code' => 'SEC-AST',
            'current_stock' => 100,
        ]);

        // Second tenant
        $tenant2 = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'St. Mary High',
            'slug' => 'st-mary-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@stmary.edu',
            'contact_phone' => '+919876544444',
            'status' => 'active',
        ]);

        $user2 = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'InventoryUser',
            'email' => 'other.inv.' . Str::random(8) . '@stmary.edu',
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

        $listRes = $this->getJson('/api/v1/operations/inventory/items');
        $codes = collect($listRes->json('data'))->pluck('item_code');
        $this->assertFalse($codes->contains('SEC-AST'));

        $showRes = $this->getJson("/api/v1/operations/inventory/items/{$item->id}");
        $showRes->assertStatus(404);
    }
}

