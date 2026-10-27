<?php

namespace App\Services\Pos;

use App\Support\PosCanonicalJson;

class PosSignedDocumentVerifier
{
    public function verify(object $instance, array $body, string $domain): void
    {
        abort_unless(($body['domain'] ?? null) === $domain && ($body['instance_id'] ?? null) === $instance->id && is_string($body['key_id'] ?? null) && is_string($body['signature'] ?? null), 403, 'Signed document scope invalid.');
        $key = json_decode($instance->signing_keys, true)[$body['key_id']] ?? null;
        abort_unless($key && ($key['active'] ?? false), 403, 'Signing key unavailable.');
        $public = openssl_pkey_get_public($key['public_key']);
        $details = $public ? openssl_pkey_get_details($public) : false;
        $signature = base64_decode($body['signature'], true);
        unset($body['signature']);
        abort_unless($signature !== false && $details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048 && openssl_verify(PosCanonicalJson::encode($body), $signature, $public, OPENSSL_ALGO_SHA256) === 1, 403, 'Signed document signature invalid.');
    }
}
