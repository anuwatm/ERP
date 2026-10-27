<?php

namespace App\Services;

use App\Models\BillOfMaterials;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProductionCostingService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly FinancialJournalService $journals,
    ) {}

    /**
     * Calculate and synchronize total BOM cost from items, labor, and overhead.
     */
    public function calculateBomCost(BillOfMaterials $bom): array
    {
        $bom->loadMissing(['items.product', 'items.subBom']);

        $materialCost = 0.0;

        foreach ($bom->items as $item) {
            $qty = (float) $item->quantity;
            $waste = (float) $item->waste_percent;
            $unitCost = (float) $item->unit_cost;

            // If sub-BOM is linked and item unit cost is not explicitly set, use sub-BOM's unit cost
            if ($item->sub_bom_id && $item->subBom && $unitCost <= 0.0) {
                $subCost = (float) $item->subBom->total_cost;
                $subQty = (float) $item->subBom->quantity ?: 1.0;
                $unitCost = round($subCost / $subQty, 2);
                $item->unit_cost = $unitCost;
            }

            $effectiveQty = $qty * (1.0 + ($waste / 100.0));
            $itemTotal = round($effectiveQty * $unitCost, 2);
            $item->total_cost = $itemTotal;
            $item->save();

            $materialCost += $itemTotal;
        }

        $laborCost = (float) $bom->labor_cost;
        $overheadCost = (float) $bom->overhead_cost;
        $totalCost = round($materialCost + $laborCost + $overheadCost, 2);
        $batchQty = (float) $bom->quantity ?: 1.0;
        $unitCost = round($totalCost / $batchQty, 2);

        $bom->total_cost = $totalCost;
        $bom->save();

        return [
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $totalCost,
            'unit_cost' => $unitCost,
        ];
    }

    /**
     * Recursively explode multi-level Bill of Materials tree with circular dependency guard.
     */
    public function explode(BillOfMaterials $bom, float $batchMultiplier = 1.0, int $level = 0, array $visited = []): array
    {
        if (in_array($bom->id, $visited, true)) {
            throw new DomainException("Circular BOM dependency detected for BOM {$bom->bom_no}.");
        }

        $visited[] = $bom->id;
        $bom->loadMissing(['items.product', 'items.subBom.items.product']);

        $nodes = [];
        $bomBatchQty = (float) $bom->quantity ?: 1.0;
        $scale = $batchMultiplier / $bomBatchQty;

        foreach ($bom->items as $item) {
            $qty = (float) $item->quantity;
            $waste = (float) $item->waste_percent;
            $effectivePerBatch = $qty * (1.0 + ($waste / 100.0));
            $requiredQty = round($effectivePerBatch * $scale, 4);
            $unitCost = (float) $item->unit_cost;
            $totalCost = round($requiredQty * $unitCost, 2);

            $subExplosion = null;
            if ($item->sub_bom_id && $item->subBom) {
                $subExplosion = $this->explode($item->subBom, $requiredQty, $level + 1, $visited);
            }

            $nodes[] = [
                'level' => $level,
                'bom_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_sku' => $item->product?->sku ?? '',
                'product_name' => $item->product?->name ?? '',
                'unit' => $item->unit,
                'quantity_per_batch' => $qty,
                'waste_percent' => $waste,
                'required_quantity' => $requiredQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'is_subassembly' => (bool) $item->sub_bom_id,
                'sub_bom' => $subExplosion,
            ];
        }

        return [
            'bom_id' => $bom->id,
            'bom_no' => $bom->bom_no,
            'name' => $bom->name,
            'version' => $bom->version,
            'product_id' => $bom->product_id,
            'product_name' => $bom->product?->name ?? '',
            'level' => $level,
            'batch_quantity' => round($batchMultiplier, 4),
            'items' => $nodes,
        ];
    }

    /**
     * Flatten multi-level exploded items into consolidated raw material requirements.
     */
    public function flattenExplodedMaterials(array $explodedTree): array
    {
        $flattened = [];
        $this->collectLeaves($explodedTree, $flattened);

        return array_values($flattened);
    }

    private function collectLeaves(array $node, array &$collector): void
    {
        foreach ($node['items'] ?? [] as $item) {
            if ($item['is_subassembly'] && ! empty($item['sub_bom'])) {
                $this->collectLeaves($item['sub_bom'], $collector);
            } else {
                $prodId = $item['product_id'];
                if (! isset($collector[$prodId])) {
                    $collector[$prodId] = [
                        'product_id' => $prodId,
                        'product_sku' => $item['product_sku'],
                        'product_name' => $item['product_name'],
                        'unit' => $item['unit'],
                        'unit_cost' => (float) $item['unit_cost'],
                        'required_quantity' => 0.0,
                        'total_cost' => 0.0,
                    ];
                }
                $collector[$prodId]['required_quantity'] += (float) $item['required_quantity'];
                $collector[$prodId]['total_cost'] += (float) $item['total_cost'];
            }
        }
    }

    /**
     * Create Work Order and initialize work_order_items scaled from BOM.
     */
    public function createWorkOrder(array $data, ?string $userId): WorkOrder
    {
        return DB::transaction(function () use ($data, $userId): WorkOrder {
            $bom = BillOfMaterials::with(['items.product'])->where('org_id', $data['org_id'])->findOrFail($data['bill_of_materials_id']);

            $plannedQty = (float) $data['planned_quantity'];
            if ($plannedQty <= 0) {
                throw new InvalidArgumentException('Planned quantity must be greater than zero.');
            }

            $bomQty = (float) $bom->quantity ?: 1.0;
            $scale = $plannedQty / $bomQty;

            $woNo = $data['wo_no'] ?? $this->numbers->next($data['org_id'], 'work_order');

            $estimatedLabor = round((float) $bom->labor_cost * $scale, 2);
            $estimatedOverhead = round((float) $bom->overhead_cost * $scale, 2);

            $workOrder = WorkOrder::create([
                'org_id' => $data['org_id'],
                'wo_no' => $woNo,
                'bill_of_materials_id' => $bom->id,
                'product_id' => $bom->product_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'source_warehouse_id' => $data['source_warehouse_id'] ?? null,
                'planned_quantity' => $plannedQty,
                'actual_quantity' => 0,
                'scrap_quantity' => 0,
                'status' => 'draft',
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'labor_cost' => $estimatedLabor,
                'overhead_cost' => $estimatedOverhead,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $flattenMaterials = (bool) ($data['flatten_materials'] ?? false);
            $totalMaterial = 0.0;

            if ($flattenMaterials) {
                $exploded = $this->explode($bom, $plannedQty);
                $leaves = $this->flattenExplodedMaterials($exploded);
                foreach ($leaves as $leaf) {
                    $itemPlannedQty = round((float) $leaf['required_quantity'], 4);
                    $unitCost = (float) $leaf['unit_cost'];
                    $itemTotal = round((float) $leaf['total_cost'], 2);
                    $totalMaterial += $itemTotal;

                    WorkOrderItem::create([
                        'org_id' => $data['org_id'],
                        'work_order_id' => $workOrder->id,
                        'product_id' => $leaf['product_id'],
                        'planned_quantity' => $itemPlannedQty,
                        'issued_quantity' => 0,
                        'unit' => $leaf['unit'],
                        'unit_cost' => $unitCost,
                        'total_cost' => $itemTotal,
                    ]);
                }
            } else {
                foreach ($bom->items as $bomItem) {
                    $itemPlannedQty = round((float) $bomItem->quantity * $scale * (1.0 + ((float) $bomItem->waste_percent / 100.0)), 4);
                    $unitCost = (float) $bomItem->unit_cost;
                    $itemTotal = round($itemPlannedQty * $unitCost, 2);
                    $totalMaterial += $itemTotal;

                    WorkOrderItem::create([
                        'org_id' => $data['org_id'],
                        'work_order_id' => $workOrder->id,
                        'product_id' => $bomItem->product_id,
                        'planned_quantity' => $itemPlannedQty,
                        'issued_quantity' => 0,
                        'unit' => $bomItem->unit,
                        'unit_cost' => $unitCost,
                        'total_cost' => $itemTotal,
                    ]);
                }
            }

            $totalCost = round($totalMaterial + $estimatedLabor + $estimatedOverhead, 2);
            $workOrder->update([
                'material_cost' => $totalMaterial,
                'total_cost' => $totalCost,
                'unit_cost' => round($totalCost / $plannedQty, 2),
            ]);

            return $workOrder->load(['items.product', 'billOfMaterials', 'product']);
        });
    }

    /**
     * Transition Work Order lifecycle status with automatic stock movements and GL journal entries.
     */
    public function transitionStatus(WorkOrder $workOrder, string $newStatus, array $payload = [], ?string $userId = null): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $newStatus, $payload, $userId): WorkOrder {
            $workOrder = WorkOrder::where('org_id', $workOrder->org_id)->whereKey($workOrder->id)->lockForUpdate()->with('items')->firstOrFail();
            app(InventoryMovementService::class)->lockProducts($workOrder->org_id, [...$workOrder->items->pluck('product_id')->all(), $workOrder->product_id]);
            $currentStatus = $workOrder->status;

            if ($currentStatus === $newStatus) {
                return $workOrder;
            }

            $allowedTransitions = [
                'draft' => ['planned', 'in_progress', 'cancelled'],
                'planned' => ['in_progress', 'cancelled'],
                'in_progress' => ['quality_check', 'cancelled'],
                'quality_check' => ['completed', 'in_progress', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
            ];

            if (! in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
                throw new DomainException("Invalid work order status transition from {$currentStatus} to {$newStatus}.");
            }

            switch ($newStatus) {
                case 'planned':
                    $workOrder->status = 'planned';
                    if (! empty($payload['start_date'])) {
                        $workOrder->start_date = $payload['start_date'];
                    }
                    if (! empty($payload['due_date'])) {
                        $workOrder->due_date = $payload['due_date'];
                    }
                    $workOrder->updated_by = $userId;
                    $workOrder->save();
                    break;

                case 'in_progress':
                    if (empty($workOrder->source_warehouse_id)) {
                        throw ValidationException::withMessages([
                            'source_warehouse_id' => 'Source warehouse must be assigned before releasing work order.',
                        ]);
                    }

                    $workOrder->status = 'in_progress';
                    $workOrder->start_date = $payload['start_date'] ?? $workOrder->start_date ?? today();
                    $workOrder->updated_by = $userId;

                    $workOrder->loadMissing('items.product');
                    $workOrderItemIds = $workOrder->items->pluck('id')->all();

                    if (! empty($payload['issued_items'])) {
                        foreach ($payload['issued_items'] as $itemId => $qty) {
                            if (! in_array($itemId, $workOrderItemIds, true)) {
                                throw ValidationException::withMessages([
                                    'issued_items' => "Invalid item ID [{$itemId}] does not belong to this work order.",
                                ]);
                            }
                            if (! is_numeric($qty) || (float) $qty <= 0) {
                                throw ValidationException::withMessages([
                                    'issued_items' => "Issued quantity for item [{$itemId}] must be greater than zero.",
                                ]);
                            }
                        }
                    }

                    // Stock availability guard with row locking
                    foreach ($workOrder->items as $item) {
                        $issueQty = isset($payload['issued_items'][$item->id])
                            ? (float) $payload['issued_items'][$item->id]
                            : (float) $item->planned_quantity;

                        $onHand = (float) StockMovement::where('org_id', $workOrder->org_id)
                            ->where('warehouse_id', $workOrder->source_warehouse_id)
                            ->where('product_id', $item->product_id)
                            ->lockForUpdate()
                            ->sum('quantity');

                        if ($onHand < $issueQty) {
                            $sku = $item->product?->sku ?? "Product #{$item->product_id}";
                            throw ValidationException::withMessages([
                                'stock' => "Insufficient stock in source warehouse for [{$sku}]. On-hand: {$onHand}, required: {$issueQty}.",
                            ]);
                        }
                    }

                    // Deduct stock for issued raw materials
                    $actualMaterialCost = 0.0;
                    foreach ($workOrder->items as $item) {
                        $issueQty = isset($payload['issued_items'][$item->id])
                            ? (float) $payload['issued_items'][$item->id]
                            : (float) $item->planned_quantity;

                        $item->issued_quantity = number_format($issueQty, 4, '.', '');
                        $itemTotal = round($issueQty * (float) $item->unit_cost, 2);
                        $item->total_cost = number_format($itemTotal, 2, '.', '');
                        $item->save();

                        $actualMaterialCost += $itemTotal;

                        // Create StockMovement out for raw material
                        app(InventoryMovementService::class)->record([
                            'org_id' => $workOrder->org_id,
                            'product_id' => $item->product_id,
                            'warehouse_id' => $workOrder->source_warehouse_id,
                            'work_order_id' => $workOrder->id,
                            'movement_type' => 'work_order_issue',
                            'movement_date' => $workOrder->start_date ?: today(),
                            'quantity' => -1 * $issueQty,
                            'unit_cost' => $item->unit_cost,
                            'total_cost' => -1 * $itemTotal,
                            'currency' => 'THB',
                            'base_currency' => 'THB',
                            'exchange_rate' => 1,
                            'base_unit_cost' => $item->unit_cost,
                            'base_total_cost' => -1 * $itemTotal,
                            'note' => "Raw materials issued for {$workOrder->wo_no}",
                            'created_by' => $userId,
                        ]);
                    }

                    $workOrder->material_cost = number_format($actualMaterialCost, 2, '.', '');
                    $totalCost = round($actualMaterialCost + (float) $workOrder->labor_cost + (float) $workOrder->overhead_cost, 2);
                    $workOrder->total_cost = number_format($totalCost, 2, '.', '');
                    $plannedQty = (float) $workOrder->planned_quantity ?: 1.0;
                    $workOrder->unit_cost = number_format(round($totalCost / $plannedQty, 2), 2, '.', '');
                    $workOrder->save();

                    // GL Posting: Dr. 1160 (WIP) / Cr. 1140 (Raw Materials)
                    $this->journals->postWorkOrderMaterialIssue($workOrder, $userId);
                    break;

                case 'quality_check':
                    $workOrder->status = 'quality_check';
                    $actualQty = isset($payload['actual_quantity']) ? (float) $payload['actual_quantity'] : (float) $workOrder->planned_quantity;
                    $scrapQty = isset($payload['scrap_quantity']) ? (float) $payload['scrap_quantity'] : 0.0;
                    $workOrder->actual_quantity = number_format($actualQty, 4, '.', '');
                    $workOrder->scrap_quantity = number_format($scrapQty, 4, '.', '');
                    $workOrder->qc_notes = $payload['qc_notes'] ?? $workOrder->qc_notes;
                    $workOrder->qc_passed = isset($payload['qc_passed']) ? (bool) $payload['qc_passed'] : false;
                    $workOrder->updated_by = $userId;
                    $workOrder->save();
                    break;

                case 'completed':
                    if (empty($workOrder->warehouse_id)) {
                        throw ValidationException::withMessages([
                            'warehouse_id' => 'Destination warehouse must be assigned before completing work order.',
                        ]);
                    }

                    if (! $workOrder->qc_passed) {
                        throw ValidationException::withMessages([
                            'qc' => 'Work order cannot be completed because quality check has not passed.',
                        ]);
                    }

                    $workOrder->status = 'completed';
                    if ((float) $workOrder->actual_quantity <= 0) {
                        $actualQty = isset($payload['actual_quantity']) ? (float) $payload['actual_quantity'] : (float) $workOrder->planned_quantity;
                        $workOrder->actual_quantity = number_format($actualQty, 4, '.', '');
                    }
                    $workOrder->completed_date = $payload['completed_date'] ?? today();
                    $workOrder->updated_by = $userId;

                    $actualGoodQty = (float) $workOrder->actual_quantity;
                    $totalProductionCost = round((float) $workOrder->material_cost + (float) $workOrder->labor_cost + (float) $workOrder->overhead_cost, 2);
                    $workOrder->total_cost = number_format($totalProductionCost, 2, '.', '');
                    $unitCost = $actualGoodQty > 0 ? round($totalProductionCost / $actualGoodQty, 2) : 0.0;
                    $workOrder->unit_cost = number_format($unitCost, 2, '.', '');
                    $workOrder->save();

                    // Create StockMovement in for finished goods received
                    if ($actualGoodQty > 0) {
                        app(InventoryMovementService::class)->record([
                            'org_id' => $workOrder->org_id,
                            'product_id' => $workOrder->product_id,
                            'warehouse_id' => $workOrder->warehouse_id,
                            'work_order_id' => $workOrder->id,
                            'movement_type' => 'work_order_receipt',
                            'movement_date' => $workOrder->completed_date,
                            'quantity' => $actualGoodQty,
                            'unit_cost' => $workOrder->unit_cost,
                            'total_cost' => $totalProductionCost,
                            'currency' => 'THB',
                            'base_currency' => 'THB',
                            'exchange_rate' => 1,
                            'base_unit_cost' => $workOrder->unit_cost,
                            'base_total_cost' => $totalProductionCost,
                            'note' => "Finished goods received from {$workOrder->wo_no}",
                            'created_by' => $userId,
                        ]);
                    }

                    // GL Posting: Dr. 1140 (Finished Goods) / Cr. 1160 (WIP) and absorbed labor/overhead
                    $this->journals->postWorkOrderCompletion($workOrder, $userId);
                    break;

                case 'cancelled':
                    $previousStatus = $workOrder->status;
                    $workOrder->status = 'cancelled';
                    $workOrder->updated_by = $userId;
                    $workOrder->save();

                    // If materials were issued, reverse stock movements and reverse GL
                    if (in_array($previousStatus, ['in_progress', 'quality_check'], true)) {
                        $workOrder->loadMissing('items');
                        foreach ($workOrder->items as $item) {
                            if ((float) $item->issued_quantity > 0) {
                                app(InventoryMovementService::class)->record([
                                    'org_id' => $workOrder->org_id,
                                    'product_id' => $item->product_id,
                                    'warehouse_id' => $workOrder->source_warehouse_id,
                                    'work_order_id' => $workOrder->id,
                                    'movement_type' => 'work_order_return',
                                    'movement_date' => today(),
                                    'quantity' => (float) $item->issued_quantity,
                                    'unit_cost' => $item->unit_cost,
                                    'total_cost' => round((float) $item->issued_quantity * (float) $item->unit_cost, 2),
                                    'currency' => 'THB',
                                    'base_currency' => 'THB',
                                    'exchange_rate' => 1,
                                    'base_unit_cost' => $item->unit_cost,
                                    'base_total_cost' => round((float) $item->issued_quantity * (float) $item->unit_cost, 2),
                                    'note' => "Raw materials returned from cancelled {$workOrder->wo_no}",
                                    'created_by' => $userId,
                                ]);
                            }
                        }

                        $this->journals->reverseWorkOrder($workOrder, $userId, today()->toDateString());
                    }
                    break;
            }

            return $workOrder->fresh(['items.product', 'billOfMaterials', 'product', 'productionCosts']);
        });
    }
}
