<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\ChartOfAccountProvisioner;
use App\Services\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    public function chartOfAccounts(Request $request, ChartOfAccountProvisioner $provisioner): Response
    {
        $provisioner->ensure($request->user()->org_id);

        return Inertia::render('Accounting/ChartOfAccounts', [
            'accounts' => ChartOfAccount::where('org_id', $request->user()->org_id)->orderBy('code')->get(),
            'types' => ChartOfAccount::TYPES,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('chart_of_accounts')->where('org_id', $request->user()->org_id)],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in(ChartOfAccount::TYPES)],
            'parent_id' => ['nullable', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('org_id', $request->user()->org_id)],
            'is_postable' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        if (filled($data['parent_id'] ?? null)) {
            $parent = ChartOfAccount::where('org_id', $request->user()->org_id)->findOrFail($data['parent_id']);
            abort_if($parent->is_postable, 422, 'Parent account must not be postable.');
        }
        $account = ChartOfAccount::create(array_merge($data, [
            'org_id' => $request->user()->org_id,
            'normal_balance' => in_array($data['account_type'], ['asset', 'expense'], true) ? 'debit' : 'credit',
            'status' => 'active',
        ]));
        $this->audit($request, 'chart_of_account.create', 'chart_of_account', $account->id, ['code' => $account->code]);

        return back()->with('success', 'Chart of account created.');
    }

    public function updateAccount(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        abort_unless($chartOfAccount->org_id === $request->user()->org_id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'description' => ['nullable', 'string', 'max:2000']]);
        $chartOfAccount->update($data);
        $this->audit($request, 'chart_of_account.update', 'chart_of_account', $chartOfAccount->id, $data);

        return back()->with('success', 'Chart of account updated.');
    }

    public function periods(Request $request): Response
    {
        return Inertia::render('Accounting/Periods', ['periods' => AccountingPeriod::where('org_id', $request->user()->org_id)->latest('start_date')->get()]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]);
        $overlaps = AccountingPeriod::where('org_id', $request->user()->org_id)->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists();
        abort_if($overlaps, 422, 'Accounting period overlaps an existing period.');
        $period = AccountingPeriod::create(array_merge($data, ['org_id' => $request->user()->org_id, 'status' => 'open']));
        $this->audit($request, 'accounting_period.create', 'accounting_period', $period->id, $data);

        return back()->with('success', 'Accounting period created.');
    }

    public function closePeriod(Request $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        abort_unless($accountingPeriod->org_id === $request->user()->org_id, 404);
        abort_unless($accountingPeriod->status === 'open', 422, 'Accounting period is already closed.');
        abort_if(JournalEntry::where('org_id', $accountingPeriod->org_id)->where('accounting_period_id', $accountingPeriod->id)->where('status', 'draft')->exists(), 422, 'Cannot close period with draft journals.');
        $accountingPeriod->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()]);
        $this->audit($request, 'accounting_period.close', 'accounting_period', $accountingPeriod->id, ['status' => 'closed']);

        return back()->with('success', 'Accounting period closed.');
    }

    public function journals(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Accounting/Journals', [
            'journals' => JournalEntry::where('org_id', $orgId)->with(['period:id,name', 'lines.account:id,code,name'])->latest('posting_date')->get(),
            'accounts' => ChartOfAccount::where('org_id', $orgId)->where('status', 'active')->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeJournal(Request $request, JournalPostingService $journals): RedirectResponse
    {
        $data = $request->validate([
            'posting_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'max:30'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ]);
        $journals->post($request->user()->org_id, $request->user()->id, 'manual_journal', $data['idempotency_key'], 'posted', $data['posting_date'], $data['description'], $data['lines']);

        return back()->with('success', 'Journal posted.');
    }

    public function reverseJournal(Request $request, JournalEntry $journalEntry, JournalPostingService $journals): RedirectResponse
    {
        abort_unless($journalEntry->org_id === $request->user()->org_id, 404);
        $data = $request->validate(['posting_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:500']]);
        $journals->reverse($journalEntry, $request->user()->id, $data['posting_date'], $data['reason'], 'manual_reversal');

        return back()->with('success', 'Journal reversed.');
    }

    public function reports(Request $request): Response
    {
        $orgId = $request->user()->org_id;
        $filters = $request->only(['account_id', 'from', 'to']);
        $entries = JournalEntry::where('org_id', $orgId)->whereIn('status', ['posted', 'reversed'])->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('posting_date', '>=', $date))->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('posting_date', '<=', $date));
        $trialBalance = JournalLine::query()->select('chart_of_account_id', DB::raw('SUM(debit) AS debit'), DB::raw('SUM(credit) AS credit'))->where('org_id', $orgId)->whereIn('journal_entry_id', (clone $entries)->select('id'))->groupBy('chart_of_account_id')->with('account:id,code,name,account_type')->get();
        $ledger = JournalLine::query()->where('org_id', $orgId)->whereIn('journal_entry_id', (clone $entries)->select('id'))->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('chart_of_account_id', $accountId))->with(['account:id,code,name', 'entry:id,entry_no,posting_date,description,status'])->orderBy('created_at')->get();

        return Inertia::render('Accounting/Reports', ['trialBalance' => $trialBalance, 'ledger' => $ledger, 'accounts' => ChartOfAccount::where('org_id', $orgId)->orderBy('code')->get(['id', 'code', 'name']), 'filters' => $filters]);
    }

    private function audit(Request $request, string $action, string $type, string $id, array $after): void
    {
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'after_json' => $after, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'request_id' => (string) Str::uuid()]);
    }
}
