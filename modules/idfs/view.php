<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/port_visualizer_helper.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$idf_id = (int)($_GET['id'] ?? 0);
$idfDebugEnabled = isset($_GET['debug_ports']) && $_GET['debug_ports'] === '1';
if ($idfDebugEnabled && $idf_id === 4) {
    // Why: In some hosts error_log file is created lazily; ensure it exists before writing IDF debug traces.
    $idfDebugLogFile = ROOT_PATH . 'error_log.txt';
    if (!file_exists($idfDebugLogFile)) {
        @file_put_contents($idfDebugLogFile, '');
    }
    // Why: Emit a guaranteed marker so operators can confirm the debug path is active even before port queries run.
    @file_put_contents(
        $idfDebugLogFile,
        '[' . date('Y-m-d H:i:s') . '] [IDF DEBUG] bootstrap idf_id=4 debug_ports=1 root_path=' . ROOT_PATH . ' writable=' . (is_writable($idfDebugLogFile) ? '1' : '0') . PHP_EOL,
        FILE_APPEND
    );
}

function idf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function idf_debug_log_line(string $message): void {
    // Why: Some hosts suppress error_log() writes for custom files; append directly so debug traces are always captured when enabled.
    $idfDebugLogFile = ROOT_PATH . 'error_log.txt';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($idfDebugLogFile, $line, FILE_APPEND);
}

function idf_extract_rj45_count(string $rawLabel): int {
    $label = strtolower(trim($rawLabel));
    if ($label === '') {
        return 0;
    }

    if (preg_match_all('/(\d+)\s*(?:x\s*)?(?:ports?\s*)?rj\s*45\b/i', $label, $rj45Matches) && !empty($rj45Matches[1])) {
        return max(array_map('intval', $rj45Matches[1]));
    }
    if (preg_match_all('/(\d+)/', $label, $allNumberMatches) && !empty($allNumberMatches[1])) {
        return max(array_map('intval', $allNumberMatches[1]));
    }

    return 0;
}

function idf_count_switch_rj45_ports(mysqli $conn, int $companyId, int $equipmentId): int {
    if ($companyId <= 0 || $equipmentId <= 0) {
        return 0;
    }
    $stmtCount = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM switch_ports
         WHERE company_id=? AND equipment_id=? AND LOWER(COALESCE(port_type, 'rj45')) LIKE 'rj45%'"
    );
    if (!$stmtCount) {
        return 0;
    }
    mysqli_stmt_bind_param($stmtCount, 'ii', $companyId, $equipmentId);
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $rowCount = $resCount ? mysqli_fetch_assoc($resCount) : null;
    mysqli_stmt_close($stmtCount);
    return (int)($rowCount['total'] ?? 0);
}

function idf_type_badge(string $t, array $idfDeviceTypeMap): string {
    $raw = trim($t);
    $lookupKey = ctype_digit($raw) ? (int)$raw : strtolower($raw);
    if ($raw !== '' && isset($idfDeviceTypeMap[$lookupKey])) {
        $item = $idfDeviceTypeMap[$lookupKey];
        $emoji = trim((string)($item['emoji'] ?? ''));
        $label = trim((string)($item['label'] ?? ''));
        if ($label === '') {
            $fallbackKey = (string)($item['key'] ?? $raw);
            $label = ucfirst(str_replace('_', ' ', strtolower($fallbackKey)));
        }
        return trim($emoji . ' ' . $label);
    }

    return '📦 Other';
}

$csrf = idf_csrf_token();

$idfDeviceTypeOptions = [];
$idfDeviceTypeMap = [];
$switchDeviceTypeId = 0;
$stmtDeviceTypes = mysqli_prepare(
    $conn,
    "SELECT id, idfdevicetype_name, field_edit_emoji
     FROM idf_device_type
     WHERE company_id=? AND active=1
     ORDER BY id ASC"
);
if ($stmtDeviceTypes) {
    mysqli_stmt_bind_param($stmtDeviceTypes, 'i', $company_id);
    mysqli_stmt_execute($stmtDeviceTypes);
    $resDeviceTypes = mysqli_stmt_get_result($stmtDeviceTypes);
    while ($resDeviceTypes && ($row = mysqli_fetch_assoc($resDeviceTypes))) {
        $typeId = (int)($row['id'] ?? 0);
        $typeNameRaw = (string)($row['idfdevicetype_name'] ?? '');
        $typeName = strtolower(trim($typeNameRaw));
        if ($typeId <= 0 || $typeName === '') {
            continue;
        }
        $emoji = trim((string)($row['field_edit_emoji'] ?? ''));
        $label = ucwords(str_replace('_', ' ', $typeName));
        if (!isset($idfDeviceTypeMap[$typeId])) {
            $idfDeviceTypeMap[$typeId] = [
                'emoji' => $emoji,
                'label' => $label,
                'key' => $typeName,
            ];
        }
        $idfDeviceTypeOptions[] = [
            'value' => $typeId,
            'label' => trim($emoji . ' ' . $label),
        ];
        if ($switchDeviceTypeId === 0 && $typeName === 'switch') {
            $switchDeviceTypeId = $typeId;
        }
    }
    mysqli_stmt_close($stmtDeviceTypes);
}

if (!$idfDeviceTypeOptions) {
    $idfDeviceTypeOptions = [
        ['value' => 1, 'label' => '🔀 Switch'],
        ['value' => 2, 'label' => '➰ Patch Panel'],
        ['value' => 3, 'label' => '🔋 UPS'],
        ['value' => 4, 'label' => '🖥️ Server'],
        ['value' => 5, 'label' => '📦 Other'],
    ];
    $idfDeviceTypeMap = [
        1 => ['emoji' => '🔀', 'label' => 'Switch', 'key' => 'switch'],
        2 => ['emoji' => '➰', 'label' => 'Patch Panel', 'key' => 'patch_panel'],
        3 => ['emoji' => '🔋', 'label' => 'UPS', 'key' => 'ups'],
        4 => ['emoji' => '🖥️', 'label' => 'Server', 'key' => 'server'],
        5 => ['emoji' => '📦', 'label' => 'Other', 'key' => 'other'],
    ];
    $switchDeviceTypeId = 1;
}

$idf = null;
if ($idf_id > 0 && $company_id > 0) {
    $stmtIdf = mysqli_prepare(
        $conn,
        "SELECT i.*, l.name AS location_name, c.company AS company_name,
                COALESCE(r_company.name, r_legacy.name) AS rack_name
         FROM idfs i
         LEFT JOIN it_locations l ON l.id=i.location_id
         LEFT JOIN companies c ON c.id=i.company_id
         LEFT JOIN racks r_company ON r_company.id=i.rack_id AND r_company.company_id=i.company_id
         LEFT JOIN racks r_legacy ON r_legacy.id=i.rack_id
         WHERE i.id=? AND i.company_id=?
         LIMIT 1"
    );
    if ($stmtIdf) {
        mysqli_stmt_bind_param($stmtIdf, 'ii', $idf_id, $company_id);
        mysqli_stmt_execute($stmtIdf);
        $res = mysqli_stmt_get_result($stmtIdf);
        $idf = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmtIdf);
    }
}

if (!$idf) {
    $_SESSION['crud_error'] = 'IDF not found.';
    header('Location: index.php');
    exit;
}


$locationNameLabel = trim((string)($idf['location_name'] ?? ''));
if ($locationNameLabel === '') {
    $locationNameLabel = 'Unknown Location';
}

