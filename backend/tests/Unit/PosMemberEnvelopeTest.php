<?php

namespace Tests\Unit;

use Company\Pos\MemberEnvelope;
use PHPUnit\Framework\TestCase;

class PosMemberEnvelopeTest extends TestCase
{
    private static string $private = '';

    private static string $public = '';

    public static function setUpBeforeClass(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $path = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($path)) {
            $options['config'] = $path;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, self::$private, null, $options);
        self::$public = openssl_pkey_get_details($key)['key'];
    }

    public function test_contact_is_confidential_and_encryption_is_randomized(): void
    {
        $codec = new MemberEnvelope;
        $contact = ['name' => 'Synthetic ชื่อ', 'phone' => '0000000101', 'consent_confirmed' => true];
        $a = $codec->seal(self::$public, $contact, 'synthetic-scope');
        $b = $codec->seal(self::$public, $contact, 'synthetic-scope');
        $this->assertNotSame($a['ciphertext'], $b['ciphertext']);
        $this->assertSame($contact, $codec->open(self::$private, $a, 'synthetic-scope'));
        $this->assertStringNotContainsString($contact['phone'], json_encode($a));
    }

    public function test_ciphertext_cannot_move_between_instance_branch_or_request_scope(): void
    {
        $codec = new MemberEnvelope;
        $sealed = $codec->seal(self::$public, ['name' => 'Synthetic'], 'instance-a|branch-a|request-a');
        $this->expectException(\InvalidArgumentException::class);
        $codec->open(self::$private, $sealed, 'instance-b|branch-a|request-a');
    }

    public function test_authenticated_tag_tampering_fails_closed(): void
    {
        $codec = new MemberEnvelope;
        $sealed = $codec->seal(self::$public, ['name' => 'Synthetic'], 'scope');
        $tag = base64_decode($sealed['tag']);
        $tag[0] = chr(ord($tag[0]) ^ 1);
        $sealed['tag'] = base64_encode($tag);
        $this->expectException(\InvalidArgumentException::class);
        $codec->open(self::$private, $sealed, 'scope');
    }

    public function test_wrong_key_identity_and_malformed_material_are_rejected(): void
    {
        $codec = new MemberEnvelope;
        $sealed = $codec->seal(self::$public, ['name' => 'Synthetic'], 'scope');
        foreach ([['key_id' => str_repeat('0', 64)], ['iv' => 'not-base64'], ['tag' => base64_encode('short')]] as $patch) {
            try {
                $codec->open(self::$private, array_replace($sealed, $patch), 'scope');
                $this->fail('Malformed enrollment accepted.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }
}
