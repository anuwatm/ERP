<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_stock_entitlements', function (Blueprint $t) {
            $t->json('freeze_evidence')->nullable();
            $t->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_stock_entitlements', fn (Blueprint $t) => $t->dropColumn(['freeze_evidence', 'closed_at']));
    }
};
