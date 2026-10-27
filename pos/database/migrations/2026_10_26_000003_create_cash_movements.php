<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cash_movements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('shift_id')->constrained('pos_shifts');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->string('direction', 3);
            $t->decimal('amount', 18, 2);
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
