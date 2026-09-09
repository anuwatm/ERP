<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\GatewayTransaction;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\WebhookEvent;
use App\Services\GatewaySettlementService;
use App\Services\OpnEventService;
use App\Services\PortalAccessService;
use App\Services\PromptPayQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GatewayController extends Controller
{
    public function settings(Request $request)
    {
        $config = PaymentGatewayConfig::where('org_id', $request->user()->org_id)->first();

        return Inertia::render('Settings/PaymentGateway', [
            'config' => $config?->only(['id', 'enabled', 'qr_type', 'bank_account_id', 'provider', 'livemode']),
            'banks' => BankAccount::where('org_id', $request->user()->org_id)->where('currency', 'THB')->where('status', 'active')->get(['id', 'bank_name', 'account_name']),
            'transactions' => GatewayTransaction::where('org_id', $request->user()->org_id)->latest()->limit(50)->get(['id', 'reference', 'amount_minor', 'currency', 'status', 'expires_at']),
            'events' => $config ? WebhookEvent::where('payment_gateway_config_id', $config->id)->latest()->limit(50)->get(['id', 'event_id', 'status', 'reason', 'created_at']) : [],
        ]);
    }

    public function configure(Request $request)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'qr_type' => ['required', 'in:biller,tax_id'], 'recipient_id' => ['nullable', 'string', 'regex:/^[0-9]{13,15}$/'], 'webhook_secret' => ['nullable', 'string', 'min:32', 'max:255'], 'bank_account_id' => ['required', 'uuid']]);
        BankAccount::whereKey($data['bank_account_id'])->where('org_id', $request->user()->org_id)->where('status', 'active')->where('currency', 'THB')->firstOrFail();
        $data += $request->validate(['provider' => ['sometimes', 'in:settlement_hmac,opn'], 'provider_secret' => ['nullable', 'string', 'max:255'], 'livemode' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $data) {
            $config = PaymentGatewayConfig::where('org_id', $request->user()->org_id)->lockForUpdate()->first();
            $recipient = ($data['recipient_id'] ?? null) ?: $config?->recipient_id;
            $secret = ($data['webhook_secret'] ?? null) ?: $config?->webhook_secret;
            $provider = $data['provider'] ?? $config?->provider ?? 'settlement_hmac';
            $providerSecret = ($data['provider_secret'] ?? null) ?: $config?->provider_secret;
            $livemode = $data['livemode'] ?? $config?->livemode ?? false;
            if ($provider === 'opn' && $data['enabled'] && (! $providerSecret || ! str_starts_with($providerSecret, $livemode ? 'skey_live_' : 'skey_test_'))) {
                throw ValidationException::withMessages(['provider_secret' => 'An Opn secret key matching test/live mode is required.']);
            }
            if ($config && ($provider !== $config->provider || $livemode !== $config->livemode || $providerSecret !== $config->provider_secret) && GatewayTransaction::where('payment_gateway_config_id', $config->id)->whereIn('status', ['pending', 'creating', 'creation_unknown'])->exists()) {
                throw ValidationException::withMessages(['provider' => 'Resolve pending transactions before changing provider or mode.']);
            }
            if (! $recipient || ! $secret) {
                throw ValidationException::withMessages(['recipient_id' => 'Recipient and signing secret are required.']);
            }
            try {
                app(PromptPayQrService::class)->payload($data['qr_type'], $recipient, 100, 'VALIDATE');
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['recipient_id' => 'Recipient length must match the selected QR type.']);
            }
            // Existing QR intents retain their bank and recipient snapshot; expire before changing receiver.
            if ($config && GatewayTransaction::where('payment_gateway_config_id', $config->id)->where(function ($query) {
                $query->whereIn('status', ['creating', 'creation_unknown'])->orWhere(function ($pending) {
                    $pending->where('status', 'pending')->where('expires_at', '>', now());
                });
            })->exists() && ($recipient !== $config->recipient_id || $data['bank_account_id'] !== $config->bank_account_id || $data['qr_type'] !== $config->qr_type)) {
                throw ValidationException::withMessages(['recipient_id' => 'Wait for active QR intents to expire before changing the recipient.']);
            }
            $config = PaymentGatewayConfig::updateOrCreate(['org_id' => $request->user()->org_id], ['provider' => $provider, 'provider_secret' => $providerSecret, 'livemode' => $livemode, 'enabled' => $data['enabled'], 'qr_type' => $data['qr_type'], 'recipient_id' => $recipient, 'webhook_secret' => $secret, 'bank_account_id' => $data['bank_account_id']]);
            AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'gateway.configured', 'entity_type' => 'payment_gateway_config', 'entity_id' => $config->id, 'after_json' => ['enabled' => $config->enabled, 'bank_account_id' => $config->bank_account_id]]);
        });

        return back()->with('success', 'Payment settings saved.');
    }

    public function qr(Request $request, Invoice $invoice, GatewaySettlementService $gateway, PortalAccessService $portal)
    {
        $user = $portal->user($request);
        $staff = $request->user();
        abort_unless(($user && $user->party_type === 'customer' && $user->org_id === $invoice->org_id && $user->party_id === $invoice->customer_id) || ($staff && $staff->hasVerifiedEmail() && $staff->org_id === $invoice->org_id && $staff->hasPermissionCode('invoices.view')), 404);
        $tx = $gateway->intent($invoice);
        abort_unless($tx, 404);

        return response()->view('documents.payment-qr', ['transaction' => $tx, 'image' => $gateway->image($tx), 'invoice' => $invoice])->header('Cache-Control', 'private, no-store');
    }

    public function webhook(Request $request, string $config, GatewaySettlementService $gateway)
    {
        return response()->json($gateway->receive($request, $config));
    }

    public function opn(Request $request, string $config, OpnEventService $opn)
    {
        return response()->json($opn->receive($request, $config));
    }
}
