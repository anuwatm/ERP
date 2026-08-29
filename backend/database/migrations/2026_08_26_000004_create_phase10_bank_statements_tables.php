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
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->date('statement_date_from');
            $table->date('statement_date_to');
            $table->decimal('opening_balance', 18, 2)->nullable();
            $table->decimal('closing_balance', 18, 2)->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->string('status', 20)->default('open');
            $table->uuid('imported_by')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'bank_account_id', 'status']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->date('transaction_date');
            $table->decimal('amount_signed', 18, 2);
            $table->decimal('balance_after', 18, 2)->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('reference_no', 255)->nullable();
            $table->char('row_fingerprint', 64);
            $table->string('status', 20)->default('unreconciled');
            $table->timestamps();
            $table->unique(['bank_account_id', 'row_fingerprint']);
            $table->index(['org_id', 'bank_account_id', 'transaction_date'], 'bank_stmt_line_org_account_date_idx');
            $table->index(['bank_statement_id', 'status']);
        });

        $permissions = [
            ['code' => 'treasury.reconciliation.view', 'module' => 'treasury_reconciliation', 'action' => 'view', 'description' => 'View bank statements and reconciliation'],
            ['code' => 'treasury.reconciliation.manage', 'module' => 'treasury_reconciliation', 'action' => 'manage', 'description' => 'Import bank statements and manage reconciliation'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, [
                    'id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
        $permissionIds = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id');
        foreach (['owner', 'admin', 'finance'] as $roleCode) {
            DB::table('roles')->where('code', $roleCode)->get(['id'])->each(function ($role) use ($permissionIds): void {
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissionId]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
