<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\User;
use App\Support\SalesAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'stage', 'owner_id', 'customer_id']);
        $deals = SalesAccess::scopeDeals(Deal::query(), $user)
            ->with(['customer:id,company_name,customer_code,owner_id', 'contact:id,name,email,phone', 'owner:id,name,email', 'project:id,deal_id,project_code,name', 'activities' => fn ($query) => $query->latest()->limit(5)])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$search}%"));
            }))
            ->when($filters['stage'] ?? null, fn ($query, $stage) => $query->where('stage', $stage))
            ->when(($filters['owner_id'] ?? null) && SalesAccess::canSeeAll($user), fn ($query) => $query->where('owner_id', $filters['owner_id']))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->latest()
            ->get();

        $this->attachNeedsSalesReview($deals, $user->org_id);

        return Inertia::render('Sales/Deals', [
            'deals' => $deals,
            'customers' => SalesAccess::scopeCustomers(Customer::query(), $user)->with('contacts:id,customer_id,name,email,phone')->orderBy('company_name')->get(['id', 'customer_code', 'company_name', 'owner_id']),
            'owners' => User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'stages' => Deal::STAGES,
            'filters' => $filters,
            'canSeeAllSales' => SalesAccess::canSeeAll($user),
            'canCreateInvoice' => $user->hasPermissionCode('invoices.create') && $user->hasPermissionCode('invoices.view'),
            'canCreateProject' => $user->hasPermissionCode('projects.create') && $user->hasPermissionCode('projects.view'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateDeal($request);
        $customer = Customer::where('org_id', $user->org_id)->findOrFail($validated['customer_id']);
        SalesAccess::assertCustomerVisible($customer, $user);
        $this->assertContactBelongsToCustomer($validated['contact_id'] ?? null, $customer);
        $validated = $this->applyStageRules($validated, null);
        $validated['org_id'] = $user->org_id;
        $validated['owner_id'] = SalesAccess::canSeeAll($user) ? ($validated['owner_id'] ?? $user->id) : $user->id;
        $validated['created_by'] = $user->id;

        $deal = Deal::create($validated);
        $this->audit($request, 'deal.create', $deal, null, $deal->only($this->trackedFields()));

        return back()->with('success', 'Deal created.');
    }

    public function update(Request $request, Deal $deal): RedirectResponse
    {
        $user = $request->user();
        SalesAccess::assertDealVisible($deal, $user);
        $before = $deal->only($this->trackedFields());
        $validated = $this->validateDeal($request);
        $customer = Customer::where('org_id', $user->org_id)->findOrFail($validated['customer_id']);
        SalesAccess::assertCustomerVisible($customer, $user);
        $this->assertContactBelongsToCustomer($validated['contact_id'] ?? null, $customer);
        $validated = $this->applyStageRules($validated, $deal);
        $validated['owner_id'] = SalesAccess::canSeeAll($user) ? ($validated['owner_id'] ?? $deal->owner_id) : $user->id;
        $validated['updated_by'] = $user->id;

        $deal->update($validated);
        $this->audit($request, 'deal.update', $deal, $before, $deal->fresh()->only($this->trackedFields()));

        return back()->with('success', 'Deal updated.');
    }

    private function attachNeedsSalesReview($deals, string $orgId): void
    {
        $activeStatuses = ['sent', 'partially_paid', 'paid', 'overdue'];
        $dealIds = $deals->pluck('id')->filter()->values();

        if ($dealIds->isEmpty()) {
            return;
        }

        $activeDealIds = Invoice::where('org_id', $orgId)
            ->whereIn('deal_id', $dealIds)
            ->whereIn('status', $activeStatuses)
            ->pluck('deal_id')
            ->unique();

        $latestStatuses = Invoice::where('org_id', $orgId)
            ->whereIn('deal_id', $dealIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['deal_id', 'status'])
            ->unique('deal_id')
            ->pluck('status', 'deal_id');

        $deals->each(fn ($deal) => $deal->setAttribute(
            'needs_sales_review',
            ! $activeDealIds->contains($deal->id) && ($latestStatuses[$deal->id] ?? null) === 'void'
        ));
    }

    private function validateDeal(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('org_id', $request->user()->org_id)],
            'contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')->where('org_id', $request->user()->org_id)],
            'stage' => ['required', Rule::in(Deal::STAGES)],
            'value_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $request->user()->org_id)],
            'source' => ['nullable', 'string', 'max:100'],
            'lost_reason' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function assertContactBelongsToCustomer(?string $contactId, Customer $customer): void
    {
        if (! $contactId) {
            return;
        }

        abort_unless(Contact::where('org_id', $customer->org_id)->where('customer_id', $customer->id)->whereKey($contactId)->exists(), 422, 'Contact must belong to selected customer.');
    }

    private function applyStageRules(array $validated, ?Deal $deal): array
    {
        if ($validated['stage'] === 'lost') {
            abort_unless((bool) ($validated['lost_reason'] ?? null), 422, 'Lost reason is required.');
            $validated['lost_at'] = $deal?->lost_at ?? now();
            $validated['won_at'] = null;
        } elseif ($validated['stage'] === 'won') {
            $validated['won_at'] = $deal?->won_at ?? now();
            $validated['lost_at'] = null;
            $validated['lost_reason'] = null;
        } else {
            $validated['won_at'] = null;
            $validated['lost_at'] = null;
            $validated['lost_reason'] = null;
        }

        return $validated;
    }

    private function trackedFields(): array
    {
        return ['title', 'customer_id', 'contact_id', 'stage', 'value_amount', 'currency', 'probability', 'expected_close_date', 'owner_id', 'source', 'lost_reason', 'won_at', 'lost_at', 'note'];
    }

    private function audit(Request $request, string $action, Deal $deal, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $deal->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'deal',
            'entity_id' => $deal->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
