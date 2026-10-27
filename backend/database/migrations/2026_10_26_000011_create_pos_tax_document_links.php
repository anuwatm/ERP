<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tax_profile_publications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances');
            $t->string('version', 80);
            $t->json('snapshot');
            $t->char('payload_hash', 64);
            $t->timestamps();
            $t->unique(['pos_instance_id', 'version']);
        });
        Schema::create('pos_import_tax_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('org_id')->constrained('organizations');
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances');
            $t->uuid('source_document_id');
            $t->foreignUuid('sale_id')->constrained('pos_import_sales');
            $t->string('document_no', 180);
            $t->string('kind', 10);
            $t->uuid('replaces_source_id')->nullable();
            $t->unsignedTinyInteger('current_slot')->nullable();
            $t->char('payload_hash', 64);
            $t->longText('source_snapshot');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_document_id'], 'pos_tax_document_source_unique');
            $t->unique(['org_id', 'document_no']);
            $t->unique(['sale_id', 'current_slot']);
            $t->unique(['pos_instance_id', 'replaces_source_id'], 'pos_tax_document_replacement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_tax_documents');
        Schema::dropIfExists('pos_tax_profile_publications');
    }
};
