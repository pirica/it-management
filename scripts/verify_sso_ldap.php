<?php
/**
 * Regression: LDAP SSO config encryption and schema columns.
 *
 * CLI: php scripts/verify_sso_ldap.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_sso_ldap.php</code>. Run when changing <code>includes/itm_ldap_auth.php</code>, <code>sso-ldap.php</code>, or company SSO schema.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/itm_ldap_auth.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$conn = $GLOBALS['conn'] ?? null;
$nl = itm_script_output_nl();
$failed = 0;

function vsso_pass(string $message): void
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function vsso_fail(string $message): void
{
    global $nl, $failed;
    $failed++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vsso_na(string $message): void
{
    global $nl;
    echo colorText('[N/A] ' . $message, 'info') . $nl;
}

if (!$conn instanceof mysqli) {
    vsso_fail('Database connection required.');
    itm_script_output_end();
    exit(1);
}

itm_script_output_begin('Verify SSO / LDAP');

$requiredCompanyColumns = ['sso_enabled', 'sso_jit_enabled', 'sso_provider', 'sso_config_json_encrypted'];
foreach ($requiredCompanyColumns as $column) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    if (!$stmt) {
        vsso_fail('Could not probe companies.' . $column);
        continue;
    }
    $table = 'companies';
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ((int)$count === 1) {
        vsso_pass('companies.' . $column . ' exists.');
    } else {
        vsso_fail('companies.' . $column . ' missing.');
    }
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
if ($stmt) {
    $table = 'employees';
    $column = 'sso_subject';
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ((int)$count === 1) {
        vsso_pass('employees.sso_subject exists.');
    } else {
        vsso_fail('employees.sso_subject missing.');
    }
}

$sampleConfig = itm_ldap_normalize_config([
    'host' => 'ldap.example.com',
    'port' => 389,
    'bind_dn' => 'cn=svc,dc=example,dc=com',
    'bind_password' => 'secret-bind',
    'base_dn' => 'dc=example,dc=com',
    'user_filter' => '(&(uid=%username%)(objectClass=person))',
    'username_attr' => 'uid',
    'email_attr' => 'mail',
]);
$encrypted = itm_ldap_encrypt_config($sampleConfig);
if ($encrypted !== null && $encrypted !== '') {
    vsso_pass('itm_ldap_encrypt_config returns ciphertext.');
} else {
    vsso_fail('itm_ldap_encrypt_config failed.');
}

$roundTrip = itm_ldap_decrypt_config($encrypted);
if (is_array($roundTrip)
    && ($roundTrip['host'] ?? '') === 'ldap.example.com'
    && ($roundTrip['bind_password'] ?? '') === 'secret-bind'
    && ($roundTrip['user_filter'] ?? '') === '(&(uid=%username%)(objectClass=person))') {
    vsso_pass('itm_ldap_decrypt_config round-trip matches sample config.');
} else {
    vsso_fail('itm_ldap_decrypt_config round-trip mismatch.');
}

if (function_exists('itm_ldap_extension_available') && itm_ldap_extension_available()) {
    vsso_pass('PHP ldap extension is loaded.');
} else {
    vsso_na('PHP ldap extension is not loaded — live LDAP bind tests skipped on this host.');
}

$ssoEntry = dirname(__DIR__) . '/sso-ldap.php';
if (is_file($ssoEntry)) {
    vsso_pass('sso-ldap.php entry point exists.');
} else {
    vsso_fail('sso-ldap.php entry point missing.');
}

$helperPath = dirname(__DIR__) . '/includes/itm_ldap_auth.php';
$helperSource = is_file($helperPath) ? (string)file_get_contents($helperPath) : '';
if (strpos($helperSource, 'function itm_sso_resolve_company_for_login') !== false
    && strpos($helperSource, 'function itm_ldap_match_or_provision_employee') !== false
    && strpos($helperSource, 'sso_jit_enabled') !== false) {
    vsso_pass('itm_ldap_auth.php exports required helpers and JIT gate.');
} else {
    vsso_fail('itm_ldap_auth.php missing required helpers or JIT gate.');
}

itm_script_output_end();
exit($failed > 0 ? 1 : 0);
