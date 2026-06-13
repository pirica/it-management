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

$positionPortIds = [];
$stmtPorts = mysqli_prepare(
    $conn,
    "SELECT id
     FROM idf_ports
     WHERE company_id = ? AND position_id = ?"
);
if ($stmtPorts) {
    mysqli_stmt_bind_param($stmtPorts, 'ii', $company_id, $position_id);
    mysqli_stmt_execute($stmtPorts);
    $resPorts = mysqli_stmt_get_result($stmtPorts);
    while ($resPorts && ($portRow = mysqli_fetch_assoc($resPorts))) {
        $portId = (int)($portRow['id'] ?? 0);
        if ($portId > 0) {
            $positionPortIds[$portId] = $portId;
        }
    }
    mysqli_stmt_close($stmtPorts);
}

if ($positionPortIds) {
    $portIdList = implode(',', array_values($positionPortIds));
    mysqli_query(
        $conn,
        "DELETE FROM idf_links
         WHERE company_id = " . (int)$company_id . "
           AND (port_id_a IN ({$portIdList}) OR port_id_b IN ({$portIdList}))"
    );
}
mysqli_query(
    $conn,
    "DELETE FROM idf_ports
     WHERE company_id = " . (int)$company_id . "
       AND position_id = " . (int)$position_id
);

if ($linkedEquipmentId !== '' && ctype_digit($linkedEquipmentId) && $linkedIdfId > 0) {
    $linkedEquipmentIdInt = (int)$linkedEquipmentId;

    $stmtStillLinked = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS c
         FROM idf_positions
         WHERE company_id = ? AND equipment_id = ?"
    );
    $stillLinkedCount = 0;
    if ($stmtStillLinked) {
        mysqli_stmt_bind_param($stmtStillLinked, 'is', $company_id, $linkedEquipmentId);
        mysqli_stmt_execute($stmtStillLinked);
        $resStillLinked = mysqli_stmt_get_result($stmtStillLinked);
        $stillLinkedRow = $resStillLinked ? mysqli_fetch_assoc($resStillLinked) : null;
        $stillLinkedCount = (int)($stillLinkedRow['c'] ?? 0);
        mysqli_stmt_close($stmtStillLinked);
    }

    if ($stillLinkedCount <= 0) {
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
}

idf_ok();
