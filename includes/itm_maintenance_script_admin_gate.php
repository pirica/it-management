<?php
/**
 * Browser-only Admin gate for maintenance and security scripts.
 *
 * Why: CLI runners (smoke, MBQA, compare_database_sql_modules --json) stay usable
 * without a session; browser access to sensitive tools requires Admin.
 */

if (!function_exists('itm_enforce_maintenance_script_admin_browser')) {
    function itm_enforce_maintenance_script_admin_browser($conn)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (!($conn instanceof mysqli)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Database connection is required.';
            exit;
        }

        // Why: scripts/* browser isolation swaps to a disposable test employee — honor the real signed-in Admin too.
        if (function_exists('itm_script_session_or_authorization_is_admin')
            && itm_script_session_or_authorization_is_admin($conn)) {
            return;
        }

        $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
        $sessionCompanyId = (int)($_SESSION['company_id'] ?? 0);
        $authorizationEmployeeId = function_exists('itm_script_get_browser_authorization_employee_id')
            ? itm_script_get_browser_authorization_employee_id()
            : 0;

        if ($sessionEmployeeId <= 0 && $sessionCompanyId <= 0 && $authorizationEmployeeId <= 0) {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Login required. Sign in as an administrator, then open this script again.';
            exit;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: administrator access required.';
        exit;
    }
}
