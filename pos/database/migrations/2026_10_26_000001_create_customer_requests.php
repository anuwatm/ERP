<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_branches', fn (Blueprint $t) => $t->string('active_catalog_version', 80)->nullable());
        Schema::create('pos_menu_tokens', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('pos_customer_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('token_id')->constrained('pos_menu_tokens');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->json('items');
            $t->string('catalog_version', 80);
            $t->string('status', 20)->default('pending');
            $t->foreignUuid('accepted_by')->nullable()->constrained('pos_staff');
            $t->foreignUuid('sale_id')->nullable()->constrained('pos_orders');
            $t->timestamps();
            $t->unique(['token_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_customer_requests');
        Schema::dropIfExists('pos_menu_tokens');
        Schema::table('pos_branches', fn (Blueprint $t) => $t->dropColumn('active_catalog_version'));
    }
};
