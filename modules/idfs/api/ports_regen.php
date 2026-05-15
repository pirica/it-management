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
    "SELECT p.rj45_count, p.sfp_count, p.id, p.equipment_id, p.switch_port_numbering_layout_id, i.company_id, i.id AS idf_id,
            COALESCE(e.hostname, '') AS equipment_hostname
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     LEFT JOIN equipment e
       ON e.id = p.equipment_id
      AND e.company_id = i.company_id
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

$count = (int)($row['rj45_count'] ?? 0);
$sfpCount = (int)($row['sfp_count'] ?? 0);
$equipmentIdRaw = trim((string)($row['equipment_id'] ?? ''));
$equipmentId = ctype_digit($equipmentIdRaw) ? (int)$equipmentIdRaw : 0;
$idfId = (int)($row['idf_id'] ?? 0);
$equipmentHostname = trim((string)($row['equipment_hostname'] ?? ''));
$positionLayoutId = (int)($row['switch_port_numbering_layout_id'] ?? 0);
$fiberPortsNumberId = 0;
$switchPortNumberingLayoutId = $positionLayoutId > 0 ? $positionLayoutId : 0;
$managementId = 0;

$unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
if ($unknownStatusId <= 0) {
    idf_fail('Unable to resolve default status for company', 500);
}
$rj45PortTypeId = idf_resolve_port_type_id($conn, $company_id, 'RJ45', 'RJ45');
if ($rj45PortTypeId <= 0) {
    idf_fail('Unable to resolve default port type for company', 500);
}

$portRowsToInsert = [];
if ($count > 0) {
    for ($n = 1; $n <= $count; $n++) {
        $rj45PortKey = $rj45PortTypeId . ':' . $n;
        $portRowsToInsert[$rj45PortKey] = [
            'port_no' => $n,
            'port_type' => $rj45PortTypeId,
        ];
    }
}

