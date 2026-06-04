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
            COALESCE(
                NULLIF(p.sfp_count, 0),
                CAST(NULLIF(TRIM(e.switch_fiber_ports_number), '') AS UNSIGNED),
                COALESCE(sfp_ports.max_sfp_no, 0),
                0
            ) AS effective_sfp_count,
            CAST(
                COALESCE(
                    NULLIF(p.sfp_count, 0),
                    CAST(NULLIF(TRIM(e.switch_fiber_ports_number), '') AS UNSIGNED),
                    COALESCE(sfp_ports.max_sfp_no, 0),
                    0
                ) AS CHAR
            ) AS equipment_switch_fiber_ports_number,
            COALESCE(NULLIF(e_layout_scoped.id, 0), NULLIF(e_layout_company_match.id, 0), NULLIF(e.switch_port_numbering_layout_id, 0), 0) AS equipment_switch_port_numbering_layout_id,
            COALESCE(
                NULLIF(e_layout_scoped.id, 0),
                NULLIF(e_layout_company_match.id, 0),
                NULLIF(e.switch_port_numbering_layout_id, 0),
                NULLIF(p_layout_scoped.id, 0),
                NULLIF(p_layout_company_match.id, 0),
                NULLIF(p.switch_port_numbering_layout_id, 0),
                NULLIF(port_layout_scoped.id, 0),
                NULLIF(port_layout_company_match.id, 0),
                NULLIF(port_layout.switch_port_numbering_layout_id, 0),
                0
            ) AS effective_switch_port_numbering_layout_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     LEFT JOIN equipment e
       ON e.company_id = p.company_id
      AND p.equipment_id REGEXP '^[0-9]+$'
      AND e.id = CAST(p.equipment_id AS UNSIGNED)
     LEFT JOIN switch_port_numbering_layout e_layout_scoped
       ON e_layout_scoped.id = e.switch_port_numbering_layout_id
      AND e_layout_scoped.company_id = e.company_id
     LEFT JOIN switch_port_numbering_layout e_layout_any
       ON e_layout_any.id = e.switch_port_numbering_layout_id
     LEFT JOIN switch_port_numbering_layout e_layout_company_match
       ON e_layout_company_match.company_id = e.company_id
      AND LOWER(e_layout_company_match.name) = LOWER(e_layout_any.name)
     LEFT JOIN switch_port_numbering_layout p_layout_scoped
       ON p_layout_scoped.id = p.switch_port_numbering_layout_id
      AND p_layout_scoped.company_id = p.company_id
     LEFT JOIN switch_port_numbering_layout p_layout_any
       ON p_layout_any.id = p.switch_port_numbering_layout_id
     LEFT JOIN switch_port_numbering_layout p_layout_company_match
       ON p_layout_company_match.company_id = p.company_id
      AND LOWER(p_layout_company_match.name) = LOWER(p_layout_any.name)
     LEFT JOIN equipment_rj45 er_count
       ON er_count.company_id = p.company_id
      AND p.rj45_count > 0
      AND er_count.name REGEXP CONCAT('(^|[^0-9])', p.rj45_count, '([^0-9]|$)')
     LEFT JOIN (
        SELECT ip.position_id, MAX(ip.port_no) AS max_sfp_no
        FROM idf_ports ip
        JOIN switch_port_types spt
          ON spt.id = ip.port_type
         AND spt.company_id = ip.company_id
        WHERE LOWER(spt.type) LIKE '%sfp%'
        GROUP BY ip.position_id
     ) sfp_ports ON sfp_ports.position_id = p.id
     LEFT JOIN (
        SELECT position_id, MIN(switch_port_numbering_layout_id) AS switch_port_numbering_layout_id
        FROM idf_ports
        WHERE switch_port_numbering_layout_id IS NOT NULL
          AND switch_port_numbering_layout_id <> 0
        GROUP BY position_id
     ) port_layout ON port_layout.position_id = p.id
     LEFT JOIN switch_port_numbering_layout port_layout_scoped
       ON port_layout_scoped.id = port_layout.switch_port_numbering_layout_id
      AND port_layout_scoped.company_id = p.company_id
     LEFT JOIN switch_port_numbering_layout port_layout_any
       ON port_layout_any.id = port_layout.switch_port_numbering_layout_id
     LEFT JOIN switch_port_numbering_layout port_layout_company_match
       ON port_layout_company_match.company_id = p.company_id
      AND LOWER(port_layout_company_match.name) = LOWER(port_layout_any.name)
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
$row['port_count'] = (int)($row['rj45_count'] ?? 0);
idf_ok(['position' => $row]);
