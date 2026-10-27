<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\CreditDebitNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinancialJournalService;
use App\Services\NumberSequenceService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosRefundImportService
{
    /** Caller holds the instance/inbox locks and commits the result with these postings. */
    public function import(object $instance, array $event): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_refunds_enabled') === true, 503, 'Local refunds disabled.');
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Refund import requires an inbox transaction.');
        }
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'sale.refunded');
        $money = ['required', 'string', 'regex:/^\d{1,14}\.\d{2}$/D'];
        $qty = ['required', 'string', 'regex:/^\d{1,10}\.\d{4}$/D'];
        $p = validator($event['payload'], ['refund_id' => 'required|uuid', 'sale_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'shift_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'currency' => 'required|string|size:3', 'reason' => 'required|in:customer_return,sale_error,damaged', 'subtotal' => $money, 'tax_amount' => $money, 'total' => $money, 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok', 'lines' => 'required|array|min:1|max:100', 'lines.*.source_line_id' => 'required|uuid|distinct', 'lines.*.product_id' => 'required|uuid', 'lines.*.quantity' => $qty, 'lines.*.returned_quantity' => $qty, 'lines.*.subtotal' => $money, 'lines.*.tax_amount' => $money, 'lines.*.total' => $money, 'lines.*.return_status' => 'required|in:quarantined,not_returned'])->validate();
        abort_unless($p['refund_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && in_array($p['terminal_id'], json_decode($instance->terminal_ids, true), true) && $p['occurred_at'] === $event['occurred_at'], 403, 'POS refund scope mismatch.');
        User::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->actor_id);
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('role', 'manager')->where('active', true)->exists(), 403, 'POS manager authority unavailable.');
        $occurred = Carbon::parse($p['occurred_at']);
        abort_if($occurred->gt(now()->addMinutes(5)), 422, 'Refund event time is in the future.');
        $existing = DB::table('pos_import_refunds')->where('pos_instance_id', $instance->id)->where('source_refund_id', $p['refund_id'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $event['payload_hash']), 409, 'Refund source conflict.');

            return ['status' => 'accepted', 'reason_code' => null, 'invoice_id' => DB::table('pos_import_sales')->where('id', $existing->sale_id)->value('invoice_id')];
        }
        $sale = DB::table('pos_import_sales')->where('pos_instance_id', $instance->id)->where('source_sale_id', $p['sale_id'])->lockForUpdate()->first();
        if (! $sale) {
            return ['status' => 'dependency_pending', 'reason_code' => 'original_sale_unavailable'];
        }
        abort_unless($sale->source_snapshot, 422, 'Original immutable sale snapshot required.');
        $source = json_decode($sale->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
        // Imported snapshots contain validated fields. The inbox retains the exact signed bytes separately.
        abort_unless($p['branch_id'] === $source['branch_id'] && $p['currency'] === $source['currency'] && $occurred->gte(Carbon::parse($source['occurred_at'])), 422, 'Refund branch, currency or date differs from original sale.');
        $invoice = Invoice::where('org_id', $instance->org_id)->where('id', $sale->invoice_id)->lockForUpdate()->firstOrFail();
        $payment = Payment::where('org_id', $instance->org_id)->where('id', $sale->payment_id)->lockForUpdate()->firstOrFail();
        abort_if($payment->reversals()->exists(), 422, 'Original payment has already been reversed.');
        $prior = DB::table('pos_import_refunds')->where('sale_id', $sale->id)->get()->flatMap(fn ($r) => json_decode($r->source_snapshot, true)['lines']);
        $originalLines = collect($source['lines'])->keyBy('line_id');
        $subtotal = '0.00';
        $tax = '0.00';
        foreach ($p['lines'] as $line) {
            $original = $originalLines->get($line['source_line_id']);
            abort_unless($original && $original['product_id'] === $line['product_id'] && bccomp($line['quantity'], '0', 4) > 0 && bccomp($line['returned_quantity'], $line['quantity'], 4) <= 0 && $line['return_status'] === (bccomp($line['returned_quantity'], '0', 4) > 0 ? 'quarantined' : 'not_returned'), 422, 'Invalid source refund line.');
            $old = $prior->where('source_line_id', $line['source_line_id']);
            abort_unless(isset($original['base_step']), 422, 'Original return precision evidence unavailable.');
            foreach (['quantity', 'returned_quantity'] as $field) {
                $raw = bcmul($line[$field], $original['conversion_factor'] ?? '1.0000', 8);
                $base = bcadd($raw, '0', 4);
                abort_unless(bccomp($raw, $base, 8) === 0 && bccomp(bcmod($base, $original['base_step'], 4), '0', 4) === 0, 422, 'Refund quantity violates original unit precision.');
            }
            $cumulative = bcadd($old->reduce(fn ($sum, $r) => bcadd($sum, $r['quantity'], 4), '0.0000'), $line['quantity'], 4);
            abort_if(bccomp($cumulative, $original['quantity'], 4) > 0, 422, 'Refund exceeds original quantity.');
            foreach (['subtotal', 'tax_amount'] as $field) {
                $oldAmount = $old->reduce(fn ($sum, $r) => bcadd($sum, $r[$field], 2), '0.00');
                $expected = bcsub(bcadd(bcdiv(bcmul($original[$field], $cumulative, 8), $original['quantity'], 8), '0.005', 2), $oldAmount, 2);
                abort_unless(bccomp($expected, $line[$field], 2) === 0, 422, 'Refund differs from original proportional amount.');
            }
            abort_unless(bccomp(bcadd($line['subtotal'], $line['tax_amount'], 2), $line['total'], 2) === 0, 422, 'Refund line total mismatch.');
            $subtotal = bcadd($subtotal, $line['subtotal'], 2);
            $tax = bcadd($tax, $line['tax_amount'], 2);
        }
        $total = bcadd($subtotal, $tax, 2);
        abort_unless(bccomp($subtotal, $p['subtotal'], 2) === 0 && bccomp($tax, $p['tax_amount'], 2) === 0 && bccomp($total, $p['total'], 2) === 0 && bccomp($total, '0', 2) > 0 && bccomp($invoice->total, $total, 2) >= 0 && bccomp($invoice->paid_amount, $total, 2) >= 0 && bccomp($invoice->balance_due, '0', 2) === 0, 422, 'Refund financial state mismatch.');
        $date = $occurred->setTimezone($p['business_timezone'])->toDateString();
        $mapping = PosAccountMapping::forSale($instance->id, $sale->id);
        $note = CreditDebitNote::create(['org_id' => $instance->org_id, 'invoice_id' => $invoice->id, 'note_no' => app(NumberSequenceService::class)->next($instance->org_id, 'credit_note'), 'type' => 'credit', 'status' => 'issued', 'issue_date' => $date, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'currency' => $p['currency'], 'base_currency' => $p['currency'], 'exchange_rate' => 1, 'base_subtotal' => $subtotal, 'base_tax_amount' => $tax, 'base_total' => $total, 'reason' => 'POS source refund '.$p['refund_id'].' '.$p['reason'], 'created_by' => $instance->actor_id]);
        foreach ($p['lines'] as $index => $line) {
            $original = $originalLines->get($line['source_line_id']);
            $note->items()->create(['org_id' => $instance->org_id, 'description' => $original['name'], 'quantity' => $line['quantity'], 'unit_price' => $original['unit_price'], 'tax_rate' => $original['tax_rate'], 'line_total' => $line['subtotal']]);
        }
        // Partial reversals use this dedicated source subledger; the ERP one-reversal constraint remains intact.
        $refundPayment = Payment::create(['org_id' => $instance->org_id, 'invoice_id' => $invoice->id, 'entry_type' => 'reversal', 'amount' => $total, 'currency' => $p['currency'], 'base_currency' => $p['currency'], 'exchange_rate' => 1, 'base_amount' => $total, 'invoice_base_amount' => $total, 'payment_date' => $date, 'payment_method' => 'cash', 'reference_no' => $p['refund_id'], 'idempotency_key' => 'pos-refund:'.$instance->id.':'.$p['refund_id'], 'created_by' => $instance->actor_id]);
        $journals = app(FinancialJournalService::class);
        $journals->postCreditDebitNote($note, $instance->actor_id, $mapping);
        $journals->postPayment($refundPayment, $instance->actor_id, $mapping);
        foreach (['subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'paid_amount' => $total, 'base_subtotal' => $subtotal, 'base_tax_amount' => $tax, 'base_total' => $total, 'base_paid_amount' => $total] as $field => $amount) {
            $invoice->$field = bcsub($invoice->$field, $amount, 2);
        }
        $invoice->save();
        DB::table('pos_import_refunds')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance->id, 'source_refund_id' => $p['refund_id'], 'sale_id' => $sale->id, 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'credit_note_id' => $note->id, 'payment_id' => $refundPayment->id, 'original_payment_id' => $payment->id, 'created_at' => now(), 'updated_at' => now()]);
        AuditLog::create(['org_id' => $instance->org_id, 'actor_user_id' => $instance->actor_id, 'action' => 'pos.refund.import', 'entity_type' => 'credit_debit_note', 'entity_id' => $note->id, 'after_json' => ['instance_id' => $instance->id, 'source_refund_id' => $p['refund_id'], 'source_sale_id' => $p['sale_id'], 'stock_return' => 'quarantine_only'], 'request_id' => (string) Str::uuid()]);

        return ['status' => 'accepted', 'reason_code' => null, 'invoice_id' => $invoice->id];
    }
}
