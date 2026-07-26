<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\NumberSequenceService;
use App\Support\SalesAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'owner_id']);
        $customers = SalesAccess::scopeCustomers(Customer::query(), $user)
            ->with(['owner:id,name,email', 'contacts' => fn ($query) => $query->select('id', 'customer_id', 'name', 'position', 'phone', 'email', 'line_id', 'is_primary', 'note')->orderByDesc('is_primary')->orderBy('name')])
            ->withCount(['contacts', 'deals'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('company_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['owner_id'] ?? null) && SalesAccess::canSeeAll($user), fn ($query) => $query->where('owner_id', $filters['owner_id']))
            ->latest()
            ->get();

        return Inertia::render('Sales/Customers', [
            'customers' => $customers,
            'owners' => User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => $filters,
            'canSeeAllSales' => SalesAccess::canSeeAll($user),
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateCustomer($request);
        $validated['org_id'] = $user->org_id;
        $validated['customer_code'] = $numbers->next($user->org_id, 'customer');
        $validated['owner_id'] = SalesAccess::canSeeAll($user) ? ($validated['owner_id'] ?? $user->id) : $user->id;
        $validated['created_by'] = $user->id;

        $customer = Customer::create($validated);
        $this->audit($request, 'customer.create', $customer, null, $customer->only($this->trackedFields()));

        return back()->with('success', 'Customer created.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();
        SalesAccess::assertCustomerVisible($customer, $user);
        $before = $customer->only($this->trackedFields());
        $validated = $this->validateCustomer($request);
        $validated['owner_id'] = SalesAccess::canSeeAll($user) ? ($validated['owner_id'] ?? $customer->owner_id) : $user->id;
        $validated['updated_by'] = $user->id;

        $customer->update($validated);
        $this->audit($request, 'customer.update', $customer, $before, $customer->fresh()->only($this->trackedFields()));

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        SalesAccess::assertCustomerVisible($customer, $request->user());
        abort_if($customer->deals()->exists(), 422, 'Cannot delete customer with deals.');
        $before = $customer->only($this->trackedFields());
        $customer->delete();
        $this->audit($request, 'customer.delete', $customer, $before, null);

        return back()->with('success', 'Customer deleted.');
    }

    private function validateCustomer(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['required', Rule::in(['lead', 'prospect', 'active', 'inactive'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $request->user()->org_id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function trackedFields(): array
    {
        return ['customer_code', 'company_name', 'tax_id', 'customer_type', 'status', 'owner_id', 'phone', 'email', 'line_id', 'website', 'address', 'source', 'note'];
    }

    private function audit(Request $request, string $action, Customer $customer, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $customer->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
