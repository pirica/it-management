<?php
/**
 * Verification script for Audit Logs module fixes.
 *
 * This script attempts to execute the FIXED SQL queries to confirm they pass.
 */

define('ITM_CLI_SCRIPT', true);
require __DIR__ . '/../../config/config.php';

echo "Testing FIXED Audit Logs SQL queries...\n";

$companyId = 1; // Assuming company ID 1 exists

// problematic components from index.php
$where = ['al.company_id = ?'];
$params = [$companyId];
$types = 'i';

// 1. Test Count Query
echo "Testing Count Query (FIXED)... ";
$countSql = 'SELECT COUNT(*) AS total '
          . 'FROM audit_logs al '
          . 'LEFT JOIN employees u ON u.id = al.user_id '
          . 'WHERE ' . implode(' AND ', $where);

try {
    $countStmt = mysqli_prepare($conn, $countSql);
    if (!$countStmt) {
        echo "FAILED (Prepare): " . mysqli_error($conn) . "\n";
    } else {
        echo "PASSED (Prepare)\n";
        mysqli_stmt_close($countStmt);
    }
} catch (Exception $e) {
    echo "FAILED (Exception): " . $e->getMessage() . "\n";
}

// 2. Test Main Data Query
echo "Testing Main Data Query (FIXED)... ";
$sql = 'SELECT al.*, u.username, u.work_email, u.first_name, u.last_name '
     . 'FROM audit_logs al '
     . 'LEFT JOIN employees u ON u.id = al.user_id '
     . 'WHERE ' . implode(' AND ', $where) . ' '
     . 'ORDER BY al.changed_at DESC LIMIT 25 OFFSET 0';

try {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo "FAILED (Prepare): " . mysqli_error($conn) . "\n";
    } else {
        echo "PASSED (Prepare)\n";
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    echo "FAILED (Exception): " . $e->getMessage() . "\n";
}

// 3. Test View Query
echo "Testing View Query (FIXED)... ";
$viewSql = 'SELECT al.*, u.username, u.work_email, u.first_name, u.last_name '
        . 'FROM audit_logs al '
        . 'LEFT JOIN employees u ON u.id = al.user_id '
        . 'WHERE al.id = ? AND al.company_id = ? '
        . 'LIMIT 1';

try {
    $viewStmt = mysqli_prepare($conn, $viewSql);
    if (!$viewStmt) {
        echo "FAILED (Prepare): " . mysqli_error($conn) . "\n";
    } else {
        echo "PASSED (Prepare)\n";
        mysqli_stmt_close($viewStmt);
    }
} catch (Exception $e) {
    echo "FAILED (Exception): " . $e->getMessage() . "\n";
}
