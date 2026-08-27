<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')->nullable()->after('invoice_id')->constrained('bank_accounts')->nullOnDelete();
            $table->index(['org_id', 'bank_account_id', 'payment_date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')->nullable()->after('purchase_order_id')->constrained('bank_accounts')->nullOnDelete();
            $table->index(['org_id', 'bank_account_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['org_id', 'bank_account_id', 'paid_at']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['org_id', 'bank_account_id', 'payment_date']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
