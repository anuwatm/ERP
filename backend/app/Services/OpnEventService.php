<?php

namespace App\Services;

use App\Models\GatewayTransaction;
use App\Models\PaymentGatewayConfig;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class OpnEventService
{
    public function createCharge(GatewayTransaction $tx): ?GatewayTransaction
    {
        $config = PaymentGatewayConfig::findOrFail($tx->payment_gateway_config_id);
        try {
            $client = Http::withBasicAuth($config->provider_secret, '')->withHeaders(['Omise-Version' => '2019-05-29'])->timeout(15)->connectTimeout(5)->withoutRedirecting();
            $response = $client->asForm()->post('https://api.omise.co/charges', [
                'amount' => $tx->amount_minor, 'currency' => 'THB', 'source' => ['type' => 'promptpay'],
                'metadata' => ['erp_reference' => $tx->reference], 'expires_at' => $tx->expires_at->toIso8601String(),
            ]);
            abort_unless($response->successful(), 503);
            $charge = $response->json();
            $chargeId = $charge['id'] ?? '';
            abort_unless(is_string($chargeId) && preg_match('/^chrg_[a-zA-Z0-9_]{1,80}$/D', $chargeId) && ($charge['livemode'] ?? null) === $config->livemode && (int) ($charge['amount'] ?? 0) === $tx->amount_minor && strtoupper($charge['currency'] ?? '') === 'THB' && ($charge['metadata']['erp_reference'] ?? '') === $tx->reference, 422);
            GatewayTransaction::whereKey($tx->id)->whereNull('provider_charge_id')->update(['provider_charge_id' => $chargeId]);
            abort_unless($tx->refresh()->provider_charge_id === $chargeId, 409);
            $url = $charge['source']['scannable_code']['image']['download_uri'] ?? '';
            abort_unless(is_string($url) && preg_match('~^https://api\.omise\.co/charges/'.preg_quote($chargeId, '~').'/documents/docu_[A-Za-z0-9_]+/downloads/[A-Za-z0-9]+$~D', $url), 422);
            $image = $client->get($url);
            $svg = $image->body();
            abort_unless($image->successful() && strlen($svg) <= 262144 && ! str_contains(strtoupper($svg), '<!DOCTYPE') && ! str_contains(strtoupper($svg), '<!ENTITY'), 422);
            $document = new \DOMDocument;
            abort_unless(@$document->loadXML($svg, LIBXML_NONET) && $document->documentElement?->localName === 'svg', 422);
            abort_if((new \DOMXPath($document))->query('//processing-instruction()')->length > 0, 422);
            foreach ($document->getElementsByTagName('*') as $element) {
                abort_unless(in_array($element->localName, ['svg', 'g', 'path', 'rect', 'title', 'desc']), 422);
                foreach ($element->attributes as $attribute) {
                    abort_unless(in_array($attribute->name, ['xmlns', 'version', 'width', 'height', 'viewBox', 'x', 'y', 'd', 'fill', 'stroke', 'stroke-width', 'transform', 'shape-rendering', 'fill-rule']), 422);
                    abort_if(preg_match('/url\s*\(/i', $attribute->value), 422);
                }
            }
            $tx->update(['provider_qr_image' => 'data:image/svg+xml;base64,'.base64_encode($svg)]);
            GatewayTransaction::whereKey($tx->id)->where('status', 'creating')->whereNull('payment_id')->update(['status' => 'pending']);

            return $tx->refresh()->payment_id ? null : $tx;
        } catch (\Throwable) {
            // Never retry an ambiguous create: the provider might already have created a payable charge.
            GatewayTransaction::whereKey($tx->id)->where('status', 'creating')->whereNull('payment_id')->update(['status' => 'creation_unknown']);

            return null;
        }
    }

    public function receive(Request $request, string $configId): array
    {
        abort_if(strlen($request->getContent()) > 16384, 413);
        $input = $request->validate(['id' => ['required', 'string', 'regex:/^evnt_[a-zA-Z0-9_]{1,80}$/D']]);
        $config = PaymentGatewayConfig::whereKey($configId)->where('provider', 'opn')->where('enabled', true)->firstOrFail();
        abort_unless($config->provider_secret, 422);
        // Retrieve both resources with this merchant's credentials; never trust callback status or URLs.
        $client = Http::withBasicAuth($config->provider_secret, '')->acceptJson()->withHeaders(['Omise-Version' => '2019-05-29'])->timeout(10)->connectTimeout(5)->withoutRedirecting();
        $response = $client->get('https://api.omise.co/events/'.$input['id']);
        abort_unless($response->successful(), 503, 'Provider verification unavailable.');
        $event = $response->json();
        abort_unless(is_array($event) && ($event['id'] ?? null) === $input['id'] && ($event['livemode'] ?? null) === $config->livemode, 422);
        $chargeId = $event['data']['id'] ?? '';
        abort_unless(($event['key'] ?? '') === 'charge.complete' && is_string($chargeId) && preg_match('/^chrg_[a-zA-Z0-9_]{1,80}$/D', $chargeId), 422);
        $response = $client->get('https://api.omise.co/charges/'.$chargeId);
        abort_unless($response->successful(), 503, 'Provider charge verification unavailable.');
        $charge = $response->json();
        abort_unless(is_array($charge) && ($charge['id'] ?? null) === $chargeId && ($charge['livemode'] ?? null) === $config->livemode && ($charge['status'] ?? '') === 'successful' && ($charge['paid'] ?? false) === true && ($charge['source']['type'] ?? '') === 'promptpay', 422);
        Validator::make(['paid_at' => $charge['paid_at'] ?? null], ['paid_at' => ['required', 'date', 'before_or_equal:now']])->validate();

        return DB::transaction(function () use ($config, $event, $charge, $chargeId) {
            $locked = PaymentGatewayConfig::whereKey($config->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->enabled && $locked->provider === 'opn' && $locked->provider_secret === $config->provider_secret && $locked->livemode === $config->livemode, 409);
            $tx = GatewayTransaction::where('payment_gateway_config_id', $config->id)->where('org_id', $config->org_id)->where('reference', $charge['metadata']['erp_reference'] ?? '')->lockForUpdate()->firstOrFail();
            abort_unless((int) ($charge['amount'] ?? 0) === $tx->amount_minor && strtoupper($charge['currency'] ?? '') === $tx->currency && ! ($charge['refunded'] ?? false) && (int) ($charge['refunded_amount'] ?? 0) === 0, 422);
            abort_if($tx->provider_charge_id && $tx->provider_charge_id !== $chargeId, 409);
            $stored = WebhookEvent::firstOrCreate(['payment_gateway_config_id' => $config->id, 'event_id' => 'opn:'.$event['id']], ['payload_hash' => hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)), 'status' => 'provider_confirmed']);
            if (! $tx->provider_confirmed_at) {
                $tx->update(['provider_charge_id' => $chargeId, 'provider_confirmed_at' => now(), 'provider_paid_at' => Carbon::parse($charge['paid_at'])->setTimezone(config('app.timezone'))]);
            }

            // A successful charge is not evidence of final bank settlement. No receipt or journal here.
            return ['status' => $stored->status, 'duplicate' => ! $stored->wasRecentlyCreated];
        });
    }
}
