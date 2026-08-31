<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingNote;
use App\Models\CreditDebitNote;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warehouse;
use App\Services\FinancialJournalService;
use App\Services\FxRateService;
use App\Services\NotificationService;
use App\Services\NumberSequenceService;
use App\Support\FileAttachmentManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CommercialDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/CommercialDocuments', [
            'creditDebitNotes' => CreditDebitNote::where('org_id', $orgId)->with('invoice:id,invoice_no')->latest()->get(),
            'billingNotes' => BillingNote::where('org_id', $orgId)->with('customer:id,company_name')->latest()->get(),
            'deliveryOrders' => DeliveryOrder::where('org_id', $orgId)->with('invoice:id,invoice_no')->latest()->get(),
            'invoices' => Invoice::where('org_id', $orgId)->where('status', '!=', 'void')->with('items.product:id,barcode,sku,name')->orderByDesc('issue_date')->get(['id', 'invoice_no', 'issue_date']),
            'purchaseRequests' => PurchaseRequest::where('org_id', $orgId)->with(['supplier:id,name', 'items'])->latest()->get(),
            'vouchers' => Voucher::where('org_id', $orgId)->with('attachment:id,file_name,mime_type,size_bytes')->latest()->get(),
            'warehouses' => Warehouse::where('org_id', $orgId)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeCreditDebitNote(Request $request, NumberSequenceService $numbers, FinancialJournalService $journals, FxRateService $fxRates): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'invoice_id' => ['required', 'uuid', Rule::exists('invoices', 'id')->where('org_id', $orgId)],
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'issue_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($request, $numbers, $orgId, $validated, $journals, $fxRates): void {
            $invoice = Invoice::where('org_id', $orgId)->lockForUpdate()->findOrFail($validated['invoice_id']);
            abort_if($invoice->status === 'void', 422, 'Cannot issue CN/DN for void invoice.');
            $totals = $this->simpleTotals($validated['items'], $invoice->tax_mode);
            $snapshot = $fxRates->snapshot($orgId, $invoice->currency, $validated['issue_date'], $totals);
            abort_if($validated['type'] === 'credit' && $totals['total'] > (float) $invoice->balance_due, 422, 'Credit note exceeds invoice balance due.');

            $note = CreditDebitNote::create([
                'org_id' => $orgId,
                'invoice_id' => $invoice->id,
                'note_no' => $numbers->next($orgId, $validated['type'] === 'credit' ? 'credit_note' : 'debit_note'),
                'type' => $validated['type'],
                'status' => 'issued',
                'issue_date' => $validated['issue_date'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'currency' => $invoice->currency,
                ...$snapshot,
                'reason' => $validated['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $note->items()->create([
                    'org_id' => $orgId,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $invoice->tax_mode === 'no_tax' ? 0 : ($item['tax_rate'] ?? 0),
                    'line_total' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
                ]);
            }

            $sign = $validated['type'] === 'credit' ? -1 : 1;
            $invoice->update([
                'subtotal' => max(0, round((float) $invoice->subtotal + ($sign * $totals['subtotal']), 2)),
                'tax_amount' => max(0, round((float) $invoice->tax_amount + ($sign * $totals['tax_amount']), 2)),
                'total' => max(0, round((float) $invoice->total + ($sign * $totals['total']), 2)),
                'balance_due' => max(0, round((float) $invoice->balance_due + ($sign * $totals['total']), 2)),
                'base_subtotal' => max(0, round((float) $invoice->base_subtotal + ($sign * $snapshot['base_subtotal']), 2)),
                'base_tax_amount' => max(0, round((float) $invoice->base_tax_amount + ($sign * $snapshot['base_tax_amount']), 2)),
                'base_total' => max(0, round((float) $invoice->base_total + ($sign * $snapshot['base_total']), 2)),
                'base_balance_due' => max(0, round((float) $invoice->base_balance_due + ($sign * $snapshot['base_total']), 2)),
                'updated_by' => $request->user()->id,
            ]);

            $journals->postCreditDebitNote($note, $request->user()->id);

            $this->audit($request, 'credit_debit_note.create', 'credit_debit_note', $note->id, null, $note->fresh()->only(['invoice_id', 'note_no', 'type', 'status', 'total']));
        });

        return back()->with('success', 'CN/DN issued.');
    }

    public function storeBillingNote(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('org_id', $orgId)],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'uuid', Rule::exists('invoices', 'id')->where('org_id', $orgId)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $numbers, $orgId, $validated): void {
            $invoices = Invoice::where('org_id', $orgId)
                ->where('customer_id', $validated['customer_id'])
                ->whereIn('id', $validated['invoice_ids'])
                ->where('balance_due', '>', 0)
                ->lockForUpdate()
                ->get();
            abort_unless($invoices->count() === count(array_unique($validated['invoice_ids'])), 422, 'Billing note invoices must belong to customer and have balance due.');

            $billing = BillingNote::create([
                'org_id' => $orgId,
                'customer_id' => $validated['customer_id'],
                'billing_no' => $numbers->next($orgId, 'billing_note'),
                'status' => 'issued',
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'] ?? null,
                'total' => round((float) $invoices->sum('balance_due'), 2),
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($invoices as $invoice) {
                $billing->lines()->create(['org_id' => $orgId, 'invoice_id' => $invoice->id, 'amount_due' => $invoice->balance_due]);
            }

            $this->audit($request, 'billing_note.create', 'billing_note', $billing->id, null, $billing->fresh()->only(['customer_id', 'billing_no', 'status', 'total']));
        });

        return back()->with('success', 'Billing note created.');
    }

    public function storeDeliveryOrder(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'invoice_id' => ['required', 'uuid', Rule::exists('invoices', 'id')->where('org_id', $orgId)],
            'delivery_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'delivered'])],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'uuid', Rule::exists('warehouses', 'id')->where('org_id', $orgId)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $numbers, $orgId, $validated): void {
            $invoice = Invoice::where('org_id', $orgId)->with('items')->lockForUpdate()->findOrFail($validated['invoice_id']);
            abort_if($invoice->status === 'void', 422, 'Cannot create delivery order from void invoice.');
            abort_if(DeliveryOrder::where('org_id', $orgId)->where('invoice_id', $invoice->id)->where('status', 'delivered')->lockForUpdate()->exists(), 422, 'Invoice has already been delivered.');
            $order = DeliveryOrder::create([
                'org_id' => $orgId,
                'invoice_id' => $invoice->id,
                'do_no' => $numbers->next($orgId, 'delivery_order'),
                'status' => $validated['status'],
                'delivery_date' => $validated['delivery_date'],
                'receiver_name' => $validated['receiver_name'] ?? null,
                'delivered_at' => $validated['status'] === 'delivered' ? now() : null,
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($invoice->items as $item) {
                $order->items()->create(['org_id' => $orgId, 'product_id' => $item->product_id, 'description' => $item->description, 'quantity' => $item->quantity, 'unit' => $item->unit]);
                if ($validated['status'] === 'delivered' && $item->product_id) {
                    $stock = StockMovement::where('org_id', $orgId)->where('product_id', $item->product_id)
                        ->when($validated['warehouse_id'] ?? null, fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId));
                    $onHand = (float) $stock->sum('quantity');
                    abort_if((float) $item->quantity > $onHand, 422, 'Delivery order cannot make stock negative.');
                    $unitCost = $onHand > 0 ? round((float) $stock->sum('base_total_cost') / $onHand, 2) : 0;
                    $currency = $request->user()->organization?->currency ?: 'THB';
                    StockMovement::create(['org_id' => $orgId, 'product_id' => $item->product_id, 'warehouse_id' => $validated['warehouse_id'] ?? null, 'movement_type' => 'deliver_to_customer', 'movement_date' => $validated['delivery_date'], 'quantity' => -((float) $item->quantity), 'unit_cost' => $unitCost, 'total_cost' => -((float) $item->quantity * $unitCost), 'currency' => $currency, 'base_currency' => $currency, 'exchange_rate' => 1, 'base_unit_cost' => $unitCost, 'base_total_cost' => -((float) $item->quantity * $unitCost), 'note' => $order->do_no, 'created_by' => $request->user()->id]);
                    $this->notifyLowStock($item->product_id, $orgId, app(NotificationService::class));
                }
            }

            $this->audit($request, 'delivery_order.create', 'delivery_order', $order->id, null, $order->fresh()->only(['invoice_id', 'do_no', 'status', 'delivery_date', 'receiver_name']));
        });

        return back()->with('success', 'Delivery order created.');
    }

    public function storePurchaseRequest(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('org_id', $orgId)],
            'request_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $numbers, $orgId, $validated): void {
            $total = collect($validated['items'])->sum(fn ($item) => round((float) $item['quantity'] * (float) ($item['unit_price'] ?? 0), 2));
            $pr = PurchaseRequest::create(['org_id' => $orgId, 'supplier_id' => $validated['supplier_id'] ?? null, 'pr_no' => $numbers->next($orgId, 'purchase_request'), 'status' => 'draft', 'request_date' => $validated['request_date'], 'total' => $total, 'reason' => $validated['reason'] ?? null, 'created_by' => $request->user()->id]);
            foreach ($validated['items'] as $item) {
                $pr->items()->create(['org_id' => $orgId, 'description' => $item['description'], 'quantity' => $item['quantity'], 'unit' => $item['unit'] ?? null, 'unit_price' => $item['unit_price'] ?? 0, 'line_total' => round((float) $item['quantity'] * (float) ($item['unit_price'] ?? 0), 2)]);
            }

            $this->audit($request, 'purchase_request.create', 'purchase_request', $pr->id, null, $pr->fresh()->only(['supplier_id', 'pr_no', 'status', 'total']));
        });

        return back()->with('success', 'Purchase request created.');
    }

    public function approvePurchaseRequest(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($purchaseRequest->org_id === $request->user()->org_id, 403);
        abort_unless($purchaseRequest->status === 'draft', 422, 'Only draft PR can be approved.');
        $before = $purchaseRequest->only(['status', 'approved_at', 'approved_by']);
        $purchaseRequest->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        $this->audit($request, 'purchase_request.approve', 'purchase_request', $purchaseRequest->id, $before, $purchaseRequest->fresh()->only(['status', 'approved_at', 'approved_by']));

        return back()->with('success', 'Purchase request approved.');
    }

    public function convertPurchaseRequest(Request $request, PurchaseRequest $purchaseRequest, NumberSequenceService $numbers): RedirectResponse
    {
        abort_unless($purchaseRequest->org_id === $request->user()->org_id, 403);
        abort_unless($purchaseRequest->status === 'approved', 422, 'Only approved PR can be converted.');
        abort_unless($purchaseRequest->supplier_id, 422, 'Supplier is required before converting PR to PO.');

        DB::transaction(function () use ($request, $purchaseRequest, $numbers): void {
            $purchaseRequest->load('items');
            $po = PurchaseOrder::create(['org_id' => $purchaseRequest->org_id, 'supplier_id' => $purchaseRequest->supplier_id, 'po_no' => $numbers->next($purchaseRequest->org_id, 'purchase_order'), 'status' => 'draft', 'order_date' => now()->toDateString(), 'tax_mode' => 'no_tax', 'subtotal' => $purchaseRequest->total, 'total' => $purchaseRequest->total, 'currency' => 'THB', 'note' => $purchaseRequest->reason, 'created_by' => $request->user()->id]);
            foreach ($purchaseRequest->items as $item) {
                $po->items()->create(['org_id' => $purchaseRequest->org_id, 'description' => $item->description, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total, 'sort_order' => 0]);
            }
            $before = $purchaseRequest->only(['status', 'converted_at', 'converted_po_id']);
            $purchaseRequest->update(['status' => 'converted', 'converted_at' => now(), 'converted_po_id' => $po->id]);
            $this->audit($request, 'purchase_request.convert', 'purchase_request', $purchaseRequest->id, $before, $purchaseRequest->fresh()->only(['status', 'converted_at', 'converted_po_id']));
        });

        return back()->with('success', 'Purchase request converted to PO.');
    }

    public function storeVoucher(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'type' => ['required', Rule::in(['payment', 'receipt'])],
            'voucher_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $voucher = Voucher::create([
            'org_id' => $orgId,
            'voucher_no' => $numbers->next($orgId, $validated['type'] === 'payment' ? 'payment_voucher' : 'receipt_voucher'),
            'type' => $validated['type'],
            'status' => 'issued',
            'voucher_date' => $validated['voucher_date'],
            'amount' => $validated['amount'],
            'partner_name' => $validated['partner_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->audit($request, 'voucher.create', 'voucher', $voucher->id, null, $voucher->only(['voucher_no', 'type', 'status', 'amount']));

        return back()->with('success', 'Voucher created.');
    }

    public function storeVoucherAttachment(Request $request, Voucher $voucher, FileAttachmentManager $files): RedirectResponse
    {
        abort_unless($voucher->org_id === $request->user()->org_id, 404);
        $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.FileAttachmentManager::MAX_KILOBYTES],
        ]);

        DB::transaction(function () use ($request, $voucher, $files): void {
            $lockedVoucher = Voucher::where('org_id', $request->user()->org_id)->lockForUpdate()->findOrFail($voucher->id);
            $previousFileId = $lockedVoucher->attachment_file_id;
            $files->delete($lockedVoucher->attachment);
            $file = $files->store($request, $request->file('attachment'), 'voucher', $lockedVoucher->id, 'voucher_proof');
            $lockedVoucher->update(['attachment_file_id' => $file->id]);
            $this->audit($request, 'voucher.attachment.upload', 'voucher', $lockedVoucher->id, ['attachment_file_id' => $previousFileId], ['attachment_file_id' => $file->id]);
        });

        return back()->with('success', 'Voucher proof uploaded.');
    }

    public function print(Request $request, string $type, string $id): View
    {
        $document = $this->commercialDocument($request, $type, $id);

        return view('documents.commercial-print', $document);
    }

    public function pdf(Request $request, string $type, string $id): HttpResponse
    {
        $view = $this->print($request, $type, $id)->with('pdf', true);

        return Pdf::loadHTML($view->render())
            ->setPaper('a4')
            ->download("{$type}-{$id}.pdf");
    }

    private function simpleTotals(array $items, string $taxMode): array
    {
        $subtotal = collect($items)->sum(fn ($item) => round((float) $item['quantity'] * (float) $item['unit_price'], 2));
        $tax = $taxMode === 'no_tax' ? 0 : collect($items)->sum(fn ($item) => round((float) $item['quantity'] * (float) $item['unit_price'] * (float) ($item['tax_rate'] ?? 0) / 100, 2));

        return ['subtotal' => round($subtotal, 2), 'tax_amount' => round($tax, 2), 'total' => round($subtotal + $tax, 2)];
    }

    private function notifyLowStock(string $productId, string $orgId, NotificationService $notifications): void
    {
        $product = Product::where('org_id', $orgId)->where('reorder_point', '>', 0)->find($productId);
        if (! $product) {
            return;
        }
        $onHand = (float) StockMovement::where('org_id', $orgId)->where('product_id', $productId)->sum('quantity');
        if ($onHand > (float) $product->reorder_point) {
            return;
        }
        User::where('org_id', $orgId)->where('status', 'active')->whereHas('roles.permissions', fn ($query) => $query->whereIn('code', ['inventory.view', 'inventory.adjust']))->get()->each(function (User $user) use ($notifications, $product, $onHand): void {
            $notifications->notify($user, 'inventory.low_stock', "inventory.low_stock:{$product->id}:".today()->toDateString(), "Low stock: {$product->name}", "On hand: {$onHand} {$product->unit}; reorder point: {$product->reorder_point}.", route('inventory-operations.index'));
        });
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $request->user()->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ]);
    }

    private function commercialDocument(Request $request, string $type, string $id): array
    {
        $orgId = $request->user()->org_id;
        $organization = $request->user()->organization;

        $payload = match ($type) {
            'credit-debit-note' => $this->creditDebitPayload(CreditDebitNote::where('org_id', $orgId)->with(['invoice.customer', 'items'])->findOrFail($id)),
            'billing-note' => $this->billingPayload(BillingNote::where('org_id', $orgId)->with(['customer', 'lines.invoice'])->findOrFail($id)),
            'delivery-order' => $this->deliveryPayload(DeliveryOrder::where('org_id', $orgId)->with(['invoice.customer', 'items'])->findOrFail($id)),
            'purchase-request' => $this->purchaseRequestPayload(PurchaseRequest::where('org_id', $orgId)->with(['supplier', 'items'])->findOrFail($id)),
            'voucher' => $this->voucherPayload(Voucher::where('org_id', $orgId)->findOrFail($id)),
            default => abort(404),
        };

        return array_merge($payload, [
            'organization' => [
                'name' => $organization?->legal_name ?: $organization?->name ?: 'Organization',
                'tax_id' => $organization?->tax_id,
                'address' => $organization?->address,
            ],
            'pdf' => false,
        ]);
    }

    private function creditDebitPayload(CreditDebitNote $note): array
    {
        return [
            'title' => $note->type === 'credit' ? 'Credit Note' : 'Debit Note',
            'number' => $note->note_no,
            'date' => $note->issue_date?->toDateString() ?: '-',
            'partner' => $note->invoice?->customer?->company_name ?: '-',
            'status' => $note->status,
            'total' => $note->total,
            'rows' => $note->items->map(fn ($item) => ['description' => $item->description, 'quantity' => $item->quantity, 'amount' => $item->line_total])->all(),
        ];
    }

    private function billingPayload(BillingNote $billing): array
    {
        return [
            'title' => 'Billing Note',
            'number' => $billing->billing_no,
            'date' => $billing->issue_date?->toDateString() ?: '-',
            'partner' => $billing->customer?->company_name ?: '-',
            'status' => $billing->status,
            'total' => $billing->total,
            'rows' => $billing->lines->map(fn ($line) => ['description' => $line->invoice?->invoice_no ?: '-', 'quantity' => 1, 'amount' => $line->amount_due])->all(),
        ];
    }

    private function deliveryPayload(DeliveryOrder $order): array
    {
        return [
            'title' => 'Delivery Order',
            'number' => $order->do_no,
            'date' => $order->delivery_date?->toDateString() ?: '-',
            'partner' => $order->invoice?->customer?->company_name ?: '-',
            'status' => $order->status,
            'total' => null,
            'rows' => $order->items->map(fn ($item) => ['description' => $item->description, 'quantity' => $item->quantity, 'amount' => null])->all(),
        ];
    }

    private function purchaseRequestPayload(PurchaseRequest $request): array
    {
        return [
            'title' => 'Purchase Request',
            'number' => $request->pr_no,
            'date' => $request->request_date?->toDateString() ?: '-',
            'partner' => $request->supplier?->name ?: '-',
            'status' => $request->status,
            'total' => $request->total,
            'rows' => $request->items->map(fn ($item) => ['description' => $item->description, 'quantity' => $item->quantity, 'amount' => $item->line_total])->all(),
        ];
    }

    private function voucherPayload(Voucher $voucher): array
    {
        return [
            'title' => $voucher->type === 'payment' ? 'Payment Voucher' : 'Receipt Voucher',
            'number' => $voucher->voucher_no,
            'date' => $voucher->voucher_date?->toDateString() ?: '-',
            'partner' => $voucher->partner_name ?: '-',
            'status' => $voucher->status,
            'total' => $voucher->amount,
            'rows' => [['description' => $voucher->description ?: $voucher->type, 'quantity' => 1, 'amount' => $voucher->amount]],
        ];
    }
}
