<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_membership_publications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('org_id')->index();
            $t->uuid('pos_instance_id');
            $t->unsignedBigInteger('version');
            $t->longText('payload');
            $t->char('payload_hash', 64);
            $t->uuid('created_by');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'version']);
        });
        Schema::create('pos_promotion_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('org_id')->index();
            $t->uuid('pos_instance_id')->index();
            $t->uuid('rule_id')->index();
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->unsignedSmallInteger('ordinal');
            $t->unique(['pos_instance_id', 'request_key', 'ordinal'], 'pos_promo_allocation_request_unique');
            $t->uuid('member_id')->nullable();
            $t->timestamps();
        });
        Schema::create('pos_import_promotion_claims', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('pos_instance_id')->index();
            $t->string('source_kind', 20);
            $t->uuid('source_id');
            $t->uuid('grant_id')->nullable();
            $t->uuid('active_slot')->nullable()->unique();
            $t->longText('proof');
            $t->char('proof_hash', 64);
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_kind', 'source_id'], 'pos_promo_claim_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_promotion_claims');
        Schema::dropIfExists('pos_promotion_allocations');
        Schema::dropIfExists('pos_membership_publications');
    }
};
