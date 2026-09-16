<?php
/**
 * Regression: QR share public join hardening (code length, token-only modules, IP rate limit).
 *
 * CLI: php scripts/verify_qr_share_join_hardening.php
 * Browser: scripts/verify_qr_share_join_hardening.php?run=1 (Administrator).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_qr_share_join_hardening.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_qr_share.php</code>, public <code>join.php</code> handlers, or share-session TTL/code policy.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('QR share join hardening verification');

$nl = itm_script_output_nl();
$failures = 0;

function qr_harden_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function qr_harden_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (itm_qr_share_code_length() !== 8) {
    qr_harden_fail('itm_qr_share_code_length() must be 8 (got ' . itm_qr_share_code_length() . ')');
} else {
    qr_harden_pass('Share code length is 8 digits');
}

$legacy = itm_qr_share_normalize_code('123456');
if ($legacy !== '123456') {
    qr_harden_fail('Legacy six-digit normalize should still accept active TTL codes');
} else {
    qr_harden_pass('Legacy six-digit codes still normalize during TTL overlap');
}

$newCode = itm_qr_share_normalize_code('12345678');
if ($newCode !== '12345678') {
    qr_harden_fail('Eight-digit normalize failed');
} else {
    qr_harden_pass('Eight-digit codes normalize');
}

if (itm_qr_share_normalize_code('1234567') !== '') {
    qr_harden_fail('Seven-digit codes must be rejected');
} else {
    qr_harden_pass('Invalid code lengths rejected');
}

$tokenOnly = itm_qr_share_token_only_module_slugs();
foreach (['passwords', 'private_contacts', 'explorer', 'notes', 'webmail'] as $requiredSlug) {
    if (!in_array($requiredSlug, $tokenOnly, true)) {
        qr_harden_fail('Token-only list missing ' . $requiredSlug);
    }
}
if ($failures === 0) {
    qr_harden_pass('Token-only module slug list includes vault/file modules');
}

if (itm_qr_share_module_allows_numeric_code('passwords')) {
    qr_harden_fail('passwords must not allow numeric join codes');
} else {
    qr_harden_pass('passwords is token-only (no numeric code join)');
}

if (!itm_qr_share_module_allows_numeric_code('todo')) {
    qr_harden_fail('todo should still allow numeric join codes');
} else {
    qr_harden_pass('todo still allows numeric join codes');
}

$blockedLookup = itm_qr_share_fetch_session_by_code($conn, 'passwords', '12345678');
if ($blockedLookup !== null) {
    qr_harden_fail('fetch_session_by_code must not resolve passwords by numeric code');
} else {
    qr_harden_pass('Numeric code lookup blocked for passwords module');
}

$rateDir = itm_qr_share_join_rate_limit_ip_dir();
if ($rateDir === '' || strpos($rateDir, 'qr_share_join') === false) {
    qr_harden_fail('Rate-limit storage path missing qr_share_join segment');
} else {
    qr_harden_pass('Join rate-limit storage path configured');
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
$probeIp = itm_qr_share_join_rate_limit_ip_dir() . DIRECTORY_SEPARATOR . hash('sha256', '203.0.113.77') . '.json';
if (is_file($probeIp)) {
    @unlink($probeIp);
}
for ($i = 0; $i < 20; $i++) {
    itm_qr_share_join_rate_limit_check(true, 20, 900);
}
$blocked = itm_qr_share_join_rate_limit_check(false, 20, 900);
if (!empty($blocked['ok'])) {
    qr_harden_fail('Rate limit should block after 20 recorded attempts per IP');
} else {
    qr_harden_pass('Join code lookup rate limit blocks excessive attempts');
}
if (is_file($probeIp)) {
    @unlink($probeIp);
}

$generated = itm_qr_share_generate_code($conn);
if ($generated === '' || strlen($generated) !== 8) {
    qr_harden_fail('Generated share codes must be eight digits');
} else {
    qr_harden_pass('New share codes are eight digits');
}

if ($failures > 0) {
    echo colorText('SUMMARY: ' . $failures . ' check(s) failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: QR share join hardening checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
