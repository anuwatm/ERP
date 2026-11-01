<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PosMember;
use App\Models\User;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosMembershipPublicationService
{
    public function allocate(User $actor, string $instanceId, string $ruleId, ?string $memberId, int $quantity, string $requestKey): array
    {
        $this->guard($actor);
        abort_unless(Str::isUuid($requestKey), 422, 'Allocation request UUID required.');
        $requestHash = hash('sha256', PosCanonicalJson::encode([$ruleId, $memberId, $quantity]));
        abort_unless($quantity > 0 && $quantity <= 100, 422, 'Allocate 1–100 rights at a time.');

        return DB::transaction(function () use ($actor, $instanceId, $ruleId, $memberId, $quantity, $requestKey, $requestHash) {
            Organization::whereKey($actor->org_id)->lockForUpdate()->firstOrFail();
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('org_id', $actor->org_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_promotion_allocations')->where('pos_instance_id', $instanceId)->where('request_key', $requestKey)->orderBy('ordinal')->get();
            if ($old->isNotEmpty()) {
                abort_unless($old->count() === $quantity && $old->every(fn ($r) => hash_equals($r->request_hash, $requestHash)), 409, 'Allocation request identity conflict.');

                return $old->pluck('id')->all();
            }
            $rule = DB::table('pos_promotion_rules')->where('id', $ruleId)->where('org_id', $actor->org_id)->firstOrFail();
            $p = json_decode($rule->policy, true, flags: JSON_THROW_ON_ERROR);
            abort_unless(Carbon::parse($p['ends_at'])->gt(now()), 422, 'Cannot allocate expired promotion rights.');
            abort_unless(in_array($instance->branch_id, $p['branch_ids'], true) && in_array($instance->business_type, $p['business_types'], true), 403, 'Allocation branch mismatch.');
            abort_unless($p['max_uses'] !== null || $p['per_member_limit'] !== null, 422, 'Unlimited policy needs no rights.');
            if ($memberId !== null) {
                $member = PosMember::where('org_id', $actor->org_id)->where('status', 'active')->findOrFail($memberId);
                abort_unless(! $p['member_tiers'] || in_array($member->tier, $p['member_tiers'], true), 422, 'Member tier is not eligible for this allocation.');
            }
            abort_unless(! $p['member_required'] || $memberId !== null, 422, 'Member-specific right required.');
            // Limits cover all allocated slots across instances, including unacknowledged ones.
            $all = DB::table('pos_promotion_allocations')->where('org_id', $actor->org_id)->whereIn('rule_id', DB::table('pos_promotion_rules')->where('org_id', $actor->org_id)->where('promotion_id', $p['id'])->select('id'));
            abort_if($p['max_uses'] !== null && (clone $all)->count() + $quantity > $p['max_uses'], 409, 'Global redemption allocation exhausted.');
            abort_if($p['per_member_limit'] !== null && (clone $all)->where('member_id', $memberId)->count() + $quantity > $p['per_member_limit'], 409, 'Member redemption allocation exhausted.');
            $ids = [];
            for ($i = 0; $i < $quantity; $i++) {
                $id = (string) Str::uuid();
                DB::table('pos_promotion_allocations')->insert(['id' => $id, 'org_id' => $actor->org_id, 'pos_instance_id' => $instanceId, 'rule_id' => $ruleId, 'member_id' => $memberId, 'request_key' => $requestKey, 'request_hash' => $requestHash, 'ordinal' => $i, 'created_at' => now(), 'updated_at' => now()]);
                $ids[] = $id;
            }

            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.promotion.rights_allocated', 'entity_type' => 'pos_membership', 'entity_id' => $requestKey, 'after_json' => ['instance_id' => $instanceId, 'rule_id' => $ruleId, 'member_id' => $memberId, 'quantity' => $quantity], 'request_id' => $requestKey]);

            return $ids;
        });
    }

    public function create(User $actor, string $instanceId, array $memberIds, array $ruleIds): array
    {
        $this->guard($actor);
        validator(['members' => $memberIds, 'rules' => $ruleIds], ['members' => 'present|array|max:500', 'members.*' => 'required|uuid|distinct', 'rules' => 'present|array|max:100', 'rules.*' => 'required|uuid|distinct'])->validate();

        return DB::transaction(function () use ($actor, $instanceId, $memberIds, $ruleIds) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('org_id', $actor->org_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $members = PosMember::where('org_id', $actor->org_id)->whereIn('id', $memberIds)->get();
            $rules = DB::table('pos_promotion_rules')->where('org_id', $actor->org_id)->whereIn('id', $ruleIds)->get();
            abort_unless($members->count() === count($memberIds) && $rules->count() === count($ruleIds), 403, 'Publication outside organization.');
            $policies = [];
            foreach ($rules as $rule) {
                $policy = json_decode($rule->policy, true, flags: JSON_THROW_ON_ERROR);
                abort_unless(in_array($instance->branch_id, $policy['branch_ids'], true) && in_array($instance->business_type, $policy['business_types'], true), 403, 'Publication branch mismatch.');
                abort_if(collect($policies)->contains(fn ($e) => $e['policy']['id'] === $policy['id']), 422, 'Publish one revision per promotion.');
                $policies[] = ['name' => $rule->name, 'policy' => $policy];
            }
            $rights = DB::table('pos_promotion_allocations as a')->join('pos_promotion_rules as r', 'r.id', '=', 'a.rule_id')->where('a.pos_instance_id', $instanceId)->where('a.org_id', $actor->org_id)->whereIn('a.rule_id', $ruleIds)->where(fn ($q) => $q->whereNull('a.member_id')->orWhereIn('a.member_id', $memberIds))->get(['a.id', 'a.member_id', 'r.promotion_id', 'r.version as policy_version'])->map(fn ($r) => (array) $r)->all();
            abort_if(count($rights) > 500, 422, 'Publication right limit exceeded.');
            $version = 1 + (int) DB::table('pos_membership_publications')->where('pos_instance_id', $instanceId)->max('version');
            $payload = ['id' => (string) Str::uuid(), 'instance_id' => $instanceId, 'branch_id' => $instance->branch_id, 'version' => $version, 'created_at' => now()->utc()->toIso8601String(), 'valid_until' => now()->addMinutes(15)->utc()->toIso8601String(), 'members' => $members->map(fn ($m) => ['member_id' => $m->id, 'code' => $m->public_code, 'name_masked' => mb_substr($m->contact['name'], 0, 1).'***', 'phone_masked' => '***'.substr($m->contact['phone'], -4), 'tier' => $m->tier, 'status' => $m->status])->all(), 'policies' => $policies, 'rights' => $rights];
            $keyPath = config('pos.member_enrollment_private_key_path');
            $payload['enrollment_key'] = null;
            if (is_string($keyPath) && is_file($keyPath)) {
                $key = openssl_pkey_get_private(file_get_contents($keyPath));
                $details = $key ? openssl_pkey_get_details($key) : false;
                abort_unless($details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048, 503, 'Invalid dedicated enrollment key.');
                $payload['enrollment_key'] = ['id' => hash('sha256', $details['key']), 'public_key' => $details['key']];
            }
            $payload['decisions'] = DB::table('pos_import_member_requests')->where('pos_instance_id', $instanceId)->whereIn('status', ['approved', 'rejected'])->where(fn ($q) => $q->whereNull('member_id')->orWhereIn('member_id', $memberIds))->orderByDesc('updated_at')->limit(500)->get(['source_request_id as request_id', 'status', 'member_id'])->map(fn ($r) => (array) $r)->all();
            abort_unless(strlen(PosCanonicalJson::encode($payload)) <= 2097152, 422, 'Membership publication exceeds connector limit.');
            DB::table('pos_membership_publications')->insert(['id' => $payload['id'], 'org_id' => $actor->org_id, 'pos_instance_id' => $instanceId, 'created_by' => $actor->id, 'version' => $version, 'payload' => PosCanonicalJson::encode($payload), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($payload)), 'created_at' => now(), 'updated_at' => now()]);

            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.membership.publication_created', 'entity_type' => 'pos_membership', 'entity_id' => $payload['id'], 'after_json' => ['instance_id' => $instanceId, 'version' => $version], 'request_id' => (string) Str::uuid()]);

            return $payload;
        });
    }

    public function forSync(string $instanceId, bool $force = false, ?User $actorOverride = null): ?array
    {
        return DB::transaction(function () use ($instanceId, $force, $actorOverride) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->lockForUpdate()->firstOrFail();
            $row = DB::table('pos_membership_publications')->where('pos_instance_id', $instanceId)->orderByDesc('version')->first();
            if (! $row) {
                return null;
            }
            $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
            $current = PosMember::where('org_id', $instance->org_id)->whereIn('id', array_column($payload['members'], 'member_id'))->get()->keyBy('id');
            $changed = collect($payload['members'])->contains(fn ($m) => ! isset($current[$m['member_id']]) || $current[$m['member_id']]->status !== $m['status'] || $current[$m['member_id']]->tier !== $m['tier']);
            if (! $force && ! $changed && Carbon::parse($payload['valid_until'])->gt(now()->addMinutes(5))) {
                return $payload;
            }
            $actor = $actorOverride ?? User::where('org_id', $instance->org_id)->findOrFail($row->created_by);
            $memberIds = array_column($payload['members'], 'member_id');
            $approved = DB::table('pos_import_member_requests')->where('pos_instance_id', $instanceId)->where('status', 'approved')->pluck('member_id')->all();
            $memberIds = array_values(array_unique(array_merge($memberIds, $approved)));
            $ruleIds = [];
            foreach ($payload['policies'] as $entry) {
                $ruleIds[] = DB::table('pos_promotion_rules')->where('org_id', $instance->org_id)->where('promotion_id', $entry['policy']['id'])->where('version', $entry['policy']['version'])->value('id');
            }

            return $this->create($actor, $instanceId, $memberIds, $ruleIds);
        });
    }

    private function guard(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_membership_enabled') === true, 503, 'Local membership disabled.');
        $current = User::find($actor->id);
        abort_unless($current && $current->org_id === $actor->org_id && $current->status === 'active' && $current->hasPermissionCode('pos.manage'), 403);
    }
}
