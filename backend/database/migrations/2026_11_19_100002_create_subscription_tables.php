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
        // 7. Subscription Plans (Tier definitions)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 12, 2)->default(0.00);
            $table->decimal('annual_price', 12, 2)->default(0.00);
            $table->string('currency', 3)->default('INR');
            $table->integer('max_students')->default(300); // -1 for unlimited
            $table->integer('max_staff')->default(50);
            $table->integer('max_branches')->default(1);
            $table->integer('storage_limit_gb')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        // 8. Plan Features (Granular feature flags & quotas)
        Schema::create('plan_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key', 100); // e.g., library_management, transport, payroll
            $table->string('feature_name', 150);
            $table->boolean('is_enabled')->default(true);
            $table->integer('quota_limit')->nullable(); // null = unlimited if enabled
            $table->timestampsTz();

            $table->unique(['plan_id', 'feature_key']);
        });

        // 9. Subscriptions (Tenant active plan subscriptions)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('subscription_plans');
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, annual
            $table->string('status', 30)->default('active')->index(); // trial, active, in_grace_period, past_due, cancelled
            $table->timestampTz('current_period_start');
            $table->timestampTz('current_period_end');
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('grace_period_ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('gateway_customer_id', 150)->nullable();
            $table->string('gateway_subscription_id', 150)->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
        });

        // 10. Subscription Invoices (Billing history)
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 30)->default('unpaid')->index(); // draft, unpaid, paid, overdue, void
            $table->date('due_date');
            $table->timestampTz('paid_at')->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
        });

        // 11. Subscription Payments (IMMUTABLE - NO soft deletes)
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();
            $table->string('transaction_reference', 150)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50); // card, upi, netbanking, bank_transfer
            $table->string('gateway', 50); // stripe, razorpay, offline
            $table->jsonb('gateway_response')->nullable();
            $table->string('status', 30)->default('success'); // success, failed, refunded
            $table->timestampTz('paid_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'paid_at']);
        });

        // 12. Usage Records (Metrics tracking for storage, student capacity, SMS)
        Schema::create('usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('metric_name', 100); // storage_bytes, students_count, staff_count, sms_count
            $table->bigInteger('current_value')->default(0);
            $table->timestampTz('recorded_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['tenant_id', 'metric_name']);
        });

        // 13. Coupons (Promotions & discount codes)
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('discount_type', 20)->default('percentage'); // percentage, fixed
            $table->decimal('discount_value', 12, 2);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to');
            $table->integer('max_redemptions')->default(100);
            $table->integer('current_redemptions')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('subscription_plans');
    }
};

