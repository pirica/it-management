<?php

if (!defined('IDF_INCLUDE_HELPERS_ONLY')) {
    define('IDF_INCLUDE_HELPERS_ONLY', true);
    require_once __DIR__ . '/api/_bootstrap.php';
} elseif (!function_exists('idf_resolve_port_type_id')) {
    require_once __DIR__ . '/api/_bootstrap.php';
}

/**
 * Build the port slots that should exist for a rack position (RJ45 capacity, SFP capacity, and live switch_ports).
 *
 * @return array<string, array{port_no:int, port_type:int}>
 */
function idf_collect_port_slots_for_position(mysqli $conn, int $company_id, array $positionRow): array
{
    $positionId = (int)($positionRow['id'] ?? 0);
    if ($positionId <= 0) {
        return [];
    }

    $rj45Count = (int)($positionRow['rj45_count'] ?? 0);
    $sfpCount = (int)($positionRow['sfp_count'] ?? 0);
    $equipmentId = idf_parse_linked_equipment_id($positionRow['equipment_id'] ?? '');

    $rj45PortTypeId = idf_resolve_port_type_id($conn, $company_id, 'RJ45', 'RJ45');
    if ($rj45PortTypeId <= 0) {
        return [];
    }

    $portSlots = [];
    if ($rj45Count > 0) {
        for ($portNo = 1; $portNo <= $rj45Count; $portNo++) {
            $portSlots[$rj45PortTypeId . ':' . $portNo] = [
                'port_no' => $portNo,
                'port_type' => $rj45PortTypeId,
            ];
        }
    }

    $fiberPortsNumberId = 0;
    $switchPortNumberingLayoutId = (int)($positionRow['switch_port_numbering_layout_id'] ?? 0);

    if ($equipmentId > 0) {
        $stmtAllSwitchPorts = mysqli_prepare(
            $conn,
            "SELECT sp.port_number,
                    COALESCE(spt.id, 0) AS port_type_id,
                    COALESCE(spt.type, sp.port_type) AS port_type_name
             FROM switch_ports sp
             LEFT JOIN switch_port_types spt
               ON spt.company_id = sp.company_id
              AND (
                   spt.type = sp.port_type
                   OR spt.id = CAST(sp.port_type AS UNSIGNED)
              )
             WHERE sp.company_id = ? AND sp.equipment_id = ?
             ORDER BY sp.port_number ASC"
        );
        if ($stmtAllSwitchPorts) {
            mysqli_stmt_bind_param($stmtAllSwitchPorts, 'ii', $company_id, $equipmentId);
            mysqli_stmt_execute($stmtAllSwitchPorts);
            $resAllSwitchPorts = mysqli_stmt_get_result($stmtAllSwitchPorts);
            while ($resAllSwitchPorts && ($switchPortRow = mysqli_fetch_assoc($resAllSwitchPorts))) {
                $portNo = (int)($switchPortRow['port_number'] ?? 0);
                if ($portNo <= 0) {
                    continue;
                }
                $portTypeId = (int)($switchPortRow['port_type_id'] ?? 0);
                $portTypeName = trim((string)($switchPortRow['port_type_name'] ?? ''));
                if ($portTypeId <= 0) {
                    $portTypeId = idf_resolve_port_type_id($conn, $company_id, $portTypeName, 'RJ45');
                }
                if ($portTypeId <= 0) {
                    continue;
                }
                $portSlots[$portTypeId . ':' . $portNo] = [
                    'port_no' => $portNo,
                    'port_type' => $portTypeId,
                ];
            }
            mysqli_stmt_close($stmtAllSwitchPorts);
        }

        $stmtPortMeta = mysqli_prepare(
            $conn,
            "SELECT COALESCE(e.switch_fiber_ports_number, '') AS switch_fiber_ports_number,
                    COALESCE(e.switch_port_numbering_layout_id, 0) AS equipment_layout_id,
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
            }
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
                            $fiberKey = $fiberTypeId . ':' . $fiberPortNo;
                            if (!isset($portSlots[$fiberKey])) {
                                $portSlots[$fiberKey] = [
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
                $fiberKey = $fiberTypeId . ':' . $fiberPortNo;
                $portSlots[$fiberKey] = [
                    'port_no' => $fiberPortNo,
                    'port_type' => $fiberTypeId,
                ];
            }
        }
    }

    $positionRow['_fiber_ports_number_id'] = $fiberPortsNumberId;
    $positionRow['_switch_port_numbering_layout_id'] = $switchPortNumberingLayoutId;

    return $portSlots;
}

