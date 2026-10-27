<?php

namespace Pos\Services;

use Pos\Support\CanonicalJson;

class DocumentSigner
{
    public function sign(array $body): array
    {
        $path = config('pos.signing_key_path');
        if (! config('pos.instance_id') || ! config('pos.signing_key_id') || ! is_string($path) || ! is_file($path)) {
            throw new \RuntimeException('POS event signing is not configured.');
        }
        $key = openssl_pkey_get_private(file_get_contents($path));
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
            throw new \RuntimeException('A valid RSA signing key of at least 2048 bits is required.');
        }
        $body['instance_id'] = (string) config('pos.instance_id');
        $body['key_id'] = (string) config('pos.signing_key_id');
        unset($body['signature']);
        if (! openssl_sign(CanonicalJson::encode($body), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('POS document signing failed.');
        }

        return $body + ['signature' => base64_encode($signature)];
    }
}
