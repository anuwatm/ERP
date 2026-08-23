<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('withholding_tax_rate', 5, 2)->default(0)->after('amount');
            $table->decimal('withholding_tax_amount', 18, 2)->default(0)->after('withholding_tax_rate');
            $table->string('withholding_tax_form', 20)->nullable()->after('withholding_tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['withholding_tax_rate', 'withholding_tax_amount', 'withholding_tax_form']);
        });
    }
};