/**
 * Resolve the equipment that owns switch_ports for a rack position (declared link, port match, or IDF).
 */
function idf_resolve_position_equipment_id(
    mysqli $conn,
    int $company_id,
    int $positionId,
    int $positionNo,
    int $idfId,
    int $declaredEquipmentId
): int {
    if ($declaredEquipmentId > 0) {
        return $declaredEquipmentId;
    }
    if ($company_id <= 0 || $positionId <= 0) {
        return 0;
    }

    $positionKeys = [$positionId];
    if ($positionNo > 0 && $positionNo !== $positionId) {
        $positionKeys[] = $positionNo;
    }
    $positionKeyList = implode(',', array_map('intval', $positionKeys));

    $resByPorts = mysqli_query(
        $conn,
        "SELECT sp.equipment_id, COUNT(*) AS match_count
         FROM switch_ports sp
         INNER JOIN idf_ports pr
           ON pr.company_id = sp.company_id
          AND pr.port_no = sp.port_number
         WHERE sp.company_id = {$company_id}
           AND sp.equipment_id IS NOT NULL
           AND sp.equipment_id > 0
           AND pr.position_id IN ({$positionKeyList})
         GROUP BY sp.equipment_id
         ORDER BY match_count DESC, sp.equipment_id ASC
         LIMIT 1"
    );
    if ($resByPorts && ($row = mysqli_fetch_assoc($resByPorts))) {
        $equipmentId = (int)($row['equipment_id'] ?? 0);
        if ($equipmentId > 0) {
            return $equipmentId;
        }
    }

    if ($idfId > 0) {
        $stmtByIdf = mysqli_prepare(
            $conn,
            'SELECT sp.equipment_id, COUNT(*) AS match_count
             FROM switch_ports sp
             WHERE sp.company_id = ?
               AND sp.equipment_id IS NOT NULL
               AND sp.equipment_id > 0
               AND sp.idf_id = ?
             GROUP BY sp.equipment_id
             ORDER BY match_count DESC, sp.equipment_id ASC
             LIMIT 1'
        );
        if ($stmtByIdf) {
            mysqli_stmt_bind_param($stmtByIdf, 'ii', $company_id, $idfId);
            mysqli_stmt_execute($stmtByIdf);
            $resByIdf = mysqli_stmt_get_result($stmtByIdf);
            $rowByIdf = $resByIdf ? mysqli_fetch_assoc($resByIdf) : null;
            mysqli_stmt_close($stmtByIdf);
            $equipmentId = (int)($rowByIdf['equipment_id'] ?? 0);
            if ($equipmentId > 0) {
                return $equipmentId;
            }
        }
    }

    return 0;
}

/**
 * Fill missing effective_* / display fields on an IDF port row from a switch_ports row.
 */
