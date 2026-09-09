<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentGatewayConfig;
use App\Models\PortalUser;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\QuotationAcceptance;
use App\Models\Supplier;
use App\Models\VendorBillSubmission;
use App\Services\NumberSequenceService;
use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function provision(Request $request, PortalAccessService $access): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate(['party_type' => ['required', 'in:customer,supplier'], 'party_id' => ['required', 'uuid'], 'email' => ['required', 'email', 'max:255'], 'name' => ['nullable', 'string', 'max:150']]);
        $model = $data['party_type'] === 'customer' ? Customer::class : Supplier::class;
        abort_unless($model::whereKey($data['party_id'])->where('org_id', $actor->org_id)->exists(), 404);
        $portalUser = PortalUser::updateOrCreate(['org_id' => $actor->org_id, 'party_type' => $data['party_type'], 'party_id' => $data['party_id'], 'email' => $data['email']], ['name' => $data['name'], 'is_active' => true]);
        $token = $access->issueMagicLink($portalUser);
        Mail::raw('Your ERP portal access link: '.route('portal.consume', $token), fn ($mail) => $mail->to($portalUser->email)->subject('ERP portal access'));
        AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'portal_user.provisioned', 'entity_type' => 'portal_user', 'entity_id' => $portalUser->id, 'after_json' => ['party_type' => $portalUser->party_type, 'party_id' => $portalUser->party_id, 'email' => $portalUser->email], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', 'Portal access link queued.');
    }

    public function consume(string $token, Request $request, PortalAccessService $access): RedirectResponse
    {
        $session = $access->consumeMagicLink($token, $request);
        abort_unless($session, 404);

        return redirect()->route('portal.dashboard')->withCookie(cookie('erp_portal_session', $session, 480, '/', null, false, true, false, 'lax'));
    }

    public function dashboard(Request $request, PortalAccessService $access): Response
    {
        $user = $this->user($request, $access);
        $qrEnabled = PaymentGatewayConfig::where('org_id', $user->org_id)->where('enabled', true)->exists();
        $quotations = $user->party_type === 'customer' ? Quotation::where('org_id', $user->org_id)->where('customer_id', $user->party_id)->whereIn('status', ['sent', 'approved', 'converted'])->get(['id', 'quotation_no', 'status', 'issue_date', 'valid_until', 'total', 'currency']) : collect();
        $invoices = $user->party_type === 'customer' ? Invoice::where('org_id', $user->org_id)->where('customer_id', $user->party_id)->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])->get(['id', 'invoice_no', 'status', 'issue_date', 'due_date', 'total', 'balance_due', 'currency']) : collect();
        $submissions = $user->party_type === 'supplier' ? VendorBillSubmission::where('org_id', $user->org_id)->where('supplier_id', $user->party_id)->latest()->get(['id', 'vendor_invoice_no', 'invoice_date', 'amount', 'currency', 'status', 'created_at']) : collect();
        $purchaseOrders = $user->party_type === 'supplier' ? PurchaseOrder::where('org_id', $user->org_id)->where('supplier_id', $user->party_id)->whereIn('status', ['sent', 'approved', 'partially_received', 'received', 'closed'])->get(['id', 'po_no', 'status', 'order_date', 'expected_date', 'total', 'currency']) : collect();
        $payables = $user->party_type === 'supplier' ? Expense::where('org_id', $user->org_id)->where('supplier_id', $user->party_id)->whereIn('status', ['approved', 'partially_paid', 'paid'])->get(['id', 'expense_no', 'status', 'expense_date', 'payable_total', 'balance_due', 'currency', 'paid_at']) : collect();
        $documents = Document::where('org_id', $user->org_id)
            ->whereHas('links', fn ($query) => $query->where('linkable_type', $user->party_type)->where('linkable_id', $user->party_id))
            ->with('currentVersion:id,document_id,original_name,scan_status')
            ->latest()
            ->get(['id', 'document_no', 'title', 'current_version_id']);

        return Inertia::render('Portal/Dashboard', ['qrEnabled' => $qrEnabled, 'portalUser' => $user->only(['email', 'name', 'party_type']), 'quotations' => $quotations, 'invoices' => $invoices, 'submissions' => $submissions, 'purchaseOrders' => $purchaseOrders, 'payables' => $payables, 'documents' => $documents]);
    }

    public function acceptQuotation(Request $request, Quotation $quotation, PortalAccessService $access, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $this->user($request, $access);
        abort_unless($user->party_type === 'customer' && $quotation->org_id === $user->org_id && $quotation->customer_id === $user->party_id, 404);
        abort_unless($quotation->status === 'sent' && (! $quotation->valid_until || ! $quotation->valid_until->isPast()), 422);
        DB::transaction(function () use ($request, $quotation, $user, $numbers): void {
            QuotationAcceptance::firstOrCreate(['quotation_id' => $quotation->id], ['org_id' => $user->org_id, 'portal_user_id' => $user->id, 'accepted_by_name' => $request->string('accepted_by_name')->trim()->value() ?: ($user->name ?: $user->email), 'accepted_by_email' => $user->email, 'ip_hash' => hash('sha256', (string) $request->ip()), 'user_agent_hash' => hash('sha256', (string) $request->userAgent()), 'accepted_at' => now()]);
            $quotation->load('items');
            $invoice = Invoice::create(['org_id' => $quotation->org_id, 'branch_id' => $quotation->branch_id, 'invoice_no' => $numbers->next($quotation->org_id, 'invoice', $quotation->branch_id), 'customer_id' => $quotation->customer_id, 'deal_id' => $quotation->deal_id, 'quotation_id' => $quotation->id, 'status' => 'draft', 'tax_mode' => $quotation->tax_mode, 'issue_date' => today(), 'subtotal' => $quotation->subtotal, 'discount_amount' => $quotation->discount_amount, 'tax_amount' => $quotation->tax_amount, 'total' => $quotation->total, 'paid_amount' => 0, 'balance_due' => $quotation->total, 'currency' => $quotation->currency, 'base_currency' => $quotation->base_currency, 'exchange_rate' => $quotation->exchange_rate, 'base_subtotal' => $quotation->base_subtotal, 'base_tax_amount' => $quotation->base_tax_amount, 'base_total' => $quotation->base_total, 'base_paid_amount' => 0, 'base_balance_due' => $quotation->base_total, 'notes' => $quotation->notes, 'created_by' => $quotation->created_by]);
            foreach ($quotation->items as $item) {
                InvoiceItem::create($item->only(['org_id', 'product_id', 'description', 'quantity', 'unit', 'unit_price', 'discount_amount', 'tax_rate', 'line_total', 'sort_order']) + ['invoice_id' => $invoice->id]);
            }
            $quotation->update(['status' => 'converted', 'approved_at' => now(), 'converted_at' => now(), 'converted_invoice_id' => $invoice->id]);
            AuditLog::create(['org_id' => $user->org_id, 'action' => 'portal_quotation.accepted', 'entity_type' => 'quotation', 'entity_id' => $quotation->id, 'after_json' => ['portal_user_id' => $user->id, 'invoice_id' => $invoice->id], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });

        return back()->with('success', 'Quotation accepted.');
    }

    public function submitVendorBill(Request $request, PortalAccessService $access): RedirectResponse
    {
        $user = $this->user($request, $access);
        abort_unless($user->party_type === 'supplier', 403);
        $data = $request->validate(['vendor_invoice_no' => ['required', 'string', 'max:100'], 'invoice_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:0.01'], 'currency' => ['required', 'string', 'size:3'], 'note' => ['nullable', 'string', 'max:2000'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120']]);
        $file = $request->file('file');
        $extension = strtolower($file->extension());
        $key = 'tenants/'.$user->org_id.'/portal-intake/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($key, file_get_contents($file->getRealPath()));
        DB::transaction(function () use ($data, $user, $file, $key): void {
            $document = Document::create(['org_id' => $user->org_id, 'document_no' => 'PORTAL-'.Str::upper(Str::random(10)), 'title' => 'Vendor bill '.$data['vendor_invoice_no'], 'sensitivity' => 'finance_confidential', 'status' => 'active']);
            $version = DocumentVersion::create(['org_id' => $user->org_id, 'document_id' => $document->id, 'version_no' => 1, 'storage_key' => $key, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath()), 'scan_status' => 'pending_scan']);
            $document->update(['current_version_id' => $version->id]);
            DocumentLink::create(['org_id' => $user->org_id, 'document_id' => $document->id, 'linkable_type' => 'supplier', 'linkable_id' => $user->party_id, 'role' => 'supporting']);
            $submission = VendorBillSubmission::create($data + ['org_id' => $user->org_id, 'supplier_id' => $user->party_id, 'portal_user_id' => $user->id, 'document_id' => $document->id, 'status' => 'pending_scan']);
            AuditLog::create(['org_id' => $user->org_id, 'action' => 'portal_vendor_bill.submitted', 'entity_type' => 'vendor_bill_submission', 'entity_id' => $submission->id, 'after_json' => ['portal_user_id' => $user->id, 'document_id' => $document->id, 'status' => 'pending_scan'], 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
        });

        return back()->with('success', 'Vendor bill submitted for security scan and finance review.');
    }

    public function download(Request $request, DocumentVersion $version, PortalAccessService $access): StreamedResponse
    {
        $user = $this->user($request, $access);
        $version->load('document');
        abort_unless($version->org_id === $user->org_id && $version->scan_status === 'clean' && $this->ownsDocument($user, $version->document), 404);
        abort_unless(Storage::disk('local')->exists($version->storage_key), 404);
        AuditLog::create(['org_id' => $user->org_id, 'action' => 'portal_document.downloaded', 'entity_type' => 'document_version', 'entity_id' => $version->id, 'after_json' => ['portal_user_id' => $user->id], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return Storage::disk('local')->download($version->storage_key, $version->original_name);
    }

    public function signIn(): Response
    {
        return Inertia::render('Portal/SignIn');
    }

    public function logout(Request $request, PortalAccessService $access): RedirectResponse
    {
        $access->revoke($request);

        return redirect()->route('portal.sign-in')->withoutCookie('erp_portal_session');
    }

    private function user(Request $request, PortalAccessService $access): PortalUser
    {
        $user = $access->user($request);
        abort_unless($user, 401);

        return $user;
    }

    private function ownsDocument(PortalUser $user, Document $document): bool
    {
        return $document->links()->where('linkable_type', $user->party_type)->where('linkable_id', $user->party_id)->exists();
    }
}
