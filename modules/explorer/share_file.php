<?php
/**
 * Serves Explorer files for an active share session (token-scoped; no login).
 */

define('ITM_QR_SHARE_PUBLIC', true);
require_once '../../config/config.php';
require_once __DIR__ . '/explorer_share_helpers.php';

$accessToken = trim((string)($_GET['t'] ?? ''));
$fileId = trim((string)($_GET['file'] ?? ''));
$session = null;
$validation = explorer_share_validate_file_request($conn, $accessToken, $fileId, $session);
if (!$validation['ok'] || !$session) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$companyId = (int)$session['company_id'];
$employeeId = (int)$session['employee_id'];
$payload = $validation['payload'] ?? [];
$ownerUsername = is_array($payload) ? (string)($payload['owner_username'] ?? '') : '';

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

$storageRoot = ROOT_PATH . 'files/' . $companyId;
itm_ensure_files_storage_directory($storageRoot);
$filePath = explorer_share_resolve_file_path($conn, $storageRoot, $session, $validation['file'], $username);
if ($filePath === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$mime = (string)($validation['file']['mime'] ?? 'application/octet-stream');
$downloadName = basename((string)($validation['file']['name'] ?? 'download'));
$disposition = 'attachment';
$inlineMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain', 'text/markdown'];
if (in_array($mime, $inlineMimes, true)) {
    $disposition = 'inline';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Cache-Control: private, max-age=300');
readfile($filePath);
exit;
