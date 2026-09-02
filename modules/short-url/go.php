<?php
/**
 * Public short URL redirect — no login.
 */
define('ITM_SHORT_URL_PUBLIC', true);
require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . 'includes/itm_short_url.php';

header('Content-Type: text/html; charset=utf-8');

$rate = itm_short_url_rate_limit_check(true);
if (empty($rate['ok'])) {
    itm_short_url_render_public_page(429, 'Too many requests', 'Too many requests. Please try again later.');
    exit;
}

$code = trim((string) ($_GET['c'] ?? ''));
$token = trim((string) ($_GET['t'] ?? ''));
$row = null;
if ($code !== '') {
    $row = itm_short_url_fetch_by_code($conn, $code);
} elseif ($token !== '') {
    $row = itm_short_url_fetch_by_token($conn, $token);
}

if (!$row) {
    itm_short_url_render_public_page(404, 'Invalid short URL', 'Invalid short URL. This link is invalid or no longer available.');
    exit;
}

$shortCode = (string) ($row['short_code'] ?? '');

if (itm_short_url_is_expired($row)) {
    itm_short_url_render_public_page(410, 'Link expired', 'This short link has expired.');
    exit;
}

$passwordHash = trim((string) ($row['password_hash'] ?? ''));
if ($passwordHash !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['short_url_password_gate'])) {
        $submitted = (string) ($_POST['link_password'] ?? '');
        if ($submitted !== '' && password_verify($submitted, $passwordHash)) {
            itm_short_url_set_password_verified($shortCode);
        } else {
            itm_short_url_render_password_gate($row, 'Incorrect password.');
            exit;
        }
    } elseif (!itm_short_url_password_verified($shortCode)) {
        itm_short_url_render_password_gate($row);
        exit;
    }
}

itm_short_url_record_click($conn, $row);

$destination = itm_short_url_normalize_destination((string) ($row['destination_url'] ?? ''));
if ($destination === '') {
    itm_short_url_render_public_page(404, 'Invalid short URL', 'Invalid short URL. Destination unavailable.');
    exit;
}

header('Location: ' . $destination, true, 302);
exit;
