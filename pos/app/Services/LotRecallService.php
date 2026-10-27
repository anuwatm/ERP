<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Support\CanonicalJson;

class LotRecallService
{
    public function receive(string $id, array $input): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_recalls_enabled') === true && Str::isUuid($id), 403);
        $p = validator($input, ['branch_id' => 'required|uuid', 'product_id' => 'required|uuid', 'lot_id' => 'required|uuid', 'reason' => 'required|string|max:500', 'recalled_at' => 'required|date'])->validate();
        abort_unless(in_array($p['branch_id'], config('pos.connector_branch_ids'), true), 403);
        $hash = hash('sha256', CanonicalJson::encode($p));
        DB::transaction(function () use ($id, $p, $hash) {
            DB::table('pos_branches')->where('id', $p['branch_id'])->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_lot_recalls')->where('id', $id)->orWhere(fn ($q) => $q->where('branch_id', $p['branch_id'])->where('lot_id', $p['lot_id']))->first();
            if ($old) {
                abort_unless($old->id === $id && hash_equals($old->payload_hash, $hash), 409, 'Recall identity conflict.');

                return;
            }
            DB::table('pos_lot_recalls')->insert(['id' => $id, 'branch_id' => $p['branch_id'], 'lot_id' => $p['lot_id'], 'product_id' => $p['product_id'], 'reason' => $p['reason'], 'recalled_at' => Carbon::parse($p['recalled_at'])->utc(), 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['actor_id' => null, 'branch_id' => $p['branch_id'], 'action' => 'lot.recalled', 'source_id' => $id, 'metadata' => CanonicalJson::encode($p), 'created_at' => now()]);
        });
    }

    public function assertAvailable(string $branchId, ?string $lotId): void
    {
        abort_if($lotId !== null && DB::table('pos_lot_recalls')->where('branch_id', $branchId)->where('lot_id', $lotId)->exists(), 422, 'Lot is recalled and quarantined.');
    }
}
