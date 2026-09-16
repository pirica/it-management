<?php
/**
 * Regression: authenticated vault encryption (AES-256-GCM v2) + legacy CBC read.
 *
 * CLI: php scripts/verify_vault_gcm.php
 * Browser: scripts/verify_vault_gcm.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_vault_gcm.php</code> — exit <code>1</code> on failure. Browser: <a href="verify_vault_gcm.php?run=1">verify_vault_gcm.php?run=1</a> (Administrator).
<p>Verifies <code>itm_encrypt()</code> writes <code>v2:</code> AES-256-GCM payloads, <code>itm_decrypt()</code> round-trips v2, still reads legacy v1 CBC rows, and rejects tampered v2 ciphertext.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Vault GCM verification');

$fail = 0;
function vault_gcm_fail($msg)
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function vault_gcm_pass($msg)
{
    echo "[PASS] {$msg}\n";
}

/**
 * Why: Regression-only helper to mint legacy v1 CBC blobs still present in live DBs.
 */
function vault_gcm_legacy_v1_encrypt($data, $key)
{
    $cipher = 'aes-256-cbc';
    $ivLen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLen);
    $encrypted = openssl_encrypt((string) $data, $cipher, $key, 0, $iv);

    return base64_encode($iv . $encrypted);
}

$key = hash('sha256', 'verify-vault-gcm-key');
$plain = 'vault-secret-🧩-' . bin2hex(random_bytes(4));

$v2 = itm_encrypt($plain, $key);
if (is_string($v2) && strncmp($v2, 'v2:', 3) === 0) {
    vault_gcm_pass('itm_encrypt emits v2: GCM prefix');
} else {
    vault_gcm_fail('itm_encrypt emits v2: GCM prefix');
}

if ($v2 !== false && itm_decrypt($v2, $key) === $plain) {
    vault_gcm_pass('v2 round-trip decrypt');
} else {
    vault_gcm_fail('v2 round-trip decrypt');
}

$legacy = vault_gcm_legacy_v1_encrypt($plain, $key);
if (strncmp((string) $legacy, 'v2:', 3) !== 0 && itm_decrypt($legacy, $key) === $plain) {
    vault_gcm_pass('legacy v1 CBC decrypt unchanged');
} else {
    vault_gcm_fail('legacy v1 CBC decrypt unchanged');
}

$tampered = $v2;
$decoded = base64_decode(substr($tampered, 3), true);
if ($decoded !== false && strlen($decoded) > 20) {
    $decoded[20] = $decoded[20] === 'a' ? 'b' : 'a';
    $tampered = 'v2:' . base64_encode($decoded);
}
if (itm_decrypt($tampered, $key) === false) {
    vault_gcm_pass('tampered v2 ciphertext rejected');
} else {
    vault_gcm_fail('tampered v2 ciphertext rejected');
}

if (itm_decrypt('v2:not-valid-base64!!!', $key) === false) {
    vault_gcm_pass('invalid v2 payload returns false');
} else {
    vault_gcm_fail('invalid v2 payload returns false');
}

if ($fail === 0) {
    vault_gcm_pass('Vault GCM regression complete');
    itm_script_output_end();
    exit(0);
}

vault_gcm_fail('Vault GCM regression failed');
itm_script_output_end();
exit(1);
