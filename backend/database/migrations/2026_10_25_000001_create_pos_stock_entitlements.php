<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_stock_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('pos_instance_id');
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->unsignedInteger('epoch')->default(1);
            $table->decimal('granted_quantity', 18, 4);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['org_id', 'product_id', 'warehouse_id'], 'pos_entitlement_pool');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('source_key', 160)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->unique(['org_id', 'source_key'], 'stock_movement_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movement_source_unique');
            $table->dropColumn(['source_key', 'source_hash']);
        });
        Schema::dropIfExists('pos_stock_entitlements');
    }
};
