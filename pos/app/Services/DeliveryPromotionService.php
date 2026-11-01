<?php

namespace Pos\Services;

use Pos\Models\Delivery;
use Pos\Models\Staff;

class DeliveryPromotionService
{
    public function reprice(Staff $staff, Delivery $delivery, array $s, ?array $selection = null): array
    {
        abort_unless($delivery->settlement_mode === 'cod', 422, 'Prepaid promotion belongs to the original sale.');
        if ($selection !== null) {
            app(MembershipService::class)->guard();
            $s['membership_selection'] = validator($selection, ['member_id' => 'nullable|uuid', 'promotion_id' => 'nullable|uuid', 'membership_publication_id' => 'nullable|uuid'])->validate();
        }
        $selection = $s['membership_selection'] ?? [];
        $s['promotion_original_lines'] ??= $s['lines'];
        $s['lines'] = $s['promotion_original_lines'];
        $basis = [];
        foreach ($s['lines'] as $line) {
            $basis[] = ['id' => $line['product_id'], 'kind' => 'merchandise', 'product_id' => $line['product_id'], 'net' => $line['subtotal'], 'tax_rate' => $line['tax_rate']];
        }
        foreach (array_keys($s['fees'] ?? []) as $kind) {
            $fee = &$s['fees'][$kind];
            $fee['original_net'] ??= $fee['net'];
            $fee['net'] = $fee['original_net'];
            $fee['tax_amount'] = bcadd(bcdiv(bcmul($fee['net'], $fee['tax_rate'], 8), '100', 8), '0.005', 2);
            $fee['total'] = bcadd($fee['net'], $fee['tax_amount'], 2);
            $fee['promotion_eligible'] = false;
            $basis[] = ['id' => $kind, 'kind' => $kind, 'net' => $fee['net'], 'tax_rate' => $fee['tax_rate']];
            unset($fee);
        }
        $proof = app(MembershipService::class)->quote($staff, $selection, $basis);
        $s['membership'] = $proof;
        $s['promotion_lines'] = $basis;
        $parts = array_column($proof['quote']['lines'] ?? [], null, 'id');
        $s['subtotal'] = $s['tax_amount'] = $s['total'] = $s['fees_total'] = '0.00';
        foreach ($s['lines'] as &$line) {
            $part = $parts[$line['product_id']] ?? null;
            if ($part) {
                $line['discount_amount'] = $part['discount_amount'];
                $line['subtotal'] = $part['net'];
                $line['tax_amount'] = $part['tax_amount'];
                $line['total'] = $part['total'];
            }
            $s['subtotal'] = bcadd($s['subtotal'], $line['subtotal'], 2);
            $s['tax_amount'] = bcadd($s['tax_amount'], $line['tax_amount'], 2);
            $s['total'] = bcadd($s['total'], $line['total'], 2);
        }
        unset($line);
        foreach (array_keys($s['fees'] ?? []) as $kind) {
            $part = $parts[$kind] ?? null;
            if ($part) {
                $s['fees'][$kind]['net'] = $part['net'];
                $s['fees'][$kind]['tax_amount'] = $part['tax_amount'];
                $s['fees'][$kind]['total'] = $part['total'];
                $s['fees'][$kind]['promotion_eligible'] = bccomp($part['discount_amount'], '0', 2) > 0;
            }
            $s['fees_total'] = bcadd($s['fees_total'], $s['fees'][$kind]['total'], 2);
        }

        return $s;
    }
}
