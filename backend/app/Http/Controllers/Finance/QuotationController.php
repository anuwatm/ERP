<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\FxRateService;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'customer_id']);

        return Inertia::render('Finance/Quotations', [
            'quotations' => Quotation::query()
                ->where('org_id', $user->org_id)
                ->with(['customer:id,company_name,customer_code', 'deal:id,title', 'items.product:id,name,sku', 'convertedInvoice:id,invoice_no'])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                    $inner->where('quotation_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$search}%"));
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
                ->latest()
                ->get(),
            'customers' => Customer::where('org_id', $user->org_id)->orderBy('company_name')->get(['id', 'customer_code', 'company_name']),
            'deals' => Deal::where('org_id', $user->org_id)->whereIn('stage', ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won'])->orderBy('title')->get(['id', 'title', 'customer_id']),
            'products' => Product::where('org_id', $user->org_id)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price']),
            'statuses' => Quotation::STATUSES,
            'taxModes' => Quotation::TAX_MODES,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers, FxRateService $fxRates): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateQuotation($request);
        $this->assertDealMatchesCustomer($validated);
        $totals = $this->calculateTotals($validated);
        $snapshot = $fxRates->snapshot($user->org_id, $validated['currency'], $validated['issue_date'], $totals);

        $quotation = DB::transaction(function () use ($request, $user, $validated, $totals, $snapshot, $numbers): Quotation {
            $quotation = Quotation::create(array_merge($this->quotationPayload($validated, $totals), $snapshot, [
                'org_id' => $user->org_id,
                'branch_id' => $user->branch_id,
                'quotation_no' => $numbers->next($user->org_id, 'quotation', $user->branch_id),
                'created_by' => $user->id,
            ]));

            $this->syncItems($quotation, $validated['items']);
            $this->audit($request, 'quotation.create', $quotation, null, $this->snapshot($quotation));

            return $quotation;
        });

        return back()->with('success', "Quotation {$quotation->quotation_no} created.");
    }

    public function update(Request $request, Quotation $quotation, FxRateService $fxRates): RedirectResponse
    {
        abort_unless($quotation->org_id === $request->user()->org_id, 403);
        abort_unless(in_array($quotation->status, ['draft', 'sent'], true), 422, 'Only draft or sent quotations can be edited.');

        $validated = $this->validateQuotation($request);
        $this->assertDealMatchesCustomer($validated);
        $totals = $this->calculateTotals($validated);
        $snapshot = $fxRates->snapshot($quotation->org_id, $validated['currency'], $validated['issue_date'], $totals);
        $before = $this->snapshot($quotation);

        DB::transaction(function () use ($request, $quotation, $validated, $totals, $snapshot, $before): void {
            $quotation->update(array_merge($this->quotationPayload($validated, $totals), $snapshot, [
                'updated_by' => $request->user()->id,
            ]));
            $quotation->items()->delete();
            $this->syncItems($quotation, $validated['items']);
            $this->audit($request, 'quotation.update', $quotation, $before, $this->snapshot($quotation));
        });

        return back()->with('success', "Quotation {$quotation->quotation_no} updated.");
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, 'approved', ['draft', 'sent'], 'quotation.approve');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, 'rejected', ['draft', 'sent'], 'quotation.reject');
    }

    public function convertToInvoice(Request $request, Quotation $quotation, NumberSequenceService $numbers): RedirectResponse
    {
        abort_unless($quotation->org_id === $request->user()->org_id, 403);
        abort_unless($quotation->status === 'approved', 422, 'Only approved quotations can be converted to invoice.');

        $invoice = DB::transaction(function () use ($request, $quotation, $numbers): Invoice {
            $quotation->load('items');
            $invoice = Invoice::create([
                'org_id' => $quotation->org_id,
                'branch_id' => $quotation->branch_id,
                'invoice_no' => $numbers->next($quotation->org_id, 'invoice', $quotation->branch_id),
                'customer_id' => $quotation->customer_id,
                'deal_id' => $quotation->deal_id,
                'quotation_id' => $quotation->id,
                'status' => 'draft',
                'tax_mode' => $quotation->tax_mode,
                'issue_date' => now()->toDateString(),
                'subtotal' => $quotation->subtotal,
                'discount_amount' => $quotation->discount_amount,
                'tax_amount' => $quotation->tax_amount,
                'total' => $quotation->total,
                'paid_amount' => 0,
                'balance_due' => $quotation->total,
                'currency' => $quotation->currency,
                'base_currency' => $quotation->base_currency,
                'exchange_rate' => $quotation->exchange_rate,
                'base_subtotal' => $quotation->base_subtotal,
                'base_tax_amount' => $quotation->base_tax_amount,
                'base_total' => $quotation->base_total,
                'base_paid_amount' => 0,
                'base_balance_due' => $quotation->base_total,
                'notes' => $quotation->notes,
                'created_by' => $request->user()->id,
            ]);

            foreach ($quotation->items as $item) {
                $invoice->items()->create($item->only(['org_id', 'product_id', 'description', 'quantity', 'unit', 'unit_price', 'discount_amount', 'tax_rate', 'line_total', 'sort_order']));
            }

            $before = $this->snapshot($quotation);
            $quotation->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_invoice_id' => $invoice->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->audit($request, 'quotation.convert', $quotation, $before, $this->snapshot($quotation));

            return $invoice;
        });

        return redirect()->route('invoices.index', ['search' => $invoice->invoice_no])->with('success', "Invoice {$invoice->invoice_no} created from quotation.");
    }

    private function transition(Request $request, Quotation $quotation, string $status, array $allowed, string $action): RedirectResponse
    {
        abort_unless($quotation->org_id === $request->user()->org_id, 403);
        abort_unless(in_array($quotation->status, $allowed, true), 422, "Quotation cannot transition to {$status}.");
        $before = $this->snapshot($quotation);
        $quotation->update([
            'status' => $status,
            $status.'_at' => now(),
            'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, $action, $quotation, $before, $this->snapshot($quotation));

        return back()->with('success', "Quotation {$quotation->quotation_no} {$status}.");
    }

    private function validateQuotation(Request $request): array
    {
        $orgId = $request->user()->org_id;

        return $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('org_id', $orgId)],
            'deal_id' => ['nullable', 'uuid', Rule::exists('deals', 'id')->where('org_id', $orgId)],
            'status' => ['required', Rule::in(['draft', 'sent'])],
            'tax_mode' => ['required', Rule::in(Quotation::TAX_MODES)],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'uuid', Rule::exists('products', 'id')->where('org_id', $orgId)],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'max:999999999999.9999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function assertDealMatchesCustomer(array $validated): void
    {
        if (! filled($validated['deal_id'] ?? null)) {
            return;
        }

        abort_unless(Deal::where('id', $validated['deal_id'])->where('customer_id', $validated['customer_id'])->exists(), 422, 'Deal must belong to selected customer.');
    }

    private function quotationPayload(array $validated, array $totals): array
    {
        return [
            'customer_id' => $validated['customer_id'],
            'deal_id' => $validated['deal_id'] ?? null,
            'status' => $validated['status'],
            'tax_mode' => $validated['tax_mode'],
            'issue_date' => $validated['issue_date'],
            'valid_until' => $validated['valid_until'] ?? null,
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'] ?? null,
            'sent_at' => $validated['status'] === 'sent' ? now() : null,
        ];
    }

    private function calculateTotals(array $validated): array
    {
        $subtotal = collect($validated['items'])->sum(fn ($item) => max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2)));
        $discount = min(round((float) ($validated['discount_amount'] ?? 0), 2), round($subtotal, 2));
        $tax = $validated['tax_mode'] === 'no_tax' ? 0 : collect($validated['items'])->sum(function ($item) use ($validated, $subtotal, $discount) {
            $line = max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2));
            $base = $subtotal > 0 ? max(0, round($line - ($discount * ($line / $subtotal)), 2)) : 0;
            $rate = (float) ($item['tax_rate'] ?? 0);

            return $validated['tax_mode'] === 'inclusive' && $rate > 0 ? round($base - ($base / (1 + $rate / 100)), 2) : round($base * $rate / 100, 2);
        });
        $total = $validated['tax_mode'] === 'exclusive' ? $subtotal - $discount + $tax : $subtotal - $discount;

        return ['subtotal' => round($subtotal, 2), 'discount_amount' => $discount, 'tax_amount' => round($tax, 2), 'total' => max(0, round($total, 2))];
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $quotation->items()->create([
                'org_id' => $quotation->org_id,
                'product_id' => filled($item['product_id'] ?? null) ? $item['product_id'] : null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate' => $quotation->tax_mode === 'no_tax' ? 0 : ($item['tax_rate'] ?? 0),
                'line_total' => max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2)),
                'sort_order' => $index,
            ]);
        }
    }

    private function snapshot(Quotation $quotation): array
    {
        return $quotation->fresh(['items'])->only(['quotation_no', 'customer_id', 'deal_id', 'status', 'tax_mode', 'issue_date', 'valid_until', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'currency', 'notes', 'converted_invoice_id']);
    }

    private function audit(Request $request, string $action, Quotation $quotation, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $quotation->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'quotation',
            'entity_id' => $quotation->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
