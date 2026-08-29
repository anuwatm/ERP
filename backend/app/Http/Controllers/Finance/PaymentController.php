<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinancialJournalService;
use App\Services\FxRateService;
use App\Support\FileAttachmentManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, FileAttachmentManager $files, FinancialJournalService $journals, FxRateService $fxRates): RedirectResponse
    {
        abort_unless($invoice->org_id === $request->user()->org_id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(Payment::METHODS)],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.FileAttachmentManager::MAX_KILOBYTES],
        ]);

        $idempotencyKey = $this->idempotencyKey($request, $validated);
        $existing = $this->existingIdempotentPayment($request, $idempotencyKey);

        if ($existing) {
            $this->assertReceiptIdempotencyMatch($existing, $invoice, $validated);

            return back()->with('success', 'Payment receipt already recorded.');
        }

        try {
            $payment = DB::transaction(function () use ($request, $invoice, $validated, $idempotencyKey, $files, $journals, $fxRates): Payment {
                $lockedInvoice = $this->lockInvoice($request, $invoice->id);
                $existing = $this->existingIdempotentPayment($request, $idempotencyKey);

                if ($existing) {
                    $this->assertReceiptIdempotencyMatch($existing, $lockedInvoice, $validated);

                    return $existing;
                }

                abort_if($lockedInvoice->status === 'void', 422, 'Cannot record payment on a void invoice.');

                $amount = round((float) $validated['amount'], 2);
                $balanceDue = round((float) $lockedInvoice->balance_due, 2);
                abort_if($amount > $balanceDue, 422, 'Payment exceeds balance due.');
                $bankAccount = filled($validated['bank_account_id'] ?? null) ? BankAccount::find($validated['bank_account_id']) : null;
                abort_if($bankAccount && strtoupper($bankAccount->currency) !== strtoupper($lockedInvoice->currency), 422, 'Bank account currency must match invoice currency.');
                $baseSnapshot = $fxRates->snapshot($lockedInvoice->org_id, $lockedInvoice->currency, $validated['payment_date'], ['amount' => $amount]);
                $invoiceBaseBalance = (float) $lockedInvoice->base_balance_due ?: (float) $lockedInvoice->balance_due;
                $invoiceBaseAmount = $balanceDue > 0
                    ? round($amount * ($invoiceBaseBalance / $balanceDue), 2)
                    : 0.0;

                $payment = Payment::create([
                    'org_id' => $lockedInvoice->org_id,
                    'invoice_id' => $lockedInvoice->id,
                    'bank_account_id' => $validated['bank_account_id'] ?? null,
                    'entry_type' => 'receipt',
                    'amount' => $amount,
                    'currency' => $lockedInvoice->currency,
                    'base_currency' => $baseSnapshot['base_currency'],
                    'exchange_rate' => $baseSnapshot['exchange_rate'],
                    'base_amount' => $baseSnapshot['base_amount'],
                    'invoice_base_amount' => $invoiceBaseAmount,
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'reference_no' => $validated['reference_no'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $request->user()->id,
                ]);

                if ($request->hasFile('attachment')) {
                    $file = $files->store($request, $request->file('attachment'), 'payment', $payment->id, 'receipt');
                    $payment->update(['attachment_file_id' => $file->id]);
                }

                $this->recalculateInvoicePaymentState($lockedInvoice, $request->user()->id);
                $journals->postPayment($payment, $request->user()->id);
                $this->audit($request, 'payment.receipt', $payment, null, $this->paymentSnapshot($payment, $lockedInvoice));

                return $payment;
            });
        } catch (QueryException $exception) {
            $this->throwValidationIfUniqueViolation($exception);
        }

        return back()->with('success', "Payment receipt {$payment->amount} recorded.");
    }

    public function reverse(Request $request, Payment $payment, FinancialJournalService $journals): RedirectResponse
    {
        abort_unless($payment->org_id === $request->user()->org_id, 403);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.FileAttachmentManager::MAX_KILOBYTES],
        ]);

        $idempotencyKey = $this->idempotencyKey($request, $validated);
        $existing = $this->existingIdempotentPayment($request, $idempotencyKey);

        if ($existing) {
            $this->assertReversalIdempotencyMatch($existing, $payment);

            return back()->with('success', 'Payment reversal already recorded.');
        }

        try {
            $reversal = DB::transaction(function () use ($request, $payment, $validated, $idempotencyKey, $journals): Payment {
                $receipt = Payment::where('id', $payment->id)
                    ->where('org_id', $request->user()->org_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($receipt->entry_type === 'receipt', 422, 'Only receipt payments can be reversed.');
                abort_if($receipt->reversals()->exists(), 422, 'Payment receipt has already been reversed.');

                $existing = $this->existingIdempotentPayment($request, $idempotencyKey);

                if ($existing) {
                    $this->assertReversalIdempotencyMatch($existing, $receipt);

                    return $existing;
                }

                $lockedInvoice = $this->lockInvoice($request, $receipt->invoice_id);

                $reversal = Payment::create([
                    'org_id' => $receipt->org_id,
                    'invoice_id' => $receipt->invoice_id,
                    'bank_account_id' => $receipt->bank_account_id,
                    'entry_type' => 'reversal',
                    'reversal_of_payment_id' => $receipt->id,
                    'amount' => $receipt->amount,
                    'currency' => $receipt->currency,
                    'base_currency' => $receipt->base_currency,
                    'exchange_rate' => $receipt->exchange_rate,
                    'base_amount' => $receipt->base_amount,
                    'invoice_base_amount' => $receipt->invoice_base_amount,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => $receipt->payment_method,
                    'reference_no' => $receipt->reference_no,
                    'note' => $validated['note'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $request->user()->id,
                ]);

                $this->recalculateInvoicePaymentState($lockedInvoice, $request->user()->id);
                $journals->postPayment($reversal, $request->user()->id);
                $this->audit($request, 'payment.reversal', $reversal, $this->paymentSnapshot($receipt, $lockedInvoice), $this->paymentSnapshot($reversal, $lockedInvoice));

                return $reversal;
            });
        } catch (QueryException $exception) {
            $this->throwValidationIfUniqueViolation($exception);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return back()->with('success', "Payment reversal {$reversal->amount} recorded.");
    }

    private function assertReceiptIdempotencyMatch(Payment $existing, Invoice $invoice, array $validated): void
    {
        $matches = $existing->entry_type === 'receipt'
            && $existing->invoice_id === $invoice->id
            && round((float) $existing->amount, 2) === round((float) $validated['amount'], 2)
            && $existing->payment_date->toDateString() === $validated['payment_date']
            && $existing->payment_method === $validated['payment_method']
            && $existing->bank_account_id === ($validated['bank_account_id'] ?? null)
            && (string) ($existing->reference_no ?? '') === (string) ($validated['reference_no'] ?? '');

        if (! $matches) {
            abort(422, 'Idempotency-Key has already been used for a different payment payload.');
        }
    }

    private function assertReversalIdempotencyMatch(Payment $existing, Payment $receipt): void
    {
        $matches = $existing->entry_type === 'reversal'
            && $existing->reversal_of_payment_id === $receipt->id
            && $existing->invoice_id === $receipt->invoice_id
            && round((float) $existing->amount, 2) === round((float) $receipt->amount, 2);

        if (! $matches) {
            abort(422, 'Idempotency-Key has already been used for a different payment payload.');
        }
    }

    private function idempotencyKey(Request $request, array $validated): string
    {
        $headerIdempotencyKey = $request->header('Idempotency-Key');
        abort_if(is_string($headerIdempotencyKey) && strlen($headerIdempotencyKey) > 100, 422, 'Idempotency-Key must not be greater than 100 characters.');

        return $headerIdempotencyKey
            ?: ($validated['idempotency_key'] ?? null)
            ?: (string) Str::uuid();
    }

    private function existingIdempotentPayment(Request $request, string $idempotencyKey): ?Payment
    {
        return Payment::where('org_id', $request->user()->org_id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function lockInvoice(Request $request, string $invoiceId): Invoice
    {
        return Invoice::where('id', $invoiceId)
            ->where('org_id', $request->user()->org_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recalculateInvoicePaymentState(Invoice $invoice, string $actorId): void
    {
        $paidAmount = (float) Payment::where('invoice_id', $invoice->id)
            ->where('entry_type', 'receipt')
            ->sum('amount');
        $reversedAmount = (float) Payment::where('invoice_id', $invoice->id)
            ->where('entry_type', 'reversal')
            ->sum('amount');
        $netPaid = round($paidAmount - $reversedAmount, 2);
        $newBalance = max(0, round((float) $invoice->total - $netPaid, 2));
        $basePaid = round((float) Payment::where('invoice_id', $invoice->id)->where('entry_type', 'receipt')->sum('invoice_base_amount') - (float) Payment::where('invoice_id', $invoice->id)->where('entry_type', 'reversal')->sum('invoice_base_amount'), 2);
        $baseTotal = (float) $invoice->base_total ?: (float) $invoice->total;
        $baseBalance = max(0, round($baseTotal - $basePaid, 2));

        $isPastDue = $invoice->due_date && $invoice->due_date->isPast() && ! $invoice->due_date->isToday();

        $invoice->update([
            'paid_amount' => $netPaid,
            'balance_due' => $newBalance,
            'base_paid_amount' => $basePaid,
            'base_balance_due' => $baseBalance,
            'status' => $newBalance <= 0 ? 'paid' : ($isPastDue ? 'overdue' : ($netPaid > 0 ? 'partially_paid' : 'sent')),
            'paid_at' => $newBalance <= 0 ? now() : null,
            'updated_by' => $actorId,
        ]);
    }

    private function throwValidationIfUniqueViolation(QueryException $exception): never
    {
        if (! $this->isUniqueConstraintViolation($exception)) {
            throw $exception;
        }

        throw ValidationException::withMessages([
            'idempotency_key' => 'Idempotency-Key or reversal target has already been used.',
        ]);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)
            || in_array((int) ($exception->errorInfo[1] ?? 0), [1062, 19, 2067], true);
    }

    private function paymentSnapshot(Payment $payment, Invoice $invoice): array
    {
        $payment->refresh();
        $invoice->refresh();

        return [
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'payment_id' => $payment->id,
            'entry_type' => $payment->entry_type,
            'reversal_of_payment_id' => $payment->reversal_of_payment_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'base_amount' => $payment->base_amount,
            'invoice_base_amount' => $payment->invoice_base_amount,
            'payment_date' => $payment->payment_date,
            'payment_method' => $payment->payment_method,
            'bank_account_id' => $payment->bank_account_id,
            'reference_no' => $payment->reference_no,
            'attachment_file_id' => $payment->attachment_file_id,
            'invoice_paid_amount' => $invoice->paid_amount,
            'invoice_balance_due' => $invoice->balance_due,
            'invoice_base_balance_due' => $invoice->base_balance_due,
            'invoice_status' => $invoice->status,
        ];
    }

    private function audit(Request $request, string $action, Payment $payment, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $payment->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
