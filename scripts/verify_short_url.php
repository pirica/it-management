<?php
/**
 * CLI: php scripts/verify_short_url.php
 * Regression for modules/short-url/ Short URL module + QR integration.
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_short_url.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_short_url.php';
require_once ROOT_PATH . 'includes/itm_qr_generator.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Short URL Verification');
$nl = itm_script_output_nl();
$failures = 0;

function su_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function su_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!($conn instanceof mysqli)) {
    su_verify_fail('Database connection unavailable.');
    exit(1);
}

foreach (['short_urls', 'short_url_clicks', 'short_url_settings'] as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$res || mysqli_num_rows($res) === 0) {
        su_verify_fail($table . ' table missing — re-import db/01_schema.sql or apply db/migrations/short_url.sql.');
        exit(1);
    }
    su_verify_pass($table . ' table exists.');
}

$colRes = mysqli_query($conn, "SHOW COLUMNS FROM qr_codes LIKE 'short_url_id'");
if (!$colRes || mysqli_num_rows($colRes) === 0) {
    su_verify_fail('qr_codes.short_url_id column missing.');
    exit(1);
}
su_verify_pass('qr_codes.short_url_id column exists.');

$companyId = 1;

if (!is_file(dirname(__DIR__) . '/go.php')) {
    su_verify_fail('Root go.php stub missing.');
} else {
    su_verify_pass('Root go.php stub exists.');
}

$publicSample = itm_short_url_build_public_url('abc123', $conn, $companyId);
if (strpos($publicSample, '/go.php?c=abc123') === false) {
    su_verify_fail('Public URL builder failed.');
} else {
    su_verify_pass('Public URL builder OK.');
}

$colPublicBase = mysqli_query($conn, "SHOW COLUMNS FROM short_url_settings LIKE 'public_base_url'");
if (!$colPublicBase || mysqli_num_rows($colPublicBase) === 0) {
    su_verify_fail('short_url_settings.public_base_url column missing — apply db/migrations/short_url_public_base_url.sql.');
    exit(1);
}
su_verify_pass('short_url_settings.public_base_url column exists.');

$customBase = 'https://short.example.test/it-management/go.php?c=';
if (empty(itm_short_url_load_settings($conn, $companyId)['id'])) {
    itm_short_url_save_settings($conn, $companyId, 1, [
        'default_expiry_days' => '',
        'custom_code_min_length' => 4,
        'require_https_destination' => '',
        'analytics_enabled' => '1',
        'allow_password_protect' => '1',
        'public_base_url' => '',
    ]);
}
$settingsSql = 'UPDATE short_url_settings SET public_base_url = ? WHERE company_id = ? AND deleted_at IS NULL';
$settingsStmt = mysqli_prepare($conn, $settingsSql);
if ($settingsStmt) {
    mysqli_stmt_bind_param($settingsStmt, 'si', $customBase, $companyId);
    mysqli_stmt_execute($settingsStmt);
    mysqli_stmt_close($settingsStmt);
}
$customPublic = itm_short_url_build_public_url('xyz789', $conn, $companyId);
if ($customPublic !== $customBase . 'xyz789') {
    su_verify_fail('Custom public_base_url not applied.');
} else {
    su_verify_pass('Custom public_base_url OK.');
}
$clearSql = 'UPDATE short_url_settings SET public_base_url = NULL WHERE company_id = ? AND deleted_at IS NULL';
$clearStmt = mysqli_prepare($conn, $clearSql);
if ($clearStmt) {
    mysqli_stmt_bind_param($clearStmt, 'i', $companyId);
    mysqli_stmt_execute($clearStmt);
    mysqli_stmt_close($clearStmt);
}

$testEmp = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'short_url_verify']);
if (empty($testEmp['id'])) {
    su_verify_fail('Could not create disposable test employee.');
    exit(1);
}
$employeeId = (int) $testEmp['id'];

$customCode = 'su' . substr(bin2hex(random_bytes(4)), 0, 6);
$input = [
    'destination_url' => 'https://example.com/verify-short-url',
    'title' => 'Verify short URL',
    'short_code' => $customCode,
    'password' => 'TestPass1',
    'expires_at' => date('d/m/Y', strtotime('+30 days')),
];
$validated = itm_short_url_validate_save($conn, $companyId, $employeeId, $input, 0);
if (!empty($validated['errors'])) {
    su_verify_fail('Validation failed: ' . implode(' ', $validated['errors']));
    exit(1);
}
$passwordHash = password_hash('TestPass1', PASSWORD_DEFAULT);
$newId = itm_short_url_insert_row($conn, $companyId, $employeeId, $validated, $passwordHash);
if ($newId <= 0) {
    su_verify_fail('Insert short_urls failed.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
su_verify_pass('Inserted short_urls id=' . $newId);

$byCode = itm_short_url_fetch_by_code($conn, $customCode);
if (!$byCode || (int) $byCode['id'] !== $newId) {
    su_verify_fail('Fetch by code failed.');
} else {
    su_verify_pass('Fetch by code OK.');
}

if (!password_verify('TestPass1', (string) $byCode['password_hash'])) {
    su_verify_fail('Password hash verify failed.');
} else {
    su_verify_pass('Password hash OK.');
}

itm_short_url_record_click($conn, $byCode);
$refetched = itm_short_url_fetch_by_id($conn, $companyId, $employeeId, $newId);
if ((int) ($refetched['click_count'] ?? 0) < 1) {
    su_verify_fail('Click count not incremented.');
} else {
    su_verify_pass('Click recording OK.');
}

$qrLink = itm_short_url_create_linked_qr($conn, $refetched);
if (empty($qrLink['ok']) || empty($qrLink['qr_code_id'])) {
    su_verify_fail('Linked QR creation failed.');
} else {
    su_verify_pass('Linked QR id=' . (int) $qrLink['qr_code_id']);
}

$qrRow = itm_qr_generator_fetch_by_id($conn, $companyId, $employeeId, (int) $qrLink['qr_code_id']);
if (!$qrRow || (int) ($qrRow['short_url_id'] ?? 0) !== $newId) {
    su_verify_fail('QR short_url_id back-link missing.');
} else {
    su_verify_pass('QR short_url_id back-link OK.');
}

$payload = itm_qr_generator_decode_json_field($qrRow['payload_json'] ?? '');
if (strpos((string) ($payload['url'] ?? ''), '/go.php') === false) {
    su_verify_fail('QR payload should encode short public URL.');
} else {
    su_verify_pass('QR payload encodes short URL.');
}

itm_qr_generator_record_scan($conn, $qrRow);
$scanCount = itm_short_url_fetch_linked_qr_scan_count($conn, $companyId, $employeeId, $newId);
if ($scanCount < 1) {
    su_verify_fail('Linked QR scan count should increment after record_scan.');
} else {
    su_verify_pass('Linked QR scan count OK (' . $scanCount . ').');
}

$qrShort = itm_short_url_create_from_destination($conn, $companyId, $employeeId, 'https://example.com/qr-shorten-chain', ['title' => 'QR chain test']);
if (empty($qrShort['ok'])) {
    su_verify_fail('create_from_destination failed.');
} else {
    su_verify_pass('create_from_destination OK.');
}

$expiredCode = 'exp' . substr(bin2hex(random_bytes(3)), 0, 5);
$expInput = [
    'destination_url' => 'https://example.com/expired',
    'short_code' => $expiredCode,
    'expires_at' => date('d/m/Y', strtotime('-2 days')),
];
$expValidated = itm_short_url_validate_save($conn, $companyId, $employeeId, $expInput, 0);
$expId = itm_short_url_insert_row($conn, $companyId, $employeeId, $expValidated, null);
$expRow = itm_short_url_fetch_by_id($conn, $companyId, $employeeId, $expId);
if (!itm_short_url_is_expired($expRow)) {
    su_verify_fail('Expired link detection failed.');
} else {
    su_verify_pass('Expired link detection OK.');
}

mysqli_query($conn, 'DELETE FROM short_url_clicks WHERE short_url_id IN (' . (int) $newId . ',' . (int) $expId . ',' . (int) ($qrShort['id'] ?? 0) . ')');
mysqli_query($conn, 'DELETE FROM qr_codes WHERE employee_id = ' . (int) $employeeId . ' AND company_id = ' . (int) $companyId);
mysqli_query($conn, 'DELETE FROM short_urls WHERE employee_id = ' . (int) $employeeId . ' AND company_id = ' . (int) $companyId);

itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo colorText('Short URL verification failed (' . $failures . ').', 'fail') . $nl;
    exit(1);
}

echo colorText('Short URL verification passed.', 'pass') . $nl;
exit(0);
