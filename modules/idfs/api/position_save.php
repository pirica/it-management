<?php
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/idf_ports_sync.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$position_no = (int)($data['position_no'] ?? 0);
$position_id = isset($data['position_id']) ? (int)$data['position_id'] : 0;

$device_type_raw = trim((string)($data['device_type'] ?? ''));
$device_name = trim((string)($data['device_name'] ?? ''));
$linkedEquipmentId = isset($data['equipment_id']) ? idf_parse_linked_equipment_id($data['equipment_id']) : 0;
$isIntentionallyUnlinked = $linkedEquipmentId <= 0;
$equipment_id = $linkedEquipmentId;
$switch_rj45_id = isset($data['switch_rj45_id']) ? (int)$data['switch_rj45_id'] : 0;
$layoutSubmitted = array_key_exists('switch_port_numbering_layout_id', $data);
$layout_id = 0;
if ($layoutSubmitted) {
    $layout_id = idf_normalize_switch_port_numbering_layout_id($conn, $company_id, $data['switch_port_numbering_layout_id']);
}
$switch_fiber_ports_number_submitted = array_key_exists('switch_fiber_ports_number', $data);
$switch_fiber_ports_number = $switch_fiber_ports_number_submitted ? substr(trim((string)($data['switch_fiber_ports_number'] ?? '')), 0, 50) : '';
if ($switch_fiber_ports_number === '__add_new__') {
    $switch_fiber_ports_number = '';
}
$rj45_count = 0;
if (isset($data['rj45_count'])) {
    $rj45_count = (int)$data['rj45_count'];
} elseif (isset($data['port_count'])) {
    $rj45_count = (int)$data['port_count'];
}
$sfp_count = 0;
if ($switch_fiber_ports_number_submitted && $switch_fiber_ports_number === '') {
    $sfp_count = 0;
} elseif (isset($data['sfp_count'])) {
    $sfp_count = max(0, min(9999, (int)$data['sfp_count']));
} elseif ($switch_fiber_ports_number !== '' && preg_match('/^\d+$/', $switch_fiber_ports_number)) {
    $sfp_count = (int)$switch_fiber_ports_number;
}
$notes = trim((string)($data['notes'] ?? ''));
$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}

if (!function_exists('idf_prune_position_port_capacity')) {
    function idf_prune_position_port_capacity(
        mysqli $conn,
        int $companyId,
        int $positionId,
        int $rj45Count,
        int $sfpCount,
        int $rj45PortTypeId,
        int $equipmentId = 0
    ): void {
        if ($companyId <= 0 || $positionId <= 0) {
            return;
        }

        if ($rj45PortTypeId > 0) {
            if ($rj45Count > 0) {
                $stmtDeleteExtraRj45 = mysqli_prepare(
                    $conn,
                    "DELETE FROM idf_ports
                     WHERE company_id = ?
                       AND position_id = ?
                       AND port_type = ?
                       AND port_no > ?"
                );
                if ($stmtDeleteExtraRj45) {
                    mysqli_stmt_bind_param($stmtDeleteExtraRj45, 'iiii', $companyId, $positionId, $rj45PortTypeId, $rj45Count);
                    mysqli_stmt_execute($stmtDeleteExtraRj45);
                    mysqli_stmt_close($stmtDeleteExtraRj45);
                }
            } else {
                $stmtDeleteAllRj45 = mysqli_prepare(
                    $conn,
                    "DELETE FROM idf_ports
                     WHERE company_id = ?
                       AND position_id = ?
                       AND port_type = ?"
                );
                if ($stmtDeleteAllRj45) {
                    mysqli_stmt_bind_param($stmtDeleteAllRj45, 'iii', $companyId, $positionId, $rj45PortTypeId);
                    mysqli_stmt_execute($stmtDeleteAllRj45);
                    mysqli_stmt_close($stmtDeleteAllRj45);
                }
            }
        }

        if ($sfpCount > 0) {
            // Why: SFP port_no is per port_type (1..N); also drop legacy RJ45-tail slots (for example 9–10 after eight RJ45 ports).
            $fiberBaselinePrune = max(0, (int)$rj45Count);
            if ($fiberBaselinePrune <= 0 && $equipmentId > 0) {
                $fiberBaselinePrune = idf_equipment_switch_rj45_capacity_hint($conn, $companyId, $equipmentId);
            }
            $fiberLegacyTailCeiling = ($fiberBaselinePrune > 0)
                ? ($fiberBaselinePrune + $sfpCount)
                : 0;

            $stmtDeleteExtraSfp = mysqli_prepare(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = ?
                   AND position_id = ?
                   AND port_type IN (
                        SELECT id
                        FROM switch_port_types
                        WHERE company_id = ?
                          AND LOWER(type) LIKE '%sfp%'
                   )
                   AND (
                        port_no > ?
                        OR (? > 0 AND port_no > ?)
                   )"
            );
            if ($stmtDeleteExtraSfp) {
                mysqli_stmt_bind_param(
                    $stmtDeleteExtraSfp,
                    'iiiiii',
                    $companyId,
                    $positionId,
                    $companyId,
                    $sfpCount,
                    $fiberLegacyTailCeiling,
                    $fiberLegacyTailCeiling
                );
                mysqli_stmt_execute($stmtDeleteExtraSfp);
                mysqli_stmt_close($stmtDeleteExtraSfp);
            }
        } else {
            $stmtDeleteAllSfp = mysqli_prepare(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = ?
                   AND position_id = ?
                   AND port_type IN (
                        SELECT id
                        FROM switch_port_types
                        WHERE company_id = ?
                          AND LOWER(type) LIKE '%sfp%'
                   )"
            );
            if ($stmtDeleteAllSfp) {
                mysqli_stmt_bind_param($stmtDeleteAllSfp, 'iii', $companyId, $positionId, $companyId);
                mysqli_stmt_execute($stmtDeleteAllSfp);
                mysqli_stmt_close($stmtDeleteAllSfp);
            }
        }
    }
}

if (!function_exists('idf_ensure_equipment_fiber_count_id')) {
    function idf_ensure_equipment_fiber_count_id(mysqli $conn, int $companyId, string $fiberPortsNumber): int
    {
        $fiberPortsNumber = trim($fiberPortsNumber);
        if ($companyId <= 0 || $fiberPortsNumber === '') {
            return 0;
        }

        $stmtLookup = mysqli_prepare(
            $conn,
            "SELECT id
             FROM equipment_fiber_count
             WHERE company_id = ? AND name = ?
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtLookup) {
            mysqli_stmt_bind_param($stmtLookup, 'is', $companyId, $fiberPortsNumber);
            mysqli_stmt_execute($stmtLookup);
            $resLookup = mysqli_stmt_get_result($stmtLookup);
            $rowLookup = $resLookup ? mysqli_fetch_assoc($resLookup) : null;
            mysqli_stmt_close($stmtLookup);
            $existingId = (int)($rowLookup['id'] ?? 0);
            if ($existingId > 0) {
                return $existingId;
            }
        }

        $stmtInsert = mysqli_prepare(
            $conn,
            "INSERT INTO equipment_fiber_count (company_id, name)
             VALUES (?, ?)"
        );
        if (!$stmtInsert) {
            idf_fail('DB error preparing fiber count lookup creation', 500);
        }
        mysqli_stmt_bind_param($stmtInsert, 'is', $companyId, $fiberPortsNumber);
        if (!mysqli_stmt_execute($stmtInsert)) {
            $err = mysqli_stmt_error($stmtInsert);
            mysqli_stmt_close($stmtInsert);
            idf_fail('DB error creating fiber count lookup: ' . $err, 500);
        }
        $createdId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmtInsert);
        return $createdId;
    }
}

