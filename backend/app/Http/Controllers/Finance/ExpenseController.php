<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Project;
use App\Services\NumberSequenceService;
use App\Support\FileAttachmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
            ->with(['receiptFile:id,file_name,mime_type,size_bytes', 'project:id,project_code,name'])
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
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers, FileAttachmentManager $files): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateExpense($request);

        $expense = DB::transaction(function () use ($request, $user, $validated, $numbers, $files): Expense {
            $expensePayload = $validated;
            unset($expensePayload['receipt']);

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
        ]);

        $before = $this->snapshot($expense);
        $expense->update([
            'status' => 'paid',
            'paid_at' => filled($validated['paid_at'] ?? null) ? $validated['paid_at'] : now(),
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

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'expense_date' => ['required', 'date'],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $request->user()->org_id)],
            'supplier_id' => ['nullable', 'uuid'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.FileAttachmentManager::MAX_KILOBYTES],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
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
        return $expense->fresh()->only(['expense_no', 'category', 'title', 'amount', 'expense_date', 'project_id', 'supplier_id', 'status', 'receipt_file_id', 'approved_by', 'approved_at', 'paid_at', 'note']);
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
