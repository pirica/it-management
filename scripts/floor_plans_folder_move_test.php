<?php
/**
 * Regression tests for Floor Plans folder reparenting (move folder).
 *
 * Usage:
 *   php scripts/floor_plans_folder_move_test.php
 *
 * Optional env:
 *   ITM_DB_HOST, ITM_DB_USER, ITM_DB_PASS, ITM_DB_NAME
 *   ITM_TEST_COMPANY_ID (default: 1)
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/config/config.php';
require $projectRoot . '/modules/floor_plans/gallery_helpers.php';

function fp_test_out(string $message): void
{
    echo $message . PHP_EOL;
}

function fp_test_pass(string $message): void
{
    fp_test_out('[PASS] ' . $message);
}

function fp_test_fail(string $message): void
{
    throw new RuntimeException('[FAIL] ' . $message);
}

function fp_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fp_test_fail($message);
    }
    fp_test_pass($message);
}

function fp_test_db_parent_id(mysqli $conn, int $folderId, int $companyId): ?int
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

function fp_test_insert_folder(mysqli $conn, int $companyId, ?int $parentId, string $name): int
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

function fp_test_delete_folder(mysqli $conn, int $folderId, int $companyId): void
{
    $stmt = mysqli_prepare($conn, 'DELETE FROM floor_plan_folders WHERE id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$failures = 0;

fp_test_out('Floor Plans folder move regression');
fp_test_out('Company ID: ' . $companyId);

try {
    fp_test_assert(fp_floor_plan_schema_ready($conn), 'Floor Plans schema is installed');

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
    fp_test_assert($err === '__NOOP__', 'no-op move to same parent returns __NOOP__');

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
    $dupTopRes = mysqli_query($conn, "SELECT id FROM floor_plan_folders WHERE company_id={$companyId} AND name='" . mysqli_real_escape_string($conn, $dupName) . "' LIMIT 1");
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
    exit(1);
}

fp_test_out('');
fp_test_out('Result: OK');
exit(0);
