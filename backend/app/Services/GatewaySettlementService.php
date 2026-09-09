<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\GatewayTransaction;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentGatewayConfig;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GatewaySettlementService
{
    public function intent(Invoice $invoice): ?GatewayTransaction
    {
        $tx = DB::transaction(function () use ($invoice) {
            $config = PaymentGatewayConfig::where('org_id', $invoice->org_id)->where('enabled', true)->lockForUpdate()->first();
            if (! $config) {
                return null;
            }
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->currency !== 'THB' || ! in_array($invoice->status, ['sent', 'partially_paid', 'overdue']) || PromptPayQrService::minor($invoice->balance_due) < 1) {
                return null;
            }
            $amount = PromptPayQrService::minor($invoice->balance_due);
            if (GatewayTransaction::where('invoice_id', $invoice->id)->whereIn('status', ['creating', 'creation_unknown'])->exists()) {
                return null;
            }
            if ($amount > 999999999999 || ! BankAccount::whereKey($config->bank_account_id)->where('org_id', $config->org_id)->where('status', 'active')->where('currency', 'THB')->exists()) {
                return null;
            }
            $existing = GatewayTransaction::where('invoice_id', $invoice->id)->where('status', 'pending')->where('amount_minor', $amount)->where('expires_at', '>', now())->first();
            if ($existing) {
                return $existing;
            }
            $reference = strtoupper(Str::random(20));

            return GatewayTransaction::create([
                'org_id' => $invoice->org_id, 'payment_gateway_config_id' => $config->id,
                'invoice_id' => $invoice->id, 'bank_account_id' => $config->bank_account_id,
                'reference' => $reference, 'amount_minor' => $amount, 'currency' => 'THB',
                'qr_payload' => app(PromptPayQrService::class)->payload($config->qr_type, $config->recipient_id, $amount, $reference),
                'status' => $config->provider === 'opn' ? 'creating' : 'pending', 'expires_at' => now()->addMinutes(30),
            ]);
        });
        if ($tx?->status === 'creating') {
            return app(OpnEventService::class)->createCharge($tx);
        }

        return $tx;
    }

    public function image(GatewayTransaction $transaction): string
    {
        return $transaction->provider_qr_image ?: app(PromptPayQrService::class)->image($transaction->qr_payload);
    }

    public function receive(Request $request, string $configId): array
    {
        abort_if(strlen($request->getContent()) > 16384, 413);

        return DB::transaction(function () use ($request, $configId) {
            $config = PaymentGatewayConfig::whereKey($configId)->where('enabled', true)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($config->provider, ['settlement_hmac', 'opn']), 422);
            $timestamp = (string) $request->header('X-Settlement-Timestamp');
            $signature = (string) $request->header('X-Settlement-Signature');
            abort_unless(ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300, 401);
            $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $config->webhook_secret);
            abort_unless(strlen($config->webhook_secret) >= 32 && hash_equals($expected, $signature), 401);
            $data = Validator::make($request->json()->all(), [
                'event_id' => ['required', 'string', 'max:100'],
                'reference' => ['required', 'string', 'size:20'],
                'status' => ['required', 'in:pending,failed,settled'],
                'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999'],
                'currency' => ['required', 'in:THB'],
                'settled_at' => ['required_if:status,settled', 'nullable', 'date', 'before_or_equal:now'],
                'provider_charge_id' => ['nullable', 'string', 'max:100'],
            ])->validate();
            $hash = hash('sha256', $request->getContent());
            $event = WebhookEvent::where('payment_gateway_config_id', $config->id)->where('event_id', $data['event_id'])->first();
            if ($event) {
                abort_unless(hash_equals($event->payload_hash, $hash), 409);

                return ['status' => $event->status, 'duplicate' => true];
            }
            $event = WebhookEvent::create(['payment_gateway_config_id' => $config->id, 'event_id' => $data['event_id'], 'payload_hash' => $hash, 'status' => 'received']);
            $tx = GatewayTransaction::where('payment_gateway_config_id', $config->id)->where('org_id', $config->org_id)->where('reference', $data['reference'])->lockForUpdate()->first();
            if (! $tx || $tx->amount_minor !== (int) $data['amount_minor'] || $tx->currency !== $data['currency']) {
                $event->update(['status' => 'review', 'reason' => 'Transaction or amount mismatch']);

                return ['status' => 'review'];
            }
            if ($tx->payment_id || $data['status'] !== 'settled') {
                $event->update(['status' => 'ignored']);

                return ['status' => 'ignored'];
            }
            $invoice = Invoice::whereKey($tx->invoice_id)->where('org_id', $config->org_id)->lockForUpdate()->firstOrFail();
            if ($config->provider === 'opn' && ! $config->livemode) {
                $event->update(['status' => 'review', 'reason' => 'Test-mode provider payments cannot post accounting']);

                return ['status' => 'review'];
            }
            if ($config->provider === 'opn' && (! $tx->provider_charge_id || ! $tx->provider_confirmed_at || ! $tx->provider_paid_at || ($data['provider_charge_id'] ?? null) !== $tx->provider_charge_id)) {
                $event->update(['status' => 'review', 'reason' => 'Provider confirmation or charge identity missing']);

                return ['status' => 'review'];
            }
            $bank = BankAccount::whereKey($tx->bank_account_id)->where('org_id', $config->org_id)->where('status', 'active')->where('currency', 'THB')->first();
            $settledAt = Carbon::parse($data['settled_at'])->setTimezone(config('app.timezone'));
            // Provider payout can arrive days after a customer paid a still-valid QR.
            $paidAt = $config->provider === 'opn' ? Carbon::parse($tx->provider_paid_at) : $settledAt;
            if (! $bank || ! in_array($invoice->status, ['sent', 'partially_paid', 'overdue']) || $invoice->currency !== $tx->currency || PromptPayQrService::minor($invoice->balance_due) < $tx->amount_minor || $paidAt->lt($tx->created_at) || $paidAt->gt($tx->expires_at) || $settledAt->lt($paidAt) || ! JournalEntry::where('org_id', $config->org_id)->where('source_type', 'invoice')->where('source_id', $invoice->id)->where('status', 'posted')->exists()) {
                $event->update(['status' => 'review', 'reason' => 'Invoice, bank, issue journal or settlement window invalid']);

                return ['status' => 'review'];
            }
            $amount = $tx->amount_minor / 100;
            if ((float) $invoice->base_balance_due <= 0 || ! $invoice->base_currency) {
                $event->update(['status' => 'review', 'reason' => 'Invoice FX snapshot is missing']);

                return ['status' => 'review'];
            }
            if (AccountingPeriod::where('org_id', $invoice->org_id)->where('status', 'closed')->whereDate('start_date', '<=', $settledAt->toDateString())->whereDate('end_date', '>=', $settledAt->toDateString())->exists()) {
                throw ValidationException::withMessages(['settled_at' => 'Settlement accounting period is closed.']);
            }
            $fx = app(FxRateService::class)->snapshot($invoice->org_id, $invoice->currency, $settledAt->toDateString(), ['amount' => $amount]);
            if ($fx['base_currency'] !== $invoice->base_currency) {
                $event->update(['status' => 'review', 'reason' => 'Invoice and organization base currencies differ']);

                return ['status' => 'review'];
            }
            $invoiceBase = round($amount * (float) $invoice->base_balance_due / (float) $invoice->balance_due, 2);
            $payment = Payment::create([
                'org_id' => $invoice->org_id, 'invoice_id' => $invoice->id, 'bank_account_id' => $bank->id,
                'entry_type' => 'receipt', 'amount' => $amount, 'currency' => 'THB',
                'base_currency' => $fx['base_currency'], 'exchange_rate' => $fx['exchange_rate'], 'base_amount' => $fx['base_amount'], 'invoice_base_amount' => $invoiceBase,
                'payment_date' => $settledAt->toDateString(), 'payment_method' => 'promptpay', 'reference_no' => $tx->reference,
                'idempotency_key' => 'gateway:'.$tx->id,
            ]);
            app(FinancialJournalService::class)->postPayment($payment, null);
            $balance = (PromptPayQrService::minor($invoice->balance_due) - $tx->amount_minor) / 100;
            $invoice->update(['paid_amount' => (float) $invoice->paid_amount + $amount, 'balance_due' => $balance, 'base_paid_amount' => (float) $invoice->base_paid_amount + $invoiceBase, 'base_balance_due' => round((float) $invoice->base_balance_due - $invoiceBase, 2), 'status' => $balance === 0 ? 'paid' : ($invoice->due_date?->lt(today()) ? 'overdue' : 'partially_paid'), 'paid_at' => $balance === 0 ? $settledAt : null]);
            $tx->update(['payment_id' => $payment->id, 'status' => 'settled', 'settled_at' => $settledAt]);
            $event->update(['status' => 'processed']);
            AuditLog::create(['org_id' => $invoice->org_id, 'action' => 'gateway.settled', 'entity_type' => 'payment', 'entity_id' => $payment->id, 'after_json' => ['gateway_transaction_id' => $tx->id, 'webhook_event_id' => $event->id]]);

            return ['status' => 'processed'];
        }, 3);
    }
}
