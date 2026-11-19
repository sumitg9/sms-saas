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
        // 58. Financial Accounts (Bank accounts & Cash ledgers)
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('account_name', 150);
            $table->string('account_number', 50);
            $table->string('bank_name', 150);
            $table->string('ifsc_code', 50)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->decimal('current_balance', 14, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'account_number']);
        });

        // 59. Expense Categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 60. Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('expense_category_id')->constrained('expense_categories');
            $table->foreignUuid('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('voucher_number', 50);
            $table->string('title', 200);
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('payment_method', 50)->default('cash');
            $table->string('vendor_name', 150)->nullable();
            $table->string('bill_path', 500)->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'voucher_number']);
            $table->index(['branch_id', 'expense_date']);
        });

        // 61. Fee Categories (Academic, Transport, Hostel, Admission)
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'name']);
        });

        // 62. Fee Structures (Class-wise fee templates)
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('fee_category_id')->constrained('fee_categories');
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->cascadeOnDelete(); // null = applicable to all classes
            $table->string('name', 150); // e.g. "Grade 10 - Annual Fee 2026-27"
            $table->string('frequency', 30)->default('annual'); // annual, term, monthly, one_time
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->date('due_date');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['branch_id', 'academic_session_id']);
        });

        // 63. Fee Structure Items (Breakdown: Tuition, Lab, Sports, Library)
        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->string('fee_head_name', 100); // Tuition Fee, Computer Fee, Examination Fee
            $table->decimal('amount', 12, 2);
            $table->boolean('is_mandatory')->default(true);
            $table->timestampsTz();

            $table->index(['fee_structure_id']);
        });

        // 64. Fee Concessions (Scholarships, Sibling discounts, Staff children)
        Schema::create('fee_concessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('discount_type', 20)->default('percentage'); // percentage, fixed
            $table->decimal('discount_value', 12, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'name']);
        });

        // 65. Student Fee Assignments (Personalized ledger assignment)
        Schema::create('student_fee_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignUuid('fee_structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->foreignUuid('concession_id')->nullable()->constrained('fee_concessions')->nullOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('net_amount', 12, 2);
            $table->string('status', 30)->default('active'); // active, cancelled
            $table->timestampsTz();

            $table->unique(['enrollment_id', 'fee_structure_id']);
            $table->index(['student_id', 'status']);
        });

        // 66. Fee Invoices (Separated entity from fee payments)
        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignUuid('fee_assignment_id')->nullable()->constrained('student_fee_assignments')->nullOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('invoice_number', 50);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('fine_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('balance_amount', 12, 2);
            $table->date('due_date');
            $table->string('status', 30)->default('unpaid')->index(); // unpaid, partially_paid, paid, overdue, cancelled
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['branch_id', 'student_id', 'status']);
        });

        // 67. Fee Invoice Items
        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->string('fee_head_name', 100);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->timestampsTz();

            $table->index(['fee_invoice_id']);
        });

        // 68. Fee Payments (IMMUTABLE - NO soft delete)
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('receipt_number', 50);
            $table->decimal('amount_paid', 12, 2);
            $table->timestampTz('payment_date');
            $table->string('payment_method', 50); // cash, cheque, upi, card, bank_transfer, online
            $table->string('reference_number', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->foreignUuid('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('successful')->index(); // successful, cleared, bounced, refunded
            $table->string('receipt_pdf_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['branch_id', 'student_id', 'payment_date']);
        });

        // 69. Fee Payment Allocations (IMMUTABLE - Line item allocation mapping)
        Schema::create('fee_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_payment_id')->constrained('fee_payments')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_item_id')->nullable()->constrained('fee_invoice_items')->nullOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->timestampsTz();

            $table->index(['fee_payment_id', 'fee_invoice_id']);
        });

        // 70. Fee Refunds
        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_payment_id')->constrained('fee_payments')->cascadeOnDelete();
            $table->string('refund_number', 50);
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending'); // pending, processed
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['fee_payment_id', 'refund_number']);
        });

        // 71. Fee Fines (Late fees)
        Schema::create('fee_fines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->decimal('fine_amount', 12, 2);
            $table->string('reason', 150)->default('Late payment penalty');
            $table->date('calculation_date');
            $table->boolean('is_waived')->default(false);
            $table->foreignUuid('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestampsTz();

            $table->index(['fee_invoice_id', 'is_waived']);
        });

        // 72. Payment Transactions (IMMUTABLE - Online Gateway integration logs)
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('gateway', 50); // razorpay, stripe
            $table->string('gateway_order_id', 150)->index();
            $table->string('gateway_payment_id', 150)->nullable()->index();
            $table->string('gateway_signature', 255)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 30)->default('initiated'); // initiated, captured, failed
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'created_at']);
        });

        // 73. Payment Webhook Events (Idempotent processing queue)
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('gateway', 50);
            $table->string('event_type', 100);
            $table->string('event_id', 150)->unique();
            $table->jsonb('payload');
            $table->boolean('is_processed')->default(false)->index();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('fee_fines');
        Schema::dropIfExists('fee_refunds');
        Schema::dropIfExists('fee_payment_allocations');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoice_items');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('student_fee_assignments');
        Schema::dropIfExists('fee_concessions');
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_categories');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('financial_accounts');
    }
};