function idf_merge_switch_port_metadata_into_port_row(array &$portRow, array $switchRow, int $equipmentDefaultPoeId = 0): void
{
    $pickEffective = static function (string $effectiveKey, string $switchKey) use (&$portRow, $switchRow): void {
        $current = (int)($portRow[$effectiveKey] ?? 0);
        $incoming = (int)($switchRow[$switchKey] ?? 0);
        if ($current <= 0 && $incoming > 0) {
            $portRow[$effectiveKey] = $incoming;
        }
    };

    $pickEffective('effective_status_id', 'status_id');
    $pickEffective('effective_vlan_id', 'vlan_id');
    $pickEffective('effective_rj45_speed_id', 'rj45_speed_id');
    $pickEffective('effective_fiber_port_id', 'fiber_port_id');
    $pickEffective('effective_fiber_patch_id', 'fiber_patch_id');
    $pickEffective('effective_fiber_rack_id', 'fiber_rack_id');
    $pickEffective('effective_to_idf_id', 'to_idf_id');
    $pickEffective('effective_to_rack_id', 'to_rack_id');
    $pickEffective('effective_to_location_id', 'to_location_id');

    // Why: Switch Port Manager historically stores home/physical routing in rack_id/location_id/idf_id while the IDF modal edits to_*; mirror equipment UI fallbacks (to_idf_id || idf_id).
    if ((int)($portRow['effective_to_idf_id'] ?? 0) <= 0) {
        $fallbackIdf = (int)($switchRow['idf_id'] ?? 0);
        if ($fallbackIdf > 0) {
            $portRow['effective_to_idf_id'] = $fallbackIdf;
        }
    }
    if ((int)($portRow['effective_to_rack_id'] ?? 0) <= 0) {
        $fallbackRack = (int)($switchRow['rack_id'] ?? 0);
        if ($fallbackRack > 0) {
            $portRow['effective_to_rack_id'] = $fallbackRack;
        }
    }
    if ((int)($portRow['effective_to_location_id'] ?? 0) <= 0) {
        $fallbackLoc = (int)($switchRow['location_id'] ?? 0);
        if ($fallbackLoc > 0) {
            $portRow['effective_to_location_id'] = $fallbackLoc;
        }
    }

    if ((int)($portRow['effective_poe_id'] ?? 0) <= 0) {
        $switchPoeId = (int)($switchRow['poe_id'] ?? 0);
        if ($switchPoeId > 0) {
            $portRow['effective_poe_id'] = $switchPoeId;
        } elseif ($equipmentDefaultPoeId > 0) {
            $portRow['effective_poe_id'] = $equipmentDefaultPoeId;
        }
    }

    $switchLabel = trim((string)($switchRow['switch_label'] ?? ''));
    if ($switchLabel !== '' && $switchLabel !== '0') {
        $currentLabel = trim((string)($portRow['label'] ?? ''));
        if ($currentLabel === '' || $currentLabel === '0') {
            $portRow['label'] = $switchLabel;
        }
    }

    $switchHostname = trim((string)($switchRow['hostname'] ?? ''));
    if ($switchHostname !== '') {
        $currentConnectedTo = trim((string)($portRow['connected_to'] ?? ''));
        if ($currentConnectedTo === '') {
            $portRow['connected_to'] = $switchHostname;
        }
    }

    $switchComments = trim((string)($switchRow['comments'] ?? ''));
    if ($switchComments !== '') {
        $currentNotes = trim((string)($portRow['notes'] ?? ''));
        if ($currentNotes === '') {
            $portRow['notes'] = $switchComments;
        }
    }

    $switchColorId = (int)($switchRow['color_id'] ?? 0);
    if ($switchColorId > 0 && (int)($portRow['cable_color_id'] ?? 0) <= 0) {
        $portRow['cable_color_id'] = $switchColorId;
    }
    $switchColorName = trim((string)($switchRow['color_name'] ?? ''));
    if ($switchColorName !== '') {
        $currentColorName = trim((string)($portRow['cable_color_name'] ?? ''));
        if ($currentColorName === '' || strcasecmp($currentColorName, 'Gray') === 0) {
            $portRow['cable_color_name'] = $switchColorName;
            $portRow['cable_color'] = $switchColorName;
        }
    }
    $switchHexColor = trim((string)($switchRow['switch_hex_color'] ?? ''));
    if ($switchHexColor !== '') {
        if (trim((string)($portRow['cable_hex_color'] ?? '')) === '') {
            $portRow['cable_hex_color'] = $switchHexColor;
        }
        if (trim((string)($portRow['status_color'] ?? '')) === '') {
            $portRow['status_color'] = $switchHexColor;
        }
    }
}

/**
 * Attach switch_ports.id and merge live switch_ports metadata (join can miss when port_type strings differ).
 */
