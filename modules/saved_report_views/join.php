<?php
/**
 * Public read-only table for a token-scoped saved report view share.
 */
define('ITM_QR_SHARE_PUBLIC', true);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'includes/itm_saved_reports.php';

$moduleSlug = itm_saved_reports_share_module_slug();
$accessToken = trim((string) ($_GET['t'] ?? $_GET['token'] ?? ''));
$submittedCode = trim((string) ($_POST['share_code'] ?? ''));
$error = '';
$session = null;
$payload = null;
$liveTableHtml = '';

if ($accessToken !== '') {
    $session = itm_qr_share_fetch_session_by_token($conn, $moduleSlug, $accessToken);
} elseif ($submittedCode !== '') {
    $rateLimit = itm_qr_share_join_rate_limit_check(true);
    if (empty($rateLimit['ok'])) {
        $error = (string) ($rateLimit['error'] ?? 'Too many attempts. Try again later.');
    } else {
        $session = itm_qr_share_fetch_session_by_code($conn, $moduleSlug, $submittedCode);
        if ($session) {
            $accessToken = (string) ($session['access_token'] ?? '');
        }
    }
}

if ($session && (string) ($session['module_slug'] ?? '') === $moduleSlug) {
    $payload = itm_qr_share_decode_payload((string) ($session['payload_json'] ?? ''));
    $live = itm_saved_reports_share_render_live_table($conn, $session);
    if (!empty($live['ok'])) {
        $liveTableHtml = (string) ($live['html'] ?? '');
        if (is_array($payload)) {
            $payload['heading'] = (string) ($live['title'] ?? ($payload['heading'] ?? 'Saved report'));
        }
    } else {
        $error = (string) ($live['error'] ?? 'Could not load report data.');
        $session = null;
    }
} elseif ($submittedCode !== '' || $accessToken !== '') {
    $error = $error !== '' ? $error : 'Share link expired or invalid.';
}

if ($session && $payload && $liveTableHtml !== '') {
    $payload['live_table_html'] = $liveTableHtml;
}

require_once ROOT_PATH . 'includes/itm_qr_share_join.php';
itm_qr_share_render_join_page(
    'Saved report',
    'modules/saved_report_views/join.php',
    $accessToken,
    $submittedCode,
    $error,
    $session,
    $payload
);
