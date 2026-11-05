<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_offline_receipt_claims', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('pos_instance_id');
            $t->uuid('permit_id');
            $t->uuid('slot_id');
            $t->uuid('device_id');
            $t->uuid('client_transaction_id');
            $t->uuid('source_sale_id');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'slot_id'], 'pos_offline_claim_slot_unique');
            $t->unique(['pos_instance_id', 'device_id', 'client_transaction_id'], 'pos_offline_claim_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_offline_receipt_claims');
    }
};
