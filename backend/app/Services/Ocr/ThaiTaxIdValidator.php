<?php

namespace App\Services\Ocr;

class ThaiTaxIdValidator
{
    /**
     * Validate Thai 13-digit Tax Identification Number using Modulo 11 check digit algorithm.
     */
    public static function isValid(?string $taxId): bool
    {
        if ($taxId === null) {
            return false;
        }

        // Remove non-digit characters (hyphens, spaces)
        $clean = preg_replace('/[^\d]/', '', $taxId);

        if (strlen($clean) !== 13) {
            return false;
        }

        // Disallow repetitive dummy sequences like 0000000000000 or 1111111111111
        if (preg_match('/^(\d)\1{12}$/', $clean)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $clean[$i]) * (13 - $i);
        }

        $mod = $sum % 11;
        $checkDigit = (11 - $mod) % 10;

        return ((int) $clean[12]) === $checkDigit;
    }

    /**
     * Clean and format a 13-digit tax ID with standard hyphens: X-XXXX-XXXXX-XX-X
     */
    public static function format(?string $taxId): ?string
    {
        if ($taxId === null) {
            return null;
        }

        $clean = preg_replace('/[^\d]/', '', $taxId);
        if (strlen($clean) !== 13) {
            return $taxId;
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($clean, 0, 1),
            substr($clean, 1, 4),
            substr($clean, 5, 5),
            substr($clean, 10, 2),
            substr($clean, 12, 1)
        );
    }
}
