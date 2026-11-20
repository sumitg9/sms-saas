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
        // 1. Enhance announcements
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->jsonb('attachments')->nullable();
            $table->boolean('requires_acknowledgement')->default(false);
        });

        // 2. Enhance notification_templates
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('language', 10)->default('en')->after('channel');
            $table->jsonb('placeholders')->nullable()->after('body_template');
        });

        // 3. Enhance notification_logs
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->index()->after('recipient');
            $table->integer('retry_count')->default(0)->after('status');
            $table->integer('max_retries')->default(3)->after('retry_count');
            $table->timestampTz('scheduled_at')->nullable()->after('sent_at');
        });

        // 4. Create communication_preferences
        Schema::create('communication_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('emergency_override')->default(true);
            $table->string('dnd_start_time', 10)->nullable(); // e.g. "21:00"
            $table->string('dnd_end_time', 10)->nullable();   // e.g. "07:00"
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id']);
        });

        // 5. Create ptm_meetings (Parent-Teacher Meetings)
        Schema::create('ptm_meetings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('meeting_type', 30)->default('in_person'); // in_person, online
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room_number', 50)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->string('status', 30)->default('scheduled'); // requested, scheduled, confirmed, completed, cancelled
            $table->string('requested_by', 20)->default('teacher'); // teacher, parent
            $table->text('parent_notes')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->text('feedback')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'branch_id', 'meeting_date']);
            $table->index(['teacher_id', 'meeting_date']);
            $table->index(['guardian_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ptm_meetings');
        Schema::dropIfExists('communication_preferences');

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'retry_count', 'max_retries', 'scheduled_at']);
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn(['language', 'placeholders']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['section_id']);
            $table->dropColumn(['class_id', 'section_id', 'attachments', 'requires_acknowledgement']);
        });
    }
};

