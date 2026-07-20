<?php
/**
 * Export floor_plan_folders rows as db/02_data.sql-style INSERT statements.
 *
 * Why: folder seed data is tenant-specific; run this against your local Laragon DB
 * and paste the output into db/ after the floor_plan_folders CREATE TABLE block.
 *
 * Usage (repository root, PHP 7.4+ with MySQLi):
 *   php scripts/export_floor_plan_folders_seed.php
 *   php scripts/export_floor_plan_folders_seed.php --company=1
 *
 * Windows Laragon when php is not on PATH:
 *   C:\<folder>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\export_floor_plan_folders_seed.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:720px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> Exports INSERT statements to stdout:</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/export_floor_plan_folders_seed.php --company=1</pre>';
    echo '</body></html>';
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(2);
}

$companyFilter = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--company=(\d+)$/', $arg, $m)) {
        $companyFilter = (int)$m[1];
        continue;
    }
    fwrite(STDERR, "Unknown option: {$arg}\n");
    exit(2);
}

$sql = 'SELECT `id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at` FROM `floor_plan_folders`';
if ($companyFilter > 0) {
    $sql .= ' WHERE `company_id` = ' . $companyFilter;
}
$sql .= ' ORDER BY `company_id`, `id`';

$res = mysqli_query($conn, $sql);
if (!$res) {
    fwrite(STDERR, 'Query failed: ' . mysqli_error($conn) . "\n");
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
        . "VALUES ('{$id}', '{$companyId}', {$parentSql}, '{$name}', '{$active}', '{$createdAt}');\n";
    $rowCount++;
}
mysqli_free_result($res);

if ($rowCount === 0) {
    fwrite(STDERR, "No rows in floor_plan_folders" . ($companyFilter > 0 ? " for company_id={$companyFilter}" : '') . ".\n");
    exit(1);
}

fwrite(STDERR, "Exported {$rowCount} row(s) to stdout.\n");