function idf_attach_switch_port_ids_to_ports(
    mysqli $conn,
    int $company_id,
    int $equipmentId,
    array &$ports,
    int $equipmentDefaultPoeId = 0
): void {
    if ($company_id <= 0 || $equipmentId <= 0 || empty($ports)) {
        return;
    }

    $switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
    if ($switchPortLabelColumn === null) {
        $switchPortLabelColumn = 'to_patch_port';
    }
    $hasSwitchPortPoeColumn = idf_first_existing_column($conn, 'switch_ports', ['poe_id']) !== null;
    $switchPoeSelect = $hasSwitchPortPoeColumn ? 'sp.poe_id' : '0 AS poe_id';

    $stmtSwitchPorts = mysqli_prepare(
        $conn,
        "SELECT sp.id,
                sp.port_number,
                sp.status_id,
                sp.vlan_id,
                sp.rj45_speed_id,
                sp.fiber_port_id,
                sp.fiber_patch_id,
                sp.fiber_rack_id,
                sp.idf_id,
                sp.rack_id,
                sp.location_id,
                sp.to_idf_id,
                sp.to_rack_id,
                sp.to_location_id,
                sp.color_id,
                {$switchPoeSelect},
                sp.{$switchPortLabelColumn} AS switch_label,
                sp.hostname,
                sp.comments,
                COALESCE(NULLIF(cc_sp.color_name, ''), '') AS color_name,
                COALESCE(spt.id, 0) AS port_type_id,
                LOWER(TRIM(COALESCE(spt.type, sp.port_type, 'RJ45'))) AS port_type_name,
                COALESCE(NULLIF(cc_sp.hex_color, ''), NULLIF(cc_ss.hex_color, ''), '') AS switch_hex_color
         FROM switch_ports sp
         LEFT JOIN switch_port_types spt
           ON spt.company_id = sp.company_id
          AND (
               spt.type = sp.port_type
               OR spt.id = CAST(sp.port_type AS UNSIGNED)
          )
         LEFT JOIN cable_colors cc_sp
           ON cc_sp.id = sp.color_id
          AND cc_sp.company_id = sp.company_id
         LEFT JOIN switch_status ss
           ON ss.id = sp.status_id
          AND ss.company_id = sp.company_id
         LEFT JOIN cable_colors cc_ss
           ON cc_ss.id = ss.color_id
          AND cc_ss.company_id = ss.company_id
         WHERE sp.company_id = ? AND sp.equipment_id = ?"
    );
    if (!$stmtSwitchPorts) {
        return;
    }

    mysqli_stmt_bind_param($stmtSwitchPorts, 'ii', $company_id, $equipmentId);
    mysqli_stmt_execute($stmtSwitchPorts);
    $resSwitchPorts = mysqli_stmt_get_result($stmtSwitchPorts);
    $switchPortsByKey = [];
    $switchPortsByNumber = [];
    $switchMetaById = [];
    while ($resSwitchPorts && ($switchPortRow = mysqli_fetch_assoc($resSwitchPorts))) {
        $switchPortId = (int)($switchPortRow['id'] ?? 0);
        $portNo = (int)($switchPortRow['port_number'] ?? 0);
        $portTypeId = (int)($switchPortRow['port_type_id'] ?? 0);
        $portTypeName = strtolower(trim((string)($switchPortRow['port_type_name'] ?? '')));
        if ($switchPortId <= 0 || $portNo <= 0) {
            continue;
        }
        $switchMetaById[$switchPortId] = $switchPortRow;
        $switchPortsByKey[$portTypeId . ':' . $portNo] = $switchPortId;
        $switchPortsByKey[$portTypeName . ':' . $portNo] = $switchPortId;
        if (!isset($switchPortsByNumber[$portNo])) {
            $switchPortsByNumber[$portNo] = $switchPortId;
        }
    }
    mysqli_stmt_close($stmtSwitchPorts);

    foreach ($ports as &$portRow) {
        $portNo = (int)($portRow['port_no'] ?? 0);
        if ($portNo <= 0) {
            continue;
        }
        $portTypeId = (int)($portRow['port_type'] ?? 0);
        $portTypeLabel = strtolower(trim((string)($portRow['port_type_label'] ?? '')));
        $resolvedSwitchPortId = (int)($portRow['switch_port_live_id'] ?? 0);
        if ($resolvedSwitchPortId <= 0) {
            if ($portTypeId > 0 && isset($switchPortsByKey[$portTypeId . ':' . $portNo])) {
                $resolvedSwitchPortId = (int)$switchPortsByKey[$portTypeId . ':' . $portNo];
            } elseif ($portTypeLabel !== '' && isset($switchPortsByKey[$portTypeLabel . ':' . $portNo])) {
                $resolvedSwitchPortId = (int)$switchPortsByKey[$portTypeLabel . ':' . $portNo];
            } elseif (isset($switchPortsByNumber[$portNo])) {
                $resolvedSwitchPortId = (int)$switchPortsByNumber[$portNo];
            }
            if ($resolvedSwitchPortId > 0) {
                $portRow['switch_port_live_id'] = $resolvedSwitchPortId;
            }
            if ($resolvedSwitchPortId > 0) {
                $portRow['switch_port_live_id'] = $resolvedSwitchPortId;
            }
        }
        if ($resolvedSwitchPortId > 0 && isset($switchMetaById[$resolvedSwitchPortId])) {
            idf_merge_switch_port_metadata_into_port_row(
                $portRow,
                $switchMetaById[$resolvedSwitchPortId],
                $equipmentDefaultPoeId
            );
        }
        if ($resolvedSwitchPortId > 0 && isset($switchMetaById[$resolvedSwitchPortId])) {
            idf_merge_switch_port_metadata_into_port_row(
                $portRow,
                $switchMetaById[$resolvedSwitchPortId],
                $equipmentDefaultPoeId
            );
        }
    }
    unset($portRow);
}

