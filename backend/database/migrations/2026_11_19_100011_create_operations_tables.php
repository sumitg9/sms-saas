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
        // 95. Library Books (Catalog)
        Schema::create('library_books', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('isbn', 50)->nullable();
            $table->string('author', 200);
            $table->string('publisher', 200)->nullable();
            $table->string('edition', 50)->nullable();
            $table->string('category', 100);
            $table->integer('total_copies')->default(1);
            $table->integer('available_copies')->default(1);
            $table->string('shelf_no', 50)->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'title']);
            $table->index(['branch_id', 'isbn']);
        });

        // 96. Book Copies (Physical copies with unique barcodes)
        Schema::create('book_copies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->string('barcode', 100)->unique();
            $table->integer('copy_number')->default(1);
            $table->string('status', 30)->default('available')->index(); // available, issued, lost, damaged
            $table->timestampsTz();
        });

        // 97. Book Issues (Circulation register)
        Schema::create('book_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('copy_id')->constrained('book_copies')->cascadeOnDelete();
            $table->string('member_type', 30); // student, employee
            $table->uuid('member_id');
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->decimal('fine_amount', 8, 2)->default(0.00);
            $table->boolean('fine_paid')->default(false);
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('issued')->index(); // issued, returned, overdue
            $table->timestampsTz();

            $table->index(['member_type', 'member_id', 'status']);
        });

        // 98. Book Reservations
        Schema::create('book_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->string('member_type', 30);
            $table->uuid('member_id');
            $table->date('reservation_date');
            $table->string('status', 30)->default('reserved'); // reserved, fulfilled, cancelled
            $table->timestampsTz();
        });

        // 99. Transport Vehicles (Bus fleet)
        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('registration_number', 50)->unique();
            $table->string('vehicle_model', 100);
            $table->integer('capacity')->default(40);
            $table->date('fitness_certificate_expiry')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->string('gps_device_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['branch_id', 'is_active']);
        });

        // 100. Transport Drivers
        Schema::create('transport_drivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('license_number', 50);
            $table->date('license_expiry');
            $table->string('phone', 50);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // 101. Transport Routes
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('route_name', 150);
            $table->foreignUuid('vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->foreignUuid('driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->string('start_point', 150);
            $table->string('end_point', 150);
            $table->decimal('fare', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'route_name']);
        });

        // 102. Transport Stops
        Schema::create('transport_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->string('stop_name', 150);
            $table->time('pickup_time');
            $table->time('drop_time');
            $table->decimal('fare_amount', 10, 2)->default(0.00);
            $table->smallInteger('stop_order')->default(1);
            $table->timestampsTz();

            $table->index(['route_id', 'stop_order']);
        });

        // 103. Student Transport Allocations
        Schema::create('student_transport_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignUuid('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignUuid('stop_id')->constrained('transport_stops')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('status', 30)->default('active'); // active, stopped
            $table->timestampsTz();

            $table->unique(['enrollment_id', 'academic_session_id']);
        });

        // 104. Inventory Categories
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 105. Inventory Items (Fixed assets and consumables)
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('inventory_categories')->cascadeOnDelete();
            $table->string('item_name', 150);
            $table->string('item_code', 50);
            $table->string('unit_of_measure', 30)->default('pcs'); // pcs, box, kg, pack
            $table->integer('min_stock_alert')->default(5);
            $table->integer('current_stock')->default(0);
            $table->timestampsTz();

            $table->unique(['branch_id', 'item_code']);
        });

        // 106. Inventory Vendors
        Schema::create('inventory_vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 50);
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 50)->nullable();
            $table->timestampsTz();
        });

        // 107. Inventory Purchases
        Schema::create('inventory_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('inventory_vendors');
            $table->string('invoice_number', 100);
            $table->date('purchase_date');
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_status', 30)->default('paid');
            $table->string('bill_path', 500)->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'purchase_date']);
        });

        // 108. Inventory Issues (Disbursements to departments/staff)
        Schema::create('inventory_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('issued_to_type', 30); // department, employee
            $table->uuid('issued_to_id');
            $table->integer('quantity');
            $table->date('issue_date');
            $table->date('return_date')->nullable();
            $table->string('status', 30)->default('issued'); // issued, returned, consumed
            $table->timestampsTz();
        });

        // 109. Hostel Buildings
        Schema::create('hostel_buildings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('building_type', 30)->default('boys'); // boys, girls, co_ed
            $table->string('warden_name', 100)->nullable();
            $table->string('warden_phone', 50)->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'name']);
        });

        // 110. Hostel Rooms
        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('building_id')->constrained('hostel_buildings')->cascadeOnDelete();
            $table->string('room_number', 50);
            $table->string('room_type', 50)->default('double'); // single, double, dormitory
            $table->integer('capacity')->default(2);
            $table->integer('occupied_beds')->default(0);
            $table->decimal('fee_per_session', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['building_id', 'room_number']);
        });

        // 111. Hostel Allocations
        Schema::create('hostel_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('hostel_rooms')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->string('bed_number', 20)->nullable();
            $table->date('allocation_date');
            $table->date('vacating_date')->nullable();
            $table->string('status', 30)->default('active'); // active, vacated
            $table->timestampsTz();

            $table->unique(['enrollment_id', 'room_id']);
        });

        // 112. Visitors (Front desk)
        Schema::create('visitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('phone', 50);
            $table->string('national_id', 50)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->text('address')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'phone']);
        });

        // 113. Visitor Passes
        Schema::create('visitor_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('visitor_id')->constrained('visitors')->cascadeOnDelete();
            $table->string('pass_number', 50);
            $table->foreignUuid('host_employee_id')->constrained('employees');
            $table->string('purpose', 255);
            $table->timestampTz('check_in_time')->useCurrent();
            $table->timestampTz('check_out_time')->nullable();
            $table->boolean('badge_printed')->default(false);
            $table->string('status', 30)->default('checked_in'); // checked_in, checked_out
            $table->timestampsTz();

            $table->unique(['branch_id', 'pass_number']);
        });

        // 114. Student Gate Passes (Early departures)
        Schema::create('student_gate_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('pass_number', 50);
            $table->foreignUuid('guardian_id')->constrained('guardians');
            $table->text('reason');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_time')->useCurrent();
            $table->timestampTz('actual_exit_time')->nullable();
            $table->string('status', 30)->default('approved'); // approved, exited, rejected
            $table->timestampsTz();

            $table->unique(['branch_id', 'pass_number']);
        });

        // 115. Clinic Visits (Infirmary/Medical room)
        Schema::create('clinic_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestampTz('visit_date')->useCurrent();
            $table->text('symptoms');
            $table->text('diagnosis')->nullable();
            $table->text('treatment_given');
            $table->boolean('referred_to_hospital')->default(false);
            $table->foreignUuid('attended_by_staff_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'visit_date']);
        });

        // 116. Medical Conditions
        Schema::create('medical_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('condition_name', 150);
            $table->string('severity', 30)->default('mild'); // mild, moderate, severe
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        // 117. School Events
        Schema::create('school_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('event_category', 50)->default('cultural'); // sports, cultural, academic, competition
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');
            $table->string('venue', 150)->nullable();
            $table->decimal('budget', 12, 2)->default(0.00);
            $table->boolean('is_published')->default(true);
            $table->timestampsTz();

            $table->index(['branch_id', 'start_datetime']);
        });

        // 118. Event Participants
        Schema::create('event_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('school_events')->cascadeOnDelete();
            $table->string('participant_type', 30); // student, employee
            $table->uuid('participant_id');
            $table->string('role', 50)->default('contestant'); // contestant, organizer, volunteer, audience
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'participant_type', 'participant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('school_events');
        Schema::dropIfExists('medical_conditions');
        Schema::dropIfExists('clinic_visits');
        Schema::dropIfExists('student_gate_passes');
        Schema::dropIfExists('visitor_passes');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('hostel_allocations');
        Schema::dropIfExists('hostel_rooms');
        Schema::dropIfExists('hostel_buildings');
        Schema::dropIfExists('inventory_issues');
        Schema::dropIfExists('inventory_purchases');
        Schema::dropIfExists('inventory_vendors');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('student_transport_allocations');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('transport_routes');
        Schema::dropIfExists('transport_drivers');
        Schema::dropIfExists('transport_vehicles');
        Schema::dropIfExists('book_reservations');
        Schema::dropIfExists('book_issues');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('library_books');
    }
};

