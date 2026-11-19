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
        // 14. Roles (Multi-Tenant Scoped Roles)
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete(); // null = system/global role
            $table->string('name', 100);
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_system_defined')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_system_defined']);
        });

        // 15. Permissions (Granular Action Matrix)
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150)->unique(); // e.g., students.view, fees.approve, exams.export
            $table->string('module', 50)->index(); // students, fees, exams, attendance, etc.
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        // 16. Role Permissions (Pivot)
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['role_id', 'permission_id']);
        });

        // 17. User Roles (Compound Tenant/Branch Assigned Roles)
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('school_branches')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'role_id', 'tenant_id', 'branch_id']);
            $table->index(['user_id', 'tenant_id']);
        });

        // 18. User Sessions (Tracking active sessions across devices)
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('device_type', 50)->default('desktop'); // desktop, mobile, tablet
            $table->timestampTz('last_activity_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['user_id', 'last_activity_at']);
        });

        // 19. Login Histories (Audit log of authentication events)
        Schema::create('login_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('status', 30); // success, failed_password, failed_2fa, locked_out
            $table->string('failure_reason', 255)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['email', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};

