<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosMember;
use App\Models\Product;
use App\Models\User;
use Company\Pos\PromotionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosMembershipMasterService
{
    public function register(User $actor, array $input): array
    {
        $this->guard($actor);
        $d = validator($input, ['request_key' => 'required|uuid', 'name' => 'required|string|max:80', 'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{8,30}$/D'], 'tier' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/D'], 'consent_confirmed' => 'required|accepted'])->validate();
        $d['name'] = trim($d['name']);
        $d['phone'] = $this->phone($d['phone']);
        $d['consent_confirmed'] = true;
        $hash = hash_hmac('sha256', json_encode($d, JSON_THROW_ON_ERROR), $this->lookupKey());

        return DB::transaction(function () use ($actor, $d, $hash) {
            Organization::whereKey($actor->org_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $current = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->org_id === $actor->org_id, 403);
            $this->guard($current);
            $old = PosMember::where('org_id', $actor->org_id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->created_by === $actor->id && hash_equals($old->request_hash, $hash), 409, 'Member request identity conflict.');

                return $this->masked($old);
            }
            $fingerprint = $this->phoneHash($actor->org_id, $d['phone']);
            abort_if(PosMember::where('org_id', $actor->org_id)->where('phone_fingerprint', $fingerprint)->exists(), 409, 'Possible existing member; explicit matching required.');
            $member = PosMember::create(['org_id' => $actor->org_id, 'public_code' => 'MBR-'.strtoupper((string) Str::uuid()), 'tier' => $d['tier'], 'status' => 'active', 'contact' => ['name' => $d['name'], 'phone' => $d['phone'], 'consent_confirmed' => true, 'consent_recorded_at' => now()->utc()->toIso8601String()], 'phone_fingerprint' => $fingerprint, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'created_by' => $actor->id]);
            $this->audit($actor, 'pos.member.created', $member->id);

            return $this->masked($member);
        });
    }

    public function candidates(User $actor, string $phone): array
    {
        $this->guard($actor);
        $this->audit($actor, 'pos.member.lookup', $actor->id);

        return PosMember::where('org_id', $actor->org_id)->where('phone_fingerprint', $this->phoneHash($actor->org_id, $this->phone($phone)))->limit(5)->get()->map(fn ($m) => $this->masked($m))->all();
    }

    public function memberForBranch(User $actor, string $memberId, string $branchId): array
    {
        $this->guard($actor);
        Branch::where('org_id', $actor->org_id)->where('status', 'active')->findOrFail($branchId);
        $member = PosMember::where('org_id', $actor->org_id)->findOrFail($memberId);
        $this->audit($actor, 'pos.member.read', $member->id);

        return $this->masked($member);
    }

    /** Immutable definition only. Publishing/entitlement issuance is a separate integration. */
    public function definePromotion(User $actor, string $name, array $policy): string
    {
        $this->guard($actor);
        validator(['name' => $name], ['name' => 'required|string|max:120'])->validate();
        $calculator = new PromotionCalculator;
        $policy = $calculator->validatePolicy($policy);
        $hash = $calculator->fingerprint($policy);

        return DB::transaction(function () use ($actor, $name, $policy, $hash) {
            $org = Organization::whereKey($actor->org_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $current = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->org_id === $actor->org_id, 403);
            $this->guard($current);
            abort_unless($policy['currency'] === $org->currency, 422, 'Organization currency mismatch.');
            abort_unless(Branch::where('org_id', $actor->org_id)->where('status', 'active')->whereIn('id', $policy['branch_ids'])->count() === count($policy['branch_ids']), 403, 'Promotion branch outside organization.');
            abort_unless(Product::where('org_id', $actor->org_id)->where('is_active', true)->whereIn('id', $policy['product_ids'])->count() === count($policy['product_ids']), 403, 'Promotion product outside organization.');
            $base = DB::table('pos_promotion_rules')->where('org_id', $actor->org_id)->where('promotion_id', $policy['id']);
            $old = (clone $base)->where('version', $policy['version'])->first();
            if ($old) {
                abort_unless(hash_equals($old->policy_hash, $hash) && $old->name === $name, 409, 'Promotion revision is immutable.');

                return $old->id;
            }
            abort_unless($policy['version'] === ((int) $base->max('version')) + 1, 409, 'Sequential policy version required.');
            $id = (string) Str::uuid();
            DB::table('pos_promotion_rules')->insert(['id' => $id, 'org_id' => $actor->org_id, 'promotion_id' => $policy['id'], 'version' => $policy['version'], 'name' => $name, 'policy' => json_encode($policy, JSON_THROW_ON_ERROR), 'policy_hash' => $hash, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'pos.promotion.defined', $id);

            return $id;
        });
    }

    private function masked(PosMember $member): array
    {
        return ['member_id' => $member->id, 'code' => $member->public_code, 'name_masked' => mb_substr($member->contact['name'], 0, 1).'***', 'phone_masked' => '***'.substr($member->contact['phone'], -4), 'tier' => $member->tier, 'status' => $member->status];
    }

    private function phone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        abort_unless(preg_match('/^[0-9]{8,15}$/D', $phone), 422, 'Invalid member phone.');

        return $phone;
    }

    private function phoneHash(string $org, string $phone): string
    {
        return hash_hmac('sha256', $org.'|'.$phone, $this->lookupKey());
    }

    private function lookupKey(): string
    {
        $key = config('pos.member_lookup_key');
        abort_unless(is_string($key) && strlen($key) >= 32, 503, 'Member lookup key is not provisioned.');

        return $key;
    }

    private function guard(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_membership_enabled') === true, 503, 'Local membership disabled.');
        $current = User::whereKey($actor->id)->first();
        abort_unless($current && $current->org_id === $actor->org_id && $current->status === 'active' && $current->hasPermissionCode('pos.manage'), 403);
    }

    private function audit(User $actor, string $action, string $id): void
    {
        AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => $action, 'entity_type' => 'pos_membership', 'entity_id' => $id, 'after_json' => [], 'request_id' => (string) Str::uuid()]);
    }
}
