<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_instances', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('org_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('name');
            $t->boolean('active')->default(false);
            $t->json('terminal_ids');
            $t->json('signing_keys');
            $t->json('account_codes');
            $t->unsignedBigInteger('durable_cursor')->default(0);
            $t->timestamps();
        });
        Schema::create('pos_catalog_publications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->string('version', 80);
            $t->json('payload');
            $t->char('payload_hash', 64);
            $t->timestamp('valid_until');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'version'], 'pos_publication_version');
        });
        Schema::create('pos_sync_inbox', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('event_id');
            $t->unsignedBigInteger('source_sequence');
            $t->char('received_hash', 64);
            $t->longText('received_bytes');
            $t->string('status', 30)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('reason_code', 80)->nullable();
            $t->json('result')->nullable();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'event_id'], 'pos_inbox_event');
            $t->unique(['pos_instance_id', 'source_sequence'], 'pos_inbox_sequence');
        });
        Schema::create('pos_sync_conflicts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('event_id');
            $t->char('received_hash', 64);
            $t->longText('received_bytes');
            $t->timestamps();
        });
        Schema::create('pos_import_sales', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_sale_id');
            $t->char('payload_hash', 64);
            $t->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $t->foreignUuid('payment_id')->constrained('payments')->restrictOnDelete();
            $t->string('inventory_status', 30)->default('pending');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_sale_id'], 'pos_import_sale_identity');
            $t->unique('invoice_id');
        });
    }

    public function down(): void
    {
        foreach (['pos_import_sales', 'pos_sync_conflicts', 'pos_sync_inbox', 'pos_catalog_publications', 'pos_instances'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
