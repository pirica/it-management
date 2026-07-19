<?php
/**
 * Bookmarks HTML/CSV import regression checks (folder paths, duplicate URL skips, vault).
 *
 * CLI: php scripts/verify_bookmarks_import.php
 * Browser: scripts/verify_bookmarks_import.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/bookmarks/helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Bookmarks Import Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;
$vaultKeyPlain = 'BkmImportVerify-' . bin2hex(random_bytes(4));

function bkm_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function bkm_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * @return mysqli|null
 */
function bkm_verify_conn()
{
    $conn = $GLOBALS['conn'] ?? null;

    return $conn instanceof mysqli ? $conn : null;
}

function bkm_verify_teardown_employee_data($conn, $employeeId, $companyId)
{
    $employeeId = (int)$employeeId;
    $companyId = (int)$companyId;
    if ($employeeId <= 0) {
        return;
    }

    mysqli_query($conn, 'DELETE FROM bookmarks WHERE employee_id = ' . $employeeId . ' AND company_id = ' . $companyId);
    mysqli_query($conn, 'DELETE FROM bookmark_folders WHERE employee_id = ' . $employeeId . ' AND company_id = ' . $companyId);
}

function bkm_verify_count_folders_named($conn, $employeeId, $companyId, $parentId, $plainName)
{
    $employeeId = (int)$employeeId;
    $companyId = (int)$companyId;
    $plainName = trim((string)$plainName);
    $nameHash = bkm_text_hash($plainName);
    if ($parentId === null) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM bookmark_folders WHERE company_id = ? AND employee_id = ? AND active = 1 AND parent_folder_id IS NULL AND name_hash = ?'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $companyId, $employeeId, $nameHash);
    } else {
        $parentId = (int)$parentId;
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM bookmark_folders WHERE company_id = ? AND employee_id = ? AND active = 1 AND parent_folder_id = ? AND name_hash = ?'
        );
        mysqli_stmt_bind_param($stmt, 'iiis', $companyId, $employeeId, $parentId, $nameHash);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['c'] ?? 0);
}

function bkm_verify_count_bookmarks_in_folder($conn, $employeeId, $companyId, $folderId)
{
    $employeeId = (int)$employeeId;
    $companyId = (int)$companyId;
    $folderId = (int)$folderId;
    if ($folderId <= 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM bookmarks WHERE company_id = ? AND employee_id = ? AND active = 1 AND folder_id IS NULL'
        );
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM bookmarks WHERE company_id = ? AND employee_id = ? AND active = 1 AND folder_id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $folderId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['c'] ?? 0);
}

function bkm_verify_import_sample_html()
{
    $path = __DIR__ . '/data/bookmarks_import_sample.html';
    if (!is_readable($path)) {
        return '';
    }

    return (string)file_get_contents($path);
}

