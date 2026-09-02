<?php
/**
 * Serves floor plan files for an active share session (token-scoped; no login).
 */

define('ITM_QR_SHARE_PUBLIC', true);
require_once '../../config/config.php';
require_once __DIR__ . '/floor_plans_share_helpers.php';

$accessToken = trim((string)($_GET['t'] ?? ''));
$session = null;
$validation = floor_plans_share_validate_asset_request($conn, $accessToken, $session);
if (!$validation['ok'] || !$session) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$companyId = (int)$session['company_id'];
$payload = $validation['payload'] ?? [];
$storedFilename = (string)($payload['stored_filename'] ?? '');
if ($storedFilename === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$path = fp_absolute_path($companyId, $storedFilename);
if (!is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$mime = (string)($payload['mime_type'] ?? 'application/octet-stream');
if ($mime === '') {
    $mime = 'application/octet-stream';
}
$downloadName = (string)($payload['display_name'] ?? 'floor-plan');
$ext = (string)($payload['file_ext'] ?? '');
if ($ext !== '' && !preg_match('/\.' . preg_quote($ext, '/') . '$/i', $downloadName)) {
    $downloadName .= '.' . $ext;
}

$previewKind = (string)($payload['preview_kind'] ?? '');
$disposition = ($previewKind === 'image' || $previewKind === 'pdf') ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
