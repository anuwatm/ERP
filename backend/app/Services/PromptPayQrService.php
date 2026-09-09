<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use InvalidArgumentException;

class PromptPayQrService
{
    public function payload(string $type, string $recipient, int $satang, string $ref1 = '', string $ref2 = ''): string
    {
        if ($satang < 1 || $satang > 999999999999) {
            throw new InvalidArgumentException('Invalid QR amount.');
        }
        if ($type === 'biller') {
            if (! preg_match('/^[0-9]{15}$/D', $recipient) || ! preg_match('/^[A-Z0-9]{1,20}$/D', $ref1) || ! preg_match('/^[A-Z0-9]{0,20}$/D', $ref2)) {
                throw new InvalidArgumentException('Invalid biller or reference.');
            }
            $merchant = $this->tlv('00', 'A000000677010112').$this->tlv('01', $recipient).$this->tlv('02', $ref1);
            if ($ref2 !== '') {
                $merchant .= $this->tlv('03', $ref2);
            }
            $tag = '30';
        } elseif ($type === 'tax_id' && preg_match('/^[0-9]{13}$/D', $recipient)) {
            $merchant = $this->tlv('00', 'A000000677010111').$this->tlv('02', $recipient);
            $tag = '29';
        } else {
            throw new InvalidArgumentException('Invalid PromptPay recipient.');
        }
        $amount = intdiv($satang, 100).'.'.str_pad((string) ($satang % 100), 2, '0', STR_PAD_LEFT);
        $payload = '000201010212'.$this->tlv($tag, $merchant).'5303764'.$this->tlv('54', $amount).'5802TH6304';
        $crc = 0xFFFF;
        foreach (unpack('C*', $payload) as $byte) {
            $crc ^= $byte << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = (($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1) & 0xFFFF;
            }
        }

        return $payload.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function image(string $payload): string
    {
        return (new SvgWriter)->write(new QrCode(data: $payload, size: 300, margin: 16))->getDataUri();
    }

    public static function minor(string $amount): int
    {
        if (! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', $amount, $match)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function tlv(string $tag, string $value): string
    {
        if (strlen($value) > 99) {
            throw new InvalidArgumentException('QR field too long.');
        }

        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }
}
