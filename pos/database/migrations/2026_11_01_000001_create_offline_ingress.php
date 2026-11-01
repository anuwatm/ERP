<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_offline_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('instance_id');
            $t->uuid('branch_id')->index();
            $t->uuid('terminal_id');
            $t->uuid('staff_id');
            $t->string('label', 64);
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });
        Schema::create('pos_offline_inbox', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('device_id');
            $t->uuid('client_transaction_id');
            $t->uuid('branch_id')->index();
            $t->uuid('staff_id');
            $t->string('kind', 32);
            $t->string('client_created_at', 80);
            $t->timestamp('server_received_at');
            $t->char('payload_hash', 64);
            $t->longText('payload');
            $t->string('status', 20)->default('review');
            $t->string('reason_code', 64);
            $t->uuid('sale_id')->nullable();
            $t->timestamps();
            $t->unique(['device_id', 'client_transaction_id'], 'pos_offline_device_transaction_unique');
        });
        Schema::create('pos_offline_conflicts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('inbox_id');
            $t->char('payload_hash', 64);
            $t->longText('payload');
            $t->timestamp('server_received_at');
            $t->unique(['inbox_id', 'payload_hash'], 'pos_offline_conflict_payload_unique');
        });
        Schema::create('pos_offline_policies', function (Blueprint $t) {
            $t->uuid('branch_id')->primary();
            $t->unsignedInteger('version');
            $t->longText('policy');
            $t->uuid('updated_by');
            $t->timestamps();
        });
        Schema::create('pos_offline_permits', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('device_id')->index();
            $t->uuid('branch_id')->index();
            $t->uuid('staff_id');
            $t->uuid('terminal_id');
            $t->uuid('active_terminal_id')->nullable()->unique();
            $t->uuid('shift_id');
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->string('status', 24)->default('active');
            $t->timestamp('issued_at');
            $t->timestamp('expires_at');
            $t->longText('payload');
            $t->timestamps();
            $t->unique(['device_id', 'request_key'], 'pos_offline_permit_request_unique');
        });
        Schema::create('pos_offline_reservations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('permit_id');
            $t->uuid('grant_id');
            $t->uuid('product_id');
            $t->unsignedBigInteger('epoch');
            $t->decimal('quantity', 18, 4);
            $t->decimal('consumed_quantity', 18, 4)->default(0);
            $t->string('status', 20)->default('reserved');
            $t->timestamps();
            $t->unique(['permit_id', 'grant_id'], 'pos_offline_permit_grant_unique');
        });
        Schema::create('pos_offline_slots', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('permit_id');
            $t->unsignedInteger('ordinal');
            $t->uuid('client_transaction_id')->nullable();
            $t->string('status', 20)->default('allocated');
            $t->unique(['permit_id', 'ordinal'], 'pos_offline_slot_ordinal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_offline_slots');
        Schema::dropIfExists('pos_offline_reservations');
        Schema::dropIfExists('pos_offline_permits');
        Schema::dropIfExists('pos_offline_policies');
        Schema::dropIfExists('pos_offline_conflicts');
        Schema::dropIfExists('pos_offline_inbox');
        Schema::dropIfExists('pos_offline_devices');
    }
};
