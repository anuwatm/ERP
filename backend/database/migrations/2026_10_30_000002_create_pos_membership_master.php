<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id')->index();
            $table->string('public_code', 50);
            $table->string('tier', 40);
            $table->string('status', 20)->default('active');
            $table->longText('contact');
            $table->char('phone_fingerprint', 64);
            $table->uuid('request_key');
            $table->char('request_hash', 64);
            $table->uuid('created_by');
            $table->timestamps();
            $table->unique(['org_id', 'public_code']);
            $table->unique(['org_id', 'phone_fingerprint']);
            $table->unique(['org_id', 'request_key']);
        });
        Schema::create('pos_promotion_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id')->index();
            $table->uuid('promotion_id');
            $table->unsignedInteger('version');
            $table->string('name', 120);
            $table->longText('policy');
            $table->char('policy_hash', 64);
            $table->uuid('created_by');
            $table->timestamps();
            $table->unique(['org_id', 'promotion_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_promotion_rules');
        Schema::dropIfExists('pos_members');
    }
};
