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

$stmtDel = mysqli_prepare($conn, "DELETE FROM idf_links WHERE id=? LIMIT 1");
if ($stmtDel) {
    mysqli_stmt_bind_param($stmtDel, 'i', $link_id);
    if (!mysqli_stmt_execute($stmtDel)) {
        idf_fail('DB error deleting link: ' . mysqli_stmt_error($stmtDel), 500);
    }
    mysqli_stmt_close($stmtDel);
}

$portA = (int)($row['port_id_a'] ?? 0);
$portB = (int)($row['port_id_b'] ?? 0);
if ($portA > 0 && $portB > 0) {
    $clearConnected = '';
    $stmtPortClear = mysqli_prepare($conn, "UPDATE idf_ports SET connected_to = ? WHERE id = ? LIMIT 1");
    if ($stmtPortClear) {
        mysqli_stmt_bind_param($stmtPortClear, 'si', $clearConnected, $portA);
        mysqli_stmt_execute($stmtPortClear);
        mysqli_stmt_bind_param($stmtPortClear, 'si', $clearConnected, $portB);
        mysqli_stmt_execute($stmtPortClear);
        mysqli_stmt_close($stmtPortClear);
    }
}

idf_ok();
