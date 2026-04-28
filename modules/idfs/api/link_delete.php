<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$link_id = (int)($data['link_id'] ?? 0);
if ($link_id <= 0) {
    idf_fail('Invalid link_id');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT l.id, l.port_id_a, l.port_id_b, i.company_id
     FROM idf_links l
     JOIN idf_ports a ON a.id=l.port_id_a
     JOIN idf_positions pa ON pa.id=a.position_id
     JOIN idfs i ON i.id=pa.idf_id
     WHERE l.id=?
     LIMIT 1"
);
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $link_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$portA = (int)($row['port_id_a'] ?? 0);
$portB = (int)($row['port_id_b'] ?? 0);

$stmtDel = mysqli_prepare(
    $conn,
    "DELETE FROM idf_links
     WHERE company_id = ?
       AND (
            (port_id_a = ? AND port_id_b = ?)
            OR (port_id_a = ? AND port_id_b = ?)
       )"
);
if ($stmtDel) {
    mysqli_stmt_bind_param($stmtDel, 'iiiii', $company_id, $portA, $portB, $portB, $portA);
    if (!mysqli_stmt_execute($stmtDel)) {
        idf_fail('DB error deleting link: ' . mysqli_stmt_error($stmtDel), 500);
    }
    mysqli_stmt_close($stmtDel);
}
if ($portA > 0 && $portB > 0) {
    $clearConnected = '';
    $clearCable = null;
    $stmtPortClear = mysqli_prepare($conn, "UPDATE idf_ports SET connected_to = ?, cable_color = ?, hex_color = ? WHERE id = ? LIMIT 1");
    if ($stmtPortClear) {
        mysqli_stmt_bind_param($stmtPortClear, 'sssi', $clearConnected, $clearCable, $clearCable, $portA);
        mysqli_stmt_execute($stmtPortClear);
        mysqli_stmt_bind_param($stmtPortClear, 'sssi', $clearConnected, $clearCable, $clearCable, $portB);
        mysqli_stmt_execute($stmtPortClear);
        mysqli_stmt_close($stmtPortClear);
    }

    $stmtSwitchClear = mysqli_prepare(
        $conn,
        "UPDATE switch_ports sp
         JOIN idf_ports pr ON pr.id = ?
         JOIN idf_positions p ON p.id = pr.position_id
         SET sp.comments = NULL
         WHERE sp.company_id = ?
           AND p.company_id = sp.company_id
           AND p.equipment_id = sp.equipment_id
           AND sp.port_number = pr.port_no
           AND sp.port_type = pr.port_type
         LIMIT 1"
    );
    if ($stmtSwitchClear) {
        mysqli_stmt_bind_param($stmtSwitchClear, 'ii', $portA, $company_id);
        mysqli_stmt_execute($stmtSwitchClear);
        mysqli_stmt_bind_param($stmtSwitchClear, 'ii', $portB, $company_id);
        mysqli_stmt_execute($stmtSwitchClear);
        mysqli_stmt_close($stmtSwitchClear);
    }
}

idf_ok();
