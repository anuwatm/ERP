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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('bank_name', 100);
            $table->string('bank_code', 20)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('account_name', 255);
            $table->text('account_number');
            $table->char('account_number_hash', 64);
            $table->string('account_type', 20);
            $table->char('currency', 3)->default('THB');
            $table->boolean('is_cash_account')->default(false);
            $table->string('status', 20)->default('active');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'account_number_hash']);
            $table->index(['org_id', 'branch_id', 'status']);
        });

        $permissions = [
            ['code' => 'treasury.accounts.view', 'module' => 'treasury_accounts', 'action' => 'view', 'description' => 'View bank and cash accounts'],
            ['code' => 'treasury.accounts.manage', 'module' => 'treasury_accounts', 'action' => 'manage', 'description' => 'Manage bank and cash accounts'],
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
        Schema::dropIfExists('bank_accounts');
    }
};
