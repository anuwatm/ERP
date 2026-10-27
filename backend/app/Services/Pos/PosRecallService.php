<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\InventoryLot;
use App\Models\User;
use App\Services\InventoryMovementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosRecallService
{
    public function recall(User $actor, string $lotId, string $reason): string
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_recalls_enabled') === true && $actor->status === 'active' && $actor->hasPermissionCode('pos.manage'), 403);
        validator(['reason' => $reason], ['reason' => 'required|string|max:500'])->validate();

        return DB::transaction(function () use ($actor, $lotId, $reason) {
            $lot = InventoryLot::where('org_id', $actor->org_id)->findOrFail($lotId);
            app(InventoryMovementService::class)->lockProducts($actor->org_id, [$lot->product_id]);
            $old = DB::table('pos_lot_recalls')->where('org_id', $actor->org_id)->where('inventory_lot_id', $lotId)->first();
            if ($old) {
                return $old->id;
            }
            $id = (string) Str::uuid();
            DB::table('pos_lot_recalls')->insert(['id' => $id, 'org_id' => $actor->org_id, 'inventory_lot_id' => $lotId, 'product_id' => $lot->product_id, 'created_by' => $actor->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.lot.recall', 'entity_type' => 'inventory_lot', 'entity_id' => $lotId, 'after_json' => ['recall_id' => $id, 'reason' => $reason], 'request_id' => (string) Str::uuid()]);

            return $id;
        });
    }

    public function publish(string $instanceId, PosTransport $transport): void
    {
        $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->firstOrFail();
        $rows = DB::table('pos_lot_recalls as r')->where('r.org_id', $instance->org_id)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pos_recall_deliveries as d')->whereColumn('d.recall_id', 'r.id')->where('d.pos_instance_id', $instanceId))->orderBy('r.created_at')->orderBy('r.id')->limit(101)->get();
        // Drain in bounded batches; a recall backlog never silently skips later holds.
        foreach ($rows->take(100) as $row) {
            $result = $transport->request($instanceId, 'recall', ['branch_id' => $instance->branch_id, 'lot_id' => $row->inventory_lot_id, 'product_id' => $row->product_id, 'reason' => $row->reason, 'recalled_at' => Carbon::parse($row->created_at)->utc()->toIso8601String()], $row->id);
            abort_unless(($result['status'] ?? null) === 'accepted', 502, 'Recall was not acknowledged.');
            DB::table('pos_recall_deliveries')->insertOrIgnore(['pos_instance_id' => $instanceId, 'recall_id' => $row->id, 'acknowledged_at' => now()]);
        }
        abort_if($rows->count() > 100, 409, 'Recall backlog remains; run sync again before polling sales.');
    }
}
