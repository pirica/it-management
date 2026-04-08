<?php
/**
 * IDF API - Move Position
 * 
 * Swaps the vertical slot (position_no) of two devices in an IDF rack.
 * Logic:
 * - Directional: Handles 'up' and 'down' requests relative to the current slot.
 * - Swap Pattern: Uses a temporary value (99) to perform a classic swap 
 *   between the source and target slots without violating uniqueness constraints 
 *   (if any existed) or losing track of records during the update.
 * - Transactional: Ensures both records are updated or none are.
 */

require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$position_no = (int)($data['position_no'] ?? 0);
$dir = (string)($data['dir'] ?? '');

if ($idf_id <= 0 || $position_no < 1 || $position_no > 10) {
    idf_fail('Invalid idf_id/position_no');
}
if (!in_array($dir, ['up', 'down'], true)) {
    idf_fail('Invalid dir');
}

// Ownership verification.
$stmtIdf = mysqli_prepare($conn, "SELECT id FROM idfs WHERE id=? AND company_id=? LIMIT 1");
if ($stmtIdf) {
    mysqli_stmt_bind_param($stmtIdf, 'ii', $idf_id, $company_id);
    mysqli_stmt_execute($stmtIdf);
    $resIdf = mysqli_stmt_get_result($stmtIdf);
    $foundIdf = $resIdf && mysqli_num_rows($resIdf) === 1;
    mysqli_stmt_close($stmtIdf);

    if (!$foundIdf) {
        idf_fail('IDF not found', 404);
    }
}

// Calculate the target slot.
$target = $dir === 'up' ? $position_no - 1 : $position_no + 1;
// Bounds checking (racks are 1-10 slots).
if ($target < 1 || $target > 10) {
    idf_ok();
}

mysqli_begin_transaction($conn);
try {
    // Stage 1: Move the source device to a hidden temporary slot.
    $tmp = 99;
    $stmt1 = mysqli_prepare($conn, "UPDATE idf_positions SET position_no=? WHERE idf_id=? AND position_no=? LIMIT 1");
    if ($stmt1) {
        mysqli_stmt_bind_param($stmt1, 'iii', $tmp, $idf_id, $position_no);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);
    }

    // Stage 2: Move the target device (the neighbor) into the original source slot.
    $stmt2 = mysqli_prepare($conn, "UPDATE idf_positions SET position_no=? WHERE idf_id=? AND position_no=? LIMIT 1");
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 'iii', $position_no, $idf_id, $target);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
    }

    // Stage 3: Move the source device from the temporary slot into the target's old slot.
    $stmt3 = mysqli_prepare($conn, "UPDATE idf_positions SET position_no=? WHERE idf_id=? AND position_no=? LIMIT 1");
    if ($stmt3) {
        mysqli_stmt_bind_param($stmt3, 'iii', $target, $idf_id, $tmp);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Move failed: ' . $e->getMessage(), 500);
}

idf_ok();