if (!function_exists('idf_sync_switch_rj45_capacity')) {
    function idf_sync_switch_rj45_capacity(
        mysqli $conn,
        int $companyId,
        int $equipmentId,
        int $idfId,
        int $targetPortCount,
        int $rj45PortTypeId,
        string $rj45PortTypeName,
        int $unknownStatusId,
        int $defaultColorId,
        int $managementId,
        string $hostname,
        string $labelColumn,
        bool $hasManagementColumn,
        bool $hasCommentsColumn
    ): void {
        if ($companyId <= 0 || $equipmentId <= 0 || $targetPortCount < 0 || $rj45PortTypeId <= 0) {
            return;
        }

        // Why: RJ45 capacity edits in rack modal must shrink stale switch rows above the selected capacity.
        $stmtDeleteExtra = mysqli_prepare(
            $conn,
            "DELETE FROM switch_ports
             WHERE company_id = ?
               AND equipment_id = ?
               AND port_number > ?
               AND (
                    LOWER(TRIM(COALESCE(port_type, ''))) = LOWER(?)
                    OR (
                        port_type REGEXP '^[0-9]+$'
                        AND CAST(port_type AS UNSIGNED) = ?
                    )
               )"
        );
        if (!$stmtDeleteExtra) {
            idf_fail('DB error preparing switch RJ45 capacity sync', 500);
        }
        mysqli_stmt_bind_param($stmtDeleteExtra, 'iiisi', $companyId, $equipmentId, $targetPortCount, $rj45PortTypeName, $rj45PortTypeId);
        if (!mysqli_stmt_execute($stmtDeleteExtra)) {
            $err = mysqli_stmt_error($stmtDeleteExtra);
            mysqli_stmt_close($stmtDeleteExtra);
            idf_fail('DB error deleting extra switch RJ45 ports: ' . $err, 500);
        }
        mysqli_stmt_close($stmtDeleteExtra);

        if ($targetPortCount <= 0) {
            return;
        }

        $insertColumns = [
            'company_id',
            'equipment_id',
            'hostname',
            'port_type',
            'port_number',
            $labelColumn,
            'status_id',
            'color_id',
            'idf_id',
        ];
        $insertValues = [
            '?',
            '?',
            'NULLIF(?, \'\')',
            '?',
            '?',
            '?',
            '?',
            '?',
            'NULLIF(?, 0)',
        ];
        $bindTypes = 'iissisiii';

        if ($hasManagementColumn) {
            $insertColumns[] = 'management_id';
            $insertValues[] = 'NULLIF(?, 0)';
            $bindTypes .= 'i';
        }
        if ($hasCommentsColumn) {
            $insertColumns[] = 'comments';
            $insertValues[] = '?';
            $bindTypes .= 's';
        }

        $updateAssignments = [
            // Why: Re-selecting RJ45 presets can upsert overlapping port_numbers that were previously synthesized as fiber; flip port_family on duplicate unique keys instead of accumulating parallel rows.
            'port_type = VALUES(port_type)',
            'idf_id = COALESCE(VALUES(idf_id), switch_ports.idf_id)',
            'hostname = COALESCE(VALUES(hostname), switch_ports.hostname)',
        ];
        if ($hasManagementColumn) {
            $updateAssignments[] = 'management_id = COALESCE(VALUES(management_id), switch_ports.management_id)';
        }

        $insertSql = "INSERT INTO switch_ports (" . implode(', ', $insertColumns) . ")
                      VALUES (" . implode(', ', $insertValues) . ")
                      ON DUPLICATE KEY UPDATE
                      " . implode(",\n                      ", $updateAssignments);

        $stmtUpsert = mysqli_prepare($conn, $insertSql);
        if (!$stmtUpsert) {
            idf_fail('DB error preparing switch RJ45 upsert', 500);
        }

        for ($portNo = 1; $portNo <= $targetPortCount; $portNo++) {
            $defaultLabel = '';
            $defaultComments = '';

            if ($hasManagementColumn && $hasCommentsColumn) {
                mysqli_stmt_bind_param(
                    $stmtUpsert,
                    $bindTypes,
                    $companyId,
                    $equipmentId,
                    $hostname,
                    $rj45PortTypeName,
                    $portNo,
                    $defaultLabel,
                    $unknownStatusId,
                    $defaultColorId,
                    $idfId,
                    $managementId,
                    $defaultComments
                );
            } elseif ($hasManagementColumn) {
                mysqli_stmt_bind_param(
                    $stmtUpsert,
                    $bindTypes,
                    $companyId,
                    $equipmentId,
                    $hostname,
                    $rj45PortTypeName,
                    $portNo,
                    $defaultLabel,
                    $unknownStatusId,
                    $defaultColorId,
                    $idfId,
                    $managementId
                );
            } elseif ($hasCommentsColumn) {
                mysqli_stmt_bind_param(
                    $stmtUpsert,
                    $bindTypes,
                    $companyId,
                    $equipmentId,
                    $hostname,
                    $rj45PortTypeName,
                    $portNo,
                    $defaultLabel,
                    $unknownStatusId,
                    $defaultColorId,
                    $idfId,
                    $defaultComments
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmtUpsert,
                    $bindTypes,
                    $companyId,
                    $equipmentId,
                    $hostname,
                    $rj45PortTypeName,
                    $portNo,
                    $defaultLabel,
                    $unknownStatusId,
                    $defaultColorId,
                    $idfId
                );
            }

            if (!mysqli_stmt_execute($stmtUpsert)) {
                $err = mysqli_stmt_error($stmtUpsert);
                mysqli_stmt_close($stmtUpsert);
                idf_fail('DB error upserting switch RJ45 port rows: ' . $err, 500);
            }
        }

        mysqli_stmt_close($stmtUpsert);
    }
}

if (!function_exists('idf_sync_switch_fiber_capacity')) {
    function idf_sync_switch_fiber_capacity(
        mysqli $conn,
        int $companyId,
        int $equipmentId,
        int $idfId,
        int $targetFiberCount,
        int $fiberPortTypeId,
        string $fiberPortTypeName,
        int $unknownStatusId,
        int $defaultColorId,
        int $managementId,
        int $fiberPhysicalNumberBase,
        string $hostname,
        string $labelColumn,
        bool $hasManagementColumn,
        bool $hasCommentsColumn
    ): void {
        if ($companyId <= 0 || $equipmentId <= 0 || $targetFiberCount < 0) {
            return;
        }

        // Why: Fiber count changes in the rack modal must remove stale SFP/SFP+ rows before the rack card is regenerated.
        $stmtDeleteExtra = mysqli_prepare(
            $conn,
            "DELETE sp
             FROM switch_ports sp
             LEFT JOIN switch_port_types spt
               ON spt.company_id = sp.company_id
              AND (
                    spt.type = sp.port_type
                    OR (
                        sp.port_type REGEXP '^[0-9]+$'
                        AND spt.id = CAST(sp.port_type AS UNSIGNED)
                    )
              )
             WHERE sp.company_id = ?
               AND sp.equipment_id = ?
               AND LOWER(TRIM(COALESCE(spt.type, sp.port_type, ''))) LIKE '%sfp%'
               AND (? <= 0 OR sp.port_number > ?)"
        );
        if (!$stmtDeleteExtra) {
            idf_fail('DB error preparing switch fiber capacity sync', 500);
        }
        $fiberCeilingExclusive = ($fiberPhysicalNumberBase > 0)
            ? ($fiberPhysicalNumberBase + $targetFiberCount)
            : ($targetFiberCount);
        mysqli_stmt_bind_param($stmtDeleteExtra, 'iiii', $companyId, $equipmentId, $targetFiberCount, $fiberCeilingExclusive);
        if (!mysqli_stmt_execute($stmtDeleteExtra)) {
            $err = mysqli_stmt_error($stmtDeleteExtra);
            mysqli_stmt_close($stmtDeleteExtra);
            idf_fail('DB error deleting extra switch fiber ports: ' . $err, 500);
        }
        mysqli_stmt_close($stmtDeleteExtra);

        // Why: After RJ45 growth, stale SFP/SFP+ may still occupy port_numbers inside the RJ45 footprint (composite unique permits SFP alongside RJ45 for the same number).
        // Those rows must disappear before inserting synthetic fiber ports after baseline+ordinal.
        if ($fiberPhysicalNumberBase > 0) {
            $stmtDeleteStaleFiberFootprint = mysqli_prepare(
                $conn,
                "DELETE sp
                 FROM switch_ports sp
                 LEFT JOIN switch_port_types spt
                   ON spt.company_id = sp.company_id
                  AND (
                        spt.type = sp.port_type
                        OR (
                            sp.port_type REGEXP '^[0-9]+$'
                            AND spt.id = CAST(sp.port_type AS UNSIGNED)
                        )
                  )
                 WHERE sp.company_id = ?
                   AND sp.equipment_id = ?
                   AND LOWER(TRIM(COALESCE(spt.type, sp.port_type, ''))) LIKE '%sfp%'
                   AND sp.port_number <= ?"
            );
            if (!$stmtDeleteStaleFiberFootprint) {
                idf_fail('DB error preparing switch fiber RJ45-footprint prune', 500);
            }
            mysqli_stmt_bind_param($stmtDeleteStaleFiberFootprint, 'iii', $companyId, $equipmentId, $fiberPhysicalNumberBase);
            if (!mysqli_stmt_execute($stmtDeleteStaleFiberFootprint)) {
                $err = mysqli_stmt_error($stmtDeleteStaleFiberFootprint);
                mysqli_stmt_close($stmtDeleteStaleFiberFootprint);
                idf_fail('DB error deleting switch fiber RJ45 footprint overlap: ' . $err, 500);
            }
            mysqli_stmt_close($stmtDeleteStaleFiberFootprint);
        }

        if ($targetFiberCount <= 0 || $fiberPortTypeId <= 0 || trim($fiberPortTypeName) === '') {
            return;
        }

        $insertColumns = [
            'company_id',
            'equipment_id',
            'hostname',
            'port_type',
            'port_number',
            $labelColumn,
            'status_id',
            'color_id',
            'idf_id',
        ];
        $insertValues = [
            '?',
            '?',
            'NULLIF(?, \'\')',
            '?',
            '?',
            '?',
            '?',
            '?',
            'NULLIF(?, 0)',
        ];
        $bindTypes = 'iissisiii';

        if ($hasManagementColumn) {
            $insertColumns[] = 'management_id';
            $insertValues[] = 'NULLIF(?, 0)';
            $bindTypes .= 'i';
        }
        if ($hasCommentsColumn) {
            $insertColumns[] = 'comments';
            $insertValues[] = '?';
            $bindTypes .= 's';
        }

        $updateAssignments = [
            'idf_id = COALESCE(VALUES(idf_id), switch_ports.idf_id)',
            'hostname = COALESCE(VALUES(hostname), switch_ports.hostname)',
        ];
        if ($hasManagementColumn) {
            $updateAssignments[] = 'management_id = COALESCE(VALUES(management_id), switch_ports.management_id)';
        }

        $insertSql = "INSERT INTO switch_ports (" . implode(', ', $insertColumns) . ")
                      VALUES (" . implode(', ', $insertValues) . ")
                      ON DUPLICATE KEY UPDATE
                      " . implode(",\n                      ", $updateAssignments);

        $stmtUpsert = mysqli_prepare($conn, $insertSql);
        if (!$stmtUpsert) {
            idf_fail('DB error preparing switch fiber upsert', 500);
        }

        for ($ordinal = 1; $ordinal <= $targetFiberCount; $ordinal++) {
            $fiberPhysicalPortNumber = idf_resolve_synthetic_fiber_port_no((int)$fiberPhysicalNumberBase, $ordinal);
            $defaultLabel = '';
            $defaultComments = '';

            if ($hasManagementColumn && $hasCommentsColumn) {
                mysqli_stmt_bind_param($stmtUpsert, $bindTypes, $companyId, $equipmentId, $hostname, $fiberPortTypeName, $fiberPhysicalPortNumber, $defaultLabel, $unknownStatusId, $defaultColorId, $idfId, $managementId, $defaultComments);
            } elseif ($hasManagementColumn) {
                mysqli_stmt_bind_param($stmtUpsert, $bindTypes, $companyId, $equipmentId, $hostname, $fiberPortTypeName, $fiberPhysicalPortNumber, $defaultLabel, $unknownStatusId, $defaultColorId, $idfId, $managementId);
            } elseif ($hasCommentsColumn) {
                mysqli_stmt_bind_param($stmtUpsert, $bindTypes, $companyId, $equipmentId, $hostname, $fiberPortTypeName, $fiberPhysicalPortNumber, $defaultLabel, $unknownStatusId, $defaultColorId, $idfId, $defaultComments);
            } else {
                mysqli_stmt_bind_param($stmtUpsert, $bindTypes, $companyId, $equipmentId, $hostname, $fiberPortTypeName, $fiberPhysicalPortNumber, $defaultLabel, $unknownStatusId, $defaultColorId, $idfId);
            }

            if (!mysqli_stmt_execute($stmtUpsert)) {
                $err = mysqli_stmt_error($stmtUpsert);
                mysqli_stmt_close($stmtUpsert);
                idf_fail('DB error upserting switch fiber port rows: ' . $err, 500);
            }
        }

        mysqli_stmt_close($stmtUpsert);
    }
}

