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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->string('account_type', 20);
            $table->string('normal_balance', 10);
            $table->foreignUuid('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_postable')->default(true);
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'code']);
            $table->index(['org_id', 'account_type', 'status']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'start_date', 'end_date']);
            $table->index(['org_id', 'status', 'start_date', 'end_date']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->string('entry_no', 30);
            $table->date('posting_date');
            $table->string('description', 500);
            $table->string('status', 20)->default('posted');
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('posting_event', 100)->nullable();
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'entry_no']);
            $table->unique(['org_id', 'source_type', 'source_id', 'posting_event'], 'journal_entries_source_event_unique');
            $table->index(['org_id', 'posting_date', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['org_id', 'chart_of_account_id']);
            $table->index(['journal_entry_id', 'sort_order']);
        });

        $permissions = [
            ['code' => 'accounting.chart_accounts.view', 'module' => 'accounting_chart_accounts', 'action' => 'view', 'description' => 'View chart of accounts'],
            ['code' => 'accounting.chart_accounts.manage', 'module' => 'accounting_chart_accounts', 'action' => 'manage', 'description' => 'Manage chart of accounts'],
            ['code' => 'accounting.periods.view', 'module' => 'accounting_periods', 'action' => 'view', 'description' => 'View accounting periods'],
            ['code' => 'accounting.periods.manage', 'module' => 'accounting_periods', 'action' => 'manage', 'description' => 'Manage accounting periods'],
            ['code' => 'accounting.journals.view', 'module' => 'accounting_journals', 'action' => 'view', 'description' => 'View journals'],
            ['code' => 'accounting.journals.create', 'module' => 'accounting_journals', 'action' => 'create', 'description' => 'Create draft journals'],
            ['code' => 'accounting.journals.post', 'module' => 'accounting_journals', 'action' => 'post', 'description' => 'Post journals'],
            ['code' => 'accounting.journals.reverse', 'module' => 'accounting_journals', 'action' => 'reverse', 'description' => 'Reverse posted journals'],
            ['code' => 'accounting.reports.view', 'module' => 'accounting_reports', 'action' => 'view', 'description' => 'View general ledger reports'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], array_merge($permission, [
                'id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
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
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('chart_of_accounts');
    }
};
