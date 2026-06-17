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

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || (int)($_SESSION['company_id'] ?? 0) <= 0) {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Login required. Sign in as an administrator, then open this script again.';
            exit;
        }

        if (!function_exists('itm_is_admin') || !itm_is_admin($conn, $userId)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden: administrator access required.';
            exit;
        }
    }
}
