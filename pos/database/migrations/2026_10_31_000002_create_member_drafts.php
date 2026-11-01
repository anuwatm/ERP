<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_member_drafts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id')->index();
            $t->uuid('staff_id');
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->longText('contact');
            $t->string('status', 30)->default('pending_review');
            $t->uuid('member_id')->nullable();
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_member_drafts');
    }
};
