<?php
/**
 * CLI: php scripts/verify_outlook_share.php
 * Verifies Outlook/mail compose helpers for temporary share sessions.
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_outlook_share.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_outlook_share.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Outlook Share Helper Verification');

$failures = 0;

function outlook_share_verify_fail($message)
{
    global $failures;
    $failures++;
    itm_script_write_stderr( "[FAIL] {$message}\n");
}

function outlook_share_verify_pass($message)
{
    itm_script_write_stdout( "[PASS] {$message}\n");
}

$joinUrl = 'http://localhost/it-management/modules/todo/join.php?t=abc123';
$shareCode = '654321';
$subject = itm_outlook_share_build_subject('task');
$body = itm_outlook_share_build_body('task', $joinUrl, $shareCode);

if ($subject !== 'Shared task') {
    outlook_share_verify_fail('Subject was not built as expected.');
} else {
    outlook_share_verify_pass('Subject includes shared item label.');
}

if (strpos($body, $joinUrl) === false || strpos($body, '654321') === false || stripos($body, '30 minutes') === false) {
    outlook_share_verify_fail('Body missing join URL, code, or expiry copy.');
} else {
    outlook_share_verify_pass('Body includes join URL, code, and expiry text.');
}

$mailtoUrl = itm_outlook_share_build_mailto_url($subject, $body);
if (strpos($mailtoUrl, 'mailto:?subject=') !== 0) {
    outlook_share_verify_fail('mailto URL does not use mailto scheme.');
} else {
    outlook_share_verify_pass('mailto URL uses mailto scheme.');
}

$webUrl = itm_outlook_share_build_web_compose_url($subject, $body);
if (strpos($webUrl, 'https://outlook.office.com/mail/deeplink/compose?') !== 0) {
    outlook_share_verify_fail('Outlook web compose URL is incorrect.');
} else {
    outlook_share_verify_pass('Outlook web compose URL is built.');
}

if ($failures > 0) {
    itm_script_write_stderr("\n{$failures} failure(s).\n");
    itm_script_output_end();
    exit(1);
}

itm_script_write_stdout("\nAll Outlook share helper checks passed.\n");
itm_script_output_end();
exit(0);