$conn = bkm_verify_conn();
if (!$conn) {
    bkm_verify_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$sampleHtml = bkm_verify_import_sample_html();
if ($sampleHtml === '') {
    bkm_verify_fail('Missing sample file scripts/data/bookmarks_import_sample.html');
    itm_script_output_end();
    exit(1);
}
bkm_verify_pass('Sample HTML fixture found (scripts/data/bookmarks_import_sample.html).');

$entries = bkm_parse_html_bookmark_entries($sampleHtml);
if (count($entries) < 3) {
    bkm_verify_fail('Sample HTML parser expected at least 3 bookmark entries, got ' . count($entries));
} else {
    bkm_verify_pass('HTML parser returned ' . count($entries) . ' bookmark entries (L1/L2 nested paths).');
}

$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-bookmarks-import']);
if (!is_array($actor) || (int)($actor['id'] ?? 0) <= 0) {
    bkm_verify_fail('Could not create disposable script test employee.');
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);
bkm_verify_teardown_employee_data($conn, $employeeId, $companyId);

$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = $employeeId;
$_SESSION['vault_key'] = hash('sha256', $vaultKeyPlain);

$folderCache = [];
$foldersCreated = 0;
$importedUrlKeys = [];
$importedCount = 0;

foreach ($entries as $entry) {
    $result = bkm_try_import_html_bookmark(
        $conn,
        $companyId,
        $employeeId,
        $entry['folder_path'],
        null,
        $folderCache,
        $foldersCreated,
        $entry['title'],
        $entry['url'],
        $entry['notes'],
        $importedUrlKeys
    );
    if (!empty($result['imported'])) {
        $importedCount++;
    }
}

if ($importedCount !== 3) {
    bkm_verify_fail('First import pass expected 3 imported rows, got ' . $importedCount);
} else {
    bkm_verify_pass('First import pass imported 3 bookmarks.');
}

$l1Id = bkm_find_folder_id_by_name($conn, $companyId, $employeeId, null, 'L1');
$l2Id = $l1Id ? bkm_find_folder_id_by_name($conn, $companyId, $employeeId, $l1Id, 'L2') : null;
if ($l1Id === null || $l2Id === null) {
    bkm_verify_fail('Could not resolve imported folder path L1/L2 by name.');
} else {
    bkm_verify_pass('Resolved folders L1 (#' . $l1Id . ') and L2 (#' . $l2Id . ').');
}

$l2BookmarkCount = bkm_verify_count_bookmarks_in_folder($conn, $employeeId, $companyId, $l2Id);
if ($l2BookmarkCount !== 2) {
    bkm_verify_fail('Folder L2 expected 2 bookmarks after import, found ' . $l2BookmarkCount);
} else {
    bkm_verify_pass('Folder L2 contains 2 bookmarks (Import A + Import B).');
}

$l1OnlyCount = bkm_verify_count_bookmarks_in_folder($conn, $employeeId, $companyId, $l1Id);
if ($l1OnlyCount !== 1) {
    bkm_verify_fail('Folder L1 expected 1 direct bookmark (Import L1 only), found ' . $l1OnlyCount);
} else {
    bkm_verify_pass('Folder L1 contains 1 direct bookmark (Import L1 only).');
}

$l2FolderDupes = bkm_verify_count_folders_named($conn, $employeeId, $companyId, $l1Id, 'L2');
if ($l2FolderDupes !== 1) {
    bkm_verify_fail('Expected exactly one L2 folder under L1, found ' . $l2FolderDupes);
} else {
    bkm_verify_pass('Exactly one L2 folder exists under L1 (no duplicate folder rows).');
}

$duplicateKeys = [];
$duplicateResult = bkm_try_import_html_bookmark(
    $conn,
    $companyId,
    $employeeId,
    ['L1', 'L2'],
    null,
    $folderCache,
    $foldersCreated,
    'Import A duplicate',
    'https://itm-verify.example/import-a',
    '',
    $duplicateKeys
);
if (!empty($duplicateResult['imported']) || ($duplicateResult['skip_reason'] ?? '') !== 'duplicate_employee') {
    bkm_verify_fail('Duplicate employee URL should skip with duplicate_employee (got ' . ($duplicateResult['skip_reason'] ?? 'none') . ').');
} else {
    bkm_verify_pass('Duplicate URL skip does not create orphan folders (precheck before folder path).');
}

$l2FolderDupesAfterSkip = bkm_verify_count_folders_named($conn, $employeeId, $companyId, $l1Id, 'L2');
$l2BookmarksAfterSkip = bkm_verify_count_bookmarks_in_folder($conn, $employeeId, $companyId, $l2Id);
if ($l2FolderDupesAfterSkip !== 1 || $l2BookmarksAfterSkip !== 2) {
    bkm_verify_fail('After duplicate skip, L2 folder/bookmark counts changed unexpectedly.');
} else {
    bkm_verify_pass('After duplicate URL skip, L2 still has 2 bookmarks and no extra folder rows.');
}

$csvKeys = [];
$csvResult = bkm_try_import_bookmark(
    $conn,
    $companyId,
    $employeeId,
    $l2Id,
    'CSV Target',
    'https://itm-verify.example/csv-target',
    'csv notes',
    $csvKeys
);
if (empty($csvResult['imported'])) {
    bkm_verify_fail('CSV-style import into folder L2 failed.');
} elseif (bkm_verify_count_bookmarks_in_folder($conn, $employeeId, $companyId, $l2Id) !== 3) {
    bkm_verify_fail('CSV import did not land in folder L2.');
} else {
    bkm_verify_pass('CSV-style flat import lands in the resolved L2 folder.');
}

bkm_verify_teardown_employee_data($conn, $employeeId, $companyId);
$folderCache = [];
$foldersCreated = 0;
$importedUrlKeys = [];

$preseedUrl = 'https://itm-verify.example/preseed-dup';
$preseed = bkm_insert_bookmark_row($conn, $companyId, $employeeId, null, 'Preseed', $preseedUrl, '', 0, 1);
if (empty($preseed['ok'])) {
    bkm_verify_fail('Could not preseed bookmark for orphan-folder test.');
} else {
    $orphanResult = bkm_try_import_html_bookmark(
        $conn,
        $companyId,
        $employeeId,
        ['L1', 'L2'],
        null,
        $folderCache,
        $foldersCreated,
        'Should not create folders',
        $preseedUrl,
        '',
        $importedUrlKeys
    );
    if (!empty($orphanResult['imported']) || ($orphanResult['skip_reason'] ?? '') !== 'duplicate_employee') {
        bkm_verify_fail('Preseed duplicate import should skip before folder creation.');
    } elseif ($foldersCreated > 0) {
        bkm_verify_fail('Duplicate import created ' . $foldersCreated . ' folder(s) — orphan folder bug.');
    } elseif (bkm_verify_count_folders_named($conn, $employeeId, $companyId, null, 'L1') > 0) {
        bkm_verify_fail('Duplicate import created L1 folder without importing a bookmark.');
    } else {
        bkm_verify_pass('Duplicate URL against preseeded bookmark does not create empty L1/L2 folders.');
    }
}

bkm_verify_teardown_employee_data($conn, $employeeId, $companyId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo colorText('Verification finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All bookmarks import checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
