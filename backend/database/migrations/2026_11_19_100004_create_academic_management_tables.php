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
        // 20. Education Boards (CBSE, ICSE, State Boards, IB, Cambridge)
        Schema::create('boards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50); // CBSE, ICSE, CAMBRIDGE, IB, STATE_MH
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code']);
        });

        // 21. Academic Sessions (e.g., 2026-2027)
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100); // 2026-2027
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false)->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['branch_id', 'name']);
            $table->index(['tenant_id', 'branch_id', 'is_current']);
        });

        // 22. Academic Calendars
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['branch_id', 'academic_session_id']);
        });

        // 23. Academic Events (Holidays, examinations, events)
        Schema::create('academic_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('calendar_id')->constrained('academic_calendars')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('event_type', 50)->default('event'); // holiday, exam, meeting, sports, celebration
            $table->timestampTz('start_date');
            $table->timestampTz('end_date');
            $table->boolean('is_holiday')->default(false);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'start_date', 'end_date']);
        });

        // 24. Class Levels (Primary, Middle, Secondary, Senior Secondary)
        Schema::create('class_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('order_index')->default(0);
            $table->timestampsTz();

            $table->unique(['school_id', 'name']);
        });

        // 25. Classes (Grade 1 to 12, Pre-KG, etc.)
        Schema::create('classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_level_id')->nullable()->constrained('class_levels')->nullOnDelete();
            $table->foreignUuid('board_id')->nullable()->constrained('boards')->nullOnDelete();
            $table->string('name', 100); // Grade 10
            $table->string('code', 50); // G10
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['branch_id', 'code']);
            $table->index(['tenant_id', 'branch_id']);
        });

        // 26. Sections (Section A, Section B)
        Schema::create('sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name', 50); // Section A
            $table->string('room_number', 50)->nullable();
            $table->integer('max_capacity')->default(40);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['class_id', 'name']);
            $table->index(['branch_id', 'class_id']);
        });

        // 27. Subjects
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 150); // Mathematics, English Literature
            $table->string('code', 50); // MATH-10
            $table->string('type', 30)->default('theory'); // theory, practical, elective, co_curricular
            $table->decimal('credit_hours', 4, 1)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['branch_id', 'code']);
            $table->index(['tenant_id', 'branch_id']);
        });

        // 28. Subject Groups (e.g. Science, Commerce, Arts)
        Schema::create('subject_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'name']);
        });

        // 29. Class Subjects (Joining Class, Subject, and Academic Session)
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->boolean('is_elective')->default(false);
            $table->integer('max_weekly_periods')->default(5);
            $table->timestampsTz();

            $table->unique(['class_id', 'subject_id', 'academic_session_id']);
            $table->index(['branch_id', 'class_id']);
        });

        // 30. Grading Schemes (CBSE 9-Point, Letter Grade, GPA 4.0)
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100); // CBSE 9-Point Scale
            $table->string('scheme_type', 50)->default('letter'); // letter, gpa, percentage
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->unique(['school_id', 'name']);
        });

        // 31. Grade Ranges (Specific intervals for grading scheme)
        Schema::create('grade_ranges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grading_scheme_id')->constrained('grading_schemes')->cascadeOnDelete();
            $table->string('grade_name', 20); // A1, A2, B1
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('max_percentage', 5, 2);
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->string('description', 100)->nullable();
            $table->timestampsTz();

            $table->index(['grading_scheme_id', 'min_percentage']);
        });

        // 32. Timetables (Master Schedule header)
        Schema::create('timetables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['branch_id', 'academic_session_id']);
        });

        // 33. Timetable Periods (Time slots definition)
        Schema::create('timetable_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->smallInteger('period_number');
            $table->string('period_name', 50); // Period 1, Lunch Break
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false);
            $table->timestampsTz();

            $table->unique(['timetable_id', 'period_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_periods');
        Schema::dropIfExists('timetables');
        Schema::dropIfExists('grade_ranges');
        Schema::dropIfExists('grading_schemes');
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subject_groups');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('class_levels');
        Schema::dropIfExists('academic_events');
        Schema::dropIfExists('academic_calendars');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('boards');
    }
};

