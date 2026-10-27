<?php

namespace App\Services\Pos;

use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PosReconciliationService
{
    /** Read-only current ledger checks. This does not claim completeness of the remote POS source. */
    public function report(string $orgId, string $instanceId, string $from, string $to, ?array $sourceFilter = null): array
    {
        validator(['from' => $from, 'to' => $to], ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
        abort_if(Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 30, 422, 'Reconciliation range is limited to 31 days.');

        return DB::transaction(function () use ($orgId, $instanceId, $from, $to, $sourceFilter) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('org_id', $orgId)->firstOrFail();
            $sales = DB::table('pos_import_sales as s')->join('invoices as i', 'i.id', '=', 's.invoice_id')->where('s.pos_instance_id', $instanceId)->whereDate('i.issue_date', '>=', $from)->whereDate('i.issue_date', '<=', $to)->when($sourceFilter !== null, fn ($q) => $q->whereIn('s.source_sale_id', $sourceFilter['sales'] ?? []))->select('s.*')->limit(101)->get();
            $refunds = DB::table('pos_import_refunds as r')->join('credit_debit_notes as n', 'n.id', '=', 'r.credit_note_id')->where('r.pos_instance_id', $instanceId)->whereDate('n.issue_date', '>=', $from)->whereDate('n.issue_date', '<=', $to)->when($sourceFilter !== null, fn ($q) => $q->whereIn('r.source_refund_id', $sourceFilter['refunds'] ?? []))->select('r.*')->limit(101)->get();
            $cash = DB::table('pos_import_cash_movements as c')->join('journal_entries as j', 'j.id', '=', 'c.journal_entry_id')->where('c.pos_instance_id', $instanceId)->whereDate('j.posting_date', '>=', $from)->whereDate('j.posting_date', '<=', $to)->when($sourceFilter !== null, fn ($q) => $q->whereIn('c.source_cash_movement_id', $sourceFilter['cash'] ?? []))->select('c.*')->limit(101)->get();
            abort_if($sales->count() > 100 || $refunds->count() > 100 || $cash->count() > 100, 422, 'Reconciliation exceeds 100 records per type; narrow the date range.');
            $issues = [];
            $gross = '0.00';
            $refunded = '0.00';
            $cashIn = '0.00';
            $cashOut = '0.00';
            $inventoryPending = 0;
            $allocationCount = 0;
            $currency = null;
            $rememberCurrency = function (string $value) use (&$currency): void {
                abort_if($currency !== null && $currency !== $value, 422, 'Mixed source currencies require separate reconciliation.');
                $currency = $value;
            };
            $journal = function (string $type, string $source, string $event, string $expected) use ($orgId, &$issues): void {
                $entry = DB::table('journal_entries')->where('org_id', $orgId)->where('source_type', $type)->where('source_id', $source)->where('posting_event', $event)->where('status', 'posted')->first();
                if (! $entry) {
                    $issues[] = ['source_id' => $source, 'code' => 'journal_missing_or_reversed'];

                    return;
                }
                $lines = DB::table('journal_lines')->where('org_id', $orgId)->where('journal_entry_id', $entry->id)->get(['debit', 'credit']);
                $debit = $lines->reduce(fn ($sum, $line) => bcadd($sum, (string) $line->debit, 2), '0.00');
                $credit = $lines->reduce(fn ($sum, $line) => bcadd($sum, (string) $line->credit, 2), '0.00');
                if ($lines->count() < 2 || bccomp($debit, $credit, 2) !== 0 || bccomp($debit, $expected, 2) !== 0) {
                    $issues[] = ['source_id' => $source, 'code' => 'journal_amount_mismatch'];
                }
            };
            foreach ($sales as $sale) {
                if (! $sale->source_snapshot) {
                    $issues[] = ['source_id' => $sale->source_sale_id, 'code' => 'original_snapshot_missing'];

                    continue;
                }
                $p = json_decode($sale->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
                $rememberCurrency($p['currency']);
                $gross = bcadd($gross, $p['total'], 2);
                $invoice = DB::table('invoices')->where('id', $sale->invoice_id)->where('org_id', $orgId)->first();
                $payment = DB::table('payments')->where('id', $sale->payment_id)->where('org_id', $orgId)->first();
                $allRefunds = DB::table('pos_import_refunds')->where('sale_id', $sale->id)->get();
                $allRefunded = $allRefunds->reduce(fn ($sum, $r) => bcadd($sum, json_decode($r->source_snapshot, true)['total'], 2), '0.00');
                $net = bcsub($p['total'], $allRefunded, 2);
                if (! $invoice || bccomp((string) $invoice->total, $net, 2) !== 0 || bccomp((string) $invoice->paid_amount, $net, 2) !== 0 || bccomp((string) $invoice->balance_due, '0', 2) !== 0) {
                    $issues[] = ['source_id' => $sale->source_sale_id, 'code' => 'invoice_net_amount_mismatch'];
                }
                if (! $payment || $payment->entry_type !== 'receipt' || bccomp((string) $payment->amount, $p['total'], 2) !== 0) {
                    $issues[] = ['source_id' => $sale->source_sale_id, 'code' => 'receipt_amount_mismatch'];
                }
                $journal('invoice', $sale->invoice_id, 'issued', $p['total']);
                $journal('payment', $sale->payment_id, 'receipt', $p['total']);
                if ($sale->inventory_status !== 'posted') {
                    $inventoryPending++;
                    $issues[] = ['source_id' => $sale->source_sale_id, 'code' => 'inventory_cogs_pending'];

                    continue;
                }
                $cost = '0.00';
                foreach ($p['lines'] as $line) {
                    if (($line['stock_effect'] ?? null) === 'prepared') {
                        foreach ($line['kitchen_refs'] as $ref) {
                            abort_if(++$allocationCount > 1000, 422, 'Reconciliation exceeds 1000 stock allocations; narrow the date range.');
                            $parent = DB::table('pos_import_kitchen_consumptions')->where('pos_instance_id', $instanceId)->where('source_line_id', $ref['line_id'])->first();
                            $claim = DB::table('pos_import_kitchen_sales')->where('pos_instance_id', $instanceId)->where('source_kitchen_line_id', $ref['line_id'])->where('sale_id', $sale->id)->where('source_sale_line_id', $line['line_id'])->exists();
                            if (! $parent || ! $claim || $parent->product_id !== $line['product_id'] || $parent->purpose !== 'preparation' || $parent->inventory_status !== 'posted' || $parent->base_cost === null || ! hash_equals($parent->payload_hash, $ref['consumption_hash'])) {
                                $issues[] = ['source_id' => $ref['line_id'], 'code' => 'kitchen_source_mismatch'];

                                continue;
                            }
                            $origin = json_decode($parent->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
                            if (! hash_equals($parent->payload_hash, hash('sha256', PosCanonicalJson::encode($origin)))) {
                                $issues[] = ['source_id' => $ref['line_id'], 'code' => 'kitchen_snapshot_mismatch'];

                                continue;
                            }
                            $kitchenCost = '0.00';
                            foreach ($origin['ingredients'] as $ingredient) {
                                foreach ($ingredient['allocations'] as $allocation) {
                                    abort_if(++$allocationCount > 1000, 422, 'Reconciliation exceeds 1000 stock allocations; narrow the date range.');
                                    $key = 'pos-kitchen:'.$instanceId.':'.$ref['line_id'].':'.$ingredient['product_id'].':'.$allocation['grant_id'];
                                    $movement = DB::table('stock_movements')->where('org_id', $orgId)->where('source_key', $key)->first();
                                    $grant = DB::table('pos_stock_entitlements')->where('id', $allocation['grant_id'])->where('org_id', $orgId)->where('pos_instance_id', $instanceId)->first();
                                    if (! $movement || ! $grant || $movement->product_id !== $ingredient['product_id'] || $movement->warehouse_id !== $grant->warehouse_id || $movement->inventory_lot_id !== ($allocation['lot_id'] ?? null) || bccomp((string) $movement->quantity, bcmul($allocation['quantity'], '-1', 4), 4) !== 0) {
                                        $issues[] = ['source_id' => $ref['line_id'], 'code' => 'kitchen_stock_source_mismatch'];
                                    }
                                    if ($movement) {
                                        $kitchenCost = bcsub($kitchenCost, (string) $movement->base_total_cost, 2);
                                    }
                                }
                            }
                            if (bccomp($kitchenCost, (string) $parent->base_cost, 2) !== 0) {
                                $issues[] = ['source_id' => $ref['line_id'], 'code' => 'kitchen_cost_mismatch'];
                            }
                            if (bccomp((string) $parent->base_cost, '0', 2) > 0) {
                                $journal('pos_kitchen_consumption', $parent->id, 'inventory', (string) $parent->base_cost);
                            }
                        }
                    }
                    foreach ($line['allocations'] as $allocation) {
                        abort_if(++$allocationCount > 1000, 422, 'Reconciliation exceeds 1000 stock allocations; narrow the date range.');
                        $key = 'pos:'.$instanceId.':'.$sale->source_sale_id.':'.$line['line_id'].':'.$allocation['grant_id'];
                        $movement = DB::table('stock_movements')->where('org_id', $orgId)->where('source_key', $key)->first();
                        $grant = DB::table('pos_stock_entitlements')->where('id', $allocation['grant_id'])->where('org_id', $orgId)->where('pos_instance_id', $instanceId)->first();
                        if (! $movement || ! $grant || $movement->product_id !== $line['product_id'] || $movement->warehouse_id !== $grant->warehouse_id || $movement->inventory_lot_id !== ($allocation['lot_id'] ?? null) || bccomp((string) $movement->quantity, bcmul($allocation['quantity'], '-1', 4), 4) !== 0) {
                            $issues[] = ['source_id' => $line['line_id'], 'code' => 'stock_source_mismatch'];
                        }
                        if ($movement) {
                            $cost = bcsub($cost, (string) $movement->base_total_cost, 2);
                        }
                    }
                }
                if (bccomp($cost, '0', 2) > 0) {
                    $journal('pos_sale', $sale->id, 'inventory', $cost);
                }
            }
            foreach ($refunds as $refund) {
                $p = json_decode($refund->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
                $rememberCurrency($p['currency']);
                $refunded = bcadd($refunded, $p['total'], 2);
                $note = DB::table('credit_debit_notes')->where('id', $refund->credit_note_id)->where('org_id', $orgId)->first();
                $payment = DB::table('payments')->where('id', $refund->payment_id)->where('org_id', $orgId)->first();
                if (! $note || $note->status !== 'issued' || bccomp((string) $note->total, $p['total'], 2) !== 0 || ! $payment || $payment->entry_type !== 'reversal' || bccomp((string) $payment->amount, $p['total'], 2) !== 0) {
                    $issues[] = ['source_id' => $refund->source_refund_id, 'code' => 'refund_document_mismatch'];
                }
                $journal('credit_debit_note', $refund->credit_note_id, 'issued', $p['total']);
                $journal('payment', $refund->payment_id, 'reversal', $p['total']);
            }
            foreach ($cash as $movement) {
                $p = json_decode($movement->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
                $rememberCurrency($p['currency']);
                if ($p['direction'] === 'in') {
                    $cashIn = bcadd($cashIn, $p['amount'], 2);
                } else {
                    $cashOut = bcadd($cashOut, $p['amount'], 2);
                }
                $journal('pos_cash_movement', $movement->id, 'recorded', $p['amount']);
            }

            $checkpoint = DB::table('pos_source_checkpoints')->where('pos_instance_id', $instanceId)->latest('updated_at')->first();

            return ['instance_id' => $instanceId, 'name' => $instance->name, 'from' => $from, 'to' => $to, 'currency' => $currency, 'source_checkpoint' => $checkpoint?->status ?? 'not_available', 'checkpoint_through' => $checkpoint?->through_sequence, 'checkpoint_checked_at' => $checkpoint?->updated_at, 'recovery_fenced' => $instance->recovery_fenced_at !== null, 'sales_count' => $sales->count(), 'refund_count' => $refunds->count(), 'cash_movement_count' => $cash->count(), 'gross_sales' => $gross, 'refunds' => $refunded, 'cash_in' => $cashIn, 'cash_out' => $cashOut, 'source_cash_flow' => bcsub(bcadd(bcsub($gross, $refunded, 2), $cashIn, 2), $cashOut, 2), 'inventory_pending' => $inventoryPending, 'issues' => $issues];
        });
    }
}
