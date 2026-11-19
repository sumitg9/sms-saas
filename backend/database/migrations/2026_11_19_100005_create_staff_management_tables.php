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
        // 34. Employee Departments (Science Dept, Mathematics Dept, Admin, Transport)
        Schema::create('employee_departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 35. Employee Designations (Principal, TGT Teacher, PGT Teacher, Accountant, Driver)
        Schema::create('employee_designations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('employee_departments')->nullOnDelete();
            $table->string('title', 100);
            $table->string('code', 50);
            $table->integer('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 36. Employees (Unified staff records: Teachers, Admin, Drivers, Facilities)
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 50);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('gender', 20); // male, female, other
            $table->date('date_of_birth');
            $table->string('blood_group', 10)->nullable();
            $table->string('email', 255);
            $table->string('phone', 50);
            $table->foreignUuid('department_id')->constrained('employee_departments');
            $table->foreignUuid('designation_id')->constrained('employee_designations');
            $table->string('employment_type', 50)->default('permanent'); // permanent, contract, temporary, part_time
            $table->date('date_of_joining');
            $table->date('date_of_leaving')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0.00);
            $table->string('pan_number', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_ifsc_code', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('status', 30)->default('active')->index(); // active, on_leave, resigned, terminated
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['branch_id', 'employee_code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        // 37. Employee Documents
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('document_type', 50); // contract, degree, police_verification, pan_card, resume
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('verification_status', 30)->default('verified');
            $table->timestampsTz();

            $table->index(['employee_id', 'document_type']);
        });

        // 38. Employee Contracts
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_type', 50)->default('permanent');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('gross_salary', 12, 2);
            $table->jsonb('terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['employee_id', 'is_active']);
        });

        // 39. Employee Leaves (Annual Quotas)
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('leave_type', 50); // casual, sick, earned, maternity
            $table->decimal('total_days', 4, 1)->default(12.0);
            $table->decimal('used_days', 4, 1)->default(0.0);
            $table->decimal('balance_days', 4, 1)->default(12.0);
            $table->timestampsTz();

            $table->unique(['employee_id', 'academic_session_id', 'leave_type']);
        });

        // 40. Employee Attendance
        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('status', 20)->default('present'); // present, absent, late, half_day, on_leave
            $table->string('source', 30)->default('manual'); // manual, biometric, rfid
            $table->string('remarks', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id', 'date']);
            $table->index(['branch_id', 'date', 'status']);
        });

        // 41. Teacher Subjects (Mapping teachers to class subjects)
        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('class_subject_id')->constrained('class_subjects')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestampsTz();

            $table->unique(['employee_id', 'class_subject_id']);
        });

        // 42. Class Teachers (Assigning primary/substitute in-charge to class sections)
        Schema::create('class_teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestampsTz();

            $table->unique(['section_id', 'academic_session_id']);
            $table->index(['branch_id', 'academic_session_id']);
        });

        // 43. Timetable Entries (Scheduled class period slots)
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('timetable_periods')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->smallInteger('day_of_week'); // 1 = Monday, 7 = Sunday
            $table->string('room_number', 50)->nullable();
            $table->timestampsTz();

            $table->unique(['period_id', 'section_id', 'day_of_week']);
            $table->index(['employee_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('class_teachers');
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('employee_attendance');
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employee_designations');
        Schema::dropIfExists('employee_departments');
    }
};

