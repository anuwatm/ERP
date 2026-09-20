<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\WorkflowApproval;
use App\Support\LowStockLocations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $canPo = $user->hasPermissionCode('purchase_orders.view');
        $canInventory = $user->hasPermissionCode('inventory.view');
        $canWorkflow = $user->hasPermissionCode('workflows.approve');
        abort_unless($canPo || $canInventory || $canWorkflow, 403);

        $purchaseOrders = PurchaseOrder::where('org_id', $user->org_id);
        $openStatuses = ['sent', 'approved', 'partially_received'];
        $openPos = $canPo ? (clone $purchaseOrders)->whereIn('status', $openStatuses)
            ->with('supplier:id,name')->latest('order_date')->limit(10)
            ->get(['id', 'org_id', 'supplier_id', 'po_no', 'status', 'total', 'currency', 'order_date']) : collect();
        $supplierValues = $canPo ? (clone $purchaseOrders)->whereNotIn('status', ['draft', 'cancelled'])
            ->selectRaw('supplier_id, currency, SUM(total) as committed_total')
            ->groupBy('supplier_id', 'currency')->with('supplier:id,name')->get() : collect();
        $pendingQuery = $canWorkflow
            ? WorkflowApproval::where('status', 'pending')
                ->where('assigned_user_id', $user->id)
                ->whereHas('instance', fn ($query) => $query->where('org_id', $user->org_id)->where('status', 'pending'))
            : null;
        $pendingCount = $pendingQuery ? (clone $pendingQuery)->count() : null;
        $pendingApprovals = $pendingQuery ? (clone $pendingQuery)
            ->with('instance:id,org_id,subject_type,subject_id,subject_snapshot,status')
            ->latest()->limit(10)->get() : collect();
        $receipts = $canInventory
            ? GoodsReceipt::where('org_id', $user->org_id)
                ->with('purchaseOrder:id,po_no')
                ->latest('received_date')->limit(10)->get(['id', 'org_id', 'purchase_order_id', 'grn_no', 'received_date', 'status'])
            : collect();
        $lowStock = $canInventory ? LowStockLocations::query($user->org_id) : null;

        return Inertia::render('Finance/ProcurementDashboard', [
            'can' => ['po' => $canPo, 'inventory' => $canInventory, 'workflow' => $canWorkflow, 'workflowLink' => $user->hasPermissionCode('workflows.view')],
            'openPoCount' => $canPo ? (clone $purchaseOrders)->whereIn('status', $openStatuses)->count() : null,
            'pendingApprovalCount' => $pendingCount,
            'poStatus' => $canPo ? (clone $purchaseOrders)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status') : null,
            'pendingApprovals' => $canWorkflow ? $pendingApprovals->map(fn ($approval) => [
                'id' => $approval->id,
                'subject_type' => $approval->instance?->subject_type,
                'subject_id' => $approval->instance?->subject_id,
            ])->values() : null,
            'openPos' => $canPo ? $openPos->map(fn ($po) => [
                'id' => $po->id, 'po_no' => $po->po_no, 'status' => $po->status,
                'supplier' => $po->supplier?->name, 'total' => $po->total, 'currency' => $po->currency,
            ])->values() : null,
            'supplierValues' => $canPo ? $supplierValues
                ->map(fn ($group) => [
                    'supplier_id' => $group->supplier_id,
                    'supplier' => $group->supplier?->name ?? 'Unknown supplier',
                    'currency' => $group->currency,
                    'total' => (float) $group->committed_total,
                ])->values() : null,
            'receipts' => $canInventory ? $receipts->map(fn ($receipt) => [
                'id' => $receipt->id, 'grn_no' => $receipt->grn_no,
                'po_no' => $receipt->purchaseOrder?->po_no,
                'received_date' => $receipt->received_date?->toDateString(),
                'status' => $receipt->status,
            ]) : null,
            'lowStockCount' => $canInventory ? (clone $lowStock)->count() : null,
            'lowStock' => $canInventory ? (clone $lowStock)->orderBy('products.name')->orderBy('warehouses.name')->limit(10)->get() : null,
        ]);
    }
}
