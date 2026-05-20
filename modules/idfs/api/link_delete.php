<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$link_id = (int)($data['link_id'] ?? 0);
if ($link_id <= 0) {
    idf_fail('Invalid link_id');
}

$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
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

try {
    $syncIds = idf_unlink_peer_sync_ids($conn, $company_id);
    $unknownStatusId = (int)($syncIds['unknown_status_id'] ?? 0);
    $grayColorId = (int)($syncIds['gray_color_id'] ?? 0);

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
        idf_reset_idf_port_visual_unlink_state($conn, $company_id, $portA, $unknownStatusId, $grayColorId, $switchPortLabelColumn);
        idf_reset_idf_port_visual_unlink_state($conn, $company_id, $portB, $unknownStatusId, $grayColorId, $switchPortLabelColumn);
    }
} catch (RuntimeException $e) {
    idf_fail($e->getMessage(), 500);
}

idf_ok();
