<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ExternalApiResponse;
use App\Support\ExternalReadPagination;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExternalInventoryController extends Controller
{
    public function balances(Request $request): JsonResponse
    {
        $org = $request->attributes->get('integration_key')->org_id;
        $filters = ExternalReadPagination::filters($request, [
            'updated_since' => ['prohibited'],
            'product_id' => ['sometimes', 'uuid', Rule::exists('products', 'id')->where('org_id', $org)->whereNull('deleted_at')],
            'warehouse_id' => ['sometimes', 'uuid', Rule::exists('warehouses', 'id')->where('org_id', $org)],
            'inventory_lot_id' => ['sometimes', 'uuid', Rule::exists('inventory_lots', 'id')->where('org_id', $org)],
        ]);
        $query = $this->movements($org);
        foreach (['product_id', 'warehouse_id', 'inventory_lot_id'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where('s.'.$filter, $filters[$filter]);
            }
        }
        $contextFilters = $filters;
        unset($contextFilters['cursor']);
        ksort($contextFilters);
        $key = $request->attributes->get('integration_key');
        $context = hash('sha256', json_encode([$key->id, $key->scopes, $org, $request->path(), $contextFilters]));
        $columns = ['s.product_id', 's.warehouse_id', 's.warehouse_bin_id', 's.inventory_lot_id'];
        $expires = now()->addMinutes(30)->timestamp;
        if (isset($filters['cursor'])) {
            try {
                $cursor = json_decode(Crypt::decryptString($filters['cursor']), true, 16, JSON_THROW_ON_ERROR);
                if (($cursor['context'] ?? null) !== $context || ($cursor['expires'] ?? 0) <= now()->timestamp
                    || ! is_array($cursor['after'] ?? null) || count($cursor['after']) !== 4) {
                    throw new \RuntimeException;
                }
                $expires = $cursor['expires'];
                $after = $cursor['after'];
                $query->where(function ($page) use ($columns, $after) {
                    foreach ($columns as $i => $column) {
                        $page->orWhere(function ($clause) use ($columns, $after, $i, $column) {
                            for ($j = 0; $j < $i; $j++) {
                                $clause->whereRaw("COALESCE({$columns[$j]}, '') = ?", [$after[$j]]);
                            }
                            $clause->whereRaw("COALESCE($column, '') > ?", [$after[$i]]);
                        });
                    }
                });
            } catch (\Throwable) {
                throw ValidationException::withMessages(['cursor' => 'The cursor is invalid or expired.']);
            }
        }
        foreach ($columns as $column) {
            $query->orderByRaw("COALESCE($column, '') ASC");
        }
        $limit = (int) ($filters['limit'] ?? 50);
        $rows = $query->select([...$columns, 'l.lot_no', 'l.expires_at'])
            ->selectRaw('SUM(s.quantity) as on_hand')->groupBy([...$columns, 'l.lot_no', 'l.expires_at'])->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $last = $rows->last();
        $next = $more ? Crypt::encryptString(json_encode(['context' => $context, 'expires' => $expires,
            'after' => [$last->product_id, $last->warehouse_id ?? '', $last->warehouse_bin_id ?? '', $last->inventory_lot_id ?? '']])) : null;

        return response()->json(['data' => $rows->map(fn ($row) => [
            'product_id' => $row->product_id, 'warehouse_id' => $row->warehouse_id,
            'warehouse_bin_id' => $row->warehouse_bin_id, 'inventory_lot_id' => $row->inventory_lot_id,
            'lot_no' => $row->lot_no, 'expires_at' => $row->expires_at,
            'on_hand' => (string) BigDecimal::of((string) $row->on_hand)->toScale(4, RoundingMode::HalfUp),
        ])->values(), 'meta' => [
            'api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request),
            'as_of' => now()->toIso8601String(), 'next_cursor' => $next, 'limit' => $limit,
            'quantity_mode' => 'on_hand', 'consistency' => 'live', 'reconciliation_required' => true, 'delta_supported' => false,
        ]]);
    }

    public function summary(Request $request): JsonResponse
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $key = $request->attributes->get('integration_key');
        $balances = $this->movements($key->org_id)->select('s.product_id')->selectRaw('SUM(s.quantity) as on_hand')->groupBy('s.product_id');
        $row = Product::where('products.org_id', $key->org_id)->where('is_active', true)->where('track_inventory', true)
            ->leftJoinSub($balances, 'balances', 'balances.product_id', '=', 'products.id')
            ->selectRaw('COUNT(*) as tracked, SUM(CASE WHEN reorder_point > 0 AND COALESCE(on_hand, 0) < reorder_point THEN 1 ELSE 0 END) as low_stock, SUM(CASE WHEN COALESCE(on_hand, 0) < 0 THEN 1 ELSE 0 END) as negative_stock')->first();
        $asOf = now($key->organization->timezone ?: config('app.timezone'))->toIso8601String();

        return response()->json(['data' => ['snapshot' => [
            'as_of' => $asOf, 'active_tracked_products' => (int) $row->tracked,
            'low_stock_products' => (int) $row->low_stock, 'negative_stock_products' => (int) $row->negative_stock,
        ]], 'meta' => ['api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request),
            'generated_at' => $asOf, 'currency_mode' => 'not_applicable', 'visibility' => 'organization', 'freshness' => 'live']]);
    }

    private function movements(string $org): Builder
    {
        return DB::table('stock_movements as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->leftJoin('warehouse_bins as b', 'b.id', '=', 's.warehouse_bin_id')
            ->leftJoin('inventory_lots as l', 'l.id', '=', 's.inventory_lot_id')
            ->where('s.org_id', $org)->where('p.org_id', $org)->whereNull('p.deleted_at')
            ->where(fn ($q) => $q->whereNull('s.warehouse_id')->orWhere('w.org_id', $org))
            ->where(fn ($q) => $q->whereNull('s.warehouse_bin_id')->orWhere(fn ($bin) => $bin->where('b.org_id', $org)->whereColumn('b.warehouse_id', 's.warehouse_id')))
            ->where(fn ($q) => $q->whereNull('s.inventory_lot_id')->orWhere(fn ($lot) => $lot->where('l.org_id', $org)->whereColumn('l.product_id', 's.product_id')));
    }
}
