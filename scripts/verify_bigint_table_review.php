<?php
/**
 * Live DB review: row counts, max IDs, and column types for BIGINT migration candidates.
 * CLI: php scripts/verify_bigint_table_review.php
 */
declare(strict_types=1);

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

if (!defined('ITM_SCRIPT_NO_AUTH')) {
    define('ITM_SCRIPT_NO_AUTH', true);
}

require_once dirname(__DIR__) . '/config/config.php';

$tables = [
    'audit_logs',
    'modules_registry',
    'company_module_access',
    'company_module_share',
    'employee_sidebar_preferences',
    'system_access',
];

$schema = DB_NAME;
$fail = false;

function bigint_review_line(string $label, bool $ok, string $detail = ''): void
{
    $prefix = $ok ? '[PASS]' : '[FAIL]';
    echo $prefix . ' ' . $label;
    if ($detail !== '') {
        echo ' — ' . $detail;
    }
    echo "\n";
}

echo "Database: {$schema}\n\n";
echo "=== ROW COUNTS / MAX IDs ===\n";

foreach ($tables as $table) {
    $res = itm_run_query($conn, 'SELECT COUNT(*) AS c, MAX(`id`) AS max_id FROM `' . $conn->real_escape_string($table) . '`');
    if ($res === false) {
        bigint_review_line($table, false, 'count query failed');
        $fail = true;
        continue;
    }
    $row = $res->fetch_assoc();
    $detail = 'rows=' . (int)($row['c'] ?? 0) . ', max_id=' . ($row['max_id'] ?? 'NULL');

    if ($table === 'audit_logs') {
        $res2 = itm_run_query($conn, 'SELECT MAX(`record_id`) AS max_record_id FROM `audit_logs`');
        if ($res2 !== false) {
            $row2 = $res2->fetch_assoc();
            $detail .= ', max_record_id=' . ($row2['max_record_id'] ?? 'NULL');
        }
    }
    if ($table === 'company_module_access' || $table === 'company_module_share') {
        $res2 = itm_run_query($conn, 'SELECT MAX(`module_id`) AS max_module_id FROM `' . $conn->real_escape_string($table) . '`');
        if ($res2 !== false) {
            $row2 = $res2->fetch_assoc();
            $detail .= ', max_module_id=' . ($row2['max_module_id'] ?? 'NULL');
        }
    }

    bigint_review_line($table, true, $detail);
}

echo "\n=== COLUMN TYPES (id / record_id / module_id) ===\n";

$inList = "'" . implode("','", array_map(static function ($t) use ($conn) {
    return $conn->real_escape_string($t);
}, $tables)) . "'";

$sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "'
          AND TABLE_NAME IN ({$inList})
          AND COLUMN_NAME IN ('id', 'record_id', 'module_id')
        ORDER BY TABLE_NAME, ORDINAL_POSITION";

$res = itm_run_query($conn, $sql);
if ($res === false) {
    bigint_review_line('information_schema column probe', false);
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'] . ' => ' . $row['COLUMN_TYPE'];
    if (!empty($row['EXTRA'])) {
        echo ' (' . $row['EXTRA'] . ')';
    }
    echo "\n";
}

echo "\n=== AUTO_INCREMENT ===\n";

$sqlAi = "SELECT TABLE_NAME, AUTO_INCREMENT
          FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "'
            AND TABLE_NAME IN ({$inList})
          ORDER BY TABLE_NAME";

$resAi = itm_run_query($conn, $sqlAi);
if ($resAi === false) {
    bigint_review_line('information_schema auto_increment probe', false);
    exit(1);
}

while ($row = $resAi->fetch_assoc()) {
    echo $row['TABLE_NAME'] . ': AUTO_INCREMENT=' . ($row['AUTO_INCREMENT'] ?? 'NULL') . "\n";
}

echo "\n=== BIGINT MIGRATION GUIDANCE ===\n";
echo "- audit_logs: recommend BIGINT for id + record_id only (append-only, generic record pointer).\n";
echo "- modules_registry + company_module_access/share module_id: only if registry hub id widens (low volume today).\n";
echo "- employee_sidebar_preferences, system_access: INT sufficient at current scale.\n";
echo "- INT max signed: 2147483647\n";

exit($fail ? 1 : 0);
