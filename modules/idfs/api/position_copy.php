<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

function idf_copy_next_unique_device_name(mysqli $conn, int $companyId, string $sourceName): string
{
    $baseName = trim($sourceName);
    if ($baseName === '') {
        return '';
    }

    $candidate = $baseName;
    $suffix = 2;
    while (true) {
        $stmtExists = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE company_id = ? AND device_name = ?
             LIMIT 1"
        );
        if (!$stmtExists) {
            return $candidate;
        }

        mysqli_stmt_bind_param($stmtExists, 'is', $companyId, $candidate);
        mysqli_stmt_execute($stmtExists);
        $resExists = mysqli_stmt_get_result($stmtExists);
        $exists = $resExists && mysqli_num_rows($resExists) > 0;
        mysqli_stmt_close($stmtExists);

        if (!$exists) {
            return $candidate;
        }

        $candidate = $baseName . ' (' . $suffix . ')';
        $suffix++;
        if ($suffix > 999) {
            return $candidate;
        }
    }
}

function idf_copy_next_unique_name_in_table(mysqli $conn, int $companyId, string $table, string $column, string $sourceName): string
{
    $baseName = trim($sourceName);
    if ($baseName === '') {
        return '';
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $candidate = $baseName;
    $suffix = 2;

    while (true) {
        $stmtExists = mysqli_prepare(
            $conn,
            "SELECT id
             FROM `{$tableEsc}`
             WHERE company_id = ? AND `{$columnEsc}` = ?
             LIMIT 1"
        );
        if (!$stmtExists) {
            return $candidate;
        }

        mysqli_stmt_bind_param($stmtExists, 'is', $companyId, $candidate);
        mysqli_stmt_execute($stmtExists);
        $resExists = mysqli_stmt_get_result($stmtExists);
        $exists = $resExists && mysqli_num_rows($resExists) > 0;
        mysqli_stmt_close($stmtExists);

        if (!$exists) {
            return $candidate;
        }

        $candidate = $baseName . ' (' . $suffix . ')';
        $suffix++;
        if ($suffix > 999) {
            return $candidate;
        }
    }
}

function idf_copy_generate_unique_asset_id(mysqli $conn, int $companyId): string
{
    for ($i = 0; $i < 500; $i++) {
        $candidate = (string)random_int(1000, 9999) . '-' . (string)random_int(1000, 9999);
        $stmtExists = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE company_id = ? AND equipment_id = ?
             LIMIT 1"
        );
        if (!$stmtExists) {
            return $candidate;
        }
        mysqli_stmt_bind_param($stmtExists, 'is', $companyId, $candidate);
        mysqli_stmt_execute($stmtExists);
        $resExists = mysqli_stmt_get_result($stmtExists);
        $exists = $resExists && mysqli_num_rows($resExists) > 0;
        mysqli_stmt_close($stmtExists);
        if (!$exists) {
            return $candidate;
        }
    }

    return (string)time() . '-' . (string)random_int(1000, 9999);
}

$position_id = (int)($data['position_id'] ?? 0);
$target_position = (int)($data['target_position'] ?? 0);
$overwrite = (int)($data['overwrite'] ?? 0);

if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}
if ($target_position < 1 || $target_position > 250) {
    idf_fail('Invalid target_position');
}

$stmtSrc = mysqli_prepare(
    $conn,
    "SELECT p.*, i.company_id,
            COALESCE(e.switch_rj45_id, er_count.id, 0) AS effective_switch_rj45_id,
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
            ) AS effective_switch_port_numbering_layout_id,
            COALESCE(rj45_count.rj45_port_count, 0) AS actual_rj45_port_count
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     LEFT JOIN equipment e
       ON e.id = p.equipment_id
      AND e.company_id = p.company_id
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
     LEFT JOIN (
        SELECT ip.position_id, COUNT(*) AS rj45_port_count
        FROM idf_ports ip
        LEFT JOIN switch_port_types spt
          ON spt.company_id = ip.company_id
         AND spt.id = ip.port_type
        WHERE LOWER(TRIM(COALESCE(spt.type, ''))) = 'rj45'
        GROUP BY ip.position_id
     ) rj45_count ON rj45_count.position_id = p.id
     WHERE p.id=?
     LIMIT 1"
);
$src = null;
if ($stmtSrc) {
    mysqli_stmt_bind_param($stmtSrc, 'i', $position_id);
    mysqli_stmt_execute($stmtSrc);
    $res = mysqli_stmt_get_result($stmtSrc);
    $src = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmtSrc);
}

