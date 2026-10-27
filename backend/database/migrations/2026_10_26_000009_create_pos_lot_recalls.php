<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_lot_recalls', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('org_id')->constrained('organizations');
            $t->foreignUuid('inventory_lot_id')->constrained('inventory_lots');
            $t->foreignUuid('product_id')->constrained('products');
            $t->foreignUuid('created_by')->constrained('users');
            $t->string('reason', 500);
            $t->timestamps();
            $t->unique(['org_id', 'inventory_lot_id']);
        });
        Schema::create('pos_recall_deliveries', function (Blueprint $t) {
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances');
            $t->foreignUuid('recall_id')->constrained('pos_lot_recalls');
            $t->timestamp('acknowledged_at');
            $t->primary(['pos_instance_id', 'recall_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_recall_deliveries');
        Schema::dropIfExists('pos_lot_recalls');
    }
};
