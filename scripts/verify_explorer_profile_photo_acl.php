<?php
/**
 * Regression: Explorer profile photo ACL in file.php.
 *
 * Same-tenant peers may read Private/{user}/profile/ thumbnails; cross-tenant
 * readers without employee_companies grants are denied. Non-profile Private paths
 * stay owner-scoped.
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
require_once ROOT_PATH . 'includes/employee_profile_photo.php';
require_once ROOT_PATH . 'modules/explorer/explorer_vault_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/itm_verify_explorer_file_probe.php';

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
    || stripos($agentNotes, 'itm_employee_has_company_access') === false) {
    vepp_fail('modules/explorer/AGENT_NOTES.md must document tenant-scoped profile photo reads');
} else {
    vepp_pass('AGENT_NOTES documents tenant-scoped profile photo sharing');
}

$fileSourcePath = dirname(__DIR__) . '/modules/explorer/file.php';
$fileSource = is_file($fileSourcePath) ? (string) file_get_contents($fileSourcePath) : '';
if ($fileSource === ''
    || strpos($fileSource, 'emp_profile_photo_request_allowed_for_employee') === false) {
    vepp_fail('file.php must call emp_profile_photo_request_allowed_for_employee() for profile paths');
} else {
    vepp_pass('file.php enforces tenant-scoped profile photo ACL');
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
$profileServePath = 'Private/' . $ownerPrivate . '/profile/vepp-test.png';

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
$profileOutput = itm_verify_explorer_file_probe_run($filePath, $readerSession, [
    'path' => $profileServePath,
]);
if (stripos($profileOutput, 'Access denied to private folder') !== false
    || stripos($profileOutput, 'Access denied.') !== false
    || stripos($profileOutput, 'File not found') !== false) {
    vepp_fail('Same-tenant peer profile photo read blocked');
} elseif (stripos($profileOutput, 'Content-Type: image/png') === false
    && stripos($profileOutput, 'PNG') === false) {
    vepp_fail('Same-tenant peer profile photo read did not return image content');
} else {
    vepp_pass('Same-tenant peer may read another user profile photo');
}

$secretOutput = itm_verify_explorer_file_probe_run($filePath, $readerSession, [
    'path' => 'Private/' . $ownerPrivate . '/vepp-secret.txt',
]);
if (stripos($secretOutput, 'Access denied to private folder') !== false
    || stripos($secretOutput, 'Access denied.') !== false) {
    vepp_pass('Non-profile Private file remains owner-scoped');
} else {
    vepp_fail('Non-profile Private file leaked to peer reader');
}

$foreignCompanyId = 2;
$foreignReader = itm_script_test_employee_create($conn, $foreignCompanyId, [
    'script_slug' => 'verify-explorer-profile-foreign',
]);
if (!is_array($foreignReader)) {
    vepp_fail('Unable to create foreign-tenant reader');
} else {
    itm_script_test_employee_register_teardown($conn, (int) $foreignReader['id'], [], [
        'cleanup' => true,
        'company_id' => $foreignCompanyId,
        'username' => (string) $foreignReader['username'],
    ]);

    $foreignSession = [
        'employee_id' => (int) $foreignReader['id'],
        'company_id' => $foreignCompanyId,
        'username' => (string) $foreignReader['username'],
    ];
    $foreignOutput = itm_verify_explorer_file_probe_run($filePath, $foreignSession, [
        'path' => $profileServePath,
    ]);
    if (stripos($foreignOutput, 'Access denied') !== false) {
        vepp_pass('Cross-tenant profile photo read blocked');
    } elseif (stripos($foreignOutput, 'Content-Type: image/png') !== false
        || stripos($foreignOutput, 'PNG') !== false) {
        vepp_fail('Cross-tenant profile photo read leaked image bytes');
    } else {
        vepp_fail('Cross-tenant profile photo probe returned unexpected body');
    }

    itm_script_test_employee_delete($conn, (int) $foreignReader['id']);
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
