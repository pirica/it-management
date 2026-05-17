<?php
/**
 * Set created_at on every row in every table that has a created_at column.
 *
 * Why: After importing database.sql, DEFAULT CURRENT_TIMESTAMP and replication gaps
 * can leave live rows with import-time created_at values. This script fixes the
 * database in place (unlike normalize_database_sql_created_at.php, which edits the SQL file).
 *
 * Usage:
 *   php scripts/update_all_created_at.php
 *   php scripts/update_all_created_at.php --dry-run
 *   php scripts/update_all_created_at.php --at="2026-01-01 00:00:01"
 *
 * Laragon PHP 7.4 example:
 * C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts/update_all_created_at.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from CLI.\n");
}

define('ITM_CLI_SCRIPT', true);

$targetCreatedAt = '2026-01-01 00:00:01';
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
        $dryRun = true;
        continue;
    }
    if (strpos($arg, '--at=') === 0) {
        $targetCreatedAt = substr($arg, 5);
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php scripts/update_all_created_at.php [--dry-run] [--at=\"2026-01-01 00:00:01\"]\n");
        exit(0);
    }
    fwrite(STDERR, "Unknown argument: {$arg}\n");
    exit(1);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $targetCreatedAt)) {
    fwrite(STDERR, "Invalid --at value; expected YYYY-MM-DD HH:MM:SS.\n");
    exit(1);
}

try {
    require_once dirname(__DIR__) . '/config/config.php';
} catch (Throwable $e) {
    fwrite(STDERR, "Unable to bootstrap application config/db connection: " . $e->getMessage() . "\n");
    exit(1);
}

if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$schemaName = mysqli_real_escape_string($conn, (string) DB_NAME);
$listSql = "
    SELECT `TABLE_NAME` AS `table_name`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = '{$schemaName}'
      AND `COLUMN_NAME` = 'created_at'
    ORDER BY `TABLE_NAME` ASC
";

$columnResult = itm_run_query($conn, $listSql);
if (!$columnResult) {
    fwrite(STDERR, "Unable to enumerate tables with created_at.\n");
    exit(1);
}

$tables = [];
while ($row = mysqli_fetch_assoc($columnResult)) {
    $tableName = isset($row['table_name']) ? (string) $row['table_name'] : '';
    if ($tableName !== '' && itm_is_safe_identifier($tableName)) {
        $tables[] = $tableName;
    }
}
mysqli_free_result($columnResult);

if (!$tables) {
    fwrite(STDOUT, "No tables with created_at were found in schema '" . DB_NAME . "'.\n");
    exit(0);
}

$modeLabel = $dryRun ? 'DRY RUN' : 'UPDATE';
fwrite(STDOUT, "[{$modeLabel}] Setting created_at to {$targetCreatedAt} on " . count($tables) . " table(s) in '" . DB_NAME . "'...\n\n");

$totalAffected = 0;
$errorCount = 0;

foreach ($tables as $tableName) {
    $countSql = "SELECT COUNT(*) AS `row_count` FROM `{$tableName}` WHERE `created_at` IS NULL OR `created_at` <> ?";
    $countStmt = mysqli_prepare($conn, $countSql);
    if (!$countStmt) {
        $errorCount++;
        fwrite(STDOUT, "[ERROR] {$tableName}: unable to prepare count query.\n");
        continue;
    }
    mysqli_stmt_bind_param($countStmt, 's', $targetCreatedAt);
    if (!mysqli_stmt_execute($countStmt)) {
        $errorCount++;
        fwrite(STDOUT, "[ERROR] {$tableName}: count query failed.\n");
        mysqli_stmt_close($countStmt);
        continue;
    }
    $countResult = mysqli_stmt_get_result($countStmt);
    $pendingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $pendingRows = (int) ($countRow['row_count'] ?? 0);
    }
    if ($countResult) {
        mysqli_free_result($countResult);
    }
    mysqli_stmt_close($countStmt);

    if ($pendingRows === 0) {
        fwrite(STDOUT, "[SKIP ] {$tableName}: already normalized (0 rows to change).\n");
        continue;
    }

    if ($dryRun) {
        $totalAffected += $pendingRows;
        fwrite(STDOUT, "[PLAN ] {$tableName}: would update {$pendingRows} row(s).\n");
        continue;
    }

    $updateSql = "UPDATE `{$tableName}` SET `created_at` = ? WHERE `created_at` IS NULL OR `created_at` <> ?";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        $errorCount++;
        fwrite(STDOUT, "[ERROR] {$tableName}: unable to prepare update query.\n");
        continue;
    }
    mysqli_stmt_bind_param($updateStmt, 'ss', $targetCreatedAt, $targetCreatedAt);
    if (!mysqli_stmt_execute($updateStmt)) {
        $errorCount++;
        fwrite(STDOUT, "[ERROR] {$tableName}: update failed — " . mysqli_stmt_error($updateStmt) . "\n");
        mysqli_stmt_close($updateStmt);
        continue;
    }
    $affected = (int) mysqli_stmt_affected_rows($updateStmt);
    mysqli_stmt_close($updateStmt);
    $totalAffected += $affected;
    fwrite(STDOUT, "[OK   ] {$tableName}: updated {$affected} row(s).\n");
}

fwrite(STDOUT, "\n");
if ($dryRun) {
    fwrite(STDOUT, "Dry run complete. {$totalAffected} row(s) would be updated.\n");
} else {
    fwrite(STDOUT, "Done. {$totalAffected} row(s) updated across " . count($tables) . " table(s).");
    if ($errorCount > 0) {
        fwrite(STDOUT, " {$errorCount} table(s) had errors.");
    }
    fwrite(STDOUT, "\n");
}

exit($errorCount > 0 ? 1 : 0);
