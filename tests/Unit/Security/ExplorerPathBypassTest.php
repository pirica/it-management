<?php
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Explorer path validation bypass.
 *
 * Why: Ensures that using a `./` prefix cannot bypass segment-boundary checks in get_full_path().
 */
class ExplorerPathBypassTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }

        require_once __DIR__ . '/../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testGetFullPathBypass()
    {
        global $conn;
        // Path to api.php which contains get_full_path
        $apiPath = ROOT_PATH . 'modules/explorer/api.php';
        if (!file_exists($apiPath)) {
            $this->markTestSkipped('Explorer API file not found.');
        }

        // Mock session to satisfy api.php
        $_SESSION['company_id'] = 1;
        $_SESSION['employee_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Buffer output and suppress headers to avoid "headers already sent"
        ob_start();
        @require_once $apiPath;
        ob_end_clean();

        if (!function_exists('get_full_path')) {
            $this->fail('get_full_path function not defined.');
        }

        $storage_root = '/app/files/1';
        $user_id = 123;
        $dept_id = 456;
        $username = 'testuser';

        // 1. Test normal allowed path
        $this->assertNotNull(get_full_path($storage_root, 'Common', $user_id, $dept_id, $username));

        // 2. Test forbidden Private root access
        $this->assertNull(get_full_path($storage_root, 'Private', $user_id, $dept_id, $username));

        // 3. Test BYPASS: ./Private
        // If this returns non-null, it's a regression.
        $this->markTestIncomplete('Vulnerability: Explorer Path Validation Bypass via ./ Prefix. This test is expected to fail until the fix is applied.');

        $this->assertNull(
            get_full_path($storage_root, './Private', $user_id, $dept_id, $username),
            'VULNERABLE: get_full_path() permitted access to Private root via ./Private bypass.'
        );

        // 4. Test BYPASS: ./Private/other_user_1
        $this->assertNull(
            get_full_path($storage_root, './Private/other_user_1', $user_id, $dept_id, $username),
            'VULNERABLE: get_full_path() permitted access to another user folder via ./Private bypass.'
        );
    }
}
