<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Cheque;
use App\Models\PettyCashFund;
use App\Models\PettyCashReimbursement;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\FinancialJournalService;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TreasuryOperationsController extends Controller
{
    public function pettyCash(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/PettyCash', [
            'funds' => PettyCashFund::where('org_id', $orgId)->latest()->get(),
            'requests' => PettyCashRequest::where('org_id', $orgId)->latest()->get(),
            'reimbursements' => PettyCashReimbursement::where('org_id', $orgId)->latest()->get(),
            'accounts' => BankAccount::where('org_id', $orgId)->where('status', 'active')->get(['id', 'bank_name', 'account_name']),
            'users' => User::where('org_id', $orgId)->where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function storeFund(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $data = $request->validate([
            'custodian_user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'imprest_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $fund = PettyCashFund::create(array_merge($data, [
            'org_id' => $request->user()->org_id,
            'fund_no' => $numbers->next($request->user()->org_id, 'petty_cash_fund'),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]));
        $this->audit($request, 'petty_cash_fund.create', 'petty_cash_fund', $fund->id, $fund->only(['fund_no', 'custodian_user_id', 'imprest_amount']));

        return back()->with('success', 'Petty cash fund created.');
    }

    public function storeRequest(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $data = $request->validate([
            'petty_cash_fund_id' => ['required', 'uuid', Rule::exists('petty_cash_funds', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:2000'],
        ]);

        $item = PettyCashRequest::create(array_merge($data, [
            'org_id' => $request->user()->org_id,
            'request_no' => $numbers->next($request->user()->org_id, 'petty_cash_request'),
            'requester_id' => $request->user()->id,
            'status' => 'submitted',
        ]));
        $this->audit($request, 'petty_cash_request.create', 'petty_cash_request', $item->id, $item->only(['request_no', 'amount', 'status']));

        return back()->with('success', 'Petty cash request submitted.');
    }

    public function approveRequest(Request $request, PettyCashRequest $pettyCashRequest): RedirectResponse
    {
        abort_unless($pettyCashRequest->org_id === $request->user()->org_id, 404);
        abort_if($pettyCashRequest->requester_id === $request->user()->id, 422, 'Requester cannot approve own petty cash request.');
        abort_unless($pettyCashRequest->status === 'submitted', 422, 'Only submitted request can be approved.');

        $pettyCashRequest->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $this->audit($request, 'petty_cash_request.approve', 'petty_cash_request', $pettyCashRequest->id, ['status' => 'approved']);

        return back()->with('success', 'Petty cash request approved.');
    }

    public function rejectRequest(Request $request, PettyCashRequest $pettyCashRequest): RedirectResponse
    {
        abort_unless($pettyCashRequest->org_id === $request->user()->org_id, 404);
        abort_if($pettyCashRequest->requester_id === $request->user()->id, 422, 'Requester cannot reject own petty cash request.');
        abort_unless($pettyCashRequest->status === 'submitted', 422, 'Only submitted request can be rejected.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $pettyCashRequest->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);
        $this->audit($request, 'petty_cash_request.reject', 'petty_cash_request', $pettyCashRequest->id, ['status' => 'rejected']);

        return back()->with('success', 'Petty cash request rejected.');
    }

    public function payRequest(Request $request, PettyCashRequest $pettyCashRequest): RedirectResponse
    {
        abort_unless($pettyCashRequest->org_id === $request->user()->org_id, 404);
        abort_unless($pettyCashRequest->status === 'approved', 422, 'Only approved request can be paid.');

        $pettyCashRequest->update(['status' => 'paid', 'paid_by' => $request->user()->id, 'paid_at' => now()]);
        $this->audit($request, 'petty_cash_request.pay', 'petty_cash_request', $pettyCashRequest->id, ['status' => 'paid']);

        return back()->with('success', 'Petty cash request paid.');
    }

    public function reimburse(Request $request, FinancialJournalService $journals): RedirectResponse
    {
        $data = $request->validate([
            'petty_cash_fund_id' => ['required', 'uuid', Rule::exists('petty_cash_funds', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reimbursed_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = DB::transaction(function () use ($data, $request, $journals): PettyCashReimbursement {
            $item = PettyCashReimbursement::create(array_merge($data, ['org_id' => $request->user()->org_id, 'created_by' => $request->user()->id]));
            $journals->postPettyCashReimbursement($item, $request->user()->id);
            $this->audit($request, 'petty_cash_reimbursement.create', 'petty_cash_reimbursement', $item->id, $item->only(['petty_cash_fund_id', 'amount']));

            return $item;
        });

        return back()->with('success', 'Petty cash reimbursed.');
    }

    public function cheques(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/Cheques', [
            'cheques' => Cheque::where('org_id', $orgId)->latest()->get(),
            'accounts' => BankAccount::where('org_id', $orgId)->where('status', 'active')->get(['id', 'bank_name', 'account_name']),
            'statementLines' => BankStatementLine::where('org_id', $orgId)->where('status', 'unreconciled')->latest('transaction_date')->limit(250)->get(['id', 'bank_account_id', 'transaction_date', 'amount_signed', 'reference_no']),
        ]);
    }

    public function storeCheque(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'direction' => ['required', Rule::in(['received', 'issued'])],
            'cheque_no' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'drawer_or_payee' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);

        $cheque = Cheque::create(array_merge($data, ['org_id' => $request->user()->org_id, 'status' => 'registered', 'created_by' => $request->user()->id]));
        $this->audit($request, 'cheque.create', 'cheque', $cheque->id, $cheque->only(['cheque_no', 'direction', 'amount', 'status']));

        return back()->with('success', 'Cheque registered.');
    }

    public function transitionCheque(Request $request, Cheque $cheque): RedirectResponse
    {
        abort_unless($cheque->org_id === $request->user()->org_id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['deposited', 'cleared', 'bounced', 'cancelled'])],
            'bank_statement_line_id' => ['nullable', 'uuid', Rule::exists('bank_statement_lines', 'id')->where('org_id', $request->user()->org_id)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(in_array($cheque->status, ['cleared', 'bounced', 'cancelled'], true), 422, 'Cheque is already final.');
        abort_if($cheque->status === $data['status'], 422, 'Cheque already has this status.');

        if ($data['status'] === 'cleared') {
            abort_unless($data['bank_statement_line_id'] ?? null, 422, 'Cleared cheque requires statement line.');
            $line = BankStatementLine::where('org_id', $cheque->org_id)->findOrFail($data['bank_statement_line_id']);
            abort_unless($line->bank_account_id === $cheque->bank_account_id && round(abs((float) $line->amount_signed), 2) === round((float) $cheque->amount, 2), 422, 'Statement line must match cheque account and amount.');
        }

        if (in_array($data['status'], ['bounced', 'cancelled'], true)) {
            abort_unless(filled($data['reason'] ?? null), 422, 'Reason is required.');
        }

        $payload = ['status' => $data['status'], 'bank_statement_line_id' => $data['bank_statement_line_id'] ?? null, 'status_reason' => $data['reason'] ?? null];
        if ($data['status'] === 'cleared') {
            $payload['cleared_at'] = now();
        }
        if ($data['status'] === 'bounced') {
            $payload['bounced_at'] = now();
        }
        if ($data['status'] === 'cancelled') {
            $payload['cancelled_at'] = now();
        }

        $cheque->update($payload);
        $this->audit($request, 'cheque.transition', 'cheque', $cheque->id, ['status' => $data['status']]);

        return back()->with('success', 'Cheque status updated.');
    }

    private function audit(Request $request, string $action, string $type, string $id, array $after): void
    {
        AuditLog::create([
            'org_id' => $request->user()->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $id,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ]);
    }
}
