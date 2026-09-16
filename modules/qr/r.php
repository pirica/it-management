<?php
/**
 * Public dynamic QR resolver — no login.
 */
define('ITM_QR_GENERATOR_PUBLIC', true);
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_generator.php';

header('Content-Type: text/html; charset=utf-8');

$rate = itm_qr_generator_rate_limit_check(true);
if (empty($rate['ok'])) {
    http_response_code(429);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Too many requests</title></head><body><p>Too many requests. Please try again later.</p></body></html>';
    exit;
}

$token = trim((string) ($_GET['t'] ?? ''));
$row = $token !== '' ? itm_qr_generator_fetch_by_token($conn, $token) : null;

if (!$row || (string) ($row['encoding_mode'] ?? '') !== 'dynamic') {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not found</title><link rel="stylesheet" href="../../css/styles.css"></head><body><p style="margin:40px;text-align:center;">This QR link is invalid or no longer available.</p></body></html>';
    exit;
}

itm_qr_generator_record_scan($conn, $row);

$typeSlug = (string) ($row['type_slug'] ?? '');
$payload = itm_qr_generator_decode_json_field($row['payload_json'] ?? '');

if ($typeSlug === 'website') {
    $url = trim((string) ($payload['url'] ?? ''));
    if ($url !== '') {
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        header('Location: ' . $url, true, 302);
        exit;
    }
}

$qrLandingRow = $row;
$qrLandingPayload = $payload;
$appTitle = 'QR Content';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($appTitle) ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>body{background:var(--bg-secondary);}</style>
</head>
<body>
<?php include __DIR__ . '/includes/partials/landing.php'; ?>
</body>
</html>