if (!$src || (int)$src['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$idf_id = (int)$src['idf_id'];
$device_name = trim((string)($src['device_name'] ?? ''));
$device_name = idf_copy_next_unique_device_name($conn, $company_id, $device_name);

$existing = null;
$stmtEx = mysqli_prepare($conn, "SELECT id, idf_id, equipment_id FROM idf_positions WHERE idf_id=? AND position_no=? LIMIT 1");
if ($stmtEx) {
    mysqli_stmt_bind_param($stmtEx, 'ii', $idf_id, $target_position);
    mysqli_stmt_execute($stmtEx);
    $resEx = mysqli_stmt_get_result($stmtEx);
    if ($resEx) {
        $existing = mysqli_fetch_assoc($resEx);
    }
    mysqli_stmt_close($stmtEx);
}

if ($existing && !$overwrite) {
    idf_fail('Target position occupied');
}

mysqli_begin_transaction($conn);
try {
    if ($existing && $overwrite) {
        $overwrittenEquipmentRaw = trim((string)($existing['equipment_id'] ?? ''));
        $overwrittenIdfId = (int)($existing['idf_id'] ?? 0);
        $overwrittenPositionId = (int)($existing['id'] ?? 0);

        if ($overwrittenPositionId > 0) {
            mysqli_query(
                $conn,
                "DELETE FROM idf_links
                 WHERE company_id = " . (int)$company_id . "
                   AND (
                        port_id_a IN (
                            SELECT id FROM idf_ports
                            WHERE company_id = " . (int)$company_id . "
                              AND position_id = " . $overwrittenPositionId . "
                        )
                        OR
                        port_id_b IN (
                            SELECT id FROM idf_ports
                            WHERE company_id = " . (int)$company_id . "
                              AND position_id = " . $overwrittenPositionId . "
                        )
                   )"
            );
            mysqli_query(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = " . (int)$company_id . "
                   AND position_id = " . $overwrittenPositionId
            );
        }

        $stmtDel = mysqli_prepare($conn, 'DELETE FROM idf_positions WHERE id=? LIMIT 1');
        if ($stmtDel) {
            $exId = $overwrittenPositionId;
            mysqli_stmt_bind_param($stmtDel, 'i', $exId);
            mysqli_stmt_execute($stmtDel);
            mysqli_stmt_close($stmtDel);
        }

        if ($overwrittenEquipmentRaw !== '' && ctype_digit($overwrittenEquipmentRaw) && $overwrittenIdfId > 0) {
            $overwrittenEquipmentId = (int)$overwrittenEquipmentRaw;
            $overwrittenEquipmentIdString = (string)$overwrittenEquipmentId;
            $stillLinkedCount = 0;

            $stmtStillLinked = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS c
                 FROM idf_positions
                 WHERE company_id = ? AND equipment_id = ?"
            );
            if ($stmtStillLinked) {
                mysqli_stmt_bind_param($stmtStillLinked, 'is', $company_id, $overwrittenEquipmentIdString);
                mysqli_stmt_execute($stmtStillLinked);
                $resStillLinked = mysqli_stmt_get_result($stmtStillLinked);
                $stillLinkedRow = $resStillLinked ? mysqli_fetch_assoc($resStillLinked) : null;
                $stillLinkedCount = (int)($stillLinkedRow['c'] ?? 0);
                mysqli_stmt_close($stmtStillLinked);
            }

            if ($stillLinkedCount <= 0) {
                $stmtDetachEquipment = mysqli_prepare(
                    $conn,
                    "UPDATE equipment
                     SET idf_id = NULL
                     WHERE id = ? AND company_id = ? AND idf_id = ?
                     LIMIT 1"
                );
                if ($stmtDetachEquipment) {
                    mysqli_stmt_bind_param($stmtDetachEquipment, 'iii', $overwrittenEquipmentId, $company_id, $overwrittenIdfId);
                    mysqli_stmt_execute($stmtDetachEquipment);
                    mysqli_stmt_close($stmtDetachEquipment);
                }

                $stmtDetachSwitchPorts = mysqli_prepare(
                    $conn,
                    "UPDATE switch_ports
                     SET idf_id = NULL
                     WHERE company_id = ? AND equipment_id = ? AND idf_id = ?"
                );
                if ($stmtDetachSwitchPorts) {
                    mysqli_stmt_bind_param($stmtDetachSwitchPorts, 'iii', $company_id, $overwrittenEquipmentId, $overwrittenIdfId);
                    mysqli_stmt_execute($stmtDetachSwitchPorts);
                    mysqli_stmt_close($stmtDetachSwitchPorts);
                }
            }
        }
    }

    $notes_val = !empty($src['notes']) ? (string)$src['notes'] : null;
    $equip_val = !empty($src['equipment_id']) ? (string)$src['equipment_id'] : null;
    $device_type = (int)$src['device_type'];
    $device_type_name = idf_lookup_idf_device_type_name($conn, $company_id, $device_type);
    $device_name = (string)$device_name;
    $rj45_count = (int)($src['rj45_count'] ?? $src['port_count'] ?? 0);
    $sfp_count = (int)($src['sfp_count'] ?? 0);
    $actualRj45PortCount = (int)($src['actual_rj45_port_count'] ?? 0);
    if ($actualRj45PortCount > 0) {
        $rj45_count = $actualRj45PortCount;
    }
    $layout_val = idf_resolve_position_switch_port_numbering_layout_id(
        $conn,
        $company_id,
        $device_type_name,
        $src['effective_switch_port_numbering_layout_id'] ?? 0,
        $src['switch_port_numbering_layout_id'] ?? 0
    );
    $effectiveSwitchRj45Id = (int)($src['effective_switch_rj45_id'] ?? 0);

    // If this is a linked equipment row, clone equipment + switch ports so copied position gets a new Asset ID.
    $didCloneLinkedEquipment = false;
    if ($equip_val !== null && ctype_digit($equip_val) && (int)$equip_val > 0) {
        $sourceEquipmentId = (int)$equip_val;
        $stmtEq = mysqli_prepare(
            $conn,
            "SELECT *
             FROM equipment
             WHERE id=? AND company_id=? AND deleted_at IS NULL
             LIMIT 1"
        );
        $sourceEquipment = null;
        if ($stmtEq) {
            mysqli_stmt_bind_param($stmtEq, 'ii', $sourceEquipmentId, $company_id);
            mysqli_stmt_execute($stmtEq);
            $resEq = mysqli_stmt_get_result($stmtEq);
            $sourceEquipment = $resEq ? mysqli_fetch_assoc($resEq) : null;
            mysqli_stmt_close($stmtEq);
        }

        if ($sourceEquipment) {
            $newEquipment = $sourceEquipment;
            unset($newEquipment['id']);
            unset($newEquipment['created_at']);
            unset($newEquipment['updated_at']);

            $newEquipment['name'] = idf_copy_next_unique_name_in_table(
                $conn,
                $company_id,
                'equipment',
                'name',
                $device_name
            );
            if ($newEquipment['name'] === '') {
                $newEquipment['name'] = $device_name;
            }
            $newEquipment['idf_id'] = $idf_id;
            if ($effectiveSwitchRj45Id > 0) {
                $newEquipment['switch_rj45_id'] = $effectiveSwitchRj45Id;
            }
            if ($layout_val > 0) {
                $newEquipment['switch_port_numbering_layout_id'] = $layout_val;
            } elseif (!empty($newEquipment['switch_port_numbering_layout_id'])) {
                $newEquipment['switch_port_numbering_layout_id'] = idf_normalize_switch_port_numbering_layout_id(
                    $conn,
                    $company_id,
                    $newEquipment['switch_port_numbering_layout_id']
                );
            }
            // Keep nullable-unique fields safe for cloned records.
            $newEquipment['serial_number'] = null;
            $newEquipment['hostname'] = null;
            $newEquipment['ip_address'] = null;

            $columns = [];
            $valuesSql = [];
            foreach ($newEquipment as $col => $val) {
                $colEsc = '`' . mysqli_real_escape_string($conn, (string)$col) . '`';
                $columns[] = $colEsc;
                if ($val === null || (is_string($val) && strtoupper($val) === 'NULL')) {
                    $valuesSql[] = 'NULL';
                } elseif (is_numeric($val) && !is_string($val)) {
                    $valuesSql[] = (string)$val;
                } else {
                    $valuesSql[] = "'" . mysqli_real_escape_string($conn, (string)$val) . "'";
                }
            }

            $insertEquipmentSql = "INSERT INTO equipment (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $valuesSql) . ")";
            if (!mysqli_query($conn, $insertEquipmentSql)) {
                throw new Exception('Unable to clone linked equipment: ' . mysqli_error($conn));
            }

            $newEquipmentId = (int)mysqli_insert_id($conn);
            if ($newEquipmentId <= 0) {
                throw new Exception('Unable to clone linked equipment.');
            }

            $stmtClonePorts = mysqli_prepare(
                $conn,
                "INSERT INTO switch_ports
                 (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, vlan_id, fiber_port_id, fiber_patch_id, fiber_rack_id, idf_id, to_idf_id, to_rack_id, rack_id, location_id, to_location_id, management_id, comments)
                 SELECT company_id, ?, hostname, port_type, port_number, to_patch_port, status_id, color_id, vlan_id, fiber_port_id, fiber_patch_id, fiber_rack_id, ?, to_idf_id, to_rack_id, rack_id, location_id, to_location_id, management_id, comments
                 FROM switch_ports
                 WHERE company_id = ? AND equipment_id = ?"
            );
            if ($stmtClonePorts) {
                mysqli_stmt_bind_param($stmtClonePorts, 'iiii', $newEquipmentId, $idf_id, $company_id, $sourceEquipmentId);
                mysqli_stmt_execute($stmtClonePorts);
                mysqli_stmt_close($stmtClonePorts);
            }

            $equip_val = (string)$newEquipmentId;
            $didCloneLinkedEquipment = true;
        }
    }
    if (!$didCloneLinkedEquipment) {
        $equip_val = idf_copy_generate_unique_asset_id($conn, $company_id);
    }

    $stmtIns = mysqli_prepare(
        $conn,
        "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, rj45_count, sfp_count, switch_port_numbering_layout_id, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), ?)"
    );
    if ($stmtIns) {
        mysqli_stmt_bind_param($stmtIns, 'iiiissiiis', $company_id, $idf_id, $target_position, $device_type, $device_name, $equip_val, $rj45_count, $sfp_count, $layout_val, $notes_val);
        mysqli_stmt_execute($stmtIns);
        mysqli_stmt_close($stmtIns);
    }

    $newPosId = (int)mysqli_insert_id($conn);

    $ports = [];
    $stmtPorts = mysqli_prepare($conn, 'SELECT * FROM idf_ports WHERE position_id=? ORDER BY port_no');
    if ($stmtPorts) {
        $srcId = (int)$src['id'];
        mysqli_stmt_bind_param($stmtPorts, 'i', $srcId);
        mysqli_stmt_execute($stmtPorts);
        $resPorts = mysqli_stmt_get_result($stmtPorts);
        while ($resPorts && ($p = mysqli_fetch_assoc($resPorts))) {
            $ports[] = $p;
        }
        mysqli_stmt_close($stmtPorts);
    }

    if ($ports) {
        $defaultManagementId = 0;
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
                $defaultManagementId = (int)($unmanagedRow['id'] ?? 0);
            }
        }

        $sqlInsPort = 'INSERT INTO idf_ports (company_id, position_id, port_no, port_type, label, status_id, connected_to, vlan_id, speed_id, rj45_speed_id, poe_id, fiber_ports_number, switch_port_numbering_layout_id, management_id, cable_color, hex_color, notes) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?)';
        $stmtInsPort = mysqli_prepare($conn, $sqlInsPort);
        if ($stmtInsPort) {
            foreach ($ports as $p) {
                $p_no = (int)$p['port_no'];
                $p_type = (int)($p['port_type'] ?? 0);
                $p_label = (string)($p['label'] ?? '');
                $p_status = (int)($p['status_id'] ?? 0);
                $p_conn = (string)($p['connected_to'] ?? '');
                $p_vlan = (int)($p['vlan_id'] ?? 0);
                $p_speed = (int)($p['speed_id'] ?? 0);
                $p_rj45_speed = (int)($p['rj45_speed_id'] ?? 0);
                $p_poe = (int)($p['poe_id'] ?? 0);
                $p_fiber_ports_number = (int)($p['fiber_ports_number'] ?? 0);
                $p_layout_id = $layout_val > 0 ? $layout_val : (int)($p['switch_port_numbering_layout_id'] ?? 0);
                $p_management_id = (int)($p['management_id'] ?? 0);
                if ($p_management_id <= 0) {
                    $p_management_id = $defaultManagementId;
                }
                $p_color = (string)($p['cable_color'] ?? '');
                $p_hex = (string)($p['hex_color'] ?? '');
                $p_notes = (string)($p['notes'] ?? '');

                mysqli_stmt_bind_param($stmtInsPort, 'iiiisisiiiiiiisss',
                    $company_id, $newPosId, $p_no, $p_type, $p_label, $p_status, $p_conn, $p_vlan, $p_speed, $p_rj45_speed, $p_poe, $p_fiber_ports_number, $p_layout_id, $p_management_id, $p_color, $p_hex, $p_notes
                );
                mysqli_stmt_execute($stmtInsPort);
            }
            mysqli_stmt_close($stmtInsPort);
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Copy failed: ' . $e->getMessage(), 500);
}

idf_ok();
