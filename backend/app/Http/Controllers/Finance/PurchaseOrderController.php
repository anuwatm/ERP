<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BahtText;
use App\Services\NotificationService;
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

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Finance/PurchaseOrders', [
            'purchaseOrders' => PurchaseOrder::where('org_id', $user->org_id)->with(['supplier:id,name,supplier_code', 'project:id,project_code,name', 'items'])->latest()->get(),
            'suppliers' => Supplier::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'supplier_code', 'name']),
            'projects' => Project::where('org_id', $user->org_id)->orderBy('name')->get(['id', 'project_code', 'name']),
            'products' => Product::where('org_id', $user->org_id)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price']),
            'statuses' => PurchaseOrder::STATUSES,
            'taxModes' => PurchaseOrder::TAX_MODES,
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers, NotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validatePurchaseOrder($request);
        $totals = $this->calculateTotals($validated);

        $po = DB::transaction(function () use ($user, $validated, $totals, $numbers): PurchaseOrder {
            $po = PurchaseOrder::create(array_merge($validated, $totals, [
                'org_id' => $user->org_id,
                'po_no' => $numbers->next($user->org_id, 'purchase_order'),
                'created_by' => $user->id,
            ]));
            $this->syncItems($po, $validated['items']);

            return $po;
        });

        if ($po->status === 'sent') {
            $this->notifyApprovers($po, $notifications);
        }

        return back()->with('success', 'Purchase order created.');
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->org_id === $request->user()->org_id, 404);
        abort_unless($purchaseOrder->status === 'draft', 422, 'Only draft purchase orders can be edited.');
        $validated = $this->validatePurchaseOrder($request);
        $totals = $this->calculateTotals($validated);

        DB::transaction(function () use ($request, $purchaseOrder, $validated, $totals): void {
            $purchaseOrder->update(array_merge($validated, $totals, ['updated_by' => $request->user()->id]));
            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $validated['items']);
        });

        return back()->with('success', 'Purchase order updated.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->org_id === $request->user()->org_id, 404);
        abort_unless(in_array($purchaseOrder->status, ['draft', 'sent'], true), 422, 'Only draft or sent purchase orders can be approved.');
        $purchaseOrder->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);

        return back()->with('success', 'Purchase order approved.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->org_id === $request->user()->org_id, 404);
        abort_if(in_array($purchaseOrder->status, ['received', 'closed', 'cancelled'], true), 422, 'Purchase order cannot be cancelled.');
        $purchaseOrder->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder, BahtText $bahtText): View
    {
        abort_unless($purchaseOrder->org_id === $request->user()->org_id, 403);

        $purchaseOrder->load(['supplier', 'items']);
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
                'name' => $request->user()->branch?->name,
                'code' => $request->user()->branch?->code,
                'address' => $request->user()->branch?->address,
                'phone' => $request->user()->branch?->phone,
                'is_head_office' => (bool) $request->user()->branch?->is_head_office,
            ],
            'party' => [
                'name' => $purchaseOrder->supplier?->name ?: '-',
                'tax_id' => $purchaseOrder->supplier?->tax_id,
                'address' => $purchaseOrder->supplier?->address,
                'phone' => $purchaseOrder->supplier?->phone,
                'email' => $purchaseOrder->supplier?->email,
            ],
            'document' => [
                'title' => 'Purchase Order',
                'number' => $purchaseOrder->po_no,
                'issue_date' => $purchaseOrder->order_date?->format('Y-m-d') ?: '-',
                'due_date' => $purchaseOrder->expected_date?->format('Y-m-d'),
                'status' => $purchaseOrder->status,
                'copy_label' => $request->boolean('copy') ? 'Copy' : 'Original',
                'party_label' => 'Supplier',
                'tax_wording' => $this->taxWording($purchaseOrder->tax_mode),
                'currency' => $purchaseOrder->currency ?: 'THB',
                'subtotal' => $this->displayMoney($purchaseOrder->subtotal),
                'discount_amount' => $this->displayMoney($purchaseOrder->discount_amount),
                'tax_amount' => $this->displayMoney($purchaseOrder->tax_amount),
                'total' => $this->displayMoney($purchaseOrder->total),
                'baht_text' => $bahtText->convert($purchaseOrder->total),
                'notes' => $purchaseOrder->note,
                'void' => $purchaseOrder->status === 'cancelled',
            ],
            'items' => $purchaseOrder->items->map(fn ($item) => [
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

    public function pdf(Request $request, PurchaseOrder $purchaseOrder, BahtText $bahtText): HttpResponse
    {
        $view = $this->print($request, $purchaseOrder, $bahtText)->with('pdf', true);

        return Pdf::loadHTML($view->render())
            ->setPaper('a4')
            ->download("purchase-order-{$purchaseOrder->po_no}.pdf");
    }

    private function validatePurchaseOrder(Request $request): array
    {
        $orgId = $request->user()->org_id;

        return $request->validate([
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')->where('org_id', $orgId)],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $orgId)],
            'status' => ['required', Rule::in(['draft', 'sent'])],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'tax_mode' => ['required', Rule::in(PurchaseOrder::TAX_MODES)],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'uuid', Rule::exists('products', 'id')->where('org_id', $orgId)],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
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

    private function syncItems(PurchaseOrder $po, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $po->items()->create([
                'org_id' => $po->org_id,
                'product_id' => filled($item['product_id'] ?? null) ? $item['product_id'] : null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate' => $po->tax_mode === 'no_tax' ? 0 : ($item['tax_rate'] ?? 0),
                'line_total' => max(0, round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2)),
                'sort_order' => $index,
            ]);
        }
    }

    private function notifyApprovers(PurchaseOrder $po, NotificationService $notifications): void
    {
        $po->load('supplier');
        $approvers = User::where('org_id', $po->org_id)
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('code', 'purchase_orders.approve'))
            ->get();

        foreach ($approvers as $approver) {
            $notifications->notify(
                $approver,
                'purchase_order.pending_approval',
                "po.pending_approval:{$po->id}",
                "Purchase Order {$po->po_no} waiting for approval",
                'Supplier: '.($po->supplier?->name ?: '-').' / Total: '.number_format((float) $po->total, 2),
                route('purchase-orders.index')
            );
        }
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
}
