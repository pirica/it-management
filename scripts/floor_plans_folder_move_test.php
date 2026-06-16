<?php
/**
 * Regression tests for Floor Plans folder create and reparenting (move folder).
 *
 * Usage (Laragon PHP 7.4+):
 *   php scripts/floor_plans_folder_move_test.php
 *
 * Browser: open this script URL for a plain-language pass/fail log.
 *
 * Optional env:
 *   ITM_DB_HOST, ITM_DB_USER, ITM_DB_PASS, ITM_DB_NAME
 *   ITM_TEST_COMPANY_ID (default: 1)
 */

if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    fwrite(STDERR, "This script requires PHP 7.1 or newer (nullable return types).\n");
    fwrite(STDERR, 'Your CLI reports PHP ' . PHP_VERSION . ". Use Laragon's PHP 7.4 binary.\n");
    exit(1);
}

define('ITM_CLI_SCRIPT', true);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require $projectRoot . '/modules/floor_plans/gallery_helpers.php';

function fp_test_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function fp_test_eol(): string
{
    return itm_script_output_nl();
}

function fp_test_esc(string $text): string
{
    return fp_test_is_cli() ? $text : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function fp_test_out($message)
{
    echo itm_script_format_status_line(fp_test_esc((string)$message)) . fp_test_eol();
    if (!fp_test_is_cli() && function_exists('flush')) {
        @flush();
    }
}

function fp_test_browser_init(): void
{
    if (fp_test_is_cli()) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    require_once __DIR__ . '/lib/script_browser_nav.php';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Floor Plans folder move test</title></head>'
        . '<body style="font-family:Segoe UI,system-ui,sans-serif;line-height:1.45;margin:16px;max-width:920px;">';
    itm_script_browser_nav_echo();
    echo '<p style="color:#57606a;margin:0 0 14px;">Regression for '
        . itm_script_format_module_link('floor_plans', '', 'Floor Plans module')
        . ' · table <code>floor_plan_folders</code> (column <code>parent_folder_id</code>, helpers <code>fp_fetch_folders</code> / <code>fp_move_folder_to_parent</code>).</p>';
}

function fp_test_browser_close(): void
{
    if (!fp_test_is_cli()) {
        echo '</body></html>';
    }
}

function fp_test_pass($message)
{
    fp_test_out('[PASS] ' . $message);
}

function fp_test_fail($message)
{
    throw new RuntimeException('[FAIL] ' . $message);
}

function fp_test_assert($condition, $message)
{
    if (!$condition) {
        fp_test_fail($message);
    }
    fp_test_pass($message);
}

/**
 * @return int|null
 */
function fp_test_db_parent_id(mysqli $conn, $folderId, $companyId)
{
    $stmt = mysqli_prepare($conn, 'SELECT parent_folder_id FROM floor_plan_folders WHERE id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
    mysqli_stmt_close($stmt);
    if ($row === null) {
        return null;
    }
    return fp_folder_parent_id_from_db_value($row['parent_folder_id'] ?? null);
}

function fp_test_insert_folder(mysqli $conn, $companyId, $parentId, $name)
{
    if ($parentId === null || $parentId <= 0) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plan_folders (company_id, parent_folder_id, name, active) VALUES (?, NULL, ?, 1)');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plan_folders (company_id, parent_folder_id, name, active) VALUES (?, ?, ?, 1)');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iis', $companyId, $parentId, $name);
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function fp_test_delete_folder(mysqli $conn, $folderId, $companyId)
{
    $stmt = mysqli_prepare($conn, 'DELETE FROM floor_plan_folders WHERE id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function fp_test_column_exists(mysqli $conn, string $column): bool
{
    if (!itm_is_safe_identifier($column)) {
        return false;
    }
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `floor_plan_folders` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
    return ($res && mysqli_num_rows($res) > 0);
}

fp_test_browser_init();

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$failures = 0;

fp_test_out('Floor Plans folder create + move regression');
fp_test_out('PHP ' . PHP_VERSION);
fp_test_out('Company ID: ' . $companyId);

try {
    fp_test_assert(fp_floor_plan_schema_ready($conn), 'Floor Plans schema is installed');
    fp_test_assert(fp_test_column_exists($conn, 'parent_folder_id'), 'floor_plan_folders.parent_folder_id column exists');
    fp_test_assert(!fp_test_column_exists($conn, 'parent_folder_name'), 'legacy parent_folder_name column is absent');

    $suffix = 'itm_create_' . gmdate('YmdHis') . '_' . mt_rand(1000, 9999);
    $rootCreateName = 'Create Root ' . $suffix;
    $childCreateName = 'Create Child ' . $suffix;
    $rootCreateId = fp_test_insert_folder($conn, $companyId, null, $rootCreateName);
    fp_test_assert($rootCreateId > 0, 'create root-level folder via parent_folder_id NULL');
    fp_test_assert(fp_test_db_parent_id($conn, $rootCreateId, $companyId) === null, 'root folder parent is NULL');

    $childCreateId = fp_test_insert_folder($conn, $companyId, $rootCreateId, $childCreateName);
    fp_test_assert($childCreateId > 0, 'create nested folder under parent');
    fp_test_assert(fp_test_db_parent_id($conn, $childCreateId, $companyId) === $rootCreateId, 'nested folder parent matches root id');

    $foldersAfterCreate = fp_fetch_folders($conn, $companyId);
    $createdRootRow = fp_folder_row_by_id($foldersAfterCreate, $rootCreateId);
    $createdChildRow = fp_folder_row_by_id($foldersAfterCreate, $childCreateId);
    fp_test_assert($createdRootRow !== null && (string)($createdRootRow['name'] ?? '') === $rootCreateName, 'fp_fetch_folders returns created root row');
    fp_test_assert($createdChildRow !== null && fp_folder_parent_id_from_row($createdChildRow) === $rootCreateId, 'fp_fetch_folders returns nested parent_folder_id');

    $folders = fp_fetch_folders($conn, $companyId);
    fp_test_assert(fp_can_move_folder_to_parent($folders, 99, null), 'can move to root (synthetic id)');
    fp_test_assert(!fp_can_move_folder_to_parent($folders, 1, 1), 'cannot move folder into itself when id in tree');

    $suffix = 'itm_move_' . gmdate('YmdHis') . '_' . mt_rand(1000, 9999);
    $rootName = 'Test Root ' . $suffix;
    $childName = 'Test Child ' . $suffix;
    $grandName = 'Test Grand ' . $suffix;

    $rootId = fp_test_insert_folder($conn, $companyId, null, $rootName);
    $childId = fp_test_insert_folder($conn, $companyId, $rootId, $childName);
    $grandId = fp_test_insert_folder($conn, $companyId, $childId, $grandName);
    fp_test_assert($rootId > 0 && $childId > 0 && $grandId > 0, 'created temporary folder hierarchy');

    $allFolders = fp_fetch_folders($conn, $companyId);

    fp_test_assert(!fp_can_move_folder_to_parent($allFolders, $rootId, $grandId), 'blocks move into descendant');

    $err = fp_move_folder_to_parent($conn, $companyId, $childId, null, $allFolders);
    fp_test_assert($err === '', 'move nested child to top-level root: ' . $err);
    fp_test_assert(fp_test_db_parent_id($conn, $childId, $companyId) === null, 'child parent is NULL at top level');

    $allFolders = fp_fetch_folders($conn, $companyId);
    $err = fp_move_folder_to_parent($conn, $companyId, $childId, null, $allFolders);
    fp_test_assert($err === '__NOOP__', 'no-op when folder already at target parent (same location)');

    $allFolders = fp_fetch_folders($conn, $companyId);
    $err = fp_move_folder_to_parent($conn, $companyId, $childId, $rootId, $allFolders);
    fp_test_assert($err === '', 'move child back under root folder: ' . $err);
    fp_test_assert(fp_test_db_parent_id($conn, $childId, $companyId) === $rootId, 'child parent restored under root folder');

    $allFolders = fp_fetch_folders($conn, $companyId);
    $err = fp_move_folder_to_parent($conn, $companyId, $rootId, $grandId, $allFolders);
    fp_test_assert($err === 'Cannot move a folder into itself or one of its subfolders.', 'rejects cycle move');

    $dupName = 'Dup Top ' . $suffix;
    fp_test_insert_folder($conn, $companyId, null, $dupName);
    $nestedDupId = fp_test_insert_folder($conn, $companyId, $rootId, $dupName);
    fp_test_assert($nestedDupId > 0, 'created nested folder for duplicate-name test');
    $allFolders = fp_fetch_folders($conn, $companyId);
    $err = fp_move_folder_to_parent($conn, $companyId, $nestedDupId, null, $allFolders);
    fp_test_assert(
        $err === 'A folder with that name already exists at the target location.',
        'rejects duplicate folder name at target level'
    );

    fp_test_delete_folder($conn, $nestedDupId, $companyId);
    fp_test_delete_folder($conn, $grandId, $companyId);
    fp_test_delete_folder($conn, $childId, $companyId);
    fp_test_delete_folder($conn, $rootId, $companyId);
    fp_test_delete_folder($conn, $childCreateId, $companyId);
    fp_test_delete_folder($conn, $rootCreateId, $companyId);
    $dupNameEsc = mysqli_real_escape_string($conn, $dupName);
    $dupTopRes = mysqli_query($conn, 'SELECT id FROM floor_plan_folders WHERE company_id=' . (int)$companyId . " AND name='" . $dupNameEsc . "' LIMIT 1");
    if ($dupTopRes && ($dupTopRow = mysqli_fetch_assoc($dupTopRes))) {
        fp_test_delete_folder($conn, (int)$dupTopRow['id'], $companyId);
    }
    fp_test_pass('cleaned up temporary folders');
} catch (Throwable $e) {
    fp_test_out($e->getMessage());
    $failures++;
}

if ($failures > 0) {
    fp_test_out('');
    fp_test_out('Result: FAILED');
    fp_test_browser_close();
    exit(1);
}

fp_test_out('');
fp_test_out('Result: OK');
fp_test_browser_close();
exit(0);
