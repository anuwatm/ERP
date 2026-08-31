<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['org_id', 'code']);
        });
        Schema::create('warehouse_bins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('lot_no', 100);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'product_id', 'lot_no']);
            $table->unique(['org_id', 'barcode']);
            $table->index(['org_id', 'expires_at'], 'inv_lot_org_expiry_idx');
        });
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('source_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignUuid('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('destination_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignUuid('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->date('transfer_date');
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_unit_cost', 18, 2);
            $table->uuid('idempotency_key');
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'idempotency_key']);
        });
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->date('count_date');
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4);
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_id', 'inventory_lot_id'], 'stock_count_product_lot_uq');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable();
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->unique(['org_id', 'barcode']);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignUuid('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->foreignUuid('stock_transfer_id')->nullable()->constrained('stock_transfers')->nullOnDelete();
            $table->foreignUuid('stock_count_id')->nullable()->constrained('stock_counts')->nullOnDelete();
            $table->index(['org_id', 'warehouse_id', 'product_id'], 'stock_move_org_warehouse_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropColumn(['warehouse_id', 'warehouse_bin_id', 'inventory_lot_id', 'stock_transfer_id', 'stock_count_id']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['barcode', 'reorder_point']));
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouses');
    }
};
