<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_import_cash_movements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_cash_movement_id');
            $t->char('payload_hash', 64);
            $t->json('source_snapshot');
            $t->json('account_snapshot');
            $t->foreignUuid('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_cash_movement_id'], 'pos_cash_source_unique');
            $t->unique('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_cash_movements');
    }
};