$positions = [];
$maxPosInDb = 0;
$hasSwitchFiberPortLabelColumn = false;
$hasSwitchFiberPortLabelColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment` LIKE 'switch_fiber_port_label'");
if ($hasSwitchFiberPortLabelColumnRes && mysqli_num_rows($hasSwitchFiberPortLabelColumnRes) > 0) {
    $hasSwitchFiberPortLabelColumn = true;
}
$switchFiberPortLabelSelect = $hasSwitchFiberPortLabelColumn
    ? "COALESCE(e.switch_fiber_port_label, '')"
    : "''";
$hasSwitchPortsLabelColumn = false;
$hasSwitchPortsToPatchPortColumn = false;
$hasSwitchPortsCommentsColumn = false;
$hasSwitchPortsHostnameColumn = false;
$switchPortsLabelColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_ports` LIKE 'label'");
if ($switchPortsLabelColumnRes && mysqli_num_rows($switchPortsLabelColumnRes) > 0) {
    $hasSwitchPortsLabelColumn = true;
}
$switchPortsToPatchPortColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_ports` LIKE 'to_patch_port'");
if ($switchPortsToPatchPortColumnRes && mysqli_num_rows($switchPortsToPatchPortColumnRes) > 0) {
    $hasSwitchPortsToPatchPortColumn = true;
}
$switchPortsCommentsColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_ports` LIKE 'comments'");
if ($switchPortsCommentsColumnRes && mysqli_num_rows($switchPortsCommentsColumnRes) > 0) {
    $hasSwitchPortsCommentsColumn = true;
}
$switchPortsHostnameColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_ports` LIKE 'hostname'");
if ($switchPortsHostnameColumnRes && mysqli_num_rows($switchPortsHostnameColumnRes) > 0) {
    $hasSwitchPortsHostnameColumn = true;
}
$switchPortsLiveLabelSelect = "''";
if ($hasSwitchPortsToPatchPortColumn) {
    $switchPortsLiveLabelSelect = "NULLIF(pr_live.to_patch_port, '')";
} elseif ($hasSwitchPortsLabelColumn) {
    $switchPortsLiveLabelSelect = "NULLIF(pr_live.label, '')";
}
$switchPortsLiveHostnameSelect = $hasSwitchPortsHostnameColumn
    ? "NULLIF(pr_live.hostname, '')"
    : "''";
$switchPortsLiveCommentsSelect = $hasSwitchPortsCommentsColumn
    ? "NULLIF(pr_live.comments, '')"
    : "''";
$switchPortsFallbackHostnameSelect = $hasSwitchPortsHostnameColumn
    ? "COALESCE(sp.hostname, '')"
    : "''";
$switchPortsFallbackCommentsSelect = $hasSwitchPortsCommentsColumn
    ? "COALESCE(sp.comments, '')"
    : "''";
$stmtPos = mysqli_prepare(
    $conn,
    "SELECT p.*, dt.idfdevicetype_name AS device_type_name,
            COALESCE(spnl_equipment.name, spnl.name, 'Vertical') AS layout_name,
            COALESCE(e.switch_fiber_ports_number, 0) AS equipment_fiber_ports_number,
            COALESCE(ef.name, '') AS equipment_fiber_name,
            {$switchFiberPortLabelSelect} AS equipment_fiber_port_label,
            CASE
                WHEN UPPER(COALESCE(et.code, '')) = 'SWITCH' THEN 1
                WHEN UPPER(COALESCE(et.name, '')) = 'SWITCH' THEN 1
                WHEN LOWER(COALESCE(dt.idfdevicetype_name, '')) LIKE 'switch' THEN 1
                ELSE 0
            END AS equipment_is_switch
     FROM idf_positions p
     LEFT JOIN idf_device_type dt ON dt.id = p.device_type AND dt.company_id = p.company_id
     LEFT JOIN equipment e ON e.id = p.equipment_id AND e.company_id = p.company_id
     LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
     LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id AND ef.company_id = e.company_id
     LEFT JOIN switch_port_numbering_layout spnl
       ON spnl.id = p.switch_port_numbering_layout_id
      AND spnl.company_id = p.company_id
     LEFT JOIN switch_port_numbering_layout spnl_equipment
       ON spnl_equipment.id = e.switch_port_numbering_layout_id
      AND spnl_equipment.company_id = e.company_id
     WHERE p.idf_id=? AND p.company_id=?
     ORDER BY p.position_no ASC"
);
if ($stmtPos) {
    mysqli_stmt_bind_param($stmtPos, 'ii', $idf_id, $company_id);
    mysqli_stmt_execute($stmtPos);
    $resPos = mysqli_stmt_get_result($stmtPos);
    $idfDebugPositionCount = 0;
    while ($resPos && ($row = mysqli_fetch_assoc($resPos))) {
        $idfDebugPositionCount++;
        $posId = (int)$row['id'];
        $posNo = (int)$row['position_no'];
        if ($posNo > $maxPosInDb) {
            $maxPosInDb = $posNo;
        }
        $row['ports'] = [];

        $stmtPorts = mysqli_prepare(
            $conn,
            "SELECT pr.*,
                    COALESCE(spt.type, 'RJ45') AS port_type_label,
                    COALESCE(pr_live.status_id, pr.status_id) AS effective_status_id,
                    COALESCE(ss_live.status, ss.status, 'Unknown') AS status_label,
                    COALESCE(cc_live.hex_color, cc_ss.hex_color) AS status_color,
                    COALESCE({$switchPortsLiveLabelSelect}, pr.label) AS label,
                    COALESCE({$switchPortsLiveHostnameSelect}, pr.connected_to) AS connected_to,
                    COALESCE(pr_live.vlan_id, pr.vlan_id, l.equipment_vlan_id) AS vlan_id,
                    CASE
                        WHEN v_live.id IS NOT NULL THEN
                            CASE
                                WHEN TRIM(COALESCE(v_live.vlan_name, '')) = '' THEN COALESCE(v_live.vlan_number, '')
                                WHEN TRIM(COALESCE(v_live.vlan_number, '')) = '' THEN v_live.vlan_name
                                ELSE CONCAT(v_live.vlan_number, ' - ', v_live.vlan_name)
                            END
                        WHEN v_pr.id IS NOT NULL THEN
                            CASE
                                WHEN TRIM(COALESCE(v_pr.vlan_name, '')) = '' THEN COALESCE(v_pr.vlan_number, '')
                                WHEN TRIM(COALESCE(v_pr.vlan_number, '')) = '' THEN v_pr.vlan_name
                                ELSE CONCAT(v_pr.vlan_number, ' - ', v_pr.vlan_name)
                            END
                        WHEN v_link.id IS NOT NULL THEN
                            CASE
                                WHEN TRIM(COALESCE(v_link.vlan_name, '')) = '' THEN COALESCE(v_link.vlan_number, '')
                                WHEN TRIM(COALESCE(v_link.vlan_number, '')) = '' THEN v_link.vlan_name
                                ELSE CONCAT(v_link.vlan_number, ' - ', v_link.vlan_name)
                            END
                        ELSE ''
                    END AS vlan_label,
                    COALESCE({$switchPortsLiveCommentsSelect}, pr.notes) AS notes,
                    p_local.position_no AS local_position_no,
                    p_local.device_name AS local_device_name,
                    p_local.equipment_id AS local_equipment_id,
                    i_local.name AS local_idf_name,
                    COALESCE(dt_local.idfdevicetype_name, et_local.name, '') AS local_device_type_label,
                    COALESCE(cc_live.hex_color, pr.hex_color, cc_l.hex_color) AS cable_hex_color,
                    COALESCE(NULLIF(cc_live.color_name, ''), NULLIF(pr.cable_color, ''), NULLIF(cc_l.color_name, ''), cc_l.hex_color, '') AS cable_color_name,
                    l.id AS link_id,
                    COALESCE(l.cable_label, '') AS cable_label,
                    l.notes AS link_notes,
                    pr_remote.port_no AS remote_port_no,
                    pr_remote.status_id AS remote_status_id,
                    COALESCE(ss_remote.status, 'Unknown') AS remote_status_label,
                    p_remote.position_no AS remote_position_no,
                    p_remote.device_name AS remote_device_name,
                    p_remote.equipment_id AS remote_equipment_id,
                    COALESCE(dt_remote.idfdevicetype_name, et_remote.name, '') AS remote_device_type_label
             FROM idf_ports pr
             JOIN idf_positions p_local
               ON p_local.company_id = pr.company_id
              AND p_local.idf_id = ?
              AND (p_local.id = pr.position_id OR p_local.position_no = pr.position_id)
             JOIN idfs i_local ON i_local.id = p_local.idf_id
             LEFT JOIN equipment e_local ON e_local.id = p_local.equipment_id AND e_local.company_id = p_local.company_id
             LEFT JOIN equipment_types et_local ON et_local.id = e_local.equipment_type_id AND et_local.company_id = e_local.company_id
             LEFT JOIN idf_device_type dt_local ON dt_local.id = p_local.device_type AND dt_local.company_id = p_local.company_id
             LEFT JOIN switch_port_types spt ON spt.id = pr.port_type AND spt.company_id = pr.company_id
             LEFT JOIN switch_ports pr_live ON pr_live.company_id = pr.company_id
                 AND pr_live.equipment_id = p_local.equipment_id
                 AND pr_live.port_number = pr.port_no
                 AND pr_live.port_type = spt.type
             LEFT JOIN switch_status ss_live ON ss_live.id = pr_live.status_id AND ss_live.company_id = pr_live.company_id
             LEFT JOIN cable_colors cc_live ON cc_live.id = pr_live.color_id AND cc_live.company_id = pr_live.company_id
             LEFT JOIN vlans v_live ON v_live.id = pr_live.vlan_id AND v_live.company_id = pr_live.company_id
             LEFT JOIN switch_status ss ON ss.id = pr.status_id AND ss.company_id = pr.company_id
             LEFT JOIN cable_colors cc_ss ON cc_ss.id = ss.color_id AND cc_ss.company_id = ss.company_id
             LEFT JOIN vlans v_pr ON v_pr.id = pr.vlan_id AND v_pr.company_id = pr.company_id
             LEFT JOIN idf_links l ON l.id = (
                 SELECT l2.id
                 FROM idf_links l2
                 WHERE (l2.port_id_a = pr.id OR l2.port_id_b = pr.id) AND l2.company_id = pr.company_id
                 ORDER BY l2.id ASC
                 LIMIT 1
             )
             LEFT JOIN vlans v_link ON v_link.id = l.equipment_vlan_id AND v_link.company_id = l.company_id
             LEFT JOIN idf_ports pr_remote
               ON pr_remote.id = CASE
                    WHEN l.port_id_a = pr.id THEN l.port_id_b
                    WHEN l.port_id_b = pr.id THEN l.port_id_a
                   ELSE NULL
               END
             LEFT JOIN switch_status ss_remote ON ss_remote.id = pr_remote.status_id AND ss_remote.company_id = pr_remote.company_id
             LEFT JOIN idf_positions p_remote ON p_remote.id = pr_remote.position_id AND p_remote.company_id = pr_remote.company_id
             LEFT JOIN equipment e_remote ON e_remote.id = p_remote.equipment_id AND e_remote.company_id = p_remote.company_id
             LEFT JOIN equipment_types et_remote ON et_remote.id = e_remote.equipment_type_id AND et_remote.company_id = e_remote.company_id
             LEFT JOIN idf_device_type dt_remote ON dt_remote.id = p_remote.device_type AND dt_remote.company_id = p_remote.company_id
             LEFT JOIN cable_colors cc_l ON cc_l.id = l.cable_color_id AND cc_l.company_id = l.company_id
             WHERE pr.company_id = ?
               AND (pr.position_id = ? OR pr.position_id = ?)
             ORDER BY pr.port_no ASC"
        );
        if ($stmtPorts) {
            mysqli_stmt_bind_param($stmtPorts, 'iiii', $idf_id, $company_id, $posId, $posNo);
            mysqli_stmt_execute($stmtPorts);
            $portRes = mysqli_stmt_get_result($stmtPorts);
            $idfPortsCount = 0;
            while ($portRes && ($pRow = mysqli_fetch_assoc($portRes))) {
                $idfPortsCount++;
                // Why: Newly added devices should render ports with a neutral gray color when status/link colors are missing.
                $statusColor = trim((string)($pRow['status_color'] ?? ''));
                $cableColor = trim((string)($pRow['cable_hex_color'] ?? ''));
                if ($statusColor === '') {
                    $pRow['status_color'] = $cableColor !== '' ? $cableColor : '#808080';
                }
                $row['ports'][] = $pRow;
            }
            mysqli_stmt_close($stmtPorts);

            if ($idfDebugEnabled && $idf_id === 4) {
                idf_debug_log_line('[IDF DEBUG] idf_id=4 position_id=' . $posId . ' idf_ports_count=' . $idfPortsCount . ' company_id=' . $company_id);
            }
        } elseif ($idfDebugEnabled && $idf_id === 4) {
            idf_debug_log_line('[IDF DEBUG] idf_id=4 position_id=' . $posId . ' idf_ports_prepare_failed=' . mysqli_error($conn));
        }

        if (empty($row['ports']) && (int)($row['equipment_id'] ?? 0) > 0) {
            // Why: Some legacy racks link switch hardware before IDF ports are synced, so render live switch ports as fallback.
            $equipmentIdForFallback = (int)$row['equipment_id'];
            $stmtLivePorts = mysqli_prepare(
                $conn,
                "SELECT sp.id,
                        sp.port_number AS port_no,
                        COALESCE(sp.port_type, 'RJ45') AS port_type_label,
                        " . ($hasSwitchPortsToPatchPortColumn ? "COALESCE(sp.to_patch_port, '')" : "''") . " AS label,
                        {$switchPortsFallbackHostnameSelect} AS connected_to,
                        {$switchPortsFallbackCommentsSelect} AS notes,
                        COALESCE(ss.status, 'Unknown') AS status_label,
                        COALESCE(cc.hex_color, '#808080') AS status_color,
                        COALESCE(cc.hex_color, '') AS cable_hex_color,
                        COALESCE(cc.color_name, '') AS cable_color_name,
                        sp.vlan_id AS vlan_id,
                        CASE
                            WHEN v.id IS NULL THEN ''
                            WHEN TRIM(COALESCE(v.vlan_name, '')) = '' THEN COALESCE(v.vlan_number, '')
                            WHEN TRIM(COALESCE(v.vlan_number, '')) = '' THEN v.vlan_name
                            ELSE CONCAT(v.vlan_number, ' - ', v.vlan_name)
                        END AS vlan_label,
                        p.position_no AS local_position_no,
                        p.device_name AS local_device_name,
                        p.equipment_id AS local_equipment_id,
                        i.name AS local_idf_name,
                        COALESCE(dt.idfdevicetype_name, et.name, '') AS local_device_type_label
                 FROM switch_ports sp
                 LEFT JOIN switch_status ss ON ss.id = sp.status_id AND ss.company_id = sp.company_id
                 LEFT JOIN cable_colors cc ON cc.id = sp.color_id AND cc.company_id = sp.company_id
                 LEFT JOIN vlans v ON v.id = sp.vlan_id AND v.company_id = sp.company_id
                 LEFT JOIN idf_positions p ON p.id = ? AND p.company_id = ?
                 LEFT JOIN idfs i ON i.id = p.idf_id
                 LEFT JOIN equipment e ON e.id = p.equipment_id AND e.company_id = p.company_id
                 LEFT JOIN equipment_types et ON et.id = e.equipment_type_id AND et.company_id = e.company_id
                 LEFT JOIN idf_device_type dt ON dt.id = p.device_type AND dt.company_id = p.company_id
                 WHERE sp.company_id = ? AND sp.equipment_id = ?
                 ORDER BY sp.port_number ASC"
            );
            if ($stmtLivePorts) {
                mysqli_stmt_bind_param($stmtLivePorts, 'iiii', $posId, $company_id, $company_id, $equipmentIdForFallback);
                mysqli_stmt_execute($stmtLivePorts);
                $livePortRes = mysqli_stmt_get_result($stmtLivePorts);
                $liveFallbackPortIndex = 0;
                $livePortsCount = 0;
                while ($livePortRes && ($liveRow = mysqli_fetch_assoc($livePortRes))) {
                    $livePortsCount++;
                    $liveFallbackPortIndex++;
                    $rawFallbackPortNo = trim((string)($liveRow['port_no'] ?? ''));
                    if ($rawFallbackPortNo === '' || !preg_match('/\d+/', $rawFallbackPortNo, $liveNoMatch)) {
                        // Why: Visualizer layout needs numeric slots; legacy switch port labels may be text-only.
                        $liveRow['port_no'] = $liveFallbackPortIndex;
                    } else {
                        $liveRow['port_no'] = (int)$liveNoMatch[0];
                    }
                    $row['ports'][] = $liveRow;
                }
                mysqli_stmt_close($stmtLivePorts);

                if ($idfDebugEnabled && $idf_id === 4) {
                    idf_debug_log_line('[IDF DEBUG] idf_id=4 position_id=' . $posId . ' switch_ports_count=' . $livePortsCount . ' equipment_id=' . $equipmentIdForFallback . ' company_id=' . $company_id);
                }
            }
        }


        $rj45PortCount = idf_extract_rj45_count((string)($row['switch_rj45_name'] ?? ''));
        if ($rj45PortCount <= 0) {
            foreach (($row['ports'] ?? []) as $itmPortMeta) {
                $itmTypeRaw = strtolower(trim((string)($itmPortMeta['port_type_label'] ?? ($itmPortMeta['port_type'] ?? ''))));
                if (strpos($itmTypeRaw, 'sfp') === false) {
                    $rj45PortCount++;
                }
            }
        }
        $equipmentIdForRj45 = (int)($row['equipment_id'] ?? 0);
        $switchPortsRj45Count = 0;
        if ($rj45PortCount <= 0 && $equipmentIdForRj45 > 0) {
            // Why: Some deployments keep detailed RJ45 rows only in switch_ports, while idf_ports contains partial (often fiber-only) links.
            $switchPortsRj45Count = idf_count_switch_rj45_ports($conn, $company_id, $equipmentIdForRj45);
            if ($switchPortsRj45Count > $rj45PortCount) {
                $rj45PortCount = $switchPortsRj45Count;
            }
        }
        $row['rj45_ports'] = $rj45PortCount > 0 ? range(1, $rj45PortCount) : [];
        if ($idfDebugEnabled && $idf_id === 4) {
            idf_debug_log_line(
                '[IDF DEBUG] idf_id=4 position_id=' . $posId
                . ' rj45_count=' . $rj45PortCount
                . ' switch_rj45_name="' . trim((string)($row['switch_rj45_name'] ?? '')) . '"'
                . ' derived_from_ports=' . (empty($row['switch_rj45_name']) ? '1' : '0')
                . ' switch_ports_rj45_count=' . $switchPortsRj45Count
                . ' company_id=' . $company_id
            );
        }

        $fiberPortCount = 0;
        $explicitSfpPorts = [];
        $explicitSfpPlusPorts = [];
        foreach (($row['ports'] ?? []) as $itmPortFiberMeta) {
            $itmFiberTypeRaw = strtolower(trim((string)($itmPortFiberMeta['port_type_label'] ?? ($itmPortFiberMeta['port_type'] ?? ''))));
            if (strpos($itmFiberTypeRaw, 'sfp') !== false) {
                $itmFiberNo = (int)($itmPortFiberMeta['port_no'] ?? 0);
                if ($itmFiberNo > $fiberPortCount) {
                    $fiberPortCount = $itmFiberNo;
                }
                if ($itmFiberNo > 0) {
                    if (strpos($itmFiberTypeRaw, 'sfp+') !== false) {
                        $explicitSfpPlusPorts[$itmFiberNo] = $itmFiberNo;
                    } else {
                        $explicitSfpPorts[$itmFiberNo] = $itmFiberNo;
                    }
                }
            }
        }
        if ($fiberPortCount <= 0) {
            // Why: Keep legacy equipment-level fallback only when no explicit SFP rows are present in IDF/switch port data.
            $fiberPortCount = (int)($row['equipment_fiber_ports_number'] ?? 0);
        }
        $fiberPortHint = strtolower(trim(
            (string)($row['equipment_fiber_port_label'] ?? '') . ' ' . (string)($row['equipment_fiber_name'] ?? '')
        ));
        $row['sfp_ports'] = array_values($explicitSfpPorts);
        $row['sfp_plus_ports'] = array_values($explicitSfpPlusPorts);
        sort($row['sfp_ports']);
        sort($row['sfp_plus_ports']);
        if (empty($row['sfp_ports']) && empty($row['sfp_plus_ports']) && $fiberPortCount > 0) {
            if (strpos($fiberPortHint, 'sfp+') !== false) {
                $row['sfp_plus_ports'] = range(1, $fiberPortCount);
            } else {
                // Why: Fiber port count must always surface in rack preview even when legacy labels are blank/non-standard.
                $row['sfp_ports'] = range(1, $fiberPortCount);
            }
        }

        $positions[$posNo] = $row;
    }
    if ($idfDebugEnabled && $idf_id === 4) {
        idf_debug_log_line('[IDF DEBUG] idf_id=4 positions_loaded=' . $idfDebugPositionCount . ' company_id=' . $company_id);
    }
    mysqli_stmt_close($stmtPos);
} elseif ($idfDebugEnabled && $idf_id === 4) {
    idf_debug_log_line('[IDF DEBUG] idf_id=4 positions_prepare_failed=' . mysqli_error($conn));
}

$displayMaxPos = max(10, $maxPosInDb);

$equipmentOptions = [];
$stmtEq = mysqli_prepare(
    $conn,
    "SELECT e.id, e.name, e.hostname, e.notes, e.switch_rj45_id, e.switch_port_numbering_layout_id, er.name AS switch_rj45_name
     FROM equipment e
     LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id AND er.company_id = e.company_id
     WHERE e.company_id=?
     ORDER BY e.name ASC
     LIMIT 500"
);
if ($stmtEq) {
    mysqli_stmt_bind_param($stmtEq, 'i', $company_id);
    mysqli_stmt_execute($stmtEq);
    $resEq = mysqli_stmt_get_result($stmtEq);
    while ($resEq && ($row = mysqli_fetch_assoc($resEq))) {
        $equipmentOptions[] = $row;
    }
    mysqli_stmt_close($stmtEq);
}

$switchRj45Options = [];
$stmtRj45 = mysqli_prepare(
    $conn,
    "SELECT id, name
     FROM equipment_rj45
     WHERE company_id=?
     ORDER BY name ASC"
);
if ($stmtRj45) {
    mysqli_stmt_bind_param($stmtRj45, 'i', $company_id);
    mysqli_stmt_execute($stmtRj45);
    $resRj45 = mysqli_stmt_get_result($stmtRj45);
    while ($resRj45 && ($row = mysqli_fetch_assoc($resRj45))) {
        $switchRj45Options[] = $row;
    }
    mysqli_stmt_close($stmtRj45);
}

$equipmentTypeOptions = [];
$switchEquipmentTypeId = 0;
$stmtEqTypes = mysqli_prepare(
    $conn,
    "SELECT id, name
     FROM equipment_types
     WHERE company_id=?
     ORDER BY name ASC"
);
if ($stmtEqTypes) {
    mysqli_stmt_bind_param($stmtEqTypes, 'i', $company_id);
    mysqli_stmt_execute($stmtEqTypes);
    $resEqTypes = mysqli_stmt_get_result($stmtEqTypes);
    while ($resEqTypes && ($row = mysqli_fetch_assoc($resEqTypes))) {
        $typeId = (int)($row['id'] ?? 0);
        $typeName = (string)($row['name'] ?? '');
        $equipmentTypeOptions[] = [
            'value' => $typeId,
            'label' => $typeName,
        ];
        if ($switchEquipmentTypeId === 0 && strcasecmp(trim($typeName), 'switch') === 0) {
            $switchEquipmentTypeId = $typeId;
        }
    }
    mysqli_stmt_close($stmtEqTypes);
}

$equipmentStatusFieldOptions = [];
$stmtEquipmentStatuses = mysqli_prepare(
    $conn,
    "SELECT id, name
     FROM equipment_statuses
     WHERE company_id=?
     ORDER BY name ASC"
);
if ($stmtEquipmentStatuses) {
    mysqli_stmt_bind_param($stmtEquipmentStatuses, 'i', $company_id);
    mysqli_stmt_execute($stmtEquipmentStatuses);
    $resEquipmentStatuses = mysqli_stmt_get_result($stmtEquipmentStatuses);
    while ($resEquipmentStatuses && ($row = mysqli_fetch_assoc($resEquipmentStatuses))) {
        $equipmentStatusFieldOptions[] = [
            'value' => (int)($row['id'] ?? 0),
            'label' => (string)($row['name'] ?? ''),
        ];
    }
    mysqli_stmt_close($stmtEquipmentStatuses);
}

$switchRj45FieldOptions = [];
foreach ($switchRj45Options as $switchRj45Option) {
    $switchRj45FieldOptions[] = [
        'value' => (int)($switchRj45Option['id'] ?? 0),
        'label' => (string)($switchRj45Option['name'] ?? ''),
    ];
}

$switchLayoutOptions = [];
$defaultHorizontalLayoutId = 0;
$defaultVerticalLayoutId = 0;
$stmtLayout = mysqli_prepare(
    $conn,
    "SELECT id, name
     FROM switch_port_numbering_layout
     WHERE company_id=?
     ORDER BY name ASC"
);
if ($stmtLayout) {
    mysqli_stmt_bind_param($stmtLayout, 'i', $company_id);
    mysqli_stmt_execute($stmtLayout);
    $resLayout = mysqli_stmt_get_result($stmtLayout);
    while ($resLayout && ($row = mysqli_fetch_assoc($resLayout))) {
        $switchLayoutOptions[] = $row;
        $layoutName = trim((string)($row['name'] ?? ''));
        if ($defaultHorizontalLayoutId === 0 && strcasecmp($layoutName, 'horizontal') === 0) {
            $defaultHorizontalLayoutId = (int)($row['id'] ?? 0);
        }
        if ($defaultVerticalLayoutId === 0 && strcasecmp($layoutName, 'vertical') === 0) {
            $defaultVerticalLayoutId = (int)($row['id'] ?? 0);
        }
    }
    mysqli_stmt_close($stmtLayout);
}

$equipmentAddExtraFields = json_encode([
    [
        'name' => 'equipment_type_id',
        'label' => 'Equipment Type',
        'type' => 'select',
        'options' => $equipmentTypeOptions,
    ],
    [
        'name' => 'switch_rj45_id',
        'label' => 'RJ45 Ports (required for Switch)',
        'type' => 'select',
        'options' => $switchRj45FieldOptions,
        'required' => false,
        'required_when' => [
            'field' => 'equipment_type_id',
            'equals' => (string)$switchEquipmentTypeId,
        ],
    ],
    [
        'name' => 'status_id',
        'label' => 'Status',
        'type' => 'select',
        'options' => $equipmentStatusFieldOptions,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ui_config = itm_get_ui_configuration($conn, $company_id);

$equipmentLookup = [];
foreach ($equipmentOptions as $equipmentOption) {
    $portCount = 0;
    if (!empty($equipmentOption['switch_rj45_name']) && preg_match('/(\d+)/', (string)$equipmentOption['switch_rj45_name'], $matches)) {
        $portCount = (int)$matches[1];
    }
    $equipmentLookup[(int)$equipmentOption['id']] = [
        'name' => (string)($equipmentOption['name'] ?? ''),
        'switch_rj45_id' => (int)($equipmentOption['switch_rj45_id'] ?? 0),
        'switch_port_numbering_layout_id' => (int)($equipmentOption['switch_port_numbering_layout_id'] ?? 0),
        'port_count' => $portCount,
        'notes' => (string)($equipmentOption['notes'] ?? ''),
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>IDF View</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <style>
        .idf-view-shell { display:grid; gap:16px; }
        .idf-command-bar {
            border:1px solid var(--border);
            border-radius:18px;
            padding:14px;
            background: linear-gradient(120deg, rgba(9,105,218,.14), rgba(9,105,218,.04));
            box-shadow: var(--shadow);
            display:flex;
            justify-content:space-between;
            gap:14px;
            flex-wrap:wrap;
            align-items:center;
        }
        .idf-command-title { display:flex; flex-direction:column; gap:4px; }
        .idf-command-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .idf-rack-wrap {
            border:1px solid var(--border);
            background:var(--bg-secondary);
            border-radius:20px;
            padding:14px;
            box-shadow: var(--shadow);
        }
        .idf-rack { background:var(--bg-primary); border-radius:16px; border:1px solid var(--border); padding:12px; }
        .idf-rack-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .idf-slot { border:1px solid var(--border); background:var(--bg-primary); border-radius:12px; padding:10px; margin-bottom:8px; }
        .idf-slot:hover { box-shadow: var(--shadow); transform: translateY(-1px); transition:all .15s ease; }
        .idf-slot-left { display:flex; gap:10px; align-items:flex-start; }
        .idf-slot-no {
            width:32px;
            min-width:32px;
            height:32px;
            border-radius:999px;
            border:1px solid var(--border);
            display:grid;
            place-items:center;
            font-weight:700;
            background:var(--bg-secondary);
        }
        .idf-slot-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
        .idf-badge {
            display:inline-flex;
            align-items:center;
            gap:4px;
            border:1px solid var(--border);
            border-radius:999px;
            font-size:11px;
            padding:2px 8px;
            background:var(--bg-secondary);
        }
        .idf-mini { min-width: 58px; }
        .idf-grid-2 { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .idf-modal-backdrop {
            display:none;
            position:fixed;
            top:0;
            right:0;
            bottom:0;
            left:250px;
            background:rgba(0, 0, 0, .45);
            z-index:1200;
            padding:20px;
            overflow:auto;
            align-items:flex-start;
            justify-content:center;
        }
        .idf-modal {
            width:min(560px, calc(100vw - 300px));
            margin:24px auto;
            border-radius:14px;
            border:1px solid var(--border);
            background:var(--bg-primary);
            box-shadow: var(--shadow-lg);
            padding:14px;
        }
        .idf-modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; gap:8px; }
        body.sidebar-collapsed .idf-modal-backdrop { left:0; }
        @media (max-width: 768px) {
            .idf-modal-backdrop { left:0; padding:12px; }
            .idf-modal { width:100%; margin:10px auto; }
            .idf-grid-2 { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="content">
            <div class="idf-view-shell">
                <section class="idf-command-bar">
                    <div class="idf-command-title">
                        <div style="display:flex; gap:8px; align-items:center;">
                            <a class="btn btn-sm" href="index.php">← Back</a>
                            <div class="idf-rack-title">
                                🗄️ IDF <?php echo sanitize($idf['name']); ?> - <?php echo sanitize($locationNameLabel); ?>
                                <?php if (!empty($idf['idf_code'])): ?><span class="idf-badge"><?php echo sanitize($idf['idf_code']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div style="opacity:.85; font-size:12px;">
                             <span class="idf-badge idf-drag-hint">Drag &amp; drop enabled</span>
                        </div>
                    </div>
                    <div class="idf-command-actions">
                        <button class="btn btn-sm" type="button" onclick="idfExportExcel()">Export Excel</button>
                        <button class="btn btn-sm" type="button" onclick="idfExportImage()">Export Image</button>
                        <button class="btn btn-sm" type="button" onclick="idfExportPdf()">Export PDF</button>
                    </div>
                </section>

                <div id="idfCaptureRoot" class="idf-rack-wrap">
                    <div class="idf-rack">
                        <div class="idf-rack-header">
                            <div>
                                <div class="idf-rack-title">Rack Face (<?php echo $displayMaxPos; ?> positions)</div>
                                <div style="font-size:12px; opacity:.8; margin-top:2px;">
                                    <?php echo sanitize((string)($idf['company_name'] ?? 'Unknown Company')); ?>
                                    · Location: <?php echo sanitize($locationNameLabel); ?>
                                    · Name: <?php echo sanitize((string)($idf['name'] ?? '')); ?>
                                    · IDF Code: <?php echo sanitize((string)($idf['idf_code'] ?? 'N/A')); ?>
                                    · Rack: <?php echo sanitize((string)($idf['rack_name'] ?? 'N/A')); ?>
                                </div>
                            </div>
                            <span class="idf-badge">Move ↑ ↓ • Drag • Copy • Ports</span>
                        </div>

                        <div class="idf-slots" id="idfSlots">
                            <?php for ($i = 1; $i <= $displayMaxPos; $i++): $pos = $positions[$i] ?? null; ?>
                                <div class="idf-slot" data-position="<?php echo $i; ?>">
                                    <div class="idf-slot-left">
                                        <div class="idf-slot-no"><?php echo $i; ?></div>

                                        <div class="idf-slot-meta">
                                            <?php if (!$pos): ?>
                                                <div class="idf-slot-name idf-empty">Empty position</div>
                                                <div class="idf-slot-sub">
                                                    <span class="idf-badge">No device</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="idf-slot-name"><?php echo sanitize($pos['device_name']); ?></div>
                                                <div class="idf-slot-sub">
                                                    <span class="idf-badge"><?php echo sanitize(idf_type_badge((string)($pos['device_type'] ?? ''), $idfDeviceTypeMap)); ?></span>
                                                    <?php if ((int)$pos['port_count'] > 0): ?>
                                                        <span class="idf-badge">🔌 <?php echo (int)$pos['port_count']; ?> ports</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($pos['equipment_id'])): ?>
                                                        <span class="idf-badge">🧾 Asset ID <?php echo sanitize((string)$pos['equipment_id']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="margin-top: 8px;">
                                                    <?php
                                                    echo itm_render_port_visualizer($pos['ports'] ?? [], [
                                                        'layout' => (string)($pos['layout_name'] ?? 'Vertical'),
                                                        'show_device_icon' => ((int)($pos['equipment_is_switch'] ?? 0) === 1),
                                                        'clickable' => true,
                                                        'company_name' => (string)($idf['company_name'] ?? ''),
                                                        'location_name' => $locationNameLabel,
                                                        'idf_name' => (string)($idf['name'] ?? ''),
                                                        'idf_code' => (string)($idf['idf_code'] ?? ''),
                                                        'rack_name' => (string)($idf['rack_name'] ?? ''),
                                                        'grid_port_type' => 'rj45',
                                                        'rj45_ports' => (array)($pos['rj45_ports'] ?? []),
                                                        'sfp_ports' => (array)($pos['sfp_ports'] ?? []),
                                                        'sfp_plus_ports' => (array)($pos['sfp_plus_ports'] ?? []),
                                                    ]);
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="idf-slot-actions">
                                        <button class="btn btn-sm idf-mini" type="button" onclick="idfMove(<?php echo $idf_id; ?>, <?php echo $i; ?>, 'up')">↑</button>
                                        <button class="btn btn-sm idf-mini" type="button" onclick="idfMove(<?php echo $idf_id; ?>, <?php echo $i; ?>, 'down')">↓</button>

                                        <?php if ($pos): ?>
                                            <a class="btn btn-sm idf-mini" href="device.php?position_id=<?php echo (int)$pos['id']; ?>">View</a>
                                            <button class="btn btn-sm idf-mini" type="button" onclick="openDeviceModal(<?php echo $i; ?>, <?php echo (int)$pos['id']; ?>)">Edit</button>
                                            <?php
                                            $idfEquipmentIdRaw = isset($pos['equipment_id']) ? trim((string)$pos['equipment_id']) : '';
                                            $idfShowEditLinked = ($idfEquipmentIdRaw !== '' && strpos($idfEquipmentIdRaw, '-') === false);
                                            ?>
                                            <?php if ($idfShowEditLinked): ?>
                                                <a class="btn btn-sm idf-mini" href="../equipment/index.php?switch_id=<?php echo (int)$idfEquipmentIdRaw; ?>&spm=1#switch-port-manager">Edit Linked</a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm idf-mini" type="button" onclick="openCopyModal(<?php echo $i; ?>, <?php echo (int)$pos['id']; ?>)">Copy to…</button>
                                            <button class="btn btn-sm idf-mini" type="button" onclick="idfDeleteDevice(<?php echo (int)$pos['id']; ?>)">Delete</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm idf-mini" type="button" onclick="openDeviceModal(<?php echo $i; ?>, null)">Add device</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <table id="idfExportTable" class="table" style="display:none;">
                <thead>
                <tr>
                    <th>Position</th><th>Device Type</th><th>Device Name</th><th>Ports</th><th>Equipment ID</th><th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php for ($i = 1; $i <= $displayMaxPos; $i++): $pos = $positions[$i] ?? null; ?>
                    <tr>
                        <td><?php echo $i; ?></td>
                        <td><?php echo $pos ? sanitize((string)($pos['device_type_name'] ?? '')) : ''; ?></td>
                        <td><?php echo $pos ? sanitize((string)$pos['device_name']) : ''; ?></td>
                        <td><?php echo $pos ? (int)$pos['port_count'] : 0; ?></td>
                        <td><?php echo $pos ? sanitize((string)($pos['equipment_id'] ?? '')) : ''; ?></td>
                        <td><?php echo $pos ? sanitize((string)($pos['notes'] ?? '')) : ''; ?></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="idf-modal-backdrop" id="idfModalBackdrop" onclick="closeModalIfBackdrop(event)">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title" id="idfModalTitle">Device</div>
            <button class="btn btn-sm" type="button" onclick="closeModal()">✖</button>
        </div>

        <form id="idfDeviceForm" class="idf-grid-2">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
            <input type="hidden" name="idf_id" value="<?php echo (int)$idf_id; ?>">
            <input type="hidden" name="position_no" id="idfPositionNo" value="">
            <input type="hidden" name="position_id" id="idfPositionId" value="">

            <div>
                <label class="label">Device Type</label>
                <select class="input" name="device_type" required>
                    <?php foreach ($idfDeviceTypeOptions as $idfDeviceTypeOption): ?>
                        <option value="<?php echo sanitize((string)$idfDeviceTypeOption['value']); ?>"><?php echo sanitize((string)$idfDeviceTypeOption['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="label">Device Name</label>
                <input class="input" name="device_name" placeholder="e.g. Cisco 2960X (48p)" required>
            </div>

            <div>
                <label class="label">Linked to Equipment (optional)</label>
                <select class="input" name="equipment_id" data-addable-select="1" data-add-table="equipment" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="equipment" data-add-extra-fields='<?php echo sanitize((string)$equipmentAddExtraFields); ?>'>
                    <option value="">-- None --</option>
                    <?php foreach ($equipmentOptions as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>">
                            <?php echo sanitize($e['name'] . (!empty($e['hostname']) ? (' • Host ' . $e['hostname']) : '')); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__add_new__">➕</option>
                </select>
            </div>

            <div id="idfSwitchLayoutWrap" style="display:none;">
                <label class="label">Numbering Layout</label>
                <select class="input" name="switch_port_numbering_layout_id">
                    <option value="">-- Select --</option>
                    <?php foreach ($switchLayoutOptions as $layoutOption): ?>
                        <option value="<?php echo (int)$layoutOption['id']; ?>"><?php echo sanitize((string)$layoutOption['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="idfPortCountWrap">
                <label class="label">Port Count</label>
                <input class="input" name="port_count" type="number" min="0" max="9999" step="1">
            </div>

            <div id="idfSwitchRj45Wrap" style="display:none;">
                <label class="label">RJ45 Ports *</label>
                <select class="input" name="switch_rj45_id" data-addable-select="1" data-add-table="equipment_rj45" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rj45 port option">
                    <option value="">-- Select --</option>
                    <?php foreach ($switchRj45Options as $switchRj45Option): ?>
                        <option value="<?php echo (int)$switchRj45Option['id']; ?>"><?php echo sanitize((string)$switchRj45Option['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">➕</option>
                </select>
            </div>

            <div style="grid-column: 1 / -1;">
                <label class="label">Notes</label>
                <input class="input" name="notes" placeholder="Optional notes">
            </div>

            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" type="button" onclick="saveDevice()">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="idf-modal-backdrop" id="idfCopyBackdrop" onclick="closeCopyIfBackdrop(event)">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Copy device to position</div>
            <button class="btn btn-sm" type="button" onclick="closeCopy()">✖</button>
        </div>

        <form id="idfCopyForm" class="idf-grid-2">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
            <input type="hidden" name="position_id" id="idfCopyPositionId" value="">
            <div>
                <label class="label">Target position</label>
                <select class="input" name="target_position" id="idfCopyTarget">
                    <?php for ($i = 1; $i <= $displayMaxPos + 5; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="label">Behavior</label>
                <select class="input" name="overwrite">
                    <option value="0">Fail if occupied</option>
                    <option value="1">Overwrite target</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" type="button" onclick="copyDevice()">Copy</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>

<script>
const IDF_BASE = '<?php echo BASE_URL; ?>modules/idfs';
const CSRF = '<?php echo sanitize($csrf); ?>';
const SWITCH_DEVICE_TYPE_ID = <?php echo (int)$switchDeviceTypeId; ?>;
const DEFAULT_NON_SWITCH_LAYOUT_ID = <?php echo (int)$defaultHorizontalLayoutId; ?>;
const DEFAULT_SWITCH_LAYOUT_ID = <?php echo (int)$defaultVerticalLayoutId; ?>;
const equipmentMetaById = <?php
$equipmentMeta = [];
foreach ($equipmentOptions as $equipmentOption) {
    $portCount = 0;
    if (!empty($equipmentOption['switch_rj45_name']) && preg_match('/(\d+)/', (string)$equipmentOption['switch_rj45_name'], $matches)) {
        $portCount = (int)$matches[1];
    }
    $equipmentMeta[] = [
        'id' => (int)$equipmentOption['id'],
        'name' => (string)($equipmentOption['name'] ?? ''),
        'notes' => (string)($equipmentOption['notes'] ?? ''),
        'switch_rj45_id' => (int)($equipmentOption['switch_rj45_id'] ?? 0),
        'switch_port_numbering_layout_id' => (int)($equipmentOption['switch_port_numbering_layout_id'] ?? 0),
        'port_count' => $portCount,
    ];
}
echo json_encode($equipmentMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>.reduce((acc, item) => {
    acc[String(item.id)] = item;
    return acc;
}, {});

function closeModalIfBackdrop(e){ if(e.target.id === 'idfModalBackdrop') closeModal(); }
function closeModal(){ document.getElementById('idfModalBackdrop').style.display = 'none'; }
function openModal(){ document.getElementById('idfModalBackdrop').style.display = 'flex'; }

function closeCopyIfBackdrop(e){ if(e.target.id === 'idfCopyBackdrop') closeCopy(); }
function closeCopy(){ document.getElementById('idfCopyBackdrop').style.display = 'none'; }
function openCopy(){ document.getElementById('idfCopyBackdrop').style.display = 'flex'; }

const EQUIPMENT_LOOKUP = <?php echo json_encode($equipmentLookup, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function applyEquipmentRelation(form) {
    const rawEquipmentId = form.equipment_id.value;
    if (!rawEquipmentId) return;

    const equipment = EQUIPMENT_LOOKUP[String(rawEquipmentId)] || null;
    if (!equipment) return;

    form.device_name.value = equipment.name || '';
    form.switch_rj45_id.value = equipment.switch_rj45_id ? String(equipment.switch_rj45_id) : '';
    form.switch_port_numbering_layout_id.value = equipment.switch_port_numbering_layout_id ? String(equipment.switch_port_numbering_layout_id) : '';
    form.port_count.value = equipment.port_count ? String(equipment.port_count) : '';
    form.notes.value = equipment.notes || '';
    refreshPortCountInputs(form);
}

async function apiPost(path, body) {
    const res = await fetch(`${IDF_BASE}/api/${path}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });
    const raw = await res.text();
    let data = {};
    if (raw) {
        try {
            data = JSON.parse(raw);
        } catch (e) {
            if (res.ok) {
                const rawSummary = raw.replace(/\s+/g, ' ').trim();
                const snippet = rawSummary.slice(0, 180);
                const suffix = snippet ? ` Response: ${snippet}` : '';
                throw new Error(`Unexpected server response (HTTP ${res.status}).${suffix}`);
            }
        }
    }
    if (!res.ok) throw new Error(data.error || `Request failed: ${res.status}`);
    if (Object.prototype.hasOwnProperty.call(data, 'ok') && !data.ok) {
        throw new Error(data.error || `Request failed: ${res.status}`);
    }
    return data;
}

