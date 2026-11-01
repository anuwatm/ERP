<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_import_member_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('org_id')->index();
            $t->uuid('pos_instance_id');
            $t->uuid('source_request_id');
            $t->uuid('source_staff_id');
            $t->char('payload_hash', 64);
            $t->longText('source_snapshot');
            $t->longText('contact');
            $t->string('status', 30)->default('pending_review');
            $t->uuid('member_id')->nullable();
            $t->uuid('reviewed_by')->nullable();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_request_id'], 'pos_member_request_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_member_requests');
    }
};
