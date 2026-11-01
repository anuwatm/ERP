<?php

namespace Company\Pos\Tests;

use Company\Pos\PromotionCalculator;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

trait PromotionContract
{
    private function policy(): array
    {
        return ['id' => '11111111-1111-4111-8111-111111111111', 'version' => 1, 'mode' => 'fixed', 'value' => '0.01', 'currency' => 'THB', 'starts_at' => '2026-10-01T00:00:00Z', 'ends_at' => '2026-11-01T00:00:00Z', 'branch_ids' => ['22222222-2222-4222-8222-222222222222'], 'business_types' => ['retail', 'restaurant', 'pharmacy'], 'member_required' => false, 'member_tiers' => [], 'product_ids' => [], 'minimum_spend' => '0.00', 'minimum_eligible_only' => true, 'minimum_components' => ['merchandise'], 'discount_components' => ['merchandise'], 'max_uses' => null, 'per_member_limit' => null, 'combination' => 'exclusive', 'refund_entitlement' => 'full_only'];
    }

    private function context(): array
    {
        return ['branch_id' => '22222222-2222-4222-8222-222222222222', 'business_type' => 'retail', 'currency' => 'THB', 'manual_discount_applied' => false, 'member' => null];
    }

    private function lines(): array
    {
        return array_map(fn ($id) => ['id' => $id, 'kind' => 'merchandise', 'product_id' => '33333333-3333-4333-8333-33333333333'.$id, 'net' => '1.00', 'tax_rate' => '7.00'], ['1', '2', '3']);
    }

    private function quote(array $policy = [], array $context = [], ?array $lines = null): array
    {
        return (new PromotionCalculator)->quote(array_replace($this->policy(), $policy), $lines ?? $this->lines(), array_replace($this->context(), $context), new DateTimeImmutable('2026-10-30T00:00:00Z'));
    }

    public function test_penny_allocation_is_exact_and_independent_of_cart_order(): void
    {
        $quote = $this->quote();
        $this->assertSame(['0.01', '0.00', '0.00'], array_column($quote['lines'], 'discount_amount'));
        $this->assertSame('2.99', $quote['subtotal']);
        $this->assertSame('0.21', $quote['tax_amount']);
        $this->assertSame('3.20', $quote['total']);
        $this->assertSame($quote, $this->quote(lines: array_reverse($this->lines())));
    }

    public function test_delivery_and_packaging_have_independent_minimum_and_discount_rules(): void
    {
        $lines = [$this->lines()[0]];
        $lines[0]['net'] = '20.00';
        $lines[] = ['id' => 'delivery', 'kind' => 'delivery', 'net' => '10.00', 'tax_rate' => '7.00'];
        $lines[] = ['id' => 'packaging', 'kind' => 'packaging', 'net' => '5.00', 'tax_rate' => '0.00'];
        $q = $this->quote(['mode' => 'percent', 'value' => '10.00', 'minimum_spend' => '35.00', 'minimum_components' => ['merchandise', 'delivery', 'packaging'], 'discount_components' => ['merchandise', 'packaging']], lines: $lines);
        $this->assertSame('35.00', $q['minimum_basis']);
        $this->assertSame('25.00', $q['discount_basis']);
        $this->assertSame('2.50', $q['discount_amount']);
        $this->assertSame('1.96', $q['tax_amount']);
        $this->assertSame('34.46', $q['total']);
    }

    public function test_minimum_can_count_all_merchandise_without_discounting_ineligible_products(): void
    {
        $q = $this->quote(['product_ids' => [$this->lines()[0]['product_id']], 'minimum_spend' => '3.00', 'minimum_eligible_only' => false]);
        $this->assertSame('3.00', $q['minimum_basis']);
        $this->assertSame('1.00', $q['discount_basis']);
        $this->expectException(DomainException::class);
        $this->quote(['product_ids' => [$this->lines()[0]['product_id']], 'minimum_spend' => '3.00', 'minimum_eligible_only' => true]);
    }

    public function test_all_business_types_use_the_identical_arithmetic(): void
    {
        foreach (['retail', 'restaurant', 'pharmacy'] as $type) {
            $this->assertSame($this->quote(), $this->quote(context: ['business_type' => $type]));
        }
    }

