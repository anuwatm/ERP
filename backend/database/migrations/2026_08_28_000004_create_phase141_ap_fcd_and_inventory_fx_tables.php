<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('payable_total', 18, 2)->default(0);
            $table->decimal('base_payable_total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('base_paid_amount', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->decimal('base_balance_due', 18, 2)->default(0);
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('expense_id')->constrained('expenses')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUuid('reversal_of_vendor_payment_id')->nullable()->unique()->constrained('vendor_payments')->restrictOnDelete();
            $table->string('entry_type', 20)->default('payment');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('base_currency', 3);
            $table->decimal('exchange_rate', 18, 6);
            $table->decimal('base_amount', 18, 2);
            $table->decimal('expense_base_amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->text('note')->nullable();
            $table->uuid('idempotency_key');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'idempotency_key']);
            $table->index(['org_id', 'expense_id', 'payment_date']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB');
            $table->string('base_currency', 3)->default('THB');
            $table->decimal('exchange_rate', 18, 6)->default(1);
        });
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB');
            $table->string('base_currency', 3)->default('THB');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('base_unit_cost', 18, 2)->default(0);
            $table->decimal('base_tax_amount', 18, 2)->default(0);
            $table->decimal('base_line_total', 18, 2)->default(0);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB');
            $table->string('base_currency', 3)->default('THB');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('base_unit_cost', 18, 2)->default(0);
            $table->decimal('base_total_cost', 18, 2)->default(0);
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->decimal('base_opening_balance', 18, 2)->default(0);
            $table->decimal('base_balance', 18, 2)->default(0);
        });
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB');
            $table->string('base_currency', 3)->default('THB');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('base_opening_balance', 18, 2)->default(0);
            $table->decimal('base_closing_balance', 18, 2)->default(0);
        });
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB');
            $table->string('base_currency', 3)->default('THB');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('base_amount_signed', 18, 2)->default(0);
            $table->decimal('base_balance_after', 18, 2)->nullable();
        });

        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('source_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUuid('destination_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->date('transfer_date');
            $table->decimal('source_amount', 18, 2);
            $table->string('source_currency', 3);
            $table->decimal('source_base_amount', 18, 2);
            $table->decimal('destination_amount', 18, 2);
            $table->string('destination_currency', 3);
            $table->decimal('destination_base_amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->text('note')->nullable();
            $table->uuid('idempotency_key');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'idempotency_key']);
            $table->index(['org_id', 'transfer_date']);
        });

        DB::table('expenses')->orderBy('id')->each(function (object $expense): void {
            $gross = $expense->tax_mode === 'exclusive' ? (float) $expense->amount + (float) $expense->tax_amount : (float) $expense->amount;
            $baseGross = $expense->tax_mode === 'exclusive' ? (float) $expense->base_amount + (float) $expense->base_tax_amount : (float) $expense->base_amount;
            DB::table('expenses')->where('id', $expense->id)->update(['payable_total' => $gross, 'base_payable_total' => $baseGross, 'paid_amount' => $expense->status === 'paid' ? $gross : 0, 'base_paid_amount' => $expense->status === 'paid' ? $baseGross : 0, 'balance_due' => in_array($expense->status, ['approved', 'paid'], true) && $expense->status !== 'paid' ? $gross : 0, 'base_balance_due' => in_array($expense->status, ['approved', 'paid'], true) && $expense->status !== 'paid' ? $baseGross : 0]);
        });
        DB::table('goods_receipt_items')->update(['base_unit_cost' => DB::raw('unit_cost'), 'base_tax_amount' => DB::raw('tax_amount'), 'base_line_total' => DB::raw('line_total')]);
        DB::table('stock_movements')->update(['base_unit_cost' => DB::raw('unit_cost'), 'base_total_cost' => DB::raw('total_cost')]);
        DB::table('bank_accounts')->update(['base_opening_balance' => DB::raw('opening_balance'), 'base_balance' => DB::raw('opening_balance')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
        Schema::table('bank_statement_lines', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_amount_signed', 'base_balance_after']));
        Schema::table('bank_statements', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_opening_balance', 'base_closing_balance']));
        Schema::table('bank_accounts', fn (Blueprint $table) => $table->dropColumn(['base_opening_balance', 'base_balance']));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_unit_cost', 'base_total_cost']));
        Schema::table('goods_receipt_items', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_unit_cost', 'base_tax_amount', 'base_line_total']));
        Schema::table('goods_receipts', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate']));
        Schema::dropIfExists('vendor_payments');
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['payable_total', 'base_payable_total', 'paid_amount', 'base_paid_amount', 'balance_due', 'base_balance_due']));
    }
};
