<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\VendorPayment;
use App\Services\FinancialJournalService;
use App\Services\FxRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorPaymentController extends Controller
{
    public function store(Request $request, Expense $expense, FinancialJournalService $journals, FxRateService $rates): RedirectResponse
    {
        abort_unless($expense->org_id === $request->user()->org_id, 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $expense->org_id)->where('status', 'active')],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ]);
        $key = $data['idempotency_key'] ?? (string) Str::uuid();

        $payment = DB::transaction(function () use ($request, $expense, $data, $key, $journals, $rates): VendorPayment {
            $locked = Expense::where('org_id', $request->user()->org_id)->lockForUpdate()->findOrFail($expense->id);
            $existing = VendorPayment::where('org_id', $locked->org_id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->expense_id === $locked->id && $existing->entry_type === 'payment', 422, 'Idempotency-Key has already been used for a different payment.');

                return $existing;
            }
            abort_unless(in_array($locked->status, ['approved', 'partially_paid'], true), 422, 'Only approved expenses can be paid.');
            $amount = round((float) $data['amount'], 2);
            abort_if($amount > round((float) $locked->balance_due, 2), 422, 'Payment exceeds balance due.');
            $bank = filled($data['bank_account_id'] ?? null) ? BankAccount::where('org_id', $locked->org_id)->findOrFail($data['bank_account_id']) : null;
            abort_if($bank && strtoupper($bank->currency) !== strtoupper($locked->currency), 422, 'Bank account currency must match expense currency.');
            $snapshot = $rates->snapshot($locked->org_id, $locked->currency, $data['payment_date'], ['amount' => $amount]);
            $baseSettlement = round($amount * ((float) $locked->base_balance_due / max((float) $locked->balance_due, 0.01)), 2);
            $payment = VendorPayment::create([
                'org_id' => $locked->org_id, 'expense_id' => $locked->id, 'bank_account_id' => $bank?->id,
                'entry_type' => 'payment', 'payment_date' => $data['payment_date'], 'amount' => $amount,
                'currency' => $locked->currency, 'base_currency' => $snapshot['base_currency'], 'exchange_rate' => $snapshot['exchange_rate'],
                'base_amount' => $snapshot['base_amount'], 'expense_base_amount' => $baseSettlement,
                'reference_no' => $data['reference_no'] ?? null, 'note' => $data['note'] ?? null, 'idempotency_key' => $key, 'created_by' => $request->user()->id,
            ]);
            $this->recalculate($locked, $request->user()->id);
            $journals->postVendorPayment($payment, $request->user()->id);
            $this->audit($request, 'vendor_payment.create', $payment);

            return $payment;
        });

        return back()->with('success', "Vendor payment {$payment->amount} recorded.");
    }

    public function reverse(Request $request, VendorPayment $vendorPayment, FinancialJournalService $journals): RedirectResponse
    {
        abort_unless($vendorPayment->org_id === $request->user()->org_id, 404);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'uuid']]);
        $key = $data['idempotency_key'] ?? (string) Str::uuid();
        DB::transaction(function () use ($request, $vendorPayment, $data, $key, $journals): void {
            $payment = VendorPayment::where('org_id', $request->user()->org_id)->lockForUpdate()->findOrFail($vendorPayment->id);
            abort_unless($payment->entry_type === 'payment', 422, 'Only vendor payments can be reversed.');
            abort_if(VendorPayment::where('reversal_of_vendor_payment_id', $payment->id)->exists(), 422, 'Vendor payment has already been reversed.');
            $expense = Expense::where('org_id', $payment->org_id)->lockForUpdate()->findOrFail($payment->expense_id);
            $reversal = VendorPayment::create([
                'org_id' => $payment->org_id, 'expense_id' => $payment->expense_id, 'bank_account_id' => $payment->bank_account_id,
                'reversal_of_vendor_payment_id' => $payment->id, 'entry_type' => 'reversal', 'payment_date' => now()->toDateString(),
                'amount' => $payment->amount, 'currency' => $payment->currency, 'base_currency' => $payment->base_currency,
                'exchange_rate' => $payment->exchange_rate, 'base_amount' => $payment->base_amount, 'expense_base_amount' => $payment->expense_base_amount,
                'reference_no' => $payment->reference_no, 'note' => $data['note'] ?? null, 'idempotency_key' => $key, 'created_by' => $request->user()->id,
            ]);
            $this->recalculate($expense, $request->user()->id);
            $journals->postVendorPayment($reversal, $request->user()->id);
            $this->audit($request, 'vendor_payment.reverse', $reversal);
        });

        return back()->with('success', 'Vendor payment reversed.');
    }

    private function recalculate(Expense $expense, string $actorId): void
    {
        $paid = (float) VendorPayment::where('expense_id', $expense->id)->where('entry_type', 'payment')->sum('amount') - (float) VendorPayment::where('expense_id', $expense->id)->where('entry_type', 'reversal')->sum('amount');
        $basePaid = (float) VendorPayment::where('expense_id', $expense->id)->where('entry_type', 'payment')->sum('expense_base_amount') - (float) VendorPayment::where('expense_id', $expense->id)->where('entry_type', 'reversal')->sum('expense_base_amount');
        $balance = max(0, round((float) $expense->payable_total - $paid, 2));
        $baseBalance = max(0, round((float) $expense->base_payable_total - $basePaid, 2));
        $expense->update(['paid_amount' => $paid, 'base_paid_amount' => $basePaid, 'balance_due' => $balance, 'base_balance_due' => $baseBalance, 'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'approved'), 'paid_at' => $balance <= 0 ? now() : null, 'updated_by' => $actorId]);
    }

    private function audit(Request $request, string $action, VendorPayment $payment): void
    {
        AuditLog::create(['org_id' => $payment->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => 'vendor_payment', 'entity_id' => $payment->id, 'after_json' => $payment->only(['expense_id', 'entry_type', 'amount', 'base_amount', 'expense_base_amount', 'bank_account_id']), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
