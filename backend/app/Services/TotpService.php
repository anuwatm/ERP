<?php

namespace App\Services;

class TotpService
{
    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) $secret .= $alphabet[random_int(0, 31)];
        return $secret;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) return false;
        $time = intdiv(time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) if (hash_equals($this->code($secret, $time + $offset), $code)) return true;
        return false;
    }

    public function uri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private function code(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split(strtoupper(rtrim($value, '='))) as $char) $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        $bytes = ''; foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $bytes .= chr(bindec($chunk));
        return $bytes;
    }
}
