<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/GoodsReceipts', [
            'goodsReceipts' => GoodsReceipt::where('org_id', $orgId)
                ->with(['purchaseOrder:id,po_no,supplier_id,status', 'purchaseOrder.supplier:id,name', 'items'])
                ->latest('received_date')
                ->latest()
                ->get(),
            'purchaseOrders' => PurchaseOrder::where('org_id', $orgId)
                ->whereIn('status', ['approved', 'partially_received'])
                ->with(['supplier:id,name', 'items'])
                ->orderBy('po_no')
                ->get()
                ->map(fn (PurchaseOrder $po) => [
                    'id' => $po->id,
                    'po_no' => $po->po_no,
                    'supplier' => $po->supplier,
                    'status' => $po->status,
                    'items' => $po->items->map(fn (PurchaseOrderItem $item) => [
                        'id' => $item->id,
                        'description' => $item->description,
                        'product_id' => $item->product_id,
                        'quantity' => $this->money4($item->quantity),
                        'received_quantity' => $this->money4($this->receivedQuantity($item->id, $orgId)),
                        'remaining_quantity' => $this->money4(max(0, (float) $item->quantity - $this->receivedQuantity($item->id, $orgId))),
                        'unit' => $item->unit,
                        'unit_price' => $this->money($item->unit_price),
                        'tax_rate' => $this->money($item->tax_rate),
                    ]),
                ]),
            'products' => Product::where('org_id', $orgId)
                ->where('type', 'product')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'unit', 'cost']),
            'stockSummary' => StockMovement::where('org_id', $orgId)
                ->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(quantity) as on_hand, SUM(total_cost) as inventory_value')
                ->with('product:id,sku,name,unit')
                ->groupBy('product_id')
                ->get()
                ->map(function (StockMovement $movement) {
                    $onHand = (float) $movement->on_hand;
                    $value = (float) $movement->inventory_value;
                    $movement->setAttribute('on_hand', $this->money4($onHand));
                    $movement->setAttribute('inventory_value', $this->money($value));
                    $movement->setAttribute('average_cost', $this->money($onHand > 0 ? $value / $onHand : 0));

                    return $movement;
                }),
            'stockMovements' => StockMovement::where('org_id', $orgId)
                ->with('product:id,sku,name,unit')
                ->latest('movement_date')
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'uuid', Rule::exists('purchase_orders', 'id')->where('org_id', $orgId)],
            'received_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', Rule::exists('purchase_order_items', 'id')->where('org_id', $orgId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $po = PurchaseOrder::where('org_id', $orgId)->with('items')->findOrFail($validated['purchase_order_id']);
        abort_unless(in_array($po->status, ['approved', 'partially_received'], true), 422, 'Only approved purchase orders can be received.');

        $receipt = DB::transaction(function () use ($request, $numbers, $orgId, $validated, $po): GoodsReceipt {
            $receipt = GoodsReceipt::create([
                'org_id' => $orgId,
                'purchase_order_id' => $po->id,
                'grn_no' => $numbers->next($orgId, 'goods_receipt'),
                'received_date' => $validated['received_date'],
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $itemData) {
                $poItem = $po->items->firstWhere('id', $itemData['purchase_order_item_id']);
                abort_unless($poItem, 422, 'Item does not belong to purchase order.');

                $remaining = (float) $poItem->quantity - $this->receivedQuantity($poItem->id, $orgId);
                $quantity = round((float) $itemData['quantity'], 4);
                abort_if($quantity > $remaining, 422, 'Receive quantity cannot exceed remaining purchase order quantity.');
                $lineBase = round($quantity * (float) $poItem->unit_price, 2);
                $taxRate = round((float) $poItem->tax_rate, 2);
                $taxAmount = match ($po->tax_mode) {
                    'inclusive' => $taxRate > 0 ? round($lineBase - ($lineBase / (1 + $taxRate / 100)), 2) : 0.0,
                    'exclusive' => round($lineBase * $taxRate / 100, 2),
                    default => 0.0,
                };
                $lineTotal = $po->tax_mode === 'exclusive' ? round($lineBase + $taxAmount, 2) : $lineBase;

                GoodsReceiptItem::create([
                    'org_id' => $orgId,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'description' => $poItem->description,
                    'quantity' => $quantity,
                    'unit' => $poItem->unit,
                    'unit_cost' => $poItem->unit_price,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineTotal,
                ]);

                StockMovement::create([
                    'org_id' => $orgId,
                    'product_id' => $poItem->product_id,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_id' => $po->id,
                    'movement_type' => 'receive_from_po',
                    'movement_date' => $validated['received_date'],
                    'quantity' => $quantity,
                    'unit_cost' => $poItem->unit_price,
                    'total_cost' => round($quantity * (float) $poItem->unit_price, 2),
                    'created_by' => $request->user()->id,
                ]);

                if ($poItem->product_id) {
                    Product::where('org_id', $orgId)->whereKey($poItem->product_id)->update(['track_inventory' => true]);
                }
            }

            $po->update(['status' => $this->poReceiptStatus($po->fresh('items'), $orgId)]);

            return $receipt;
        });

        return back()->with('success', "Goods receipt {$receipt->grn_no} posted.");
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('org_id', $orgId)->where('type', 'product')],
            'movement_type' => ['required', Rule::in(['adjustment_in', 'adjustment_out', 'return_to_supplier'])],
            'movement_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $quantity = round((float) $validated['quantity'], 4);
        $isOutbound = in_array($validated['movement_type'], ['adjustment_out', 'return_to_supplier'], true);
        $signedQuantity = $isOutbound ? -$quantity : $quantity;
        $onHand = $this->onHand($validated['product_id'], $orgId);
        abort_if($isOutbound && $quantity > $onHand, 422, 'Stock movement cannot make on-hand quantity negative.');

        $unitCost = filled($validated['unit_cost'] ?? null)
            ? round((float) $validated['unit_cost'], 2)
            : $this->averageCost($validated['product_id'], $orgId);

        StockMovement::create([
            'org_id' => $orgId,
            'product_id' => $validated['product_id'],
            'movement_type' => $validated['movement_type'],
            'movement_date' => $validated['movement_date'],
            'quantity' => $signedQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($signedQuantity * $unitCost, 2),
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        Product::where('org_id', $orgId)->whereKey($validated['product_id'])->update(['track_inventory' => true]);

        return back()->with('success', 'Stock movement posted.');
    }

    private function poReceiptStatus(PurchaseOrder $po, string $orgId): string
    {
        $allReceived = $po->items->every(fn (PurchaseOrderItem $item) => $this->receivedQuantity($item->id, $orgId) >= (float) $item->quantity);

        return $allReceived ? 'received' : 'partially_received';
    }

    private function receivedQuantity(string $poItemId, string $orgId): float
    {
        return (float) GoodsReceiptItem::where('org_id', $orgId)->where('purchase_order_item_id', $poItemId)->sum('quantity');
    }

    private function onHand(string $productId, string $orgId): float
    {
        return (float) StockMovement::where('org_id', $orgId)->where('product_id', $productId)->sum('quantity');
    }

    private function averageCost(string $productId, string $orgId): float
    {
        $summary = StockMovement::where('org_id', $orgId)
            ->where('product_id', $productId)
            ->selectRaw('SUM(quantity) as on_hand, SUM(total_cost) as inventory_value')
            ->first();
        $onHand = (float) ($summary?->on_hand ?? 0);

        return $onHand > 0 ? round((float) $summary->inventory_value / $onHand, 2) : 0.0;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function money4(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
