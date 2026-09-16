<?php
/**
 * WhatsApp deep-link message/url helper regression.
 *
 * Browser: open scripts/verify_whatsapp_share.php?run=1 (signed-in session).
 * CLI: php scripts/verify_whatsapp_share.php
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_whatsapp_share.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_whatsapp_share.php</code> or <code>js/itm-whatsapp-share.js</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_whatsapp_share.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('WhatsApp Share Helper Verification');
$nl = itm_script_output_nl();

$failures = 0;

function whatsapp_share_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function whatsapp_share_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
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
    echo $nl . colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . itm_script_format_status_line('[PASS] All WhatsApp share helper checks passed.') . $nl;
itm_script_output_end();
exit(0);