if ($switch_rj45_id > 0) {
    $stmtSwitchRj45 = mysqli_prepare(
        $conn,
        "SELECT name
         FROM equipment_rj45
         WHERE id=? AND company_id=?
         LIMIT 1"
    );
    if ($stmtSwitchRj45) {
        mysqli_stmt_bind_param($stmtSwitchRj45, 'ii', $switch_rj45_id, $company_id);
        mysqli_stmt_execute($stmtSwitchRj45);
        $resSwitchRj45 = mysqli_stmt_get_result($stmtSwitchRj45);
        $switchRj45 = $resSwitchRj45 ? mysqli_fetch_assoc($resSwitchRj45) : null;
        mysqli_stmt_close($stmtSwitchRj45);

        if (!$switchRj45) {
            idf_fail('Invalid port count option');
        }
        if (!empty($switchRj45['name']) && preg_match('/(\d+)/', (string)$switchRj45['name'], $matches)) {
            $rj45_count = (int)$matches[1];
        }
    }
}

if ($idf_id <= 0 || $position_no < 1 || $position_no > 250) {
    idf_fail('Invalid idf_id/position_no');
}
if ($device_name === '') {
    idf_fail('Device name is required');
}
if ($rj45_count < 0 || $rj45_count > 9999) {
    idf_fail('Invalid rj45_count');
}
if ($sfp_count < 0 || $sfp_count > 9999) {
    idf_fail('Invalid sfp_count');
}
if ($switch_fiber_ports_number !== '' && (!preg_match('/^\d+$/', $switch_fiber_ports_number) || (int)$switch_fiber_ports_number < 1 || (int)$switch_fiber_ports_number > 9999)) {
    idf_fail('Invalid Fiber Ports Number');
}

$validTypeIdsByName = [];
$validTypeNamesById = [];
$stmtValidTypes = mysqli_prepare(
    $conn,
    "SELECT id, idfdevicetype_name
     FROM idf_device_type
     WHERE company_id=? AND active=1"
);
if ($stmtValidTypes) {
    mysqli_stmt_bind_param($stmtValidTypes, 'i', $company_id);
    mysqli_stmt_execute($stmtValidTypes);
    $resValidTypes = mysqli_stmt_get_result($stmtValidTypes);
    while ($resValidTypes && ($row = mysqli_fetch_assoc($resValidTypes))) {
        $typeId = (int)($row['id'] ?? 0);
        $typeName = strtolower(trim((string)($row['idfdevicetype_name'] ?? '')));
        if ($typeId > 0 && $typeName !== '') {
            $validTypeIdsByName[$typeName] = $typeId;
            $validTypeNamesById[$typeId] = $typeName;
        }
    }
    mysqli_stmt_close($stmtValidTypes);
}

if (!$validTypeIdsByName) {
    $validTypeIdsByName = [
        'switch' => 1,
        'patch_panel' => 2,
        'ups' => 3,
        'server' => 4,
        'other' => 5,
    ];
    $validTypeNamesById = array_flip($validTypeIdsByName);
}

$device_type_id = 0;
$device_type_name = '';
if ($device_type_raw !== '' && ctype_digit($device_type_raw)) {
    $candidateId = (int)$device_type_raw;
    if (isset($validTypeNamesById[$candidateId])) {
        $device_type_id = $candidateId;
        $device_type_name = (string)$validTypeNamesById[$candidateId];
    }
} else {
    $candidateName = strtolower($device_type_raw);
    if (isset($validTypeIdsByName[$candidateName])) {
        $device_type_id = (int)$validTypeIdsByName[$candidateName];
        $device_type_name = $candidateName;
    }
}

if ($device_type_id <= 0) {
    idf_fail('Invalid device_type');
}

if ($equipment_id > 0) {
    $stmtEquipment = mysqli_prepare(
        $conn,
        "SELECT e.name, e.notes, e.switch_rj45_id, e.switch_port_numbering_layout_id, e.switch_environment_id, e.switch_fiber_ports_number, er.name AS switch_rj45_name
         FROM equipment e
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         WHERE e.id=? AND e.company_id=?
         LIMIT 1"
    );
    if ($stmtEquipment) {
        mysqli_stmt_bind_param($stmtEquipment, 'ii', $equipment_id, $company_id);
        mysqli_stmt_execute($stmtEquipment);
        $resEquipment = mysqli_stmt_get_result($stmtEquipment);
        $equipment = $resEquipment ? mysqli_fetch_assoc($resEquipment) : null;
        mysqli_stmt_close($stmtEquipment);

        if (!$equipment) {
            idf_fail('Selected equipment not found', 404);
        }

        $existingEquipmentName = trim((string)($equipment['name'] ?? ''));
        if ($device_name === '') {
            $device_name = $existingEquipmentName;
        }

        if ($device_name !== '') {
            $stmtDuplicateEquipmentName = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM equipment
                 WHERE company_id = ?
                   AND LOWER(name) = LOWER(?)
                   AND id <> ?
                 LIMIT 1"
            );
            if ($stmtDuplicateEquipmentName) {
                mysqli_stmt_bind_param($stmtDuplicateEquipmentName, 'isi', $company_id, $device_name, $equipment_id);
                mysqli_stmt_execute($stmtDuplicateEquipmentName);
                $resDuplicateEquipmentName = mysqli_stmt_get_result($stmtDuplicateEquipmentName);
                $duplicateEquipmentNameExists = $resDuplicateEquipmentName && mysqli_num_rows($resDuplicateEquipmentName) > 0;
                mysqli_stmt_close($stmtDuplicateEquipmentName);
                if ($duplicateEquipmentNameExists) {
                    idf_fail('Equipment name already exists for this company.');
                }
            }
        }

        if ($device_type_name === 'switch') {
            if ($switch_rj45_id <= 0) {
                $switch_rj45_id = (int)($equipment['switch_rj45_id'] ?? 0);
                if (!empty($equipment['switch_rj45_name']) && preg_match('/(\d+)/', (string)$equipment['switch_rj45_name'], $matches)) {
                    $rj45_count = (int)$matches[1];
                }
            }
        } elseif ($switch_rj45_id <= 0) {
            // Why: preserve linked equipment metadata in sync updates without forcing switch port presets into non-switch IDF entries.
            $switch_rj45_id = (int)($equipment['switch_rj45_id'] ?? 0);
        }
        if (!$layoutSubmitted && $layout_id <= 0) {
            $layout_id = idf_normalize_switch_port_numbering_layout_id(
                $conn,
                $company_id,
                $equipment['switch_port_numbering_layout_id'] ?? 0
            );
        }
        if ($device_type_name === 'switch') {
            if (!$switch_fiber_ports_number_submitted) {
                $switch_fiber_ports_number = trim((string)($equipment['switch_fiber_ports_number'] ?? ''));
            }
        } else {
            // Why: hidden rack-modal switch fields must not clear existing linked equipment switch metadata for non-switch positions.
            $switch_fiber_ports_number = trim((string)($equipment['switch_fiber_ports_number'] ?? ''));
        }
        if ($switch_fiber_ports_number !== '') {
            idf_ensure_equipment_fiber_count_id($conn, $company_id, $switch_fiber_ports_number);
        }
        if ($sfp_count <= 0) {
            $equipmentFiberCountRaw = trim((string)($equipment['switch_fiber_ports_number'] ?? ''));
            if ($equipmentFiberCountRaw !== '' && preg_match('/^\d+$/', $equipmentFiberCountRaw)) {
                $sfp_count = (int)$equipmentFiberCountRaw;
            }
        }

        // Keep equipment table in sync
        $stmtUpdateEq = mysqli_prepare(
            $conn,
            "UPDATE equipment
             SET name=?, switch_rj45_id=?, switch_port_numbering_layout_id=NULLIF(?, 0), switch_fiber_ports_number=NULLIF(?, ''), notes=?
             WHERE id=? AND company_id=?
             LIMIT 1"
        );
        if ($stmtUpdateEq) {
            $notesForEquipment = $notes;
            mysqli_stmt_bind_param($stmtUpdateEq, 'siissii', $device_name, $switch_rj45_id, $layout_id, $switch_fiber_ports_number, $notesForEquipment, $equipment_id, $company_id);
            if (!mysqli_stmt_execute($stmtUpdateEq)) {
                $updateEquipmentError = mysqli_stmt_error($stmtUpdateEq);
                mysqli_stmt_close($stmtUpdateEq);
                idf_fail('DB error updating linked equipment: ' . $updateEquipmentError, 500);
            }
            mysqli_stmt_close($stmtUpdateEq);
        }
    }
}

