<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_instances', fn (Blueprint $t) => $t->string('business_type', 20)->default('retail'));
    }

    public function down(): void
    {
        Schema::table('pos_instances', fn (Blueprint $t) => $t->dropColumn('business_type'));
    }
};
