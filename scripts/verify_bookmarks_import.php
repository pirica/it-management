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

$breadcrumbFixture = [
    1 => ['id' => 1, 'name' => 'L1', 'parent_folder_id' => null],
    2 => ['id' => 2, 'name' => 'L2', 'parent_folder_id' => 1],
];
$nestedImportLabel = bkm_format_import_folder_label(['L2'], 1, $breadcrumbFixture);
if ($nestedImportLabel !== 'Root / L1 / L2') {
    bkm_verify_fail('Import folder label expected Root / L1 / L2, got: ' . $nestedImportLabel);
} else {
    bkm_verify_pass('Import folder labels include Root / nested segments.');
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

$hydrateRow = [
    'title' => itm_encrypt('192.168.1.74/UI/', $_SESSION['vault_key']),
    'url' => itm_encrypt('http://192.168.1.74/UI/', $_SESSION['vault_key']),
    'notes' => itm_encrypt('', $_SESSION['vault_key']),
    'shared' => 0,
    'employee_id' => $employeeId,
];
bkm_hydrate_bookmark_row($hydrateRow, $employeeId);
if (!empty($hydrateRow['notes_locked']) || ($hydrateRow['notes_display'] ?? '') === '🔒 Unable to decrypt notes') {
    bkm_verify_fail('Encrypted empty private notes must hydrate as blank, not "Unable to decrypt".');
} else {
    bkm_verify_pass('Encrypted empty private notes hydrate without false decrypt failure.');
}

$folderCache = [];
$foldersCreated = 0;
$importedUrlKeys = [];
$importedCount = 0;

foreach ($entries as $entry) {
    $importFolderLabel = bkm_format_import_folder_label($entry['folder_path'], null, []);
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
        $importFolderLabel,
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

$foldersById = [];
foreach (bkm_get_folders($conn, $companyId, $employeeId, false) as $folderRow) {
    $foldersById[(int)$folderRow['id']] = $folderRow;
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
$dupImportLabel = bkm_format_import_folder_label(['L1', 'L2'], null, $foldersById);
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
    $dupImportLabel,
    $duplicateKeys
);
if (!empty($duplicateResult['imported']) || ($duplicateResult['skip_reason'] ?? '') !== 'duplicate_employee') {
    bkm_verify_fail('Duplicate employee URL should skip with duplicate_employee (got ' . ($duplicateResult['skip_reason'] ?? 'none') . ').');
} else {
    bkm_verify_pass('Duplicate URL skip does not create orphan folders (precheck before folder path).');
}

$existingDupLabel = bkm_resolve_import_duplicate_folder_label(
    'duplicate_employee',
    'https://itm-verify.example/import-a',
    $dupImportLabel,
    $duplicateKeys,
    $conn,
    $companyId,
    $employeeId,
    $foldersById
);
if ($existingDupLabel !== 'Root / L1 / L2') {
    bkm_verify_fail('Duplicate employee skip should show existing folder Root / L1 / L2, got: ' . $existingDupLabel);
} else {
    bkm_verify_pass('Duplicate employee skip reports full existing folder path.');
}

$fileDupKeys = [];
$fileDupUrl = 'https://itm-verify.example/dup-in-file';
$fileDupLabelFirst = bkm_format_import_folder_label(['L1'], null, $foldersById);
$fileDupFirst = bkm_try_import_html_bookmark(
    $conn,
    $companyId,
    $employeeId,
    ['L1'],
    null,
    $folderCache,
    $foldersCreated,
    'Dup in file first',
    $fileDupUrl,
    '',
    $fileDupLabelFirst,
    $fileDupKeys
);
$fileDupLabelSecond = bkm_format_import_folder_label(['L2'], null, $foldersById);
$fileDupSecond = bkm_try_import_html_bookmark(
    $conn,
    $companyId,
    $employeeId,
    ['L2'],
    null,
    $folderCache,
    $foldersCreated,
    'Dup in file second',
    $fileDupUrl,
    '',
    $fileDupLabelSecond,
    $fileDupKeys
);
if (empty($fileDupFirst['imported']) || ($fileDupSecond['skip_reason'] ?? '') !== 'duplicate_file') {
    bkm_verify_fail('In-file duplicate URL test failed setup.');
} else {
    $fileDupReportLabel = bkm_resolve_import_duplicate_folder_label(
        'duplicate_file',
        $fileDupUrl,
        $fileDupLabelSecond,
        $fileDupKeys,
        $conn,
        $companyId,
        $employeeId,
        $foldersById
    );
    if ($fileDupReportLabel !== 'Root / L1') {
        bkm_verify_fail('Duplicate file skip should show first row folder Root / L1, got: ' . $fileDupReportLabel);
    } else {
        bkm_verify_pass('Duplicate file skip reports first occurrence folder path.');
    }
}

$l2FolderDupesAfterSkip = bkm_verify_count_folders_named($conn, $employeeId, $companyId, $l1Id, 'L2');
$l2BookmarksAfterSkip = bkm_verify_count_bookmarks_in_folder($conn, $employeeId, $companyId, $l2Id);
if ($l2FolderDupesAfterSkip !== 1 || $l2BookmarksAfterSkip !== 2) {
    bkm_verify_fail('After duplicate skip, L2 folder/bookmark counts changed unexpectedly.');
} else {
    bkm_verify_pass('After duplicate URL skip, L2 still has 2 bookmarks and no extra folder rows.');
}

$csvKeys = [];
$csvFolderLabel = bkm_format_folder_breadcrumb($l2Id, $foldersById);
$csvResult = bkm_try_import_bookmark(
    $conn,
    $companyId,
    $employeeId,
    $l2Id,
    'CSV Target',
    'https://itm-verify.example/csv-target',
    'csv notes',
    $csvFolderLabel,
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
    $orphanImportLabel = bkm_format_import_folder_label(['L1', 'L2'], null, []);
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
        $orphanImportLabel,
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
