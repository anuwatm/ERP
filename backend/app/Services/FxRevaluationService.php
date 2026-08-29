<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\FxRevaluation;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FxRevaluationService
{
    public function __construct(private readonly FxRateService $rates, private readonly JournalPostingService $journals) {}

    public function revalueReceivables(string $orgId, string $month, ?string $actorId): int
    {
        $date = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $count = 0;

        Invoice::query()->where('org_id', $orgId)->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('balance_due', '>', 0)->orderBy('id')->chunkById(100, function ($invoices) use ($orgId, $month, $date, $actorId, &$count): void {
                foreach ($invoices as $invoice) {
                    $created = DB::transaction(function () use ($invoice, $orgId, $month, $date, $actorId): bool {
                        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                        if (strtoupper($invoice->currency) === strtoupper($invoice->base_currency) || (float) $invoice->balance_due <= 0) {
                            return false;
                        }
                        if (FxRevaluation::where('org_id', $orgId)->where('source_type', 'invoice')->where('source_id', $invoice->id)->whereDate('revaluation_month', Carbon::createFromFormat('Y-m', $month)->startOfMonth())->exists()) {
                            return false;
                        }

                        $rate = $this->rates->rate($orgId, $invoice->base_currency, $invoice->currency, $date);
                        $before = round((float) $invoice->base_balance_due, 2);
                        $after = round((float) $invoice->balance_due * $rate, 2);
                        $difference = round($after - $before, 2);
                        if ($difference === 0.0) {
                            return false;
                        }

                        $entry = $this->journals->post($orgId, $actorId, 'invoice', $invoice->id, 'fx_revaluation:'.$month, $date, 'FX revaluation '.$invoice->invoice_no, $difference > 0 ? [
                            ['account_code' => '1120', 'debit' => $difference, 'description' => 'Accounts receivable revaluation'],
                            ['account_code' => '4310', 'credit' => $difference, 'description' => 'Unrealized foreign exchange gain'],
                        ] : [
                            ['account_code' => '5410', 'debit' => abs($difference), 'description' => 'Unrealized foreign exchange loss'],
                            ['account_code' => '1120', 'credit' => abs($difference), 'description' => 'Accounts receivable revaluation'],
                        ]);

                        FxRevaluation::create(['org_id' => $orgId, 'source_type' => 'invoice', 'source_id' => $invoice->id, 'currency' => $invoice->currency, 'revaluation_month' => Carbon::createFromFormat('Y-m', $month)->startOfMonth(), 'foreign_amount' => $invoice->balance_due, 'closing_rate' => $rate, 'base_before' => $before, 'base_after' => $after, 'difference' => $difference, 'journal_entry_id' => $entry->id, 'created_by' => $actorId]);
                        $invoice->update(['base_total' => round((float) $invoice->base_total + $difference, 2), 'base_balance_due' => $after]);

                        return true;
                    });
                    $count += $created ? 1 : 0;
                }
            });

        return $count;
    }

    public function reverseMonth(string $orgId, string $month, ?string $actorId): int
    {
        $count = 0;
        FxRevaluation::query()->where('org_id', $orgId)->whereDate('revaluation_month', Carbon::createFromFormat('Y-m', $month)->startOfMonth())->whereNull('reversed_at')->orderBy('id')->chunkById(100, function ($revaluations) use ($actorId, &$count): void {
            foreach ($revaluations as $revaluation) {
                DB::transaction(function () use ($revaluation, $actorId, &$count): void {
                    $revaluation = FxRevaluation::whereKey($revaluation->id)->lockForUpdate()->firstOrFail();
                    if ($revaluation->reversed_at) {
                        return;
                    }
                    $entry = JournalEntry::findOrFail($revaluation->journal_entry_id);
                    $reversal = $this->journals->reverse($entry, $actorId, now()->toDateString(), 'Auto reverse FX revaluation', 'fx_revaluation_reversal:'.$revaluation->revaluation_month->format('Y-m'));
                    if ($revaluation->source_type === 'invoice') {
                        $invoice = Invoice::whereKey($revaluation->source_id)->lockForUpdate()->first();
                        if ($invoice && (float) $invoice->balance_due > 0) {
                            $invoice->update(['base_total' => round((float) $invoice->base_total - (float) $revaluation->difference, 2), 'base_balance_due' => round((float) $invoice->base_balance_due - (float) $revaluation->difference, 2)]);
                        }
                    }
                    if ($revaluation->source_type === 'expense') {
                        $expense = Expense::whereKey($revaluation->source_id)->lockForUpdate()->first();
                        if ($expense && (float) $expense->balance_due > 0) {
                            $expense->update(['base_payable_total' => round((float) $expense->base_payable_total - (float) $revaluation->difference, 2), 'base_balance_due' => round((float) $expense->base_balance_due - (float) $revaluation->difference, 2)]);
                        }
                    }
                    if ($revaluation->source_type === 'bank_account') {
                        BankAccount::whereKey($revaluation->source_id)->decrement('base_balance', $revaluation->difference);
                    }
                    $revaluation->update(['reversal_journal_entry_id' => $reversal->id, 'reversed_at' => now()]);
                    $count++;
                });
            }
        });

        return $count;
    }

    public function revaluePayables(string $orgId, string $month, ?string $actorId): int
    {
        return $this->revalueOpen($orgId, $month, $actorId, Expense::class, 'expense');
    }

    public function revalueFcd(string $orgId, string $month, ?string $actorId): int
    {
        $date = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $baseCurrency = strtoupper((string) (Organization::findOrFail($orgId)->currency ?: 'THB'));
        $count = 0;
        BankAccount::where('org_id', $orgId)->where('status', 'active')->orderBy('id')->each(function (BankAccount $account) use ($orgId, $month, $date, $actorId, $baseCurrency, &$count): void {
            $created = DB::transaction(function () use ($account, $orgId, $month, $date, $actorId, $baseCurrency): bool {
                $account = BankAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
                if (strtoupper($account->currency) === $baseCurrency || ! $account->chart_of_account_id || FxRevaluation::where('org_id', $orgId)->where('source_type', 'bank_account')->where('source_id', $account->id)->whereDate('revaluation_month', Carbon::createFromFormat('Y-m', $month)->startOfMonth())->exists()) {
                    return false;
                }
                $rate = $this->rates->rate($orgId, $baseCurrency, $account->currency, $date);
                $foreign = (float) $account->opening_balance + (float) BankTransfer::where('destination_bank_account_id', $account->id)->whereDate('transfer_date', '<=', $date)->sum('destination_amount') - (float) BankTransfer::where('source_bank_account_id', $account->id)->whereDate('transfer_date', '<=', $date)->sum('source_amount');
                $before = (float) $account->base_balance;
                $after = round($foreign * $rate, 2);
                $difference = round($after - $before, 2);
                if ($difference === 0.0) {
                    return false;
                }
                $code = $account->chartOfAccount?->code ?: '1110';
                $entry = $this->journals->post($orgId, $actorId, 'bank_account', $account->id, 'fx_revaluation:'.$month, $date, 'FCD revaluation '.$account->account_name, $difference > 0 ? [['account_code' => $code, 'debit' => $difference, 'description' => 'FCD revaluation'], ['account_code' => '4310', 'credit' => $difference, 'description' => 'Unrealized foreign exchange gain']] : [['account_code' => '5410', 'debit' => abs($difference), 'description' => 'Unrealized foreign exchange loss'], ['account_code' => $code, 'credit' => abs($difference), 'description' => 'FCD revaluation']]);
                FxRevaluation::create(['org_id' => $orgId, 'source_type' => 'bank_account', 'source_id' => $account->id, 'currency' => $account->currency, 'revaluation_month' => Carbon::createFromFormat('Y-m', $month)->startOfMonth(), 'foreign_amount' => $foreign, 'closing_rate' => $rate, 'base_before' => $before, 'base_after' => $after, 'difference' => $difference, 'journal_entry_id' => $entry->id, 'created_by' => $actorId]);
                $account->update(['base_balance' => $after]);

                return true;
            });
            $count += $created ? 1 : 0;
        });

        return $count;
    }

    private function revalueOpen(string $orgId, string $month, ?string $actorId, string $model, string $sourceType): int
    {
        $date = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $count = 0;
        $model::where('org_id', $orgId)->whereIn('status', ['approved', 'partially_paid'])->where('balance_due', '>', 0)->orderBy('id')->each(function (Expense $expense) use ($orgId, $month, $date, $actorId, &$count): void {
            $created = DB::transaction(function () use ($expense, $orgId, $month, $date, $actorId): bool {
                $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
                if (strtoupper($expense->currency) === strtoupper($expense->base_currency) || FxRevaluation::where('org_id', $orgId)->where('source_type', 'expense')->where('source_id', $expense->id)->whereDate('revaluation_month', Carbon::createFromFormat('Y-m', $month)->startOfMonth())->exists()) {
                    return false;
                }
                $rate = $this->rates->rate($orgId, $expense->base_currency, $expense->currency, $date);
                $before = (float) $expense->base_balance_due;
                $after = round((float) $expense->balance_due * $rate, 2);
                $difference = round($after - $before, 2);
                if ($difference === 0.0) {
                    return false;
                }
                $entry = $this->journals->post($orgId, $actorId, 'expense', $expense->id, 'fx_revaluation:'.$month, $date, 'FX revaluation '.$expense->expense_no, $difference > 0 ? [['account_code' => '5410', 'debit' => $difference, 'description' => 'Accounts payable revaluation'], ['account_code' => '2110', 'credit' => $difference, 'description' => 'Accounts payable revaluation']] : [['account_code' => '2110', 'debit' => abs($difference), 'description' => 'Accounts payable revaluation'], ['account_code' => '4310', 'credit' => abs($difference), 'description' => 'Unrealized foreign exchange gain']]);
                FxRevaluation::create(['org_id' => $orgId, 'source_type' => 'expense', 'source_id' => $expense->id, 'currency' => $expense->currency, 'revaluation_month' => Carbon::createFromFormat('Y-m', $month)->startOfMonth(), 'foreign_amount' => $expense->balance_due, 'closing_rate' => $rate, 'base_before' => $before, 'base_after' => $after, 'difference' => $difference, 'journal_entry_id' => $entry->id, 'created_by' => $actorId]);
                $expense->update(['base_payable_total' => round((float) $expense->base_payable_total + $difference, 2), 'base_balance_due' => $after]);

                return true;
            });
            $count += $created ? 1 : 0;
        });

        return $count;
    }
}
