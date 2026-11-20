<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Transport Vehicle Maintenance
        Schema::create('transport_vehicle_maintenances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->string('maintenance_type', 50); // routine_service, oil_change, brake_repair, tire_replacement, overhaul, inspection
            $table->date('service_date');
            $table->integer('odometer_reading')->nullable();
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->string('service_center', 150)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['vehicle_id', 'service_date']);
        });

        // 2. Transport Fuel Logs
        Schema::create('transport_fuel_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->date('fuel_date');
            $table->string('fuel_type', 30)->default('diesel'); // diesel, petrol, cng, electric
            $table->decimal('quantity_litres', 8, 2);
            $table->decimal('cost_per_litre', 8, 2)->default(0.00);
            $table->decimal('total_cost', 10, 2);
            $table->integer('odometer_reading')->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->timestampsTz();

            $table->index(['vehicle_id', 'fuel_date']);
        });

        // 3. Transport Attendance
        Schema::create('transport_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('trip_type', 20)->default('pickup'); // pickup, drop
            $table->string('status', 20)->default('present'); // present, absent, skipped
            $table->string('remarks', 255)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'route_id', 'student_id', 'attendance_date', 'trip_type'], 'trans_att_unique');
        });

        // 4. Transport GPS Telemetry (Ingestion Schema Architecture)
        Schema::create('transport_gps_telemetries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kph', 5, 2)->default(0.00);
            $table->decimal('heading', 5, 2)->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index(['vehicle_id', 'recorded_at']);
        });

        // 5. Inventory Stock Transactions (Full audit ledger for items)
        Schema::create('inventory_stock_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('transaction_type', 30); // purchase, issue, return, damaged, adjustment
            $table->integer('quantity'); // positive for stock increase, negative for stock decrease
            $table->integer('balance_after')->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['item_id', 'created_at']);
        });

        // 6. Hostel Attendance
        Schema::create('hostel_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('building_id')->constrained('hostel_buildings')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('present'); // present, absent, on_leave, sick
            $table->string('remarks', 255)->nullable();
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'building_id', 'student_id', 'attendance_date'], 'hostel_att_unique');
        });

        // 7. Hostel Mess Menus
        Schema::create('hostel_mess_menus', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('building_id')->constrained('hostel_buildings')->cascadeOnDelete();
            $table->string('day_of_week', 20); // monday, tuesday, etc.
            $table->string('meal_type', 30); // breakfast, lunch, snacks, dinner
            $table->jsonb('menu_items'); // array of food items
            $table->text('special_notes')->nullable();
            $table->timestampsTz();

            $table->unique(['building_id', 'day_of_week', 'meal_type']);
        });

        // 8. Hostel Complaints
        Schema::create('hostel_complaints', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('building_id')->constrained('hostel_buildings')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('hostel_rooms')->nullOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->string('category', 50)->default('other'); // plumbing, electrical, cleanliness, food, security, other
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('status', 20)->default('pending'); // pending, in_progress, resolved, closed
            $table->text('resolution_notes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['building_id', 'status']);
        });

        // 9. Visitor Student Pickups (Authorization and Verification)
        Schema::create('visitor_student_pickups', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('visitor_pass_id')->constrained('visitor_passes')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('authorization_status', 30)->default('verified'); // verified, pending, rejected
            $table->string('relationship_to_student', 100);
            $table->boolean('parent_consent_confirmed')->default(false);
            $table->string('parent_contact_phone', 50)->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['student_id', 'authorization_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_student_pickups');
        Schema::dropIfExists('hostel_complaints');
        Schema::dropIfExists('hostel_mess_menus');
        Schema::dropIfExists('hostel_attendances');
        Schema::dropIfExists('inventory_stock_transactions');
        Schema::dropIfExists('transport_gps_telemetries');
        Schema::dropIfExists('transport_attendances');
        Schema::dropIfExists('transport_fuel_logs');
        Schema::dropIfExists('transport_vehicle_maintenances');
    }
};

