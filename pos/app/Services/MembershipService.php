<?php

namespace Pos\Services;

use Company\Pos\PromotionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class MembershipService
{
    public function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_membership_enabled') === true, 503, 'Local membership disabled.');
    }

    public function provision(string $id, array $input): void
    {
        $this->guard();
        abort_unless(strlen(CanonicalJson::encode($input)) <= 2097152, 422, 'Membership publication exceeds connector limit.');
        $d = validator($input, ['id' => 'required|uuid', 'instance_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'created_at' => 'required|date', 'valid_until' => 'required|date|after:now', 'enrollment_key' => 'nullable|array:id,public_key', 'enrollment_key.id' => 'required_with:enrollment_key|string|size:64', 'enrollment_key.public_key' => 'required_with:enrollment_key|string|max:8000', 'decisions' => 'sometimes|array|max:500', 'decisions.*.request_id' => 'required|uuid|distinct', 'decisions.*.status' => 'required|in:approved,rejected', 'decisions.*.member_id' => 'present|nullable|uuid', 'members' => 'present|array|max:500', 'members.*' => 'array:member_id,code,name_masked,phone_masked,tier,status', 'members.*.member_id' => 'required|uuid|distinct', 'members.*.code' => 'required|string|max:50', 'members.*.name_masked' => 'required|string|max:10', 'members.*.phone_masked' => ['required', 'regex:/^\*{3}[0-9]{4}$/D'], 'members.*.tier' => 'required|string|max:40', 'members.*.status' => 'required|in:active,inactive', 'policies' => 'present|array|max:100', 'policies.*.name' => 'required|string|max:120', 'policies.*.policy' => 'required|array', 'rights' => 'present|array|max:500', 'rights.*' => 'array:id,promotion_id,policy_version,member_id', 'rights.*.id' => 'required|uuid|distinct', 'rights.*.promotion_id' => 'required|uuid', 'rights.*.policy_version' => 'required|integer|min:1', 'rights.*.member_id' => 'present|nullable|uuid'])->validate();
        abort_unless($id === $d['id'] && $d['instance_id'] === config('pos.instance_id') && in_array($d['branch_id'], config('pos.connector_branch_ids'), true), 403, 'Membership publication scope mismatch.');
        abort_unless(Carbon::parse($d['created_at'])->lte(now()->addSeconds(30)) && Carbon::parse($d['created_at'])->gte(now()->subMinutes(15)) && Carbon::parse($d['valid_until'])->lte(Carbon::parse($d['created_at'])->addMinutes(15)), 422, 'Membership publication window invalid.');
        $policies = [];
        if (! empty($d['enrollment_key'])) {
            $key = openssl_pkey_get_public($d['enrollment_key']['public_key']);
            $details = $key ? openssl_pkey_get_details($key) : false;
            abort_unless($details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048 && hash_equals(hash('sha256', $details['key']), $d['enrollment_key']['id']), 422, 'Invalid enrollment public key fingerprint.');
        }
        foreach ($d['policies'] as &$entry) {
            try {
                $entry['policy'] = (new PromotionCalculator)->validatePolicy($entry['policy']);
            } catch (\InvalidArgumentException $e) {
                abort(422, $e->getMessage());
            }
            $p = $entry['policy'];
            abort_unless(in_array($d['branch_id'], $p['branch_ids'], true) && ! isset($policies[$p['id']]), 422, 'Invalid publication policy scope.');
            $policies[$p['id']] = $p;
        }
        unset($entry);
        foreach ($d['rights'] as $right) {
            $p = $policies[$right['promotion_id']] ?? null;
            abort_unless($p && $p['version'] === $right['policy_version'] && ($right['member_id'] === null || collect($d['members'])->contains('member_id', $right['member_id'])), 422, 'Invalid right scope.');
        }
        $hash = hash('sha256', CanonicalJson::encode($d));
        DB::transaction(function () use ($d, $hash) {
            DB::table('pos_branches')->where('id', $d['branch_id'])->where('active', true)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_membership_publications')->where('id', $d['id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409, 'Membership publication identity conflict.');

                return;
            }
            abort_unless($d['version'] > (int) DB::table('pos_membership_publications')->where('branch_id', $d['branch_id'])->max('version'), 409, 'Stale membership publication.');
            foreach ($d['rights'] as $right) {
                $existing = DB::table('pos_promotion_rights')->where('id', $right['id'])->first();
                if ($existing) {
                    abort_unless($existing->branch_id === $d['branch_id'] && $existing->promotion_id === $right['promotion_id'] && $existing->policy_version === $right['policy_version'] && $existing->member_id === $right['member_id'], 409, 'Right identity conflict.');
                } else {
                    DB::table('pos_promotion_rights')->insert($right + ['branch_id' => $d['branch_id'], 'used_by' => null, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            foreach ($d['decisions'] ?? [] as $decision) {
                abort_unless($decision['status'] !== 'approved' || collect($d['members'])->contains('member_id', $decision['member_id']), 422, 'Approved member must be published.');
                DB::table('pos_member_drafts')->where('branch_id', $d['branch_id'])->where('id', $decision['request_id'])->update(['status' => $decision['status'], 'member_id' => $decision['member_id'], 'updated_at' => now()]);
            }
            DB::table('pos_membership_publications')->insert(['id' => $d['id'], 'branch_id' => $d['branch_id'], 'version' => $d['version'], 'payload' => CanonicalJson::encode($d), 'payload_hash' => $hash, 'valid_until' => Carbon::parse($d['valid_until']), 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function options(Staff $staff): array
    {
        $branch = $this->actor($staff);
        $row = DB::table('pos_membership_publications')->where('branch_id', $branch->id)->orderByDesc('version')->first();
        if (! $row || Carbon::parse($row->valid_until)->lte(now())) {
            return ['publication_id' => null, 'enrollment_ready' => false, 'members' => [], 'policies' => []];
        }
        $d = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->payload_hash, hash('sha256', CanonicalJson::encode($d))), 409, 'Membership publication evidence conflict.');

        return ['publication_id' => $row->id, 'enrollment_ready' => ! empty($d['enrollment_key']), 'members' => array_values(array_filter($d['members'], fn ($m) => $m['status'] === 'active')), 'policies' => array_map(fn ($p) => ['id' => $p['policy']['id'], 'name' => $p['name']], $d['policies'])];
    }

    public function quote(Staff $staff, array $input, array $lines): ?array
    {
        if (empty($input['member_id']) && empty($input['promotion_id'])) {
            return null;
        }
        $branch = $this->actor($staff);
        $row = DB::table('pos_membership_publications')->where('branch_id', $branch->id)->orderByDesc('version')->firstOrFail();
        abort_unless($row->id === ($input['membership_publication_id'] ?? '') && Carbon::parse($row->valid_until)->gt(now()), 409, 'Review current membership publication.');
        $pub = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->payload_hash, hash('sha256', CanonicalJson::encode($pub))), 409, 'Membership publication evidence conflict.');
        $member = empty($input['member_id']) ? null : collect($pub['members'])->firstWhere('member_id', $input['member_id']);
        abort_unless(empty($input['member_id']) || ($member && $member['status'] === 'active'), 422, 'Active published member required.');
        $proof = ['publication_id' => $pub['id'], 'member_id' => $member['member_id'] ?? null, 'promotion_id' => $input['promotion_id'] ?? null, 'claim_id' => null, 'grant_id' => null, 'quote' => null, 'evaluated_at' => now()->utc()->toIso8601String()];
        if (empty($input['promotion_id'])) {
            return $proof;
        }
        abort_if(! empty($input['discount_approval_id']), 422, 'Promotion cannot stack with manual discount.');
        $entry = collect($pub['policies'])->first(fn ($p) => $p['policy']['id'] === $input['promotion_id']);
        abort_unless($entry, 422, 'Published promotion required.');
        try {
            $q = (new PromotionCalculator)->quote($entry['policy'], $lines, ['branch_id' => $branch->id, 'business_type' => $branch->business_type, 'currency' => $branch->currency, 'manual_discount_applied' => false, 'member' => $member ? ['id' => $member['member_id'], 'tier' => $member['tier'], 'status' => $member['status'], 'valid_until' => Carbon::parse($pub['valid_until'])->utc()->format('Y-m-d\TH:i:s\Z')] : null], now()->toDateTimeImmutable());
        } catch (\DomainException|\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
        $proof['quote'] = $q;
        if ($q['requires_entitlement']) {
            $ids = array_column($pub['rights'], 'id');
            $right = DB::table('pos_promotion_rights')->whereIn('id', $ids)->where('branch_id', $branch->id)->where('promotion_id', $q['promotion_id'])->where('policy_version', $q['version'])->where('member_id', $proof['member_id'])->whereNull('used_by')->orderBy('id')->lockForUpdate()->first();
            abort_unless($right, 409, 'No allocated redemption right available.');
            $proof['grant_id'] = $right->id;
        }

        return $proof;
    }

    public function claim(Staff $staff, string $kind, string $source, ?array $proof): ?array
    {
        if (! $proof || ! $proof['promotion_id']) {
            return $proof;
        }
        abort_unless(DB::transactionLevel() > 0 && in_array($kind, ['sale', 'delivery'], true), 500);
        $id = (string) Str::uuid();
        if ($proof['grant_id']) {
            abort_unless(DB::table('pos_promotion_rights')->where('id', $proof['grant_id'])->where('branch_id', $staff->branch_id)->whereNull('used_by')->update(['used_by' => $id, 'updated_at' => now()]) === 1, 409, 'Redemption right already used.');
        }
        $proof['claim_id'] = $id;
        DB::table('pos_promotion_claims')->insert(['id' => $id, 'branch_id' => $staff->branch_id, 'source_kind' => $kind, 'source_id' => $source, 'grant_id' => $proof['grant_id'], 'proof' => CanonicalJson::encode($proof), 'created_at' => now(), 'updated_at' => now()]);

        return $proof;
    }

    public function release(string $branch, string $kind, string $source): void
    {
        abort_unless(DB::transactionLevel() > 0, 500);
        $claim = DB::table('pos_promotion_claims')->where('branch_id', $branch)->where('source_kind', $kind)->where('source_id', $source)->lockForUpdate()->first();
        if (! $claim || $claim->released_at) {
            return;
        }
        if ($claim->grant_id) {
            abort_unless(DB::table('pos_promotion_rights')->where('id', $claim->grant_id)->where('used_by', $claim->id)->update(['used_by' => null, 'updated_at' => now()]) === 1, 409, 'Redemption release evidence conflict.');
        }
        DB::table('pos_promotion_claims')->where('id', $claim->id)->update(['released_at' => now(), 'updated_at' => now()]);
    }

    public function releaseRefundedSale(object $order): void
    {
        $snapshot = json_decode($order->snapshot, true);
        if (empty($snapshot['membership']['claim_id'])) {
            return;
        }
        foreach ($snapshot['lines'] as $line) {
            $quantity = DB::table('pos_refund_lines')->where('order_item_id', $line['line_id'])->get()->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->quantity, 4), '0.0000');
            if (bccomp($quantity, $line['quantity'], 4) !== 0) {
                return;
            }
        }
        $this->release($order->branch_id, 'sale', $order->id);
    }

    public function actor(Staff $staff): object
    {
        $this->guard();
        $current = Staff::whereKey($staff->id)->firstOrFail();
        abort_unless($current->active && $current->branch_id === $staff->branch_id && $current->role === $staff->role && in_array($current->role, ['cashier', 'manager', 'pharmacist'], true), 403);

        return DB::table('pos_branches')->where('id', $current->branch_id)->where('active', true)->firstOrFail();
    }
}
