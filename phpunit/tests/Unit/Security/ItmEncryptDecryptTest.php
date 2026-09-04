<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * Vault encrypt/decrypt contract: v2 AES-256-GCM writes + legacy v1 CBC reads.
 */
class ItmEncryptDecryptTest extends TestCase
{
    private static function legacyV1CbcEncrypt($plain, $key)
    {
        $cipher = 'aes-256-cbc';
        $ivLen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $encrypted = openssl_encrypt((string) $plain, $cipher, $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function testRoundTripPreservesPlaintext(): void
    {
        $key = hash('sha256', 'phpunit-vault-key');
        $plain = 'secret-value-🧩';

        $cipher = itm_encrypt($plain, $key);
        $this->assertNotSame($plain, $cipher);
        $this->assertStringStartsWith('v2:', (string) $cipher);
        $this->assertSame($plain, itm_decrypt($cipher, $key));
    }

    public function testLegacyV1CbcCiphertextStillDecrypts(): void
    {
        $key = hash('sha256', 'phpunit-legacy-key');
        $plain = 'legacy-cbc-row';
        $legacy = self::legacyV1CbcEncrypt($plain, $key);

        $this->assertStringNotContainsString('v2:', $legacy);
        $this->assertSame($plain, itm_decrypt($legacy, $key));
    }

    public function testTamperedV2PayloadReturnsFalse(): void
    {
        $key = hash('sha256', 'phpunit-tamper-key');
        $cipher = (string) itm_encrypt('tamper-me', $key);
        $raw = base64_decode(substr($cipher, 3), true);
        $this->assertNotFalse($raw);
        $raw[18] = $raw[18] === 'a' ? 'b' : 'a';
        $tampered = 'v2:' . base64_encode($raw);

        $this->assertFalse(itm_decrypt($tampered, $key));
    }

    public function testDecryptReturnsFalseForTruncatedPayload(): void
    {
        $key = hash('sha256', 'phpunit-vault-key');

        $this->assertFalse(itm_decrypt('not-valid-base64!!!', $key));
        $this->assertFalse(itm_decrypt(base64_encode('short'), $key));
        $this->assertFalse(itm_decrypt('v2:' . base64_encode('short'), $key));
    }

    public function testWrongKeyDoesNotRestorePlaintext(): void
    {
        $plain = 'vault-entry-secret';
        $oldKey = hash('sha256', 'old-key');
        $wrongKey = hash('sha256', 'wrong-key');
        $cipher = itm_encrypt($plain, $oldKey);

        $decrypted = itm_decrypt($cipher, $wrongKey);
        if ($decrypted === false) {
            $this->assertFalse($decrypted);
            return;
        }

        // Why: OpenSSL may return non-false garbage for a wrong AES key — never assertFalse alone.
        $this->assertNotSame($plain, $decrypted);
    }
}
