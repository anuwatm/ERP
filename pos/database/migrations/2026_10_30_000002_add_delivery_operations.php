<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_delivery_reservations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('delivery_id')->constrained('pos_deliveries');
            $t->foreignUuid('grant_id')->constrained('pos_grants');
            $t->unsignedInteger('epoch');
            $t->decimal('quantity', 18, 4);
            $t->string('status', 20)->default('reserved');
            $t->timestamps();
        });
        Schema::create('pos_delivery_kitchen_lines', function (Blueprint $t) {
            $t->foreignUuid('delivery_id')->constrained('pos_deliveries');
            $t->foreignUuid('line_id')->unique()->constrained('pos_kitchen_lines');
            $t->primary(['delivery_id', 'line_id'], 'delivery_kitchen_pk');
        });
        Schema::create('pos_delivery_cash', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('delivery_id')->constrained('pos_deliveries');
            $t->foreignUuid('shift_id')->constrained('pos_shifts');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('direction', 3);
            $t->decimal('amount', 18, 2);
            $t->timestamps();
            $t->unique(['delivery_id', 'direction'], 'delivery_cash_direction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_delivery_cash');
        Schema::dropIfExists('pos_delivery_kitchen_lines');
        Schema::dropIfExists('pos_delivery_reservations');
    }
};
