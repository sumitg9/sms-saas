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
        // 74. Examination Types (Unit Test, Half-Yearly, Final Exam, Practical)
        Schema::create('examination_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('weightage_percent', 5, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['school_id', 'name']);
        });

        // 75. Examinations (Master assessment session)
        Schema::create('examinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('exam_type_id')->constrained('examination_types');
            $table->foreignUuid('grading_scheme_id')->constrained('grading_schemes');
            $table->string('title', 150); // e.g. "Term 1 Examination 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_published')->default(false)->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'academic_session_id']);
        });

        // 76. Exam Schedules (Subject-wise date & timing per class)
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('examination_id')->constrained('examinations')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room_number', 50)->nullable();
            $table->decimal('max_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->timestampsTz();

            $table->unique(['examination_id', 'class_id', 'subject_id']);
            $table->index(['examination_id', 'exam_date']);
        });

        // 77. Exam Subjects (Theory vs practical pass marks)
        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('examination_id')->constrained('examinations')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->decimal('max_theory_marks', 6, 2)->default(80.00);
            $table->decimal('max_practical_marks', 6, 2)->default(20.00);
            $table->decimal('pass_percentage', 5, 2)->default(33.00);
            $table->timestampsTz();

            $table->unique(['examination_id', 'subject_id']);
        });

        // 78. Assessment Components (Sub-division: Theory, Viva, Project, Practical)
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('exam_schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->string('component_name', 100);
            $table->decimal('max_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->timestampsTz();

            $table->unique(['exam_schedule_id', 'component_name']);
        });

        // 79. Student Marks
        Schema::create('student_marks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('exam_schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('remarks', 255)->nullable();
            $table->foreignUuid('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['exam_schedule_id', 'enrollment_id']);
            $table->index(['branch_id', 'enrollment_id']);
        });

        // 80. Student Assessments (Component marks breakdown)
        Schema::create('student_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_marks_id')->constrained('student_marks')->cascadeOnDelete();
            $table->foreignUuid('component_id')->constrained('assessment_components')->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2);
            $table->timestampsTz();

            $table->unique(['student_marks_id', 'component_id']);
        });

        // 81. Exam Results (Consolidated marksheet & rankings)
        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('examination_id')->constrained('examinations')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->decimal('total_marks', 7, 2);
            $table->decimal('marks_obtained', 7, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade', 10)->nullable();
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->integer('rank')->nullable();
            $table->string('result_status', 30)->default('passed'); // passed, failed, detained, withheld
            $table->timestampsTz();

            $table->unique(['examination_id', 'enrollment_id']);
            $table->index(['examination_id', 'result_status']);
        });

        // 82. Report Card Templates
        Schema::create('report_card_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->jsonb('template_schema')->nullable();
            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['school_id', 'name']);
        });

        // 83. Report Cards
        Schema::create('report_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('examination_id')->constrained('examinations')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('report_card_templates');
            $table->string('generated_pdf_path', 500)->nullable();
            $table->boolean('is_signed')->default(false);
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->unique(['examination_id', 'enrollment_id']);
        });

        // 84. Result Publications (Controlling portal visibility)
        Schema::create('result_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('examination_id')->constrained('examinations')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->useCurrent();
            $table->boolean('allow_parent_view')->default(true);
            $table->boolean('allow_student_view')->default(true);
            $table->timestampsTz();

            $table->unique(['examination_id', 'class_id']);
        });

        // 85. Result Corrections (Dispute & modification audit)
        Schema::create('result_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_marks_id')->constrained('student_marks')->cascadeOnDelete();
            $table->decimal('old_marks', 6, 2)->nullable();
            $table->decimal('new_marks', 6, 2);
            $table->text('reason');
            $table->foreignUuid('requested_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending'); // pending, approved, rejected
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_corrections');
        Schema::dropIfExists('result_publications');
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('report_card_templates');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('student_assessments');
        Schema::dropIfExists('student_marks');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('examinations');
        Schema::dropIfExists('examination_types');
    }
};

