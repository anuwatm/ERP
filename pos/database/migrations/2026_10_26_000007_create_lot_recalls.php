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
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->uuid('lot_id');
            $t->uuid('product_id');
            $t->string('reason', 500);
            $t->char('payload_hash', 64);
            $t->timestamp('recalled_at');
            $t->timestamps();
            $t->unique(['branch_id', 'lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_lot_recalls');
    }
};
