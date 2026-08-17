<?php
/**
 * Integration webhooks regression checks.
 * CLI: php scripts/verify_integration_webhooks.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM'
<code>php scripts/verify_integration_webhooks.php</code> — validates webhook tables, enqueue, and queue processor.
ITM;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Integration Webhooks');
$nl = itm_script_output_nl();
$failures = 0;

function iw_fail($msg)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $msg, 'fail') . $nl;
}
function iw_pass($msg)
{
    global $nl;
    echo colorText('[PASS] ' . $msg, 'pass') . $nl;
}

foreach (['integration_webhooks', 'integration_webhook_deliveries'] as $table) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ((int)($row['c'] ?? 0) < 1) {
        iw_fail("Missing table {$table}");
    } else {
        iw_pass("Table {$table} exists");
    }
}

$secret = itm_webhook_queue_generate_secret();
$enc = itm_webhook_queue_encrypt_secret($secret);
$dec = itm_webhook_queue_decrypt_secret($enc);
if ($dec !== $secret) {
    iw_fail('Secret encrypt/decrypt round-trip failed');
} else {
    iw_pass('Secret encrypt/decrypt round-trip');
}

$urlCheck = itm_webhook_queue_validate_url('https://example.com/hook');
if (empty($urlCheck['ok'])) {
    iw_fail('Public HTTPS URL should be allowed');
} else {
    iw_pass('Webhook URL validation allows public HTTPS');
}

$blocked = itm_webhook_queue_validate_url('http://127.0.0.1/hook');
if (!empty($blocked['ok'])) {
    iw_fail('Private webhook URL should be blocked');
} else {
    iw_pass('Webhook URL validation blocks localhost');
}

$companyId = 1;
$hookName = 'Verify Hook ' . bin2hex(random_bytes(4));
$plainSecret = itm_webhook_queue_generate_secret();
$encSecret = itm_webhook_queue_encrypt_secret($plainSecret);
$ins = mysqli_prepare(
    $conn,
    "INSERT INTO integration_webhooks (company_id, name, target_url, event_types, secret_encrypted, max_attempts, active, created_at)
     VALUES (?, ?, 'https://example.com/itm-verify-hook', 'ticket.created', ?, 3, 1, NOW())"
);
$hookId = 0;
if ($ins) {
    mysqli_stmt_bind_param($ins, 'iss', $companyId, $hookName, $encSecret);
    if (mysqli_stmt_execute($ins)) {
        $hookId = (int) mysqli_insert_id($conn);
    }
    mysqli_stmt_close($ins);
}
if ($hookId <= 0) {
    iw_fail('Could not seed integration_webhooks test row');
} else {
    $queued = itm_webhook_queue_enqueue($conn, $companyId, 'ticket.created', ['event' => 'ticket.created', 'ticket_id' => 99999]);
    if ($queued < 1) {
        iw_fail('Enqueue returned zero deliveries');
    } else {
        iw_pass('Enqueue created delivery row(s)');
    }
    mysqli_query($conn, 'DELETE FROM integration_webhook_deliveries WHERE webhook_id = ' . $hookId);
    mysqli_query($conn, 'DELETE FROM integration_webhooks WHERE id = ' . $hookId);
}

$eventTypes = itm_webhook_queue_event_types();
foreach (['ticket.status_changed', 'alert.created'] as $eventType) {
    if (!in_array($eventType, $eventTypes, true)) {
        iw_fail('Event type missing: ' . $eventType);
    }
}
if ($failures === 0) {
    iw_pass('Extended webhook event types registered');
}

foreach (['itm_webhook_queue_emit_ticket_status_changed', 'itm_webhook_queue_emit_alert_created'] as $fn) {
    if (!function_exists($fn)) {
        iw_fail('Missing emitter ' . $fn . '()');
    } else {
        iw_pass('Emitter ' . $fn . '() loaded');
    }
}

itm_script_output_end($failures === 0 ? 0 : 1);
