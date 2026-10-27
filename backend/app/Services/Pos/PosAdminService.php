<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosAdminService
{
    public function register(User $actor, array $input): string
    {
        $this->guard($actor);
        $d = validator($input, ['id' => 'required|uuid', 'branch_id' => 'required|uuid', 'warehouse_id' => 'required|uuid', 'customer_id' => 'required|uuid', 'actor_id' => 'required|uuid', 'name' => 'required|string|max:255', 'business_type' => 'required|in:retail,restaurant,pharmacy', 'branch_code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,30}$/D'], 'terminals' => 'required|array|min:1|max:20', 'terminals.*.id' => 'required|uuid|distinct', 'terminals.*.code' => ['required', 'string', 'distinct', 'regex:/^[A-Za-z0-9_-]{1,30}$/D'], 'key_id' => 'required|string|max:100', 'public_key' => 'required|string|max:5000', 'recovery_generation' => 'required|string|min:8|max:100', 'account_codes' => 'required|array'])->validate();
        $public = openssl_pkey_get_public($d['public_key']);
        $details = $public ? openssl_pkey_get_details($public) : false;
        abort_unless($details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048, 422, 'RSA public signing key of at least 2048 bits required.');

        return DB::transaction(function () use ($actor, $d, $details) {
            DB::table('organizations')->where('id', $actor->org_id)->lockForUpdate()->firstOrFail();
            Branch::where('org_id', $actor->org_id)->where('status', 'active')->findOrFail($d['branch_id']);
            Warehouse::where('org_id', $actor->org_id)->findOrFail($d['warehouse_id']);
            Customer::where('org_id', $actor->org_id)->findOrFail($d['customer_id']);
            User::where('org_id', $actor->org_id)->where('status', 'active')->findOrFail($d['actor_id']);
            abort_if(DB::table('pos_instances')->where('id', $d['id'])->exists(), 409, 'POS instance identity already exists.');
            $mapping = PosAccountMapping::forProvisioning($actor->org_id, $d['account_codes']);
            DB::table('pos_instances')->insert(['id' => $d['id'], 'org_id' => $actor->org_id, 'branch_id' => $d['branch_id'], 'warehouse_id' => $d['warehouse_id'], 'customer_id' => $d['customer_id'], 'actor_id' => $d['actor_id'], 'name' => $d['name'], 'business_type' => $d['business_type'], 'branch_code' => $d['branch_code'], 'terminal_ids' => json_encode(array_column($d['terminals'], 'id')), 'terminal_codes' => json_encode(array_column($d['terminals'], 'code', 'id')), 'signing_keys' => json_encode([$d['key_id'] => ['active' => true, 'public_key' => $details['key']]]), 'account_codes' => json_encode($mapping->snapshot($actor->org_id)), 'recovery_generation' => $d['recovery_generation'], 'active' => false, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'pos.instance.registered', $d['id'], ['branch_id' => $d['branch_id'], 'key_id' => $d['key_id'], 'key_fingerprint' => hash('sha256', $details['key'])]);

            return $d['id'];
        });
    }

    public function activate(User $actor, string $id): void
    {
        $this->guard($actor);
        DB::transaction(function () use ($actor, $id) {
            DB::table('organizations')->where('id', $actor->org_id)->lockForUpdate()->firstOrFail();
            $i = DB::table('pos_instances')->where('id', $id)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            abort_if($i->recovery_fenced_at !== null || ! $i->recovery_generation || ! $i->branch_code || ! $i->terminal_codes, 423, 'Provisioning or recovery acceptance is incomplete.');
            abort_if(DB::table('pos_instances')->where('org_id', $actor->org_id)->where('branch_id', $i->branch_id)->where('id', '!=', $id)->where('active', true)->exists(), 409, 'Another instance is active for this branch.');
            abort_unless(array_filter(json_decode($i->signing_keys, true), fn ($key) => $key['active'] ?? false), 422, 'An active verification key is required.');
            PosAccountMapping::forProvisioning($actor->org_id, json_decode($i->account_codes, true));
            DB::table('pos_instances')->where('id', $id)->update(['active' => true, 'updated_at' => now()]);
            $this->audit($actor, 'pos.instance.activated', $id, []);
        });
    }

    public function authority(User $actor, string $id, array $input): void
    {
        $this->guard($actor);
        $d = validator($input, ['source_staff_id' => 'required|uuid', 'role' => 'required|in:cashier,manager,kitchen,pharmacist', 'active' => 'required|boolean', 'pharmacist_qualified' => 'required|boolean'])->validate();
        DB::transaction(function () use ($actor, $id, $d) {
            DB::table('pos_instances')->where('id', $id)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_staff_authorities')->where('pos_instance_id', $id)->where('source_staff_id', $d['source_staff_id'])->first();
            DB::table('pos_staff_authorities')->updateOrInsert(['pos_instance_id' => $id, 'source_staff_id' => $d['source_staff_id']], ['id' => $old?->id ?? (string) Str::uuid(), 'role' => $d['role'], 'active' => $d['active'], 'pharmacist_qualified' => $d['pharmacist_qualified'], 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
            $this->audit($actor, 'pos.staff.authority_changed', $id, $d);
        });
    }

    public function signingKey(User $actor, string $id, array $input): void
    {
        $this->guard($actor);
        $d = validator($input, ['key_id' => 'required|string|max:100', 'operation' => 'required|in:add,revoke', 'public_key' => 'required_if:operation,add|nullable|string|max:5000', 'reason' => 'required|string|max:500'])->validate();
        DB::transaction(function () use ($actor, $id, $d) {
            $instance = DB::table('pos_instances')->where('id', $id)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            $keys = json_decode($instance->signing_keys, true);
            if ($d['operation'] === 'add') {
                $public = openssl_pkey_get_public($d['public_key']);
                $details = $public ? openssl_pkey_get_details($public) : false;
                abort_unless($details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048, 422, 'RSA public key required.');
                abort_if(isset($keys[$d['key_id']]) && $keys[$d['key_id']]['public_key'] !== $details['key'], 409, 'Signing key identity cannot be reused.');
                abort_if(isset($keys[$d['key_id']]) && ! $keys[$d['key_id']]['active'], 409, 'Revoked key IDs cannot be reactivated.');
                $keys[$d['key_id']] = ['active' => true, 'public_key' => $details['key']];
            } else {
                abort_unless(isset($keys[$d['key_id']]), 404);
                $keys[$d['key_id']]['active'] = false;
            }
            $update = ['signing_keys' => json_encode($keys), 'updated_at' => now()];
            if (! array_filter($keys, fn ($key) => $key['active'])) {
                $update['active'] = false;
            }
            DB::table('pos_instances')->where('id', $id)->update($update);
            $this->audit($actor, 'pos.signing_key.'.$d['operation'], $id, ['key_id' => $d['key_id'], 'reason' => $d['reason']]);
        });
    }

    public function recover(User $actor, string $id, string $checkpointId, string $reason): void
    {
        $this->guard($actor);
        validator(['reason' => $reason], ['reason' => 'required|string|max:500'])->validate();
        DB::transaction(function () use ($actor, $id, $checkpointId, $reason) {
            $i = DB::table('pos_instances')->where('id', $id)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            $cp = DB::table('pos_source_checkpoints')->where('pos_instance_id', $id)->where('source_checkpoint_id', $checkpointId)->firstOrFail();
            $snapshot = json_decode($cp->snapshot, true);
            $target = (int) $cp->through_sequence;
            abort_unless($i->recovery_fenced_at && $target >= $i->durable_cursor && $snapshot['high_watermark'] === $target && $cp->recovery_generation === $i->recovery_generation, 409, 'Recovery requires a complete source cutoff at or beyond the durable inbox.');
            $pages = [];
            $cursor = 0;
            do {
                $page = $cursor === (int) $cp->after_sequence ? $cp : DB::table('pos_source_checkpoints')->where('pos_instance_id', $id)->where('recovery_generation', $i->recovery_generation)->where('after_sequence', $cursor)->where('through_sequence', '>', $cursor)->where('through_sequence', '<=', (int) $cp->after_sequence)->where('created_at', '>=', $i->recovery_fenced_at)->orderByDesc('through_sequence')->first();
                abort_unless($page && count($pages) < 1000, 409, 'Recovery checkpoint chain is incomplete. Capture pages from sequence zero without gaps.');
                $body = json_decode($page->snapshot, true);
                abort_unless(Carbon::parse($body['occurred_at'])->gte(Carbon::parse($i->recovery_fenced_at)), 409, 'Fresh source checkpoints after fencing are required.');
                $checked = app(PosCheckpointService::class)->verify($id, $body);
                abort_unless(count(array_filter($checked['issues'], fn ($issue) => ! in_array($issue['code'], ['instance_recovery_fenced', 'source_event_not_accepted'], true))) === 0, 409, 'Reconstructed source evidence conflicts with ERP.');
                $pages[] = $body;
                $cursor = (int) $page->through_sequence;
            } while ($cursor < $target);
            foreach ($pages as $body) {
                foreach (array_chunk(array_column($body['entries'], 'event_id'), 500) as $sourceIds) {
                    DB::table('pos_sync_inbox')->where('pos_instance_id', $id)->whereIn('event_id', $sourceIds)->where('status', '!=', 'accepted')->update(['attempts' => 0]);
                }
            }
            for ($pass = 0; $pass < 4; $pass++) {
                $remaining = 0;
                foreach ($pages as $body) {
                    foreach (array_chunk(array_column($body['entries'], 'event_id'), 500) as $sourceIds) {
                        $waiting = DB::table('pos_sync_inbox')->where('pos_instance_id', $id)->whereIn('event_id', $sourceIds)->where('status', '!=', 'accepted')->orderBy('source_sequence')->get();
                        $remaining += $waiting->count();
                        foreach ($waiting as $event) {
                            app(PosImportService::class)->process($id, $event->event_id, $actor);
                        }
                    }
                }
                if ($remaining === 0) {
                    break;
                }
            }
            abort_if(DB::table('pos_sync_inbox')->where('pos_instance_id', $id)->where('status', '!=', 'accepted')->exists() || DB::table('pos_sync_conflicts')->where('pos_instance_id', $id)->exists(), 409, 'Outstanding review or integrity conflicts block recovery.');
            foreach ($pages as $body) {
                $final = app(PosCheckpointService::class)->verify($id, $body);
                abort_unless(count(array_filter($final['issues'], fn ($issue) => $issue['code'] !== 'instance_recovery_fenced')) === 0, 409, 'Recovery ledger reconciliation failed.');
            }
            DB::table('pos_instances')->where('id', $id)->update(['recovery_fenced_at' => null, 'updated_at' => now()]);
            foreach ($pages as $body) {
                abort_unless(app(PosCheckpointService::class)->verify($id, $body)['status'] === 'verified', 409, 'Final source checkpoint failed.');
            }
            $this->audit($actor, 'pos.instance.recovered', $id, ['source_checkpoint_id' => $checkpointId, 'reason' => $reason]);
        });
    }

    private function guard(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']) && $actor->status === 'active' && $actor->hasPermissionCode('pos.manage') && $actor->hasPermissionCode('inventory.adjust'), 403);
    }

    private function audit(User $actor, string $action, string $id, array $metadata): void
    {
        AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => $action, 'entity_type' => 'pos', 'entity_id' => $id, 'after_json' => $metadata, 'request_id' => (string) Str::uuid()]);
    }
}
