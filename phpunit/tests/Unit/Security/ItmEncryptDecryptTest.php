<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * AES-256-CBC vault encrypt/decrypt contract without database fixtures.
 */
class ItmEncryptDecryptTest extends TestCase
{
    public function testRoundTripPreservesPlaintext(): void
    {
        $key = hash('sha256', 'phpunit-vault-key');
        $plain = 'secret-value-🧩';

        $cipher = itm_encrypt($plain, $key);
        $this->assertNotSame($plain, $cipher);
        $this->assertSame($plain, itm_decrypt($cipher, $key));
    }

    public function testDecryptReturnsFalseForTruncatedPayload(): void
    {
        $key = hash('sha256', 'phpunit-vault-key');

        $this->assertFalse(itm_decrypt('not-valid-base64!!!', $key));
        $this->assertFalse(itm_decrypt(base64_encode('short'), $key));
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
