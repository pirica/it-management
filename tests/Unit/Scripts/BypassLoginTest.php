<?php
/**
 * Bypass Login Functional Test
 *
 * Verifies that scripts/bypass_login.php correctly authenticates an admin session.
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

use PHPUnit\Framework\TestCase;

class BypassLoginTest extends TestCase
{
    public function testBypassLoginScript()
    {
        global $conn;

        // Ensure we are in a clean session state for testing if possible
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $originalSession = $_SESSION;
        $_SESSION = [];

        // 1. Verify file exists
        $scriptPath = __DIR__ . '/../../../scripts/bypass_login.php';
        $this->assertFileExists($scriptPath);

        // 2. Execute the script via inclusion
        ob_start();
        require $scriptPath;
        $output = ob_get_clean();

        // 3. Verify Session Variables
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertArrayHasKey('username', $_SESSION);
        $this->assertArrayHasKey('role_name', $_SESSION);
        $this->assertArrayHasKey('company_id', $_SESSION);
        $this->assertArrayHasKey('vault_key', $_SESSION);

        $this->assertEquals('admin', $_SESSION['role_name']);
        $this->assertEquals(64, strlen($_SESSION['vault_key'])); // SHA-256 hash length

        // 4. Verify Database Connectivity (implicitly tested by bypass_login.php fetching user/company)
        $this->assertInstanceOf(mysqli::class, $conn);

        // Restore session
        $_SESSION = $originalSession;
    }
}
