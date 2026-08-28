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
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->foreignUuid('asset_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignUuid('accumulated_depreciation_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignUuid('depreciation_expense_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedInteger('default_useful_life_months');
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'code']);
            $table->index(['org_id', 'status']);
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('asset_no', 30);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('capitalization_source_type', 100);
            $table->uuid('capitalization_source_id');
            $table->date('acquisition_date');
            $table->date('available_for_use_date');
            $table->date('depreciation_start_date');
            $table->decimal('cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('net_book_value', 18, 2);
            $table->date('last_depreciated_for')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('location', 255)->nullable();
            $table->foreignUuid('custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('attachment_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->date('disposed_at')->nullable();
            $table->decimal('disposal_proceeds', 18, 2)->nullable();
            $table->text('disposal_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'asset_no']);
            $table->unique(['org_id', 'capitalization_source_type', 'capitalization_source_id'], 'fixed_assets_capitalization_source_unique');
            $table->index(['org_id', 'status', 'depreciation_start_date']);
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('depreciation_month');
            $table->decimal('amount', 18, 2);
            $table->decimal('accumulated_depreciation_after', 18, 2);
            $table->decimal('net_book_value_after', 18, 2);
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'depreciation_month']);
            $table->index(['org_id', 'depreciation_month']);
        });

        $permissions = [
            ['code' => 'fixed_assets.view', 'module' => 'fixed_assets', 'action' => 'view', 'description' => 'View fixed assets'],
            ['code' => 'fixed_assets.manage', 'module' => 'fixed_assets', 'action' => 'manage', 'description' => 'Manage fixed assets and categories'],
            ['code' => 'fixed_assets.depreciate', 'module' => 'fixed_assets', 'action' => 'depreciate', 'description' => 'Run fixed asset depreciation'],
            ['code' => 'fixed_assets.dispose', 'module' => 'fixed_assets', 'action' => 'dispose', 'description' => 'Dispose or write off fixed assets'],
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
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
    }
};