$fiberPortsToInsert = [];
if ($equipmentId > 0) {
    $stmtPortMeta = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(e.switch_fiber_ports_number, '') AS switch_fiber_ports_number,
            COALESCE(e.switch_port_numbering_layout_id, 0) AS equipment_layout_id,
            COALESCE(e.switch_environment_id, 0) AS switch_environment_id,
            efc.id AS fiber_ports_number_id
         FROM equipment e
         LEFT JOIN equipment_fiber_count efc
           ON efc.company_id = e.company_id
          AND efc.name = e.switch_fiber_ports_number
         WHERE e.company_id = ? AND e.id = ?
         LIMIT 1"
    );
    if ($stmtPortMeta) {
        mysqli_stmt_bind_param($stmtPortMeta, 'ii', $company_id, $equipmentId);
        mysqli_stmt_execute($stmtPortMeta);
        $resPortMeta = mysqli_stmt_get_result($stmtPortMeta);
        $portMetaRow = $resPortMeta ? mysqli_fetch_assoc($resPortMeta) : null;
        mysqli_stmt_close($stmtPortMeta);
        if ($portMetaRow) {
            $fiberPortsNumberId = (int)($portMetaRow['fiber_ports_number_id'] ?? 0);
            if ($switchPortNumberingLayoutId <= 0) {
                $switchPortNumberingLayoutId = (int)($portMetaRow['equipment_layout_id'] ?? 0);
            }
            $managementId = (int)($portMetaRow['switch_environment_id'] ?? 0);
        }
    }

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
            $resolvedPortTypeId = (int)($fiberSwitchPortRow['port_type_id'] ?? 0);
            if ($resolvedPortTypeId <= 0) {
                $fiberPortTypeFallback = (strpos($portTypeNameNormalized, 'sfp+') !== false || strpos($portTypeNameNormalized, 'sfp plus') !== false)
                    ? 'sfp+'
                    : ((strpos($portTypeNameNormalized, 'sfp') !== false) ? 'sfp' : 'RJ45');
                $resolvedPortTypeId = idf_resolve_port_type_id($conn, $company_id, $portTypeNameRaw, $fiberPortTypeFallback);
            }
            if ($resolvedPortTypeId <= 0) {
                continue;
            }

            $portKey = $resolvedPortTypeId . ':' . $fiberPortNo;
            $portMeta = [
                'port_no' => $fiberPortNo,
                'port_type' => $resolvedPortTypeId,
            ];
            $portRowsToInsert[$portKey] = $portMeta;
            if (strpos($portTypeNameNormalized, 'sfp') !== false) {
                $fiberPortsToInsert[$portKey] = $portMeta;
            }
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
} elseif ($sfpCount > 0) {
    $fiberTypeId = idf_resolve_port_type_id($conn, $company_id, 'sfp', 'sfp');
    if ($fiberTypeId > 0) {
        for ($fiberPortNo = 1; $fiberPortNo <= $sfpCount; $fiberPortNo++) {
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
if ($managementId <= 0) {
    $stmtUnmanaged = mysqli_prepare(
        $conn,
        "SELECT id
         FROM equipment_environment
         WHERE company_id = ?
           AND LOWER(name) = 'unmanaged'
         ORDER BY id ASC
         LIMIT 1"
    );
    if ($stmtUnmanaged) {
        mysqli_stmt_bind_param($stmtUnmanaged, 'i', $company_id);
        mysqli_stmt_execute($stmtUnmanaged);
        $resUnmanaged = mysqli_stmt_get_result($stmtUnmanaged);
        $unmanagedRow = $resUnmanaged ? mysqli_fetch_assoc($resUnmanaged) : null;
        mysqli_stmt_close($stmtUnmanaged);
        if ($unmanagedRow) {
            $managementId = (int)($unmanagedRow['id'] ?? 0);
        }
    }
}

if (!empty($fiberPortsToInsert)) {
    foreach ($fiberPortsToInsert as $fiberPortKey => $fiberPortMeta) {
        $portRowsToInsert[$fiberPortKey] = $fiberPortMeta;
    }
}

if (empty($portRowsToInsert)) {
    idf_fail('This device has rj45_count=0 and no SFP/SFP+ ports configured');
}

mysqli_begin_transaction($conn);
try {
    $stmtDel = mysqli_prepare($conn, "DELETE FROM idf_ports WHERE position_id=? AND company_id=?");
    if ($stmtDel) {
        mysqli_stmt_bind_param($stmtDel, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmtDel);
        mysqli_stmt_close($stmtDel);
    }

    $stmtIns = mysqli_prepare($conn, "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status_id, fiber_ports_number, switch_port_numbering_layout_id, management_id) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0))");
    if ($stmtIns) {
        if (!empty($portRowsToInsert)) {
            ksort($portRowsToInsert, SORT_NATURAL);
            foreach ($portRowsToInsert as $portMeta) {
                $portNo = (int)($portMeta['port_no'] ?? 0);
                $portTypeId = (int)($portMeta['port_type'] ?? 0);
                if ($portNo <= 0 || $portTypeId <= 0) {
                    continue;
                }
                mysqli_stmt_bind_param($stmtIns, 'iiiiiiii', $company_id, $position_id, $portNo, $portTypeId, $unknownStatusId, $fiberPortsNumberId, $switchPortNumberingLayoutId, $managementId);
                mysqli_stmt_execute($stmtIns);
            }
        }
        mysqli_stmt_close($stmtIns);
    }

    if ($equipmentId > 0) {
        $stmtDeleteSwitchPorts = mysqli_prepare($conn, "DELETE FROM switch_ports WHERE company_id = ? AND equipment_id = ?");
        if ($stmtDeleteSwitchPorts) {
            mysqli_stmt_bind_param($stmtDeleteSwitchPorts, 'ii', $company_id, $equipmentId);
            mysqli_stmt_execute($stmtDeleteSwitchPorts);
            mysqli_stmt_close($stmtDeleteSwitchPorts);
        }

        $portTypeNameById = [];
        $stmtPortTypes = mysqli_prepare($conn, "SELECT id, type FROM switch_port_types WHERE company_id = ?");
        if ($stmtPortTypes) {
            mysqli_stmt_bind_param($stmtPortTypes, 'i', $company_id);
            mysqli_stmt_execute($stmtPortTypes);
            $resPortTypes = mysqli_stmt_get_result($stmtPortTypes);
            while ($resPortTypes && ($portTypeRow = mysqli_fetch_assoc($resPortTypes))) {
                $resolvedPortTypeId = (int)($portTypeRow['id'] ?? 0);
                $resolvedPortTypeName = trim((string)($portTypeRow['type'] ?? ''));
                if ($resolvedPortTypeId > 0 && $resolvedPortTypeName !== '') {
                    $portTypeNameById[$resolvedPortTypeId] = $resolvedPortTypeName;
                }
            }
            mysqli_stmt_close($stmtPortTypes);
        }

        $defaultColorId = 0;
        $stmtGrayColor = mysqli_prepare(
            $conn,
            "SELECT id
             FROM cable_colors
             WHERE company_id = ?
               AND LOWER(color_name) = 'gray'
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtGrayColor) {
            mysqli_stmt_bind_param($stmtGrayColor, 'i', $company_id);
            mysqli_stmt_execute($stmtGrayColor);
            $resGrayColor = mysqli_stmt_get_result($stmtGrayColor);
            $grayColorRow = $resGrayColor ? mysqli_fetch_assoc($resGrayColor) : null;
            mysqli_stmt_close($stmtGrayColor);
            if ($grayColorRow) {
                $defaultColorId = (int)($grayColorRow['id'] ?? 0);
            }
        }
        if ($defaultColorId <= 0) {
            $stmtAnyColor = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM cable_colors
                 WHERE company_id = ?
                 ORDER BY id ASC
                 LIMIT 1"
            );
            if ($stmtAnyColor) {
                mysqli_stmt_bind_param($stmtAnyColor, 'i', $company_id);
                mysqli_stmt_execute($stmtAnyColor);
                $resAnyColor = mysqli_stmt_get_result($stmtAnyColor);
                $anyColorRow = $resAnyColor ? mysqli_fetch_assoc($resAnyColor) : null;
                mysqli_stmt_close($stmtAnyColor);
                if ($anyColorRow) {
                    $defaultColorId = (int)($anyColorRow['id'] ?? 0);
                }
            }
        }
        if ($defaultColorId <= 0) {
            throw new RuntimeException('Unable to resolve default cable color for switch port regeneration');
        }

        $stmtInsertSwitchPort = mysqli_prepare(
            $conn,
            "INSERT INTO switch_ports
                (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, idf_id, management_id, comments)
             VALUES
                (?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?)"
        );
        if ($stmtInsertSwitchPort) {
            $switchPatchPortDefault = '0';
            $switchCommentsDefault = '';
            foreach ($portRowsToInsert as $portMeta) {
                $portNo = (int)($portMeta['port_no'] ?? 0);
                $portTypeId = (int)($portMeta['port_type'] ?? 0);
                $portTypeName = trim((string)($portTypeNameById[$portTypeId] ?? ''));
                if ($portNo <= 0 || $portTypeName === '') {
                    continue;
                }
                mysqli_stmt_bind_param(
                    $stmtInsertSwitchPort,
                    'iissisiiiis',
                    $company_id,
                    $equipmentId,
                    $equipmentHostname,
                    $portTypeName,
                    $portNo,
                    $switchPatchPortDefault,
                    $unknownStatusId,
                    $defaultColorId,
                    $idfId,
                    $managementId,
                    $switchCommentsDefault
                );
                mysqli_stmt_execute($stmtInsertSwitchPort);
            }
            mysqli_stmt_close($stmtInsertSwitchPort);
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Regen failed: ' . $e->getMessage(), 500);
}

idf_ok();
