<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Services\FinancialJournalService;
use App\Services\FxRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BankTransferController extends Controller
{
    public function store(Request $request, FinancialJournalService $journals, FxRateService $rates): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'source_bank_account_id' => ['required', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'destination_bank_account_id' => ['required', 'uuid', 'different:source_bank_account_id', Rule::exists('bank_accounts', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'transfer_date' => ['required', 'date'], 'source_amount' => ['required', 'numeric', 'gt:0'], 'destination_amount' => ['required', 'numeric', 'gt:0'],
            'reference_no' => ['nullable', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'uuid'],
        ]);
        $key = $data['idempotency_key'] ?? (string) Str::uuid();
        $transfer = DB::transaction(function () use ($request, $orgId, $data, $key, $journals, $rates): BankTransfer {
            $existing = BankTransfer::where('org_id', $orgId)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $source = BankAccount::where('org_id', $orgId)->lockForUpdate()->findOrFail($data['source_bank_account_id']);
            $destination = BankAccount::where('org_id', $orgId)->lockForUpdate()->findOrFail($data['destination_bank_account_id']);
            abort_if(! $source->chart_of_account_id || ! $destination->chart_of_account_id, 422, 'Both bank accounts must be mapped to chart of accounts.');
            $sourceSnapshot = $rates->snapshot($orgId, $source->currency, $data['transfer_date'], ['amount' => $data['source_amount']]);
            $destinationSnapshot = $rates->snapshot($orgId, $destination->currency, $data['transfer_date'], ['amount' => $data['destination_amount']]);
            $transfer = BankTransfer::create(['org_id' => $orgId, 'source_bank_account_id' => $source->id, 'destination_bank_account_id' => $destination->id, 'transfer_date' => $data['transfer_date'], 'source_amount' => $data['source_amount'], 'source_currency' => $source->currency, 'source_base_amount' => $sourceSnapshot['base_amount'], 'destination_amount' => $data['destination_amount'], 'destination_currency' => $destination->currency, 'destination_base_amount' => $destinationSnapshot['base_amount'], 'reference_no' => $data['reference_no'] ?? null, 'note' => $data['note'] ?? null, 'idempotency_key' => $key, 'created_by' => $request->user()->id]);
            $source->update(['base_balance' => round((float) $source->base_balance - (float) $transfer->source_base_amount, 2)]);
            $destination->update(['base_balance' => round((float) $destination->base_balance + (float) $transfer->destination_base_amount, 2)]);
            $journals->postBankTransfer($transfer, $request->user()->id);
            AuditLog::create(['org_id' => $orgId, 'actor_user_id' => $request->user()->id, 'action' => 'bank_transfer.create', 'entity_type' => 'bank_transfer', 'entity_id' => $transfer->id, 'after_json' => $transfer->only(['source_bank_account_id', 'destination_bank_account_id', 'source_amount', 'destination_amount']), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

            return $transfer;
        });

        return back()->with('success', "Bank transfer {$transfer->id} posted.");
    }
}
