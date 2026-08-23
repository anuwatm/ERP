<?php

namespace App\Services;

class BahtText
{
    /** @var array<int, string> */
    private const DIGITS = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];

    /** @var array<int, string> */
    private const PLACES = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    public function convert(float|int|string $amount): string
    {
        $normalized = number_format(max(0, (float) $amount), 2, '.', '');
        [$baht, $satang] = explode('.', $normalized);

        $text = $this->integerToThai($baht).'บาท';

        if ((int) $satang === 0) {
            return $text.'ถ้วน';
        }

        return $text.$this->integerToThai($satang).'สตางค์';
    }

    private function integerToThai(string $number): string
    {
        $number = ltrim($number, '0');

        if ($number === '') {
            return self::DIGITS[0];
        }

        $chunks = str_split($number);
        $length = count($chunks);
        $text = '';

        foreach ($chunks as $index => $digitChar) {
            $digit = (int) $digitChar;
            $position = $length - $index - 1;

            if ($position >= 6) {
                $millionPosition = $position % 6;
                $text .= $this->digitText($digit, $millionPosition);

                if ($millionPosition === 0) {
                    $text .= 'ล้าน';
                }

                continue;
            }

            $text .= $this->digitText($digit, $position, $length > 1);
        }

        return $text;
    }

    private function digitText(int $digit, int $position, bool $hasLeadingDigit = false): string
    {
        if ($digit === 0) {
            return '';
        }

        if ($position === 0 && $digit === 1 && $hasLeadingDigit) {
            return 'เอ็ด';
        }

        if ($position === 1 && $digit === 1) {
            return 'สิบ';
        }

        if ($position === 1 && $digit === 2) {
            return 'ยี่สิบ';
        }

        return self::DIGITS[$digit].self::PLACES[$position];
    }
}
