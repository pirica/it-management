<?php
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/idf_ports_sync.php';

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
if ($equipmentId > 0) {
    $stmtPortMeta = mysqli_prepare(
        $conn,
        "SELECT COALESCE(e.switch_environment_id, 0) AS switch_environment_id
         FROM equipment e
         WHERE e.company_id = ? AND e.id = ? AND e.deleted_at IS NULL
         LIMIT 1"
    );
    if ($stmtPortMeta) {
        mysqli_stmt_bind_param($stmtPortMeta, 'ii', $company_id, $equipmentId);
        mysqli_stmt_execute($stmtPortMeta);
        $resPortMeta = mysqli_stmt_get_result($stmtPortMeta);
        $portMetaRow = $resPortMeta ? mysqli_fetch_assoc($resPortMeta) : null;
        mysqli_stmt_close($stmtPortMeta);
        if ($portMetaRow) {
            $managementId = (int)($portMetaRow['switch_environment_id'] ?? 0);
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

mysqli_begin_transaction($conn);
try {
    /**
     * Why: Regenerating ports deletes local idf_ports (CASCADE idf_links) but leaves peer endpoints with stale
     * connection display and no unlink control; unlink peers first like link_delete.php.
     */
    $switchPortLabelColumnPeer = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
    if ($switchPortLabelColumnPeer === null) {
        $switchPortLabelColumnPeer = 'to_patch_port';
    }

    $regenPortIds = [];
    $stmtRegenIds = mysqli_prepare($conn, "SELECT id FROM idf_ports WHERE position_id = ? AND company_id = ?");
    if ($stmtRegenIds) {
        mysqli_stmt_bind_param($stmtRegenIds, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmtRegenIds);
        $resRegenIds = mysqli_stmt_get_result($stmtRegenIds);
        while ($resRegenIds && ($idRow = mysqli_fetch_assoc($resRegenIds))) {
            $regenPortIds[] = (int)($idRow['id'] ?? 0);
        }
        mysqli_stmt_close($stmtRegenIds);
    }

    $safeRegenIds = [];
    foreach ($regenPortIds as $rid) {
        $rid = (int)$rid;
        if ($rid > 0) {
            $safeRegenIds[$rid] = true;
        }
    }
    $safeRegenIds = array_keys($safeRegenIds);

    if (!empty($safeRegenIds)) {
        $syncPeer = idf_unlink_peer_sync_ids($conn, $company_id);
        $unknownStatusIdPeer = (int)($syncPeer['unknown_status_id'] ?? 0);
        $grayColorIdPeer = (int)($syncPeer['gray_color_id'] ?? 0);

        $inPlaceholders = implode(',', array_fill(0, count($safeRegenIds), '?'));
        $bindTypesPeer = str_repeat('i', 1 + 2 * count($safeRegenIds));
        $bindValsPeer = array_merge([$company_id], $safeRegenIds, $safeRegenIds);

        $peerById = [];
        $linkSql =
            'SELECT port_id_a, port_id_b
             FROM idf_links
             WHERE company_id = ?
               AND (port_id_a IN (' . $inPlaceholders . ') OR port_id_b IN (' . $inPlaceholders . '))';

        $stmtLinks = mysqli_prepare($conn, $linkSql);
        if (!$stmtLinks) {
            throw new RuntimeException('DB prepare failed loading idf_links for ports regen');
        }
        mysqli_stmt_bind_param($stmtLinks, $bindTypesPeer, ...$bindValsPeer);
        if (!mysqli_stmt_execute($stmtLinks)) {
            mysqli_stmt_close($stmtLinks);
            throw new RuntimeException('DB error loading idf_links for ports regen: ' . mysqli_error($conn));
        }
        $resLinks = mysqli_stmt_get_result($stmtLinks);
        $regenFlip = array_fill_keys($safeRegenIds, true);
        while ($resLinks && ($linkRow = mysqli_fetch_assoc($resLinks))) {
            $pa = (int)($linkRow['port_id_a'] ?? 0);
            $pb = (int)($linkRow['port_id_b'] ?? 0);
            if ($pa > 0 && isset($regenFlip[$pa]) && $pb > 0 && empty($regenFlip[$pb])) {
                $peerById[$pb] = true;
            }
            if ($pb > 0 && isset($regenFlip[$pb]) && $pa > 0 && empty($regenFlip[$pa])) {
                $peerById[$pa] = true;
            }
        }
        mysqli_stmt_close($stmtLinks);

        $delLinksSql =
            'DELETE FROM idf_links
             WHERE company_id = ?
               AND (port_id_a IN (' . $inPlaceholders . ') OR port_id_b IN (' . $inPlaceholders . '))';

        $stmtDelLinks = mysqli_prepare($conn, $delLinksSql);
        if (!$stmtDelLinks) {
            throw new RuntimeException('DB prepare failed deleting idf_links for ports regen');
        }
        mysqli_stmt_bind_param($stmtDelLinks, $bindTypesPeer, ...$bindValsPeer);
        if (!mysqli_stmt_execute($stmtDelLinks)) {
            mysqli_stmt_close($stmtDelLinks);
            throw new RuntimeException('DB error deleting impacted idf_links for ports regen: ' . mysqli_stmt_error($stmtDelLinks));
        }
        mysqli_stmt_close($stmtDelLinks);

        foreach (array_keys($peerById) as $peerIdfPortId) {
            idf_reset_idf_port_visual_unlink_state($conn, $company_id, (int)$peerIdfPortId, $unknownStatusIdPeer, $grayColorIdPeer, $switchPortLabelColumnPeer);
        }
    }

    // Why: Regen rebuilds from equipment + position capacity; stale switch_ports SFP 1..N must not stack onto RJ45-tail synthesis.
    if ($equipmentId > 0) {
        $stmtDeleteSwitchPortsEarly = mysqli_prepare($conn, "DELETE FROM switch_ports WHERE company_id = ? AND equipment_id = ?");
        if ($stmtDeleteSwitchPortsEarly) {
            mysqli_stmt_bind_param($stmtDeleteSwitchPortsEarly, 'ii', $company_id, $equipmentId);
            mysqli_stmt_execute($stmtDeleteSwitchPortsEarly);
            mysqli_stmt_close($stmtDeleteSwitchPortsEarly);
        }
    }

    $positionRowForSlots = [
        'id' => $position_id,
        'rj45_count' => $count,
        'sfp_count' => $sfpCount,
        'equipment_id' => $equipmentId > 0 ? (string)$equipmentId : '',
        'switch_port_numbering_layout_id' => $switchPortNumberingLayoutId,
    ];
    $portRowsToInsert = idf_collect_port_slots_for_position($conn, $company_id, $positionRowForSlots);
    $fiberPortsNumberId = (int)($positionRowForSlots['_fiber_ports_number_id'] ?? 0);
    if ((int)($positionRowForSlots['_switch_port_numbering_layout_id'] ?? 0) > 0) {
        $switchPortNumberingLayoutId = (int)$positionRowForSlots['_switch_port_numbering_layout_id'];
    }

    if (empty($portRowsToInsert)) {
        throw new RuntimeException('This device has rj45_count=0 and no fiber (SFP) ports configured');
    }

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
            $switchPatchPortDefault = '';
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
