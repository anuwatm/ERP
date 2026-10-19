<?php

namespace App\Services;

class WebhookDestinationResolver
{
    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! preg_match('/\A[a-z0-9.-]+\z/i', $parts['host'])) {
            throw new \RuntimeException('unsafe_destination');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        $records = filter_var($host, FILTER_VALIDATE_IP) ? [['ip' => $host]] : dns_get_record($host, DNS_A | DNS_AAAA);
        if (! $records || count($records) > 32) {
            throw new \RuntimeException('unsafe_destination');
        }
        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! $ip || ! $this->isPublicIp($ip)) {
                throw new \RuntimeException('unsafe_destination');
            }
            $ips[] = $ip;
        }

        return ['host' => $host, 'ip' => $ips[0], 'url' => 'https://'.$host.($parts['path'] ?? '/')];
    }

    public function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $binary = inet_pton($ip);
        if (strlen($binary) === 16) {
            $canonical = strtolower(inet_ntop($binary));

            return (ord($binary[0]) & 0xE0) === 0x20 && ! str_starts_with($canonical, '2002:')
                && substr($binary, 0, 4) !== hex2bin('20010000') && ! str_starts_with($canonical, '2001:db8:');
        }
        $number = (int) sprintf('%u', ip2long($ip));
        foreach (['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
            '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $mask = (0xFFFFFFFF << (32 - (int) $bits)) & 0xFFFFFFFF;
            if (($number & $mask) === (ip2long($network) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
