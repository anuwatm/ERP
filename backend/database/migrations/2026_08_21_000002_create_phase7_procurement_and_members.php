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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('supplier_code', 30);
            $table->string('name');
            $table->string('tax_id', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['org_id', 'supplier_code']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('po_no', 30);
            $table->string('status', 30)->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('tax_mode', 20)->default('exclusive');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('currency', 3)->default('THB');
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['org_id', 'po_no']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUuid('purchase_order_id')->nullable()->after('supplier_id')->constrained('purchase_orders')->nullOnDelete();
        });

        $permissions = [
            ['code' => 'suppliers.view', 'module' => 'suppliers', 'action' => 'view', 'description' => 'View suppliers'],
            ['code' => 'suppliers.create', 'module' => 'suppliers', 'action' => 'create', 'description' => 'Create suppliers'],
            ['code' => 'suppliers.update', 'module' => 'suppliers', 'action' => 'update', 'description' => 'Update suppliers'],
            ['code' => 'suppliers.delete', 'module' => 'suppliers', 'action' => 'delete', 'description' => 'Delete suppliers safely'],
            ['code' => 'purchase_orders.view', 'module' => 'purchase_orders', 'action' => 'view', 'description' => 'View purchase orders'],
            ['code' => 'purchase_orders.create', 'module' => 'purchase_orders', 'action' => 'create', 'description' => 'Create purchase orders'],
            ['code' => 'purchase_orders.update', 'module' => 'purchase_orders', 'action' => 'update', 'description' => 'Update draft purchase orders'],
            ['code' => 'purchase_orders.approve', 'module' => 'purchase_orders', 'action' => 'approve', 'description' => 'Approve purchase orders'],
            ['code' => 'purchase_orders.cancel', 'module' => 'purchase_orders', 'action' => 'cancel', 'description' => 'Cancel purchase orders'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, ['id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(), 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id', 'code');
        $roleMap = [
            'owner' => array_column($permissions, 'code'),
            'admin' => array_column($permissions, 'code'),
            'finance' => array_column($permissions, 'code'),
            'project_manager' => ['suppliers.view', 'purchase_orders.view'],
        ];

        foreach ($roleMap as $roleCode => $codes) {
            DB::table('roles')->where('code', $roleCode)->get(['id'])->each(function ($role) use ($codes, $permissionIds): void {
                foreach ($codes as $code) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissionIds[$code]]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
