<?php

namespace App\Services\Pos;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** User-authorized local-only development adapter. Production and remote destinations remain blocked. */
class PosTransport
{
    public function request(string $instanceId, string $operation, array $data = [], ?string $identity = null): array
    {
        if (! app()->environment(['local', 'testing']) || config('pos.local_transport_enabled') !== true) {
            throw new \RuntimeException('POS transport is restricted to explicitly enabled local development.');
        }
        abort_unless(Str::isUuid($instanceId), 422, 'Invalid POS instance.');
        $connection = config('pos.local_connections', [])[$instanceId] ?? null;
        abort_unless(is_array($connection), 422, 'Local POS destination is not provisioned.');
        $parts = parse_url($connection['origin'] ?? '');
        abort_unless(is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, ['127.0.0.1', '[::1]'], true)
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && in_array($parts['path'] ?? '', ['', '/'], true)
            && ($parts['port'] ?? 443) >= 1 && ($parts['port'] ?? 443) <= 65535, 422, 'Only pinned loopback HTTPS origins are allowed.');
        foreach (['ca_bundle', 'certificate', 'private_key'] as $field) {
            abort_unless(isset($connection[$field]) && is_string($connection[$field]) && is_file($connection[$field]), 422, 'Local POS TLS material is not provisioned.');
        }
        if ($identity !== null) {
            abort_unless(preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $identity), 422, 'Invalid POS resource identity.');
        }
        [$method, $path] = match ($operation) {
            'events' => ['GET', '/events'],
            'tax-profile' => ['PUT', '/tax-profile'],
            'checkpoint' => ['GET', '/checkpoint'],
            'checkpoint-ack' => ['PUT', '/checkpoints/'.$identity.'/ack'],
            'recall' => ['PUT', '/recalls/'.$identity],
            'catalog' => ['PUT', '/catalog/'.$identity],
            'grant' => ['PUT', '/grants/'.$identity],
            'freeze-grant' => ['PUT', '/grants/'.$identity.'/freeze'],
            'result' => ['PUT', '/results/'.$identity],
            default => throw new \InvalidArgumentException('Unsupported POS operation.'),
        };
        abort_if(! in_array($operation, ['events', 'checkpoint', 'tax-profile'], true) && $identity === null, 422, 'POS resource identity required.');
        abort_if(strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 2097152, 413, 'POS request exceeds limit.');
        $origin = 'https://'.$parts['host'].':'.($parts['port'] ?? 443);
        $response = Http::withHeaders(['X-POS-Instance' => $instanceId, 'Accept-Encoding' => 'identity'])->acceptJson()->connectTimeout(5)->timeout(30)
            ->withOptions(['verify' => $connection['ca_bundle'], 'cert' => $connection['certificate'], 'ssl_key' => $connection['private_key'], 'allow_redirects' => false, 'proxy' => '', 'stream' => true, 'decode_content' => false, 'read_timeout' => 30,
                'progress' => static function ($total, $received): void {
                    if ($total > 2097152 || $received > 2097152) {
                        throw new \RuntimeException('Local POS transfer exceeds limit.');
                    }
                },
            ])
            ->send($method, $origin.'/connector/v1'.$path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        abort_unless($response->status() >= 200 && $response->status() < 300, 502, 'Local POS transport returned an unsuccessful status.');
        abort_unless(in_array(strtolower($response->header('Content-Encoding') ?? ''), ['', 'identity'], true), 502, 'Compressed local POS responses are not accepted.');
        $stream = $response->toPsrResponse()->getBody();
        $bytes = '';
        try {
            while (! $stream->eof()) {
                $bytes .= $stream->read(8192);
                abort_if(strlen($bytes) > 2097152, 502, 'Local POS response exceeds limit.');
            }
        } finally {
            $stream->close();
        }
        $result = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($result), 502, 'Invalid local POS response.');

        return $result;
    }
}
