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

    public function testSyncCsrfToBrowserSessionBackupUpdatesGlobalBackup(): void
    {
        $GLOBALS['itm_script_browser_session_backup'] = [
            'employee_id' => 1,
            'username' => 'Admin',
            'company_id' => 1,
        ];

        itm_script_sync_csrf_to_browser_session_backup('backup-sync-token');

        $this->assertSame('backup-sync-token', $GLOBALS['itm_script_browser_session_backup']['csrf_token'] ?? null);

        unset($GLOBALS['itm_script_browser_session_backup']);
    }

    public function testFinishBrowserIsolatedSessionMergesCsrfTokenIntoRestoredSession(): void
    {
        $backup = [
            'employee_id' => 1,
            'username' => 'Admin',
            'company_id' => 1,
            'vault_key' => 'keep-me',
        ];
        $isolatedCsrf = 'isolated-csrf-' . bin2hex(random_bytes(8));

        $GLOBALS['itm_script_browser_session_backup'] = $backup;
        $GLOBALS['itm_script_browser_isolated_employee_id'] = 0;
        $GLOBALS['itm_script_browser_isolated_conn'] = null;
        $_SESSION = [
            'employee_id' => 999901,
            'username' => 'script-phpunit-abc12345',
            'company_id' => 1,
            'itm_script_browser_isolated' => 1,
            'csrf_token' => $isolatedCsrf,
        ];

        itm_script_finish_browser_isolated_session();

        $this->assertSame(1, $_SESSION['employee_id'] ?? null);
        $this->assertSame('Admin', $_SESSION['username'] ?? null);
        $this->assertSame('keep-me', $_SESSION['vault_key'] ?? null);
        $this->assertSame($isolatedCsrf, $_SESSION['csrf_token'] ?? null);
        $this->assertArrayNotHasKey('itm_script_browser_isolated', $_SESSION);
        $this->assertArrayNotHasKey('itm_script_browser_session_backup', $GLOBALS);
    }

    public function testFinishBrowserIsolatedSessionPreservesBackupCsrfWhenIsolatedTokenMissing(): void
    {
        $backupCsrf = 'backup-csrf-' . bin2hex(random_bytes(8));
        $backup = [
            'employee_id' => 1,
            'username' => 'Admin',
            'company_id' => 1,
            'csrf_token' => $backupCsrf,
        ];

        $GLOBALS['itm_script_browser_session_backup'] = $backup;
        $GLOBALS['itm_script_browser_isolated_employee_id'] = 0;
        $GLOBALS['itm_script_browser_isolated_conn'] = null;
        $_SESSION = [
            'employee_id' => 999901,
            'username' => 'script-phpunit-def67890',
            'company_id' => 1,
            'itm_script_browser_isolated' => 1,
        ];

        itm_script_finish_browser_isolated_session();

        $this->assertSame($backupCsrf, $_SESSION['csrf_token'] ?? null);
        $this->assertArrayNotHasKey('itm_script_browser_session_backup', $GLOBALS);
    }
}