/**
 * Ensure port visualizer rows always expose DB cable/status hex colors.
 */
function idf_normalize_port_visualizer_colors(array &$ports): void
{
    foreach ($ports as &$portRow) {
        $cableHex = trim((string)($portRow['cable_hex_color'] ?? ''));
        $statusColor = trim((string)($portRow['status_color'] ?? ''));
        $portHex = trim((string)($portRow['hex_color'] ?? ''));
        if ($cableHex === '' && $portHex !== '') {
            $cableHex = $portHex;
        }
        if ($statusColor === '') {
            $statusColor = $cableHex;
        }
        if ($cableHex === '' && $statusColor === '') {
            $cableHex = '#808080';
            $statusColor = '#808080';
        } elseif ($cableHex === '') {
            $cableHex = $statusColor;
        } elseif ($statusColor === '') {
            $statusColor = $cableHex;
        }
        $portRow['cable_hex_color'] = $cableHex;
        $portRow['status_color'] = $statusColor;
    }
    unset($portRow);
}

/**
 * Re-home IDF ports that belong to this equipment's switch_ports but were saved on another position_id.
 */
function idf_claim_switch_ports_for_position(mysqli $conn, int $company_id, int $positionId, int $equipmentId): int
{
    if ($company_id <= 0 || $positionId <= 0 || $equipmentId <= 0) {
        return 0;
    }

    $stmtClaim = mysqli_prepare(
        $conn,
        "UPDATE idf_ports pr
         INNER JOIN switch_ports sp
           ON sp.company_id = pr.company_id
          AND sp.equipment_id = ?
          AND sp.port_number = pr.port_no
         SET pr.position_id = ?
         WHERE pr.company_id = ?
           AND pr.position_id <> ?"
    );
    if (!$stmtClaim) {
        return 0;
    }
    mysqli_stmt_bind_param($stmtClaim, 'iiii', $equipmentId, $positionId, $company_id, $positionId);
    mysqli_stmt_execute($stmtClaim);
    $claimed = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmtClaim);

    return $claimed > 0 ? $claimed : 0;
}

/**
 * Minimal port list when the full device query returns no rows (legacy position_id / missing joins).
 *
 * @return array<int, array<string, mixed>>
 */
function idf_fetch_position_ports_simple(mysqli $conn, int $company_id, int $positionId, int $positionNo): array
{
    if ($company_id <= 0 || $positionId <= 0) {
        return [];
    }

    $positionIds = [$positionId];
    if ($positionNo > 0 && $positionNo !== $positionId) {
        $positionIds[] = $positionNo;
    }
    $positionIdList = implode(',', array_map('intval', $positionIds));

    $res = mysqli_query(
        $conn,
        "SELECT pr.*,
                COALESCE(spt.type, spt_any.type, 'RJ45') AS port_type_label,
                COALESCE(ss.status, 'Unknown') AS status_label,
                COALESCE(NULLIF(cc_ss.hex_color, ''), NULLIF(pr.hex_color, ''), '#808080') AS status_color,
                COALESCE(NULLIF(pr.hex_color, ''), NULLIF(cc_ss.hex_color, ''), '#808080') AS cable_hex_color,
                pr.status_id AS effective_status_id,
                COALESCE(pr.vlan_id, 0) AS effective_vlan_id,
                COALESCE(pr.poe_id, 0) AS effective_poe_id,
                COALESCE(pr.rj45_speed_id, 0) AS effective_rj45_speed_id,
                COALESCE(pr.speed_id, 0) AS effective_fiber_port_id
         FROM idf_ports pr
         LEFT JOIN switch_port_types spt
           ON spt.id = pr.port_type AND spt.company_id = pr.company_id
         LEFT JOIN switch_port_types spt_any
           ON spt_any.id = pr.port_type
         LEFT JOIN switch_status ss
           ON ss.id = pr.status_id AND ss.company_id = pr.company_id
         LEFT JOIN cable_colors cc_ss
           ON cc_ss.id = ss.color_id
          AND cc_ss.company_id = pr.company_id
         WHERE pr.company_id = {$company_id}
           AND pr.position_id IN ({$positionIdList})
         ORDER BY pr.port_no ASC"
    );

    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Move legacy IDF ports that were keyed by position_no onto the real position id when safe.
 */
function idf_repair_legacy_position_port_ids(mysqli $conn, int $company_id, int $positionId, int $positionNo): int
{
    if ($positionId <= 0 || $positionNo <= 0 || $positionId === $positionNo) {
        return 0;
    }

    $stmtRepair = mysqli_prepare(
        $conn,
        "UPDATE idf_ports pr
         SET pr.position_id = ?
         WHERE pr.company_id = ?
           AND pr.position_id = ?
           AND NOT EXISTS (
               SELECT 1
               FROM idf_positions p_actual
               WHERE p_actual.company_id = pr.company_id
                 AND p_actual.id = ?
               LIMIT 1
           )"
    );
    if (!$stmtRepair) {
        return 0;
    }
    mysqli_stmt_bind_param($stmtRepair, 'iiii', $positionId, $company_id, $positionNo, $positionNo);
    mysqli_stmt_execute($stmtRepair);
    $repaired = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmtRepair);

    return $repaired > 0 ? $repaired : 0;
}

