<?php
/**
 * Regression: ui_configuration integration API keys stored as SHA-256 hash + prefix only.
 *
 * CLI: php scripts/verify_api_key_hashing.php
 * Browser: scripts/verify_api_key_hashing.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_api_key_hashing.php</code> — exit <code>1</code> on failure. Browser: <a href="verify_api_key_hashing.php?run=1">verify_api_key_hashing.php?run=1</a> (Administrator).
<p>Verifies hash/prefix helpers, lookup by <code>X-API-Key</code>/POST (not query), Settings save path, and rejects query-string keys.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_api_tier_test_helpers.php';

itm_script_output_begin('API key hashing verification');

$fail = 0;
function api_key_hash_fail($msg)
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function api_key_hash_pass($msg)
{
    echo "[PASS] {$msg}\n";
}

$sampleKey = 'apitest-hash-' . bin2hex(random_bytes(8));
$prefix = itm_api_key_prefix($sampleKey);
$hash = itm_api_hash_api_key($sampleKey);

if (strlen($prefix) === 16 && $prefix === substr($sampleKey, 0, 16)) {
    api_key_hash_pass('api_key_prefix length');
} else {
    api_key_hash_fail('api_key_prefix length');
}

if (strlen($hash) === 64 && preg_match('/^[a-f0-9]{64}$/', $hash)) {
    api_key_hash_pass('api_key_hash is SHA-256 hex');
} else {
    api_key_hash_fail('api_key_hash is SHA-256 hex');
}

if (itm_api_verify_api_key($sampleKey, $hash) && !itm_api_verify_api_key($sampleKey . 'x', $hash)) {
    api_key_hash_pass('api_key verify');
} else {
    api_key_hash_fail('api_key verify');
}

$_GET['api_key'] = $sampleKey;
$_POST = [];
unset($_SERVER['HTTP_X_API_KEY']);
if (itm_api_extract_request_key() === '') {
    api_key_hash_pass('query api_key ignored');
} else {
    api_key_hash_fail('query api_key ignored');
}
unset($_GET['api_key']);

$_POST['api_key'] = $sampleKey;
if (itm_api_extract_request_key() === $sampleKey) {
    api_key_hash_pass('POST api_key accepted');
} else {
    api_key_hash_fail('POST api_key accepted');
}
$_POST = [];

$_SERVER['HTTP_X_API_KEY'] = $sampleKey;
if (itm_api_extract_request_key() === $sampleKey) {
    api_key_hash_pass('X-API-Key header accepted');
} else {
    api_key_hash_fail('X-API-Key header accepted');
}
unset($_SERVER['HTTP_X_API_KEY']);

if (function_exists('itm_table_has_column') && itm_table_has_column($conn, 'ui_configuration', 'api_key_hash')) {
    api_key_hash_pass('ui_configuration.api_key_hash column');
} else {
    api_key_hash_fail('ui_configuration.api_key_hash column missing — apply db/migrations/ui_configuration_api_key_hash.sql');
}

if (function_exists('itm_table_has_column') && itm_table_has_column($conn, 'ui_configuration', 'api_key_prefix')) {
    api_key_hash_pass('ui_configuration.api_key_prefix column');
} else {
    api_key_hash_fail('ui_configuration.api_key_prefix column missing');
}

$companyId = ITM_APITEST_COMPANY_ID;
$employeeId = itm_apitest_disposable_user_id(7);
itm_apitest_cleanup_configuration($conn, $companyId, $employeeId);
if (!itm_apitest_ensure_employee_exists($conn, $companyId, $employeeId)) {
    api_key_hash_fail('disposable employee seed');
} else {
    api_key_hash_pass('disposable employee seed');
}

$seedRow = itm_apitest_seed_configuration($conn, $companyId, $employeeId, 'Basic', ['api_key' => '']);
if (!is_array($seedRow) || ($seedRow['tier'] ?? '') !== 'Basic') {
    api_key_hash_fail('seed Basic tier row for save test');
} else {
    api_key_hash_pass('seed Basic tier row for save test');
}

if (!itm_api_save_user_api_key($conn, $companyId, $employeeId, $sampleKey)) {
    api_key_hash_fail('itm_api_save_user_api_key');
} else {
    api_key_hash_pass('itm_api_save_user_api_key');
}

$stored = itm_api_lookup_configuration_by_user($conn, $companyId, $employeeId);
if (is_array($stored)
    && trim((string)($stored['api_key'] ?? '')) === ''
    && (string)($stored['api_key_prefix'] ?? '') === $prefix
    && (string)($stored['api_key_hash'] ?? '') === $hash) {
    api_key_hash_pass('stored row has hash + prefix only');
} else {
    api_key_hash_fail('stored row has hash + prefix only');
}

$lookup = itm_api_lookup_configuration_by_key($conn, $sampleKey);
if (is_array($lookup) && (int)($lookup['employee_id'] ?? 0) === $employeeId) {
    api_key_hash_pass('lookup by presented key');
} else {
    api_key_hash_fail('lookup by presented key');
}

if (itm_api_lookup_configuration_by_key($conn, $sampleKey . '-wrong') === null) {
    api_key_hash_pass('lookup rejects wrong key');
} else {
    api_key_hash_fail('lookup rejects wrong key');
}

itm_apitest_cleanup_configuration($conn, $companyId, $employeeId);

if ($fail === 0) {
    api_key_hash_pass('API key hashing regression complete');
    itm_script_output_end();
    exit(0);
}

api_key_hash_fail('API key hashing regression failed');
itm_script_output_end();
exit(1);
