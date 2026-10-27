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
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances');
            $t->uuid('source_checkpoint_id');
            $t->unsignedBigInteger('after_sequence');
            $t->unsignedBigInteger('through_sequence');
            $t->string('recovery_generation', 100);
            $t->char('digest', 64);
            $t->longText('snapshot');
            $t->string('status', 30);
            $t->json('issues');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_checkpoint_id'], 'pos_checkpoint_source_unique');
        });
        Schema::table('pos_instances', function (Blueprint $t) {
            $t->string('recovery_generation', 100)->nullable();
            $t->unsignedBigInteger('checkpoint_cursor')->default(0);
            $t->timestamp('recovery_fenced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_instances', fn (Blueprint $t) => $t->dropColumn(['recovery_generation', 'checkpoint_cursor', 'recovery_fenced_at']));
        Schema::dropIfExists('pos_source_checkpoints');
    }
};
