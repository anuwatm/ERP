<?php

namespace App\Services\Pos;

use App\Support\PosCanonicalJson;
use Company\Pos\PromotionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PosMembershipImportService
{
    public function validate(object $instance, array $proof, array $lines, string $occurred, bool $historicalClaim = false): ?array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_membership_enabled') === true, 503, 'Local membership disabled.');
        $p = validator($proof, ['publication_id' => 'required|uuid', 'member_id' => 'present|nullable|uuid', 'promotion_id' => 'present|nullable|uuid', 'claim_id' => 'present|nullable|uuid', 'grant_id' => 'present|nullable|uuid', 'evaluated_at' => 'required|date', 'quote' => 'present|nullable|array'])->validate();
        abort_unless(count($p) === count($proof), 422, 'Unknown membership proof fields.');
        $evaluated = Carbon::parse($p['evaluated_at']);
        abort_unless($evaluated->lte(Carbon::parse($occurred)) && ($historicalClaim || $evaluated->gte(Carbon::parse($occurred)->subMinute())), 422, 'Promotion evaluation time invalid.');
        $row = DB::table('pos_membership_publications')->where('id', $p['publication_id'])->where('org_id', $instance->org_id)->where('pos_instance_id', $instance->id)->firstOrFail();
        $pub = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->payload_hash, hash('sha256', PosCanonicalJson::encode($pub))) && $pub['branch_id'] === $instance->branch_id && Carbon::parse($pub['created_at'])->lte($evaluated) && Carbon::parse($pub['valid_until'])->gt($evaluated), 422, 'Membership publication unavailable at event time.');
        $member = $p['member_id'] ? collect($pub['members'])->firstWhere('member_id', $p['member_id']) : null;
        abort_unless(! $p['member_id'] || ($member && $member['status'] === 'active'), 422, 'Active published member required.');
        if (! $p['promotion_id']) {
            abort_if($p['claim_id'] || $p['grant_id'] || $p['quote'], 422, 'Unexpected promotion evidence.');

            return null;
        }
        $entry = collect($pub['policies'])->first(fn ($e) => $e['policy']['id'] === $p['promotion_id']);
        abort_unless($entry && $p['claim_id'] && $p['quote'], 422, 'Complete promotion evidence required.');
        try {
            $q = (new PromotionCalculator)->quote($entry['policy'], $lines, ['branch_id' => $instance->branch_id, 'business_type' => $instance->business_type, 'currency' => $entry['policy']['currency'], 'manual_discount_applied' => false, 'member' => $member ? ['id' => $member['member_id'], 'tier' => $member['tier'], 'status' => $member['status'], 'valid_until' => Carbon::parse($pub['valid_until'])->utc()->format('Y-m-d\TH:i:s\Z')] : null], $evaluated->toDateTimeImmutable());
        } catch (\InvalidArgumentException|\DomainException $e) {
            abort(422, $e->getMessage());
        }
        abort_unless(PosCanonicalJson::encode($q) === PosCanonicalJson::encode($p['quote']), 422, 'Promotion arithmetic differs from server policy.');
        if ($q['requires_entitlement']) {
            $right = collect($pub['rights'])->firstWhere('id', $p['grant_id']);
            abort_unless($right && $right['promotion_id'] === $q['promotion_id'] && $right['policy_version'] === $q['version'] && $right['member_id'] === $p['member_id'], 403, 'Allocated redemption right required.');
            $allocation = DB::table('pos_promotion_allocations')->where('id', $p['grant_id'])->where('org_id', $instance->org_id)->where('pos_instance_id', $instance->id)->where('member_id', $p['member_id'])->firstOrFail();
            $rule = DB::table('pos_promotion_rules')->where('id', $allocation->rule_id)->where('org_id', $instance->org_id)->firstOrFail();
            abort_unless($rule->promotion_id === $q['promotion_id'] && $rule->version === $q['version'], 403, 'Redemption policy scope mismatch.');
        } else {
            abort_if($p['grant_id'], 422, 'Unexpected redemption right.');
        }

        return $q;
    }

    public function accept(object $instance, string $kind, string $source, array $proof): void
    {
        if (empty($proof['promotion_id'])) {
            return;
        }
        abort_unless(DB::transactionLevel() > 0, 500);
        $hash = hash('sha256', PosCanonicalJson::encode($proof));
        $old = DB::table('pos_import_promotion_claims')->where('pos_instance_id', $instance->id)->where('source_kind', $kind)->where('source_id', $source)->first();
        if ($old) {
            abort_unless($old->id === $proof['claim_id'] && hash_equals($old->proof_hash, $hash), 409, 'Promotion claim source conflict.');

            return;
        }
        if ($proof['grant_id']) {
            DB::table('pos_promotion_allocations')->where('id', $proof['grant_id'])->where('pos_instance_id', $instance->id)->lockForUpdate()->firstOrFail();
            $active = DB::table('pos_import_promotion_claims')->where('active_slot', $proof['grant_id'])->first();
            if ($active) {
                // The source may have fully refunded the old sale before its refund event arrived.
                // Wait for that signed dependency, never create another financial document.
                throw new PosDependencyPending('promotion_release', [$active->id]);
            }
        }
        DB::table('pos_import_promotion_claims')->insert(['id' => $proof['claim_id'], 'pos_instance_id' => $instance->id, 'source_kind' => $kind, 'source_id' => $source, 'grant_id' => $proof['grant_id'], 'active_slot' => $proof['grant_id'], 'proof' => PosCanonicalJson::encode($proof), 'proof_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function delivery(object $instance, array $p): void
    {
        if (! isset($p['membership'])) {
            return;
        }
        abort_unless($p['settlement_mode'] === 'cod', 422, 'Prepaid membership belongs to the original sale.');
        $claim = DB::table('pos_import_promotion_claims')->where('pos_instance_id', $instance->id)->where('source_kind', 'delivery')->where('source_id', $p['delivery_id'])->first();
        $proof = $p['membership'];
        $promoted = ! empty($proof['promotion_id']);
        $catalog = DB::table('pos_catalog_publications')->where('pos_instance_id', $instance->id)->where('version', $p['promotion_catalog_version'])->firstOrFail();
        $publication = json_decode($catalog->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($catalog->payload_hash, hash('sha256', PosCanonicalJson::encode($publication))), 409, 'Delivery catalog evidence conflict.');
        $evaluated = Carbon::parse($proof['evaluated_at'] ?? '');
        abort_unless($evaluated->gte(Carbon::parse($catalog->created_at)) && $evaluated->lte(Carbon::parse($catalog->valid_until)), 422, 'Delivery quote catalog unavailable.');
        $products = collect($publication['items'])->keyBy('product_id');
        $expected = [];
        foreach ($p['promotion_cart'] as $item) {
            $product = $products->get($item['product_id']);
            abort_unless($product && $product['active'] && $product['unit'] === $item['unit'] && bccomp($item['quantity'], '0', 4) > 0, 422, 'Delivery promotion product differs from catalog.');
            $expected[] = ['id' => $item['product_id'], 'kind' => 'merchandise', 'product_id' => $item['product_id'], 'net' => bcadd(bcmul($product['price'], $item['quantity'], 8), '0.005', 2), 'tax_rate' => $product['tax_rate']];
        }
        $merchandise = array_values(array_filter($p['promotion_lines'], fn ($l) => $l['kind'] === 'merchandise'));
        usort($expected, fn ($a, $b) => strcmp($a['id'], $b['id']));
        usort($merchandise, fn ($a, $b) => strcmp($a['id'], $b['id']));
        abort_unless(PosCanonicalJson::encode($expected) === PosCanonicalJson::encode($merchandise), 422, 'Delivery promotion basis differs from server prices.');
        foreach ($p['promotion_lines'] as $line) {
            if ($line['kind'] !== 'merchandise') {
                abort_unless($line['id'] === $line['kind'] && empty($line['product_id']) && in_array($line['tax_rate'], config('pos.membership_fee_tax_rates'), true), 422, 'Delivery fee policy mismatch.');
            }
        }
        $q = $this->validate($instance, $proof, $p['promotion_lines'], $p['occurred_at'], $claim !== null || ! $promoted);
        if (! $promoted) {
            abort_if($p['membership_released'], 422, 'Unexpected membership release.');

            return;
        }
        abort_unless($q && bccomp($q['total'], $p['quote_total'], 2) === 0, 422, 'Delivery promoted total mismatch.');
        $fees = array_reduce($q['lines'], fn ($sum, $l) => $l['kind'] === 'merchandise' ? $sum : bcadd($sum, $l['total'], 2), '0.00');
        abort_unless(bccomp($fees, $p['fees_total'], 2) === 0, 422, 'Delivery promoted fee total mismatch.');
        if (! $claim) {
            abort_unless($p['status'] === 'ready' && ! $p['membership_released'], 422, 'Delivery promotion must first bind at ready.');
        }
        $this->accept($instance, 'delivery', $p['delivery_id'], $proof);
        if ($p['membership_released']) {
            $prior = DB::table('pos_import_delivery_revisions')->where('pos_instance_id', $instance->id)->where('source_delivery_id', $p['delivery_id'])->where('version', $p['version'] - 1)->first();
            $priorStatus = $prior ? json_decode($prior->source_snapshot, true, flags: JSON_THROW_ON_ERROR)['status'] : null;
            $unpaidCancel = $p['status'] === 'cancelled' && bccomp($p['cash_collected'], '0', 2) === 0 && (in_array($priorStatus, ['draft', 'ready'], true) || ($claim?->released_at && $priorStatus === 'cancelled'));
            $manager = DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('active', true)->where('role', 'manager')->exists();
            $paidRefund = bccomp($p['cash_collected'], '0', 2) > 0 && bccomp($p['cash_collected'], $p['quote_total'], 2) === 0 && bccomp($p['cash_refunded'], $p['cash_collected'], 2) === 0;
            $unpaidReturn = $p['status'] === 'returned' && in_array($p['inventory_status'], ['returned_inspected', 'disposed'], true) && bccomp($p['cash_collected'], '0', 2) === 0 && ($priorStatus === 'return_review' || ($claim?->released_at && $priorStatus === 'returned'));
            abort_unless($unpaidCancel || ($manager && ($paidRefund || $unpaidReturn)), 403, 'Delivery coupon release requires cancellation or verified full return/refund.');
            $this->release($instance, 'delivery', $p['delivery_id']);
        } else {
            abort_if($claim && $claim->released_at, 409, 'Released delivery claim cannot reactivate.');
        }
    }

    public function release(object $instance, string $kind, string $source): void
    {
        abort_unless(DB::transactionLevel() > 0, 500);
        DB::table('pos_import_promotion_claims')->where('pos_instance_id', $instance->id)->where('source_kind', $kind)->where('source_id', $source)->whereNull('released_at')->update(['active_slot' => null, 'released_at' => now(), 'updated_at' => now()]);
    }

    public function releaseRefundedSale(object $instance, object $sale): void
    {
        $snapshot = json_decode($sale->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
        if (empty($snapshot['membership']['claim_id'])) {
            return;
        }
        $quantities = [];
        foreach (DB::table('pos_import_refunds')->where('pos_instance_id', $instance->id)->where('sale_id', $sale->id)->get() as $refund) {
            foreach (json_decode($refund->source_snapshot, true)['lines'] as $line) {
                $quantities[$line['source_line_id']] = bcadd($quantities[$line['source_line_id']] ?? '0', $line['quantity'], 4);
            }
        }
        foreach ($snapshot['lines'] as $line) {
            if (bccomp($quantities[$line['line_id']] ?? '0', $line['quantity'], 4) !== 0) {
                return;
            }
        }
        $this->release($instance, 'sale', $snapshot['sale_id']);
    }
}
