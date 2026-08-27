<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Services\BahtText;
use App\Services\NumberSequenceService;
use App\Support\FileAttachmentManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'category', 'project_id']);

        $expenses = Expense::query()
            ->where('org_id', $user->org_id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('expense_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->with(['receiptFile:id,file_name,mime_type,size_bytes', 'project:id,project_code,name', 'supplier:id,name,tax_id', 'bankAccount:id,bank_name,account_name'])
            ->latest('expense_date')
            ->latest()
            ->get();

        return Inertia::render('Finance/Expenses', [
            'expenses' => $expenses,
            'statuses' => Expense::STATUSES,
            'categories' => Expense::CATEGORIES,
            'projects' => Project::where('org_id', $user->org_id)->orderBy('name')->get(['id', 'project_code', 'name']),
            'filters' => $filters,
            'canCreateExpenses' => $user->hasPermissionCode('expenses.create'),
            'canUpdateExpenses' => $user->hasPermissionCode('expenses.update'),
            'canApproveExpenses' => $user->hasPermissionCode('expenses.approve'),
            'canPayExpenses' => $user->hasPermissionCode('expenses.pay'),
            'canRejectExpenses' => $user->hasPermissionCode('expenses.reject'),
            'bankAccounts' => BankAccount::where('org_id', $user->org_id)->where('status', 'active')->orderBy('account_name')->get(['id', 'bank_name', 'account_name']),
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers, FileAttachmentManager $files): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateExpense($request);

        $expense = DB::transaction(function () use ($request, $user, $validated, $numbers, $files): Expense {
            $expensePayload = $validated;
            unset($expensePayload['receipt']);
            $expensePayload = $this->withholdingPayload($this->taxPayload($expensePayload));

            $expense = Expense::create(array_merge($expensePayload, [
                'org_id' => $user->org_id,
                'expense_no' => $numbers->next($user->org_id, 'expense'),
                'status' => 'draft',
                'created_by' => $user->id,
            ]));

            if ($request->hasFile('receipt')) {
                $files->delete($expense->receiptFile);
                $file = $files->store($request, $request->file('receipt'), 'expense', $expense->id, 'receipt');
                $expense->update(['receipt_file_id' => $file->id]);
            }

            $this->audit($request, 'expense.create', $expense, null, $this->snapshot($expense));

            return $expense;
        });

        return back()->with('success', "Expense {$expense->expense_no} created.");
    }

    public function update(Request $request, Expense $expense, FileAttachmentManager $files): RedirectResponse
    {
        abort_unless($expense->org_id === $request->user()->org_id, 403);
        abort_unless($expense->status === 'draft', 422, 'Only draft expenses can be edited.');

        $validated = $this->validateExpense($request);
        $before = $this->snapshot($expense);

        $expensePayload = $validated;
        unset($expensePayload['receipt']);
        $expensePayload = $this->withholdingPayload($this->taxPayload($expensePayload));

        $expense->update(array_merge($expensePayload, [
            'updated_by' => $request->user()->id,
        ]));

        if ($request->hasFile('receipt')) {
            $files->delete($expense->receiptFile);
            $file = $files->store($request, $request->file('receipt'), 'expense', $expense->id, 'receipt');
            $expense->update(['receipt_file_id' => $file->id]);
        }

        $this->audit($request, 'expense.update', $expense, $before, $this->snapshot($expense));

        return back()->with('success', "Expense {$expense->expense_no} updated.");
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->org_id === $request->user()->org_id, 403);
        abort_unless($expense->status === 'draft', 422, 'Only draft expenses can be approved.');

        $before = $this->snapshot($expense);
        $expense->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'expense.approve', $expense, $before, $this->snapshot($expense));

        return back()->with('success', "Expense {$expense->expense_no} approved.");
    }

    public function pay(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->org_id === $request->user()->org_id, 403);
        abort_unless($expense->status === 'approved', 422, 'Only approved expenses can be paid.');

        $validated = $request->validate([
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
        ]);

        $before = $this->snapshot($expense);
        $expense->update([
            'status' => 'paid',
            'paid_at' => filled($validated['paid_at'] ?? null) ? $validated['paid_at'] : now(),
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'note' => $this->appendStatusNote($expense->note, 'Paid', $validated['note'] ?? null),
            'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'expense.pay', $expense, $before, $this->snapshot($expense));

        return back()->with('success', "Expense {$expense->expense_no} paid.");
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->org_id === $request->user()->org_id, 403);
        abort_unless(in_array($expense->status, ['draft', 'approved'], true), 422, 'Only draft or approved expenses can be rejected.');

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $before = $this->snapshot($expense);
        $expense->update([
            'status' => 'rejected',
            'note' => $this->appendStatusNote($expense->note, 'Rejected', $validated['note']),
            'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'expense.reject', $expense, $before, $this->snapshot($expense));

        return back()->with('success', "Expense {$expense->expense_no} rejected.");
    }

    public function withholdingCertificate(Request $request, Expense $expense, BahtText $bahtText): HttpResponse
    {
        abort_unless($expense->org_id === $request->user()->org_id, 403);
        abort_unless((float) $expense->withholding_tax_amount > 0, 404);

        $expense->load('supplier');
        $organization = $request->user()->organization;

        return Pdf::loadView('documents.withholding-certificate', [
            'organization' => [
                'legal_name' => $organization?->legal_name ?: $organization?->name ?: 'Organization',
                'tax_id' => $organization?->tax_id,
                'address' => $organization?->address,
                'phone' => $organization?->phone,
            ],
            'supplier' => [
                'name' => $expense->supplier?->name ?: '-',
                'tax_id' => $expense->supplier?->tax_id,
                'address' => $expense->supplier?->address,
            ],
            'expense' => [
                'expense_no' => $expense->expense_no,
                'date' => $expense->expense_date?->toDateString() ?: '-',
                'title' => $expense->title,
                'form' => $expense->withholding_tax_form ?: 'pnd53',
                'base_amount' => $this->money($expense->amount),
                'rate' => $this->money($expense->withholding_tax_rate),
                'withholding_amount' => $this->money($expense->withholding_tax_amount),
                'baht_text' => $bahtText->convert($expense->withholding_tax_amount),
            ],
        ])->setPaper('a4')->download("withholding-certificate-{$expense->expense_no}.pdf");
    }

    private function validateExpense(Request $request): array
    {
        return Validator::make($request->all(), [
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'tax_mode' => ['nullable', Rule::in(['no_tax', 'exclusive', 'inclusive'])],
            'tax_invoice_no' => ['nullable', 'string', 'max:50'],
            'withholding_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'withholding_tax_form' => ['nullable', Rule::in(['pnd3', 'pnd53'])],
            'expense_date' => ['required', 'date'],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $request->user()->org_id)],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('org_id', $request->user()->org_id)],
            'purchase_order_id' => ['nullable', 'uuid', Rule::exists('purchase_orders', 'id')->where('org_id', $request->user()->org_id)],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.FileAttachmentManager::MAX_KILOBYTES],
            'note' => ['nullable', 'string', 'max:2000'],
        ])->after(function ($validator) use ($request): void {
            if (! filled($request->input('purchase_order_id'))) {
                return;
            }

            $purchaseOrder = PurchaseOrder::where('org_id', $request->user()->org_id)->find($request->input('purchase_order_id'));
            if (! $purchaseOrder) {
                return;
            }

            if (filled($request->input('supplier_id')) && $purchaseOrder->supplier_id !== $request->input('supplier_id')) {
                $validator->errors()->add('purchase_order_id', 'Purchase order must belong to selected supplier.');
            }
        })->validate();
    }

    private function appendStatusNote(?string $note, string $label, ?string $statusNote): ?string
    {
        if (! filled($statusNote)) {
            return $note;
        }

        $entry = '['.$label.' '.now()->toDateString().']: '.$statusNote;

        return filled($note) ? $note.PHP_EOL.$entry : $entry;
    }

    private function snapshot(Expense $expense): array
    {
        return $expense->fresh()->only(['expense_no', 'category', 'title', 'amount', 'tax_mode', 'tax_invoice_no', 'tax_amount', 'withholding_tax_rate', 'withholding_tax_amount', 'withholding_tax_form', 'expense_date', 'project_id', 'supplier_id', 'purchase_order_id', 'status', 'receipt_file_id', 'approved_by', 'approved_at', 'paid_at', 'note']);
    }

    private function taxPayload(array $payload): array
    {
        $mode = $payload['tax_mode'] ?? 'no_tax';
        $amount = round((float) $payload['amount'], 2);
        $taxAmount = match ($mode) {
            'exclusive' => round($amount * 0.07, 2),
            'inclusive' => round($amount - ($amount / 1.07), 2),
            default => 0.0,
        };

        $payload['tax_mode'] = $mode;
        $payload['tax_amount'] = $taxAmount;
        $payload['tax_invoice_no'] = filled($payload['tax_invoice_no'] ?? null) ? $payload['tax_invoice_no'] : null;

        return $payload;
    }

    private function withholdingPayload(array $payload): array
    {
        $rate = round((float) ($payload['withholding_tax_rate'] ?? 0), 2);
        $amount = round((float) $payload['amount'] * $rate / 100, 2);

        $payload['withholding_tax_rate'] = $rate;
        $payload['withholding_tax_amount'] = $amount;
        $payload['withholding_tax_form'] = $rate > 0 ? ($payload['withholding_tax_form'] ?? 'pnd53') : null;

        return $payload;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function audit(Request $request, string $action, Expense $expense, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $expense->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
