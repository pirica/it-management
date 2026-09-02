<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * ITM_REQUEST_PASSWORD_APPROVAL_SECRET helper (includes/itm_request_password_approval.php).
 */
class RequestPasswordApprovalTest extends TestCase
{
    public function testApprovalSecretReadsFromEnv(): void
    {
        $envKey = 'ITM_REQUEST_PASSWORD_APPROVAL_SECRET';
        $previous = getenv($envKey);
        $testValue = 'phpunit-request-password-approval-secret';

        putenv($envKey . '=' . $testValue);

        try {
            $this->assertSame($testValue, itm_request_password_approval_secret());
        } finally {
            if ($previous === false) {
                putenv($envKey);
            } else {
                putenv($envKey . '=' . $previous);
            }
        }
    }

    public function testApprovalSecretEmptyWhenEnvUnset(): void
    {
        $envKey = 'ITM_REQUEST_PASSWORD_APPROVAL_SECRET';
        $previous = getenv($envKey);

        putenv($envKey);

        try {
            $this->assertSame('', itm_request_password_approval_secret());
        } finally {
            if ($previous === false) {
                putenv($envKey);
            } else {
                putenv($envKey . '=' . $previous);
            }
        }
    }
}
