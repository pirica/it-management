<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT p.id, p.idf_id, p.equipment_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=? AND i.company_id=?
     LIMIT 1"
);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $position_id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $positionRow = $res ? mysqli_fetch_assoc($res) : null;
    $found = $positionRow !== null;
    mysqli_stmt_close($stmt);

    if (!$found) {
        idf_fail('Not found', 404);
    }
}

$stmtDel = mysqli_prepare($conn, "DELETE FROM idf_positions WHERE id=? LIMIT 1");
if ($stmtDel) {
    mysqli_stmt_bind_param($stmtDel, 'i', $position_id);
    if (!mysqli_stmt_execute($stmtDel)) {
        idf_fail('DB error deleting: ' . mysqli_stmt_error($stmtDel), 500);
    }
    mysqli_stmt_close($stmtDel);
}

$linkedEquipmentId = isset($positionRow['equipment_id']) ? trim((string)$positionRow['equipment_id']) : '';
$linkedIdfId = isset($positionRow['idf_id']) ? (int)$positionRow['idf_id'] : 0;
if ($linkedEquipmentId !== '' && ctype_digit($linkedEquipmentId) && $linkedIdfId > 0) {
    $linkedEquipmentIdInt = (int)$linkedEquipmentId;
    $stmtEquipment = mysqli_prepare(
        $conn,
        "UPDATE equipment
         SET idf_id = NULL
         WHERE id=? AND company_id=? AND idf_id=?
         LIMIT 1"
    );
    if ($stmtEquipment) {
        mysqli_stmt_bind_param($stmtEquipment, 'iii', $linkedEquipmentIdInt, $company_id, $linkedIdfId);
        mysqli_stmt_execute($stmtEquipment);
        mysqli_stmt_close($stmtEquipment);
    }

    $stmtSwitchPorts = mysqli_prepare(
        $conn,
        "UPDATE switch_ports
         SET idf_id = NULL
         WHERE company_id = ? AND equipment_id = ? AND idf_id = ?"
    );
    if ($stmtSwitchPorts) {
        mysqli_stmt_bind_param($stmtSwitchPorts, 'iii', $company_id, $linkedEquipmentIdInt, $linkedIdfId);
        mysqli_stmt_execute($stmtSwitchPorts);
        mysqli_stmt_close($stmtSwitchPorts);
    }
}

idf_ok();
