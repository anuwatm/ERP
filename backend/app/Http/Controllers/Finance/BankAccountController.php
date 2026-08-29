<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Finance/BankAccounts', [
            'accounts' => BankAccount::where('org_id', $user->org_id)
                ->with(['branch:id,code,name', 'chartOfAccount:id,code,name'])
                ->latest()
                ->get()
                ->map(fn (BankAccount $account) => $this->payload($account)),
            'branches' => Branch::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'chartAccounts' => ChartOfAccount::where('org_id', $user->org_id)->where('status', 'active')->where('is_postable', true)->where('account_type', 'asset')->orderBy('code')->get(['id', 'code', 'name']),
            'accountTypes' => BankAccount::ACCOUNT_TYPES,
            'statuses' => BankAccount::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAccount($request);
        $account = BankAccount::create(array_merge($validated, [
            'org_id' => $request->user()->org_id,
            'account_number_hash' => $this->accountNumberHash($validated['account_number']),
            'created_by' => $request->user()->id,
        ]));
        $this->audit($request, 'bank_account.create', $account, null, $this->auditPayload($account));

        return back()->with('success', 'Bank account created.');
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        abort_unless($bankAccount->org_id === $request->user()->org_id, 404);
        $before = $this->auditPayload($bankAccount);
        $validated = $this->validateAccount($request, $bankAccount);
        if ($validated['account_number'] ?? null) {
            $validated['account_number_hash'] = $this->accountNumberHash($validated['account_number']);
        } else {
            unset($validated['account_number']);
        }
        $validated['updated_by'] = $request->user()->id;
        $bankAccount->update($validated);
        $this->audit($request, 'bank_account.update', $bankAccount, $before, $this->auditPayload($bankAccount->fresh()));

        return back()->with('success', 'Bank account updated.');
    }

    private function validateAccount(Request $request, ?BankAccount $bankAccount = null): array
    {
        $orgId = $request->user()->org_id;
        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'chart_of_account_id' => ['nullable', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('org_id', $orgId)->where('status', 'active')->where('is_postable', true)],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => [$bankAccount ? 'nullable' : 'required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'account_type' => ['required', Rule::in(BankAccount::ACCOUNT_TYPES)],
            'currency' => ['required', 'string', 'size:3'],
            'is_cash_account' => ['required', 'boolean'],
            'status' => ['required', Rule::in(BankAccount::STATUSES)],
            'opening_balance' => ['required', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'opening_balance_date' => ['nullable', 'date'],
        ]);
        if ($validated['account_number'] ?? null) {
            $hash = $this->accountNumberHash($validated['account_number']);
            $duplicate = BankAccount::where('org_id', $orgId)->where('account_number_hash', $hash)->when($bankAccount, fn ($query) => $query->whereKeyNot($bankAccount->id))->exists();
            abort_if($duplicate, 422, 'Bank account number already exists in this organization.');
        }

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['bank_code'] = $validated['bank_code'] ?: null;
        $validated['branch_name'] = $validated['branch_name'] ?: null;

        return $validated;
    }

    private function accountNumberHash(string $accountNumber): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $accountNumber)));
    }

    private function payload(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'branch_id' => $account->branch_id,
            'chart_of_account_id' => $account->chart_of_account_id,
            'chart_of_account' => $account->chartOfAccount ? ['id' => $account->chartOfAccount->id, 'code' => $account->chartOfAccount->code, 'name' => $account->chartOfAccount->name] : null,
            'branch' => $account->branch ? ['id' => $account->branch->id, 'code' => $account->branch->code, 'name' => $account->branch->name] : null,
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'branch_name' => $account->branch_name,
            'account_name' => $account->account_name,
            'account_number_masked' => $account->maskedAccountNumber(),
            'account_type' => $account->account_type,
            'currency' => $account->currency,
            'is_cash_account' => $account->is_cash_account,
            'status' => $account->status,
            'opening_balance' => $account->opening_balance,
            'opening_balance_date' => $account->opening_balance_date?->toDateString(),
        ];
    }

    private function auditPayload(BankAccount $account): array
    {
        return [
            'branch_id' => $account->branch_id,
            'chart_of_account_id' => $account->chart_of_account_id,
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'branch_name' => $account->branch_name,
            'account_name' => $account->account_name,
            'account_number_masked' => $account->maskedAccountNumber(),
            'account_type' => $account->account_type,
            'currency' => $account->currency,
            'is_cash_account' => $account->is_cash_account,
            'status' => $account->status,
            'opening_balance' => $account->opening_balance,
            'opening_balance_date' => $account->opening_balance_date?->toDateString(),
        ];
    }

    private function audit(Request $request, string $action, BankAccount $account, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $account->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'bank_account',
            'entity_id' => $account->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ]);
    }
}