/**
 * Insert missing idf_ports rows for a position without deleting switch_ports.
 */
function idf_ensure_ports_for_position(mysqli $conn, int $company_id, int $positionId): int
{
    if ($company_id <= 0 || $positionId <= 0) {
        return 0;
    }

    $stmtPosition = mysqli_prepare(
        $conn,
        "SELECT p.id, p.position_no, p.rj45_count, p.sfp_count, p.equipment_id, p.switch_port_numbering_layout_id
         FROM idf_positions p
         JOIN idfs i ON i.id = p.idf_id
         WHERE p.id = ? AND i.company_id = ?
         LIMIT 1"
    );
    if (!$stmtPosition) {
        return 0;
    }
    mysqli_stmt_bind_param($stmtPosition, 'ii', $positionId, $company_id);
    mysqli_stmt_execute($stmtPosition);
    $resPosition = mysqli_stmt_get_result($stmtPosition);
    $positionRow = $resPosition ? mysqli_fetch_assoc($resPosition) : null;
    mysqli_stmt_close($stmtPosition);
    if (!$positionRow) {
        return 0;
    }

    $positionNo = (int)($positionRow['position_no'] ?? 0);
    $equipmentId = idf_parse_linked_equipment_id($positionRow['equipment_id'] ?? '');

    idf_repair_legacy_position_port_ids($conn, $company_id, $positionId, $positionNo);
    $claimed = 0;
    if ($equipmentId > 0) {
        $claimed = idf_claim_switch_ports_for_position($conn, $company_id, $positionId, $equipmentId);
    }

    $portSlots = idf_collect_port_slots_for_position($conn, $company_id, $positionRow);
    if (empty($portSlots)) {
        return $claimed;
    }

    $unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
    if ($unknownStatusId <= 0) {
        return 0;
    }

    $fiberPortsNumberId = (int)($positionRow['_fiber_ports_number_id'] ?? 0);
    $switchPortNumberingLayoutId = (int)($positionRow['_switch_port_numbering_layout_id'] ?? 0);
    $managementId = 0;

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

    $inserted = 0;
    $stmtInsert = mysqli_prepare(
        $conn,
        "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status_id, fiber_ports_number, switch_port_numbering_layout_id, management_id)
         VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0))
         ON DUPLICATE KEY UPDATE port_no = port_no"
    );
    if (!$stmtInsert) {
        return 0;
    }

    ksort($portSlots, SORT_NATURAL);
    foreach ($portSlots as $portMeta) {
        $portNo = (int)($portMeta['port_no'] ?? 0);
        $portTypeId = (int)($portMeta['port_type'] ?? 0);
        if ($portNo <= 0 || $portTypeId <= 0) {
            continue;
        }
        mysqli_stmt_bind_param(
            $stmtInsert,
            'iiiiiiii',
            $company_id,
            $positionId,
            $portNo,
            $portTypeId,
            $unknownStatusId,
            $fiberPortsNumberId,
            $switchPortNumberingLayoutId,
            $managementId
        );
        mysqli_stmt_execute($stmtInsert);
        if (mysqli_affected_rows($conn) === 1) {
            $inserted++;
        }
    }
    mysqli_stmt_close($stmtInsert);

    return $inserted + $claimed;
}
