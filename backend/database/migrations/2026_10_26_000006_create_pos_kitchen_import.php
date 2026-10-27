<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_import_kitchen_consumptions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_line_id');
            $t->uuid('source_order_id');
            $t->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $t->string('catalog_version', 80);
            $t->char('recipe_hash', 64);
            $t->char('payload_hash', 64);
            $t->json('source_snapshot');
            $t->json('account_snapshot');
            $t->string('purpose', 32);
            $t->string('inventory_status', 24)->default('pending');
            $t->decimal('base_cost', 18, 2)->nullable();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_line_id'], 'pos_kitchen_consumption_source_unique');
        });
        Schema::create('pos_import_kitchen_sales', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_kitchen_line_id');
            $t->foreignUuid('sale_id')->constrained('pos_import_sales')->restrictOnDelete();
            $t->uuid('source_sale_line_id');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_kitchen_line_id'], 'pos_kitchen_sale_source_unique');
        });
        Schema::create('pos_import_kitchen_waste', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_waste_id');
            $t->foreignUuid('consumption_id')->constrained('pos_import_kitchen_consumptions')->restrictOnDelete();
            $t->char('payload_hash', 64);
            $t->json('source_snapshot');
            $t->json('account_snapshot');
            $t->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_waste_id']);
            $t->unique('consumption_id');
        });
        Schema::create('pos_sync_dependencies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->foreignUuid('inbox_id')->constrained('pos_sync_inbox')->restrictOnDelete();
            $t->string('source_kind', 30);
            $t->uuid('source_id');
            $t->timestamps();
            $t->unique(['inbox_id', 'source_kind', 'source_id']);
        });
    }

    public function down(): void
    {
        foreach (['pos_sync_dependencies', 'pos_import_kitchen_waste', 'pos_import_kitchen_sales', 'pos_import_kitchen_consumptions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
