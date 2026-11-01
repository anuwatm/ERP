<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('created_by')->constrained('pos_staff');
            $t->foreignUuid('sale_id')->nullable()->unique()->constrained('pos_orders');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->string('settlement_mode', 20);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->text('contact');
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key'], 'delivery_branch_request_unique');
            $t->index(['branch_id', 'status']);
        });
        Schema::create('pos_delivery_actions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('delivery_id')->constrained('pos_deliveries');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->string('action', 20);
            $t->string('from_status', 20);
            $t->string('to_status', 20);
            $t->unsignedInteger('result_version');
            $t->timestamps();
            $t->unique(['delivery_id', 'request_key'], 'delivery_action_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_delivery_actions');
        Schema::dropIfExists('pos_deliveries');
    }
};
