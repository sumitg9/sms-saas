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
        // 119. Audit Logs (IMMUTABLE - Non-repudiation audit trail)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('school_branches')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_email', 255)->nullable();
            $table->string('action', 50); // created, updated, deleted, approved, exported, logged_in
            $table->string('auditable_type', 150); // App\Models\Student, App\Models\FeePayment
            $table->uuid('auditable_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        // 120. File Uploads (Supabase Storage reference tracking)
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bucket_name', 100);
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->bigInteger('file_size')->default(0);
            $table->string('mime_type', 100);
            $table->boolean('is_private')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'bucket_name']);
        });

        // 121. Import Jobs (Excel / CSV bulk migration tracking)
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('import_type', 50); // students, staff, fees, books
            $table->string('file_path', 500);
            $table->string('status', 30)->default('pending')->index(); // pending, processing, completed, failed
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->jsonb('errors')->nullable();
            $table->timestampsTz();
        });

        // 122. Export Jobs (Asynchronous reports / exports)
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('export_type', 50); // students, fee_defaulters, payroll, report_cards
            $table->string('format', 20)->default('xlsx'); // xlsx, csv, pdf
            $table->string('file_path', 500)->nullable();
            $table->string('status', 30)->default('pending')->index(); // pending, processing, completed, failed
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });

        // 123. System Settings (Platform-wide configuration)
        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group', 50); // general, billing, email, sms, security
            $table->string('key', 100);
            $table->jsonb('value');
            $table->string('description', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('file_uploads');
        Schema::dropIfExists('audit_logs');
    }
};

