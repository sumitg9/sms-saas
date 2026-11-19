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
        // 1. Leave Types Master
        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->decimal('annual_quota', 4, 1)->default(12.0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_carry_forward')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 2. Employee Resignations & Terminations (Exits)
        Schema::create('employee_exits', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('exit_type', 30)->default('resignation'); // resignation, termination, retirement, contract_end
            $table->date('notice_date');
            $table->date('last_working_day');
            $table->text('reason');
            $table->string('clearance_status', 30)->default('pending'); // pending, in_progress, completed
            $table->string('settlement_status', 30)->default('pending'); // pending, cleared
            $table->jsonb('clearance_checklist')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id']);
            $table->index(['branch_id', 'exit_type', 'clearance_status']);
        });

        // 3. Salary Components (Allowances & Deductions - Configurable)
        Schema::create('salary_components', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->string('type', 20); // allowance, deduction
            $table->string('calculation_type', 20)->default('fixed'); // fixed, percentage
            $table->string('percentage_of', 50)->nullable(); // BASIC, GROSS
            $table->decimal('default_amount', 12, 2)->default(0.00);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 4. Salary Structures (Bands / Templates)
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
        });

        // 5. Salary Structure Items (Component Mappings)
        Schema::create('salary_structure_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('salary_structure_id')->constrained('salary_structures')->cascadeOnDelete();
            $table->foreignUuid('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->timestampsTz();

            $table->unique(['salary_structure_id', 'salary_component_id']);
        });

        // 6. Employee Salary Profiles
        Schema::create('employee_salary_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete();
            $table->decimal('base_salary', 12, 2)->default(0.00);
            $table->string('payment_method', 30)->default('bank_transfer'); // bank_transfer, cash, cheque
            $table->jsonb('custom_components')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['employee_id']);
        });

        // 7. Payroll Periods
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date')->nullable();
            $table->string('status', 30)->default('draft'); // draft, processing, approved, paid, locked
            $table->decimal('total_gross', 14, 2)->default(0.00);
            $table->decimal('total_deductions', 14, 2)->default(0.00);
            $table->decimal('total_net', 14, 2)->default(0.00);
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['branch_id', 'start_date', 'end_date']);
        });

        // 8. Payslips (Generated Payroll Line Items)
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('payslip_number', 50);
            $table->decimal('basic_salary', 12, 2)->default(0.00);
            $table->decimal('total_allowances', 12, 2)->default(0.00);
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('gross_salary', 12, 2)->default(0.00);
            $table->decimal('net_salary', 12, 2)->default(0.00);
            $table->integer('working_days')->default(30);
            $table->decimal('present_days', 4, 1)->default(30.0);
            $table->decimal('paid_leaves', 4, 1)->default(0.0);
            $table->decimal('unpaid_leaves', 4, 1)->default(0.0);
            $table->jsonb('earnings_breakdown')->default('[]');
            $table->jsonb('deductions_breakdown')->default('[]');
            $table->string('status', 30)->default('draft'); // draft, published, paid
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('payment_reference', 100)->nullable();
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'payslip_number']);
            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_salary_profiles');
        Schema::dropIfExists('salary_structure_items');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('employee_exits');
        Schema::dropIfExists('leave_types');
    }
};

