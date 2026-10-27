<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_grants', fn (Blueprint $t) => $t->decimal('reserved_quantity', 18, 4)->default(0));
        Schema::create('pos_restaurant_tables', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->string('label', 60);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['branch_id', 'label']);
        });
        Schema::create('pos_table_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('table_id')->constrained('pos_restaurant_tables');
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('opened_by')->constrained('pos_staff');
            $t->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->unique(['table_id', 'active_slot']);
        });
        Schema::create('pos_kitchen_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('terminal_id')->constrained('pos_terminals');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->foreignUuid('table_session_id')->nullable()->constrained('pos_table_sessions');
            $t->string('channel', 12);
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->string('catalog_version', 80);
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
        });
        Schema::create('pos_kitchen_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('pos_kitchen_orders');
            $t->uuid('product_id');
            $t->string('status', 24)->default('reserved');
            $t->json('snapshot');
            $t->foreignUuid('billed_sale_id')->nullable()->constrained('pos_orders');
            $t->char('cancel_command_hash', 64)->nullable();
            $t->char('consumption_hash', 64)->nullable();
            $t->string('consumption_purpose', 32)->nullable();
            $t->timestamps();
            $t->index(['order_id', 'status']);
        });
        Schema::create('pos_kitchen_reservations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('line_id')->constrained('pos_kitchen_lines');
            $t->foreignUuid('grant_id')->constrained('pos_grants');
            $t->unsignedInteger('epoch');
            $t->decimal('quantity', 18, 4);
            $t->string('status', 12)->default('reserved');
            $t->timestamps();
            $t->unique(['line_id', 'grant_id']);
        });
        Schema::create('pos_kitchen_waste', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('line_id')->constrained('pos_kitchen_lines');
            $t->foreignUuid('manager_id')->constrained('pos_staff');
            $t->string('reason', 30);
            $t->json('snapshot');
            $t->timestamps();
            $t->unique('line_id');
        });
    }

    public function down(): void
    {
        foreach (['pos_kitchen_waste', 'pos_kitchen_reservations', 'pos_kitchen_lines', 'pos_kitchen_orders', 'pos_table_sessions', 'pos_restaurant_tables'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('pos_grants', fn (Blueprint $t) => $t->dropColumn('reserved_quantity'));
    }
};
