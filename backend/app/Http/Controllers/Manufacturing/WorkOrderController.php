<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillOfMaterials;
use App\Models\ProductionCost;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\ProductionCostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function __construct(
        private readonly ProductionCostingService $costing,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['status', 'search', 'start_date', 'due_date']);

        $workOrders = WorkOrder::query()
            ->with([
                'product:id,sku,name,unit',
                'billOfMaterials:id,bom_no,name,version',
                'warehouse:id,code,name',
                'sourceWarehouse:id,code,name',
                'items.product:id,sku,name,unit',
                'productionCosts',
                'stockMovements',
                'creator:id,name',
            ])
            ->where('org_id', $user->org_id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('wo_no', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                    ->orWhereHas('billOfMaterials', fn ($b) => $b->where('bom_no', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            }))
            ->when($filters['start_date'] ?? null, fn ($query, $date) => $query->whereDate('start_date', '>=', $date))
            ->when($filters['due_date'] ?? null, fn ($query, $date) => $query->whereDate('due_date', '<=', $date))
            ->orderByDesc('created_at')
            ->get();

        $boms = BillOfMaterials::with('product:id,sku,name,unit')
            ->where('org_id', $user->org_id)
            ->where('is_active', true)
            ->select('id', 'bom_no', 'name', 'product_id', 'version', 'quantity', 'unit', 'total_cost')
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('org_id', $user->org_id)
            ->where('status', 'active')
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        return Inertia::render('Manufacturing/WorkOrders', [
            'workOrders' => $workOrders,
            'boms' => $boms,
            'warehouses' => $warehouses,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'bill_of_materials_id' => ['required', Rule::exists('bill_of_materials', 'id')->where('org_id', $user->org_id)],
            'planned_quantity' => ['required', 'numeric', 'min:0.0001'],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('org_id', $user->org_id)],
            'source_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('org_id', $user->org_id)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'flatten_materials' => ['nullable', 'boolean'],
        ]);

        $validated['org_id'] = $user->org_id;

        $workOrder = $this->costing->createWorkOrder($validated, $user->id);

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'work_order.create',
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'after_json' => $workOrder->only(['wo_no', 'status', 'planned_quantity', 'total_cost']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Work Order {$workOrder->wo_no} created.");
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);
        abort_if(in_array($workOrder->status, ['completed', 'cancelled'], true), 422, 'Cannot update a completed or cancelled work order.');

        $validated = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('org_id', $user->org_id)],
            'source_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('org_id', $user->org_id)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $workOrder->update(array_merge($validated, ['updated_by' => $user->id]));

        return back()->with('success', "Work Order {$workOrder->wo_no} updated.");
    }

    public function plan(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);

        $this->costing->transitionStatus($workOrder, 'planned', $request->all(), $user->id);

        return back()->with('success', "Work Order {$workOrder->wo_no} moved to Planned.");
    }

    public function release(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'issued_items' => ['nullable', 'array'],
            'issued_items.*' => ['numeric', 'min:0.0001'],
        ]);

        $this->costing->transitionStatus($workOrder, 'in_progress', $validated, $user->id);

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'work_order.release',
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'after_json' => ['status' => 'in_progress', 'material_cost' => $workOrder->material_cost],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Work Order {$workOrder->wo_no} released to In-Progress. Raw materials issued to WIP.");
    }

    public function qualityCheck(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);

        $validated = $request->validate([
            'actual_quantity' => ['required', 'numeric', 'min:0'],
            'scrap_quantity' => ['nullable', 'numeric', 'min:0'],
            'qc_notes' => ['nullable', 'string', 'max:1000'],
            'qc_passed' => ['nullable', 'boolean'],
        ]);

        $this->costing->transitionStatus($workOrder, 'quality_check', $validated, $user->id);

        return back()->with('success', "Work Order {$workOrder->wo_no} moved to Quality Check.");
    }

    public function complete(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);

        $validated = $request->validate([
            'actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'completed_date' => ['nullable', 'date'],
        ]);

        $this->costing->transitionStatus($workOrder, 'completed', $validated, $user->id);

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'work_order.complete',
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'after_json' => ['status' => 'completed', 'actual_quantity' => $workOrder->actual_quantity, 'total_cost' => $workOrder->total_cost],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Work Order {$workOrder->wo_no} completed. Finished goods received into warehouse.");
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);

        $this->costing->transitionStatus($workOrder, 'cancelled', [], $user->id);

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'work_order.cancel',
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'after_json' => ['status' => 'cancelled'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Work Order {$workOrder->wo_no} cancelled.");
    }

    public function addCost(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workOrder->org_id === $user->org_id, 403);
        abort_if(in_array($workOrder->status, ['completed', 'cancelled'], true), 422, 'Cannot add costs to a closed work order.');

        $validated = $request->validate([
            'cost_type' => ['required', Rule::in(['labor', 'overhead'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cost_date' => ['nullable', 'date'],
        ]);

        ProductionCost::create([
            'org_id' => $user->org_id,
            'work_order_id' => $workOrder->id,
            'cost_type' => $validated['cost_type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'cost_date' => $validated['cost_date'] ?? today(),
            'created_by' => $user->id,
        ]);

        if ($validated['cost_type'] === 'labor') {
            $workOrder->labor_cost = number_format((float) $workOrder->labor_cost + (float) $validated['amount'], 2, '.', '');
        } elseif ($validated['cost_type'] === 'overhead') {
            $workOrder->overhead_cost = number_format((float) $workOrder->overhead_cost + (float) $validated['amount'], 2, '.', '');
        }

        $workOrder->total_cost = number_format((float) $workOrder->material_cost + (float) $workOrder->labor_cost + (float) $workOrder->overhead_cost, 2, '.', '');
        $plannedQty = (float) $workOrder->planned_quantity ?: 1.0;
        $workOrder->unit_cost = number_format((float) $workOrder->total_cost / $plannedQty, 2, '.', '');
        $workOrder->save();

        return back()->with('success', 'Production cost recorded.');
    }
}
