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
        // 53. Attendance Sessions (Session tracking)
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('session_name', 100);
            $table->boolean('is_open')->default(true);
            $table->timestampsTz();
        });

        // 54. Student Attendance (Daily / Period-wise)
        Schema::create('student_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('subjects')->nullOnDelete(); // null = daily morning attendance
            $table->date('date');
            $table->string('status', 20)->default('present'); // present, absent, late, half_day, excused
            $table->text('remarks')->nullable();
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['enrollment_id', 'date', 'subject_id']);
            $table->index(['branch_id', 'date', 'section_id', 'status']);
        });

        // 55. Staff Attendance (Biometric & RFID integration)
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('status', 20)->default('present'); // present, absent, late, on_leave, half_day
            $table->string('shift_name', 50)->default('General');
            $table->string('remarks', 255)->nullable();
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['employee_id', 'date']);
            $table->index(['branch_id', 'date', 'status']);
        });

        // 56. Attendance Corrections (Audit & approval for disputes)
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('attendance_type', 20); // student, staff
            $table->uuid('attendance_record_id');
            $table->string('old_status', 20);
            $table->string('new_status', 20);
            $table->text('reason');
            $table->foreignUuid('requested_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending'); // pending, approved, rejected
            $table->timestampsTz();

            $table->index(['branch_id', 'status']);
        });

        // 57. Leave Applications (Students and Staff)
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('applicant_type', 20); // student, employee
            $table->uuid('applicant_id');
            $table->string('leave_type', 50); // casual, sick, medical, emergency, vacation
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1.0);
            $table->text('reason');
            $table->string('medical_certificate_path', 500)->nullable();
            $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'applicant_type', 'applicant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('staff_attendance');
        Schema::dropIfExists('student_attendance');
        Schema::dropIfExists('attendance_sessions');
    }
};