function openDeviceModal(positionNo, positionId) {
    document.getElementById('idfModalTitle').textContent = positionId ? `Edit device (Position ${positionNo})` : `Add device (Position ${positionNo})`;
    document.getElementById('idfPositionNo').value = positionNo;
    document.getElementById('idfPositionId').value = positionId || '';

    const form = document.getElementById('idfDeviceForm');
    form.reset();
    form.querySelector('input[name="csrf_token"]').value = CSRF;
    form.querySelector('input[name="idf_id"]').value = '<?php echo (int)$idf_id; ?>';
    form.querySelector('input[name="position_no"]').value = positionNo;
    form.equipment_id.onchange = () => applyEquipmentRelation(form);
    form.dataset.isEdit = positionId ? '1' : '0';

    if (positionId) {
        apiPost('position_get.php', {csrf_token: CSRF, position_id: positionId})
            .then(({position}) => {
                form.device_type.value = position.device_type;
                form.device_name.value = position.device_name;
                form.equipment_id.value = position.equipment_id || '';
                form.equipment_id.dataset.previousValue = form.equipment_id.value || '';
                form.switch_rj45_id.value = position.switch_rj45_id || '';
                form.switch_port_numbering_layout_id.value = position.switch_port_numbering_layout_id || '';
                form.port_count.value = position.port_count || '';
                form.notes.value = position.notes || '';
                refreshPortCountInputs(form);
                syncFieldsFromEquipment(form, false);
                openModal();
            })
            .catch(err => alert(err.message));
    } else {
        refreshPortCountInputs(form);
        syncFieldsFromEquipment(form, false);
        openModal();
    }
}

