<?php
/**
 * Database Analyze Helper (CLI)
 *
 * Why: phpMyAdmin "Analyze table" can stop on the first failing table,
 * which hides which table caused the failure and why.
 * This script runs ANALYZE TABLE across base tables and prints per-table results.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from CLI.\n");
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
    SELECT `table_name`
    FROM `information_schema`.`tables`
    WHERE `table_schema` = '{$schemaName}'
      AND `table_type` = 'BASE TABLE'
    ORDER BY `table_name` ASC
";

$tableResult = itm_run_query($conn, $listSql);
if (!$tableResult) {
    fwrite(STDERR, "Unable to enumerate database tables.\n");
    exit(1);
}

$tables = [];
while ($tableRow = mysqli_fetch_assoc($tableResult)) {
    $tableName = isset($tableRow['table_name']) ? (string) $tableRow['table_name'] : '';
    if ($tableName !== '' && itm_is_safe_identifier($tableName)) {
        $tables[] = $tableName;
    }
}
mysqli_free_result($tableResult);

if (!$tables) {
    fwrite(STDOUT, "No base tables were found in schema '" . DB_NAME . "'.\n");
    exit(0);
}

$errorCount = 0;
$warningCount = 0;
$okCount = 0;

fwrite(STDOUT, "Running ANALYZE TABLE on " . count($tables) . " base table(s) in schema '" . DB_NAME . "'...\n\n");

foreach ($tables as $tableName) {
    $sql = "ANALYZE TABLE `{$tableName}`";
    $analyzeResult = itm_run_query($conn, $sql);

    if (!$analyzeResult) {
        $errorCount++;
        fwrite(STDOUT, "[ERROR] {$tableName}: query execution failed.\n");
        continue;
    }

    $tableHadIssue = false;
    while ($analyzeRow = mysqli_fetch_assoc($analyzeResult)) {
        $msgType = strtolower((string) ($analyzeRow['Msg_type'] ?? 'status'));
        $msgText = (string) ($analyzeRow['Msg_text'] ?? '');

        if ($msgType === 'error') {
            $errorCount++;
            $tableHadIssue = true;
            fwrite(STDOUT, "[ERROR] {$tableName}: {$msgText}\n");
            if (stripos($msgText, "doesn't exist in engine") !== false) {
                fwrite(
                    STDOUT,
                    "        Hint: php scripts/repair_table_from_schema.php --table={$tableName}\n"
                );
            }
        } elseif ($msgType === 'warning') {
            $warningCount++;
            $tableHadIssue = true;
            fwrite(STDOUT, "[WARN ] {$tableName}: {$msgText}\n");
        }
    }

    mysqli_free_result($analyzeResult);

    if (!$tableHadIssue) {
        $okCount++;
        fwrite(STDOUT, "[OK   ] {$tableName}\n");
    }
}

fwrite(STDOUT, "\nSummary: OK={$okCount}, WARN={$warningCount}, ERROR={$errorCount}\n");

exit($errorCount > 0 ? 1 : 0);
