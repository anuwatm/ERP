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
        Schema::create('forecast_scenarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('scenario_key', 50); // base, best, worst
            $table->boolean('is_default')->default(false);
            $table->boolean('deal_probability_weighting')->default(true);
            $table->decimal('deal_multiplier', 5, 2)->default(1.00);
            $table->decimal('ar_collection_rate', 5, 2)->default(1.00);
            $table->integer('ar_delay_days')->default(0);
            $table->decimal('ap_payment_rate', 5, 2)->default(1.00);
            $table->decimal('expense_buffer_percent', 5, 2)->default(0.00);
            $table->decimal('payroll_buffer_percent', 5, 2)->default(0.00);
            $table->json('assumptions_json')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['org_id', 'scenario_key']);
            $table->index(['org_id', 'is_default']);
        });

        Schema::create('cash_flow_forecast_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('forecast_scenario_id')->nullable()->constrained('forecast_scenarios')->nullOnDelete();
            $table->string('scenario_key', 50)->default('base');
            $table->date('as_of_date');
            $table->integer('horizon_days')->default(90); // 30, 60, 90
            $table->decimal('starting_cash', 15, 2)->default(0.00);
            $table->decimal('projected_inflow', 15, 2)->default(0.00);
            $table->decimal('projected_outflow', 15, 2)->default(0.00);
            $table->decimal('net_cash_flow', 15, 2)->default(0.00);
            $table->decimal('projected_ending_cash', 15, 2)->default(0.00);
            $table->decimal('lowest_cash_point', 15, 2)->default(0.00);
            $table->date('lowest_cash_date')->nullable();
            $table->json('breakdown_json')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['org_id', 'as_of_date']);
            $table->index(['org_id', 'scenario_key']);
        });

        Schema::create('financial_metric_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('as_of_date');
            $table->decimal('current_ratio', 8, 2)->default(0.00);
            $table->decimal('quick_ratio', 8, 2)->default(0.00);
            $table->decimal('dso_days', 8, 1)->default(0.0);
            $table->decimal('dpo_days', 8, 1)->default(0.0);
            $table->decimal('working_capital', 15, 2)->default(0.00);
            $table->decimal('working_capital_runway_months', 8, 1)->default(0.0);
            $table->decimal('monthly_burn_rate', 15, 2)->default(0.00);
            $table->decimal('current_cash', 15, 2)->default(0.00);
            $table->decimal('current_ar', 15, 2)->default(0.00);
            $table->decimal('current_ap', 15, 2)->default(0.00);
            $table->decimal('inventory_value', 15, 2)->default(0.00);
            $table->json('metadata_json')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['org_id', 'as_of_date']);
        });

        // Seed permissions
        $permissions = [
            ['code' => 'cash_flow.view', 'module' => 'cash_flow', 'action' => 'view', 'description' => 'View cash flow forecasts and financial health analytics'],
            ['code' => 'cash_flow.manage', 'module' => 'cash_flow', 'action' => 'manage', 'description' => 'Manage forecast scenarios and generate snapshots'],
        ];

        foreach ($permissions as $perm) {
            $exists = DB::table('permissions')->where('code', $perm['code'])->first();
            $permId = $exists?->id;
            if (! $exists) {
                $permId = (string) Str::orderedUuid();
                DB::table('permissions')->insert([
                    'id' => $permId,
                    'code' => $perm['code'],
                    'module' => $perm['module'],
                    'action' => $perm['action'],
                    'description' => $perm['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Attach to owner, admin, director, and finance roles
            $roleIds = DB::table('roles')
                ->whereIn('code', ['owner', 'admin', 'director', 'finance'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                $alreadyAttached = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists();

                if (! $alreadyAttached) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_metric_logs');
        Schema::dropIfExists('cash_flow_forecast_snapshots');
        Schema::dropIfExists('forecast_scenarios');

        $permIds = DB::table('permissions')->whereIn('code', ['cash_flow.view', 'cash_flow.manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('id', $permIds)->delete();
    }
};
