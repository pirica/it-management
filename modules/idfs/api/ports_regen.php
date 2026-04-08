<?php
/**
 * IDF API - Regenerate Ports
 * 
 * Resets and reconstructs the port list for a specific IDF position (device).
 * Logic:
 * - Destructive: Deletes ALL existing ports and associated links/data for the position.
 * - Reconstruction: Creates a fresh sequence of ports from 1 up to the `port_count` defined 
 *   in the `idf_positions` table.
 * - Transactional: Uses a database transaction to ensure either all ports are recreated 
 *   or none are, preventing a partial data state.
 */

require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}

// Ownership verification through the IDF hierarchy.
$stmt = mysqli_prepare(
    $conn,
    "SELECT p.port_count, p.id, i.company_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=?
     LIMIT 1"
);
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $position_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$count = (int)$row['port_count'];
if ($count <= 0) {
    idf_fail('This device has port_count=0');
}

// Perform regeneration within a transaction for data integrity.
mysqli_begin_transaction($conn);
try {
    // Wipe existing ports for this device.
    $stmtDel = mysqli_prepare($conn, "DELETE FROM idf_ports WHERE position_id=?");
    if ($stmtDel) {
        mysqli_stmt_bind_param($stmtDel, 'i', $position_id);
        mysqli_stmt_execute($stmtDel);
        mysqli_stmt_close($stmtDel);
    }

    // Insert new ports sequentially based on the device capacity.
    $stmtIns = mysqli_prepare($conn, "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status) VALUES (?, ?, ?, 'RJ45', 'unknown')");
    if ($stmtIns) {
        for ($n = 1; $n <= $count; $n++) {
            mysqli_stmt_bind_param($stmtIns, 'iii', $company_id, $position_id, $n);
            mysqli_stmt_execute($stmtIns);
        }
        mysqli_stmt_close($stmtIns);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    // If anything fails, revert the deletion.
    mysqli_rollback($conn);
    idf_fail('Regen failed: ' . $e->getMessage(), 500);
}

idf_ok();
