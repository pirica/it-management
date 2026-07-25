<?php
/**
 * CLI: php scripts/verify_whatsapp_share.php
 * Verifies WhatsApp deep-link message/url helpers for temporary share sessions.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_whatsapp_share.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('WhatsApp Share Helper Verification');

$failures = 0;

function whatsapp_share_verify_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function whatsapp_share_verify_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

$joinUrl = 'http://localhost/it-management/modules/notes/join.php?t=abc123';
$shareCode = '123456';
$message = itm_whatsapp_share_build_message('note', $joinUrl, $shareCode);
if (strpos($message, $joinUrl) === false || strpos($message, '123456') === false || stripos($message, '30 minutes') === false) {
    whatsapp_share_verify_fail('Message missing join URL, code, or expiry copy.');
} else {
    whatsapp_share_verify_pass('Share message includes join URL, code, and expiry text.');
}

$waUrl = itm_whatsapp_share_build_url($message);
if (strpos($waUrl, 'https://wa.me/?text=') !== 0) {
    whatsapp_share_verify_fail('WhatsApp URL does not use wa.me scheme.');
} else {
    whatsapp_share_verify_pass('WhatsApp URL uses wa.me deep link.');
}

$decoded = rawurldecode(substr($waUrl, strlen('https://wa.me/?text=')));
if ($decoded !== $message) {
    whatsapp_share_verify_fail('WhatsApp URL text does not round-trip the message.');
} else {
    whatsapp_share_verify_pass('WhatsApp URL encodes the full message.');
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failure(s).\n");
    exit(1);
}

fwrite(STDOUT, "\nAll WhatsApp share helper checks passed.\n");
exit(0);
