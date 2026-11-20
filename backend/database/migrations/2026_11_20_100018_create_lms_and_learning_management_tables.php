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
        // 1. Homework & Assignments Master
        Schema::create('homework', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->date('assigned_date');
            $table->date('due_date');
            $table->decimal('max_marks', 5, 2)->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'branch_id', 'class_id', 'due_date']);
            $table->index(['tenant_id', 'subject_id']);
        });

        // 2. Homework Submissions (Student online submissions, late tracking, grading & resubmission)
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('submission_text')->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->timestampTz('submitted_at')->useCurrent();
            $table->boolean('is_late')->default(false);
            $table->string('status', 30)->default('submitted')->index(); // submitted, graded, resubmission_requested
            $table->decimal('marks_obtained', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('graded_at')->nullable();
            $table->integer('resubmission_count')->default(0);
            $table->timestampsTz();

            $table->unique(['homework_id', 'student_id']);
            $table->index(['tenant_id', 'student_id', 'status']);
        });

        // 3. Learning Resources & Study Materials (Notes, Documents, Video Links)
        Schema::create('learning_resources', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('resource_type', 50)->default('document'); // document, notes, video, link
            $table->string('file_url', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->boolean('is_published')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'class_id', 'subject_id', 'is_published']);
        });

        // 4. Online Quizzes
        Schema::create('quizzes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->decimal('total_marks', 5, 2)->default(0.00);
            $table->decimal('pass_marks', 5, 2)->default(0.00);
            $table->timestampTz('start_time')->nullable();
            $table->timestampTz('end_time')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestampsTz();

            $table->index(['tenant_id', 'class_id', 'subject_id', 'is_published']);
        });

        // 5. Quiz Questions (MCQ, True/False, Short Answer)
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type', 30); // multiple_choice, true_false, short_answer
            $table->decimal('marks', 5, 2)->default(1.00);
            $table->jsonb('options')->nullable(); // [{"key": "A", "text": "Option A"}, ...]
            $table->text('correct_answer'); // key e.g. "A", or "true", or text
            $table->text('explanation')->nullable();
            $table->integer('order_number')->default(0);
            $table->timestampsTz();

            $table->index(['quiz_id', 'order_number']);
        });

        // 6. Quiz Attempts (Student attempts, automated grading & report)
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();
            $table->decimal('score', 5, 2)->default(0.00);
            $table->decimal('total_marks', 5, 2)->default(0.00);
            $table->decimal('percentage', 5, 2)->default(0.00);
            $table->boolean('is_passed')->default(false);
            $table->string('status', 30)->default('in_progress')->index(); // in_progress, completed, abandoned
            $table->jsonb('answers')->default('[]'); // [{"question_id": "...", "given_answer": "...", "is_correct": true, "marks_awarded": 1.0}]
            $table->timestampsTz();

            $table->index(['tenant_id', 'student_id', 'quiz_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('learning_resources');
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework');
    }
};

