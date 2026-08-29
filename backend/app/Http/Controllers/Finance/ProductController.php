<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'type', 'is_active']);
        $products = Product::query()
            ->where('org_id', $user->org_id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            }))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(($filters['is_active'] ?? null) !== null && ($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance/Products', [
            'products' => $products,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateProduct($request);
        $validated['sku'] = filled($validated['sku'] ?? null) ? $validated['sku'] : null;
        $validated['barcode'] = filled($validated['barcode'] ?? null) ? $validated['barcode'] : null;
        $validated['org_id'] = $user->org_id;
        $validated['created_by'] = $user->id;
        $validated['cost'] ??= 0;
        $validated['track_inventory'] = false;

        $product = Product::create($validated);
        $this->audit($request, 'product.create', $product, null, $product->only($this->trackedFields()));

        return back()->with('success', 'Product/service created.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->org_id === $request->user()->org_id, 403);
        $before = $product->only($this->trackedFields());
        $validated = $this->validateProduct($request, $product);
        $validated['sku'] = filled($validated['sku'] ?? null) ? $validated['sku'] : null;
        $validated['barcode'] = filled($validated['barcode'] ?? null) ? $validated['barcode'] : null;
        $validated['updated_by'] = $request->user()->id;
        $validated['cost'] ??= 0;
        $validated['track_inventory'] = false;

        $product->update($validated);
        $this->audit($request, 'product.update', $product, $before, $product->fresh()->only($this->trackedFields()));

        return back()->with('success', 'Product/service updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->org_id === $request->user()->org_id, 403);
        $before = $product->only($this->trackedFields());
        $product->delete();
        $this->audit($request, 'product.delete', $product, $before, null);

        return back()->with('success', 'Product/service deleted.');
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where('org_id', $request->user()->org_id)
                    ->ignore($product?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->where('org_id', $request->user()->org_id)->ignore($product?->id)],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['product', 'service', 'package'])],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function trackedFields(): array
    {
        return ['sku', 'barcode', 'reorder_point', 'name', 'type', 'category', 'unit', 'price', 'cost', 'is_active', 'description', 'track_inventory'];
    }

    private function audit(Request $request, string $action, Product $product, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $product->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
