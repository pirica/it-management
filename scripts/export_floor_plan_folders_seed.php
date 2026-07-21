<?php
/**
 * Export floor_plan_folders rows as db/02_data.sql-style INSERT statements.
 *
 * Browser: open scripts/export_floor_plan_folders_seed.php?company=1 (read-only dump).
 * CLI: php scripts/export_floor_plan_folders_seed.php [--company=1]
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_access_helpers.php';

$isCli = itm_script_access_is_cli();
if ($isCli) {
    if (!defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
    itm_script_output_begin('Export floor_plan_folders seed');
}

$nl = itm_script_output_nl();

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo 'Database connection failed.' . $nl;
    if (!$isCli) {
        itm_script_output_end();
    }
    exit(2);
}

$companyFilter = 0;
if ($isCli) {
    foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $arg) {
        if (preg_match('/^--company=(\d+)$/', $arg, $m)) {
            $companyFilter = (int)$m[1];
            continue;
        }
        echo "Unknown option: {$arg}" . $nl;
        if (!$isCli) {
            itm_script_output_end();
        }
        exit(2);
    }
} elseif (isset($_GET['company']) && (string)$_GET['company'] !== '') {
    $companyFilter = (int)$_GET['company'];
}

$sql = 'SELECT `id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at` FROM `floor_plan_folders`';
if ($companyFilter > 0) {
    $sql .= ' WHERE `company_id` = ' . $companyFilter;
}
$sql .= ' ORDER BY `company_id`, `id`';

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo 'Query failed: ' . mysqli_error($conn) . $nl;
    if (!$isCli) {
        itm_script_output_end();
    }
    exit(2);
}

$rowCount = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $id = (int)($row['id'] ?? 0);
    $companyId = (int)($row['company_id'] ?? 0);
    $parentRaw = $row['parent_folder_id'] ?? null;
    $parentSql = $parentRaw === null || $parentRaw === '' ? 'NULL' : (string)(int)$parentRaw;
    $name = mysqli_real_escape_string($conn, (string)($row['name'] ?? ''));
    $active = (int)($row['active'] ?? 1);
    $createdAt = mysqli_real_escape_string($conn, (string)($row['created_at'] ?? '2026-01-01 00:00:01'));

    echo "INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) "
        . "VALUES ('{$id}', '{$companyId}', {$parentSql}, '{$name}', '{$active}', '{$createdAt}');" . $nl;
    $rowCount++;
}
mysqli_free_result($res);

if ($rowCount === 0) {
    echo 'No rows in floor_plan_folders' . ($companyFilter > 0 ? " for company_id={$companyFilter}" : '') . '.' . $nl;
    if (!$isCli) {
        itm_script_output_end();
    }
    exit(1);
}

echo ($isCli ? "Exported {$rowCount} row(s) to stdout." : "Exported {$rowCount} row(s).") . $nl;
if (!$isCli) {
    itm_script_output_end();
}
exit(0);
