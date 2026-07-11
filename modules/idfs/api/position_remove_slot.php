<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$position_no = (int)($data['position_no'] ?? 0);

if ($idf_id <= 0 || $position_no <= 0 || $position_no > 250) {
    idf_fail('Invalid idf_id/position_no');
}

$stmtOwn = mysqli_prepare(
    $conn,
    "SELECT id
     FROM idfs
     WHERE id=? AND company_id=?
     LIMIT 1"
);
if (!$stmtOwn) {
    idf_fail('DB error', 500);
}
mysqli_stmt_bind_param($stmtOwn, 'ii', $idf_id, $company_id);
mysqli_stmt_execute($stmtOwn);
$resOwn = mysqli_stmt_get_result($stmtOwn);
$idfFound = $resOwn && mysqli_num_rows($resOwn) === 1;
mysqli_stmt_close($stmtOwn);
if (!$idfFound) {
    idf_fail('IDF not found', 404);
}

$stmtOccupied = mysqli_prepare(
    $conn,
    "SELECT id
     FROM idf_positions
     WHERE idf_id=? AND company_id=? AND position_no=?
     LIMIT 1"
);
if (!$stmtOccupied) {
    idf_fail('DB error', 500);
}
mysqli_stmt_bind_param($stmtOccupied, 'iii', $idf_id, $company_id, $position_no);
mysqli_stmt_execute($stmtOccupied);
$resOccupied = mysqli_stmt_get_result($stmtOccupied);
$slotOccupied = $resOccupied && mysqli_num_rows($resOccupied) > 0;
mysqli_stmt_close($stmtOccupied);
if ($slotOccupied) {
    idf_fail('This position contains a device. Delete or move the device first.', 400);
}

mysqli_begin_transaction($conn);
try {
    $stmtShift = mysqli_prepare(
        $conn,
        "UPDATE idf_positions
         SET position_no = position_no - 1
         WHERE idf_id=? AND company_id=? AND position_no > ?"
    );
    if (!$stmtShift) {
        throw new Exception('DB error');
    }
    mysqli_stmt_bind_param($stmtShift, 'iii', $idf_id, $company_id, $position_no);
    if (!mysqli_stmt_execute($stmtShift)) {
        $shiftErr = mysqli_stmt_error($stmtShift);
        mysqli_stmt_close($stmtShift);
        throw new Exception($shiftErr !== '' ? $shiftErr : 'DB error');
    }
    mysqli_stmt_close($stmtShift);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('DB error: ' . $e->getMessage(), 500);
}

idf_ok();
