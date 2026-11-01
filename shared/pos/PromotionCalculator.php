<?php

namespace Company\Pos;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

/** Pure arithmetic shared by POS and ERP. Inputs must come from trusted server records. */
final class PromotionCalculator
{
    public function validatePolicy(array $p): array
    {
        $this->require(! array_diff(array_keys($p), ['id', 'version', 'mode', 'value', 'currency', 'starts_at', 'ends_at', 'branch_ids', 'business_types', 'member_required', 'member_tiers', 'product_ids', 'minimum_spend', 'minimum_eligible_only', 'minimum_components', 'discount_components', 'max_uses', 'per_member_limit', 'combination', 'refund_entitlement']), 'Unknown policy fields.');
        $this->require($this->uuid($p['id'] ?? null) && is_int($p['version'] ?? null) && $p['version'] > 0, 'Invalid policy identity.');
        $this->require(in_array($p['mode'] ?? null, ['fixed', 'percent'], true), 'Invalid discount mode.');
        $p['value'] = $this->money($p['value'] ?? null);
        $this->require(bccomp($p['value'], '0', 2) > 0 && ($p['mode'] !== 'percent' || bccomp($p['value'], '100', 2) <= 0), 'Invalid discount value.');
        $this->require(is_string($p['currency'] ?? null) && preg_match('/^[A-Z]{3}$/D', $p['currency']), 'Invalid currency.');
        $this->require($this->date($p['starts_at'] ?? null) < $this->date($p['ends_at'] ?? null), 'Invalid policy window.');
        $this->require(is_array($p['branch_ids'] ?? null) && count($p['branch_ids']) > 0 && count($p['branch_ids']) <= 100, 'Explicit branches required.');
        foreach ($p['branch_ids'] as $id) {
            $this->require($this->uuid($id), 'Invalid branch identity.');
        }
        $this->require(is_array($p['business_types'] ?? null) && count($p['business_types']) > 0 && ! array_diff($p['business_types'], ['retail', 'restaurant', 'pharmacy']), 'Invalid business types.');
        $this->require(is_bool($p['member_required'] ?? null) && is_bool($p['minimum_eligible_only'] ?? null), 'Explicit member and minimum basis rules required.');
        $this->require(is_array($p['member_tiers'] ?? null) && count($p['member_tiers']) <= 20, 'Invalid member tiers.');
        foreach ($p['member_tiers'] as $tier) {
            $this->require(is_string($tier) && preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $tier), 'Invalid member tier.');
        }
        $this->require(! $p['member_tiers'] || $p['member_required'], 'Tier rules require membership.');
        $this->require(is_array($p['product_ids'] ?? null) && count($p['product_ids']) <= 1000, 'Invalid product scope.');
        foreach ($p['product_ids'] as $id) {
            $this->require($this->uuid($id), 'Invalid product identity.');
        }
        $p['minimum_spend'] = $this->money($p['minimum_spend'] ?? null);
        foreach (['minimum_components', 'discount_components'] as $field) {
            $this->require(is_array($p[$field] ?? null) && count($p[$field]) > 0 && count($p[$field]) <= 3 && ! array_diff($p[$field], ['merchandise', 'delivery', 'packaging']), 'Explicit component rules required.');
        }
        foreach (['max_uses', 'per_member_limit'] as $field) {
            $this->require(array_key_exists($field, $p) && ($p[$field] === null || (is_int($p[$field]) && $p[$field] > 0)), 'Invalid redemption limit.');
        }
        $this->require($p['per_member_limit'] === null || $p['member_required'], 'Per-member limit requires membership.');
        $this->require(($p['combination'] ?? null) === 'exclusive' && ($p['refund_entitlement'] ?? null) === 'full_only', 'Unsupported stacking or refund rule.');
        foreach (['branch_ids', 'business_types', 'member_tiers', 'product_ids', 'minimum_components', 'discount_components'] as $field) {
            $this->require(count(array_unique($p[$field])) === count($p[$field]), 'Duplicate policy scope.');
            sort($p[$field], SORT_STRING);
        }

