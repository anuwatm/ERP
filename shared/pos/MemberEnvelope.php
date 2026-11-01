<?php

namespace Company\Pos;

/** Dedicated enrollment encryption; never reuse the event signing key. */
final class MemberEnvelope
{
    public function seal(string $public, array $contact, string $scope): array
    {
        $key = openssl_pkey_get_public($public);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
            throw new \InvalidArgumentException('Invalid enrollment public key.');
        }
        $key = random_bytes(32);
        $iv = random_bytes(12);
        $cipher = openssl_encrypt(json_encode($contact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $scope, 16);
        if ($cipher === false || ! openssl_public_encrypt($key, $wrapped, $public, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new \RuntimeException('Enrollment encryption failed.');
        }

        return ['key_id' => hash('sha256', $details['key']), 'wrapped_key' => base64_encode($wrapped), 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'ciphertext' => base64_encode($cipher)];
    }

    public function open(string $private, array $envelope, string $scope): array
    {
        $key = openssl_pkey_get_private($private);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048 || ! hash_equals(hash('sha256', $details['key']), (string) ($envelope['key_id'] ?? ''))) {
            throw new \InvalidArgumentException('Enrollment key mismatch.');
        }
        $bytes = [];
        foreach (['wrapped_key', 'iv', 'tag', 'ciphertext'] as $field) {
            $raw = $envelope[$field] ?? null;
            if (! is_string($raw) || strlen($raw) > 8192 || ($bytes[$field] = base64_decode($raw, true)) === false) {
                throw new \InvalidArgumentException('Invalid enrollment envelope.');
            }
        }
        if (strlen($bytes['iv']) !== 12 || strlen($bytes['tag']) !== 16 || ! openssl_private_decrypt($bytes['wrapped_key'], $secret, $key, OPENSSL_PKCS1_OAEP_PADDING) || strlen($secret) !== 32) {
            throw new \InvalidArgumentException('Invalid enrollment encryption material.');
        }
        $clear = openssl_decrypt($bytes['ciphertext'], 'aes-256-gcm', $secret, OPENSSL_RAW_DATA, $bytes['iv'], $bytes['tag'], $scope);
        if ($clear === false || strlen($clear) > 2048) {
            throw new \InvalidArgumentException('Enrollment authentication failed.');
        }

        return json_decode($clear, true, flags: JSON_THROW_ON_ERROR);
    }
}
