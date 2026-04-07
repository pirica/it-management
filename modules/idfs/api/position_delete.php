<?php
/**
 * IDF API - Delete Position
 * 
 * Removes a device (position) from the IDF.
 * Cascading deletion in the database (or manual deletion triggers) 
 * will typically remove child ports and links associated with this device.
 */

require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}

// Verify company ownership of the device.
$stmt = mysqli_prepare(
    $conn,
    "SELECT p.id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=? AND i.company_id=?
     LIMIT 1"
);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $position_id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $found = $res && mysqli_num_rows($res) === 1;
    mysqli_stmt_close($stmt);

    if (!$found) {
        idf_fail('Not found', 404);
    }
}

// Execute deletion.
$stmtDel = mysqli_prepare($conn, "DELETE FROM idf_positions WHERE id=? LIMIT 1");
if ($stmtDel) {
    mysqli_stmt_bind_param($stmtDel, 'i', $position_id);
    if (!mysqli_stmt_execute($stmtDel)) {
        idf_fail('DB error deleting: ' . mysqli_stmt_error($stmtDel), 500);
    }
    mysqli_stmt_close($stmtDel);
}

idf_ok();
