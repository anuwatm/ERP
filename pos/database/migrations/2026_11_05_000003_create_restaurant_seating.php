<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_restaurant_tables', fn (Blueprint $table) => $table->unsignedSmallInteger('capacity')->default(4));
        Schema::table('pos_kitchen_orders', fn (Blueprint $table) => $table->boolean('queue_held')->default(false));
        Schema::create('pos_seating_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('pos_branches');
            $table->foreignUuid('table_id')->nullable()->constrained('pos_restaurant_tables');
            $table->foreignUuid('session_id')->nullable()->unique()->constrained('pos_table_sessions');
            $table->uuid('request_key');
            $table->char('request_hash', 64);
            $table->string('kind', 12);
            $table->string('status', 12);
            $table->unsignedSmallInteger('party_size');
            $table->unsignedBigInteger('queue_number');
            $table->dateTime('booked_at')->nullable();
            $table->text('contact')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'request_key']);
            $table->unique(['branch_id', 'queue_number']);
            $table->index(['branch_id', 'status', 'booked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_seating_entries');
        Schema::table('pos_kitchen_orders', fn (Blueprint $table) => $table->dropColumn('queue_held'));
        Schema::table('pos_restaurant_tables', fn (Blueprint $table) => $table->dropColumn('capacity'));
    }
};
