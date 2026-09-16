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

$requiredCompanyColumns = ['sso_enabled', 'sso_jit_enabled', 'sso_provider', 'sso_config_json_encrypted', 'asset_disposal_approval_required'];
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

$samlEntry = dirname(__DIR__) . '/sso-saml.php';
$samlAcs = dirname(__DIR__) . '/sso-saml-acs.php';
if (is_file($samlEntry) && is_file($samlAcs)) {
    vsso_pass('sso-saml.php and sso-saml-acs.php entry points exist.');
} else {
    vsso_fail('SAML entry points missing.');
}

$samlHelperPath = dirname(__DIR__) . '/includes/itm_saml_auth.php';
if (is_file($samlHelperPath)) {
    require_once $samlHelperPath;
    $sampleSaml = itm_saml_normalize_config([
        'idp_entity_id' => 'https://idp.example.com/metadata',
        'idp_sso_url' => 'https://idp.example.com/sso',
        'idp_x509_cert' => 'MIIBsample',
        'attribute_username' => 'name',
        'attribute_email' => 'email',
    ]);
    $samlEncrypted = itm_saml_encrypt_config($sampleSaml);
    $samlRoundTrip = itm_saml_decrypt_config($samlEncrypted);
    if (is_array($samlRoundTrip) && ($samlRoundTrip['idp_entity_id'] ?? '') === 'https://idp.example.com/metadata') {
        vsso_pass('itm_saml_encrypt_config / decrypt_config round-trip.');
    } else {
        vsso_fail('itm_saml config round-trip mismatch.');
    }
} else {
    vsso_fail('includes/itm_saml_auth.php missing.');
}

$helperPath = dirname(__DIR__) . '/includes/itm_ldap_auth.php';
$helperSource = is_file($helperPath) ? (string)file_get_contents($helperPath) : '';
if (strpos($helperSource, 'function itm_sso_resolve_company_for_login') !== false
    && strpos($helperSource, 'function itm_ldap_match_or_provision_employee') !== false
    && strpos($helperSource, 'function itm_ldap_resolve_jit_default_role_id') !== false
    && strpos($helperSource, 'sso_jit_enabled') !== false) {
    vsso_pass('itm_ldap_auth.php exports required helpers and JIT gate.');
} else {
    vsso_fail('itm_ldap_auth.php missing required helpers or JIT gate.');
}

$jitCompanyId = 1;
$jitUsername = 'jit-verify-' . bin2hex(random_bytes(4));
$jitSubject = 'cn=' . $jitUsername . ',dc=verify,dc=local';
$restoreJit = null;
$jitColRes = mysqli_query($conn, 'SELECT sso_jit_enabled FROM companies WHERE id = ' . $jitCompanyId . ' LIMIT 1');
$jitColRow = $jitColRes ? mysqli_fetch_assoc($jitColRes) : null;
$restoreJit = (int)($jitColRow['sso_jit_enabled'] ?? 0);
mysqli_query($conn, 'UPDATE companies SET sso_jit_enabled = 1 WHERE id = ' . $jitCompanyId . ' LIMIT 1');

$ldapUser = [
    'sso_subject' => $jitSubject,
    'username' => $jitUsername,
    'email' => $jitUsername . '@jit-verify.example.com',
    'display_name' => 'JIT Verify User',
];
$provisioned = itm_ldap_match_or_provision_employee($conn, $jitCompanyId, $ldapUser);
if (!is_array($provisioned) || (int)($provisioned['id'] ?? 0) <= 0) {
    vsso_fail('JIT provisioning did not create employee row.');
} else {
    $newId = (int)$provisioned['id'];
    vsso_pass('JIT provisioning created employee id ' . $newId . '.');
    mysqli_query($conn, 'DELETE FROM employee_companies WHERE employee_id = ' . $newId);
    mysqli_query($conn, 'DELETE FROM employees WHERE id = ' . $newId . ' AND company_id = ' . $jitCompanyId);
}
if ($restoreJit !== null) {
    mysqli_query($conn, 'UPDATE companies SET sso_jit_enabled = ' . (int)$restoreJit . ' WHERE id = ' . $jitCompanyId . ' LIMIT 1');
}

itm_script_output_end();
exit($failed > 0 ? 1 : 0);
