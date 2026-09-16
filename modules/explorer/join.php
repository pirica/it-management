<?php
/**
 * Public join page for temporary Explorer share sessions (QR / 6-digit code).
 */

define('ITM_QR_SHARE_PUBLIC', true);
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'includes/itm_qr_share_join.php';
require_once __DIR__ . '/explorer_share_helpers.php';

$accessToken = trim((string)($_GET['t'] ?? ''));
$submittedCode = itm_qr_share_normalize_code($_POST['code'] ?? ($_GET['code'] ?? ''));
$error = '';
$session = null;

if ($accessToken !== '') {
    $session = itm_qr_share_fetch_session_by_token($conn, explorer_share_table_name(), $accessToken);
    if (!$session) {
        $error = 'This share link has expired or is invalid.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $submittedCode !== '') {
    $session = itm_qr_share_fetch_session_by_code($conn, explorer_share_table_name(), $submittedCode);
    if (!$session) {
        $error = 'Code not found or expired. Check the code and try again.';
    } else {
        $accessToken = (string)$session['access_token'];
    }
}

$payload = $session ? itm_qr_share_decode_payload($session['payload_json'] ?? '') : null;
itm_qr_share_render_join_page(
    'folder',
    explorer_share_join_script_path(),
    $accessToken,
    $submittedCode,
    $error,
    $session,
    $payload
);
