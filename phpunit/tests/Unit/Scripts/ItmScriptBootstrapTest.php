<?php

declare(strict_types=1);

namespace Tests\Unit\Scripts;

use PHPUnit\Framework\TestCase;

class ItmScriptBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        require_once ROOT_PATH . 'scripts/lib/itm_script_bootstrap.php';
    }

    public function testIsDisposableTestSessionForApitestSlot(): void
    {
        $_SESSION = [
            'employee_id' => 999901,
            'username' => 'apitest-user-999901',
            'company_id' => 1,
        ];
        $this->assertTrue(itm_script_is_disposable_test_session());
    }

    public function testIsDisposableTestSessionForScriptSlugUser(): void
    {
        $_SESSION = [
            'employee_id' => 42,
            'username' => 'script-verify-free-a1b2c3d4',
        ];
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
        $this->assertTrue(itm_script_is_disposable_test_session());
    }

    public function testIsNotDisposableTestSessionForAdmin(): void
    {
        $_SESSION = [
            'employee_id' => 1,
            'username' => 'Admin',
            'company_id' => 1,
        ];
        $this->assertFalse(itm_script_is_disposable_test_session());
    }

    public function testWithTestSessionContextRestoresAdminSession(): void
    {
        $_SESSION = [
            'employee_id' => 1,
            'username' => 'Admin',
            'company_id' => 1,
            'vault_key' => 'keep-me',
        ];

        $seen = null;
        itm_script_with_test_session_context(1, 999901, 'apitest-user-999901', function () use (&$seen) {
            $seen = $_SESSION;
            return true;
        });

        $this->assertSame(999901, $seen['employee_id'] ?? null);
        $this->assertSame('apitest-user-999901', $seen['username'] ?? null);
        $this->assertSame(1, $_SESSION['employee_id'] ?? null);
        $this->assertSame('Admin', $_SESSION['username'] ?? null);
        $this->assertSame('keep-me', $_SESSION['vault_key'] ?? null);
    }

    public function testGetBrowserAuthorizationEmployeeIdUsesBackup(): void
    {
        $GLOBALS['itm_script_browser_session_backup'] = [
            'employee_id' => 42,
            'username' => 'Admin',
            'company_id' => 1,
        ];
        $_SESSION = [
            'employee_id' => 999999,
            'username' => 'script-verify-abc12345',
            'company_id' => 1,
            'itm_script_browser_isolated' => 1,
        ];

        $this->assertSame(42, itm_script_get_browser_authorization_employee_id());

        unset($GLOBALS['itm_script_browser_session_backup']);
    }
}
