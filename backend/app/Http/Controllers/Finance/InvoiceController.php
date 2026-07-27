<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'customer_id']);
        $invoices = Invoice::query()
            ->where('org_id', $user->org_id)
            ->with(['customer:id,company_name,customer_code', 'deal:id,title', 'items.product:id,name,sku'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->latest()
            ->get();

        return Inertia::render('Finance/Invoices', [
            'invoices' => $invoices,
            'customers' => Customer::where('org_id', $user->org_id)->orderBy('company_name')->get(['id', 'customer_code', 'company_name']),
            'deals' => Deal::where('org_id', $user->org_id)->whereIn('stage', ['won', 'proposal', 'negotiation'])->orderBy('title')->get(['id', 'title', 'customer_id']),
            'products' => Product::where('org_id', $user->org_id)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price']),
            'statuses' => Invoice::STATUSES,
            'taxModes' => Invoice::TAX_MODES,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateInvoice($request);
        $this->assertDealMatchesCustomer($validated);
        $totals = $this->calculateTotals($validated);

        $invoice = DB::transaction(function () use ($request, $user, $validated, $totals, $numbers): Invoice {
            $invoice = Invoice::create(array_merge($this->invoicePayload($validated, $totals), [
                'org_id' => $user->org_id,
                'branch_id' => $user->branch_id,
                'invoice_no' => $numbers->next($user->org_id, 'invoice'),
                'paid_amount' => 0,
                'balance_due' => $totals['total'],
                'created_by' => $user->id,
            ]));

            $this->syncItems($invoice, $validated['items']);
            $this->audit($request, 'invoice.create', $invoice, null, $this->snapshot($invoice));

            return $invoice;
        });

        return back()->with('success', "Invoice {$invoice->invoice_no} created.");
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->org_id === $request->user()->org_id, 403);
        abort_if((float) $invoice->paid_amount > 0 || $invoice->status === 'void', 422, 'Cannot edit invoice after payment or void.');

        $validated = $this->validateInvoice($request, $invoice);
        $this->assertDealMatchesCustomer($validated);
        $totals = $this->calculateTotals($validated);
        $before = $this->snapshot($invoice);

        DB::transaction(function () use ($request, $invoice, $validated, $totals, $before): void {
            $invoice->update(array_merge($this->invoicePayload($validated, $totals), [
                'balance_due' => $totals['total'] - (float) $invoice->paid_amount,
                'updated_by' => $request->user()->id,
            ]));
            $invoice->items()->delete();
            $this->syncItems($invoice, $validated['items']);
            $this->audit($request, 'invoice.update', $invoice, $before ?? null, $this->snapshot($invoice));
        });

        return back()->with('success', "Invoice {$invoice->invoice_no} updated.");
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->org_id === $request->user()->org_id, 403);
        abort_if((float) $invoice->paid_amount > 0 || $invoice->status === 'void', 422, 'Cannot void invoice after payment or already void.');
        $before = $this->snapshot($invoice);
        $invoice->update([
            'status' => 'void',
            'voided_at' => now(),
            'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'invoice.void', $invoice, $before, $this->snapshot($invoice));

        return back()->with('success', "Invoice {$invoice->invoice_no} voided.");
    }

    private function validateInvoice(Request $request, ?Invoice $invoice = null): array
    {
        $orgId = $request->user()->org_id;

        return $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('org_id', $orgId)],
            'deal_id' => ['nullable', 'uuid', Rule::exists('deals', 'id')->where('org_id', $orgId)],
            'status' => ['required', Rule::in(['draft', 'sent'])],
            'tax_mode' => ['required', Rule::in(Invoice::TAX_MODES)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
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

        $matches = Deal::where('id', $validated['deal_id'])
            ->where('customer_id', $validated['customer_id'])
            ->exists();

        abort_unless($matches, 422, 'Deal must belong to selected customer.');
    }
    private function invoicePayload(array $validated, array $totals): array
    {
        $status = $validated['status'];

        return [
            'customer_id' => $validated['customer_id'],
            'deal_id' => $validated['deal_id'] ?? null,
            'status' => $status,
            'tax_mode' => $validated['tax_mode'],
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'] ?? null,
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'] ?? null,
            'sent_at' => $status === 'sent' ? now() : null,
        ];
    }

    private function calculateTotals(array $validated): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($validated['items'] as $item) {
            $lineTotal = max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2));
            $subtotal += $lineTotal;
            $taxRate = (float) ($item['tax_rate'] ?? 0);

            if ($validated['tax_mode'] === 'exclusive') {
                $taxAmount += round($lineTotal * $taxRate / 100, 2);
            } elseif ($validated['tax_mode'] === 'inclusive' && $taxRate > 0) {
                $taxAmount += round($lineTotal - ($lineTotal / (1 + ($taxRate / 100))), 2);
            }
        }

        $discountAmount = min(round((float) ($validated['discount_amount'] ?? 0), 2), round($subtotal, 2));
        $total = $validated['tax_mode'] === 'exclusive'
            ? $subtotal - $discountAmount + $taxAmount
            : $subtotal - $discountAmount;

        if ($validated['tax_mode'] === 'no_tax') {
            $taxAmount = 0.0;
            $total = $subtotal - $discountAmount;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => $discountAmount,
            'tax_amount' => round($taxAmount, 2),
            'total' => max(0, round($total, 2)),
        ];
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $invoice->items()->create([
                'org_id' => $invoice->org_id,
                'product_id' => filled($item['product_id'] ?? null) ? $item['product_id'] : null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate' => $invoice->tax_mode === 'no_tax' ? 0 : ($item['tax_rate'] ?? 0),
                'line_total' => max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2)),
                'sort_order' => $index,
            ]);
        }
    }

    private function snapshot(Invoice $invoice): array
    {
        return $invoice->fresh(['items'])->only(['invoice_no', 'customer_id', 'deal_id', 'status', 'tax_mode', 'issue_date', 'due_date', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'paid_amount', 'balance_due', 'currency', 'notes']);
    }

    private function audit(Request $request, string $action, Invoice $invoice, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $invoice->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