if ($device_type_name === 'switch' && $switch_rj45_id <= 0) {
    idf_fail('RJ45 Ports are required for switch devices');
}

if (!$isIntentionallyUnlinked && $equipment_id <= 0 && $device_type_name === 'switch' && $device_name !== '') {
    $stmtEquipmentByName = mysqli_prepare(
        $conn,
        "SELECT e.id, e.switch_rj45_id, e.switch_port_numbering_layout_id, er.name AS switch_rj45_name
         FROM equipment e
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         WHERE e.company_id = ? AND LOWER(e.name) = LOWER(?)
         ORDER BY e.id DESC
         LIMIT 1"
    );
    if ($stmtEquipmentByName) {
        mysqli_stmt_bind_param($stmtEquipmentByName, 'is', $company_id, $device_name);
        mysqli_stmt_execute($stmtEquipmentByName);
        $resEquipmentByName = mysqli_stmt_get_result($stmtEquipmentByName);
        $equipmentByName = $resEquipmentByName ? mysqli_fetch_assoc($resEquipmentByName) : null;
        mysqli_stmt_close($stmtEquipmentByName);
        if ($equipmentByName) {
            // Why: Linked equipment select may be left blank by UI flows; for switches we should still mirror known switch_ports by matching device_name.
            $equipment_id = (int)($equipmentByName['id'] ?? 0);
            if ($switch_rj45_id <= 0) {
                $switch_rj45_id = (int)($equipmentByName['switch_rj45_id'] ?? 0);
            }
            if (!$layoutSubmitted && $layout_id <= 0) {
                $layout_id = idf_normalize_switch_port_numbering_layout_id(
                    $conn,
                    $company_id,
                    $equipmentByName['switch_port_numbering_layout_id'] ?? 0
                );
            }
            if ($rj45_count <= 0 && !empty($equipmentByName['switch_rj45_name']) && preg_match('/(\d+)/', (string)$equipmentByName['switch_rj45_name'], $matches)) {
                $rj45_count = (int)$matches[1];
            }
        }
    }
}

if ($device_type_name === 'ups') {
    // Why: UPS entries are not network switch panels and must not carry RJ45/SFP port-count presets.
    $rj45_count = 0;
    $sfp_count = 0;
}

if ($layout_id <= 0 && $device_type_name !== '' && $device_type_name !== 'ups') {
    $layout_id = idf_default_switch_port_numbering_layout_id($conn, $company_id, $device_type_name);
}

$stmtIdf = mysqli_prepare($conn, "SELECT id FROM idfs WHERE id=? AND company_id=? LIMIT 1");
if ($stmtIdf) {
    mysqli_stmt_bind_param($stmtIdf, 'ii', $idf_id, $company_id);
    mysqli_stmt_execute($stmtIdf);
    $resIdf = mysqli_stmt_get_result($stmtIdf);
    $foundIdf = $resIdf && mysqli_num_rows($resIdf) === 1;
    mysqli_stmt_close($stmtIdf);

    if (!$foundIdf) {
        idf_fail('IDF not found', 404);
    }
}

$previousLinkedEquipmentIds = [];
$registerPreviousLinkedEquipment = static function ($rawEquipmentId) use (&$previousLinkedEquipmentIds): void {
    $equipmentIdRaw = trim((string)$rawEquipmentId);
    if ($equipmentIdRaw !== '' && ctype_digit($equipmentIdRaw)) {
        $equipmentIdInt = (int)$equipmentIdRaw;
        if ($equipmentIdInt > 0) {
            $previousLinkedEquipmentIds[$equipmentIdInt] = true;
        }
    }
};

$stmtTargetSlotBeforeSave = mysqli_prepare(
    $conn,
    "SELECT id, equipment_id
     FROM idf_positions
     WHERE company_id = ? AND idf_id = ? AND position_no = ?
     LIMIT 1"
);
if ($stmtTargetSlotBeforeSave) {
    mysqli_stmt_bind_param($stmtTargetSlotBeforeSave, 'iii', $company_id, $idf_id, $position_no);
    mysqli_stmt_execute($stmtTargetSlotBeforeSave);
    $resTargetSlotBeforeSave = mysqli_stmt_get_result($stmtTargetSlotBeforeSave);
    $targetSlotBeforeSave = $resTargetSlotBeforeSave ? mysqli_fetch_assoc($resTargetSlotBeforeSave) : null;
    mysqli_stmt_close($stmtTargetSlotBeforeSave);
    if ($targetSlotBeforeSave) {
        $targetSlotBeforeSaveId = (int)($targetSlotBeforeSave['id'] ?? 0);
        if ($position_id <= 0 || $targetSlotBeforeSaveId !== $position_id) {
            $registerPreviousLinkedEquipment($targetSlotBeforeSave['equipment_id'] ?? null);
        }
    }
}

if ($device_name !== '') {
    // Why: device_name must remain unique per company across all IDFs to avoid ambiguous references in link/device pickers.
    $stmtDuplicateDeviceName = null;
    if ($position_id > 0) {
        $stmtDuplicateDeviceName = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE company_id=? AND device_name=? AND id<>?
             LIMIT 1"
        );
        if ($stmtDuplicateDeviceName) {
            mysqli_stmt_bind_param($stmtDuplicateDeviceName, 'isi', $company_id, $device_name, $position_id);
        }
    } else {
        $stmtDuplicateDeviceName = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE company_id=? AND device_name=?
             LIMIT 1"
        );
        if ($stmtDuplicateDeviceName) {
            mysqli_stmt_bind_param($stmtDuplicateDeviceName, 'is', $company_id, $device_name);
        }
    }

    if ($stmtDuplicateDeviceName) {
        mysqli_stmt_execute($stmtDuplicateDeviceName);
        $resDuplicateDeviceName = mysqli_stmt_get_result($stmtDuplicateDeviceName);
        $duplicateDeviceNameExists = $resDuplicateDeviceName && mysqli_num_rows($resDuplicateDeviceName) > 0;
        mysqli_stmt_close($stmtDuplicateDeviceName);

        if ($duplicateDeviceNameExists) {
            idf_fail('Device name already exists. Please choose a unique device name.');
        }
    }
}

if ($equipment_id > 0 && $device_type_name === 'switch') {
    // Why: For non-switch rack entries (UPS/server/other), the same linked equipment can legitimately appear in multiple positions.
    // Keep strict uniqueness for switches to avoid ambiguous switch-port synchronization targets.
    $equipmentIdString = (string)$equipment_id;
    $stmtDuplicateEquipment = null;
    if ($position_id > 0) {
        $stmtDuplicateEquipment = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE idf_id=? AND equipment_id=? AND id<>?
             LIMIT 1"
        );
        if ($stmtDuplicateEquipment) {
            mysqli_stmt_bind_param($stmtDuplicateEquipment, 'isi', $idf_id, $equipmentIdString, $position_id);
        }
    } else {
        $stmtDuplicateEquipment = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE idf_id=? AND equipment_id=?
             LIMIT 1"
        );
        if ($stmtDuplicateEquipment) {
            mysqli_stmt_bind_param($stmtDuplicateEquipment, 'is', $idf_id, $equipmentIdString);
        }
    }

    if ($stmtDuplicateEquipment) {
        mysqli_stmt_execute($stmtDuplicateEquipment);
        $resDuplicateEquipment = mysqli_stmt_get_result($stmtDuplicateEquipment);
        $duplicateEquipmentExists = $resDuplicateEquipment && mysqli_num_rows($resDuplicateEquipment) > 0;
        mysqli_stmt_close($stmtDuplicateEquipment);

        if ($duplicateEquipmentExists) {
            idf_fail('Equipment already on the list');
        }
    }
}

$notes_val = $notes !== '' ? $notes : null;
$equipmentId_val = $equipment_id > 0 ? (string)$equipment_id : idf_generate_unlinked_equipment_token();
$portFiberPortsNumberId = 0;
$portSwitchLayoutId = $layout_id > 0 ? $layout_id : 0;
$portManagementId = 0;
if ($equipment_id > 0) {
    $stmtPortMeta = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(e.switch_port_numbering_layout_id, 0) AS switch_port_numbering_layout_id,
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
        mysqli_stmt_bind_param($stmtPortMeta, 'ii', $company_id, $equipment_id);
        mysqli_stmt_execute($stmtPortMeta);
        $resPortMeta = mysqli_stmt_get_result($stmtPortMeta);
        $portMetaRow = $resPortMeta ? mysqli_fetch_assoc($resPortMeta) : null;
        mysqli_stmt_close($stmtPortMeta);
        if ($portMetaRow) {
            $portFiberPortsNumberId = (int)($portMetaRow['fiber_ports_number_id'] ?? 0);
            if ($portSwitchLayoutId <= 0) {
                $portSwitchLayoutId = (int)($portMetaRow['switch_port_numbering_layout_id'] ?? 0);
            }
            $portManagementId = (int)($portMetaRow['switch_environment_id'] ?? 0);
        }
    }
} elseif ($isIntentionallyUnlinked && $switch_fiber_ports_number !== '') {
    $portFiberPortsNumberId = idf_ensure_equipment_fiber_count_id($conn, $company_id, $switch_fiber_ports_number);
}
if ($portManagementId <= 0) {
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
            $portManagementId = (int)($unmanagedRow['id'] ?? 0);
        }
    }
}

