<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_discount_approvals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('requester_id')->constrained('pos_staff');
            $t->foreignUuid('manager_id')->constrained('pos_staff');
            $t->string('request_key', 100);
            $t->char('command_hash', 64);
            $t->json('command');
            $t->decimal('discounted_total', 18, 2);
            $t->string('approved_at', 35);
            $t->timestamp('expires_at');
            $t->foreignUuid('sale_id')->nullable()->constrained('pos_orders');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
            $t->unique('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_discount_approvals');
    }
};
