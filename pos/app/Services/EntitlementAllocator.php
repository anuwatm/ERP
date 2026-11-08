<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntitlementAllocator
{
    public function availableLots(string $branchId, string $timezone): array
    {
        return DB::table('pos_grants as g')
            ->where('g.branch_id', $branchId)
            ->where('g.status', 'active')
            ->whereNotNull('g.lot_id')
            ->where(fn ($query) => $query->whereNull('g.expires_at')->orWhere('g.expires_at', '>=', now($timezone)->toDateString()))
            ->whereRaw('g.granted_quantity > g.consumed_quantity + g.reserved_quantity')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pos_lot_recalls as recall')
                ->whereColumn('recall.branch_id', 'g.branch_id')
                ->whereColumn('recall.lot_id', 'g.lot_id'))
            ->orderByRaw('g.expires_at IS NULL')
            ->orderBy('g.expires_at')
            ->orderBy('g.lot_id')
            ->distinct()
            ->get(['g.product_id', 'g.lot_id', 'g.expires_at'])
            ->groupBy('product_id')
            ->map(fn ($lots) => $lots->map(fn ($lot) => [
                'lot_id' => $lot->lot_id,
                'expires_at' => $lot->expires_at,
            ])->values())
            ->all();
    }

    public function take(string $branchId, string $productId, string $quantity, string $timezone, ?string $reservationLine = null, ?string $deliveryId = null, ?string $offlinePermitId = null): array
    {
        $this->lockBranch($branchId);
        abort_unless(preg_match('/^\d{1,10}(\.\d{1,4})?$/D', $quantity) && bccomp($quantity, '0', 4) > 0, 422, 'Invalid entitlement quantity.');
        $remaining = $quantity;
        $allocations = [];
        $grants = DB::table('pos_grants')->where('branch_id', $branchId)->where('product_id', $productId)->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now($timezone)->toDateString()))->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
        $lotIds = $grants->pluck('lot_id')->filter()->unique()->values()->all();
        $recalledLots = $lotIds === []
            ? []
            : DB::table('pos_lot_recalls')->where('branch_id', $branchId)->whereIn('lot_id', $lotIds)->pluck('lot_id')->flip()->all();
        foreach ($grants as $grant) {
            if ($grant->lot_id !== null && isset($recalledLots[$grant->lot_id])) {
                continue;
            }
            abort_unless(bccomp((string) $grant->reserved_quantity, '0', 4) >= 0 && bccomp((string) $grant->consumed_quantity, '0', 4) >= 0 && bccomp(bcadd((string) $grant->reserved_quantity, (string) $grant->consumed_quantity, 4), (string) $grant->granted_quantity, 4) <= 0, 409, 'Entitlement counters conflict.');
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }
            $available = bcsub(bcsub((string) $grant->granted_quantity, (string) $grant->consumed_quantity, 4), (string) $grant->reserved_quantity, 4);
            if (bccomp($available, '0', 4) <= 0) {
                continue;
            }
            $take = bccomp($available, $remaining, 4) > 0 ? $remaining : $available;
            $column = $reservationLine === null && $deliveryId === null && $offlinePermitId === null ? 'consumed_quantity' : 'reserved_quantity';
            DB::table('pos_grants')->where('id', $grant->id)->update([$column => bcadd((string) $grant->$column, $take, 4), 'updated_at' => now()]);
            if ($reservationLine !== null) {
                DB::table('pos_kitchen_reservations')->insert(['id' => (string) Str::uuid(), 'line_id' => $reservationLine, 'grant_id' => $grant->id, 'epoch' => $grant->epoch, 'quantity' => $take, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($deliveryId !== null) {
                DB::table('pos_delivery_reservations')->insert(['id' => (string) Str::uuid(), 'delivery_id' => $deliveryId, 'grant_id' => $grant->id, 'epoch' => $grant->epoch, 'quantity' => $take, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($offlinePermitId !== null) {
                DB::table('pos_offline_reservations')->insert(['id' => (string) Str::uuid(), 'permit_id' => $offlinePermitId, 'grant_id' => $grant->id, 'product_id' => $productId, 'epoch' => $grant->epoch, 'quantity' => $take, 'consumed_quantity' => '0.0000', 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
            }
            $allocations[] = ['grant_id' => $grant->id, 'epoch' => $grant->epoch, 'lot_id' => $grant->lot_id, 'quantity' => $take];
            abort_if(count($allocations) > 100, 422, 'Entitlement fragmentation exceeds the event limit.');
            $remaining = bcsub($remaining, $take, 4);
        }
        abort_if(bccomp($remaining, '0', 4) > 0, 422, 'Insufficient stock entitlement.');

        return $allocations;
    }

    public function finishReservation(string $branchId, string $lineId, string $timezone, bool $consume, bool $allowExpiredWaste = false): array
    {
        $this->lockBranch($branchId);
        $allocations = [];
        $reservations = DB::table('pos_kitchen_reservations')->where('line_id', $lineId)->where('status', 'reserved')->orderBy('grant_id')->lockForUpdate()->get();
        abort_unless($reservations->isNotEmpty(), 409, 'Kitchen reservation is not active.');
        foreach ($reservations as $reservation) {
            $grant = DB::table('pos_grants')->where('id', $reservation->grant_id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
            $quantity = bcadd((string) $reservation->quantity, '0', 4);
            if ($consume && ! $allowExpiredWaste) {
                app(LotRecallService::class)->assertAvailable($branchId, $grant->lot_id);
            }
            abort_unless($grant->epoch === $reservation->epoch && bccomp($quantity, '0', 4) > 0 && bccomp((string) $grant->reserved_quantity, $quantity, 4) >= 0 && bccomp(bcadd((string) $grant->consumed_quantity, (string) $grant->reserved_quantity, 4), (string) $grant->granted_quantity, 4) <= 0, 409, 'Kitchen reservation evidence conflict.');
            if ($consume) {
                abort_unless($grant->status === 'active' && ($allowExpiredWaste || ! $grant->expires_at || $grant->expires_at >= now($timezone)->toDateString()), 422, 'Reserved ingredient expired or unavailable.');
            }
            $update = ['reserved_quantity' => bcsub((string) $grant->reserved_quantity, $quantity, 4), 'updated_at' => now()];
            if ($consume) {
                $update['consumed_quantity'] = bcadd((string) $grant->consumed_quantity, $quantity, 4);
            }
            DB::table('pos_grants')->where('id', $grant->id)->update($update);
            DB::table('pos_kitchen_reservations')->where('id', $reservation->id)->update(['status' => $consume ? 'consumed' : 'released', 'updated_at' => now()]);
            $allocations[] = ['product_id' => $grant->product_id, 'grant_id' => $grant->id, 'epoch' => $grant->epoch, 'lot_id' => $grant->lot_id, 'quantity' => $quantity];
        }

        return $allocations;
    }

    private function lockBranch(string $branchId): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Entitlement allocation requires a transaction.');
        }
        DB::table('pos_branches')->where('id', $branchId)->where('active', true)->lockForUpdate()->firstOrFail();
    }
}
