<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\IntegrationApiKey;
use App\Models\Organization;
use App\Models\Supplier;
use App\Support\ExternalDocumentAccess;
use App\Support\SalesAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationApiKeyController extends Controller
{
    public function documents(Request $request): JsonResponse
    {
        abort_unless($request->user()->status === 'active' && $request->user()->hasPermissionCode('documents.view'), 403);
        $filters = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']]);
        $rows = ExternalDocumentAccess::forUser($request->user())->where(fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%')->orWhere('document_no', 'like', '%'.$filters['search'].'%'))
            ->orderBy('document_no')->limit(20)->get(['id', 'document_no', 'title']);

        return response()->json(['data' => $rows])->header('Cache-Control', 'no-store, private');
    }

    public function parties(Request $request): JsonResponse
    {
        abort_unless($request->user()->status === 'active', 403);
        $filters = $request->validate(['type' => ['required', Rule::in(['customer', 'supplier'])], 'search' => ['required', 'string', 'min:2', 'max:100']]);
        $scopes = IntegrationApiKey::grantableScopes($request->user());
        abort_unless(array_intersect($scopes, IntegrationApiKey::PARTY_SCOPES[$filters['type']]), 403);
        $query = $filters['type'] === 'customer'
            ? SalesAccess::scopeCustomers(Customer::query(), $request->user())
            : Supplier::where('org_id', $request->user()->org_id);
        $name = $filters['type'] === 'customer' ? 'company_name' : 'name';
        $code = $filters['type'] === 'customer' ? 'customer_code' : 'supplier_code';
        $query->where(fn ($q) => $q->where($name, 'like', '%'.$filters['search'].'%')->orWhere($code, 'like', '%'.$filters['search'].'%'));

        return response()->json(['data' => $query->orderBy($code)->limit(20)->get(['id', $name, $code])->map(fn ($row) => [
            'id' => $row->id, 'name' => $row->$name, 'code' => $row->$code,
        ])])->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->status === 'active', 403);

        return Inertia::render('Settings/ApiAccess', [
            'keys' => IntegrationApiKey::where('org_id', $request->user()->org_id)->latest()->get()->map(fn ($key) => $this->metadata($key)),
            'availableScopes' => IntegrationApiKey::grantableScopes($request->user()),
            'hrBranches' => $request->user()->hasPermissionCode('hr.employees.manage')
                ? Branch::where('org_id', $request->user()->org_id)->orderBy('code')->get(['id', 'code', 'name']) : [],
            'defaultLifetimeDays' => config('external_api.key_lifetime_days'),
            'maxLifetimeDays' => config('external_api.max_lifetime_days'),
            'maxActiveKeys' => config('external_api.max_active_keys'),
            'apiBaseUrl' => url('/api/v1/summaries'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->status === 'active', 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1', 'max:'.count(IntegrationApiKey::SCOPES)],
            'scopes.*' => ['required', 'string', 'distinct', Rule::in(IntegrationApiKey::grantableScopes($request->user()))],
            'lifetime_days' => ['sometimes', 'integer', 'min:1', 'max:'.config('external_api.max_lifetime_days')],
            'org_id' => ['prohibited'],
            'access_policy' => ['sometimes', 'array:hr_all_branches,hr_branch_ids,principal,party_id,document_ids'],
            'access_policy.document_ids' => ['sometimes', 'array', 'max:100'],
            'access_policy.document_ids.*' => ['uuid', 'distinct'],
            'access_policy.principal' => ['sometimes', Rule::in(['organization', 'customer', 'supplier'])],
            'access_policy.party_id' => ['sometimes', 'nullable', 'uuid'],
            'access_policy.hr_all_branches' => ['sometimes', 'boolean'],
            'access_policy.hr_branch_ids' => ['sometimes', 'array', 'max:100'],
            'access_policy.hr_branch_ids.*' => ['uuid', 'distinct', Rule::exists('branches', 'id')->where('org_id', $request->user()->org_id)->whereNull('deleted_at')],
        ]);
        foreach (['products:prices:read' => 'products:read', 'products:costs:read' => 'products:read',
            'manufacturing:formulas:read' => 'manufacturing:boms:read', 'documents:download' => 'documents:read'] as $extra => $base) {
            if (in_array($extra, $data['scopes'], true) && ! in_array($base, $data['scopes'], true)) {
                throw ValidationException::withMessages(['scopes' => 'Additional data scopes require their basic resource scope.']);
            }
        }
        if (array_intersect($data['scopes'], ['documents:read', 'documents:download'])) {
            $ids = $data['access_policy']['document_ids'] ?? [];
            if (! $ids || ExternalDocumentAccess::forUser($request->user())->whereIn('documents.id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['access_policy' => 'Select permitted, non-confidential documents explicitly.']);
            }
        }
        $principal = $data['access_policy']['principal'] ?? 'organization';
        if ($principal !== 'organization') {
            $partyId = $data['access_policy']['party_id'] ?? null;
            $party = $principal === 'customer'
                ? SalesAccess::scopeCustomers(Customer::query(), $request->user())->whereKey($partyId)->exists()
                : Supplier::where('org_id', $request->user()->org_id)->whereKey($partyId)->exists();
            if (! $party || array_diff($data['scopes'], IntegrationApiKey::PARTY_SCOPES[$principal])) {
                throw ValidationException::withMessages(['access_policy' => 'Choose an accessible party and only its permitted scopes.']);
            }
        } elseif (! empty($data['access_policy']['party_id'])) {
            throw ValidationException::withMessages(['access_policy' => 'Organization credentials must not contain a party ID.']);
        }
        $hasHrScope = collect($data['scopes'])->contains(fn ($scope) => str_starts_with($scope, 'employees:') || in_array($scope, ['summary:hr:read', 'summary:attendance:read', 'summary:leaves:read', 'summary:roster:read'], true));
        if ($hasHrScope && ! ($data['access_policy']['hr_all_branches'] ?? false) && empty($data['access_policy']['hr_branch_ids'])) {
            throw ValidationException::withMessages(['access_policy' => 'Choose all branches explicitly or select permitted HR branches.']);
        }
        $result = DB::transaction(function () use ($request, $data) {
            $organization = Organization::whereKey($request->user()->org_id)->lockForUpdate()->firstOrFail();
            abort_unless($organization->status === 'active', 403);
            $count = IntegrationApiKey::where('org_id', $organization->id)->whereNull('revoked_at')->where('expires_at', '>', now())->count();
            if ($count >= config('external_api.max_active_keys')) {
                throw ValidationException::withMessages(['name' => 'The organization has reached its active key limit. Revoke an unused key first.']);
            }
            $prefix = 'erp_'.bin2hex(random_bytes(12));
            $secret = $prefix.'.'.bin2hex(random_bytes(32));
            $key = IntegrationApiKey::create([
                'org_id' => $organization->id, 'name' => $data['name'], 'prefix' => $prefix,
                'secret_hash' => hash('sha256', $secret), 'scopes' => $data['scopes'],
                'access_policy' => $data['access_policy'] ?? null,
                'created_by' => $request->user()->id,
                'expires_at' => now()->addDays($data['lifetime_days'] ?? config('external_api.key_lifetime_days')),
            ]);
            $this->audit($request, 'api_key.create', $key);

            return ['key' => $this->metadata($key), 'secret' => $secret];
        });

        // Deliberately JSON-only: never persist the one-time secret in session flash/Inertia history.
        return response()->json($result, 201)->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, IntegrationApiKey $apiKey): JsonResponse
    {
        $this->assertOwner($request, $apiKey);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'expires_at' => ['bail', 'required', 'string', 'date_format:Y-m-d\TH:i:sP', 'after:now', 'before_or_equal:'.now()->addDays(config('external_api.max_lifetime_days'))->toIso8601String()],
            'scopes' => ['prohibited'], 'org_id' => ['prohibited'], 'access_policy' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $apiKey, $data) {
            $key = IntegrationApiKey::whereKey($apiKey->id)->lockForUpdate()->firstOrFail();
            abort_if($key->revoked_at || $key->expires_at->lte(now()), 422, 'Issue a new key instead of reactivating an expired or revoked key.');
            $before = $this->metadata($key);
            $key->update(['name' => $data['name'], 'expires_at' => CarbonImmutable::parse($data['expires_at'])->setTimezone(config('app.timezone'))]);
            $this->audit($request, 'api_key.update', $key, $before);
        });

        return response()->json(['key' => $this->metadata($apiKey->fresh())])->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request, IntegrationApiKey $apiKey): JsonResponse
    {
        $this->assertOwner($request, $apiKey);
        DB::transaction(function () use ($request, $apiKey) {
            $key = IntegrationApiKey::whereKey($apiKey->id)->lockForUpdate()->firstOrFail();
            if (! $key->revoked_at) {
                $before = $this->metadata($key);
                $key->update(['revoked_at' => now()]);
                $this->audit($request, 'api_key.revoke', $key, $before);
            }
        });

        return response()->json(['key' => $this->metadata($apiKey->fresh())])->header('Cache-Control', 'no-store, private');
    }

    private function assertOwner(Request $request, IntegrationApiKey $key): void
    {
        abort_unless($request->user()->status === 'active' && $key->org_id === $request->user()->org_id, 403);
    }

    private function metadata(IntegrationApiKey $key): array
    {
        return [
            ...$key->only(['id', 'name', 'prefix', 'scopes', 'created_by']),
            'access_policy' => $key->access_policy,
            'created_at' => $key->created_at->toIso8601String(),
            'expires_at' => $key->expires_at->toIso8601String(),
            'revoked_at' => $key->revoked_at?->toIso8601String(),
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'status' => $key->revoked_at ? 'revoked' : ($key->expires_at->lte(now()) ? 'expired' : 'active'),
        ];
    }

    private function audit(Request $request, string $action, IntegrationApiKey $key, ?array $before = null): void
    {
        AuditLog::create([
            'org_id' => $key->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action,
            'entity_type' => 'integration_api_key', 'entity_id' => $key->id,
            'before_json' => $before, 'after_json' => $this->metadata($key),
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
