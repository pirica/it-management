<?php

use PHPUnit\Runner\AfterTestHook;

/**
 * Why: PHPUnit reuses one MySQL connection for the full suite. Tests that set
 * @app_company_id to a temp company and delete that company leave stale audit
 * session vars that break audit triggers on subsequent INSERTs.
 */
final class ItmAuditContextCleanupExtension implements AfterTestHook
{
    public function executeAfterTest(string $test, float $time): void
    {
        global $conn;

        if (!($conn instanceof mysqli)) {
            return;
        }

        if (!function_exists('itm_script_test_employee_clear_audit_context')) {
            require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
        }

        itm_script_test_employee_clear_audit_context($conn);
    }
}