if ($position_id > 0) {
    $stmtPos = mysqli_prepare(
        $conn,
        "SELECT p.id, p.equipment_id
         FROM idf_positions p
         JOIN idfs i ON i.id=p.idf_id
         WHERE p.id=? AND i.company_id=?
         LIMIT 1"
    );
    if ($stmtPos) {
        mysqli_stmt_bind_param($stmtPos, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmtPos);
        $resPos = mysqli_stmt_get_result($stmtPos);
        $posRow = $resPos ? mysqli_fetch_assoc($resPos) : null;
        $foundPos = $posRow !== null;
        mysqli_stmt_close($stmtPos);

        if (!$foundPos) {
            idf_fail('Position not found', 404);
        }

        $registerPreviousLinkedEquipment($posRow['equipment_id'] ?? null);
    }

    $layout_val = $layout_id > 0 ? $layout_id : null;
    $stmtUpdatePos = mysqli_prepare(
        $conn,
        "UPDATE idf_positions
         SET device_type=?,
             device_name=?,
             equipment_id=?,
             rj45_count=?,
             sfp_count=?,
             switch_port_numbering_layout_id=?,
             notes=?
         WHERE id=?
         LIMIT 1"
    );
    if ($stmtUpdatePos) {
        mysqli_stmt_bind_param($stmtUpdatePos, 'issiiisi', $device_type_id, $device_name, $equipmentId_val, $rj45_count, $sfp_count, $layout_val, $notes_val, $position_id);
        if (!mysqli_stmt_execute($stmtUpdatePos)) {
            idf_fail('DB error updating position: ' . mysqli_stmt_error($stmtUpdatePos), 500);
        }
        mysqli_stmt_close($stmtUpdatePos);
    }
} else {
    $layout_val = $layout_id > 0 ? $layout_id : null;
    $stmtInsertPos = mysqli_prepare(
        $conn,
        "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, rj45_count, sfp_count, switch_port_numbering_layout_id, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           company_id=VALUES(company_id),
           device_type=VALUES(device_type),
           device_name=VALUES(device_name),
           equipment_id=VALUES(equipment_id),
           rj45_count=VALUES(rj45_count),
           sfp_count=VALUES(sfp_count),
           switch_port_numbering_layout_id=VALUES(switch_port_numbering_layout_id),
           notes=VALUES(notes)"
    );
    if ($stmtInsertPos) {
        mysqli_stmt_bind_param($stmtInsertPos, 'iiiissiiis', $company_id, $idf_id, $position_no, $device_type_id, $device_name, $equipmentId_val, $rj45_count, $sfp_count, $layout_val, $notes_val);
        if (!mysqli_stmt_execute($stmtInsertPos)) {
            idf_fail('DB error saving position: ' . mysqli_stmt_error($stmtInsertPos), 500);
        }
        mysqli_stmt_close($stmtInsertPos);
    }
}

$stmtPid = mysqli_prepare($conn, "SELECT id FROM idf_positions WHERE idf_id=? AND position_no=? LIMIT 1");
if ($stmtPid) {
    mysqli_stmt_bind_param($stmtPid, 'ii', $idf_id, $position_no);
    mysqli_stmt_execute($stmtPid);
    $resPid = mysqli_stmt_get_result($stmtPid);
    $pidRow = $resPid ? mysqli_fetch_assoc($resPid) : null;
    $pid = $pidRow ? (int)$pidRow['id'] : 0;
    mysqli_stmt_close($stmtPid);
}

