<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosQuotaRecoveryService
{
    /** Obtain proof through the Connector; callers cannot submit claimed freeze counters. */
    public function close(User $actor, string $instanceId, string $grantId, string $reason): string
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_quota_recovery_enabled') === true, 503, 'Local quota recovery disabled.');
        abort_unless($actor->status === 'active' && $actor->hasPermissionCode('pos.manage') && $actor->hasPermissionCode('inventory.adjust'), 403);
        abort_unless(trim($reason) !== '' && strlen($reason) <= 500, 422, 'Quota recovery reason required.');
        DB::table('pos_instances')->where('id', $instanceId)->where('org_id', $actor->org_id)->firstOrFail();
        $reference = DB::table('pos_stock_entitlements')->where('id', $grantId)->where('pos_instance_id', $instanceId)->where('org_id', $actor->org_id)->firstOrFail();
        $proof = app(PosTransport::class)->request($instanceId, 'freeze-grant', ['epoch' => (int) $reference->epoch], $grantId);
        $proof = validator($proof, ['grant_id' => 'required|uuid', 'epoch' => 'required|integer|min:1', 'status' => 'required|in:frozen', 'granted_quantity' => ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/D'], 'consumed_quantity' => ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/D'], 'reserved_quantity' => ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/D'], 'source_sequence' => 'required|integer|min:0'])->validate();

        return DB::transaction(function () use ($actor, $instanceId, $grantId, $proof, $reason) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            $reference = DB::table('pos_stock_entitlements')->where('id', $grantId)->where('org_id', $actor->org_id)->where('pos_instance_id', $instanceId)->firstOrFail();
            app(InventoryMovementService::class)->lockProducts($actor->org_id, [$reference->product_id]);
            $grant = DB::table('pos_stock_entitlements')->where('id', $grantId)->lockForUpdate()->firstOrFail();
            abort_unless($proof['grant_id'] === $grantId && $proof['epoch'] === $grant->epoch && bccomp($proof['reserved_quantity'], '0', 4) === 0 && bccomp($proof['granted_quantity'], (string) $grant->granted_quantity, 4) === 0 && bccomp($proof['consumed_quantity'], (string) $grant->consumed_quantity, 4) === 0, 409, 'Quota freeze counters do not reconcile.');
            $evidence = PosCanonicalJson::encode($proof);
            if ($grant->status === 'closed') {
                abort_unless(PosCanonicalJson::encode(json_decode($grant->freeze_evidence, true, flags: JSON_THROW_ON_ERROR)) === $evidence, 409, 'Quota freeze evidence conflict.');

                return bcsub((string) $grant->granted_quantity, (string) $grant->consumed_quantity, 4);
            }
            abort_unless($instance->durable_cursor >= $proof['source_sequence'] && ! DB::table('pos_sync_inbox')->where('pos_instance_id', $instanceId)->where('source_sequence', '<=', $proof['source_sequence'])->where('status', '!=', 'accepted')->exists(), 409, 'Quota event watermark is not fully imported.');
            abort_if(DB::table('pos_sync_conflicts')->where('pos_instance_id', $instanceId)->exists(), 409, 'Quota integrity conflicts require review.');
            $unused = bcsub((string) $grant->granted_quantity, (string) $grant->consumed_quantity, 4);
            abort_if(bccomp($unused, '0', 4) < 0, 409, 'Quota counters invalid.');
            DB::table('pos_stock_entitlements')->where('id', $grantId)->update(['status' => 'closed', 'freeze_evidence' => $evidence, 'closed_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.local_quota_reclaim', 'entity_type' => 'pos_entitlement', 'entity_id' => $grantId, 'after_json' => ['reason' => $reason, 'unused_quantity' => $unused, 'freeze_hash' => hash('sha256', $evidence)], 'request_id' => (string) Str::uuid()]);

            return $unused;
        });
    }
}
