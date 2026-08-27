<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Cheque;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PettyCashFund;
use App\Models\PettyCashReimbursement;
use App\Models\PettyCashRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TreasuryReportController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;
        $receipts = Payment::query()
            ->where('org_id', $orgId)
            ->whereNotNull('bank_account_id')
            ->selectRaw("bank_account_id, SUM(CASE WHEN entry_type = 'receipt' THEN amount ELSE -amount END) AS total")
            ->groupBy('bank_account_id')
            ->pluck('total', 'bank_account_id');
        $expenses = Expense::query()
            ->where('org_id', $orgId)
            ->where('status', 'paid')
            ->whereNotNull('bank_account_id')
            ->selectRaw('bank_account_id, SUM(amount) AS total')
            ->groupBy('bank_account_id')
            ->pluck('total', 'bank_account_id');
        $unreconciled = BankStatementLine::query()
            ->where('org_id', $orgId)
            ->where('status', 'unreconciled')
            ->selectRaw('bank_account_id, COUNT(*) AS count, SUM(amount_signed) AS amount')
            ->groupBy('bank_account_id')
            ->get()
            ->keyBy('bank_account_id');
        $pendingCheques = Cheque::query()
            ->where('org_id', $orgId)
            ->whereIn('status', ['registered', 'deposited'])
            ->whereNotNull('bank_account_id')
            ->selectRaw('bank_account_id, COUNT(*) AS count, SUM(amount) AS amount')
            ->groupBy('bank_account_id')
            ->get()
            ->keyBy('bank_account_id');

        $accounts = BankAccount::where('org_id', $orgId)->orderBy('bank_name')->get()->map(function (BankAccount $account) use ($receipts, $expenses, $unreconciled, $pendingCheques): array {
            $statement = $unreconciled->get($account->id);
            $cheques = $pendingCheques->get($account->id);
            $opening = (float) $account->opening_balance;
            $netReceipts = (float) ($receipts[$account->id] ?? 0);
            $paidExpenses = (float) ($expenses[$account->id] ?? 0);

            return [
                'id' => $account->id,
                'name' => $account->bank_name.' - '.$account->account_name,
                'currency' => $account->currency,
                'opening_balance' => $opening,
                'net_receipts' => $netReceipts,
                'paid_expenses' => $paidExpenses,
                'expected_balance' => $opening + $netReceipts - $paidExpenses,
                'unreconciled_count' => (int) ($statement?->count ?? 0),
                'unreconciled_amount' => (float) ($statement?->amount ?? 0),
                'pending_cheque_count' => (int) ($cheques?->count ?? 0),
                'pending_cheque_amount' => (float) ($cheques?->amount ?? 0),
            ];
        })->values();

        return Inertia::render('Finance/TreasuryReports', [
            'accounts' => $accounts,
            'pettyCash' => [
                'active_funds' => PettyCashFund::where('org_id', $orgId)->where('status', 'active')->count(),
                'imprest_total' => (float) PettyCashFund::where('org_id', $orgId)->where('status', 'active')->sum('imprest_amount'),
                'paid_requests' => (float) PettyCashRequest::where('org_id', $orgId)->where('status', 'paid')->sum('amount'),
                'reimbursements' => (float) PettyCashReimbursement::where('org_id', $orgId)->sum('amount'),
            ],
            'cheques' => [
                'pending_count' => Cheque::where('org_id', $orgId)->whereIn('status', ['registered', 'deposited'])->count(),
                'pending_amount' => (float) Cheque::where('org_id', $orgId)->whereIn('status', ['registered', 'deposited'])->sum('amount'),
            ],
        ]);
    }
}
