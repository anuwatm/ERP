<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Services\BahtText;
use App\Services\NumberSequenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'customer_id', 'project_id']);
        $canViewPayments = $user->hasPermissionCode('payments.view');
        $canRecordPayments = $user->hasPermissionCode('payments.create');
        $canReversePayments = $user->hasPermissionCode('payments.reverse');
        $relations = ['customer:id,company_name,customer_code', 'deal:id,title', 'project:id,project_code,name', 'items.product:id,name,sku'];

        if ($canViewPayments) {
            $relations[] = 'payments:id,invoice_id,entry_type,reversal_of_payment_id,amount,payment_date,payment_method,reference_no,attachment_file_id';
            $relations[] = 'payments.attachment:id,file_name,mime_type,size_bytes';
        }

        $invoices = Invoice::query()
            ->where('org_id', $user->org_id)
            ->with($relations)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->latest()
            ->get();

        $this->attachNeedsSalesReview($invoices, $user->org_id);
        $this->attachTaxSummaries($invoices);

        $sourceDeal = filled($request->query('deal_id'))
            ? Deal::where('org_id', $user->org_id)->whereKey($request->query('deal_id'))->first(['id', 'title', 'customer_id'])
            : null;

        return Inertia::render('Finance/Invoices', [
            'invoices' => $invoices,
            'customers' => Customer::where('org_id', $user->org_id)->orderBy('company_name')->get(['id', 'customer_code', 'company_name']),
            'deals' => Deal::where('org_id', $user->org_id)->whereIn('stage', ['won', 'proposal', 'negotiation'])->orderBy('title')->get(['id', 'title', 'customer_id']),
            'projects' => Project::where('org_id', $user->org_id)->orderBy('name')->get(['id', 'project_code', 'name', 'customer_id']),
            'products' => Product::where('org_id', $user->org_id)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price']),
            'bankAccounts' => BankAccount::where('org_id', $user->org_id)->where('status', 'active')->orderBy('account_name')->get(['id', 'bank_name', 'account_name']),
            'statuses' => Invoice::STATUSES,
            'taxModes' => Invoice::TAX_MODES,
            'filters' => $filters,
            'canRecordPayments' => $canRecordPayments,
            'canReversePayments' => $canReversePayments,
            'sourceDeal' => $sourceDeal,
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateInvoice($request);
        $this->assertDealMatchesCustomer($validated);
        $this->assertProjectMatchesCustomer($validated);
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
        $this->assertProjectMatchesCustomer($validated);
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

    public function print(Request $request, Invoice $invoice, BahtText $bahtText): View
    {
        abort_unless($invoice->org_id === $request->user()->org_id, 403);

        $invoice->load(['branch', 'customer', 'items']);
        $organization = $request->user()->organization;

        return view('documents.official-print', [
            'organization' => [
                'legal_name' => $organization?->legal_name ?: $organization?->name ?: 'Organization',
                'tax_id' => $organization?->tax_id,
                'address' => $organization?->address,
                'phone' => $organization?->phone,
                'email' => $organization?->email,
                'logo_url' => $organization ? $organization::formatLogoUrl($organization->logo_url) : null,
            ],
            'branch' => [
                'name' => $invoice->branch?->name,
                'code' => $invoice->branch?->code,
                'address' => $invoice->branch?->address,
                'phone' => $invoice->branch?->phone,
                'is_head_office' => (bool) $invoice->branch?->is_head_office,
            ],
            'party' => [
                'name' => $invoice->customer?->company_name ?: '-',
                'tax_id' => $invoice->customer?->tax_id,
                'address' => $invoice->customer?->address,
                'phone' => $invoice->customer?->phone,
                'email' => $invoice->customer?->email,
            ],
            'document' => [
                'title' => $invoice->status === 'paid' ? 'Tax Invoice / Receipt' : 'Invoice',
                'number' => $invoice->invoice_no,
                'issue_date' => $invoice->issue_date?->format('Y-m-d') ?: '-',
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'status' => $invoice->status,
                'copy_label' => $request->boolean('copy') ? 'Copy' : 'Original',
                'party_label' => 'Customer',
                'tax_wording' => $this->taxWording($invoice->tax_mode),
                'currency' => $invoice->currency ?: 'THB',
                'subtotal' => $this->displayMoney($invoice->subtotal),
                'discount_amount' => $this->displayMoney($invoice->discount_amount),
                'tax_amount' => $this->displayMoney($invoice->tax_amount),
                'total' => $this->displayMoney($invoice->total),
                'baht_text' => $bahtText->convert($invoice->total),
                'notes' => $invoice->notes,
                'void' => $invoice->status === 'void',
            ],
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.'),
                'unit' => $item->unit,
                'unit_price' => $this->displayMoney($item->unit_price),
                'discount_amount' => $this->displayMoney($item->discount_amount),
                'tax_rate' => rtrim(rtrim((string) $item->tax_rate, '0'), '.'),
                'line_total' => $this->displayMoney($item->line_total),
            ])->all(),
        ]);
    }

    public function pdf(Request $request, Invoice $invoice, BahtText $bahtText): HttpResponse
    {
        $view = $this->print($request, $invoice, $bahtText)->with('pdf', true);

        return Pdf::loadHTML($view->render())
            ->setPaper('a4')
            ->download("invoice-{$invoice->invoice_no}.pdf");
    }

    private function attachNeedsSalesReview($invoices, string $orgId): void
    {
        $activeStatuses = ['sent', 'partially_paid', 'paid', 'overdue'];
        $dealIds = $invoices->pluck('deal_id')->filter()->unique()->values();

        if ($dealIds->isEmpty()) {
            $invoices->each(fn ($invoice) => $invoice->setAttribute('needs_sales_review', false));

            return;
        }

        $activeDealIds = Invoice::where('org_id', $orgId)
            ->whereIn('deal_id', $dealIds)
            ->whereIn('status', $activeStatuses)
            ->pluck('deal_id')
            ->unique();

        $invoices->each(fn ($invoice) => $invoice->setAttribute(
            'needs_sales_review',
            (bool) $invoice->deal_id && $invoice->status === 'void' && ! $activeDealIds->contains($invoice->deal_id)
        ));
    }

    private function validateInvoice(Request $request, ?Invoice $invoice = null): array
    {
        $orgId = $request->user()->org_id;

        return $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('org_id', $orgId)],
            'deal_id' => ['nullable', 'uuid', Rule::exists('deals', 'id')->where('org_id', $orgId)],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $orgId)],
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

    private function assertProjectMatchesCustomer(array $validated): void
    {
        if (! filled($validated['project_id'] ?? null)) {
            return;
        }

        $matches = Project::where('id', $validated['project_id'])
            ->where('customer_id', $validated['customer_id'])
            ->exists();

        abort_unless($matches, 422, 'Project must belong to selected customer.');
    }

    private function invoicePayload(array $validated, array $totals): array
    {
        $status = $validated['status'];

        return [
            'customer_id' => $validated['customer_id'],
            'deal_id' => $validated['deal_id'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
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
        $summary = $this->taxSummaryFromLines(
            $validated['tax_mode'],
            $validated['discount_amount'] ?? 0,
            array_map(fn ($item) => [
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
            ], $validated['items'])
        );

        return [
            'subtotal' => $summary['gross_subtotal'],
            'discount_amount' => $summary['header_discount'],
            'tax_amount' => $summary['tax_amount'],
            'total' => $summary['total'],
        ];
    }

    private function attachTaxSummaries($invoices): void
    {
        $invoices->each(fn ($invoice) => $invoice->setAttribute(
            'tax_summary',
            $this->taxSummaryFromLines(
                $invoice->tax_mode,
                $invoice->discount_amount,
                $invoice->items->map(fn ($item) => [
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                ])->all()
            )
        ));
    }

    private function taxSummaryFromLines(string $taxMode, mixed $headerDiscount, array $items): array
    {
        $grossSubtotal = 0.0;
        $taxAmount = 0.0;
        $lineSummaries = [];

        foreach ($items as $item) {
            $lineTotal = max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2));
            $grossSubtotal += $lineTotal;
            $lineSummaries[] = [
                'gross_total' => $lineTotal,
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
            ];
        }

        $grossSubtotal = round($grossSubtotal, 2);
        $discountAmount = min(round((float) $headerDiscount, 2), $grossSubtotal);
        $allocatedDiscount = 0.0;

        foreach ($lineSummaries as $index => $line) {
            $lineDiscount = 0.0;

            if ($discountAmount > 0 && $grossSubtotal > 0) {
                $lineDiscount = $index === array_key_last($lineSummaries)
                    ? round($discountAmount - $allocatedDiscount, 2)
                    : round($discountAmount * ($line['gross_total'] / $grossSubtotal), 2);
                $allocatedDiscount += $lineDiscount;
            }

            $grossAfterDiscount = max(0, round($line['gross_total'] - $lineDiscount, 2));
            $lineTax = 0.0;
            $taxableBase = $grossAfterDiscount;

            if ($taxMode === 'exclusive') {
                $lineTax = round($grossAfterDiscount * $line['tax_rate'] / 100, 2);
            } elseif ($taxMode === 'inclusive' && $line['tax_rate'] > 0) {
                $lineTax = round($grossAfterDiscount - ($grossAfterDiscount / (1 + ($line['tax_rate'] / 100))), 2);
                $taxableBase = round($grossAfterDiscount - $lineTax, 2);
            }

            $taxAmount += $lineTax;
            $lineSummaries[$index]['allocated_header_discount'] = $this->moneyValue($lineDiscount);
            $lineSummaries[$index]['gross_after_discount'] = $this->moneyValue($grossAfterDiscount);
            $lineSummaries[$index]['taxable_base'] = $this->moneyValue($taxMode === 'no_tax' ? $grossAfterDiscount : $taxableBase);
            $lineSummaries[$index]['tax_amount'] = $this->moneyValue($taxMode === 'no_tax' ? 0 : $lineTax);
        }

        $taxAmount = $taxMode === 'no_tax' ? 0.0 : round($taxAmount, 2);
        $grossAfterDiscount = max(0, round($grossSubtotal - $discountAmount, 2));
        $total = $taxMode === 'exclusive'
            ? $grossAfterDiscount + $taxAmount
            : $grossAfterDiscount;
        $netSubtotal = $taxMode === 'inclusive'
            ? max(0, round($grossAfterDiscount - $taxAmount, 2))
            : $grossAfterDiscount;

        return [
            'mode' => $taxMode,
            'gross_subtotal' => $this->moneyValue($grossSubtotal),
            'header_discount' => $this->moneyValue($discountAmount),
            'gross_after_discount' => $this->moneyValue($grossAfterDiscount),
            'net_subtotal' => $this->moneyValue($netSubtotal),
            'taxable_base' => $this->moneyValue($netSubtotal),
            'tax_amount' => $this->moneyValue($taxAmount),
            'total' => $this->moneyValue(max(0, round($total, 2))),
            'wording' => match ($taxMode) {
                'inclusive' => 'Prices include VAT. VAT amount is shown for reporting.',
                'exclusive' => 'VAT is added on top of taxable base.',
                default => 'No VAT applied.',
            },
            'lines' => $lineSummaries,
        ];
    }

    private function moneyValue(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function displayMoney(mixed $value): string
    {
        return number_format((float) $value, 2);
    }

    private function taxWording(string $taxMode): string
    {
        return match ($taxMode) {
            'inclusive' => 'Prices include VAT. VAT amount is shown for reporting.',
            'exclusive' => 'VAT is added on top of taxable base.',
            default => 'No VAT applied.',
        };
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
        return $invoice->fresh(['items'])->only(['invoice_no', 'customer_id', 'deal_id', 'project_id', 'status', 'tax_mode', 'issue_date', 'due_date', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'paid_amount', 'balance_due', 'currency', 'notes']);
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
