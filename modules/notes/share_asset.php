<?php
/**
 * Serves note images for an active share session (token-scoped; no login).
 */

define('ITM_NOTES_SHARE_PUBLIC', true);
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/notes_share_helpers.php';

$accessToken = trim((string)($_GET['t'] ?? ''));
$storedFilename = (string)($_GET['file'] ?? '');
$session = null;
$validation = notes_share_validate_asset_request($conn, $accessToken, $storedFilename, $session);
if (!$validation['ok'] || !$session) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$companyId = (int)$session['company_id'];
$employeeId = (int)$session['employee_id'];
$payload = notes_share_decode_payload($session['payload_json'] ?? '');
$ownerUsername = $payload ? (string)$payload['owner_username'] : '';

$stmt = $conn->prepare('SELECT username FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
$username = $ownerUsername;
if ($stmt) {
    $stmt->bind_param('ii', $employeeId, $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($row['username'])) {
        $username = (string)$row['username'];
    }
}

$path = itm_notes_resolve_image_path($companyId, $username, $employeeId, $storedFilename);
if ($path === null || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $path);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
