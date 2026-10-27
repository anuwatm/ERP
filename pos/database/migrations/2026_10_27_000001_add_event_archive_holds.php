<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pos_outbox', 'pos_outbox_archive'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->timestamp('hold_at')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['pos_outbox', 'pos_outbox_archive'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('hold_at'));
        }
    }
};
