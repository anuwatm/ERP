<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Finance/Suppliers', [
            'suppliers' => Supplier::query()
                ->where('org_id', $user->org_id)
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                    $inner->where('supplier_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->get(),
            'statuses' => Supplier::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateSupplier($request);
        $validated['org_id'] = $user->org_id;
        $validated['supplier_code'] = $numbers->next($user->org_id, 'supplier');
        $validated['created_by'] = $user->id;

        Supplier::create($validated);

        return back()->with('success', 'Supplier created.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($supplier->org_id === $request->user()->org_id, 404);
        $validated = $this->validateSupplier($request, $supplier);
        $validated['updated_by'] = $request->user()->id;
        $supplier->update($validated);

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($supplier->org_id === $request->user()->org_id, 404);
        abort_if($supplier->purchaseOrders()->exists(), 422, 'Cannot delete supplier with purchase orders.');
        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }

    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'supplier_code' => ['nullable', 'string', 'max:30', Rule::unique('suppliers', 'supplier_code')->where('org_id', $request->user()->org_id)->ignore($supplier?->id)],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Supplier::STATUSES)],
        ]);
    }
}