if ($pid > 0) {
    $stmtSeedPosMeta = mysqli_prepare(
        $conn,
        "SELECT equipment_id, rj45_count, sfp_count, device_name
         FROM idf_positions
         WHERE id = ? AND company_id = ?
         LIMIT 1"
    );
    if ($stmtSeedPosMeta) {
        mysqli_stmt_bind_param($stmtSeedPosMeta, 'ii', $pid, $company_id);
        mysqli_stmt_execute($stmtSeedPosMeta);
        $resSeedPosMeta = mysqli_stmt_get_result($stmtSeedPosMeta);
        $seedPosMeta = $resSeedPosMeta ? mysqli_fetch_assoc($resSeedPosMeta) : null;
        mysqli_stmt_close($stmtSeedPosMeta);
        if ($seedPosMeta) {
            $positionEquipmentId = idf_parse_linked_equipment_id($seedPosMeta['equipment_id'] ?? '');
            if ($positionEquipmentId > 0) {
                // Why: Linked equipment persistence in idf_positions is the canonical source for sync and must override stale request payloads.
                $equipment_id = $positionEquipmentId;
                $linkedEquipmentId = $positionEquipmentId;
                $isIntentionallyUnlinked = false;
            }
            if ($rj45_count <= 0) {
                $rj45_count = (int)($seedPosMeta['rj45_count'] ?? 0);
            }
            if ($sfp_count <= 0) {
                $sfp_count = (int)($seedPosMeta['sfp_count'] ?? 0);
            }
            if (!$isIntentionallyUnlinked && $equipment_id <= 0) {
                $seedDeviceName = trim((string)($seedPosMeta['device_name'] ?? ''));
                if ($seedDeviceName !== '') {
                    $stmtSeedEquipmentByName = mysqli_prepare(
                        $conn,
                        "SELECT id
                         FROM equipment
                         WHERE company_id = ? AND LOWER(name) = LOWER(?)
                         ORDER BY id DESC
                         LIMIT 1"
                    );
                    if ($stmtSeedEquipmentByName) {
                        mysqli_stmt_bind_param($stmtSeedEquipmentByName, 'is', $company_id, $seedDeviceName);
                        mysqli_stmt_execute($stmtSeedEquipmentByName);
                        $resSeedEquipmentByName = mysqli_stmt_get_result($stmtSeedEquipmentByName);
                        $seedEquipmentByName = $resSeedEquipmentByName ? mysqli_fetch_assoc($resSeedEquipmentByName) : null;
                        mysqli_stmt_close($stmtSeedEquipmentByName);
                        if ($seedEquipmentByName) {
                            $equipment_id = (int)($seedEquipmentByName['id'] ?? 0);
                            $linkedEquipmentId = $equipment_id;
                        }
                    }
                }
            }
        }
    }

    $stmtCnt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM idf_ports WHERE position_id=?");
    $existing = 0;
    if ($stmtCnt) {
        mysqli_stmt_bind_param($stmtCnt, 'i', $pid);
        mysqli_stmt_execute($stmtCnt);
        $resCnt = mysqli_stmt_get_result($stmtCnt);
        if ($resCnt && ($r = mysqli_fetch_assoc($resCnt))) {
            $existing = (int)$r['c'];
        }
        mysqli_stmt_close($stmtCnt);
    }

    if ($rj45_count > 0 || $equipment_id > 0 || $sfp_count > 0) {
        $unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
        if ($unknownStatusId <= 0) {
            idf_fail('Unable to resolve default status for company', 500);
        }
        $defaultCableColorName = 'Gray';
        $defaultCableHexColor = '#808080';

        $defaultCableColorId = 0;
        $stmtDefaultCableColorId = mysqli_prepare(
            $conn,
            "SELECT id
             FROM cable_colors
             WHERE company_id = ?
               AND LOWER(color_name) = 'gray'
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtDefaultCableColorId) {
            mysqli_stmt_bind_param($stmtDefaultCableColorId, 'i', $company_id);
            mysqli_stmt_execute($stmtDefaultCableColorId);
            $resDefaultCableColorId = mysqli_stmt_get_result($stmtDefaultCableColorId);
            $defaultCableColorIdRow = $resDefaultCableColorId ? mysqli_fetch_assoc($resDefaultCableColorId) : null;
            mysqli_stmt_close($stmtDefaultCableColorId);
            $defaultCableColorId = (int)($defaultCableColorIdRow['id'] ?? 0);
        }
        if ($defaultCableColorId <= 0) {
            $stmtFallbackCableColorId = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM cable_colors
                 WHERE company_id = ?
                 ORDER BY id ASC
                 LIMIT 1"
            );
            if ($stmtFallbackCableColorId) {
                mysqli_stmt_bind_param($stmtFallbackCableColorId, 'i', $company_id);
                mysqli_stmt_execute($stmtFallbackCableColorId);
                $resFallbackCableColorId = mysqli_stmt_get_result($stmtFallbackCableColorId);
                $fallbackCableColorIdRow = $resFallbackCableColorId ? mysqli_fetch_assoc($resFallbackCableColorId) : null;
                mysqli_stmt_close($stmtFallbackCableColorId);
                $defaultCableColorId = (int)($fallbackCableColorIdRow['id'] ?? 0);
            }
        }
        if ($defaultCableColorId <= 0) {
            idf_fail('Unable to resolve default cable color for company', 500);
        }

        $rj45PortTypeId = idf_resolve_port_type_id($conn, $company_id, 'RJ45', 'RJ45');
        if ($rj45_count > 0 && $rj45PortTypeId <= 0) {
            idf_fail('Unable to resolve RJ45 port type for company', 500);
        }
        $rj45PortTypeName = 'RJ45';
        if ($rj45PortTypeId > 0) {
            $stmtRj45TypeName = mysqli_prepare(
                $conn,
                "SELECT type
                 FROM switch_port_types
                 WHERE company_id = ? AND id = ?
                 LIMIT 1"
            );
            if ($stmtRj45TypeName) {
                mysqli_stmt_bind_param($stmtRj45TypeName, 'ii', $company_id, $rj45PortTypeId);
                mysqli_stmt_execute($stmtRj45TypeName);
                $resRj45TypeName = mysqli_stmt_get_result($stmtRj45TypeName);
                $rj45TypeNameRow = $resRj45TypeName ? mysqli_fetch_assoc($resRj45TypeName) : null;
                mysqli_stmt_close($stmtRj45TypeName);
                $resolvedRj45TypeName = trim((string)($rj45TypeNameRow['type'] ?? ''));
                if ($resolvedRj45TypeName !== '') {
                    $rj45PortTypeName = $resolvedRj45TypeName;
                }
            }
        }

        if ($device_type_name === 'switch' && $equipment_id > 0 && $rj45_count >= 0 && $rj45PortTypeId > 0) {
            // Why: Rack Edit must keep linked switch_ports capacity aligned with selected RJ45 count (grow + shrink), not only idf_positions metadata.
            $switchPortsHasManagementColumn = idf_table_has_column($conn, 'switch_ports', 'management_id');
            $switchPortsHasCommentsColumn = idf_table_has_column($conn, 'switch_ports', 'comments');

            $switchHostnameForSync = '';
            $stmtSwitchHostname = mysqli_prepare(
                $conn,
                "SELECT COALESCE(hostname, '') AS hostname
                 FROM equipment
                 WHERE company_id = ? AND id = ?
                 LIMIT 1"
            );
            if ($stmtSwitchHostname) {
                mysqli_stmt_bind_param($stmtSwitchHostname, 'ii', $company_id, $equipment_id);
                mysqli_stmt_execute($stmtSwitchHostname);
                $resSwitchHostname = mysqli_stmt_get_result($stmtSwitchHostname);
                $switchHostnameRow = $resSwitchHostname ? mysqli_fetch_assoc($resSwitchHostname) : null;
                mysqli_stmt_close($stmtSwitchHostname);
                $switchHostnameForSync = trim((string)($switchHostnameRow['hostname'] ?? ''));
            }

            idf_sync_switch_rj45_capacity(
                $conn,
                $company_id,
                $equipment_id,
                $idf_id,
                $rj45_count,
                $rj45PortTypeId,
                $rj45PortTypeName,
                $unknownStatusId,
                $defaultCableColorId,
                $portManagementId,
                $switchHostnameForSync,
                $switchPortLabelColumn,
                $switchPortsHasManagementColumn,
                $switchPortsHasCommentsColumn
            );

            // Why: Shrinking RJ45 capacity must also remove stale idf_ports rows above selected count to keep rack visualizer and DB in sync.
            $stmtDeleteExtraIdfRj45 = mysqli_prepare(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = ?
                   AND position_id = ?
                   AND port_type = ?
                   AND port_no > ?"
            );
            if ($stmtDeleteExtraIdfRj45) {
                mysqli_stmt_bind_param($stmtDeleteExtraIdfRj45, 'iiii', $company_id, $pid, $rj45PortTypeId, $rj45_count);
                if (!mysqli_stmt_execute($stmtDeleteExtraIdfRj45)) {
                    $err = mysqli_stmt_error($stmtDeleteExtraIdfRj45);
                    mysqli_stmt_close($stmtDeleteExtraIdfRj45);
                    idf_fail('DB error deleting extra IDF RJ45 ports: ' . $err, 500);
                }
                mysqli_stmt_close($stmtDeleteExtraIdfRj45);
            } else {
                idf_fail('DB error preparing IDF RJ45 prune', 500);
            }
        }

        if ($device_type_name === 'switch' && $equipment_id > 0) {
            $fiberCountForSync = 0;
            $fiberPortTypeIdForSync = 0;
            $fiberPortTypeNameForSync = 'SFP';
            $stmtFiberSyncMeta = mysqli_prepare(
                $conn,
                "SELECT COALESCE(e.switch_fiber_ports_number, 0) AS switch_fiber_ports_number,
                        COALESCE(e.switch_fiber_port_label, '') AS switch_fiber_port_label,
                        COALESCE(ef.name, '') AS switch_fiber_name
                 FROM equipment e
                 LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
                 WHERE e.company_id = ? AND e.id = ?
                 LIMIT 1"
            );
            if ($stmtFiberSyncMeta) {
                mysqli_stmt_bind_param($stmtFiberSyncMeta, 'ii', $company_id, $equipment_id);
                mysqli_stmt_execute($stmtFiberSyncMeta);
                $resFiberSyncMeta = mysqli_stmt_get_result($stmtFiberSyncMeta);
                $fiberSyncMeta = $resFiberSyncMeta ? mysqli_fetch_assoc($resFiberSyncMeta) : null;
                mysqli_stmt_close($stmtFiberSyncMeta);
                if ($fiberSyncMeta) {
                    $fiberCountForSync = (int)($fiberSyncMeta['switch_fiber_ports_number'] ?? 0);
                    $fiberHintForSync = strtolower(trim((string)($fiberSyncMeta['switch_fiber_port_label'] ?? '') . ' ' . (string)($fiberSyncMeta['switch_fiber_name'] ?? '')));
                    $fiberTypeFallbackForSync = (strpos($fiberHintForSync, 'sfp+') !== false || strpos($fiberHintForSync, 'sfp plus') !== false) ? 'sfp+' : 'sfp';
                    if ($fiberCountForSync > 0) {
                        $fiberPortTypeIdForSync = idf_resolve_port_type_id($conn, $company_id, $fiberTypeFallbackForSync, $fiberTypeFallbackForSync);
                        if ($fiberPortTypeIdForSync > 0) {
                            $stmtFiberTypeName = mysqli_prepare(
                                $conn,
                                "SELECT type
                                 FROM switch_port_types
                                 WHERE company_id = ? AND id = ?
                                 LIMIT 1"
                            );
                            if ($stmtFiberTypeName) {
                                mysqli_stmt_bind_param($stmtFiberTypeName, 'ii', $company_id, $fiberPortTypeIdForSync);
                                mysqli_stmt_execute($stmtFiberTypeName);
                                $resFiberTypeName = mysqli_stmt_get_result($stmtFiberTypeName);
                                $fiberTypeNameRow = $resFiberTypeName ? mysqli_fetch_assoc($resFiberTypeName) : null;
                                mysqli_stmt_close($stmtFiberTypeName);
                                $resolvedFiberTypeName = trim((string)($fiberTypeNameRow['type'] ?? ''));
                                if ($resolvedFiberTypeName !== '') {
                                    $fiberPortTypeNameForSync = $resolvedFiberTypeName;
                                }
                            }
                        }
                    }
                }
            }

            $fiberSwitchPhysicalBase = max(0, (int)$rj45_count);
            if ($fiberSwitchPhysicalBase <= 0 && $equipment_id > 0) {
                $fiberSwitchPhysicalBase = idf_equipment_switch_rj45_capacity_hint($conn, $company_id, $equipment_id);
            }

            idf_sync_switch_fiber_capacity(
                $conn,
                $company_id,
                $equipment_id,
                $idf_id,
                $fiberCountForSync,
                $fiberPortTypeIdForSync,
                $fiberPortTypeNameForSync,
                $unknownStatusId,
                $defaultCableColorId,
                $portManagementId,
                $fiberSwitchPhysicalBase,
                (string)($switchHostnameForSync ?? ''),
                $switchPortLabelColumn,
                idf_table_has_column($conn, 'switch_ports', 'management_id'),
                idf_table_has_column($conn, 'switch_ports', 'comments')
            );

            $stmtDeleteExtraIdfFiber = mysqli_prepare(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = ?
                   AND position_id = ?
                   AND port_type IN (
                        SELECT id
                        FROM switch_port_types
                        WHERE company_id = ?
                          AND LOWER(type) LIKE '%sfp%'
                   )
                   AND (? <= 0 OR port_no > ?)"
            );
            if ($stmtDeleteExtraIdfFiber) {
                $fiberIdfCeilingExclusive = ($fiberSwitchPhysicalBase > 0)
                    ? ($fiberSwitchPhysicalBase + $fiberCountForSync)
                    : $fiberCountForSync;
                mysqli_stmt_bind_param($stmtDeleteExtraIdfFiber, 'iiiii', $company_id, $pid, $company_id, $fiberCountForSync, $fiberIdfCeilingExclusive);
                if (!mysqli_stmt_execute($stmtDeleteExtraIdfFiber)) {
                    $err = mysqli_stmt_error($stmtDeleteExtraIdfFiber);
                    mysqli_stmt_close($stmtDeleteExtraIdfFiber);
                    idf_fail('DB error deleting extra IDF fiber ports: ' . $err, 500);
                }
                mysqli_stmt_close($stmtDeleteExtraIdfFiber);
            } else {
                idf_fail('DB error preparing IDF fiber prune', 500);
            }
        }

        $portSeedByKey = [];
        $portTypeByNumber = [];
        if ($equipment_id > 0) {
            $stmtSwitchPortColors = mysqli_prepare(
                $conn,
                "SELECT sp.port_number, sp.port_type, sp.{$switchPortLabelColumn} AS label, sp.status_id, sp.hostname, sp.vlan_id, sp.comments,
                        spt.id AS switch_port_type_id,
                        LOWER(TRIM(COALESCE(spt.type, CAST(sp.port_type AS CHAR)))) AS normalized_port_type,
                        cc.color_name,
                        cc.hex_color
                 FROM switch_ports sp
                 LEFT JOIN switch_port_types spt ON spt.type = sp.port_type AND spt.company_id = sp.company_id
                 LEFT JOIN cable_colors cc ON cc.id = sp.color_id AND cc.company_id = sp.company_id
                 WHERE sp.company_id = ? AND sp.equipment_id = ?"
            );
            if ($stmtSwitchPortColors) {
                mysqli_stmt_bind_param($stmtSwitchPortColors, 'ii', $company_id, $equipment_id);
                mysqli_stmt_execute($stmtSwitchPortColors);
                $resSwitchPortColors = mysqli_stmt_get_result($stmtSwitchPortColors);
                while ($resSwitchPortColors && ($switchPortColorRow = mysqli_fetch_assoc($resSwitchPortColors))) {
                    $normalizedPortType = trim((string)($switchPortColorRow['normalized_port_type'] ?? ''));
                    if ($normalizedPortType !== '' && $normalizedPortType !== 'rj45') {
                        // Why: IDF RJ45 auto-generation must not be contaminated by SFP rows that share the same port_number values.
                        continue;
                    }
                    $portNumber = (int)($switchPortColorRow['port_number'] ?? 0);
                    if ($portNumber <= 0) {
                        continue;
                    }
                    $rawPortType = $switchPortColorRow['port_type'] ?? '';
                    $normalizedPortType = trim((string)($switchPortColorRow['normalized_port_type'] ?? ''));
                    $resolvedPortTypeId = (int)($switchPortColorRow['switch_port_type_id'] ?? 0);
                    if ($resolvedPortTypeId <= 0) {
                        $resolvedPortTypeId = idf_resolve_port_type_id($conn, $company_id, $rawPortType, $normalizedPortType !== '' ? $normalizedPortType : 'RJ45');
                    }
                    if ($resolvedPortTypeId <= 0) {
                        continue;
                    }
                    $cableColorName = trim((string)($switchPortColorRow['color_name'] ?? ''));
                    $cableHexColor = strtoupper(trim((string)($switchPortColorRow['hex_color'] ?? '')));
                    if ($cableColorName === '') {
                        $cableColorName = $defaultCableColorName;
                    }
                    if (!preg_match('/^#[0-9A-F]{6}$/', $cableHexColor)) {
                        $cableHexColor = $defaultCableHexColor;
                    }
                    $portKey = $resolvedPortTypeId . ':' . $portNumber;
                    $portSeedByKey[$portKey] = [
                        // Why: Seeded IDF ports must mirror switch port metadata so initial state is immediately useful in the rack workflow.
                        'label' => trim((string)($switchPortColorRow['label'] ?? '')),
                        'status_id' => (int)($switchPortColorRow['status_id'] ?? 0),
                        'connected_to' => trim((string)($switchPortColorRow['hostname'] ?? '')),
                        'vlan_id' => (int)($switchPortColorRow['vlan_id'] ?? 0),
                        'speed_id' => 0,
                        'poe_id' => 0,
                        'cable_color' => $cableColorName,
                        'hex_color' => $cableHexColor,
                        'notes' => trim((string)($switchPortColorRow['comments'] ?? '')),
                    ];
                    $portTypeByNumber[$portKey] = [
                        'port_no' => $portNumber,
                        'port_type' => $resolvedPortTypeId,
                    ];
                }
                mysqli_stmt_close($stmtSwitchPortColors);
            }

            $stmtFiberPorts = mysqli_prepare(
                $conn,
                "SELECT COALESCE(e.switch_fiber_ports_number, 0) AS switch_fiber_ports_number,
                        COALESCE(e.switch_fiber_port_label, '') AS switch_fiber_port_label,
                        COALESCE(ef.name, '') AS switch_fiber_name
                 FROM equipment e
                 LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
                 WHERE e.company_id = ? AND e.id = ?
                 LIMIT 1"
            );
            if ($stmtFiberPorts) {
                mysqli_stmt_bind_param($stmtFiberPorts, 'ii', $company_id, $equipment_id);
                mysqli_stmt_execute($stmtFiberPorts);
                $resFiberPorts = mysqli_stmt_get_result($stmtFiberPorts);
                $fiberMeta = $resFiberPorts ? mysqli_fetch_assoc($resFiberPorts) : null;
                mysqli_stmt_close($stmtFiberPorts);
                if ($fiberMeta) {
                    $fiberCount = (int)($fiberMeta['switch_fiber_ports_number'] ?? 0);
                    $fiberHint = strtolower(trim((string)($fiberMeta['switch_fiber_port_label'] ?? '') . ' ' . (string)($fiberMeta['switch_fiber_name'] ?? '')));
                    if ($fiberCount > 0) {
                        $fiberTypeFallback = strpos($fiberHint, 'sfp+') !== false ? 'sfp+' : 'sfp';
                        $fiberTypeId = idf_resolve_port_type_id($conn, $company_id, $fiberTypeFallback, $fiberTypeFallback);
                        if ($fiberTypeId > 0) {
                            $rjHintForFiberIdfSeed = max(0, (int)$rj45_count);
                            if ($rjHintForFiberIdfSeed <= 0 && $equipment_id > 0) {
                                $rjHintForFiberIdfSeed = idf_equipment_switch_rj45_capacity_hint($conn, $company_id, $equipment_id);
                            }
                            $fiberBaselineForSeed = idf_resolve_fiber_number_baseline_after_rj45($portTypeByNumber, $rj45PortTypeId, $rjHintForFiberIdfSeed);
                            for ($fiberOrdinal = 1; $fiberOrdinal <= $fiberCount; $fiberOrdinal++) {
                                $fiberPortNo = idf_resolve_synthetic_fiber_port_no($fiberBaselineForSeed, $fiberOrdinal);
                                $fiberKey = $fiberTypeId . ':' . $fiberPortNo;
                                if (!isset($portTypeByNumber[$fiberKey])) {
                                    // Why: Equipment can declare SFP/SFP+ capacity even when switch_ports rows are incomplete; preserve those ports in IDF generation.
                                    $portTypeByNumber[$fiberKey] = [
                                        'port_no' => $fiberPortNo,
                                        'port_type' => $fiberTypeId,
                                    ];
                                    $portSeedByKey[$fiberKey] = [
                                        'label' => '',
                                        'status_id' => $unknownStatusId,
                                        'connected_to' => '',
                                        'vlan_id' => 0,
                                        'speed_id' => 0,
                                        'poe_id' => 0,
                                        'cable_color' => $defaultCableColorName,
                                        'hex_color' => $defaultCableHexColor,
                                        'notes' => '',
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($sfp_count > 0) {
            $fiberTypeFallback = 'sfp';
            $fiberTypeId = idf_resolve_port_type_id($conn, $company_id, $fiberTypeFallback, $fiberTypeFallback);
            if ($fiberTypeId > 0) {
                for ($fiberPortNo = 1; $fiberPortNo <= $sfp_count; $fiberPortNo++) {
                    $fiberKey = $fiberTypeId . ':' . $fiberPortNo;
                    if (!isset($portTypeByNumber[$fiberKey])) {
                        $portTypeByNumber[$fiberKey] = [
                            'port_no' => $fiberPortNo,
                            'port_type' => $fiberTypeId,
                        ];
                        $portSeedByKey[$fiberKey] = [
                            'label' => '',
                            'status_id' => $unknownStatusId,
                            'connected_to' => '',
                            'vlan_id' => 0,
                            'speed_id' => 0,
                            'poe_id' => 0,
                            'cable_color' => $defaultCableColorName,
                            'hex_color' => $defaultCableHexColor,
                            'notes' => '',
                        ];
                    }
                }
            }
        }
        if ($rj45_count > 0) {
            for ($rj45PortNo = 1; $rj45PortNo <= $rj45_count; $rj45PortNo++) {
                $rj45Key = $rj45PortTypeId . ':' . $rj45PortNo;
                if (!isset($portTypeByNumber[$rj45Key])) {
                    // Why: Add-device flow must always materialize selected RJ45 capacity even when linked switch metadata only contributes fiber rows.
                    $portTypeByNumber[$rj45Key] = [
                        'port_no' => $rj45PortNo,
                        'port_type' => $rj45PortTypeId,
                    ];
                }
                if (!isset($portSeedByKey[$rj45Key])) {
                    $portSeedByKey[$rj45Key] = [
                        'label' => '',
                        'status_id' => $unknownStatusId,
                        'connected_to' => '',
                        'vlan_id' => 0,
                        'speed_id' => 0,
                        'poe_id' => 0,
                        'cable_color' => $defaultCableColorName,
                        'hex_color' => $defaultCableHexColor,
                        'notes' => '',
                    ];
                }
            }
        }
        $insertPortSql = "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, label, status_id, connected_to, vlan_id, speed_id, poe_id, fiber_ports_number, switch_port_numbering_layout_id, management_id, cable_color, hex_color, notes)
                          VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?)
                          ON DUPLICATE KEY UPDATE
                            label=CASE WHEN VALUES(label) <> '' THEN VALUES(label) ELSE label END,
                            status_id=CASE WHEN VALUES(status_id) > 0 THEN VALUES(status_id) ELSE status_id END,
                            connected_to=CASE WHEN VALUES(connected_to) <> '' THEN VALUES(connected_to) ELSE connected_to END,
                            vlan_id=COALESCE(VALUES(vlan_id), vlan_id),
                            speed_id=COALESCE(VALUES(speed_id), speed_id),
                            poe_id=COALESCE(VALUES(poe_id), poe_id),
                            fiber_ports_number=COALESCE(VALUES(fiber_ports_number), fiber_ports_number),
                            switch_port_numbering_layout_id=COALESCE(VALUES(switch_port_numbering_layout_id), switch_port_numbering_layout_id),
                            management_id=COALESCE(VALUES(management_id), management_id),
                            cable_color=CASE WHEN VALUES(cable_color) <> '' THEN VALUES(cable_color) ELSE cable_color END,
                            hex_color=CASE WHEN VALUES(hex_color) <> '' THEN VALUES(hex_color) ELSE hex_color END,
                            notes=CASE WHEN VALUES(notes) <> '' THEN VALUES(notes) ELSE notes END";
        $stmtInsertPort = mysqli_prepare($conn, $insertPortSql);
        if ($stmtInsertPort) {
            if ($portTypeByNumber) {
                foreach ($portTypeByNumber as $portKey => $portMeta) {
                    $portSeed = $portSeedByKey[$portKey] ?? [];
                    $portNo = (int)($portMeta['port_no'] ?? 0);
                    $portTypeId = (int)($portMeta['port_type'] ?? 0);
                    if ($portNo <= 0 || $portTypeId <= 0) {
                        continue;
                    }
                    $portLabel = (string)($portSeed['label'] ?? '');
                    $portStatus = (int)($portSeed['status_id'] ?? 0);
                    $portConn = (string)($portSeed['connected_to'] ?? '');
                    $portVlan = (int)($portSeed['vlan_id'] ?? 0);
                    $portSpeed = (int)($portSeed['speed_id'] ?? 0);
                    $portPoe = (int)($portSeed['poe_id'] ?? 0);
                    $portColor = (string)($portSeed['cable_color'] ?? $defaultCableColorName);
                    $portHex = (string)($portSeed['hex_color'] ?? $defaultCableHexColor);
                    $portNotes = (string)($portSeed['notes'] ?? '');
                    $portStatus = $portStatus > 0 ? $portStatus : $unknownStatusId;
                    mysqli_stmt_bind_param($stmtInsertPort, 'iiiisisiiiiiisss', $company_id, $pid, $portNo, $portTypeId, $portLabel, $portStatus, $portConn, $portVlan, $portSpeed, $portPoe, $portFiberPortsNumberId, $portSwitchLayoutId, $portManagementId, $portColor, $portHex, $portNotes);
                    mysqli_stmt_execute($stmtInsertPort);
                }
            } else {
                for ($n = 1; $n <= $rj45_count; $n++) {
                    $emptyLabel = '';
                    $emptyConn = '';
                    $emptyNotes = '';
                    $zeroVal = 0;
                    mysqli_stmt_bind_param($stmtInsertPort, 'iiiisisiiiiiisss', $company_id, $pid, $n, $rj45PortTypeId, $emptyLabel, $unknownStatusId, $emptyConn, $zeroVal, $zeroVal, $zeroVal, $portFiberPortsNumberId, $portSwitchLayoutId, $portManagementId, $defaultCableColorName, $defaultCableHexColor, $emptyNotes);
                    mysqli_stmt_execute($stmtInsertPort);
                }
            }
            mysqli_stmt_close($stmtInsertPort);
        }

        idf_prune_position_port_capacity($conn, $company_id, $pid, $rj45_count, $sfp_count, $rj45PortTypeId, $equipment_id);

        $layoutSyncValue = $layout_id > 0 ? $layout_id : 0;
        $stmtSyncPortLayout = mysqli_prepare(
            $conn,
            "UPDATE idf_ports
             SET switch_port_numbering_layout_id = NULLIF(?, 0)
             WHERE company_id = ?
               AND position_id = ?"
        );
        if ($stmtSyncPortLayout) {
            mysqli_stmt_bind_param($stmtSyncPortLayout, 'iii', $layoutSyncValue, $company_id, $pid);
            if (!mysqli_stmt_execute($stmtSyncPortLayout)) {
                $err = mysqli_stmt_error($stmtSyncPortLayout);
                mysqli_stmt_close($stmtSyncPortLayout);
                idf_fail('DB error syncing IDF port numbering layout: ' . $err, 500);
            }
            mysqli_stmt_close($stmtSyncPortLayout);
        } else {
            idf_fail('DB error preparing IDF port layout sync', 500);
        }

        if ($equipment_id > 0) {
            // Why: enforce linked-equipment metadata parity even if seed arrays missed rows due legacy naming/lookup drift.
            $sqlForceSync = "
                UPDATE idf_ports ip
                JOIN switch_ports sp
                  ON sp.company_id = ip.company_id
                 AND sp.equipment_id = ?
                 AND sp.port_number = ip.port_no
                LEFT JOIN switch_port_types spt
                  ON spt.company_id = sp.company_id
                 AND spt.type = sp.port_type
                LEFT JOIN cable_colors cc
                  ON cc.company_id = sp.company_id
                 AND cc.id = sp.color_id
                SET ip.label = COALESCE(NULLIF(NULLIF(TRIM(sp.{$switchPortLabelColumn}), ''), '0'), ip.label),
                    ip.status_id = COALESCE(sp.status_id, ip.status_id),
                    ip.connected_to = COALESCE(NULLIF(sp.hostname, ''), ip.connected_to),
                    ip.vlan_id = COALESCE(sp.vlan_id, ip.vlan_id),
                    ip.cable_color = COALESCE(NULLIF(cc.color_name, ''), ip.cable_color),
                    ip.hex_color = COALESCE(NULLIF(cc.hex_color, ''), ip.hex_color),
                    ip.notes = COALESCE(NULLIF(sp.comments, ''), ip.notes)
                WHERE ip.company_id = ?
                  AND ip.position_id = ?
                  AND spt.id = ip.port_type
            ";
            $stmtForceSync = mysqli_prepare($conn, $sqlForceSync);
            if ($stmtForceSync) {
                mysqli_stmt_bind_param($stmtForceSync, 'iii', $equipment_id, $company_id, $pid);
                mysqli_stmt_execute($stmtForceSync);
                mysqli_stmt_close($stmtForceSync);
            }
        }

        $resolvedLinkedEquipmentId = 0;
        $stmtResolvedPosition = mysqli_prepare(
            $conn,
            "SELECT equipment_id
             FROM idf_positions
             WHERE id = ? AND company_id = ?
             LIMIT 1"
        );
        if ($stmtResolvedPosition) {
            mysqli_stmt_bind_param($stmtResolvedPosition, 'ii', $pid, $company_id);
            mysqli_stmt_execute($stmtResolvedPosition);
            $resResolvedPosition = mysqli_stmt_get_result($stmtResolvedPosition);
            $resolvedPositionRow = $resResolvedPosition ? mysqli_fetch_assoc($resResolvedPosition) : null;
            mysqli_stmt_close($stmtResolvedPosition);
            if ($resolvedPositionRow) {
                $resolvedEquipmentRaw = trim((string)($resolvedPositionRow['equipment_id'] ?? ''));
                if ($resolvedEquipmentRaw !== '' && ctype_digit($resolvedEquipmentRaw)) {
                    $resolvedLinkedEquipmentId = (int)$resolvedEquipmentRaw;
                }
            }
        }

        if ($resolvedLinkedEquipmentId > 0) {
            // Why: rack-side saves must keep the canonical equipment->idf and switch_ports->idf references in lock-step.
            $stmtSyncEquipmentIdf = mysqli_prepare(
                $conn,
                "UPDATE equipment
                 SET idf_id = ?
                 WHERE id = ? AND company_id = ?
                 LIMIT 1"
            );
            if ($stmtSyncEquipmentIdf) {
                mysqli_stmt_bind_param($stmtSyncEquipmentIdf, 'iii', $idf_id, $resolvedLinkedEquipmentId, $company_id);
                mysqli_stmt_execute($stmtSyncEquipmentIdf);
                mysqli_stmt_close($stmtSyncEquipmentIdf);
            }

            $stmtSyncSwitchPortsIdf = mysqli_prepare(
                $conn,
                "UPDATE switch_ports
                 SET idf_id = ?
                 WHERE company_id = ? AND equipment_id = ?"
            );
            if ($stmtSyncSwitchPortsIdf) {
                mysqli_stmt_bind_param($stmtSyncSwitchPortsIdf, 'iii', $idf_id, $company_id, $resolvedLinkedEquipmentId);
                mysqli_stmt_execute($stmtSyncSwitchPortsIdf);
                mysqli_stmt_close($stmtSyncSwitchPortsIdf);
            }

            unset($previousLinkedEquipmentIds[$resolvedLinkedEquipmentId]);
        }

        if ($previousLinkedEquipmentIds) {
            $stmtStillLinked = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS c
                 FROM idf_positions
                 WHERE company_id = ? AND equipment_id = ?"
            );
            $stmtDetachEquipmentIdf = mysqli_prepare(
                $conn,
                "UPDATE equipment
                 SET idf_id = NULL
                 WHERE id = ? AND company_id = ?
                 LIMIT 1"
            );
            $stmtDetachSwitchPortsIdf = mysqli_prepare(
                $conn,
                "UPDATE switch_ports
                 SET idf_id = NULL
                 WHERE company_id = ? AND equipment_id = ?"
            );

            foreach (array_keys($previousLinkedEquipmentIds) as $previousEquipmentId) {
                $previousEquipmentId = (int)$previousEquipmentId;
                if ($previousEquipmentId <= 0 || $previousEquipmentId === $resolvedLinkedEquipmentId) {
                    continue;
                }

                $stillLinkedCount = 0;
                if ($stmtStillLinked) {
                    $previousEquipmentIdString = (string)$previousEquipmentId;
                    mysqli_stmt_bind_param($stmtStillLinked, 'is', $company_id, $previousEquipmentIdString);
                    mysqli_stmt_execute($stmtStillLinked);
                    $resStillLinked = mysqli_stmt_get_result($stmtStillLinked);
                    $stillLinkedRow = $resStillLinked ? mysqli_fetch_assoc($resStillLinked) : null;
                    $stillLinkedCount = (int)($stillLinkedRow['c'] ?? 0);
                }

                if ($stillLinkedCount <= 0) {
                    if ($stmtDetachEquipmentIdf) {
                        mysqli_stmt_bind_param($stmtDetachEquipmentIdf, 'ii', $previousEquipmentId, $company_id);
                        mysqli_stmt_execute($stmtDetachEquipmentIdf);
                    }
                    if ($stmtDetachSwitchPortsIdf) {
                        mysqli_stmt_bind_param($stmtDetachSwitchPortsIdf, 'ii', $company_id, $previousEquipmentId);
                        mysqli_stmt_execute($stmtDetachSwitchPortsIdf);
                    }
                }
            }

            if ($stmtStillLinked) {
                mysqli_stmt_close($stmtStillLinked);
            }
            if ($stmtDetachEquipmentIdf) {
                mysqli_stmt_close($stmtDetachEquipmentIdf);
            }
            if ($stmtDetachSwitchPortsIdf) {
                mysqli_stmt_close($stmtDetachSwitchPortsIdf);
            }
        }
    }
}

idf_ok();
