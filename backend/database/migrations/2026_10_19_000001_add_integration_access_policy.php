<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_api_keys', fn (Blueprint $table) => $table->json('access_policy')->nullable());
    }

    public function down(): void
    {
        Schema::table('integration_api_keys', fn (Blueprint $table) => $table->dropColumn('access_policy'));
    }
};
