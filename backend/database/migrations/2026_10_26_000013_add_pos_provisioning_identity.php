<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_instances', function (Blueprint $t) {
            $t->string('branch_code', 30)->nullable();
            $t->json('terminal_codes')->nullable();
            $t->string('current_catalog_version', 80)->nullable();
            $t->string('current_tax_profile_version', 80)->nullable();
        });
        Schema::table('pos_stock_entitlements', fn (Blueprint $t) => $t->json('publication_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('pos_instances', fn (Blueprint $t) => $t->dropColumn(['branch_code', 'terminal_codes', 'current_catalog_version', 'current_tax_profile_version']));
        Schema::table('pos_stock_entitlements', fn (Blueprint $t) => $t->dropColumn('publication_snapshot'));
    }
};
