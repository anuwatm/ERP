<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sameCurrency = static fn ($query) => $query
            ->whereColumn('currency', 'base_currency')
            ->where('exchange_rate', 1);

        $invoiceFields = [
            'base_subtotal' => 'subtotal',
            'base_tax_amount' => 'tax_amount',
            'base_total' => 'total',
            'base_paid_amount' => 'paid_amount',
            'base_balance_due' => 'balance_due',
        ];

        foreach ($invoiceFields as $base => $source) {
            $sameCurrency(DB::table('invoices'))
                ->where($base, 0)
                ->where($source, '>', 0)
                ->update([$base => DB::raw($source)]);
        }

        foreach (['expenses', 'payments'] as $table) {
            $sameCurrency(DB::table($table))
                ->where('base_amount', 0)
                ->where('amount', '>', 0)
                ->update(['base_amount' => DB::raw('amount')]);
        }
    }

    public function down(): void
    {
        // Historical source amounts cannot be distinguished after backfill.
    }
};
