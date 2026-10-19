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
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('bom_no', 30);
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('version', 20)->default('1.0');
            $table->decimal('quantity', 18, 4)->default(1.0000);
            $table->string('unit', 30)->default('pcs');
            $table->text('routing_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('overhead_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['org_id', 'bom_no']);
            $table->index(['org_id', 'product_id', 'is_active']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('bill_of_materials_id')->constrained('bill_of_materials')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('sub_bom_id')->nullable()->constrained('bill_of_materials')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 30);
            $table->decimal('waste_percent', 6, 2)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->string('note', 500)->nullable();
            $table->integer('sequence')->default(0);
            $table->timestamps();

            $table->index(['org_id', 'bill_of_materials_id']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('wo_no', 30);
            $table->foreignUuid('bill_of_materials_id')->constrained('bill_of_materials')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->default(0);
            $table->decimal('scrap_quantity', 18, 4)->default(0);
            $table->string('status', 30)->default('draft');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('overhead_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('qc_notes')->nullable();
            $table->boolean('qc_passed')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['org_id', 'wo_no']);
            $table->index(['org_id', 'status', 'due_date']);
        });

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('issued_quantity', 18, 4)->default(0);
            $table->string('unit', 30);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['org_id', 'work_order_id']);
        });

        Schema::create('production_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('cost_type', 30);
            $table->string('description', 255);
            $table->decimal('amount', 18, 2);
            $table->date('cost_date');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['org_id', 'work_order_id']);
        });

        if (Schema::hasTable('stock_movements') && ! Schema::hasColumn('stock_movements', 'work_order_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreignUuid('work_order_id')->nullable()->after('stock_count_id')->constrained('work_orders')->nullOnDelete();
            });
        }

        $permissions = [
            ['code' => 'manufacturing.bom.view', 'module' => 'manufacturing', 'action' => 'bom_view', 'description' => 'View bill of materials'],
            ['code' => 'manufacturing.bom.manage', 'module' => 'manufacturing', 'action' => 'bom_manage', 'description' => 'Create and manage bill of materials'],
            ['code' => 'manufacturing.work_orders.view', 'module' => 'manufacturing', 'action' => 'work_orders_view', 'description' => 'View work orders and production schedule'],
            ['code' => 'manufacturing.work_orders.manage', 'module' => 'manufacturing', 'action' => 'work_orders_manage', 'description' => 'Create and manage work orders'],
            ['code' => 'manufacturing.work_orders.execute', 'module' => 'manufacturing', 'action' => 'work_orders_execute', 'description' => 'Issue stock, record QC, and complete work orders'],
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
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'work_order_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropForeign(['work_order_id']);
                $table->dropColumn('work_order_id');
            });
        }

        Schema::dropIfExists('production_costs');
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bill_of_materials');

        DB::table('permissions')->whereIn('code', [
            'manufacturing.bom.view',
            'manufacturing.bom.manage',
            'manufacturing.work_orders.view',
            'manufacturing.work_orders.manage',
            'manufacturing.work_orders.execute',
        ])->delete();
    }
};
