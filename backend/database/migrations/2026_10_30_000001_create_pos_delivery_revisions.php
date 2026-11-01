<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_import_delivery_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances');
            $t->uuid('source_revision_id');
            $t->uuid('source_delivery_id');
            $t->unsignedInteger('version');
            $t->char('payload_hash', 64);
            $t->json('source_snapshot');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_revision_id'], 'pos_delivery_revision_unique');
            $t->unique(['pos_instance_id', 'source_delivery_id', 'version'], 'pos_delivery_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_delivery_revisions');
    }
};
