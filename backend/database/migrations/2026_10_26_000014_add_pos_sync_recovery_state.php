<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('pos_instances', function (Blueprint $t) { $t->timestamp('last_sync_at')->nullable(); $t->string('last_sync_error', 200)->nullable(); $t->unsignedInteger('sync_failures')->default(0); $t->timestamp('next_sync_at')->nullable(); }); }
    public function down(): void { Schema::table('pos_instances', fn (Blueprint $t) => $t->dropColumn(['last_sync_at', 'last_sync_error', 'sync_failures', 'next_sync_at'])); }
};
