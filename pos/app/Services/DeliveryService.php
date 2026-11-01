<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Delivery;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

/** Local workflow and cash custody. COD financial posting remains policy-pending. */
class DeliveryService
{
    public function actor(Staff $staff): object
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_delivery_enabled') === true, 503, 'Local delivery disabled.');
        $current = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
        abort_unless($current->active && $current->branch_id === $staff->branch_id && $current->role === $staff->role && in_array($current->role, ['cashier', 'manager'], true), 403);
        $branch = DB::table('pos_branches')->where('id', $current->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($branch->business_type, ['retail', 'restaurant'], true), 403, 'Pharmacy delivery is not enabled.');

        return $branch;
    }

    public function create(Staff $staff, array $input): Delivery
    {
        $d = validator($input, [
            'request_key' => 'required|uuid', 'settlement_mode' => 'required|in:prepaid_cash,cod',
            'sale_id' => 'nullable|uuid', 'recipient' => 'required|string|max:120',
            'phone' => 'required|string|max:40', 'address' => 'required|string|max:1000',
            'catalog_version' => 'nullable|string|max:80', 'items' => 'sometimes|array|min:1|max:100',
            'delivery_fee' => 'prohibited', 'packaging_fee' => 'prohibited', 'cash_tendered' => 'prohibited',
            'items.*.product_id' => 'required|uuid|distinct',
            'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'],
        ])->validate();
        $d['sale_id'] ??= null;
        $hash = $this->hash($d);

        return DB::transaction(function () use ($staff, $d, $hash) {
            $branch = $this->actor($staff);
            $old = Delivery::where('branch_id', $branch->id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->created_by === $staff->id && hash_equals($old->request_hash, $hash), 409, 'Delivery request conflict.');

                return $old;
            }
            if ($d['settlement_mode'] === 'prepaid_cash') {
                abort_unless($d['sale_id'], 422, 'Completed cash sale required.');
                $sale = $this->paidSale($branch->id, $d['sale_id']);
                abort_if(Delivery::where('sale_id', $sale->id)->exists(), 409, 'Sale already has a delivery.');
                $snapshot = json_decode($sale->snapshot, true, flags: JSON_THROW_ON_ERROR);
                $snapshot['total'] = (string) $sale->total;
                $snapshot['fees_total'] = '0.00';
            } else {
                abort_if($d['sale_id'], 422, 'COD intake cannot reference a paid sale.');
                $snapshot = app(DeliveryOperations::class)->quote($staff, $branch, $d);
            }
            $delivery = new Delivery;
            $delivery->forceFill(['id' => (string) Str::uuid(), 'branch_id' => $branch->id, 'created_by' => $staff->id, 'sale_id' => $d['sale_id'], 'request_key' => $d['request_key'], 'request_hash' => $hash, 'settlement_mode' => $d['settlement_mode'], 'status' => 'draft', 'version' => 1, 'contact' => array_intersect_key($d, array_flip(['recipient', 'phone', 'address'])), 'snapshot' => $snapshot])->save();
            $this->audit($staff, $delivery, 'create');
            app(DeliveryOperations::class)->publish($staff, $delivery);

            return $delivery;
        });
    }

    public function transition(Staff $staff, string $id, array $input): Delivery
    {
        $d = validator($input, ['request_key' => 'required|uuid', 'version' => 'required|integer|min:1', 'action' => 'required|in:ready,dispatch,deliver,fail,cancel,edit,fees,membership,retry,receive,inspect,collect,remit,refund,refund_prepaid', 'reason' => 'required_if:action,fail,cancel|nullable|in:customer_cancel,address_issue,recipient_absent,transport_issue,other', 'courier_id' => 'required_if:action,dispatch|nullable|uuid'])->validate();
        $d['data'] = array_intersect_key($input, array_flip(['recipient', 'phone', 'address', 'catalog_version', 'items', 'delivery_fee', 'delivery_tax_rate', 'packaging_fee', 'packaging_tax_rate', 'amount', 'terminal_id', 'condition_verified', 'decision', 'member_id', 'promotion_id', 'membership_publication_id']));
        $hash = $this->hash($d);

        return DB::transaction(function () use ($staff, $id, $d, $hash, $input) {
            $branch = $this->actor($staff);
            $delivery = Delivery::where('branch_id', $branch->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_delivery_actions')->where('delivery_id', $id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->staff_id === $staff->id && hash_equals($old->request_hash, $hash), 409, 'Delivery action conflict.');

                return $delivery;
            }
            abort_unless($delivery->version === (int) $d['version'], 409, 'Delivery changed. Reload before retrying.');
            $allowed = ['draft' => ['edit', 'fees', 'membership', 'ready', 'cancel'], 'ready' => ['dispatch', 'cancel', 'receive'], 'dispatched' => ['deliver', 'fail'], 'delivered' => ['collect', 'remit', 'receive'], 'failed' => ['retry', 'edit', 'receive'], 'return_review' => ['refund', 'refund_prepaid', 'inspect'], 'returned' => ['refund'], 'cancelled' => ['receive']];
            abort_unless(in_array($d['action'], $allowed[$delivery->status] ?? [], true), 409, 'Invalid delivery transition.');
            $from = $delivery->status;
            if ($d['action'] === 'deliver' && $delivery->settlement_mode === 'prepaid_cash') {
                $this->paidSale($branch->id, $delivery->sale_id);
            }
            if ($d['action'] === 'dispatch') {
                if ($delivery->settlement_mode === 'prepaid_cash') {
                    $this->paidSale($branch->id, $delivery->sale_id);
                }
                Staff::whereKey($d['courier_id'])->where('branch_id', $branch->id)->where('active', true)->lockForUpdate()->firstOrFail();
            }
            $snapshot = $delivery->snapshot;
            if ($d['action'] === 'dispatch') {
                $snapshot['courier_id'] = $d['courier_id'];
            }
            if (! empty($d['reason'])) {
                $snapshot['last_reason'] = $d['reason'];
            }
            $delivery->setAttribute('snapshot', $snapshot);
            $changes = app(DeliveryOperations::class)->apply($staff, $branch, $delivery, $d, $input);
            $delivery->forceFill($changes + ['version' => $delivery->version + 1])->save();
            DB::table('pos_delivery_actions')->insert(['id' => (string) Str::uuid(), 'delivery_id' => $id, 'staff_id' => $staff->id, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'action' => $d['action'], 'from_status' => $from, 'to_status' => $delivery->status, 'result_version' => $delivery->version, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, $delivery, $d['action']);
            app(DeliveryOperations::class)->publish($staff, $delivery);

            return $delivery;
        });
    }

    private function paidSale(string $branch, string $id): object
    {
        $sale = DB::table('pos_orders')->where('branch_id', $branch)->where('id', $id)->where('status', 'completed')->lockForUpdate()->firstOrFail();
        $paid = DB::table('pos_payments')->where('order_id', $id)->where('method', 'cash')->get()->reduce(fn ($sum, $p) => bcadd($sum, (string) $p->amount, 2), '0.00');
        abort_unless(bccomp($paid, (string) $sale->total, 2) === 0 && bccomp($paid, '0', 2) > 0, 409, 'Cash payment evidence required.');
        abort_if(DB::table('pos_refunds')->where('order_id', $id)->exists(), 409, 'Refunded sale cannot be dispatched.');

        return $sale;
    }

    private function hash(array $data): string
    {
        return hash_hmac('sha256', CanonicalJson::encode($data), (string) config('app.key'));
    }

    private function audit(Staff $staff, Delivery $delivery, string $action): void
    {
        DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => 'delivery.'.$action, 'source_id' => $delivery->id, 'metadata' => CanonicalJson::encode(['status' => $delivery->status, 'version' => $delivery->version]), 'created_at' => now()]);
    }
}
