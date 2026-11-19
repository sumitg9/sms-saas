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
        // 86. Announcements (Broadcast circulars)
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('school_branches')->cascadeOnDelete(); // null = whole tenant
            $table->string('title', 200);
            $table->text('content');
            $table->string('target_audience', 50)->default('all'); // all, teachers, parents, students, staff
            $table->string('priority', 20)->default('normal'); // low, normal, urgent
            $table->timestampTz('published_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(true)->index();
            $table->timestampsTz();

            $table->index(['tenant_id', 'target_audience', 'is_published']);
        });

        // 87. Announcement Acknowledgements (Read tracking)
        Schema::create('announcement_acknowledgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('acknowledged_at')->useCurrent();
            $table->timestampsTz();

            $table->unique(['announcement_id', 'user_id']);
        });

        // 88. Notification Templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('event_name', 100); // student_absent, fee_due, exam_published, report_card_ready
            $table->string('channel', 30); // email, sms, whatsapp, push
            $table->string('subject', 200)->nullable();
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'event_name', 'channel']);
        });

        // 89. Notifications (In-app notification store)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('channel', 30)->default('in_app');
            $table->timestampTz('read_at')->nullable()->index();
            $table->string('action_url', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'read_at']);
        });

        // 90. Notification Logs (Delivery metrics & audit for SMS/Email/WhatsApp)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 30); // sms, whatsapp, email
            $table->string('recipient', 150);
            $table->text('message_content');
            $table->string('status', 30)->default('sent'); // queued, sent, delivered, failed
            $table->text('error_message')->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->timestampTz('sent_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'sent_at']);
        });

        // 91. Message Conversations
        Schema::create('message_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->string('conversation_type', 30)->default('direct'); // direct, class_group
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestampsTz();
        });

        // 92. Messages
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('message_conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users');
            $table->text('content');
            $table->jsonb('attachment_paths')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampsTz();

            $table->index(['conversation_id', 'created_at']);
        });

        // 93. Support Tickets (Helpdesk & complaints)
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('ticket_number', 50);
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('category', 50); // academic, fees, transport, facility, tech
            $table->string('subject', 200);
            $table->string('priority', 20)->default('medium'); // low, medium, high, critical
            $table->string('status', 30)->default('open')->index(); // open, in_progress, resolved, closed
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['branch_id', 'ticket_number']);
            $table->index(['branch_id', 'status']);
        });

        // 94. Ticket Messages
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users');
            $table->text('message');
            $table->jsonb('attachment_paths')->nullable();
            $table->boolean('is_internal_note')->default(false);
            $table->timestampsTz();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_conversations');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('announcement_acknowledgements');
        Schema::dropIfExists('announcements');
    }
};

