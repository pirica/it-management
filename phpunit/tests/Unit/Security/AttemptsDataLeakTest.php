<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for authentication attempt identifier redaction.
 */
class AttemptsDataLeakTest extends TestCase
{
    public function testPasswordLikeIdentifierIsRedactedBeforeStorage()
    {
        if (!function_exists('itm_normalize_login_attempt_identifier')) {
            require_once ROOT_PATH . 'includes/itm_login_attempt_identifier.php';
        }

        $secret = 'P@ssword_Leaked_' . bin2hex(random_bytes(4));
        $stored = itm_normalize_login_attempt_identifier($secret);

        $this->assertNotSame($secret, $stored);
        $this->assertStringStartsWith('[redacted:', (string)$stored);
    }

    public function testMixedCasePasswordWithoutSpecialCharsIsRedacted()
    {
        if (!function_exists('itm_normalize_login_attempt_identifier')) {
            require_once ROOT_PATH . 'includes/itm_login_attempt_identifier.php';
        }

        $secret = 'Password123';
        $stored = itm_normalize_login_attempt_identifier($secret);

        $this->assertNotSame($secret, $stored);
        $this->assertStringStartsWith('[redacted:', (string)$stored);
    }

    public function testSimpleUsernameIsStored()
    {
        if (!function_exists('itm_normalize_login_attempt_identifier')) {
            require_once ROOT_PATH . 'includes/itm_login_attempt_identifier.php';
        }

        $this->assertSame('admin.user', itm_normalize_login_attempt_identifier('admin.user'));
    }

    public function testValidEmailIdentifierIsStored()
    {
        if (!function_exists('itm_normalize_login_attempt_identifier')) {
            require_once ROOT_PATH . 'includes/itm_login_attempt_identifier.php';
        }

        $email = 'user@example.com';
        $this->assertSame($email, itm_normalize_login_attempt_identifier($email));
    }
}
