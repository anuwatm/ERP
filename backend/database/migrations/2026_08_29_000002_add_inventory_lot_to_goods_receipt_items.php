<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignUuid('inventory_lot_id')->nullable()->after('product_id')->constrained('inventory_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_lot_id']);
            $table->dropColumn('inventory_lot_id');
        });
    }
};
