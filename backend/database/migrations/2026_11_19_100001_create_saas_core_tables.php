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
        // 1. Tenants (Organizations / Educational Trusts / Chains)
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->string('organization_type', 50)->default('trust'); // trust, private, government, society
            $table->string('contact_email', 255);
            $table->string('contact_phone', 50);
            $table->jsonb('billing_address')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->string('status', 30)->default('active')->index(); // active, in_trial, past_due, suspended, cancelled
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // 2. Schools (Institutions under a Tenant)
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 50); // e.g., APEX-MAIN
            $table->string('affiliation_number', 100)->nullable();
            $table->string('education_board', 50)->default('CBSE'); // CBSE, ICSE, STATE, IB, CAMBRIDGE
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('address')->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('stamp_path', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        // 3. School Branches (Campuses)
        Schema::create('school_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('branch_code', 50); // e.g., NORTH-01
            $table->string('campus_type', 50)->default('main'); // main, branch, primary_annex
            $table->jsonb('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['school_id', 'branch_code']);
            $table->index(['tenant_id', 'school_id']);
        });

        // 4. Tenant Domains (Custom domains & subdomains)
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain', 255)->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('verification_token', 100)->nullable();
            $table->string('ssl_status', 30)->default('pending'); // pending, active, failed
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_primary']);
        });

        // 5. Tenant Settings (Configurable key-values per tenant/school)
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->string('key', 100);
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'school_id', 'key']);
            $table->index(['tenant_id', 'key']);
        });

        // 6. Tenant Memberships (User to Tenant & Branch relationships)
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id', 'branch_id']);
            $table->index(['user_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('school_branches');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('tenants');
    }
};

