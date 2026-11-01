<?php

namespace App\Services\Pos;

use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Operational evidence only. Never posts sales, payments, stock or journals. */
class PosDeliveryImportService
{
    public function import(object $instance, array $event): array
    {
        abort_unless(DB::transactionLevel() > 0, 500);
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_delivery_enabled') === true && in_array($instance->business_type, ['retail', 'restaurant'], true), 503, 'Local delivery import disabled.');
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'delivery.updated');
        $money = ['required', 'string', 'regex:/^\d{1,12}\.\d{2}$/D'];
        $p = validator($event['payload'], ['revision_id' => 'required|uuid', 'delivery_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'status' => 'required|in:draft,ready,dispatched,delivered,failed,cancelled,return_review,returned', 'settlement_mode' => 'required|in:prepaid_cash,cod', 'source_sale_id' => 'nullable|uuid', 'currency' => 'required|in:THB', 'quote_total' => $money, 'cash_collected' => $money, 'cash_remitted' => $money, 'cash_refunded' => $money, 'inventory_status' => 'required|string|max:40', 'financial_status' => 'required|in:policy_pending', 'courier_id' => 'nullable|uuid', 'customer_paid_at' => 'nullable|date', 'remittance_shift_id' => 'nullable|uuid', 'refund_shift_id' => 'nullable|uuid', 'fees_total' => ['sometimes', 'string', 'regex:/^\\d{1,12}\\.\\d{2}$/D'], 'membership' => 'sometimes|array', 'membership_released' => 'required_with:membership|boolean', 'promotion_lines' => 'required_with:membership|array|min:1|max:102', 'promotion_lines.*' => 'array:id,kind,product_id,net,tax_rate', 'promotion_lines.*.id' => 'required|string|max:100|distinct', 'promotion_lines.*.kind' => 'required|in:merchandise,delivery,packaging', 'promotion_lines.*.net' => $money, 'promotion_lines.*.tax_rate' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/D'], 'promotion_lines.*.product_id' => 'sometimes|uuid', 'promotion_catalog_version' => 'required_with:membership|string|max:80', 'promotion_cart' => 'required_with:membership|array|min:1|max:100', 'promotion_cart.*' => 'array:product_id,quantity,unit', 'promotion_cart.*.product_id' => 'required|uuid|distinct', 'promotion_cart.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'promotion_cart.*.unit' => 'required|string|max:30', 'snapshot_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok'])->validate();
        abort_unless(array_diff(array_keys($event['payload']), array_keys($p)) === [] && $p['revision_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && $p['occurred_at'] === $event['occurred_at'], 403, 'Delivery scope or schema mismatch.');
        abort_if(Carbon::parse($p['occurred_at'])->gt(now()->addMinutes(5)), 422, 'Future delivery event.');
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('active', true)->whereIn('role', ['cashier', 'manager'])->exists(), 403, 'Registered delivery staff required.');
        abort_unless(bccomp($p['cash_collected'], $p['quote_total'], 2) <= 0 && bccomp($p['cash_remitted'], $p['cash_collected'], 2) <= 0 && bccomp($p['cash_refunded'], $p['cash_collected'], 2) <= 0, 422, 'Invalid custody amounts.');
        $old = DB::table('pos_import_delivery_revisions')->where('pos_instance_id', $instance->id)->where(fn ($q) => $q->where('source_revision_id', $p['revision_id'])->orWhere(fn ($q) => $q->where('source_delivery_id', $p['delivery_id'])->where('version', $p['version'])))->first();
        if ($old) {
            abort_unless($old->source_revision_id === $p['revision_id'] && hash_equals($old->payload_hash, $event['payload_hash']), 409, 'Delivery revision conflict.');
        } else {
            if ($p['version'] > 1 && ! DB::table('pos_import_delivery_revisions')->where('pos_instance_id', $instance->id)->where('source_delivery_id', $p['delivery_id'])->where('version', $p['version'] - 1)->exists()) {
                throw new PosDependencyPending('delivery_revision', [$p['delivery_id']]);
            }
            app(PosMembershipImportService::class)->delivery($instance, $p);
            DB::table('pos_import_delivery_revisions')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance->id, 'source_revision_id' => $p['revision_id'], 'source_delivery_id' => $p['delivery_id'], 'version' => $p['version'], 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['status' => 'accepted', 'reason_code' => null, 'financial_status' => 'policy_pending', 'inventory_status' => $p['inventory_status']];
    }
}
