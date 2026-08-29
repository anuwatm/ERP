<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Reconciliation;
use App\Models\VendorPayment;
use App\Services\FxRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankStatementController extends Controller
{
    private const HEADERS = ['transaction_date', 'amount', 'description', 'reference_no', 'balance_after'];

    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/BankStatements', [
            'accounts' => BankAccount::where('org_id', $orgId)->where('status', 'active')->orderBy('account_name')->get(['id', 'bank_name', 'account_name']),
            'statements' => BankStatement::where('org_id', $orgId)->with('bankAccount:id,bank_name,account_name')->latest('imported_at')->get(),
            'lines' => BankStatementLine::where('org_id', $orgId)->with('reconciliation')->when($request->query('statement_id'), fn ($query, $id) => $query->where('bank_statement_id', $id))->latest('transaction_date')->limit(250)->get(),
            'candidates' => $this->candidates($orgId),
        ]);
    }

    public function import(Request $request, FxRateService $rates): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $request->user()->org_id)->where('status', 'active')],
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);
        $rows = $this->csvRows($request->file('statement')->getRealPath());
        abort_if(count($rows) > 5000, 422, 'Statement import is limited to 5,000 rows.');

        $inserted = DB::transaction(function () use ($request, $validated, $rows, $rates): int {
            $account = BankAccount::where('org_id', $request->user()->org_id)->lockForUpdate()->findOrFail($validated['bank_account_id']);
            $newRows = collect($rows)->reject(fn ($row) => BankStatementLine::where('bank_account_id', $account->id)->where('row_fingerprint', $row['row_fingerprint'])->exists())->values();
            abort_if($newRows->isEmpty(), 422, 'All statement rows were already imported.');
            $snapshot = $rates->snapshot($account->org_id, $account->currency, $newRows->max('transaction_date'), ['opening_balance' => $account->opening_balance, 'closing_balance' => $newRows->last()['balance_after'] ?? 0]);
            $statement = BankStatement::create(['org_id' => $account->org_id, 'bank_account_id' => $account->id, 'statement_date_from' => $newRows->min('transaction_date'), 'statement_date_to' => $newRows->max('transaction_date'), 'closing_balance' => $newRows->last()['balance_after'], 'currency' => $account->currency, 'base_currency' => $snapshot['base_currency'], 'exchange_rate' => $snapshot['exchange_rate'], 'base_opening_balance' => $snapshot['base_opening_balance'], 'base_closing_balance' => $snapshot['base_closing_balance'], 'line_count' => $newRows->count(), 'status' => 'open', 'imported_by' => $request->user()->id, 'imported_at' => now()]);
            foreach ($newRows as $row) {
                $lineSnapshot = $rates->snapshot($account->org_id, $account->currency, $row['transaction_date'], ['amount_signed' => $row['amount_signed'], 'balance_after' => $row['balance_after'] ?? 0]);
                $statement->lines()->create(array_merge($row, ['org_id' => $account->org_id, 'bank_account_id' => $account->id, 'currency' => $account->currency, 'base_currency' => $lineSnapshot['base_currency'], 'exchange_rate' => $lineSnapshot['exchange_rate'], 'base_amount_signed' => $lineSnapshot['base_amount_signed'], 'base_balance_after' => $row['balance_after'] === null ? null : $lineSnapshot['base_balance_after']]));
            }
            AuditLog::create(['org_id' => $account->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'bank_statement.import', 'entity_type' => 'bank_statement', 'entity_id' => $statement->id, 'after_json' => ['bank_account_id' => $account->id, 'imported_rows' => $newRows->count(), 'skipped_duplicates' => count($rows) - $newRows->count()], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'request_id' => (string) Str::uuid()]);

            return $newRows->count();
        });

        return back()->with('success', "Imported {$inserted} statement line(s).");
    }

    public function match(Request $request, BankStatementLine $line): RedirectResponse
    {
        abort_unless($line->org_id === $request->user()->org_id, 404);
        $validated = $request->validate(['reconcilable_type' => ['required', Rule::in(['payment', 'expense', 'vendor_payment', 'bank_transfer_source', 'bank_transfer_destination'])], 'reconcilable_id' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $line, $validated): void {
            $lockedLine = BankStatementLine::where('org_id', $request->user()->org_id)->with('statement')->lockForUpdate()->findOrFail($line->id);
            abort_if($lockedLine->statement->status === 'closed', 422, 'Cannot reconcile a closed statement.');
            abort_if($lockedLine->status === 'reconciled', 422, 'Statement line is already reconciled.');
            $this->assertCandidateMatches($lockedLine, $validated['reconcilable_type'], $validated['reconcilable_id']);
            $reconciliation = Reconciliation::create(['org_id' => $lockedLine->org_id, 'bank_statement_line_id' => $lockedLine->id, 'reconcilable_type' => $validated['reconcilable_type'], 'reconcilable_id' => $validated['reconcilable_id'], 'match_method' => 'manual', 'note' => $validated['note'] ?? null, 'matched_by' => $request->user()->id, 'matched_at' => now()]);
            $lockedLine->update(['status' => 'reconciled']);
            $this->audit($request, 'reconciliation.match', $reconciliation->id, ['statement_line_id' => $lockedLine->id, 'reconcilable_type' => $validated['reconcilable_type'], 'reconcilable_id' => $validated['reconcilable_id']]);
        });

        return back()->with('success', 'Statement line reconciled.');
    }

    public function unmatch(Request $request, BankStatementLine $line): RedirectResponse
    {
        abort_unless($line->org_id === $request->user()->org_id, 404);
        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $line, $validated): void {
            $lockedLine = BankStatementLine::where('org_id', $request->user()->org_id)->with(['statement', 'reconciliation'])->lockForUpdate()->findOrFail($line->id);
            abort_if($lockedLine->statement->status === 'closed', 422, 'Cannot unmatch a closed statement.');
            abort_unless($lockedLine->reconciliation && ! $lockedLine->reconciliation->unmatched_at, 422, 'Statement line is not reconciled.');
            $lockedLine->reconciliation->update(['unmatched_by' => $request->user()->id, 'unmatched_at' => now(), 'unmatch_note' => $validated['note']]);
            $lockedLine->update(['status' => 'unreconciled']);
            $this->audit($request, 'reconciliation.unmatch', $lockedLine->reconciliation->id, ['statement_line_id' => $lockedLine->id, 'note' => $validated['note']]);
        });

        return back()->with('success', 'Statement line unmatched.');
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        abort_unless($handle, 422, 'Cannot read statement file.');
        $headers = fgetcsv($handle);
        abort_unless($headers === self::HEADERS, 422, 'CSV headers must be: '.implode(',', self::HEADERS));
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            abort_unless(count($data) === count(self::HEADERS), 422, 'Invalid CSV row.');
            [$date, $amount, $description, $reference, $balance] = $data;
            abort_unless(strtotime($date) !== false && is_numeric($amount) && ($balance === '' || is_numeric($balance)), 422, 'Invalid statement row value.');
            $normalized = [trim($date), number_format((float) $amount, 2, '.', ''), trim($description), trim($reference), $balance === '' ? null : number_format((float) $balance, 2, '.', '')];
            $rows[] = ['transaction_date' => $normalized[0], 'amount_signed' => $normalized[1], 'description' => $normalized[2] ?: null, 'reference_no' => $normalized[3] ?: null, 'balance_after' => $normalized[4], 'row_fingerprint' => hash('sha256', implode('|', array_map(fn ($value) => $value ?? '', $normalized))), 'status' => 'unreconciled'];
        }
        fclose($handle);
        abort_if(empty($rows), 422, 'Statement has no data rows.');

        return $rows;
    }

    private function candidates(string $orgId): array
    {
        $payments = Payment::where('org_id', $orgId)->whereNotNull('bank_account_id')->get()->map(fn (Payment $payment) => ['id' => $payment->id, 'type' => 'payment', 'bank_account_id' => $payment->bank_account_id, 'amount_signed' => $payment->entry_type === 'receipt' ? $payment->amount : -$payment->amount, 'date' => $payment->payment_date?->toDateString(), 'label' => strtoupper($payment->entry_type).' '.$payment->reference_no]);
        $expenses = Expense::where('org_id', $orgId)->where('status', 'paid')->whereNotNull('bank_account_id')->get()->map(fn (Expense $expense) => ['id' => $expense->id, 'type' => 'expense', 'bank_account_id' => $expense->bank_account_id, 'amount_signed' => -$expense->amount, 'date' => $expense->paid_at?->toDateString(), 'label' => 'EXP '.$expense->expense_no.' '.$expense->title]);
        $vendorPayments = VendorPayment::where('org_id', $orgId)->whereNotNull('bank_account_id')->get()->map(fn (VendorPayment $payment) => ['id' => $payment->id, 'type' => 'vendor_payment', 'bank_account_id' => $payment->bank_account_id, 'amount_signed' => $payment->entry_type === 'payment' ? -$payment->amount : $payment->amount, 'date' => $payment->payment_date?->toDateString(), 'label' => 'AP '.$payment->expense?->expense_no]);
        $transfers = BankTransfer::where('org_id', $orgId)->get()->flatMap(fn (BankTransfer $transfer) => [['id' => $transfer->id, 'type' => 'bank_transfer_source', 'bank_account_id' => $transfer->source_bank_account_id, 'amount_signed' => -(float) $transfer->source_amount, 'date' => $transfer->transfer_date?->toDateString(), 'label' => 'Transfer out '.$transfer->reference_no], ['id' => $transfer->id, 'type' => 'bank_transfer_destination', 'bank_account_id' => $transfer->destination_bank_account_id, 'amount_signed' => (float) $transfer->destination_amount, 'date' => $transfer->transfer_date?->toDateString(), 'label' => 'Transfer in '.$transfer->reference_no]]);

        return $payments->merge($expenses)->merge($vendorPayments)->merge($transfers)->values()->all();
    }

    private function assertCandidateMatches(BankStatementLine $line, string $type, string $id): void
    {
        $candidate = match ($type) {
            'payment' => Payment::where('org_id', $line->org_id)->whereKey($id)->firstOrFail(), 'vendor_payment' => VendorPayment::where('org_id', $line->org_id)->whereKey($id)->firstOrFail(), 'bank_transfer_source', 'bank_transfer_destination' => BankTransfer::where('org_id', $line->org_id)->whereKey($id)->firstOrFail(), default => Expense::where('org_id', $line->org_id)->whereKey($id)->firstOrFail()
        };
        $amount = match ($type) {
            'payment' => $candidate->entry_type === 'receipt' ? (float) $candidate->amount : -(float) $candidate->amount, 'vendor_payment' => $candidate->entry_type === 'payment' ? -(float) $candidate->amount : (float) $candidate->amount, 'bank_transfer_source' => -(float) $candidate->source_amount, 'bank_transfer_destination' => (float) $candidate->destination_amount, default => -(float) $candidate->amount
        };
        $bankAccountId = match ($type) {
            'bank_transfer_source' => $candidate->source_bank_account_id, 'bank_transfer_destination' => $candidate->destination_bank_account_id, default => $candidate->bank_account_id
        };
        abort_unless($bankAccountId === $line->bank_account_id && round($amount, 2) === round((float) $line->amount_signed, 2), 422, 'Candidate must use the same account and amount direction.');
    }

    private function audit(Request $request, string $action, string $id, array $after): void
    {
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => 'reconciliation', 'entity_id' => $id, 'after_json' => $after, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'request_id' => (string) Str::uuid()]);
    }
}
