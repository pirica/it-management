<?php
/**
 * Benchmark Dashboard Optimization
 * Compares baseline vs optimized query count and timing.
 */
define('ITM_CLI_SCRIPT', true);
require dirname(dirname(dirname(dirname(__DIR__)))) . '/config/config.php';

$companyId = 1;

function get_queries($conn) {
    $res = mysqli_query($conn, "SHOW SESSION STATUS LIKE 'Questions'");
    $row = mysqli_fetch_assoc($res);
    return (int)$row['Value'];
}

echo "Starting benchmark...\n";

// --- BASELINE ---
$start_queries = get_queries($conn);
$start_time = microtime(true);

// Re-defining legacy helpers for the test
function baseline_table_has_column($conn, $table, $column): bool {
    $sql = 'SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $count = (int)(($res ? mysqli_fetch_assoc($res) : [])['count'] ?? 0);
    mysqli_stmt_close($stmt);
    return $count > 0;
}
function baseline_fetch_company_count($conn, $sql, $companyId): int {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $count = (int)(($res ? mysqli_fetch_assoc($res) : [])['count'] ?? 0);
    mysqli_stmt_close($stmt);
    return $count;
}

$equipmentSql = 'SELECT COUNT(*) AS count FROM equipment WHERE company_id = ?';
if (baseline_table_has_column($conn, 'equipment', 'active')) { $equipmentSql .= ' AND active = 1'; }
$c1 = baseline_fetch_company_count($conn, $equipmentSql, $companyId);
$c2 = baseline_fetch_company_count($conn, 'SELECT COUNT(*) AS count FROM tickets WHERE company_id = ?', $companyId);
$c3 = baseline_fetch_company_count($conn, 'SELECT COUNT(*) AS count FROM employees WHERE company_id = ?', $companyId);
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
$c4 = itm_employee_count_by_employment_status_name($conn, $companyId, 'Active');
$c5 = itm_employee_count_by_employment_status_name($conn, $companyId, 'On Leave');

$end_time = microtime(true);
$end_queries = get_queries($conn);
$baseline_queries = $end_queries - $start_queries - 1;
$baseline_time = $end_time - $start_time;

// --- OPTIMIZED ---
$start_queries = get_queries($conn);
$start_time = microtime(true);

$statsSql = "SELECT
    (SELECT COUNT(*) FROM equipment WHERE company_id = ?) AS equipment_count,
    (SELECT COUNT(*) FROM tickets WHERE company_id = ?) AS tickets_count,
    (SELECT COUNT(*) FROM employees WHERE company_id = ?) AS employees_count,
    (SELECT COUNT(e.id) FROM employees e
     INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
     WHERE e.company_id = ? AND LOWER(TRIM(es.name)) = 'active') AS active_count,
    (SELECT COUNT(e.id) FROM employees e
     INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
     WHERE e.company_id = ? AND LOWER(TRIM(es.name)) = 'on leave') AS on_leave_count";

$statsStmt = mysqli_prepare($conn, $statsSql);
mysqli_stmt_bind_param($statsStmt, 'iiiii', $companyId, $companyId, $companyId, $companyId, $companyId);
mysqli_stmt_execute($statsStmt);
$statsRes = mysqli_stmt_get_result($statsStmt);
$counts = mysqli_fetch_assoc($statsRes);
mysqli_stmt_close($statsStmt);

$end_time = microtime(true);
$end_queries = get_queries($conn);
$opt_queries = $end_queries - $start_queries - 1;
$opt_time = $end_time - $start_time;

echo "\nResults:\n";
echo "Baseline:  Queries = $baseline_queries, Time = " . round($baseline_time, 4) . "s\n";
echo "Optimized: Queries = $opt_queries, Time = " . round($opt_time, 4) . "s\n";
echo "Reduction: Queries = " . ($baseline_queries - $opt_queries) . " (" . round((1 - ($opt_queries / $baseline_queries)) * 100, 1) . "%)\n";
echo "Improvement: Time = " . round($baseline_time - $opt_time, 4) . "s (" . round((1 - ($opt_time / $baseline_time)) * 100, 1) . "%)\n";

if ($c1 == $counts['equipment_count'] && $c2 == $counts['tickets_count'] && $c3 == $counts['employees_count'] && $c4 == $counts['active_count'] && $c5 == $counts['on_leave_count']) {
    echo "\n[PASS] All counts match.\n";
} else {
    echo "\n[FAIL] Counts do not match!\n";
    print_r(['baseline' => [$c1, $c2, $c3, $c4, $c5], 'optimized' => $counts]);
}
