<?php
/**
 * Public token-scoped asset serve for QR landing pages.
 */
define('ITM_QR_GENERATOR_PUBLIC', true);
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_generator.php';

$token = trim((string) ($_GET['t'] ?? ''));
$rel = ltrim(str_replace('\\', '/', (string) ($_GET['p'] ?? '')), '/');
if ($token === '' || $rel === '' || strpos($rel, '..') !== false) {
    http_response_code(404);
    exit;
}

$row = itm_qr_generator_fetch_by_token($conn, $token);
if (!$row) {
    http_response_code(404);
    exit;
}

$payload = itm_qr_generator_decode_json_field($row['payload_json'] ?? '');
$allowed = [];
if (!empty($payload['file_path'])) {
    $allowed[] = (string) $payload['file_path'];
}
foreach ((array) ($payload['files'] ?? []) as $f) {
    $allowed[] = (string) $f;
}
if (!in_array($rel, $allowed, true)) {
    http_response_code(403);
    exit;
}

if (!function_exists('itm_files_storage_root')) {
    http_response_code(500);
    exit;
}
$abs = rtrim(itm_files_storage_root(), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
if (!is_file($abs)) {
    http_response_code(404);
    exit;
}

$mime = function_exists('mime_content_type') ? mime_content_type($abs) : 'application/octet-stream';
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($abs));
readfile($abs);
exit;
