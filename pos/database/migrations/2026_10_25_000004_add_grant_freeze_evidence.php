<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_grants', fn (Blueprint $t) => $t->json('freeze_evidence')->nullable());
    }

    public function down(): void
    {
        Schema::table('pos_grants', fn (Blueprint $t) => $t->dropColumn('freeze_evidence'));
    }
};
