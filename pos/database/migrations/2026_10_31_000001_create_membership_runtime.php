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
            $t->uuid('branch_id');
            $t->unsignedBigInteger('version');
            $t->longText('payload');
            $t->char('payload_hash', 64);
            $t->timestamp('valid_until');
            $t->timestamps();
            $t->unique(['branch_id', 'version']);
        });
        Schema::create('pos_promotion_rights', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id')->index();
            $t->uuid('promotion_id');
            $t->unsignedInteger('policy_version');
            $t->uuid('member_id')->nullable();
            $t->uuid('used_by')->nullable();
            $t->timestamps();
        });
        Schema::create('pos_promotion_claims', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id')->index();
            $t->string('source_kind', 20);
            $t->uuid('source_id');
            $t->uuid('grant_id')->nullable();
            $t->longText('proof');
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
            $t->unique(['source_kind', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_promotion_claims');
        Schema::dropIfExists('pos_promotion_rights');
        Schema::dropIfExists('pos_membership_publications');
    }
};
