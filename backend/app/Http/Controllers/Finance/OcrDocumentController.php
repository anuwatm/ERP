<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\OcrExtraction;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\FxRateService;
use App\Services\NumberSequenceService;
use App\Services\Ocr\OcrProcessingService;
use App\Support\FileAttachmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OcrDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ocr.view'), 403);

        $status = $request->query('status');
        $search = $request->query('search');

        $extractions = OcrExtraction::where('org_id', $user->org_id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('raw_response_text', 'like', "%{$search}%")
                        ->orWhereHas('storedFile', fn ($f) => $f->where('file_name', 'like', "%{$search}%"));
                });
            })
            ->with(['storedFile:id,file_name,mime_type,size_bytes', 'creator:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Finance/OcrIndex', [
            'extractions' => $extractions,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'statuses' => OcrExtraction::STATUSES,
            'canUpload' => $user->hasPermissionCode('ocr.upload'),
            'canConvert' => $user->hasPermissionCode('ocr.convert'),
        ]);
    }

    public function upload(
        Request $request,
        FileAttachmentManager $files,
        OcrProcessingService $ocr
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ocr.upload'), 403);

        $request->validate([
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $upload = $request->file('document');
        $extractionId = (string) Str::orderedUuid();

        // 1. Store file in private tenant storage
        $storedFile = $files->store($request, $upload, 'ocr_extraction', $extractionId, 'receipt');

        // 2. Create OCR extraction record
        $extraction = OcrExtraction::create([
            'id' => $extractionId,
            'org_id' => $user->org_id,
            'stored_file_id' => $storedFile->id,
            'status' => OcrExtraction::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        // 3. Process with AI Vision OCR
        try {
            $ocr->process($extraction);

            return redirect()->route('ocr.show', $extraction->id)
                ->with('success', 'Document scanned and parsed successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('ocr.show', $extraction->id)
                ->with('error', 'AI OCR processing failed. Please verify AI provider configuration or retry.');
        }
    }

    public function show(Request $request, OcrExtraction $extraction): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ocr.view'), 403);
        abort_unless($extraction->org_id === $user->org_id, 403);

        $extraction->load([
            'storedFile:id,file_name,mime_type,size_bytes',
            'fieldResults',
            'creator:id,name',
            'reviewer:id,name',
        ]);

        $suppliers = Supplier::where('org_id', $user->org_id)
            ->orderBy('name')
            ->get(['id', 'name', 'tax_id']);

        $projects = Project::where('org_id', $user->org_id)
            ->orderBy('name')
            ->get(['id', 'project_code', 'name']);

        $purchaseOrders = PurchaseOrder::where('org_id', $user->org_id)
            ->whereIn('status', ['approved', 'partially_received'])
            ->orderBy('po_no')
            ->get(['id', 'po_no', 'supplier_id', 'total']);

        return Inertia::render('Finance/OcrReviewer', [
            'extraction' => $extraction,
            'suppliers' => $suppliers,
            'projects' => $projects,
            'purchaseOrders' => $purchaseOrders,
            'categories' => Expense::CATEGORIES,
            'canConvert' => $user->hasPermissionCode('ocr.convert'),
        ]);
    }

    public function convertExpense(
        Request $request,
        OcrExtraction $extraction,
        NumberSequenceService $numbers,
        FxRateService $fxRates
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ocr.convert'), 403);
        abort_unless($extraction->org_id === $user->org_id, 403);
        abort_unless(in_array($extraction->status, [OcrExtraction::STATUS_EXTRACTED, OcrExtraction::STATUS_PENDING, OcrExtraction::STATUS_FAILED]), 422, 'Document has already been processed or converted.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', Expense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'expense_date' => ['required', 'date'],
            'tax_mode' => ['required', 'string', 'in:none,include,exclude'],
            'tax_invoice_no' => ['nullable', 'string', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('org_id', $user->org_id)],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $user->org_id)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $currency = strtoupper($validated['currency']);
        $expenseDate = $validated['expense_date'];
        $amount = (float) $validated['amount'];
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);

        $payableTotal = $validated['tax_mode'] === 'exclude' ? $amount + $taxAmount : $amount;

        $snapshot = $fxRates->snapshot($user->org_id, $currency, $expenseDate, [
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'payable_total' => $payableTotal,
        ]);

        $expense = DB::transaction(function () use ($request, $user, $extraction, $validated, $amount, $taxAmount, $payableTotal, $snapshot, $numbers) {
            // 1. Create Draft Expense
            $expense = Expense::create(array_merge([
                'org_id' => $user->org_id,
                'expense_no' => $numbers->next($user->org_id, 'expense'),
                'title' => $validated['title'],
                'category' => $validated['category'],
                'amount' => $amount,
                'currency' => $validated['currency'],
                'expense_date' => $validated['expense_date'],
                'tax_mode' => $validated['tax_mode'],
                'tax_invoice_no' => $validated['tax_invoice_no'] ?? null,
                'tax_amount' => $taxAmount,
                'payable_total' => $payableTotal,
                'balance_due' => $payableTotal,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'receipt_file_id' => $extraction->stored_file_id,
                'status' => 'draft',
                'created_by' => $user->id,
            ], $snapshot));

            // Link stored file to expense as well
            if ($extraction->storedFile) {
                $extraction->storedFile->update([
                    'entity_type' => 'expense',
                    'entity_id' => $expense->id,
                ]);
            }

            // 2. Mark Extraction converted
            $extraction->update([
                'status' => OcrExtraction::STATUS_CONVERTED,
                'target_type' => 'expense',
                'target_id' => $expense->id,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            // 3. Audit Log
            AuditLog::create([
                'org_id' => $user->org_id,
                'actor_user_id' => $user->id,
                'action' => 'ocr.convert_to_expense',
                'entity_type' => 'expense',
                'entity_id' => $expense->id,
                'after_json' => [
                    'expense_no' => $expense->expense_no,
                    'ocr_extraction_id' => $extraction->id,
                    'amount' => $expense->amount,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $expense;
        });

        return redirect()->route('expenses.index')
            ->with('success', "Draft Expense {$expense->expense_no} created from OCR document.");
    }

    public function convertVendorBill(
        Request $request,
        OcrExtraction $extraction,
        NumberSequenceService $numbers,
        FxRateService $fxRates
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ocr.convert'), 403);
        abort_unless($extraction->org_id === $user->org_id, 403);
        abort_unless(in_array($extraction->status, [OcrExtraction::STATUS_EXTRACTED, OcrExtraction::STATUS_PENDING, OcrExtraction::STATUS_FAILED]), 422, 'Document has already been processed or converted.');

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')->where('org_id', $user->org_id)],
            'purchase_order_id' => ['nullable', 'uuid', Rule::exists('purchase_orders', 'id')->where('org_id', $user->org_id)],
            'category' => ['nullable', 'string', 'in:'.implode(',', Expense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'expense_date' => ['required', 'date'],
            'tax_mode' => ['required', 'string', 'in:none,include,exclude'],
            'tax_invoice_no' => ['nullable', 'string', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $user->org_id)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($validated['purchase_order_id'])) {
            $po = PurchaseOrder::where('org_id', $user->org_id)->find($validated['purchase_order_id']);
            if ($po && $po->supplier_id !== $validated['supplier_id']) {
                return back()->withErrors(['purchase_order_id' => 'Selected purchase order does not belong to the selected supplier.']);
            }
        }

        $currency = strtoupper($validated['currency']);
        $expenseDate = $validated['expense_date'];
        $amount = (float) $validated['amount'];
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);

        $payableTotal = $validated['tax_mode'] === 'exclude' ? $amount + $taxAmount : $amount;

        $snapshot = $fxRates->snapshot($user->org_id, $currency, $expenseDate, [
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'payable_total' => $payableTotal,
        ]);

        $supplier = Supplier::where('org_id', $user->org_id)->find($validated['supplier_id']);
        $title = $validated['title'] ?: ('Vendor Bill: '.($validated['tax_invoice_no'] ?: ($supplier?->name ?? 'Supplier')));

        $expense = DB::transaction(function () use ($request, $user, $extraction, $validated, $title, $amount, $taxAmount, $payableTotal, $snapshot, $numbers) {
            // 1. Create Draft Expense as Vendor Bill
            $expense = Expense::create(array_merge([
                'org_id' => $user->org_id,
                'expense_no' => $numbers->next($user->org_id, 'expense'),
                'title' => $title,
                'category' => $validated['category'] ?? 'office',
                'amount' => $amount,
                'currency' => $validated['currency'],
                'expense_date' => $validated['expense_date'],
                'tax_mode' => $validated['tax_mode'],
                'tax_invoice_no' => $validated['tax_invoice_no'] ?? null,
                'tax_amount' => $taxAmount,
                'payable_total' => $payableTotal,
                'balance_due' => $payableTotal,
                'supplier_id' => $validated['supplier_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'receipt_file_id' => $extraction->stored_file_id,
                'status' => 'draft',
                'created_by' => $user->id,
            ], $snapshot));

            // Link stored file to expense
            if ($extraction->storedFile) {
                $extraction->storedFile->update([
                    'entity_type' => 'expense',
                    'entity_id' => $expense->id,
                ]);
            }

            // 2. Mark Extraction converted to vendor_bill
            $extraction->update([
                'status' => OcrExtraction::STATUS_CONVERTED,
                'target_type' => 'vendor_bill',
                'target_id' => $expense->id,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            // 3. Audit Log
            AuditLog::create([
                'org_id' => $user->org_id,
                'actor_user_id' => $user->id,
                'action' => 'ocr.convert_to_vendor_bill',
                'entity_type' => 'expense',
                'entity_id' => $expense->id,
                'after_json' => [
                    'expense_no' => $expense->expense_no,
                    'ocr_extraction_id' => $extraction->id,
                    'supplier_id' => $expense->supplier_id,
                    'purchase_order_id' => $expense->purchase_order_id,
                    'amount' => $expense->amount,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $expense;
        });

        return redirect()->route('expenses.index')
            ->with('success', "Draft Vendor Bill {$expense->expense_no} created from OCR document.");
    }

    public function reject(Request $request, OcrExtraction $extraction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ocr.convert'), 403);
        abort_unless($extraction->org_id === $user->org_id, 403);

        $extraction->update([
            'status' => OcrExtraction::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Document marked as rejected.');
    }
}
