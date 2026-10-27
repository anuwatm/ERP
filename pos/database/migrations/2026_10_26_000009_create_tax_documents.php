<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tax_profiles', function (Blueprint $t) {
            $t->foreignUuid('branch_id')->primary()->constrained('pos_branches');
            $t->string('version', 80);
            $t->json('snapshot');
            $t->char('payload_hash', 64);
            $t->timestamps();
        });
        Schema::create('pos_tax_sequences', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignUuid('terminal_id')->constrained('pos_terminals');
            $t->date('business_date');
            $t->string('kind', 10);
            $t->unsignedBigInteger('last_number')->default(0);
            $t->unique(['terminal_id', 'business_date', 'kind']);
        });
        Schema::create('pos_tax_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('order_id')->constrained('pos_orders');
            $t->foreignUuid('terminal_id')->constrained('pos_terminals');
            $t->foreignUuid('staff_id')->constrained('pos_staff');
            $t->string('kind', 10);
            $t->string('document_no', 180)->unique();
            $t->string('status', 20);
            $t->unsignedTinyInteger('current_slot')->nullable();
            $t->foreignUuid('replaces_id')->nullable()->constrained('pos_tax_documents');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->longText('snapshot');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
            $t->unique(['order_id', 'current_slot']);
            $t->unique('replaces_id');
        });
        Schema::create('pos_etax_queue', function (Blueprint $t) {
            $t->foreignUuid('document_id')->primary()->constrained('pos_tax_documents');
            $t->string('status', 30)->default('awaiting_provider');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('reason_code', 100)->default('provider_not_configured');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_etax_queue');
        Schema::dropIfExists('pos_tax_documents');
        Schema::dropIfExists('pos_tax_sequences');
        Schema::dropIfExists('pos_tax_profiles');
    }
};
