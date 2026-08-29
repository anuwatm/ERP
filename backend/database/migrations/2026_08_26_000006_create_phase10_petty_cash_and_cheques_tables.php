<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignUuid('custodian_user_id')->constrained('users')->restrictOnDelete();
            $table->string('fund_no', 30);
            $table->decimal('imprest_amount', 18, 2);
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'fund_no']);
            $table->index(['org_id', 'status']);
        });
        Schema::create('petty_cash_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('petty_cash_fund_id')->constrained('petty_cash_funds')->restrictOnDelete();
            $table->string('request_no', 30);
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('expense_date');
            $table->text('purpose');
            $table->string('status', 20)->default('draft');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'request_no']);
            $table->index(['org_id', 'petty_cash_fund_id', 'status']);
        });
        Schema::create('petty_cash_reimbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('petty_cash_fund_id')->constrained('petty_cash_funds')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('reimbursed_at');
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'petty_cash_fund_id', 'reimbursed_at'], 'petty_reimb_org_fund_date_idx');
        });
        Schema::create('cheques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignUuid('bank_statement_line_id')->nullable()->constrained('bank_statement_lines')->nullOnDelete();
            $table->string('direction', 10);
            $table->string('cheque_no', 100);
            $table->string('bank_name', 100);
            $table->string('drawer_or_payee', 255);
            $table->decimal('amount', 18, 2);
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 20)->default('registered');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'bank_name', 'direction', 'cheque_no']);
            $table->index(['org_id', 'status', 'due_date']);
        });
        $permissions = [['code' => 'treasury.reports.view', 'module' => 'treasury_reports', 'action' => 'view', 'description' => 'View treasury reports'], ['code' => 'petty_cash.view', 'module' => 'petty_cash', 'action' => 'view', 'description' => 'View petty cash'], ['code' => 'petty_cash.manage', 'module' => 'petty_cash', 'action' => 'manage', 'description' => 'Manage petty cash funds and requests'], ['code' => 'petty_cash.approve', 'module' => 'petty_cash', 'action' => 'approve', 'description' => 'Approve petty cash requests'], ['code' => 'cheques.view', 'module' => 'cheques', 'action' => 'view', 'description' => 'View cheques and PDC'], ['code' => 'cheques.manage', 'module' => 'cheques', 'action' => 'manage', 'description' => 'Manage cheques and PDC']];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], array_merge($permission, ['id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(), 'created_at' => now(), 'updated_at' => now()]));
        }
        $ids = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id');
        foreach (['owner', 'admin', 'finance'] as $roleCode) {
            DB::table('roles')->where('code', $roleCode)->get(['id'])->each(function ($role) use ($ids): void {
                foreach ($ids as $id) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $id]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('petty_cash_reimbursements');
        Schema::dropIfExists('petty_cash_requests');
        Schema::dropIfExists('petty_cash_funds');
    }
};