function syncFieldsFromEquipment(form, shouldAlert) {
    const selectedEquipmentId = String(form.equipment_id.value || '');
    const meta = equipmentMetaById[selectedEquipmentId];
    if (!meta) return;

    form.device_name.value = meta.name || '';
    form.switch_rj45_id.value = meta.switch_rj45_id ? String(meta.switch_rj45_id) : '';
    form.switch_port_numbering_layout_id.value = meta.switch_port_numbering_layout_id ? String(meta.switch_port_numbering_layout_id) : '';
    form.port_count.value = meta.port_count ? String(meta.port_count) : '';
    form.notes.value = meta.notes || '';
    refreshPortCountInputs(form);

    if (shouldAlert) {
        alert('Device Name, Port Count, and Notes were filled from linked equipment.');
    }
}

function refreshPortCountInputs(form) {
    const isSwitch = Number(form.device_type.value || 0) === SWITCH_DEVICE_TYPE_ID;
    const hasLinkedEquipment = String(form.equipment_id.value || '') !== '';
    const portCountWrap = document.getElementById('idfPortCountWrap');
    const switchWrap = document.getElementById('idfSwitchRj45Wrap');
    const layoutWrap = document.getElementById('idfSwitchLayoutWrap');
    if (portCountWrap) portCountWrap.style.display = isSwitch ? 'none' : 'block';
    if (switchWrap) switchWrap.style.display = isSwitch ? 'block' : 'none';
    if (layoutWrap) layoutWrap.style.display = hasLinkedEquipment ? 'none' : 'block';
    form.switch_rj45_id.required = isSwitch;
    const isEditMode = form.dataset.isEdit === '1';
    if (isSwitch && !isEditMode && DEFAULT_SWITCH_LAYOUT_ID > 0) {
        form.switch_port_numbering_layout_id.value = String(DEFAULT_SWITCH_LAYOUT_ID);
    }
    if (!isSwitch && !isEditMode && DEFAULT_NON_SWITCH_LAYOUT_ID > 0) {
        form.switch_port_numbering_layout_id.value = String(DEFAULT_NON_SWITCH_LAYOUT_ID);
    }
    if (!isSwitch) {
        form.switch_rj45_id.value = '';
    }
}

