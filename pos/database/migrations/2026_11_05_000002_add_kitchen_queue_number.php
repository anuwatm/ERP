<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_kitchen_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('queue_number')->nullable();
            $table->unique(['branch_id', 'queue_number']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_kitchen_orders', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'queue_number']);
            $table->dropColumn('queue_number');
        });
    }
};
