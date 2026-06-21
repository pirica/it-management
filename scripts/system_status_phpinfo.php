<?php
/**
 * Admin-only phpinfo() for the active Apache PHP runtime (System Status helper).
 */

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['employee_id']) || !itm_is_admin($conn, $_SESSION['employee_id'])) {
    http_response_code(403);
    echo 'Forbidden: administrator access required.';
    exit;
}

phpinfo();
