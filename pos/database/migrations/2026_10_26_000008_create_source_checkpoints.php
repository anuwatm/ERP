<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_source_checkpoints', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->unsignedBigInteger('after_sequence');
            $t->unsignedBigInteger('through_sequence');
            $t->char('digest', 64);
            $t->longText('snapshot');
            $t->timestamp('reconciled_at')->nullable();
            $t->timestamp('hold_at')->nullable();
            $t->timestamps();
        });
        Schema::create('pos_outbox_archive', function (Blueprint $t) {
            $t->unsignedBigInteger('sequence')->primary();
            $t->uuid('event_id')->unique();
            $t->uuid('branch_id')->index();
            $t->uuid('aggregate_id');
            $t->string('event_type', 80);
            $t->longText('envelope');
            $t->string('result_status', 30);
            $t->json('result')->nullable();
            $t->timestamps();
            $t->timestamp('archived_at');
            $t->foreignUuid('checkpoint_id')->constrained('pos_source_checkpoints');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_outbox_archive');
        Schema::dropIfExists('pos_source_checkpoints');
    }
};
