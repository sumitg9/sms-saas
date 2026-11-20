<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Certificate Templates
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('school_branches')->nullOnDelete();
            $table->string('type', 50); // transfer, bonafide, character, study, fee_clearance, custom
            $table->string('title', 150);
            $table->text('body_template');
            $table->text('header_content')->nullable();
            $table->text('footer_content')->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('signature_title_1', 100)->default('Prepared By');
            $table->string('signature_title_2', 100)->default('Principal');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'type']);
        });

        // 2. Issued Certificates
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('certificate_templates')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('certificate_number', 100);
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('rendered_content');
            $table->string('recipient_name', 150);
            $table->date('issue_date');
            $table->string('verification_code', 100)->unique();
            $table->string('file_path', 500)->nullable();
            $table->string('status', 30)->default('issued'); // issued, revoked
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'certificate_number']);
            $table->index(['student_id', 'status']);
            $table->index(['verification_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
    }
};

