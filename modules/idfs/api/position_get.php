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
    "SELECT p.*, i.company_id,
            COALESCE(e.switch_rj45_id, er_count.id, 0) AS effective_switch_rj45_id,
            COALESCE(NULLIF(p.switch_port_numbering_layout_id, 0), NULLIF(e.switch_port_numbering_layout_id, 0), NULLIF(port_layout.switch_port_numbering_layout_id, 0), 0) AS effective_switch_port_numbering_layout_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     LEFT JOIN equipment e
       ON e.id = p.equipment_id
      AND e.company_id = p.company_id
     LEFT JOIN equipment_rj45 er_count
       ON er_count.company_id = p.company_id
      AND p.port_count > 0
      AND er_count.name REGEXP CONCAT('(^|[^0-9])', p.port_count, '([^0-9]|$)')
     LEFT JOIN (
        SELECT position_id, MIN(switch_port_numbering_layout_id) AS switch_port_numbering_layout_id
        FROM idf_ports
        WHERE switch_port_numbering_layout_id IS NOT NULL
          AND switch_port_numbering_layout_id <> 0
        GROUP BY position_id
     ) port_layout ON port_layout.position_id = p.id
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

unset($row['company_id']);
idf_ok(['position' => $row]);
