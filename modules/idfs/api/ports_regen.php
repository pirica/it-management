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
    "SELECT p.port_count, p.id, p.equipment_id, i.company_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
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

$count = (int)$row['port_count'];
$equipmentIdRaw = trim((string)($row['equipment_id'] ?? ''));
$equipmentId = ctype_digit($equipmentIdRaw) ? (int)$equipmentIdRaw : 0;

$unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
if ($unknownStatusId <= 0) {
    idf_fail('Unable to resolve default status for company', 500);
}
$rj45PortTypeId = idf_resolve_port_type_id($conn, $company_id, 'RJ45', 'RJ45');
if ($rj45PortTypeId <= 0) {
    idf_fail('Unable to resolve default port type for company', 500);
}

$fiberPortsToInsert = [];
if ($equipmentId > 0) {
    $stmtFiberSwitchPorts = mysqli_prepare(
        $conn,
        "SELECT sp.port_number,
                COALESCE(spt.id, 0) AS port_type_id,
                COALESCE(spt.type, sp.port_type) AS port_type_name
         FROM switch_ports sp
         LEFT JOIN switch_port_types spt
           ON spt.company_id = sp.company_id
          AND spt.type = sp.port_type
         WHERE sp.company_id = ? AND sp.equipment_id = ?
         ORDER BY sp.port_number ASC"
    );
    if ($stmtFiberSwitchPorts) {
        mysqli_stmt_bind_param($stmtFiberSwitchPorts, 'ii', $company_id, $equipmentId);
        mysqli_stmt_execute($stmtFiberSwitchPorts);
        $resFiberSwitchPorts = mysqli_stmt_get_result($stmtFiberSwitchPorts);
        while ($resFiberSwitchPorts && ($fiberSwitchPortRow = mysqli_fetch_assoc($resFiberSwitchPorts))) {
            $fiberPortNo = (int)($fiberSwitchPortRow['port_number'] ?? 0);
            if ($fiberPortNo <= 0) {
                continue;
            }

            $portTypeNameRaw = trim((string)($fiberSwitchPortRow['port_type_name'] ?? ''));
            $portTypeNameNormalized = strtolower($portTypeNameRaw);
            if (strpos($portTypeNameNormalized, 'sfp') === false) {
                continue;
            }

            $fiberPortTypeId = (int)($fiberSwitchPortRow['port_type_id'] ?? 0);
            if ($fiberPortTypeId <= 0) {
                $fiberPortTypeFallback = (strpos($portTypeNameNormalized, 'sfp+') !== false || strpos($portTypeNameNormalized, 'sfp plus') !== false)
                    ? 'sfp+'
                    : 'sfp';
                $fiberPortTypeId = idf_resolve_port_type_id($conn, $company_id, $portTypeNameRaw, $fiberPortTypeFallback);
            }
            if ($fiberPortTypeId <= 0) {
                continue;
            }

            $fiberPortKey = $fiberPortTypeId . ':' . $fiberPortNo;
            $fiberPortsToInsert[$fiberPortKey] = [
                'port_no' => $fiberPortNo,
                'port_type' => $fiberPortTypeId,
            ];
        }
        mysqli_stmt_close($stmtFiberSwitchPorts);
    }

    $stmtFiberMeta = mysqli_prepare(
        $conn,
        "SELECT COALESCE(e.switch_fiber_ports_number, 0) AS switch_fiber_ports_number,
                COALESCE(e.switch_fiber_port_label, '') AS switch_fiber_port_label,
                COALESCE(ef.name, '') AS switch_fiber_name
         FROM equipment e
         LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
         WHERE e.company_id = ? AND e.id = ?
         LIMIT 1"
    );
    if ($stmtFiberMeta) {
        mysqli_stmt_bind_param($stmtFiberMeta, 'ii', $company_id, $equipmentId);
        mysqli_stmt_execute($stmtFiberMeta);
        $resFiberMeta = mysqli_stmt_get_result($stmtFiberMeta);
        $fiberMetaRow = $resFiberMeta ? mysqli_fetch_assoc($resFiberMeta) : null;
        mysqli_stmt_close($stmtFiberMeta);

        if ($fiberMetaRow) {
            $fiberCount = (int)($fiberMetaRow['switch_fiber_ports_number'] ?? 0);
            if ($fiberCount > 0) {
                $fiberHint = strtolower(trim(
                    (string)($fiberMetaRow['switch_fiber_port_label'] ?? '') . ' ' . (string)($fiberMetaRow['switch_fiber_name'] ?? '')
                ));
                $fiberTypeFallback = (strpos($fiberHint, 'sfp+') !== false || strpos($fiberHint, 'sfp plus') !== false)
                    ? 'sfp+'
                    : 'sfp';
                $fiberTypeId = idf_resolve_port_type_id($conn, $company_id, $fiberTypeFallback, $fiberTypeFallback);
                if ($fiberTypeId > 0) {
                    for ($fiberPortNo = 1; $fiberPortNo <= $fiberCount; $fiberPortNo++) {
                        $fiberPortKey = $fiberTypeId . ':' . $fiberPortNo;
                        if (!isset($fiberPortsToInsert[$fiberPortKey])) {
                            $fiberPortsToInsert[$fiberPortKey] = [
                                'port_no' => $fiberPortNo,
                                'port_type' => $fiberTypeId,
                            ];
                        }
                    }
                }
            }
        }
    }
}

if ($count <= 0 && empty($fiberPortsToInsert)) {
    idf_fail('This device has port_count=0 and no SFP/SFP+ ports configured');
}

mysqli_begin_transaction($conn);
try {
    $stmtDel = mysqli_prepare($conn, "DELETE FROM idf_ports WHERE position_id=? AND company_id=?");
    if ($stmtDel) {
        mysqli_stmt_bind_param($stmtDel, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmtDel);
        mysqli_stmt_close($stmtDel);
    }

    $stmtIns = mysqli_prepare($conn, "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status_id) VALUES (?, ?, ?, ?, ?)");
    if ($stmtIns) {
        if ($count > 0) {
            for ($n = 1; $n <= $count; $n++) {
                mysqli_stmt_bind_param($stmtIns, 'iiiii', $company_id, $position_id, $n, $rj45PortTypeId, $unknownStatusId);
                mysqli_stmt_execute($stmtIns);
            }
        }

        if (!empty($fiberPortsToInsert)) {
            ksort($fiberPortsToInsert, SORT_NATURAL);
            foreach ($fiberPortsToInsert as $fiberPortMeta) {
                $fiberPortNo = (int)($fiberPortMeta['port_no'] ?? 0);
                $fiberPortTypeId = (int)($fiberPortMeta['port_type'] ?? 0);
                if ($fiberPortNo <= 0 || $fiberPortTypeId <= 0) {
                    continue;
                }
                mysqli_stmt_bind_param($stmtIns, 'iiiii', $company_id, $position_id, $fiberPortNo, $fiberPortTypeId, $unknownStatusId);
                mysqli_stmt_execute($stmtIns);
            }
        }
        mysqli_stmt_close($stmtIns);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Regen failed: ' . $e->getMessage(), 500);
}

idf_ok();
