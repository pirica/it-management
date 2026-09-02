<?php
/**
 * Regression: Hotel distribution outbound webhook URL SSRF guardrails.
 *
 * CLI: php scripts/verify_hotel_booking_distribution_webhook_ssrf.php
 * Browser: scripts/verify_hotel_booking_distribution_webhook_ssrf.php?run=1 (Administrator).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_hotel_booking_distribution_webhook_ssrf.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_hotel_booking_distribution_webhooks.php</code> or channel <code>webhook_url</code> save paths.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_webhooks.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Hotel distribution webhook SSRF verification');

$nl = itm_script_output_nl();
$failures = 0;

function hbw_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function hbw_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!function_exists('itm_hotel_booking_distribution_validate_webhook_url')) {
    hbw_fail('itm_hotel_booking_distribution_validate_webhook_url() missing');
} else {
    hbw_pass('Webhook URL validator loaded');
}

$blockedUrls = [
    'http://127.0.0.1/admin',
    'http://localhost/hook',
    'http://169.254.169.254/latest/meta-data/',
    'http://10.0.0.5/callback',
    'http://192.168.1.20/callback',
];
foreach ($blockedUrls as $blockedUrl) {
    $result = itm_hotel_booking_distribution_validate_webhook_url($blockedUrl);
    if (!empty($result['ok'])) {
        hbw_fail('Must block SSRF-prone URL: ' . $blockedUrl);
    }
}
if ($failures === 0) {
    hbw_pass('Private/link-local/metadata webhook URLs rejected');
}

$allowed = itm_hotel_booking_distribution_validate_webhook_url('https://partner.example.com/itm/webhook');
if (empty($allowed['ok'])) {
    hbw_fail('Public HTTPS partner webhook URL should be allowed');
} else {
    hbw_pass('Public HTTPS partner webhook URL allowed');
}

$empty = itm_hotel_booking_distribution_validate_webhook_url('');
if (empty($empty['ok'])) {
    hbw_fail('Empty webhook URL should be allowed (optional field)');
} else {
    hbw_pass('Empty webhook URL allowed');
}

$editPath = dirname(__DIR__) . '/modules/hotel_booking_distribution_channels/edit.php';
$editSource = is_file($editPath) ? (string) file_get_contents($editPath) : '';
if ($editSource === '' || strpos($editSource, 'itm_hotel_booking_distribution_validate_webhook_url') === false) {
    hbw_fail('Channel edit.php must validate webhook_url with shared SSRF helper');
} else {
    hbw_pass('Channel edit.php uses shared webhook URL validator');
}

$queueRow = [
    'id' => 999999,
    'attempt_count' => 0,
    'max_attempts' => 1,
    'target_url' => 'http://127.0.0.1/internal',
    'payload_body' => '{}',
    'content_type' => 'application/json; charset=utf-8',
];
$channelRow = ['outbound_webhook_api_key_encrypted' => '', 'webhook_signing_secret_encrypted' => ''];
$deliver = itm_hotel_booking_distribution_deliver_webhook_queue_row($conn, $queueRow, $channelRow);
if (!empty($deliver['success'])) {
    hbw_fail('Deliver must not succeed for blocked webhook URL');
} else {
    hbw_pass('Deliver rejects blocked webhook URL without outbound HTTP');
}

if ($failures > 0) {
    echo colorText('SUMMARY: ' . $failures . ' check(s) failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: Hotel distribution webhook SSRF checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