function saveDevice() {
    const form = document.getElementById('idfDeviceForm');
    const payload = {
        csrf_token: CSRF,
        idf_id: Number(form.idf_id.value),
        position_no: Number(form.position_no.value),
        position_id: form.position_id.value ? Number(form.position_id.value) : null,
        device_type: form.device_type.value,
        device_name: form.device_name.value.trim(),
        equipment_id: form.equipment_id.value ? Number(form.equipment_id.value) : null,
        switch_rj45_id: form.switch_rj45_id.value ? Number(form.switch_rj45_id.value) : null,
        switch_port_numbering_layout_id: form.switch_port_numbering_layout_id.value ? Number(form.switch_port_numbering_layout_id.value) : null,
        port_count: form.port_count.value === '' ? null : Number(form.port_count.value),
        notes: form.notes.value.trim(),
    };
    apiPost('position_save.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

document.getElementById('idfDeviceForm').equipment_id.addEventListener('change', function () {
    const form = document.getElementById('idfDeviceForm');
    syncFieldsFromEquipment(form, false);
    refreshPortCountInputs(form);
});
document.getElementById('idfDeviceForm').device_type.addEventListener('change', function () {
    const form = document.getElementById('idfDeviceForm');
    refreshPortCountInputs(form);
});

function openCopyModal(positionNo, positionId) {
    ensureCopyModalOptions();
    document.getElementById('idfCopyPositionId').value = positionId;
    document.getElementById('idfCopyTarget').value = positionNo;
    openCopy();
}

function ensureCopyModalOptions() {
    const targetSelect = document.getElementById('idfCopyTarget');
    if (targetSelect && targetSelect.options.length === 0) {
        const currentMax = <?php echo (int)$displayMaxPos; ?>;
        for (let i = 1; i <= currentMax + 10; i++) {
            const option = document.createElement('option');
            option.value = String(i);
            option.textContent = String(i);
            targetSelect.appendChild(option);
        }
    }

    const copyForm = document.getElementById('idfCopyForm');
    if (!copyForm || !copyForm.overwrite) return;

    if (copyForm.overwrite.options.length === 0) {
        const failOption = document.createElement('option');
        failOption.value = '0';
        failOption.textContent = 'Fail if occupied';
        copyForm.overwrite.appendChild(failOption);

        const overwriteOption = document.createElement('option');
        overwriteOption.value = '1';
        overwriteOption.textContent = 'Overwrite target';
        copyForm.overwrite.appendChild(overwriteOption);
    }
}

function copyDevice() {
    const form = document.getElementById('idfCopyForm');
    const payload = {
        csrf_token: CSRF,
        position_id: Number(form.position_id.value),
        target_position: Number(form.target_position.value),
        overwrite: Number(form.overwrite.value),
    };
    apiPost('position_copy.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function idfMove(idfId, positionNo, dir) {
    apiPost('position_move.php', {csrf_token: CSRF, idf_id: idfId, position_no: positionNo, dir})
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function idfDeleteDevice(positionId) {
    if (!confirm('Delete this device from the rack position? Ports and links will be deleted too.')) return;
    apiPost('position_delete.php', {csrf_token: CSRF, position_id: positionId})
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function onPortClick(portId, portElement) {
    const portNode = portElement && portElement.dataset ? portElement : null;
    const statusLabelRaw = portNode ? String(portNode.dataset.portStatusLabel || '').trim().toLowerCase() : '';
    const positionId = portNode ? Number(portNode.dataset.positionId || 0) : 0;
    if (!positionId) {
        alert('Position not found for this port.');
        return;
    }

    const url = new URL('device.php', window.location.href);
    url.searchParams.set('position_id', String(positionId));

    if (statusLabelRaw === 'unknown') {
        url.searchParams.set('open_link_port_id', String(portId));
    } else {
        url.searchParams.set('open_edit_port_id', String(portId));
    }

    window.location.href = url.toString();
}


function onPortDotClick(portElement) {
    const portNode = portElement && portElement.dataset ? portElement : null;
    if (!portNode) {
        return;
    }

    const portId = Number(portNode.dataset.portId || 0);
    if (portId > 0) {
        onPortClick(portId, portElement);
        return;
    }

    const positionId = Number(portNode.dataset.positionId || 0);
    const portNo = Number(portNode.dataset.portNumber || 0);
    const portType = String(portNode.dataset.portType || 'sfp').toUpperCase();
    if (!positionId) {
        alert('Position not found for this SFP port.');
        return;
    }

    const url = new URL('device.php', window.location.href);
    url.searchParams.set('position_id', String(positionId));
    alert(`SFP port ${portNo > 0 ? portNo : ''} (${portType}) does not have an IDF port record yet. Opening device view so you can regenerate/save ports first.`);
    window.location.href = url.toString();
}

function idfExportExcel() {
    if (typeof XLSX === 'undefined') { alert('XLSX library not loaded.'); return; }
    const table = document.getElementById('idfExportTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: 'IDF'});
    XLSX.writeFile(wb, `idf-<?php echo (int)$idf_id; ?>.xlsx`);
}

async function idfExportImage() {
    const node = document.getElementById('idfCaptureRoot');
    const canvas = await html2canvas(node, {scale: 2, useCORS: true});
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `idf-<?php echo (int)$idf_id; ?>.png`;
    a.click();
}

async function idfExportPdf() {
    const node = document.getElementById('idfCaptureRoot');
    const canvas = await html2canvas(node, {scale: 2, useCORS: true});
    const imgData = canvas.toDataURL('image/png');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({orientation: 'portrait', unit: 'pt', format: 'a4'});
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();

    const imgW = pageW;
    const imgH = (canvas.height * imgW) / canvas.width;

    let y = 0;
    let remaining = imgH;

    while (remaining > 0) {
        pdf.addImage(imgData, 'PNG', 0, y, imgW, imgH);
        remaining -= pageH;
        if (remaining > 0) {
            pdf.addPage();
            y -= pageH;
        }
    }
    pdf.save(`idf-<?php echo (int)$idf_id; ?>.pdf`);
}

(function initSortable() {
    const el = document.getElementById('idfSlots');
    if (!el || typeof Sortable === 'undefined') return;

    new Sortable(el, {
        animation: 160,
        ghostClass: 'idf-sortable-ghost',
        chosenClass: 'idf-sortable-chosen',
        onEnd: async () => {
            try {
                const ordered = Array.from(el.querySelectorAll('.idf-slot')).map((slot, idx) => {
                    const posNo = idx + 1;
                    const posIdBtn = slot.querySelector('a[href*="device.php?position_id="]');
                    const positionId = posIdBtn
                        ? Number(new URL(posIdBtn.getAttribute('href'), window.location.href).searchParams.get('position_id'))
                        : null;
                    return {position_no: posNo, position_id: positionId};
                });

                await apiPost('position_reorder.php', {
                    csrf_token: CSRF,
                    idf_id: <?php echo (int)$idf_id; ?>,
                    order: ordered,
                });

                location.reload();
            } catch (e) {
                alert(e.message || 'Reorder failed');
                location.reload();
            }
        },
    });
})();
</script>
<script src="<?php echo BASE_URL; ?>js/select-add-option.js"></script>
</body>
</html>
