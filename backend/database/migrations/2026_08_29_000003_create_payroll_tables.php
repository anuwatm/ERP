<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monthly_salary', 18, 2);
            $table->decimal('fixed_allowance', 18, 2)->default(0);
            $table->decimal('fixed_deduction', 18, 2)->default(0);
            $table->decimal('annual_tax_allowance', 18, 2)->default(60000);
            $table->string('tax_id', 20)->nullable();
            $table->boolean('social_security_enabled')->default(true);
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('payment_reference', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'user_id']);
        });

        Schema::create('payroll_tax_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('employment_expense_rate', 5, 2)->default(50);
            $table->decimal('employment_expense_cap', 18, 2)->default(100000);
            $table->json('brackets_json');
            $table->string('source_url', 1000)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'effective_from', 'effective_to'], 'payroll_tax_policy_effective_idx');
        });

        Schema::create('social_security_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('employee_rate', 5, 2);
            $table->decimal('employer_rate', 5, 2);
            $table->decimal('wage_ceiling', 18, 2);
            $table->string('source_url', 1000)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'effective_from', 'effective_to'], 'social_security_policy_effective_idx');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('run_no', 50);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->foreignUuid('payroll_tax_policy_id')->constrained('payroll_tax_policies')->restrictOnDelete();
            $table->foreignUuid('social_security_policy_id')->constrained('social_security_policies')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('currency', 3)->default('THB');
            $table->string('status', 20)->default('draft');
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('employee_social_security_amount', 18, 2)->default(0);
            $table->decimal('employer_social_security_amount', 18, 2)->default(0);
            $table->decimal('withholding_tax_amount', 18, 2)->default(0);
            $table->decimal('net_pay_amount', 18, 2)->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'run_no']);
            $table->index(['org_id', 'status', 'period_end']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('employee_payroll_profile_id')->constrained('employee_payroll_profiles')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('salary_amount', 18, 2);
            $table->decimal('allowance_amount', 18, 2)->default(0);
            $table->decimal('other_deduction_amount', 18, 2)->default(0);
            $table->decimal('employee_social_security_amount', 18, 2)->default(0);
            $table->decimal('employer_social_security_amount', 18, 2)->default(0);
            $table->decimal('withholding_tax_amount', 18, 2)->default(0);
            $table->decimal('net_pay_amount', 18, 2);
            $table->json('calculation_snapshot');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_payroll_profile_id']);
            $table->index(['org_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('social_security_policies');
        Schema::dropIfExists('payroll_tax_policies');
        Schema::dropIfExists('employee_payroll_profiles');
    }
};
