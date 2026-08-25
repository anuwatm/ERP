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
        Schema::create('credit_debit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('note_no', 30);
            $table->string('type', 10);
            $table->string('status', 30)->default('issued');
            $table->date('issue_date');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'note_no']);
            $table->index(['org_id', 'type', 'issue_date']);
        });

        Schema::create('credit_debit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('credit_debit_note_id')->constrained('credit_debit_notes')->cascadeOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });

        Schema::create('billing_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('billing_no', 30);
            $table->string('status', 30)->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'billing_no']);
            $table->index(['org_id', 'customer_id', 'status']);
        });

        Schema::create('billing_note_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('billing_note_id')->constrained('billing_notes')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount_due', 18, 2);
            $table->timestamps();
            $table->unique(['billing_note_id', 'invoice_id']);
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('do_no', 30);
            $table->string('status', 30)->default('draft');
            $table->date('delivery_date');
            $table->string('receiver_name')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'do_no']);
            $table->index(['org_id', 'status', 'delivery_date']);
        });

        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('pr_no', 30);
            $table->string('status', 30)->default('draft');
            $table->date('request_date');
            $table->decimal('total', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->uuid('converted_po_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'pr_no']);
            $table->index(['org_id', 'status', 'request_date']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('voucher_no', 30);
            $table->string('type', 10);
            $table->string('status', 30)->default('issued');
            $table->nullableUuidMorphs('source');
            $table->date('voucher_date');
            $table->decimal('amount', 18, 2);
            $table->string('partner_name')->nullable();
            $table->text('description')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'voucher_no']);
            $table->index(['org_id', 'type', 'voucher_date']);
        });

        $permissions = [];
        foreach (['credit_debit_notes', 'billing_notes', 'delivery_orders', 'purchase_requests', 'vouchers'] as $module) {
            foreach (['view', 'create', 'update', 'approve'] as $action) {
                $permissions[] = ['code' => "{$module}.{$action}", 'module' => $module, 'action' => $action, 'description' => ucfirst(str_replace('_', ' ', $action.' '.$module))];
            }
        }

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
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('delivery_order_items');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('billing_note_lines');
        Schema::dropIfExists('billing_notes');
        Schema::dropIfExists('credit_debit_note_items');
        Schema::dropIfExists('credit_debit_notes');
    }
};
