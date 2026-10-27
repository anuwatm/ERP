<?php

namespace App\Services\Pos;

use App\Support\PosCanonicalJson;

class PosEventVerifier
{
    public function verify(object $instance, array $event, string $type): array
    {
        abort_unless(in_array($type, ['sale.completed', 'sale.refunded', 'cash.moved', 'kitchen.consumed', 'kitchen.wasted', 'return.inspected', 'tax.document_issued'], true), 422);
        $event = validator($event, ['domain' => 'required|in:company-os.pos-event.v1', 'schema_version' => 'required|integer|in:1', 'instance_id' => 'required|uuid', 'event_id' => 'required|uuid', 'aggregate_id' => 'required|uuid', 'aggregate_version' => 'required|integer|in:1', 'source_sequence' => 'required|integer|min:1', 'event_type' => 'required|in:'.$type, 'occurred_at' => 'required|date', 'key_id' => 'required|string|max:100', 'payload_hash' => 'required|string|size:64', 'signature' => 'required|string|max:2000', 'payload' => 'required|array'])->validate();
        abort_unless($event['instance_id'] === $instance->id, 403, 'POS identity mismatch.');
        $keys = json_decode($instance->signing_keys, true, flags: JSON_THROW_ON_ERROR);
        $key = $keys[$event['key_id']] ?? null;
        abort_unless($key && ($key['active'] ?? false) && isset($key['public_key']), 403, 'POS signing key unavailable.');
        $unsigned = $event;
        unset($unsigned['signature']);
        $public = openssl_pkey_get_public($key['public_key']);
        $details = $public ? openssl_pkey_get_details($public) : false;
        abort_unless($details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048, 403, 'POS verification key invalid.');
        $signature = base64_decode($event['signature'], true);
        abort_unless($signature !== false && openssl_verify(PosCanonicalJson::encode($unsigned), $signature, $public, OPENSSL_ALGO_SHA256) === 1, 403, 'POS signature invalid.');
        abort_unless(hash_equals($event['payload_hash'], hash('sha256', PosCanonicalJson::encode($event['payload']))), 409, 'POS payload hash mismatch.');

        return $event;
    }
}
