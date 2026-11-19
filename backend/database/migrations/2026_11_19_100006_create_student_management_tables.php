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
        // 44. Students (Master demographic identity)
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admission_number', 50);
            $table->date('admission_date');
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('gender', 20); // male, female, other
            $table->date('date_of_birth');
            $table->string('blood_group', 10)->nullable();
            $table->string('nationality', 50)->default('Indian');
            $table->string('religion', 50)->nullable();
            $table->string('caste_category', 50)->default('General'); // General, OBC, SC, ST, EWS
            $table->string('national_id', 50)->nullable(); // Aadhaar / Passport
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('status', 30)->default('active')->index(); // active, alumni, transferred, expelled, inactive
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['branch_id', 'admission_number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        // 45. Student Admissions (Inquiry & Enrollment CRM pipeline)
        Schema::create('student_admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('application_number', 50);
            $table->string('applicant_first_name', 100);
            $table->string('applicant_last_name', 100);
            $table->foreignUuid('applied_class_id')->constrained('classes');
            $table->date('date_of_application');
            $table->string('status', 30)->default('applied')->index(); // applied, test_scheduled, interview, offered, enrolled, rejected
            $table->boolean('application_fee_paid')->default(false);
            $table->jsonb('inquiry_details')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'application_number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        // 46. Student Enrollments (HISTORICAL RECORDS PRESERVED)
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('roll_number', 30)->nullable();
            $table->date('enrollment_date')->useCurrent();
            $table->string('status', 30)->default('enrolled')->index(); // enrolled, promoted, detained, transferred, graduated
            $table->timestampsTz();

            $table->unique(['student_id', 'academic_session_id']);
            $table->index(['branch_id', 'class_id', 'section_id', 'academic_session_id']);
        });

        // 47. Guardians / Parents
        Schema::create('guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('relation_type', 50); // father, mother, legal_guardian
            $table->string('phone', 50);
            $table->string('email', 255)->nullable();
            $table->string('occupation', 150)->nullable();
            $table->decimal('annual_income', 14, 2)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->text('address')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'phone']);
        });

        // 48. Student Guardians (Many-to-Many with rights)
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('has_pickup_rights')->default(true);
            $table->boolean('receives_invoices')->default(true);
            $table->smallInteger('emergency_contact_priority')->default(1);
            $table->timestampsTz();

            $table->unique(['student_id', 'guardian_id']);
        });

        // 49. Student Documents
        Schema::create('student_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('document_type', 50); // birth_certificate, transfer_certificate, aadhaar, medical, photo
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->default('application/pdf');
            $table->string('verification_status', 30)->default('pending'); // pending, verified, rejected
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['student_id', 'document_type']);
        });

        // 50. Student Medical Records
        Schema::create('student_medical_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('blood_group', 10)->nullable();
            $table->jsonb('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('medications')->nullable();
            $table->text('emergency_notes')->nullable();
            $table->string('insurance_policy_number', 100)->nullable();
            $table->string('doctor_name', 150)->nullable();
            $table->string('doctor_phone', 50)->nullable();
            $table->timestampsTz();

            $table->unique(['student_id']);
        });

        // 51. Student Promotions (Audit trail of academic progression)
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('from_session_id')->constrained('academic_sessions');
            $table->foreignUuid('to_session_id')->constrained('academic_sessions');
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('from_class_id')->constrained('classes');
            $table->foreignUuid('to_class_id')->constrained('classes');
            $table->string('promotion_status', 30)->default('promoted'); // promoted, detained, double_promoted
            $table->text('remarks')->nullable();
            $table->foreignUuid('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['student_id', 'from_session_id', 'to_session_id']);
        });

        // 52. Student Transfers (TC issuance)
        Schema::create('student_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('tc_number', 50);
            $table->date('application_date');
            $table->date('issue_date')->nullable();
            $table->string('destination_school', 255)->nullable();
            $table->text('reason_for_leaving')->nullable();
            $table->jsonb('clearance_status')->nullable(); // library, fees, lab clearances
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['branch_id', 'tc_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_transfers');
        Schema::dropIfExists('student_promotions');
        Schema::dropIfExists('student_medical_records');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('student_admissions');
        Schema::dropIfExists('students');
    }
};

