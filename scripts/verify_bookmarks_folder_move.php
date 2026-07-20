<?php
/**
 * Bookmarks folder move / merge regression checks.
 *
 * CLI: php scripts/verify_bookmarks_folder_move.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/bookmarks/helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Bookmarks Folder Move Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;
$vaultKeyPlain = 'BkmFolderMove-' . bin2hex(random_bytes(4));

function bkm_folder_move_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function bkm_folder_move_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    bkm_folder_move_verify_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-bookmarks-folder-move']);
if (!is_array($actor) || (int)($actor['id'] ?? 0) <= 0) {
    bkm_folder_move_verify_fail('Could not create disposable script test employee.');
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);
$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = $employeeId;
$_SESSION['vault_key'] = hash('sha256', $vaultKeyPlain);

$folderA = bkm_insert_folder_row($conn, $companyId, $employeeId, null, 'MergeMe', 0, 1);
$folderB = bkm_insert_folder_row($conn, $companyId, $employeeId, null, 'MergeMe', 0, 1);
if (empty($folderA['ok']) || empty($folderB['ok'])) {
    bkm_folder_move_verify_fail('Could not seed duplicate-named folders.');
    itm_script_test_employee_delete($conn, $employeeId);
    itm_script_output_end();
    exit(1);
}

$sourceId = (int)$folderA['id'];
$targetId = (int)$folderB['id'];
$bookmark = bkm_insert_bookmark_row(
    $conn,
    $companyId,
    $employeeId,
    $sourceId,
    'Move test',
    'https://itm-verify.example/folder-move',
    '',
    0,
    1
);
if (empty($bookmark['ok'])) {
    bkm_folder_move_verify_fail('Could not seed bookmark in source folder.');
}

$reparent = bkm_move_folder($conn, $companyId, $employeeId, $sourceId, null, 0, true);
if (empty($reparent['ok'])) {
    bkm_folder_move_verify_fail('Reparent without merge should succeed when duplicate names are allowed.');
}

$sourceAfterReparent = bkm_get_folder_row_by_id($conn, $sourceId, $companyId, $employeeId);
if (!$sourceAfterReparent || $sourceAfterReparent['parent_folder_id'] !== null) {
    bkm_folder_move_verify_fail('Source folder should remain at root after non-merge move.');
} else {
    bkm_folder_move_verify_pass('Non-merge move keeps both same-named folders at root.');
}

$merge = bkm_move_folder($conn, $companyId, $employeeId, $sourceId, null, $targetId, true);
if (empty($merge['ok'])) {
    bkm_folder_move_verify_fail('Merge move failed: ' . ($merge['message'] ?? 'unknown'));
}

$sourceGone = bkm_get_folder_row_by_id($conn, $sourceId, $companyId, $employeeId);
if ($sourceGone !== null) {
    bkm_folder_move_verify_fail('Source folder should be removed after merge.');
}

$targetFolderId = bkm_find_bookmark_folder_id_for_employee_url($conn, $companyId, $employeeId, 'https://itm-verify.example/folder-move');
if ($targetFolderId !== $targetId) {
    bkm_folder_move_verify_fail('Bookmark should land in merge target folder.');
} else {
    bkm_folder_move_verify_pass('Merge moves bookmarks into the existing same-named folder.');
}

mysqli_query($conn, 'DELETE FROM bookmarks WHERE employee_id = ' . $employeeId . ' AND company_id = ' . $companyId);
mysqli_query($conn, 'DELETE FROM bookmark_folders WHERE employee_id = ' . $employeeId . ' AND company_id = ' . $companyId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo colorText('Verification finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All bookmarks folder move checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
