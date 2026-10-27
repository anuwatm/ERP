<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_branches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('business_type', 20);
            $t->string('currency', 3)->default('THB');
            $t->string('timezone')->default('Asia/Bangkok');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('pos_terminals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->string('code', 30);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['branch_id', 'code']);
        });
        Schema::create('pos_staff', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role', 30);
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('pos_catalogs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->string('version', 80);
            $t->char('payload_hash', 64);
            $t->json('items');
            $t->timestamp('valid_until');
            $t->timestamps();
            $t->unique(['branch_id', 'version']);
        });
        Schema::create('pos_grants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->uuid('product_id');
            $t->uuid('lot_id')->nullable();
            $t->date('expires_at')->nullable();
            $t->unsignedInteger('epoch');
            $t->decimal('granted_quantity', 18, 4);
            $t->decimal('consumed_quantity', 18, 4)->default(0);
            $t->string('status', 20)->default('active');
            $t->char('payload_hash', 64);
            $t->timestamps();
            $t->index(['branch_id', 'product_id', 'status']);
        });
        Schema::create('pos_shifts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('terminal_id')->constrained('pos_terminals');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('status', 20);
            $t->decimal('opening_cash', 18, 2);
            $t->decimal('expected_cash', 18, 2)->nullable();
            $t->decimal('counted_cash', 18, 2)->nullable();
            $t->decimal('over_short', 18, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('pos_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('terminal_id')->constrained('pos_terminals');
            $t->foreignUuid('shift_id')->constrained('pos_shifts');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->string('receipt_no', 100)->unique();
            $t->string('status', 30);
            $t->string('catalog_version', 80);
            $t->string('currency', 3);
            $t->decimal('subtotal', 18, 2);
            $t->decimal('tax_amount', 18, 2);
            $t->decimal('total', 18, 2);
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
        });
        Schema::create('pos_order_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('pos_orders');
            $t->uuid('product_id');
            $t->decimal('quantity', 18, 4);
            $t->json('snapshot');
            $t->timestamps();
        });
        Schema::create('pos_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained('pos_orders');
            $t->string('method', 20);
            $t->decimal('amount', 18, 2);
            $t->decimal('tendered', 18, 2);
            $t->decimal('change', 18, 2);
            $t->timestamps();
            $t->unique('order_id');
        });
        Schema::create('pos_outbox', function (Blueprint $t) {
            $t->bigIncrements('sequence');
            $t->uuid('event_id')->unique();
            $t->uuid('aggregate_id');
            $t->string('event_type', 80);
            $t->longText('envelope');
            $t->string('result_status', 30)->default('pending');
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::create('pos_audit', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('actor_id')->nullable();
            $t->uuid('branch_id');
            $t->string('action', 100);
            $t->uuid('source_id')->nullable();
            $t->json('metadata');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['pos_audit', 'pos_outbox', 'pos_payments', 'pos_order_items', 'pos_orders', 'pos_shifts', 'pos_grants', 'pos_catalogs', 'pos_staff', 'pos_terminals', 'pos_branches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
