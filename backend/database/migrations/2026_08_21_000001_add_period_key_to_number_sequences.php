<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->string('period_key', 20)->default('all')->after('year_key');
        });

        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropUnique(['org_id', 'branch_key', 'doc_type', 'year_key']);
            $table->unique(['org_id', 'branch_key', 'doc_type', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropUnique(['org_id', 'branch_key', 'doc_type', 'period_key']);
            $table->unique(['org_id', 'branch_key', 'doc_type', 'year_key']);
        });

        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropColumn('period_key');
        });
    }
};
