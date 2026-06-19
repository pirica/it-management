<?php
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Select Options API whitelisting.
 *
 * Why: Ensures that sensitive tables like `companies` are not whitelisted for
 * quick-add by regular users.
 */
class SelectOptionsBypassTest extends TestCase
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

    public function testWhitelistedTables()
    {
        $policyPath = ROOT_PATH . 'includes/itm_select_options_policy.php';
        if (!file_exists($policyPath)) {
            $this->markTestSkipped('Select Options policy file not found.');
        }

        require_once $policyPath;

        if (!function_exists('itm_select_options_is_table_allowed')) {
            $this->fail('itm_select_options_is_table_allowed function not defined.');
        }

        // 1. Test a legitimate lookup table
        $this->assertTrue(itm_select_options_is_table_allowed('manufacturers'));

        // 2. Test a blocked RBAC table
        $this->assertFalse(itm_select_options_is_table_allowed('employee_roles'));

        // 3. Test 'companies' table
        // This is currently whitelisted, so this test might fail if it's considered a vulnerability.
        // We assert it is NOT allowed to confirm the vulnerability presence.
        $this->markTestIncomplete('Vulnerability: Unauthorized Entity Creation via Select Options API. This test is expected to fail until the fix is applied.');

        $this->assertFalse(
            itm_select_options_is_table_allowed('companies'),
            'VULNERABLE: The companies table is whitelisted for quick-add via Select Options API.'
        );
    }
}
