<?php
/**
 * Audit Logs Module - Delete
 *
 * Why: Audit history is immutable; purge endpoints stay disabled so operators
 * cannot remove evidence through the UI or direct POST attempts.
 */

require '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

if ((int)($ui_config['enable_audit_logs'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Audit logs are disabled in Settings.');
}

itm_require_post_csrf();

$_SESSION['audit_logs_flash_error'] = ['Audit logs are read-only and cannot be deleted.'];
header('Location: index.php');
exit;
