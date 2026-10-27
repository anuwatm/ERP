<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_refunds', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('order_id')->constrained('pos_orders');
            $t->foreignUuid('shift_id')->constrained('pos_shifts');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->json('snapshot');
            $t->decimal('subtotal', 18, 2);
            $t->decimal('tax_amount', 18, 2);
            $t->decimal('total', 18, 2);
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
        });
        Schema::create('pos_refund_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('refund_id')->constrained('pos_refunds');
            $t->foreignUuid('order_item_id')->constrained('pos_order_items');
            $t->decimal('quantity', 18, 4);
            $t->decimal('returned_quantity', 18, 4);
            $t->decimal('subtotal', 18, 2);
            $t->decimal('tax_amount', 18, 2);
            $t->decimal('total', 18, 2);
            $t->string('return_status', 20)->default('not_returned');
            $t->timestamps();
            $t->unique(['refund_id', 'order_item_id']);
        });
        Schema::table('pos_outbox', fn (Blueprint $t) => $t->uuid('branch_id')->nullable()->index());
        foreach (DB::table('pos_orders')->select('id', 'branch_id')->cursor() as $order) {
            DB::table('pos_outbox')->where('aggregate_id', $order->id)->update(['branch_id' => $order->branch_id]);
        }
    }

    public function down(): void
    {
        Schema::table('pos_outbox', fn (Blueprint $t) => $t->dropColumn('branch_id'));
        Schema::dropIfExists('pos_refund_lines');
        Schema::dropIfExists('pos_refunds');
    }
};