    public function test_active_member_and_tier_are_required_when_selected(): void
    {
        $member = ['id' => '44444444-4444-4444-8444-444444444444', 'tier' => 'gold', 'status' => 'active', 'valid_until' => '2026-10-31T00:00:00Z'];
        $q = $this->quote(['member_required' => true, 'member_tiers' => ['gold'], 'per_member_limit' => 1], ['member' => $member]);
        $this->assertTrue($q['requires_entitlement']);
        $this->assertSame($member['id'], $q['member_id']);
        foreach (['status' => 'inactive', 'tier' => 'silver', 'valid_until' => '2026-10-30T00:00:00Z'] as $key => $value) {
            try {
                $this->quote(['member_required' => true, 'member_tiers' => ['gold']], ['member' => array_replace($member, [$key => $value])]);
                $this->fail('Ineligible member accepted.');
            } catch (DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_scope_time_minimum_and_stacking_fail_closed(): void
    {
        $cases = [
            [[], ['manual_discount_applied' => true]],
            [[], ['branch_id' => '55555555-5555-4555-8555-555555555555']],
            [[], ['currency' => 'USD']],
            [['business_types' => ['restaurant']], []],
            [['ends_at' => '2026-10-30T00:00:00Z'], []],
            [['starts_at' => '2026-10-31T00:00:00Z'], []],
            [['minimum_spend' => '4.00'], []],
            [['member_required' => true], []],
        ];
        foreach ($cases as [$policy, $context]) {
            try {
                $this->quote($policy, $context);
                $this->fail('Ineligible quote accepted.');
            } catch (DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame('0.01', $this->quote(['starts_at' => '2026-10-30T00:00:00Z'])['discount_amount']);
    }

    public function test_limited_rights_are_flagged_but_quote_does_not_issue_or_consume_them(): void
    {
        $this->assertFalse($this->quote()['requires_entitlement']);
        $this->assertTrue($this->quote(['max_uses' => 1])['requires_entitlement']);
        $this->assertSame('3.00', $this->quote(['value' => '999.00'])['discount_basis']);
        $this->assertSame('0.00', $this->quote(['value' => '999.00'])['total']);
    }

    public function test_invalid_policy_shapes_and_unsupported_rules_are_rejected(): void
    {
        foreach ([['value' => 0.1], ['value' => '-1.00'], ['value' => '1e2'], ['mode' => 'percent', 'value' => '100.01'], ['ends_at' => '2026-02-30T00:00:00Z'], ['combination' => 'stack'], ['refund_entitlement' => 'partial'], ['minimum_components' => []], ['member_tiers' => ['gold']], ['per_member_limit' => 1], ['branch_ids' => []], ['max_uses' => 0], ['extra' => 'unknown']] as $patch) {
            try {
                $this->quote($patch);
                $this->fail('Invalid policy accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_allocation_preserves_exact_sum_and_each_line_bound_for_many_vectors(): void
    {
        $calculator = new PromotionCalculator;
        for ($n = 1; $n <= 100; $n++) {
            $weights = ['a' => bcdiv((string) ($n % 17), '100', 2), 'b' => bcdiv((string) ($n % 31), '100', 2), 'c' => bcdiv((string) ($n % 7), '100', 2)];
            $total = array_reduce($weights, fn ($sum, $w) => bcadd($sum, $w, 2), '0.00');
            $discount = bcdiv($total, '2', 2);
            $allocated = $calculator->allocate($discount, $weights);
            $this->assertSame($discount, array_reduce($allocated, fn ($sum, $w) => bcadd($sum, $w, 2), '0.00'));
            foreach ($weights as $id => $weight) {
                $this->assertLessThanOrEqual(0, bccomp($allocated[$id], $weight, 2));
                $this->assertGreaterThanOrEqual(0, bccomp($allocated[$id], '0', 2));
            }
        }
    }

    public function test_policy_fingerprint_ignores_object_and_scope_order_but_preserves_meaning(): void
    {
        $calculator = new PromotionCalculator;
        $p = $this->policy();
        $p['business_types'] = array_reverse($p['business_types']);
        $this->assertSame($calculator->fingerprint($this->policy()), $calculator->fingerprint(array_reverse($p, true)));
        $p['value'] = '0.02';
        $this->assertNotSame($calculator->fingerprint($this->policy()), $calculator->fingerprint($p));
    }
}