        return $p;
    }

    public function quote(array $policy, array $lines, array $context, DateTimeImmutable $now): array
    {
        $p = $this->validatePolicy($policy);
        $this->require($this->uuid($context['branch_id'] ?? null) && is_bool($context['manual_discount_applied'] ?? null), 'Invalid authoritative context.');
        $this->eligible(! $context['manual_discount_applied'], 'Promotion cannot stack with manual discount.');
        $this->eligible(in_array($context['branch_id'], $p['branch_ids'], true) && in_array($context['business_type'] ?? null, $p['business_types'], true), 'Promotion outside branch or business scope.');
        $this->eligible(($context['currency'] ?? null) === $p['currency'], 'Promotion currency mismatch.');
        $this->eligible($now >= $this->date($p['starts_at']) && $now < $this->date($p['ends_at']), 'Promotion outside validity window.');
        $member = $context['member'] ?? null;
        if ($member !== null) {
            $this->require(is_array($member) && $this->uuid($member['id'] ?? null), 'Invalid member identity.');
            $this->eligible(($member['status'] ?? null) === 'active' && $now < $this->date($member['valid_until'] ?? null), 'Member evidence is inactive or stale.');
        }
        $this->eligible(! $p['member_required'] || $member !== null, 'Active member required.');
        $this->eligible(! $p['member_tiers'] || in_array($member['tier'] ?? null, $p['member_tiers'], true), 'Member tier ineligible.');
        $this->require(count($lines) > 0 && count($lines) <= 102, 'Invalid quote lines.');
        $weights = $rows = [];
        $spend = $basis = '0.00';
        foreach ($lines as $line) {
            $key = $line['id'] ?? null;
            $kind = $line['kind'] ?? null;
            $this->require(is_string($key) && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $key) && ! isset($rows[$key]), 'Unique stable line identities required.');
            $this->require(in_array($kind, ['merchandise', 'delivery', 'packaging'], true), 'Invalid line component.');
            $this->require($kind !== 'merchandise' || $this->uuid($line['product_id'] ?? null), 'Merchandise product required.');
            $net = $this->money($line['net'] ?? null);
            $rate = $this->money($line['tax_rate'] ?? null);
            $this->require(bccomp($rate, '100', 2) <= 0, 'Invalid tax rate.');
            $productEligible = $kind !== 'merchandise' || ! $p['product_ids'] || in_array($line['product_id'], $p['product_ids'], true);
            if (in_array($kind, $p['minimum_components'], true) && (! $p['minimum_eligible_only'] || $productEligible)) {
                $spend = bcadd($spend, $net, 2);
            }
            $eligible = $productEligible && in_array($kind, $p['discount_components'], true);
            $weights[$key] = $eligible ? $net : '0.00';
            $basis = bcadd($basis, $weights[$key], 2);
            $rows[$key] = ['id' => $key, 'kind' => $kind, 'original_net' => $net, 'tax_rate' => $rate];
        }
        $this->eligible(bccomp($spend, $p['minimum_spend'], 2) >= 0, 'Minimum spend not reached.');
        $this->eligible(bccomp($basis, '0', 2) > 0, 'No eligible promotion basis.');
        $amount = $p['mode'] === 'fixed' ? $p['value'] : $this->round(bcdiv(bcmul($basis, $p['value'], 8), '100', 8));
        $amount = bccomp($amount, $basis, 2) > 0 ? $basis : $amount;
        $this->eligible(bccomp($amount, '0', 2) > 0, 'Discount rounds to zero.');
        $allocated = $this->allocate($amount, $weights);
        ksort($rows, SORT_STRING);
        $subtotal = $tax = '0.00';
        foreach ($rows as $key => &$line) {
            $line['discount_amount'] = $allocated[$key];
            $line['net'] = bcsub($line['original_net'], $allocated[$key], 2);
            $line['tax_amount'] = $this->round(bcdiv(bcmul($line['net'], $line['tax_rate'], 8), '100', 8));
            $line['total'] = bcadd($line['net'], $line['tax_amount'], 2);
            $subtotal = bcadd($subtotal, $line['net'], 2);
            $tax = bcadd($tax, $line['tax_amount'], 2);
        }
        unset($line);

        return ['promotion_id' => $p['id'], 'version' => $p['version'], 'policy_hash' => $this->fingerprint($p), 'member_id' => $member['id'] ?? null, 'minimum_basis' => $spend, 'discount_basis' => $basis, 'discount_amount' => $amount, 'subtotal' => $this->money($subtotal), 'tax_amount' => $this->money($tax), 'total' => $this->money(bcadd($subtotal, $tax, 2)), 'requires_entitlement' => $p['max_uses'] !== null || $p['per_member_limit'] !== null, 'lines' => array_values($rows)];
    }

    public function fingerprint(array $policy): string
    {
        $policy = $this->validatePolicy($policy);
        ksort($policy, SORT_STRING);

        return hash('sha256', json_encode($policy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Largest remainder, integer cents; ties resolve by stable line ID. */
    public function allocate(string $amount, array $weights): array
    {
        $cents = bcmul($this->money($amount), '100', 0);
        $this->require(count($weights) > 0 && count($weights) <= 102, 'Invalid allocation weights.');
        ksort($weights, SORT_STRING);
        $total = '0';
        foreach ($weights as &$weight) {
            $weight = bcmul($this->money($weight), '100', 0);
            $total = bcadd($total, $weight, 0);
        }
        unset($weight);
        $this->require(bccomp($cents, $total, 0) <= 0, 'Discount exceeds allocation basis.');
        $used = '0';
        $result = $remainders = [];
        foreach ($weights as $key => $weight) {
            $product = bcmul($cents, $weight, 0);
            $result[$key] = $total === '0' ? '0' : bcdiv($product, $total, 0);
            $remainders[$key] = $total === '0' ? '0' : bcmod($product, $total, 0);
            $used = bcadd($used, $result[$key], 0);
        }
        $keys = array_keys($weights);
        usort($keys, fn ($a, $b) => bccomp($remainders[$b], $remainders[$a], 0) ?: strcmp((string) $a, (string) $b));
        $left = (int) bcsub($cents, $used, 0);
        for ($i = 0; $i < $left; $i++) {
            $result[$keys[$i]] = bcadd($result[$keys[$i]], '1', 0);
        }

        return array_map(fn ($value) => bcdiv($value, '100', 2), $result);
    }

    private function money(mixed $value): string
    {
        $this->require(is_string($value) && preg_match('/^(0|[1-9]\d{0,9})(\.\d{1,2})?$/D', $value), 'Invalid decimal amount.');

        return bcadd($value, '0', 2);
    }

    private function date(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value), 'UTC date required.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        $this->require($date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value, 'Invalid UTC date.');

        return $date;
    }

    private function uuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value) === 1;
    }

    private function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($message);
        }
    }

    private function eligible(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new DomainException($message);
        }
    }
}
