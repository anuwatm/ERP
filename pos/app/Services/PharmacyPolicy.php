<?php

namespace Pos\Services;

use Pos\Models\Staff;

class PharmacyPolicy
{
    public function resolve(Staff $staff, string $businessType, array $product, array $line): array
    {
        $policy = $product['pharmacy'] ?? null;
        if ($businessType === 'pharmacy') {
            abort_unless(app()->environment(['local', 'testing']) && config('pos.local_pharmacy_enabled') === true, 503, 'Local Pharmacy capability is disabled.');
        }
        $required = $policy['requires_pharmacist'] ?? false;
        abort_if($required && $businessType !== 'pharmacy', 422, 'Pharmacy capability required for this product.');
        abort_if($required && $staff->role !== 'pharmacist', 403, 'Pharmacist review required before dispensing.');
        $unit = $line['unit'] ?? $product['unit'];
        $selected = $unit === $product['unit'] ? ['unit' => $unit, 'factor' => '1.0000', 'price' => $product['price'], 'conversion_version' => 'base'] : collect($policy['units'] ?? [])->firstWhere('unit', $unit);
        abort_unless($selected, 422, 'Published selling unit unavailable.');
        $raw = bcmul($line['quantity'], $selected['factor'], 8);
        $base = bcadd($raw, '0', 4);
        $step = $policy['base_step'] ?? '0.0001';
        abort_unless(bccomp($raw, $base, 8) === 0 && bccomp($base, '0', 4) > 0 && bccomp(bcmod($base, $step, 4), '0', 4) === 0, 422, 'Quantity cannot be represented in the published base unit.');

        return ['unit' => $unit, 'unit_price' => $selected['price'], 'base_quantity' => $base, 'base_step' => $step, 'conversion_factor' => $selected['factor'], 'conversion_version' => $selected['conversion_version'], 'pharmacist_id' => $required ? $staff->id : null];
    }
}
