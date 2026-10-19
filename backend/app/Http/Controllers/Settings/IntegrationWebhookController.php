<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IntegrationApiKey;
use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookDelivery;
use App\Models\Organization;
use App\Services\ExternalWebhookService;
use App\Services\WebhookDestinationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class IntegrationWebhookController extends Controller
{
    private const FIELDS = ['id', 'name', 'url', 'integration_api_key_id', 'events', 'status', 'verified_at', 'created_at'];

    public function index(Request $request)
    {
        abort_unless($request->user()->status === 'active', 403);
        $enabled = (bool) config('external_api.webhooks_enabled');
        $org = $request->user()->org_id;

        return Inertia::render('Settings/ApiWebhooks', [
            'enabled' => $enabled,
            'endpoints' => $enabled ? IntegrationWebhook::where('org_id', $org)->latest()->get()->map(fn ($row) => $row->only(self::FIELDS)) : [],
            'keys' => $enabled ? IntegrationApiKey::where('org_id', $org)->whereNull('revoked_at')->where('expires_at', '>', now())->get(['id', 'name', 'prefix', 'scopes', 'access_policy'])
                ->map(fn ($key) => ['id' => $key->id, 'name' => $key->name, 'prefix' => $key->prefix,
                    'events' => array_keys(array_filter(ExternalWebhookService::EVENTS, fn ($scope) => in_array($scope, $key->scopes, true) && $key->allowsPrincipalScope($scope)))]) : [],
            'deliveries' => $enabled ? IntegrationWebhookDelivery::whereHas('webhook', fn ($q) => $q->where('org_id', $org))->latest()->limit(50)
                ->get(['id', 'integration_webhook_id', 'integration_webhook_event_id', 'status', 'attempts', 'last_http_status', 'last_error', 'available_at', 'completed_at']) : [],
        ]);
    }

    public function store(Request $request, WebhookDestinationResolver $resolver)
    {
        $this->enabled($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'url' => ['required', 'url:https', 'max:2048'],
            'integration_api_key_id' => ['required', 'uuid'], 'events' => ['required', 'array', 'min:1', 'max:4'],
            'events.*' => ['required', 'distinct', Rule::in(array_keys(ExternalWebhookService::EVENTS))], 'org_id' => ['prohibited']]);
        $key = IntegrationApiKey::where('org_id', $request->user()->org_id)->whereNull('revoked_at')->where('expires_at', '>', now())->whereKey($data['integration_api_key_id'])->firstOrFail();
        foreach ($data['events'] as $event) {
            $scope = ExternalWebhookService::EVENTS[$event];
            if (! in_array($scope, $key->scopes, true) || ! $key->allowsPrincipalScope($scope)) {
                throw ValidationException::withMessages(['events' => 'The key does not allow these events.']);
            }
        }
        try {
            $target = $resolver->resolve($data['url']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['url' => 'Use a public HTTPS endpoint on port 443 without credentials, query or fragment.']);
        }
        $secret = bin2hex(random_bytes(32));
        $endpoint = DB::transaction(function () use ($data, $target, $request, $secret) {
            Organization::whereKey($request->user()->org_id)->lockForUpdate()->firstOrFail();
            if (IntegrationWebhook::where('org_id', $request->user()->org_id)->count() >= 10) {
                throw ValidationException::withMessages(['name' => 'The organization has reached its webhook endpoint limit.']);
            }
            $endpoint = IntegrationWebhook::create(['org_id' => $request->user()->org_id, 'created_by' => $request->user()->id,
                'name' => $data['name'], 'url' => $target['url'], 'integration_api_key_id' => $data['integration_api_key_id'],
                'events' => $data['events'], 'signing_secret' => $secret, 'status' => 'paused']);
            $this->audit($request, $endpoint, 'create');

            return $endpoint;
        });

        return response()->json(['endpoint' => $endpoint->only(self::FIELDS), 'secret' => $secret], 201)->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request, IntegrationWebhook $webhook, ExternalWebhookService $service)
    {
        $this->owned($request, $webhook);
        if (! $service->verify($webhook)) {
            return response()->json(['message' => 'Endpoint verification failed. Check the receiver and signing secret.'], 422)->header('Cache-Control', 'no-store, private');
        }
        $this->audit($request, $webhook, 'verify');

        return response()->json(['endpoint' => $webhook->fresh()->only(self::FIELDS)])->header('Cache-Control', 'no-store, private');
    }

    public function pause(Request $request, IntegrationWebhook $webhook)
    {
        $this->owned($request, $webhook);
        $webhook->update(['status' => 'paused']);
        $this->audit($request, $webhook, 'pause');

        return response()->json(['endpoint' => $webhook->only(self::FIELDS)])->header('Cache-Control', 'no-store, private');
    }

    public function rotate(Request $request, IntegrationWebhook $webhook)
    {
        $this->owned($request, $webhook);
        $secret = bin2hex(random_bytes(32));
        $webhook->update(['signing_secret' => $secret, 'status' => 'paused', 'verified_at' => null]);
        $this->audit($request, $webhook, 'rotate');

        return response()->json(['endpoint' => $webhook->only(self::FIELDS), 'secret' => $secret])->header('Cache-Control', 'no-store, private');
    }

    private function enabled(Request $request): void
    {
        abort_unless($request->user()->status === 'active', 403);
        abort_unless(config('external_api.webhooks_enabled'), 503, 'External webhooks are disabled.');
    }

    private function owned(Request $request, IntegrationWebhook $webhook): void
    {
        $this->enabled($request);
        abort_unless($webhook->org_id === $request->user()->org_id, 404);
    }

    private function audit(Request $request, IntegrationWebhook $endpoint, string $action): void
    {
        AuditLog::create(['org_id' => $endpoint->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'api_webhook.'.$action,
            'entity_type' => 'integration_webhook', 'entity_id' => $endpoint->id,
            'after_json' => $endpoint->only(['id', 'integration_api_key_id', 'events', 'status', 'verified_at'])]);
    }
}
