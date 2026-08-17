<?php
/**
 * Regression: intentional Explorer cross-user profile photo read contract.
 *
 * documents/modules/explorer/AGENT_NOTES.md — any authenticated user may read
 * profile folders under Private/{user}/profile/; other Private paths stay owner-scoped.
 *
 * CLI: php scripts/verify_explorer_profile_photo_acl.php
 * Browser: scripts/verify_explorer_profile_photo_acl.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_explorer_profile_photo_acl.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/explorer/file.php</code>, profile photo storage, or Explorer Private ACL.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_cli_binary.php';
require_once ROOT_PATH . 'modules/explorer/explorer_vault_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Explorer profile photo ACL verification');

$nl = itm_script_output_nl();
$failures = 0;

function vepp_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vepp_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$agentNotesPath = dirname(__DIR__) . '/modules/explorer/AGENT_NOTES.md';
$agentNotes = is_file($agentNotesPath) ? (string) file_get_contents($agentNotesPath) : '';
if ($agentNotes === ''
    || stripos($agentNotes, 'Private/*/profile/') === false
    || stripos($agentNotes, 'readable by any authenticated user') === false) {
    vepp_fail('modules/explorer/AGENT_NOTES.md must document intentional cross-user profile photo reads');
} else {
    vepp_pass('AGENT_NOTES documents intentional profile photo sharing');
}

$fileSourcePath = dirname(__DIR__) . '/modules/explorer/file.php';
$fileSource = is_file($fileSourcePath) ? (string) file_get_contents($fileSourcePath) : '';
if ($fileSource === ''
    || strpos($fileSource, '$isEmployeeProfilePhotoPath') === false
    || strpos($fileSource, 'Private/[^/]+/profile/') === false) {
    vepp_fail('file.php must detect Private/*/profile/ paths before owner-only Private checks');
} else {
    vepp_pass('file.php implements profile photo path bypass for owner ACL');
}

if (!explorer_path_is_profile_storage('Private/Admin_1/profile/photo.png')) {
    vepp_fail('explorer_path_is_profile_storage() must match profile asset paths');
} else {
    vepp_pass('Profile storage paths recognized by vault helper');
}

if (explorer_path_requires_vault_unlock('Private/Admin_1/profile/photo.png', 'Admin_1')) {
    vepp_fail('Profile photos must not require vault unlock');
} else {
    vepp_pass('Profile photos exempt from vault gate');
}

if (!explorer_path_requires_vault_unlock('Private/Admin_1/secret.txt', 'Admin_1')) {
    vepp_fail('Non-profile Private paths must still require vault unlock');
} else {
    vepp_pass('Non-profile Private paths still vault-gated');
}

/**
 * @return string
 */
function vepp_run_file_request($scriptPath, array $sessionData, array $getData = [])
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $scriptPath = str_replace('\\', '/', (string) $scriptPath);
    $configPath = str_replace('\\', '/', realpath(dirname(__DIR__) . '/config/config.php') ?: '');
    if ($configPath === '' || !is_file($scriptPath)) {
        return '';
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'explorer_profile');
    if ($tmpFile === false) {
        return '';
    }

    $repoRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', dirname($repoRoot));
    $scriptName = '/it-management/modules/explorer/file.php';

    $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = \'GET\';
$_SERVER[\'REMOTE_ADDR\'] = \'127.0.0.1\';
$_SERVER[\'HTTP_HOST\'] = \'localhost\';
$_SERVER[\'SCRIPT_NAME\'] = ' . var_export($scriptName, true) . ';
$_SERVER[\'PHP_SELF\'] = ' . var_export($scriptName, true) . ';
$_SERVER[\'SCRIPT_FILENAME\'] = ' . var_export($scriptPath, true) . ';
$_SERVER[\'DOCUMENT_ROOT\'] = ' . var_export($documentRoot, true) . ';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = unserialize(' . var_export(serialize($sessionData), true) . ');
$_GET = ' . var_export($getData, true) . ';
require ' . var_export($configPath, true) . ';
chdir(dirname(' . var_export($scriptPath, true) . '));
ob_start();
include basename(' . var_export($scriptPath, true) . ');
echo ob_get_clean();
';

    file_put_contents($tmpFile, $code);
    $phpBin = itm_resolve_cli_php_binary();
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($phpBin) . $phpIni . ' ' . escapeshellarg($tmpFile) . ' 2>&1');
    @unlink($tmpFile);

    return is_string($output) ? $output : '';
}

$companyId = 1;
$owner = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-explorer-profile-owner']);
$reader = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-explorer-profile-reader']);
if (!is_array($owner) || !is_array($reader)) {
    vepp_fail('Unable to create disposable Explorer test employees');
    itm_script_output_end();
    exit(1);
}

itm_script_test_employee_register_teardown($conn, (int) $owner['id'], [], [
    'cleanup' => true,
    'company_id' => $companyId,
    'username' => (string) $owner['username'],
]);
itm_script_test_employee_register_teardown($conn, (int) $reader['id'], [], [
    'cleanup' => true,
    'company_id' => $companyId,
    'username' => (string) $reader['username'],
]);

$ownerPrivate = (string) $owner['username'] . '_' . (int) $owner['id'];
$profileDir = ROOT_PATH . 'files/' . $companyId . '/Private/' . $ownerPrivate . '/profile';
$profileFile = $profileDir . '/vepp-test.png';
$secretFile = ROOT_PATH . 'files/' . $companyId . '/Private/' . $ownerPrivate . '/vepp-secret.txt';

if (function_exists('itm_ensure_files_storage_directory')) {
    itm_ensure_files_storage_directory($profileDir);
} elseif (!is_dir($profileDir)) {
    @mkdir($profileDir, 0755, true);
}

@file_put_contents($profileFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
@file_put_contents($secretFile, 'secret');

$readerSession = [
    'employee_id' => (int) $reader['id'],
    'company_id' => $companyId,
    'username' => (string) $reader['username'],
];

$filePath = dirname(__DIR__) . '/modules/explorer/file.php';
$profileOutput = vepp_run_file_request($filePath, $readerSession, [
    'path' => 'Private/' . $ownerPrivate . '/profile/vepp-test.png',
]);
if (stripos($profileOutput, 'Access denied to private folder') !== false
    || stripos($profileOutput, 'File not found') !== false) {
    vepp_fail('Cross-user profile photo read blocked (expected intentional allow)');
} elseif (stripos($profileOutput, 'Content-Type: image/png') === false
    && stripos($profileOutput, 'PNG') === false) {
    vepp_fail('Cross-user profile photo read did not return image content');
} else {
    vepp_pass('Authenticated peer may read another user profile photo');
}

$secretOutput = vepp_run_file_request($filePath, $readerSession, [
    'path' => 'Private/' . $ownerPrivate . '/vepp-secret.txt',
]);
if (stripos($secretOutput, 'Access denied to private folder') !== false) {
    vepp_pass('Non-profile Private file remains owner-scoped');
} else {
    vepp_fail('Non-profile Private file leaked to peer reader');
}

@unlink($profileFile);
@unlink($secretFile);

if ($failures > 0) {
    echo colorText('SUMMARY: Explorer profile photo ACL checks failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: Explorer profile photo ACL checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
