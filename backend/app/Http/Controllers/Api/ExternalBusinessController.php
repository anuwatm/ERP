<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrderItem;
use App\Models\GoodsReceiptItem;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationItem;
use App\Support\ExternalApiResponse;
use App\Support\ExternalBusinessAccess;
use App\Support\ExternalPurchaseRead;
use App\Support\ExternalReadPagination;
use App\Support\ExternalUnits;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalBusinessController extends Controller
{
    public const RESOURCES = [
        'customers' => ['customers:read', ['id', 'customer_code', 'company_name', 'customer_type', 'status', 'updated_at']],
        'suppliers' => ['suppliers:read', ['id', 'supplier_code', 'name', 'status', 'updated_at']],
        'quotations' => ['quotations:read', ['id', 'quotation_no', 'customer_id', 'status', 'issue_date', 'valid_until', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'currency', 'updated_at']],
        'invoices' => ['invoices:read', ['id', 'invoice_no', 'customer_id', 'status', 'issue_date', 'due_date', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'paid_amount', 'balance_due', 'currency', 'updated_at']],
        'delivery-orders' => ['delivery-orders:read', ['id', 'do_no', 'invoice_id', 'status', 'delivery_date', 'delivered_at', 'updated_at']],
        'payments' => ['payments:read', ['id', 'invoice_id', 'entry_type', 'reversal_of_payment_id', 'payment_date', 'amount', 'currency', 'updated_at']],
        'purchase-orders' => ['purchase-orders:read', ['id', 'po_no', 'supplier_id', 'status', 'order_date', 'expected_date', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'currency', 'updated_at']],
        'goods-receipts' => ['goods-receipts:read', ['id', 'grn_no', 'purchase_order_id', 'received_date', 'status', 'currency', 'updated_at']],
        'projects' => ['projects:read', ['id', 'project_code', 'name', 'status', 'start_date', 'due_date', 'progress_percent', 'updated_at']],
        'tasks' => ['tasks:read', ['id', 'project_id', 'title', 'status', 'priority', 'due_date', 'completed_at', 'updated_at']],
    ];

    public const ITEMS = [
        'quotations' => [QuotationItem::class, 'quotation_id', ['id', 'product_id', 'quantity', 'unit', 'unit_price', 'discount_amount', 'tax_rate', 'line_total', 'updated_at']],
        'invoices' => [InvoiceItem::class, 'invoice_id', ['id', 'product_id', 'quantity', 'unit', 'unit_price', 'discount_amount', 'tax_rate', 'line_total', 'updated_at']],
        'delivery-orders' => [DeliveryOrderItem::class, 'delivery_order_id', ['id', 'product_id', 'quantity', 'unit', 'updated_at']],
        'purchase-orders' => [PurchaseOrderItem::class, 'purchase_order_id', ['id', 'product_id', 'quantity', 'unit', 'unit_price', 'discount_amount', 'tax_rate', 'line_total', 'updated_at']],
        'goods-receipts' => [GoodsReceiptItem::class, 'goods_receipt_id', ['id', 'purchase_order_item_id', 'product_id', 'quantity', 'unit', 'updated_at']],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        [$scope, $fields] = self::RESOURCES[$resource];
        $rules = in_array('status', $fields, true) ? ['status' => ['sometimes', 'string', 'max:30']] : [];
        $filters = ExternalReadPagination::filters($request, $rules);
        $query = ExternalBusinessAccess::query($request, $resource, $scope);
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['updated_since']) && method_exists($query->getModel(), 'bootSoftDeletes')) {
            $query->withTrashed();
            $fields[] = 'deleted_at';
        }

        return ExternalReadPagination::page($request, $query, $fields, $filters, fn ($row) => $this->serialize($row, $fields, $resource));
    }

    public function show(Request $request, string $id, string $resource): JsonResponse
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        [$scope, $fields] = self::RESOURCES[$resource];
        $record = ExternalBusinessAccess::query($request, $resource, $scope)->whereKey($id)->firstOrFail($fields);
        $data = $this->serialize($record, $fields, $resource);
        if (isset(self::ITEMS[$resource])) {
            [$query, $itemFields] = $this->itemQuery($request, $resource, $id);
            $items = $query->orderBy('id')->limit(101)->get($itemFields);
            $data['items'] = $items->take(100)->map(fn ($row) => $this->serializeItem($row, $itemFields, $resource))->all();
            $data['items_truncated'] = $items->count() > 100;
        }
        if ($resource === 'payments' && $record->reversal_of_payment_id) {
            $visible = ExternalBusinessAccess::query($request, 'payments', $scope)->whereKey($record->reversal_of_payment_id)->where('invoice_id', $record->invoice_id)->exists();
            if (! $visible) {
                $data['reversal_of_payment_id'] = null;
            }
        }

        return response()->json(['data' => $data, 'meta' => ['api_version' => 'v1',
            'request_id' => ExternalApiResponse::requestId($request), 'generated_at' => now()->toIso8601String()]]);
    }

    public function items(Request $request, string $id, string $resource): JsonResponse
    {
        abort_unless(isset(self::ITEMS[$resource]), 404);
        ExternalBusinessAccess::query($request, $resource, self::RESOURCES[$resource][0])->whereKey($id)->firstOrFail(['id']);
        $filters = ExternalReadPagination::filters($request, $resource === 'purchase-orders' ? ['updated_since' => ['prohibited']] : []);
        [$query, $fields] = $this->itemQuery($request, $resource, $id);

        return ExternalReadPagination::page($request, $query, $fields, $filters, fn ($row) => $this->serializeItem($row, $fields, $resource), $resource !== 'purchase-orders');
    }

    public function projectTasks(Request $request, string $id): JsonResponse
    {
        abort_unless(in_array('projects:read', $request->attributes->get('integration_key')->scopes, true), 403);
        ExternalBusinessAccess::query($request, 'projects', 'projects:read')->whereKey($id)->firstOrFail(['id']);
        $filters = ExternalReadPagination::filters($request);
        $query = ExternalBusinessAccess::query($request, 'tasks', 'tasks:read')->where('project_id', $id);

        return ExternalReadPagination::page($request, $query, self::RESOURCES['tasks'][1], $filters);
    }

    private function itemQuery(Request $request, string $resource, string $id): array
    {
        [$model, $parent, $fields] = self::ITEMS[$resource];
        $org = $request->attributes->get('integration_key')->org_id;
        $query = $model::where('org_id', $org)->where($parent, $id)
            ->where(fn ($q) => $q->whereNull('product_id')->orWhereIn('product_id', Product::withTrashed()->where('org_id', $org)->select('id')));
        if ($resource === 'purchase-orders') {
            $query->leftJoinSub(ExternalPurchaseRead::received($org), 'receipts', 'receipts.purchase_order_item_id', '=', 'purchase_order_items.id')
                ->select(array_map(fn ($field) => 'purchase_order_items.'.$field, $fields))
                ->selectRaw('COALESCE(receipts.received_quantity, 0) as received_quantity');
            $fields[] = 'received_quantity';
        }
        if ($resource === 'goods-receipts') {
            $query->whereExists(function ($parent) use ($org, $id) {
                $parent->selectRaw('1')->from('purchase_order_items as poi')->join('goods_receipts as gr', 'gr.purchase_order_id', '=', 'poi.purchase_order_id')
                    ->where('gr.id', $id)->where('gr.org_id', $org)->where('poi.org_id', $org)
                    ->whereColumn('poi.id', 'goods_receipt_items.purchase_order_item_id')
                    ->where(fn ($q) => $q->whereColumn('poi.product_id', 'goods_receipt_items.product_id')->orWhere(fn ($both) => $both->whereNull('poi.product_id')->whereNull('goods_receipt_items.product_id')))
                    ->where(fn ($q) => $q->whereRaw(ExternalUnits::expression('poi.unit').' = '.ExternalUnits::expression('goods_receipt_items.unit'))->orWhere(fn ($both) => $both->whereNull('poi.unit')->whereNull('goods_receipt_items.unit')));
            });
        }

        return [$query, $fields];
    }

    private function serialize($row, array $fields, string $resource): array
    {
        $data = ExternalReadPagination::fields($row, $fields);
        if ($resource === 'payments' && $row->reversal_of_payment_id) {
            $data['reversal_of_payment_id'] = $row->reversalOf?->invoice_id === $row->invoice_id ? $row->reversalOf->id : null;
        }

        return $data;
    }

    private function serializeItem($row, array $fields, string $resource): array
    {
        $data = ExternalReadPagination::fields($row, $fields);
        if ($resource === 'purchase-orders') {
            $received = BigDecimal::of((string) $row->received_quantity)->toScale(4, RoundingMode::HalfUp);
            $remaining = BigDecimal::of((string) $row->quantity)->minus($received);
            $data['received_quantity'] = (string) $received;
            $data['outstanding_quantity'] = (string) ($remaining->isNegative() ? BigDecimal::zero() : $remaining)->toScale(4, RoundingMode::HalfUp);
        }

        return $data;
    }
}
