<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('tax_mode', 20)->default('no_tax')->after('amount');
            $table->string('tax_invoice_no', 50)->nullable()->after('tax_mode');
            $table->decimal('tax_amount', 18, 2)->default(0)->after('tax_invoice_no');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('unit_cost');
            $table->decimal('tax_amount', 18, 2)->default(0)->after('tax_rate');
            $table->decimal('line_total', 18, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount', 'line_total']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['tax_mode', 'tax_invoice_no', 'tax_amount']);
        });
    }
};
