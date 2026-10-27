<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_outbox_archive', function (Blueprint $t) {
            $t->string('archive_path')->nullable();
            $t->char('envelope_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_outbox_archive', fn (Blueprint $t) => $t->dropColumn(['archive_path', 'envelope_hash']));
    }
};
