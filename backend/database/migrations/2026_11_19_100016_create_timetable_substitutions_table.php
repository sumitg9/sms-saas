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
        Schema::create('timetable_substitutions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('school_branches')->cascadeOnDelete();
            $table->foreignUuid('timetable_entry_id')->constrained('timetable_entries')->cascadeOnDelete();
            $table->foreignUuid('original_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('substitute_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->string('status', 30)->default('approved'); // pending, approved, rejected, cancelled
            $table->timestampsTz();

            $table->unique(['timetable_entry_id', 'date']);
            $table->index(['branch_id', 'date', 'status']);
            $table->index(['substitute_employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_substitutions');
    }
};

