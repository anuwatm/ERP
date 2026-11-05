<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;

/** Internal checkout capability. HTTP checkout never deserializes this object. */
final class OfflineSaleAuthority
{
    public function __construct(public readonly string $permitId, public readonly array $evidence) {}

    public function consume(string $branchId, string $productId, string $quantity, string $timezone): array
    {
        abort_unless(DB::transactionLevel() > 0, 409);
        $remaining = $quantity;
        $result = [];
        foreach (DB::table('pos_offline_reservations')->where('permit_id', $this->permitId)->where('product_id', $productId)->where('status', 'reserved')->orderBy('grant_id')->lockForUpdate()->get() as $reservation) {
            $available = bcsub($reservation->quantity, $reservation->consumed_quantity, 4);
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }
            if (bccomp($available, '0', 4) <= 0) {
                continue;
            }
            $take = bccomp($remaining, $available, 4) < 0 ? $remaining : $available;
            $grant = DB::table('pos_grants')->where('id', $reservation->grant_id)->where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->firstOrFail();
            app(LotRecallService::class)->assertAvailable($branchId, $grant->lot_id);
            abort_unless($grant->status === 'active' && $grant->epoch === $reservation->epoch && (! $grant->expires_at || $grant->expires_at >= now($timezone)->toDateString()), 422, 'Offline lot is expired, recalled or unavailable; retain evidence for review.');
            abort_unless(bccomp($grant->reserved_quantity, $take, 4) >= 0 && bccomp(bcadd($grant->reserved_quantity, $grant->consumed_quantity, 4), $grant->granted_quantity, 4) <= 0, 409, 'Offline reservation counters conflict.');
            DB::table('pos_grants')->where('id', $grant->id)->update(['reserved_quantity' => bcsub($grant->reserved_quantity, $take, 4), 'consumed_quantity' => bcadd($grant->consumed_quantity, $take, 4), 'updated_at' => now()]);
            DB::table('pos_offline_reservations')->where('id', $reservation->id)->update(['consumed_quantity' => bcadd($reservation->consumed_quantity, $take, 4), 'updated_at' => now()]);
            $result[] = ['grant_id' => $grant->id, 'epoch' => $grant->epoch, 'lot_id' => $grant->lot_id, 'quantity' => $take];
            $remaining = bcsub($remaining, $take, 4);
        }
        abort_unless(bccomp($remaining, '0', 4) === 0, 422, 'Offline stock quota exceeded.');

        return $result;
    }
}
