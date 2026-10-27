<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class RefundService
{
    public function command(array $input): array
    {
        $d = validator($input, ['order_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'request_key' => 'required|string|max:100', 'reason' => 'required|in:customer_return,sale_error,damaged', 'items' => 'required|array|min:1|max:100', 'items.*.line_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'items.*.returned_quantity' => ['sometimes', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D']])->validate();
        foreach ($d['items'] as &$line) {
            $line = ['line_id' => $line['line_id'], 'quantity' => bcadd($line['quantity'], '0', 4), 'returned_quantity' => bcadd($line['returned_quantity'] ?? '0', '0', 4)];
            abort_unless(bccomp($line['quantity'], '0', 4) > 0 && bccomp($line['returned_quantity'], $line['quantity'], 4) <= 0, 422, 'Invalid refund quantity.');
        }
        unset($line);
        usort($d['items'], fn ($a, $b) => strcmp($a['line_id'], $b['line_id']));

        return $d;
    }

    public function refund(Staff $staff, array $command, ManagerApproval $approval): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_refunds_enabled') === true, 503, 'Local refunds disabled.');
        $command = $this->command($command);
        $approval->assertFor($staff, $command);
        $hash = hash('sha256', CanonicalJson::encode($command));

        return DB::transaction(function () use ($staff, $command, $hash, $approval) {
            $lockedStaff = Staff::where('id', $staff->id)->lockForUpdate()->firstOrFail();
            $approval->assertFor($lockedStaff, $command);
            $terminal = DB::table('pos_terminals')->where('id', $command['terminal_id'])->where('branch_id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $existing = DB::table('pos_refunds')->where('branch_id', $staff->branch_id)->where('request_key', $command['request_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Refund identity conflict.');

                return json_decode($existing->snapshot, true);
            }
            $shift = DB::table('pos_shifts')->where('terminal_id', $terminal->id)->where('staff_id', $staff->id)->where('status', 'open')->lockForUpdate()->firstOrFail();
            $order = DB::table('pos_orders')->where('id', $command['order_id'])->where('branch_id', $staff->branch_id)->where('status', 'completed')->lockForUpdate()->firstOrFail();
            $original = json_decode($order->snapshot, true);
            $originalLines = collect($original['lines'])->keyBy('line_id');
            $id = (string) Str::uuid();
            $lines = [];
            $subtotal = '0.00';
            $tax = '0.00';
            foreach ($command['items'] as $requested) {
                $source = $originalLines->get($requested['line_id']);
                abort_unless($source, 422, 'Refund line not in original sale.');
                $step = $source['base_step'] ?? null;
                abort_unless($step, 422, 'Original return precision evidence unavailable.');
                foreach (['quantity', 'returned_quantity'] as $field) {
                    $raw = bcmul($requested[$field], $source['conversion_factor'], 8);
                    $base = bcadd($raw, '0', 4);
                    abort_unless(bccomp($raw, $base, 8) === 0 && bccomp(bcmod($base, $step, 4), '0', 4) === 0, 422, 'Refund quantity violates original unit precision.');
                }
                $previous = DB::table('pos_refund_lines')->where('order_item_id', $requested['line_id'])->get();
                $oldQuantity = $previous->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->quantity, 4), '0.0000');
                $newQuantity = bcadd($oldQuantity, $requested['quantity'], 4);
                abort_if(bccomp($newQuantity, $source['quantity'], 4) > 0, 422, 'Refund exceeds original quantity.');
                $oldSub = $previous->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->subtotal, 2), '0.00');
                $oldTax = $previous->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->tax_amount, 2), '0.00');
                $sub = bcsub(bcadd(bcdiv(bcmul($source['subtotal'], $newQuantity, 8), $source['quantity'], 8), '0.005', 2), $oldSub, 2);
                $lineTax = bcsub(bcadd(bcdiv(bcmul($source['tax_amount'], $newQuantity, 8), $source['quantity'], 8), '0.005', 2), $oldTax, 2);
                $lines[] = ['source_line_id' => $requested['line_id'], 'product_id' => $source['product_id'], 'quantity' => $requested['quantity'], 'returned_quantity' => $requested['returned_quantity'], 'subtotal' => $sub, 'tax_amount' => $lineTax, 'total' => bcadd($sub, $lineTax, 2), 'return_status' => bccomp($requested['returned_quantity'], '0', 4) > 0 ? 'quarantined' : 'not_returned'];
                $subtotal = bcadd($subtotal, $sub, 2);
                $tax = bcadd($tax, $lineTax, 2);
            }
            $total = bcadd($subtotal, $tax, 2);
            abort_unless(bccomp($total, '0', 2) > 0, 422, 'Refund amount must be positive.');
            $available = app(ShiftCashService::class)->balance($shift);
            abort_if(bccomp($available, $total, 2) < 0, 422, 'Insufficient cash in current drawer.');
            $snapshot = ['refund_id' => $id, 'sale_id' => $order->id, 'branch_id' => $staff->branch_id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'currency' => $order->currency, 'reason' => $command['reason'], 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'lines' => $lines, 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => $original['business_timezone']];
            DB::table('pos_refunds')->insert(['id' => $id, 'branch_id' => $staff->branch_id, 'order_id' => $order->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'request_key' => $command['request_key'], 'request_hash' => $hash, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'snapshot' => CanonicalJson::encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                DB::table('pos_refund_lines')->insert(['id' => (string) Str::uuid(), 'refund_id' => $id, 'order_item_id' => $line['source_line_id'], 'quantity' => $line['quantity'], 'returned_quantity' => $line['returned_quantity'], 'subtotal' => $line['subtotal'], 'tax_amount' => $line['tax_amount'], 'total' => $line['total'], 'return_status' => $line['return_status'], 'created_at' => now(), 'updated_at' => now()]);
            }
            app(OutboxService::class)->append('sale.refunded', $id, $snapshot);
            DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => 'sale.local_refund', 'source_id' => $id, 'metadata' => CanonicalJson::encode(['sale_id' => $order->id, 'reason' => $command['reason']]), 'created_at' => now()]);

            return $snapshot;
        });
    }
}
