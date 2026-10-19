<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Support\ExternalApiResponse;
use App\Support\ExternalBusinessAccess;
use App\Support\ExternalPeriod;
use App\Support\ExternalPurchaseRead;
use App\Support\ExternalReadPagination;
use App\Support\ExternalUnits;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalProcurementSummaryController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $filters = ExternalReadPagination::filters($request, array_merge(ExternalPeriod::rules(), [
            'limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited'],
        ]));
        [$start, $end, $zone, $now] = ExternalPeriod::resolve($request, $filters);
        $key = $request->attributes->get('integration_key');
        $orders = ExternalBusinessAccess::query($request, 'purchase-orders', 'summary:procurement:read');
        $period = (clone $orders)->whereBetween('order_date', [$start, $end])->select('status', 'currency')
            ->selectRaw('COUNT(*) as records_count, SUM(total) as amount')->groupBy('status', 'currency')->orderBy('status')->orderBy('currency')->get()
            ->map(fn ($row) => ['status' => $row->status, 'currency' => $row->currency ?: 'UNSPECIFIED', 'count' => (int) $row->records_count,
                'amount' => (string) BigDecimal::of((string) $row->amount)->toScale(2, RoundingMode::HalfUp)]);
        $items = PurchaseOrderItem::where('purchase_order_items.org_id', $key->org_id)
            ->whereIn('purchase_order_id', (clone $orders)->whereIn('status', ['sent', 'approved', 'partially_received'])->select('id'))
            ->where(fn ($q) => $q->whereNull('product_id')->orWhereIn('product_id', Product::withTrashed()->where('org_id', $key->org_id)->select('id')))
            ->leftJoinSub(ExternalPurchaseRead::received($key->org_id), 'receipts', 'receipts.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->selectRaw(ExternalUnits::expression('unit').' as unit')->selectRaw('COUNT(*) as lines_count, SUM(quantity) as ordered_quantity, SUM(COALESCE(received_quantity, 0)) as received_quantity, SUM(CASE WHEN quantity > COALESCE(received_quantity, 0) THEN quantity - COALESCE(received_quantity, 0) ELSE 0 END) as outstanding_quantity')
            ->groupByRaw(ExternalUnits::expression('unit'))->orderByRaw(ExternalUnits::expression('unit'))->limit(101)->get();
        $quantities = $items->take(100)->map(function ($row) {
            $data = ['unit' => $row->unit, 'lines_count' => (int) $row->lines_count];
            foreach (['ordered_quantity', 'received_quantity', 'outstanding_quantity'] as $field) {
                $data[$field] = (string) BigDecimal::of((string) $row->$field)->toScale(4, RoundingMode::HalfUp);
            }

            return $data;
        });

        return response()->json(['data' => ['period' => ['orders_by_status_and_currency' => $period],
            'snapshot' => ['as_of' => $now->toIso8601String(), 'open_order_quantities_by_unit' => $quantities, 'units_truncated' => $items->count() > 100]],
            'meta' => ['api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request),
                'generated_at' => $now->toIso8601String(), 'period' => ['start_date' => $start, 'end_date' => $end],
                'timezone' => $zone, 'currency_mode' => 'original_currency', 'visibility' => 'organization', 'freshness' => 'live']]);
    }
}
