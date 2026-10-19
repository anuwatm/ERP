<?php

namespace App\Services;

use App\Models\IntegrationApiKey;
use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookDelivery;
use App\Models\IntegrationWebhookEvent;
use App\Models\Product;
use App\Support\ExternalApprovalAccess;
use App\Support\ExternalBusinessAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExternalWebhookService
{
    public const EVENTS = ['invoice.payment.status.changed' => 'invoices:read', 'stock.balance.changed' => 'inventory:read',
        'task.status.changed' => 'tasks:read', 'approval.status.changed' => 'approvals:read'];

    public function __construct(private readonly WebhookDestinationResolver $destinations) {}

    public function record(string $org, string $type, string $resource, string $id): void
    {
        if (! config('external_api.webhooks_enabled') || ! isset(self::EVENTS[$type])) {
            return;
        }
        $endpoints = IntegrationWebhook::where('org_id', $org)->where('status', 'active')->whereNotNull('verified_at')->whereJsonContains('events', $type)->get(['id']);
        if ($endpoints->isEmpty()) {
            return;
        }
        $eventId = (string) Str::orderedUuid();
        $payload = ['id' => $eventId, 'schema_version' => 1, 'event_type' => $type, 'occurred_at' => now()->toIso8601String(),
            'resource_type' => $resource, 'resource_id' => $id];
        $event = IntegrationWebhookEvent::create(['id' => $eventId, 'org_id' => $org, 'event_type' => $type,
            'resource_type' => $resource, 'resource_id' => $id, 'payload' => $payload]);
        foreach ($endpoints as $endpoint) {
            IntegrationWebhookDelivery::firstOrCreate(['integration_webhook_id' => $endpoint->id, 'integration_webhook_event_id' => $event->id], ['available_at' => now()]);
        }
    }

    public function verify(IntegrationWebhook $endpoint): bool
    {
        $challenge = bin2hex(random_bytes(32));
        try {
            $response = $this->send($endpoint, ['type' => 'endpoint.verification', 'schema_version' => 1, 'challenge' => $challenge]);
            if (! $response->successful() || strlen($response->body()) > 4096) {
                return false;
            }
            $data = $response->json();
            if (! is_array($data) || ($data['challenge'] ?? null) !== $challenge || ! is_string($data['signature'] ?? null)
                || ! hash_equals(hash_hmac('sha256', 'verify:'.$challenge, $endpoint->signing_secret), $data['signature'])) {
                return false;
            }
            $endpoint->update(['verified_at' => now(), 'status' => 'active']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function dispatch(int $limit = 20): int
    {
        if (! config('external_api.webhooks_enabled')) {
            return 0;
        }
        $processed = 0;
        for ($i = 0; $i < min(100, max(1, $limit)); $i++) {
            $delivery = DB::transaction(function () {
                $row = IntegrationWebhookDelivery::where(function ($q) {
                    $q->whereIn('status', ['pending', 'retry'])->orWhere(fn ($expired) => $expired->where('status', 'processing')->where('locked_until', '<=', now()));
                })->where('available_at', '<=', now())->whereHas('webhook', fn ($q) => $q->where('status', 'active')->whereNotNull('verified_at'))
                    ->orderBy('available_at')->orderBy('id')->lockForUpdate()->first();
                if (! $row) {
                    return null;
                }
                if ($row->attempts >= 5) {
                    $row->update(['status' => 'dead_letter', 'completed_at' => now(), 'locked_until' => null, 'lease_token' => null]);

                    return $row;
                }
                $row->update(['status' => 'processing', 'attempts' => $row->attempts + 1, 'locked_until' => now()->addSeconds(30), 'lease_token' => (string) Str::uuid()]);

                return $row;
            });
            if (! $delivery) {
                break;
            }
            $processed++;
            if ($delivery->status === 'dead_letter') {
                continue;
            }
            $this->deliver($delivery);
        }

        return $processed;
    }

    private function deliver(IntegrationWebhookDelivery $delivery): void
    {
        $endpoint = $delivery->webhook;
        $event = $delivery->event;
        $key = $endpoint?->key;
        $result = ['locked_until' => null, 'lease_token' => null, 'last_error' => null];
        if (! $endpoint || ! $event || ! $key || ! $this->mayDeliver($endpoint, $event, $key)) {
            $result += ['status' => 'skipped_no_access', 'completed_at' => now()];
        } else {
            $status = null;
            $retryAfter = null;
            try {
                $response = $this->send($endpoint, $event->payload);
                $status = $response->status();
                $retryAfter = $response->header('Retry-After');
            } catch (\Throwable) {
                $result['last_error'] = 'transport_error';
            }
            $result['last_http_status'] = $status;
            if ($status !== null && $status >= 200 && $status < 300) {
                $result += ['status' => 'delivered', 'completed_at' => now()];
            } elseif ($delivery->attempts >= 5 || ($status !== null && $status < 500 && $status !== 429)) {
                $result += ['status' => 'dead_letter', 'completed_at' => now()];
            } else {
                $delay = min(900, 30 * 2 ** ($delivery->attempts - 1));
                if (is_string($retryAfter) && $retryAfter !== '') {
                    $delay = ctype_digit($retryAfter) ? (int) $retryAfter : max(1, (int) (strtotime($retryAfter) - now()->timestamp));
                }
                if ($delay > 86400) {
                    $result += ['status' => 'dead_letter', 'completed_at' => now()];
                } else {
                    $result += ['status' => 'retry', 'available_at' => now()->addSeconds(max(1, $delay))];
                }
            }
        }
        IntegrationWebhookDelivery::whereKey($delivery->id)->where('lease_token', $delivery->lease_token)->update($result);
    }

    private function mayDeliver(IntegrationWebhook $endpoint, IntegrationWebhookEvent $event, IntegrationApiKey $key): bool
    {
        $scope = self::EVENTS[$event->event_type] ?? null;
        if (! $scope || $endpoint->status !== 'active' || ! $endpoint->verified_at || $endpoint->org_id !== $event->org_id || $key->org_id !== $event->org_id || $key->revoked_at || $key->expires_at->lte(now())
            || $key->organization?->status !== 'active' || ! in_array($scope, $key->scopes, true) || ! $key->allowsPrincipalScope($scope)
            || ! in_array($event->event_type, $endpoint->events, true)) {
            return false;
        }
        $request = Request::create('/api/v1/webhook-visibility');
        $request->attributes->set('integration_key', $key);
        try {
            return match ($event->resource_type) {
                'invoice' => ExternalBusinessAccess::query($request, 'invoices', 'invoices:read')->whereKey($event->resource_id)->exists(),
                'task' => ExternalBusinessAccess::query($request, 'tasks', 'tasks:read')->whereKey($event->resource_id)->exists(),
                'workflow_instance' => ExternalApprovalAccess::query($request)->where('workflow_instance_id', $event->resource_id)->exists(),
                'product' => (bool) ExternalBusinessAccess::issuer($request, 'inventory:read')
                    && Product::where('org_id', $key->org_id)->whereKey($event->resource_id)->exists(),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function send(IntegrationWebhook $endpoint, array $payload)
    {
        if (! extension_loaded('curl')) {
            throw new \RuntimeException('transport_unavailable');
        }
        $target = $this->destinations->resolve($endpoint->url);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];

        return Http::withoutRedirecting()->connectTimeout(2)->timeout(5)->withOptions([
            'on_headers' => function ($response) {
                if ((int) $response->getHeaderLine('Content-Length') > 8192) {
                    throw new \RuntimeException('response_too_large');
                }
            },
            'progress' => function ($total, $downloaded) {
                if ($downloaded > 8192) {
                    throw new \RuntimeException('response_too_large');
                }
            },
            'verify' => true, 'curl' => [CURLOPT_RESOLVE => [$target['host'].':443:'.$ip], CURLOPT_PROXY => '',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS],
        ])->withHeaders(['X-ERP-Event-Id' => $payload['id'] ?? 'verification',
            'X-ERP-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->signing_secret)])
            ->withBody($body, 'application/json')->post($target['url']);
    }
}
