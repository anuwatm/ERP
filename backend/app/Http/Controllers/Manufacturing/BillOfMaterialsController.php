<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillOfMaterials;
use App\Models\BomItem;
use App\Models\Product;
use App\Services\NumberSequenceService;
use App\Services\ProductionCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillOfMaterialsController extends Controller
{
    public function __construct(
        private readonly ProductionCostingService $costing,
        private readonly NumberSequenceService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'is_active', 'product_id']);

        $boms = BillOfMaterials::query()
            ->with(['product:id,sku,name,unit,price,cost', 'creator:id,name', 'items.product:id,sku,name,unit'])
            ->withCount(['items', 'workOrders'])
            ->where('org_id', $user->org_id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('bom_no', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            }))
            ->when(($filters['is_active'] ?? null) !== null && ($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
            ->when($filters['product_id'] ?? null, fn ($query, $pid) => $query->where('product_id', $pid))
            ->orderByDesc('created_at')
            ->get();

        $products = Product::where('org_id', $user->org_id)
            ->where('is_active', true)
            ->select('id', 'sku', 'name', 'unit', 'cost', 'price')
            ->orderBy('name')
            ->get();

        $availableSubBoms = BillOfMaterials::where('org_id', $user->org_id)
            ->where('is_active', true)
            ->select('id', 'bom_no', 'name', 'version', 'product_id', 'total_cost', 'quantity', 'unit')
            ->orderBy('name')
            ->get();

        return Inertia::render('Manufacturing/BillOfMaterials', [
            'boms' => $boms,
            'products' => $products,
            'availableSubBoms' => $availableSubBoms,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateBom($request);

        $bom = DB::transaction(function () use ($user, $validated) {
            $bomNo = $this->numbers->next($user->org_id, 'bill_of_materials');

            $bom = BillOfMaterials::create([
                'org_id' => $user->org_id,
                'bom_no' => $bomNo,
                'product_id' => $validated['product_id'],
                'name' => $validated['name'],
                'version' => $validated['version'] ?? '1.0',
                'quantity' => $validated['quantity'] ?? 1.0,
                'unit' => $validated['unit'] ?? 'pcs',
                'routing_notes' => $validated['routing_notes'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'labor_cost' => $validated['labor_cost'] ?? 0,
                'overhead_cost' => $validated['overhead_cost'] ?? 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($validated['items'] as $index => $itemData) {
                BomItem::create([
                    'org_id' => $user->org_id,
                    'bill_of_materials_id' => $bom->id,
                    'product_id' => $itemData['product_id'],
                    'sub_bom_id' => $itemData['sub_bom_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? 'pcs',
                    'waste_percent' => $itemData['waste_percent'] ?? 0,
                    'unit_cost' => $itemData['unit_cost'] ?? 0,
                    'note' => $itemData['note'] ?? null,
                    'sequence' => $index,
                ]);
            }

            $this->costing->calculateBomCost($bom);

            return $bom;
        });

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'bom.create',
            'entity_type' => 'bill_of_materials',
            'entity_id' => $bom->id,
            'after_json' => $bom->only(['bom_no', 'name', 'total_cost']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Bill of Materials {$bom->bom_no} created.");
    }

    public function update(Request $request, BillOfMaterials $billOfMaterial): RedirectResponse
    {
        $user = $request->user();
        abort_unless($billOfMaterial->org_id === $user->org_id, 403);

        $validated = $this->validateBom($request, $billOfMaterial->id);

        DB::transaction(function () use ($billOfMaterial, $user, $validated) {
            $billOfMaterial->update([
                'product_id' => $validated['product_id'],
                'name' => $validated['name'],
                'version' => $validated['version'] ?? $billOfMaterial->version,
                'quantity' => $validated['quantity'] ?? 1.0,
                'unit' => $validated['unit'] ?? 'pcs',
                'routing_notes' => $validated['routing_notes'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'labor_cost' => $validated['labor_cost'] ?? 0,
                'overhead_cost' => $validated['overhead_cost'] ?? 0,
                'updated_by' => $user->id,
            ]);

            // Re-sync items
            $billOfMaterial->items()->delete();

            foreach ($validated['items'] as $index => $itemData) {
                BomItem::create([
                    'org_id' => $user->org_id,
                    'bill_of_materials_id' => $billOfMaterial->id,
                    'product_id' => $itemData['product_id'],
                    'sub_bom_id' => $itemData['sub_bom_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? 'pcs',
                    'waste_percent' => $itemData['waste_percent'] ?? 0,
                    'unit_cost' => $itemData['unit_cost'] ?? 0,
                    'note' => $itemData['note'] ?? null,
                    'sequence' => $index,
                ]);
            }

            $this->costing->calculateBomCost($billOfMaterial);
        });

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'bom.update',
            'entity_type' => 'bill_of_materials',
            'entity_id' => $billOfMaterial->id,
            'after_json' => $billOfMaterial->only(['bom_no', 'name', 'total_cost']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Bill of Materials {$billOfMaterial->bom_no} updated.");
    }

    public function duplicate(Request $request, BillOfMaterials $billOfMaterial): RedirectResponse
    {
        $user = $request->user();
        abort_unless($billOfMaterial->org_id === $user->org_id, 403);

        $newVersion = $request->input('version') ?: (string) (((float) $billOfMaterial->version) + 0.1);

        $newBom = DB::transaction(function () use ($billOfMaterial, $user, $newVersion) {
            $billOfMaterial->loadMissing('items');
            $bomNo = $this->numbers->next($user->org_id, 'bill_of_materials');

            $clone = BillOfMaterials::create([
                'org_id' => $user->org_id,
                'bom_no' => $bomNo,
                'product_id' => $billOfMaterial->product_id,
                'name' => "{$billOfMaterial->name} (Copy)",
                'version' => $newVersion,
                'quantity' => $billOfMaterial->quantity,
                'unit' => $billOfMaterial->unit,
                'routing_notes' => $billOfMaterial->routing_notes,
                'is_active' => true,
                'labor_cost' => $billOfMaterial->labor_cost,
                'overhead_cost' => $billOfMaterial->overhead_cost,
                'total_cost' => $billOfMaterial->total_cost,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($billOfMaterial->items as $item) {
                BomItem::create([
                    'org_id' => $user->org_id,
                    'bill_of_materials_id' => $clone->id,
                    'product_id' => $item->product_id,
                    'sub_bom_id' => $item->sub_bom_id,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'waste_percent' => $item->waste_percent,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $item->total_cost,
                    'note' => $item->note,
                    'sequence' => $item->sequence,
                ]);
            }

            $this->costing->calculateBomCost($clone);

            return $clone;
        });

        return back()->with('success', "BOM duplicated as {$newBom->bom_no}.");
    }

    public function destroy(Request $request, BillOfMaterials $billOfMaterial): RedirectResponse
    {
        $user = $request->user();
        abort_unless($billOfMaterial->org_id === $user->org_id, 403);

        $billOfMaterial->delete();

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'bom.delete',
            'entity_type' => 'bill_of_materials',
            'entity_id' => $billOfMaterial->id,
            'after_json' => ['deleted' => true],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Bill of Materials {$billOfMaterial->bom_no} deleted.");
    }

    public function explosion(Request $request, BillOfMaterials $billOfMaterial): JsonResponse
    {
        $user = $request->user();
        abort_unless($billOfMaterial->org_id === $user->org_id, 403);

        $qty = (float) $request->input('quantity', $billOfMaterial->quantity ?: 1.0);
        $exploded = $this->costing->explode($billOfMaterial, $qty);
        $flattened = $this->costing->flattenExplodedMaterials($exploded);

        return response()->json([
            'tree' => $exploded,
            'materials_summary' => $flattened,
        ]);
    }

    private function validateBom(Request $request, ?string $bomId = null): array
    {
        $orgId = $request->user()->org_id;

        return $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('org_id', $orgId)],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit' => ['required', 'string', 'max:30'],
            'routing_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('org_id', $orgId)],
            'items.*.sub_bom_id' => ['nullable', Rule::exists('bill_of_materials', 'id')->where('org_id', $orgId)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
