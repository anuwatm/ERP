<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('number_sequences', 'period_key')) {
            Schema::table('number_sequences', function (Blueprint $table) {
                $table->string('period_key', 20)->default('all')->after('year_key');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('number_sequences', function (Blueprint $table) {
                $table->dropForeign(['org_id']);
            });
        }
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropUnique(['org_id', 'branch_key', 'doc_type', 'year_key']);
            $table->unique(['org_id', 'branch_key', 'doc_type', 'period_key']);
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('number_sequences', function (Blueprint $table) {
                $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('number_sequences', function (Blueprint $table) {
                $table->dropForeign(['org_id']);
            });
        }
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropUnique(['org_id', 'branch_key', 'doc_type', 'period_key']);
            $table->unique(['org_id', 'branch_key', 'doc_type', 'year_key']);
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('number_sequences', function (Blueprint $table) {
                $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }

        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropColumn('period_key');
        });
    }
};
