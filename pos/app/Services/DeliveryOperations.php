<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Delivery;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class DeliveryOperations
{
    public function quote(Staff $staff, object $branch, array $d): array
    {
        $catalog = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('version', $d['catalog_version'] ?? '')->where('valid_until', '>', now())->first();
        abort_unless($catalog && (! $branch->active_catalog_version || $branch->active_catalog_version === $catalog->version), 422, 'Current catalog required.');
        $items = validator($d, ['items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D']])->validate()['items'];
        $products = collect(json_decode($catalog->items, true))->keyBy('product_id');
        $subtotal = $tax = '0.00';
        $lines = [];
        foreach ($items as $item) {
            $p = $products->get($item['product_id']);
            abort_unless($p && ($p['active'] ?? false) && bccomp($item['quantity'], '0', 4) > 0, 422, 'Product unavailable.');
            if ($branch->business_type === 'restaurant') {
                abort_unless(isset($p['restaurant']) && bccomp(bcmod($item['quantity'], '1', 4), '0', 4) === 0 && bccomp($item['quantity'], '50', 4) <= 0, 422, 'Whole published restaurant portions required.');
            }
            $resolved = app(PharmacyPolicy::class)->resolve($staff, $branch->business_type, $p, $item);
            $net = $this->round(bcmul($resolved['unit_price'], $item['quantity'], 8));
            $vat = $this->round(bcdiv(bcmul($net, $p['tax_rate'], 8), '100', 8));
            $lines[] = ['product_id' => $p['product_id'], 'name' => $p['name'], 'unit' => $p['unit'], 'quantity' => $item['quantity'], 'price' => $resolved['unit_price'], 'base_quantity' => $resolved['base_quantity'], 'tax_rate' => $p['tax_rate'], 'subtotal' => $net, 'tax_amount' => $vat, 'total' => bcadd($net, $vat, 2)];
            $subtotal = bcadd($subtotal, $net, 2);
            $tax = bcadd($tax, $vat, 2);
        }

        return ['catalog_version' => $catalog->version, 'lines' => $lines, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $this->money(bcadd($subtotal, $tax, 2)), 'fees_total' => '0.00', 'financial_status' => 'policy_pending', 'stock_status' => 'not_reserved'];
    }

    public function apply(Staff $staff, object $branch, Delivery $delivery, array $d, array $raw): array
    {
        $s = $delivery->snapshot;
        $action = $d['action'];
        if (in_array($action, ['fees', 'inspect', 'refund', 'refund_prepaid', 'remit', 'retry'], true) || ($action === 'edit' && $delivery->status !== 'draft')) {
            ManagerApproval::verify($staff, (string) ($raw['manager_password'] ?? ''), $d)->assertFor($staff, $d);
        }
        $status = $delivery->status;
        abort_if($action === 'receive' && $status === 'cancelled' && $delivery->settlement_mode === 'cod', 409, 'Cancelled unshipped COD has no physical return.');
        if ($action === 'edit') {
            $contact = validator($raw, ['recipient' => 'required|string|max:120', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:1000'])->validate();
            if (isset($raw['items'])) {
                abort_unless($status === 'draft' && $delivery->settlement_mode === 'cod', 409, 'Only COD draft items can change.');
                $s = $this->quote($staff, $branch, $raw);
            }

            return ['snapshot' => $s, 'contact' => $contact, 'status' => $status];
        }
        if ($action === 'membership') {
            $s = app(DeliveryPromotionService::class)->reprice($staff, $delivery, $s, $raw);
        }
        if ($action === 'fees') {
            foreach (['delivery', 'packaging'] as $kind) {
                $net = $this->money((string) ($raw[$kind.'_fee'] ?? '0'));
                $rate = (string) ($raw[$kind.'_tax_rate'] ?? '0.00');
                abort_unless(in_array($rate, config('pos.delivery_fee_tax_rates'), true), 422, 'Server-approved local tax rate required.');
                $vat = $this->round(bcdiv(bcmul($net, $rate, 8), '100', 8));
                $s['fees'][$kind] = ['net' => $net, 'tax_rate' => $rate, 'tax_amount' => $vat, 'total' => bcadd($net, $vat, 2), 'promotion_eligible' => false];
            }
            $s['fees_total'] = bcadd($s['fees']['delivery']['total'], $s['fees']['packaging']['total'], 2);
            $this->money(bcadd($s['total'], $s['fees_total'], 2));
            $s['fees_policy_status'] = 'review_pending';
            if ($delivery->settlement_mode === 'cod' && isset($s['membership_selection'])) {
                $s = app(DeliveryPromotionService::class)->reprice($staff, $delivery, $s);
            }
        }
        if ($action === 'ready' && $delivery->settlement_mode === 'cod' && empty($s['reserved_at'])) {
            if (isset($s['membership_selection'])) {
                $s = app(DeliveryPromotionService::class)->reprice($staff, $delivery, $s);
                $s['membership'] = app(MembershipService::class)->claim($staff, 'delivery', $delivery->id, $s['membership']);
            }
            // A quote is frozen only against the current, still-valid publication.
            $fresh = $this->quote($staff, $branch, ['catalog_version' => $s['catalog_version'], 'items' => $s['lines']]);
            abort_unless(CanonicalJson::encode($fresh['lines']) === CanonicalJson::encode($s['promotion_original_lines'] ?? $s['lines']), 409, 'Quote changed; edit and review the draft.');
            $terminal = DB::table('pos_terminals')->where('id', $raw['terminal_id'] ?? '')->where('branch_id', $branch->id)->where('active', true)->lockForUpdate()->firstOrFail();
            $s['terminal_id'] = $terminal->id;
            if ($branch->business_type === 'restaurant') {
                $order = app(RestaurantService::class)->createOrder($staff, ['terminal_id' => $terminal->id, 'catalog_version' => $s['catalog_version'], 'request_key' => 'delivery-'.$delivery->id, 'channel' => 'takeaway', 'items' => array_map(fn ($l) => ['product_id' => $l['product_id'], 'quantity' => (int) $l['quantity']], $s['lines'])]);
                $s['kitchen_line_ids'] = $order['line_ids'];
                foreach ($order['line_ids'] as $line) {
                    DB::table('pos_delivery_kitchen_lines')->insert(['delivery_id' => $delivery->id, 'line_id' => $line]);
                }
            } else {
                foreach ($s['lines'] as $line) {
                    app(EntitlementAllocator::class)->take($branch->id, $line['product_id'], $line['base_quantity'], $branch->timezone, null, $delivery->id);
                }
            }
            $s['reserved_at'] = now()->utc()->toIso8601String();
            $s['stock_status'] = 'reserved';
        }
        if ($action === 'dispatch' && $delivery->settlement_mode === 'cod') {
            $this->assertDispatch($branch, $delivery, $s);
            foreach ($s['kitchen_line_ids'] ?? [] as $lineId) {
                if (DB::table('pos_kitchen_lines')->where('id', $lineId)->value('status') === 'ready') {
                    app(RestaurantService::class)->advance($staff, $lineId, 'served');
                }
            }
            $s['stock_status'] = $branch->business_type === 'restaurant' ? 'prepared_held' : 'in_transit_reserved';
        }
        if ($action === 'cancel' && $delivery->settlement_mode === 'cod' && ! empty($s['reserved_at'])) {
            if ($branch->business_type === 'restaurant') {
                foreach ($s['kitchen_line_ids'] as $lineId) {
                    $command = app(RestaurantService::class)->cancelCommand(['line_id' => $lineId, 'reason' => 'customer_cancel', 'physical_waste' => false]);
                    app(RestaurantService::class)->cancel($staff, $command, ManagerApproval::verify($staff, (string) ($raw['manager_password'] ?? ''), $command));
                }
            } else {
                $this->finishReservations($branch, $delivery, false, false);
            }
            $s['stock_status'] = 'released';
        }
        if ($action === 'collect') {
            $expected = $this->due($delivery, $s);
            abort_unless(empty($s['cash_collected']) && bccomp($expected, '0', 2) > 0 && bccomp($this->money((string) ($raw['amount'] ?? '')), $expected, 2) === 0, 409, 'Collect the exact outstanding custody amount once.');
            $s['cash_collected'] = $expected;
            $s['customer_paid_at'] = now()->utc()->toIso8601String();
        }
        if ($action === 'remit') {
            abort_unless(! empty($s['cash_collected']) && empty($s['cash_remitted']) && empty($s['cash_refunded']), 409, 'Cash remittance already recorded or unavailable.');
            $s['remittance_shift_id'] = $this->drawer($staff, $delivery, $raw, $s['cash_collected'], 'in');
            $s['cash_remitted'] = $s['cash_collected'];
        }
        if ($action === 'refund') {
            abort_unless(! empty($s['cash_collected']) && empty($s['cash_refunded']), 409, 'No refundable custody balance.');
            if (! empty($s['cash_remitted'])) {
                $s['refund_shift_id'] = $this->drawer($staff, $delivery, $raw, $s['cash_collected'], 'out');
            }
            $s['cash_refunded'] = $s['cash_collected'];
        }
        if ($action === 'refund_prepaid') {
            abort_unless($delivery->settlement_mode === 'prepaid_cash' && empty($s['refund_id']), 409, 'Original refund already linked.');
            $order = json_decode(DB::table('pos_orders')->where('id', $delivery->sale_id)->value('snapshot'), true);
            $prior = DB::table('pos_refunds')->where('order_id', $delivery->sale_id)->where('branch_id', $branch->id)->get();
            if ($prior->isNotEmpty()) {
                abort_unless($prior->count() === 1 && bccomp((string) $prior->first()->total, (string) $s['total'], 2) === 0, 409, 'Partial original refunds require explicit reconciliation.');
                foreach ($order['lines'] as $line) {
                    $returned = DB::table('pos_refund_lines')->where('refund_id', $prior->first()->id)->where('order_item_id', $line['line_id'])->first();
                    abort_unless($returned && bccomp((string) $returned->returned_quantity, $line['quantity'], 4) === 0, 409, 'Original physical return requires explicit reconciliation.');
                }
                $s['refund_id'] = $prior->first()->id;
                $s['refund_prepaid_at'] = now()->utc()->toIso8601String();

                return ['snapshot' => $s, 'status' => $status];
            }
            $service = app(RefundService::class);
            $command = $service->command(['order_id' => $delivery->sale_id, 'terminal_id' => $raw['terminal_id'] ?? '', 'request_key' => 'delivery-'.$delivery->id, 'reason' => 'customer_return', 'items' => array_map(fn ($l) => ['line_id' => $l['line_id'], 'quantity' => $l['quantity'], 'returned_quantity' => $l['quantity']], $order['lines'])]);
            $refund = $service->refund($staff, $command, ManagerApproval::verify($staff, (string) ($raw['manager_password'] ?? ''), $command));
            $s['refund_id'] = $refund['refund_id'];
        }
        if ($action === 'inspect') {
            $decision = $raw['decision'] ?? '';
            abort_unless(in_array($decision, ['restock', 'destroy'], true) && filter_var($raw['condition_verified'] ?? false, FILTER_VALIDATE_BOOLEAN), 422, 'Manager condition verification required.');
            abort_if($branch->business_type === 'restaurant' && $decision !== 'destroy', 422, 'Prepared food cannot return to raw ingredient stock.');
            if ($delivery->settlement_mode === 'cod') {
                if ($branch->business_type === 'restaurant') {
                    foreach ($s['kitchen_line_ids'] as $lineId) {
                        $command = app(RestaurantService::class)->cancelCommand(['line_id' => $lineId, 'reason' => 'spoiled', 'physical_waste' => true]);
                        app(RestaurantService::class)->cancel($staff, $command, ManagerApproval::verify($staff, (string) $raw['manager_password'], $command));
                    }
                } else {
                    $this->finishReservations($branch, $delivery, $decision === 'destroy', true);
                }
            } else {
                abort_unless(! empty($s['refund_id']), 409, 'Use the original prepaid refund before closing return inspection.');
                if ($branch->business_type === 'retail') {
                    $this->inspectPrepaid($staff, $delivery, $s, $decision, (string) $raw['manager_password']);
                }
            }
            $s['stock_status'] = $decision === 'restock' ? 'returned_inspected' : 'disposed';
            $s['return_decision'] = $decision;
        }
        $next = ['ready' => 'ready', 'retry' => 'ready', 'dispatch' => 'dispatched', 'deliver' => 'delivered', 'fail' => 'failed', 'cancel' => 'cancelled', 'receive' => 'return_review', 'inspect' => 'returned'][$action] ?? $status;
        if ($action === 'retry') {
            abort_unless(empty($s['cash_collected']) && empty($s['receive_at']), 409, 'Received or paid goods cannot be re-dispatched.');
        }
        if ($delivery->settlement_mode === 'cod' && ! empty($s['membership']['claim_id'])) {
            $collected = $s['cash_collected'] ?? '0.00';
            $fullRefund = bccomp($collected, '0', 2) > 0 && bccomp($s['cash_refunded'] ?? '0', $collected, 2) === 0;
            if ($next === 'cancelled' || $fullRefund || ($next === 'returned' && bccomp($collected, '0', 2) === 0)) {
                app(MembershipService::class)->release($branch->id, 'delivery', $delivery->id);
                $s['membership_released'] = true;
            }
        }
        $s[$action.'_at'] = now()->utc()->toIso8601String();

        return ['snapshot' => $s, 'status' => $next];
    }

    public function publish(Staff $staff, Delivery $delivery): void
    {
        $s = $delivery->snapshot;
        $revision = (string) Str::uuid();
        $p = ['revision_id' => $revision, 'delivery_id' => $delivery->id, 'branch_id' => $staff->branch_id, 'staff_id' => $staff->id, 'version' => $delivery->version, 'status' => $delivery->status, 'settlement_mode' => $delivery->settlement_mode, 'source_sale_id' => $delivery->sale_id, 'currency' => DB::table('pos_branches')->where('id', $staff->branch_id)->value('currency'), 'quote_total' => bcadd((string) ($s['total'] ?? '0'), (string) ($s['fees_total'] ?? '0'), 2), 'cash_collected' => $s['cash_collected'] ?? '0.00', 'cash_remitted' => $s['cash_remitted'] ?? '0.00', 'cash_refunded' => $s['cash_refunded'] ?? '0.00', 'inventory_status' => $s['stock_status'] ?? 'original_sale', 'financial_status' => 'policy_pending', 'snapshot_hash' => hash('sha256', CanonicalJson::encode($s)), 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => 'Asia/Bangkok'];
        $p += ['courier_id' => $s['courier_id'] ?? null, 'customer_paid_at' => $s['customer_paid_at'] ?? null, 'remittance_shift_id' => $s['remittance_shift_id'] ?? null, 'refund_shift_id' => $s['refund_shift_id'] ?? null, 'fees_total' => $s['fees_total'] ?? '0.00'];
        if ($delivery->settlement_mode === 'cod' && ! empty($s['membership']) && (empty($s['membership']['promotion_id']) || ! empty($s['membership']['claim_id']))) {
            $p += ['membership' => $s['membership'], 'promotion_lines' => $s['promotion_lines'], 'promotion_catalog_version' => $s['catalog_version'], 'promotion_cart' => array_map(fn ($l) => array_intersect_key($l, array_flip(['product_id', 'quantity', 'unit'])), $s['promotion_original_lines'] ?? $s['lines']), 'membership_released' => $s['membership_released'] ?? false];
        }
        app(OutboxService::class)->append('delivery.updated', $revision, $p);
    }

    private function assertDispatch(object $branch, Delivery $delivery, array $s): void
    {
        abort_unless(! empty($s['reserved_at']), 409, 'Reserve before dispatch.');
        if ($branch->business_type === 'restaurant') {
            foreach ($s['kitchen_line_ids'] as $line) {
                abort_unless(in_array(DB::table('pos_kitchen_lines')->where('id', $line)->value('status'), ['ready', 'served'], true), 409, 'Kitchen portions are not ready.');
            }
        } else {
            foreach (DB::table('pos_delivery_reservations')->where('delivery_id', $delivery->id)->where('status', 'reserved')->get() as $r) {
                $g = DB::table('pos_grants')->where('id', $r->grant_id)->lockForUpdate()->firstOrFail();
                app(LotRecallService::class)->assertAvailable($branch->id, $g->lot_id);
                abort_unless($g->status === 'active' && $g->epoch === $r->epoch && bccomp((string) $g->reserved_quantity, (string) $r->quantity, 4) >= 0 && (! $g->expires_at || $g->expires_at >= now($branch->timezone)->toDateString()), 409, 'Reserved stock unavailable.');
            }
        }
    }

    private function finishReservations(object $branch, Delivery $delivery, bool $consume, bool $inspect): void
    {
        $rows = DB::table('pos_delivery_reservations')->where('delivery_id', $delivery->id)->where('status', 'reserved')->orderBy('grant_id')->lockForUpdate()->get();
        abort_unless($rows->isNotEmpty(), 409, 'No active delivery reservations.');
        foreach ($rows as $r) {
            $g = DB::table('pos_grants')->where('id', $r->grant_id)->where('branch_id', $branch->id)->lockForUpdate()->firstOrFail();
            abort_unless($g->epoch === $r->epoch && bccomp((string) $g->reserved_quantity, (string) $r->quantity, 4) >= 0, 409, 'Reservation evidence conflict.');
            if ($inspect && ! $consume) {
                app(LotRecallService::class)->assertAvailable($branch->id, $g->lot_id);
                abort_if($g->expires_at && $g->expires_at < now($branch->timezone)->toDateString(), 409, 'Expired stock cannot be restocked.');
            }
            DB::table('pos_grants')->where('id', $g->id)->update(['reserved_quantity' => bcsub((string) $g->reserved_quantity, (string) $r->quantity, 4), 'consumed_quantity' => $consume ? bcadd((string) $g->consumed_quantity, (string) $r->quantity, 4) : $g->consumed_quantity, 'updated_at' => now()]);
            DB::table('pos_delivery_reservations')->where('id', $r->id)->update(['status' => $consume ? 'disposed' : 'released', 'updated_at' => now()]);
        }
    }

    private function drawer(Staff $staff, Delivery $delivery, array $raw, string $amount, string $direction): string
    {
        $terminal = DB::table('pos_terminals')->where('id', $raw['terminal_id'] ?? '')->where('branch_id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
        $shift = DB::table('pos_shifts')->where('terminal_id', $terminal->id)->where('staff_id', $staff->id)->where('status', 'open')->lockForUpdate()->firstOrFail();
        abort_if($direction === 'out' && bccomp(app(ShiftCashService::class)->balance($shift), $amount, 2) < 0, 409, 'Insufficient drawer cash.');
        DB::table('pos_delivery_cash')->insert(['id' => (string) Str::uuid(), 'delivery_id' => $delivery->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'direction' => $direction, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()]);

        return $shift->id;
    }

    private function inspectPrepaid(Staff $staff, Delivery $delivery, array $s, string $decision, string $password): void
    {
        foreach ($s['lines'] as $line) {
            foreach ($line['allocations'] as $allocation) {
                $service = app(ReturnInspectionService::class);
                $command = $service->command(['refund_id' => $s['refund_id'], 'source_line_id' => $line['line_id'], 'grant_id' => $allocation['grant_id'], 'request_key' => 'delivery-'.hash('sha256', $delivery->id.$allocation['grant_id'].$line['line_id']), 'base_quantity' => $allocation['quantity'], 'decision' => $decision, 'packaging_intact' => true, 'storage_verified' => true, 'lot_verified' => true, 'reason' => 'delivery_return']);
                $service->inspect($staff, $command, ManagerApproval::verify($staff, $password, $command));
            }
        }
    }

    private function due(Delivery $delivery, array $s): string
    {
        return bcadd($delivery->settlement_mode === 'cod' ? (string) $s['total'] : '0', (string) ($s['fees_total'] ?? '0'), 2);
    }

    private function money(string $value): string
    {
        abort_unless(preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $value), 422, 'Invalid money precision.');

        return bcadd($value, '0', 2);
    }

    private function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }
}
