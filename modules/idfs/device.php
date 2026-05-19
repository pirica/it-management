<?php
require_once __DIR__ . '/../../config/config.php';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
require_once __DIR__ . '/port_visualizer_helper.php';
require_once __DIR__ . '/idf_positions_schema.php';
require_once __DIR__ . '/idf_ports_sync.php';
require_once ROOT_PATH . 'includes/idf_device_port_sort_sql.php';
idf_ensure_idf_positions_capacity_columns($conn);

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$position_id = (int)($_GET['position_id'] ?? 0);
$open_edit_port_id = (int)($_GET['open_edit_port_id'] ?? 0);
$open_link_port_id = (int)($_GET['open_link_port_id'] ?? 0);
$open_edit_port_no = (int)($_GET['open_edit_port_no'] ?? 0);
$open_link_port_no = (int)($_GET['open_link_port_no'] ?? 0);
$embed_mode = isset($_GET['embed']) && (string)$_GET['embed'] === '1';
$embed_modal_only = $embed_mode && isset($_GET['embed_modal']) && (string)$_GET['embed_modal'] === '1';

function idf_sanitize_return_to(string $raw): string {
    $value = trim($raw);
    if (isset($_GET['return_to']) && is_array($_GET['return_to'])) {
        $value = trim((string)($_GET['return_to'][0] ?? ''));
    }
    if (strpos($value, '&return_to=') !== false) {
        $value = trim((string)strstr($value, '&return_to=', true));
    }
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\s*javascript:/i', $value)) {
        return '';
    }
    if (strpos($value, '://') !== false) {
        $allowedPrefix = rtrim((string)BASE_URL, '/') . '/modules/idfs/';
        if (stripos($value, $allowedPrefix) !== 0) {
            return '';
        }
        return $value;
    }
    if (preg_match('/^view\.php\?id=\d+(&[a-zA-Z0-9_=%.\-+]*)?$/', $value)) {
        return $value;
    }
    return '';
}

function idf_resolve_return_to(int $returnIdfId, string $rawReturnTo): string {
    if ($returnIdfId > 0) {
        return 'view.php?id=' . $returnIdfId;
    }
    return idf_sanitize_return_to($rawReturnTo);
}

$return_idf_id = (int)($_GET['return_idf'] ?? 0);
$return_to = idf_resolve_return_to($return_idf_id, (string)($_GET['return_to'] ?? ''));

function idf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function idf_normalize_label_display($value): string {
    return idf_normalize_port_label_value($value);
}

$csrf = idf_csrf_token();

$pos = null;
if ($position_id > 0 && $company_id > 0) {
    $hasIdfCodeColumn = false;
    $hasRackNameColumn = false;
    $hasIdfsRackIdColumn = false;
    $hasCompaniesTable = false;
    $hasCompaniesCompanyColumn = false;
    $hasCompaniesNameColumn = false;
    $hasRacksTable = false;
    $hasRacksNameColumn = false;

    $hasIdfCodeColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `idfs` LIKE 'idf_code'");
    if ($hasIdfCodeColumnRes && mysqli_num_rows($hasIdfCodeColumnRes) > 0) {
        $hasIdfCodeColumn = true;
    }
    $hasRackNameColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `idfs` LIKE 'rack_name'");
    if ($hasRackNameColumnRes && mysqli_num_rows($hasRackNameColumnRes) > 0) {
        $hasRackNameColumn = true;
    }
    $hasIdfsRackIdColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `idfs` LIKE 'rack_id'");
    if ($hasIdfsRackIdColumnRes && mysqli_num_rows($hasIdfsRackIdColumnRes) > 0) {
        $hasIdfsRackIdColumn = true;
    }
    $hasCompaniesTableRes = mysqli_query($conn, "SHOW TABLES LIKE 'companies'");
    if ($hasCompaniesTableRes && mysqli_num_rows($hasCompaniesTableRes) > 0) {
        $hasCompaniesTable = true;
    }
    if ($hasCompaniesTable) {
        $hasCompaniesCompanyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `companies` LIKE 'company'");
        if ($hasCompaniesCompanyColumnRes && mysqli_num_rows($hasCompaniesCompanyColumnRes) > 0) {
            $hasCompaniesCompanyColumn = true;
        }
        $hasCompaniesNameColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `companies` LIKE 'name'");
        if ($hasCompaniesNameColumnRes && mysqli_num_rows($hasCompaniesNameColumnRes) > 0) {
            $hasCompaniesNameColumn = true;
        }
    }
    $hasRacksTableRes = mysqli_query($conn, "SHOW TABLES LIKE 'racks'");
    if ($hasRacksTableRes && mysqli_num_rows($hasRacksTableRes) > 0) {
        $hasRacksTable = true;
    }
    if ($hasRacksTable) {
        $hasRacksNameColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `racks` LIKE 'name'");
        if ($hasRacksNameColumnRes && mysqli_num_rows($hasRacksNameColumnRes) > 0) {
            $hasRacksNameColumn = true;
        }
    }

    $idfCodeSelect = $hasIdfCodeColumn ? 'i.idf_code' : "'' AS idf_code";
    if ($hasRacksTable && $hasRacksNameColumn && $hasIdfsRackIdColumn) {
        $rackNameSelect = $hasRackNameColumn
            ? "COALESCE(NULLIF(r_company.name, ''), NULLIF(r_legacy.name, ''), NULLIF(i.rack_name, ''), '') AS rack_name"
            : "COALESCE(NULLIF(r_company.name, ''), NULLIF(r_legacy.name, ''), '') AS rack_name";
    } else {
        $rackNameSelect = $hasRackNameColumn ? "COALESCE(i.rack_name, '') AS rack_name" : "'' AS rack_name";
    }
    if ($hasCompaniesTable && $hasCompaniesCompanyColumn && $hasCompaniesNameColumn) {
        $companyNameSelect = "COALESCE(NULLIF(c.company, ''), NULLIF(c.name, ''), '') AS company_name";
    } elseif ($hasCompaniesTable && $hasCompaniesCompanyColumn) {
        $companyNameSelect = "COALESCE(c.company, '') AS company_name";
    } elseif ($hasCompaniesTable && $hasCompaniesNameColumn) {
        $companyNameSelect = "COALESCE(c.name, '') AS company_name";
    } else {
        $companyNameSelect = "'' AS company_name";
    }
    $companiesJoinSql = $hasCompaniesTable ? 'LEFT JOIN companies c ON c.id = i.company_id' : '';
    $racksJoinSql = ($hasRacksTable && $hasRacksNameColumn && $hasIdfsRackIdColumn)
        ? 'LEFT JOIN racks r_company ON r_company.id = i.rack_id AND r_company.company_id = i.company_id
           LEFT JOIN racks r_legacy ON r_legacy.id = i.rack_id'
        : '';

    $stmt = mysqli_prepare(
        $conn,
        'SELECT p.*, i.name AS idf_name, ' . $idfCodeSelect . ', ' . $rackNameSelect . ', l.name AS location_name, i.id AS idf_id, ' . $companyNameSelect . ', COALESCE(spnl_equipment.name, spnl.name, "Vertical") AS layout_name,
                COALESCE(er.name, "") AS switch_rj45_name,
                COALESCE(e.switch_fiber_ports_number, 0) AS equipment_fiber_ports_number,
                COALESCE(e.switch_fiber_port_label, "") AS equipment_fiber_port_label,
                CASE
                    WHEN UPPER(COALESCE(et.code, "")) = "SWITCH" THEN 1
                    WHEN UPPER(COALESCE(et.name, "")) = "SWITCH" THEN 1
                    WHEN LOWER(COALESCE(dt.idfdevicetype_name, "")) LIKE "switch" THEN 1
                    ELSE 0
                 END AS equipment_is_switch
         FROM idf_positions p
         JOIN idfs i ON i.id = p.idf_id JOIN it_locations l ON l.id = i.location_id
         ' . $companiesJoinSql . '
         ' . $racksJoinSql . '
         LEFT JOIN equipment e ON e.id = p.equipment_id
         LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id AND er.company_id = p.company_id
         LEFT JOIN idf_device_type dt ON dt.id = p.device_type AND dt.company_id = p.company_id
         LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = p.switch_port_numbering_layout_id
         LEFT JOIN switch_port_numbering_layout spnl_equipment ON spnl_equipment.id = e.switch_port_numbering_layout_id
         WHERE p.id = ? AND i.company_id = ?
         LIMIT 1'
    );
    $res = false;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    }
    $pos = $res ? mysqli_fetch_assoc($res) : null;
}

if (!$pos) {
    $_SESSION['crud_error'] = 'Device not found.';
    header('Location: index.php');
    exit;
}

$ports = [];
$rj45PortNumbers = [];
$sfpPortNumbers = [];

$hasSwitchPortsLabelColumn = false;
$hasSwitchPortsToPatchPortColumn = false;
$hasSwitchPortsPatchPortColumn = false;
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
$switchPortsPatchPortColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_ports` LIKE 'patch_port'");
if ($switchPortsPatchPortColumnRes && mysqli_num_rows($switchPortsPatchPortColumnRes) > 0) {
    $hasSwitchPortsPatchPortColumn = true;
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
    $switchPortsLiveLabelSelect = "NULLIF(NULLIF(pr_live.to_patch_port, ''), '0')";
} elseif ($hasSwitchPortsLabelColumn) {
    $switchPortsLiveLabelSelect = "NULLIF(NULLIF(pr_live.label, ''), '0')";
} elseif ($hasSwitchPortsPatchPortColumn) {
    $switchPortsLiveLabelSelect = "NULLIF(NULLIF(pr_live.patch_port, ''), '0')";
}
$switchPortsLiveCommentsSelect = $hasSwitchPortsCommentsColumn
    ? "NULLIF(NULLIF(pr_live.comments, ''), '0')"
    : "''";
$switchPortsLiveHostnameSelect = $hasSwitchPortsHostnameColumn
    ? "NULLIF(pr_live.hostname, '')"
    : "''";
$switchPortsLinkedLabelSelect = "''";
if ($hasSwitchPortsToPatchPortColumn) {
    $switchPortsLinkedLabelSelect = "NULLIF(NULLIF(sp_link.to_patch_port, ''), '0')";
} elseif ($hasSwitchPortsLabelColumn) {
    $switchPortsLinkedLabelSelect = "NULLIF(NULLIF(sp_link.label, ''), '0')";
} elseif ($hasSwitchPortsPatchPortColumn) {
    $switchPortsLinkedLabelSelect = "NULLIF(NULLIF(sp_link.patch_port, ''), '0')";
}
$switchPortsLinkedCommentsSelect = $hasSwitchPortsCommentsColumn
    ? "NULLIF(sp_link.comments, '')"
    : "''";
$hasRj45SpeedTable = false;
$hasRj45SpeedTableRes = mysqli_query($conn, "SHOW TABLES LIKE 'rj45_speed'");
if ($hasRj45SpeedTableRes && mysqli_num_rows($hasRj45SpeedTableRes) > 0) {
    $hasRj45SpeedTable = true;
}
$hasIdfPortsRj45SpeedColumn = false;
$idfPortsRj45SpeedColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_ports` LIKE 'rj45_speed_id'");
if ($idfPortsRj45SpeedColumnRes && mysqli_num_rows($idfPortsRj45SpeedColumnRes) > 0) {
    $hasIdfPortsRj45SpeedColumn = true;
}
$rj45SpeedIdExpr = $hasIdfPortsRj45SpeedColumn ? 'pr.rj45_speed_id' : 'NULL';
$normalizedPortTypeExpr = "UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, spt_any.type, 'RJ45')), ' ', ''), '+', 'PLUS'))";
$speedLabelExpr = $hasRj45SpeedTable
    ? "CASE
          WHEN {$normalizedPortTypeExpr} = 'RJ45' THEN COALESCE(rs.cable_type, '')
          ELSE COALESCE(ef.name, '')
       END"
    : "COALESCE(ef.name, '')";
$speedValueIdExpr = $hasRj45SpeedTable
    ? "CASE
          WHEN {$normalizedPortTypeExpr} LIKE 'SFP%' THEN COALESCE(NULLIF(pr_live.fiber_port_id, 0), NULLIF(sp_link.fiber_port_id, 0), NULLIF(l.equipment_fiber_port_id, 0), NULLIF(pr.speed_id, 0), 0)
          ELSE COALESCE(NULLIF(pr_live.rj45_speed_id, 0), NULLIF(sp_link.rj45_speed_id, 0), NULLIF(l.equipment_rj45_speed_id, 0), NULLIF({$rj45SpeedIdExpr}, 0), NULLIF(pr.speed_id, 0), 0)
       END"
    : "COALESCE(NULLIF(pr_live.fiber_port_id, 0), NULLIF(sp_link.fiber_port_id, 0), NULLIF(l.equipment_fiber_port_id, 0), NULLIF(pr.speed_id, 0), 0)";
$rj45SpeedJoinSql = $hasRj45SpeedTable
    ? "LEFT JOIN rj45_speed rs
       ON rs.id = COALESCE({$rj45SpeedIdExpr}, pr.speed_id)
      AND rs.company_id = pr.company_id"
    : "";

$portSortField = (string)($_GET['sort'] ?? 'port_type');
$portSortDir = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$vlanSortExpr = "CASE
    WHEN v_live.id IS NOT NULL THEN
        CASE
            WHEN TRIM(COALESCE(v_live.vlan_name, '')) = '' THEN COALESCE(v_live.vlan_number, '')
            WHEN TRIM(COALESCE(v_live.vlan_number, '')) = '' THEN v_live.vlan_name
            ELSE CONCAT(v_live.vlan_number, ' - ', v_live.vlan_name)
        END
    WHEN v.id IS NOT NULL THEN
        CASE
            WHEN TRIM(COALESCE(v.vlan_name, '')) = '' THEN COALESCE(v.vlan_number, '')
            WHEN TRIM(COALESCE(v.vlan_number, '')) = '' THEN v.vlan_name
            ELSE CONCAT(v.vlan_number, ' - ', v.vlan_name)
        END
    WHEN v_link_sp.id IS NOT NULL THEN
        CASE
            WHEN TRIM(COALESCE(v_link_sp.vlan_name, '')) = '' THEN COALESCE(v_link_sp.vlan_number, '')
            WHEN TRIM(COALESCE(v_link_sp.vlan_number, '')) = '' THEN v_link_sp.vlan_name
            ELSE CONCAT(v_link_sp.vlan_number, ' - ', v_link_sp.vlan_name)
        END
    WHEN v_link.id IS NOT NULL THEN
        CASE
            WHEN TRIM(COALESCE(v_link.vlan_name, '')) = '' THEN COALESCE(v_link.vlan_number, '')
            WHEN TRIM(COALESCE(v_link.vlan_number, '')) = '' THEN v_link.vlan_name
            ELSE CONCAT(v_link.vlan_number, ' - ', v_link.vlan_name)
        END
    ELSE ''
END";
$labelSortExpr = "COALESCE(NULLIF(NULLIF(pr.label, ''), '0'), NULLIF(NULLIF(l.equipment_label, ''), '0'), '')";
$notesSortExpr = "COALESCE({$switchPortsLiveCommentsSelect}, NULLIF(NULLIF(pr.notes, ''), '0'), NULLIF(l.notes, ''), {$switchPortsLinkedCommentsSelect}, NULLIF(NULLIF(l.equipment_comments, ''), '0'))";
$normalizedPortTypeExpr = "LOWER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, spt_any.type, 'RJ45')), ' ', ''), '+', 'plus'))";
$speedLabelExpr = $hasRj45SpeedTable
    ? "CASE
    WHEN {$normalizedPortTypeExpr} LIKE 'sfp%' THEN COALESCE(ef.name, '')
    ELSE ''
END"
    : "COALESCE(ef.name, '')";
$rj45SpeedLabelExpr = $hasRj45SpeedTable
    ? "CASE
    WHEN {$normalizedPortTypeExpr} = 'rj45' THEN COALESCE(rs.cable_type, '')
    ELSE ''
END"
    : "''";
$fiberPatchLabelExpr = 'COALESCE(efp.name, \'\')';
$fiberRackLabelExpr = 'COALESCE(efr.name, \'\')';

$idfPortTypeLabelSql = itm_idf_port_type_label_sql();
$portFiberFamilyRankExpr = itm_idf_port_fiber_family_rank_sql();

$portSortMap = [
    'port_no' => 'pr.port_no',
    'port_type' => $idfPortTypeLabelSql,
    'label' => $labelSortExpr,
    'status' => "COALESCE(NULLIF(TRIM(ss_live.status), ''), NULLIF(TRIM(ss_link.status), ''), NULLIF(TRIM(ss.status), ''), 'Unknown')",
    'connected_to' => 'pr.connected_to',
    'vlan' => $vlanSortExpr,
    'speed' => $speedLabelExpr,
    'rj45_speed' => $rj45SpeedLabelExpr,
    'poe' => 'COALESCE(ep.name, "")',
    'fiber_patch' => $fiberPatchLabelExpr,
    'fiber_rack' => $fiberRackLabelExpr,
    'notes' => $notesSortExpr,
    'link' => 'l.id'
];
if (!isset($portSortMap[$portSortField])) {
    $portSortField = 'port_type';
}
// Fiber family first for every chosen sort column so RJ45/SFP duplex rows (same port_no) never interleave (e.g. 1RJ45 / 1SFP / 2RJ45).
$portOrderSql = itm_idf_ports_device_list_order_sql(
    $portFiberFamilyRankExpr,
    $portSortMap[$portSortField],
    $portSortDir
);

$stmtPorts = mysqli_prepare(
    $conn,
    "SELECT
       pr.*,
       COALESCE(spt.type, spt_any.type, 'RJ45') AS port_type_label,
       COALESCE(pr_live.status_id, sp_link.status_id, pr.status_id, l.equipment_status_id) AS effective_status_id,
       COALESCE(
           NULLIF(TRIM(ss_live.status), ''),
           NULLIF(TRIM(ss_link.status), ''),
           NULLIF(TRIM(ss.status), ''),
           NULLIF(TRIM(ss_link_meta.status), ''),
           'Unknown'
       ) AS status_label,
       COALESCE(cc_pr_live_direct.hex_color, cc_live.hex_color, cc_sp_link_direct.hex_color, cc_ss_link.hex_color, cc_ss.hex_color, cc_ss_link_meta.hex_color, cc_l.hex_color, pr.hex_color, '#808080') AS status_color,
       COALESCE(NULLIF(NULLIF(pr.label, ''), '0'), NULLIF(NULLIF(l.equipment_label, ''), '0'), '') AS label,
       COALESCE({$switchPortsLiveHostnameSelect}, pr.connected_to) AS connected_to,
       COALESCE({$switchPortsLiveCommentsSelect}, NULLIF(pr.notes, ''), NULLIF(l.notes, ''), {$switchPortsLinkedCommentsSelect}, NULLIF(l.equipment_comments, ''), '') AS notes,
       p_local.position_no AS local_position_no,
       p_local.device_name AS local_device_name,
       p_local.equipment_id AS local_equipment_id,
       i_local.name AS local_idf_name,
       COALESCE(dt_local.idfdevicetype_name, et_local.name, '') AS local_device_type_label,
       COALESCE(NULLIF(pr_live.vlan_id, 0), NULLIF(pr.vlan_id, 0), NULLIF(sp_link.vlan_id, 0), NULLIF(l.equipment_vlan_id, 0), 0) AS effective_vlan_id,
       COALESCE(NULLIF(pr_live.rj45_speed_id, 0), NULLIF(sp_link.rj45_speed_id, 0), NULLIF(l.equipment_rj45_speed_id, 0), NULLIF({$rj45SpeedIdExpr}, 0), NULLIF(pr.speed_id, 0), 0) AS effective_rj45_speed_id,
       COALESCE(NULLIF(pr_live.fiber_port_id, 0), NULLIF(sp_link.fiber_port_id, 0), NULLIF(l.equipment_fiber_port_id, 0), NULLIF(pr.speed_id, 0), 0) AS effective_fiber_port_id,
       COALESCE(NULLIF(pr.fiber_patch_id, 0), NULLIF(pr_live.fiber_patch_id, 0), NULLIF(sp_link.fiber_patch_id, 0), NULLIF(l.equipment_fiber_patch_id, 0), 0) AS effective_fiber_patch_id,
       COALESCE(NULLIF(pr.fiber_rack_id, 0), NULLIF(pr_live.fiber_rack_id, 0), NULLIF(sp_link.fiber_rack_id, 0), NULLIF(l.equipment_fiber_rack_id, 0), 0) AS effective_fiber_rack_id,
       COALESCE(
           NULLIF(pr.to_idf_id, 0),
           NULLIF(pr_live.to_idf_id, 0),
           NULLIF(sp_link.to_idf_id, 0),
           NULLIF(l.equipment_to_idf_id, 0),
           NULLIF(pr_live.idf_id, 0),
           NULLIF(sp_link.idf_id, 0),
           0
       ) AS effective_to_idf_id,
       COALESCE(
           NULLIF(pr.to_rack_id, 0),
           NULLIF(pr_live.to_rack_id, 0),
           NULLIF(sp_link.to_rack_id, 0),
           NULLIF(l.equipment_to_rack_id, 0),
           NULLIF(pr_live.rack_id, 0),
           NULLIF(sp_link.rack_id, 0),
           0
       ) AS effective_to_rack_id,
       COALESCE(
           NULLIF(pr.to_location_id, 0),
           NULLIF(pr_live.to_location_id, 0),
           NULLIF(sp_link.to_location_id, 0),
           NULLIF(l.equipment_to_location_id, 0),
           NULLIF(pr_live.location_id, 0),
           NULLIF(sp_link.location_id, 0),
           0
       ) AS effective_to_location_id,
       COALESCE(NULLIF(pr_live.poe_id, 0), NULLIF(pr.poe_id, 0), 0) AS effective_poe_id,
       CASE
            WHEN v_live.id IS NOT NULL THEN
              CASE
                WHEN TRIM(COALESCE(v_live.vlan_name, '')) = '' THEN COALESCE(v_live.vlan_number, '')
                WHEN TRIM(COALESCE(v_live.vlan_number, '')) = '' THEN v_live.vlan_name
                ELSE CONCAT(v_live.vlan_number, ' - ', v_live.vlan_name)
              END
            WHEN v.id IS NOT NULL THEN
              CASE
                WHEN TRIM(COALESCE(v.vlan_name, '')) = '' THEN COALESCE(v.vlan_number, '')
                WHEN TRIM(COALESCE(v.vlan_number, '')) = '' THEN v.vlan_name
                ELSE CONCAT(v.vlan_number, ' - ', v.vlan_name)
              END
            WHEN v_link_sp.id IS NOT NULL THEN
              CASE
                WHEN TRIM(COALESCE(v_link_sp.vlan_name, '')) = '' THEN COALESCE(v_link_sp.vlan_number, '')
                WHEN TRIM(COALESCE(v_link_sp.vlan_number, '')) = '' THEN v_link_sp.vlan_name
                ELSE CONCAT(v_link_sp.vlan_number, ' - ', v_link_sp.vlan_name)
              END
            WHEN v_link.id IS NOT NULL THEN
              CASE
                WHEN TRIM(COALESCE(v_link.vlan_name, '')) = '' THEN COALESCE(v_link.vlan_number, '')
                WHEN TRIM(COALESCE(v_link.vlan_number, '')) = '' THEN v_link.vlan_name
                ELSE CONCAT(v_link.vlan_number, ' - ', v_link.vlan_name)
             END
           ELSE ''
         END AS vlan_label,
       {$speedLabelExpr} AS speed_label,
       {$rj45SpeedLabelExpr} AS rj45_speed_label,
       {$speedValueIdExpr} AS speed_value_id,
       CASE
         WHEN ep.id IS NULL THEN ''
         WHEN TRIM(COALESCE(ep.watts, '')) <> '' THEN CONCAT(ep.name, ' - ', ep.watts)
         ELSE COALESCE(ep.name, '')
       END AS poe_label,
       {$fiberPatchLabelExpr} AS fiber_patch_label,
       {$fiberRackLabelExpr} AS fiber_rack_label,
       pr_live.id AS switch_port_live_id,
       l.id AS link_id,
       l.cable_color_id,
       COALESCE(NULLIF(cc_pr_live_direct.color_name, ''), NULLIF(cc_live.color_name, ''), NULLIF(cc_sp_link_direct.color_name, ''), NULLIF(cc_l.color_name, ''), NULLIF(pr.cable_color, ''), cc_ss_link_meta.color_name, cc_l.hex_color, 'Gray') AS cable_color_name,
       COALESCE(NULLIF(cc_l.hex_color, ''), NULLIF(l.cable_color_hex, ''), NULLIF(pr.hex_color, ''), NULLIF(cc_pr_live_direct.hex_color, ''), NULLIF(cc_live.hex_color, ''), NULLIF(cc_sp_link_direct.hex_color, ''), NULLIF(cc_ss_link_meta.hex_color, ''), '#808080') AS cable_hex_color,
       l.cable_label,
       l.notes AS link_notes,
       COALESCE(NULLIF(TRIM(le.name), ''), NULLIF(TRIM(le.hostname), ''), NULLIF(TRIM(sp_link.hostname), ''), '') AS equipment_hostname,
       COALESCE(CAST(l.equipment_port AS CHAR), CAST(sp_link.port_number AS CHAR), '') AS equipment_port,
       CASE
         WHEN l.port_id_a = pr.id THEN l.port_id_b
         WHEN l.port_id_b = pr.id THEN l.port_id_a
         ELSE NULL
       END AS other_port_id,
       pr_remote.port_no AS remote_port_no,
       pr_remote.status_id AS remote_status_id,
       COALESCE(
         NULLIF(ss_remote.status, ''),
         NULLIF(ss_link.status, ''),
         NULLIF(ss_link_meta.status, ''),
         NULLIF(ss_live.status, ''),
         NULLIF(ss.status, ''),
         'Unknown'
       ) AS remote_status_label,
       p_remote.position_no AS remote_position_no,
       p_remote.device_name AS remote_device_name,
       p_remote.equipment_id AS remote_equipment_id,
       COALESCE(dt_remote.idfdevicetype_name, et_remote.name, '') AS remote_device_type_label,
       l.equipment_id AS linked_equipment_id,
       CASE
         WHEN UPPER(COALESCE(let.code, '')) = 'SWITCH' THEN 1
         WHEN UPPER(COALESCE(let.name, '')) = 'SWITCH' THEN 1
         ELSE 0
       END AS linked_equipment_is_switch
      FROM idf_ports pr
      JOIN idf_positions p_local
        ON p_local.company_id = pr.company_id
       AND p_local.idf_id = ?
       AND (
            p_local.id = pr.position_id
            OR p_local.position_no = pr.position_id
       )
     JOIN idfs i_local
       ON i_local.id = p_local.idf_id
     LEFT JOIN equipment e_local
       ON e_local.id = p_local.equipment_id
     LEFT JOIN equipment_types et_local
       ON et_local.id = e_local.equipment_type_id
     LEFT JOIN idf_device_type dt_local
       ON dt_local.id = p_local.device_type
      AND dt_local.company_id = p_local.company_id
      LEFT JOIN switch_port_types spt
        ON spt.id = pr.port_type
       AND spt.company_id = pr.company_id
      LEFT JOIN switch_port_types spt_any
        ON spt_any.id = pr.port_type
      LEFT JOIN switch_ports pr_live
        ON pr_live.company_id = pr.company_id
       AND " . itm_idf_position_numeric_equipment_match_sql('p_local', 'pr_live.equipment_id') . "
       AND pr_live.port_number = pr.port_no
       AND (
            CONVERT(pr_live.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(COALESCE(spt.type, spt_any.type, 'RJ45') USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(pr_live.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(CAST(spt.id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR (
                pr_live.port_type REGEXP '^[0-9]+$'
                AND CAST(pr_live.port_type AS UNSIGNED) = spt.id
            )
            OR CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(pr_live.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, spt_any.type, 'RJ45')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
       )
      LEFT JOIN switch_status ss
        ON ss.id = pr.status_id
       AND ss.company_id = pr.company_id
      LEFT JOIN switch_status ss_live
        ON ss_live.id = pr_live.status_id
      AND ss_live.company_id = pr_live.company_id
      LEFT JOIN cable_colors cc_ss
        ON cc_ss.id = ss.color_id
       AND cc_ss.company_id = ss.company_id
      LEFT JOIN cable_colors cc_live
        ON cc_live.id = ss_live.color_id
       AND cc_live.company_id = ss_live.company_id
      LEFT JOIN cable_colors cc_pr_live_direct
        ON cc_pr_live_direct.id = pr_live.color_id
       AND cc_pr_live_direct.company_id = pr_live.company_id
      LEFT JOIN vlans v
        ON v.id = pr.vlan_id
       AND v.company_id = pr.company_id
      LEFT JOIN vlans v_live
        ON v_live.id = pr_live.vlan_id
       AND v_live.company_id = pr_live.company_id
     LEFT JOIN equipment_fiber ef
       ON ef.id = pr.speed_id
      AND ef.company_id = pr.company_id
     {$rj45SpeedJoinSql}
     LEFT JOIN equipment_poe ep
       ON ep.id = pr.poe_id
      AND ep.company_id = pr.company_id
      LEFT JOIN idf_links l ON l.id = (
          SELECT l2.id
          FROM idf_links l2
          WHERE (l2.port_id_a = pr.id OR l2.port_id_b = pr.id)
            AND l2.company_id = pr.company_id
          ORDER BY
            CASE WHEN COALESCE(l2.equipment_fiber_patch_id, 0) > 0 THEN 0 ELSE 1 END,
            CASE WHEN COALESCE(l2.equipment_fiber_rack_id, 0) > 0 THEN 0 ELSE 1 END,
            CASE WHEN COALESCE(l2.equipment_fiber_port_id, 0) > 0 THEN 0 ELSE 1 END,
            l2.id ASC
          LIMIT 1
      )
      LEFT JOIN switch_ports sp_link
        ON sp_link.company_id = pr.company_id
       AND CONVERT(CAST(sp_link.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           = CONVERT(CAST(l.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
       AND CONVERT(CAST(sp_link.port_number AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           = CONVERT(CAST(l.equipment_port AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
       AND CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(sp_link.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(l.equipment_port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
      LEFT JOIN switch_status ss_link
        ON ss_link.id = sp_link.status_id
       AND ss_link.company_id = sp_link.company_id
      LEFT JOIN cable_colors cc_ss_link
        ON cc_ss_link.id = ss_link.color_id
       AND cc_ss_link.company_id = ss_link.company_id
      LEFT JOIN cable_colors cc_sp_link_direct
        ON cc_sp_link_direct.id = sp_link.color_id
       AND cc_sp_link_direct.company_id = sp_link.company_id
      LEFT JOIN vlans v_link_sp
        ON v_link_sp.id = sp_link.vlan_id
       AND v_link_sp.company_id = sp_link.company_id
      LEFT JOIN vlans v_link
        ON v_link.id = l.equipment_vlan_id
       AND v_link.company_id = l.company_id
      LEFT JOIN equipment_fiber_patch efp
        ON efp.company_id = pr.company_id
       AND efp.id = COALESCE(NULLIF(pr.fiber_patch_id, 0), NULLIF(pr_live.fiber_patch_id, 0), NULLIF(sp_link.fiber_patch_id, 0), NULLIF(l.equipment_fiber_patch_id, 0))
      LEFT JOIN equipment_fiber_rack efr
        ON efr.company_id = pr.company_id
       AND efr.id = COALESCE(NULLIF(pr.fiber_rack_id, 0), NULLIF(pr_live.fiber_rack_id, 0), NULLIF(sp_link.fiber_rack_id, 0), NULLIF(l.equipment_fiber_rack_id, 0))
      LEFT JOIN idf_ports pr_remote
        ON pr_remote.id = CASE
            WHEN l.port_id_a = pr.id THEN l.port_id_b
            WHEN l.port_id_b = pr.id THEN l.port_id_a
           ELSE NULL
       END
      LEFT JOIN switch_status ss_remote
        ON ss_remote.id = pr_remote.status_id
       AND ss_remote.company_id = pr_remote.company_id
      LEFT JOIN switch_status ss_link_meta
        ON ss_link_meta.id = l.equipment_status_id
       AND ss_link_meta.company_id = l.company_id
      LEFT JOIN cable_colors cc_ss_link_meta
        ON cc_ss_link_meta.id = ss_link_meta.color_id
       AND cc_ss_link_meta.company_id = ss_link_meta.company_id
      LEFT JOIN idf_positions p_remote
        ON p_remote.company_id = pr_remote.company_id
       AND (
            p_remote.id = pr_remote.position_id
            OR (
                p_remote.position_no = pr_remote.position_id
                AND NOT EXISTS (
                    SELECT 1
                    FROM idf_positions p_actual
                    WHERE p_actual.company_id = pr_remote.company_id
                      AND p_actual.id = pr_remote.position_id
                    LIMIT 1
                )
            )
       )
     LEFT JOIN equipment e_remote
       ON e_remote.id = p_remote.equipment_id
     LEFT JOIN equipment_types et_remote
       ON et_remote.id = e_remote.equipment_type_id
     LEFT JOIN idf_device_type dt_remote
       ON dt_remote.id = p_remote.device_type
      AND dt_remote.company_id = p_remote.company_id
     LEFT JOIN cable_colors cc_l
       ON cc_l.id = l.cable_color_id
      AND cc_l.company_id = l.company_id
     LEFT JOIN equipment le ON le.id = l.equipment_id
     LEFT JOIN equipment_types let ON let.id = le.equipment_type_id
      WHERE p_local.id=?
      ORDER BY " . $portOrderSql
);

$idfDeviceHydratePortRow = static function (array $row): array {
    $row['persisted_vlan_id'] = (int)($row['vlan_id'] ?? 0);
    $row['persisted_notes'] = idf_normalize_port_notes_value($row['notes'] ?? '');
    $row['persisted_connected_to'] = trim((string)($row['connected_to'] ?? ''));
    $row['persisted_label'] = idf_normalize_port_label_value($row['label'] ?? '');
    $row['persisted_rj45_speed_id'] = (int)($row['rj45_speed_id'] ?? 0);
    $row['persisted_fiber_port_id'] = (int)($row['speed_id'] ?? 0);
    $row['persisted_fiber_patch_id'] = (int)($row['fiber_patch_id'] ?? 0);
    $row['persisted_fiber_rack_id'] = (int)($row['fiber_rack_id'] ?? 0);

    if (isset($row['effective_status_id'])) {
        $row['status_id'] = (int)$row['effective_status_id'];
    }
    if (isset($row['effective_vlan_id'])) {
        $row['vlan_id'] = (int)$row['effective_vlan_id'];
    }
    if (isset($row['effective_poe_id'])) {
        $row['poe_id'] = (int)$row['effective_poe_id'];
    }
    if (array_key_exists('label', $row)) {
        $row['label'] = idf_normalize_port_label_value($row['label'] ?? '');
    }
    $portTypeRaw = strtolower(trim((string)($row['port_type_label'] ?? '')));
    if (strpos($portTypeRaw, 'sfp') !== false) {
        $row['speed_value_id'] = (int)($row['effective_fiber_port_id'] ?? 0);
    } else {
        $row['speed_value_id'] = (int)($row['effective_rj45_speed_id'] ?? 0);
    }
    return $row;
};

$idfDeviceLoadPorts = static function () use (&$ports, $stmtPorts, $pos, $position_id, $idfDeviceHydratePortRow): void {
    $ports = [];
    if (!$stmtPorts) {
        return;
    }
    $idfIdForPorts = (int)($pos['idf_id'] ?? 0);
    mysqli_stmt_reset($stmtPorts);
    mysqli_stmt_bind_param($stmtPorts, 'ii', $idfIdForPorts, $position_id);
    mysqli_stmt_execute($stmtPorts);
    $resPorts = mysqli_stmt_get_result($stmtPorts);
    while ($resPorts && ($row = mysqli_fetch_assoc($resPorts))) {
        $ports[] = $idfDeviceHydratePortRow($row);
    }
    if ($resPorts) {
        mysqli_free_result($resPorts);
    }
};

$positionLinkedEquipmentId = idf_resolve_position_equipment_id(
    $conn,
    $company_id,
    $position_id,
    (int)($pos['position_no'] ?? 0),
    (int)($pos['idf_id'] ?? 0),
    idf_parse_linked_equipment_id($pos['equipment_id'] ?? '')
);

if ($position_id > 0) {
    // Why: Materialize missing idf_ports from equipment + switch capacity on every GET so the Ports table fills without forcing Regenerate — see idf_collect_port_slots_for_position().
    idf_ensure_ports_for_position($conn, $company_id, $position_id);
}

if ($stmtPorts) {
    $idfDeviceLoadPorts();
    mysqli_stmt_close($stmtPorts);
}

if (empty($ports) && $position_id > 0) {
    $positionNoForPorts = (int)($pos['position_no'] ?? 0);
    $simplePorts = idf_fetch_position_ports_simple($conn, $company_id, $position_id, $positionNoForPorts);
    foreach ($simplePorts as $simplePortRow) {
        $ports[] = $idfDeviceHydratePortRow($simplePortRow);
    }
}

if ($positionLinkedEquipmentId <= 0 && !empty($ports)) {
    $inferredEquipmentId = idf_infer_equipment_id_after_ports_load($conn, $company_id, $ports, $pos);
    if ($inferredEquipmentId > 0) {
        $positionLinkedEquipmentId = $inferredEquipmentId;
    }
}

if ($positionLinkedEquipmentId > 0 && !empty($ports)) {
    $equipmentDefaultPoeId = 0;
    $stmtEquipmentPoe = mysqli_prepare(
        $conn,
        'SELECT COALESCE(switch_poe_id, 0) AS switch_poe_id
         FROM equipment
         WHERE company_id = ? AND id = ?
         LIMIT 1'
    );
    if ($stmtEquipmentPoe) {
        mysqli_stmt_bind_param($stmtEquipmentPoe, 'ii', $company_id, $positionLinkedEquipmentId);
        mysqli_stmt_execute($stmtEquipmentPoe);
        $resEquipmentPoe = mysqli_stmt_get_result($stmtEquipmentPoe);
        $equipmentPoeRow = $resEquipmentPoe ? mysqli_fetch_assoc($resEquipmentPoe) : null;
        if ($equipmentPoeRow) {
            $equipmentDefaultPoeId = (int)($equipmentPoeRow['switch_poe_id'] ?? 0);
        }
        mysqli_stmt_close($stmtEquipmentPoe);
    }
    idf_attach_switch_port_ids_to_ports(
        $conn,
        $company_id,
        $positionLinkedEquipmentId,
        $ports,
        $equipmentDefaultPoeId
    );
    foreach ($ports as &$portRowAfterSwitchSync) {
        $portRowAfterSwitchSync = $idfDeviceHydratePortRow($portRowAfterSwitchSync);
    }
    unset($portRowAfterSwitchSync);
}

// Re-sort in PHP so list order always matches shared fiber-first rules (covers MySQL tie/order drift on large joins).
if (!empty($ports)) {
    usort(
        $ports,
        static function (array $a, array $b) use ($portSortField, $portSortDir): int {
            return itm_idf_ports_device_list_compare_rows($a, $b, $portSortField, $portSortDir);
        }
    );
}

if (!empty($ports)) {
    idf_normalize_port_visualizer_colors($ports);
}

if (!empty($ports)) {
    idf_enrich_ports_link_fields_for_device($conn, $company_id, $ports);
}

if (!empty($ports)) {
    foreach ($ports as &$portRowIsolateDisplay) {
        idf_isolate_unlinked_port_display_fields($portRowIsolateDisplay);
    }
    unset($portRowIsolateDisplay);
}

foreach ($ports as $portMeta) {
    $portNo = (int)($portMeta['port_no'] ?? 0);
    if ($portNo <= 0) {
        continue;
    }
    $typeRaw = strtolower(trim((string)($portMeta['port_type_label'] ?? ($portMeta['port_type'] ?? ''))));
    if (strpos($typeRaw, 'sfp') !== false) {
        $sfpPortNumbers[] = $portNo;
    } else {
        $rj45PortNumbers[] = $portNo;
    }
}

$rj45PortNumbers = array_values(array_unique($rj45PortNumbers));
sort($rj45PortNumbers);
$sfpPortNumbers = array_values(array_unique($sfpPortNumbers));
sort($sfpPortNumbers);

$fiberDeclaredCapDevice = (int)($pos['sfp_count'] ?? 0);
if ($fiberDeclaredCapDevice <= 0) {
    $fiberDeclaredCapDevice = (int)($pos['equipment_fiber_ports_number'] ?? 0);
}
$fiberAuthoritativeSwitchList = false;
if ($positionLinkedEquipmentId > 0) {
    $switchFiberNosFromEquipment = idf_switch_fiber_port_numbers_for_equipment($conn, $company_id, $positionLinkedEquipmentId);
    $fiberAuthoritativeSwitchList = !empty($switchFiberNosFromEquipment);
    if ($fiberAuthoritativeSwitchList) {
        // Why: Prefer live switch numbering (e.g. SFP 1-4 beside RJ45 22+) over idf placeholders that duplicated RJ45-tail slots.
        $capSlice = ($fiberDeclaredCapDevice > 0 && $fiberDeclaredCapDevice < count($switchFiberNosFromEquipment))
            ? $fiberDeclaredCapDevice
            : count($switchFiberNosFromEquipment);
        $sfpPortNumbers = array_slice($switchFiberNosFromEquipment, 0, (int)$capSlice);
        sort($sfpPortNumbers);
    }
}

if (empty($rj45PortNumbers)) {
    $rj45PortLabel = (string)($pos['switch_rj45_name'] ?? '');
    $rj45FallbackCount = 0;
    if ($rj45PortLabel !== '' && preg_match('/(\d+)/', $rj45PortLabel, $rj45Matches)) {
        $rj45FallbackCount = (int)$rj45Matches[1];
    } elseif ((int)($pos['rj45_count'] ?? $pos['port_count'] ?? 0) > 0) {
        $rj45FallbackCount = (int)($pos['rj45_count'] ?? $pos['port_count'] ?? 0);
    }
    if ($rj45FallbackCount > 0) {
        $rj45PortNumbers = range(1, $rj45FallbackCount);
    }
}

if (empty($sfpPortNumbers)) {
    $fiberFallbackCount = (int)($pos['sfp_count'] ?? 0);
    if ($fiberFallbackCount <= 0) {
        $fiberFallbackCount = (int)($pos['equipment_fiber_ports_number'] ?? 0);
    }
    if ($fiberFallbackCount > 0) {
        $sfpPortNumbers = range(1, $fiberFallbackCount);
    }
}

if ($fiberDeclaredCapDevice > 0 && count($sfpPortNumbers) > $fiberDeclaredCapDevice) {
    $sfpPortNumbers = array_slice($sfpPortNumbers, 0, $fiberDeclaredCapDevice);
}

// Why: Linked equipment with live typed SFP switch rows should mirror switch_ports rows (hostname-backed idf merges). Otherwise keep capped idf footprints for unlinked / switch-not-ready positions.
if (!empty($ports)) {
    $allowedSfpNosFlip = [];
    foreach ($sfpPortNumbers as $allowNoRaw) {
        $allowNo = (int)$allowNoRaw;
        if ($allowNo > 0) {
            $allowedSfpNosFlip[$allowNo] = true;
        }
    }
    $ports = array_values(array_filter($ports, static function (array $p) use ($positionLinkedEquipmentId, $fiberAuthoritativeSwitchList, $allowedSfpNosFlip): bool {
        if (itm_port_visualizer_type_key($p) !== 'sfp') {
            return true;
        }
        $pn = (int)($p['port_no'] ?? 0);
        if ($pn <= 0) {
            return false;
        }
        if ($positionLinkedEquipmentId > 0 && $fiberAuthoritativeSwitchList) {
            return (int)($p['switch_port_live_id'] ?? 0) > 0;
        }
        if ($allowedSfpNosFlip && !isset($allowedSfpNosFlip[$pn])) {
            return false;
        }

        return true;
    }));
}
if (!empty($ports)) {
    usort(
        $ports,
        static function (array $a, array $b) use ($portSortField, $portSortDir): int {
            return itm_idf_ports_device_list_compare_rows($a, $b, $portSortField, $portSortDir);
        }
    );
}

$otherIds = [];
foreach ($ports as $p) {
    if (!empty($p['other_port_id'])) {
        $otherIds[] = (int)$p['other_port_id'];
    }
}
$otherIds = array_values(array_unique($otherIds));

$otherMap = [];
if ($otherIds) {
    $list = implode(',', array_map('intval', $otherIds));
    $resOther = mysqli_query(
        $conn,
        "SELECT
           pr.id AS port_id,
           pr.port_no,
           p.id AS position_id,
           p.position_no,
           p.device_name,
           p.equipment_id,
           e.serial_number AS equipment_serial_number,
           i.id AS idf_id
         FROM idf_ports pr
         JOIN idf_positions p
           ON p.company_id = pr.company_id
          AND (
               p.id = pr.position_id
               OR (
                   p.position_no = pr.position_id
                   AND NOT EXISTS (
                       SELECT 1
                       FROM idf_positions p_actual
                       WHERE p_actual.company_id = pr.company_id
                         AND p_actual.id = pr.position_id
                       LIMIT 1
                   )
               )
          )
         JOIN idfs i ON i.id=p.idf_id
         LEFT JOIN equipment e ON e.id = p.equipment_id
         WHERE pr.id IN ($list) AND i.company_id=$company_id"
    );
    while ($resOther && ($r = mysqli_fetch_assoc($resOther))) {
        $otherMap[(int)$r['port_id']] = $r;
    }
}

$linkOverview = [];
foreach ($ports as $p) {
    if (empty($p['link_id'])) {
        continue;
    }

    $otherPortId = (int)($p['other_port_id'] ?? 0);
    $remote = $otherPortId > 0 ? ($otherMap[$otherPortId] ?? null) : null;

    $remotePositionNo = (int)($p['remote_position_no'] ?? 0);
    $remoteDeviceName = trim((string)($p['remote_device_name'] ?? ''));
    $remotePortNo = (int)($p['remote_port_no'] ?? 0);

    if ($remote) {
        if ($remotePositionNo <= 0) {
            $remotePositionNo = (int)($remote['position_no'] ?? 0);
        }
        if ($remoteDeviceName === '') {
            $remoteDeviceName = trim((string)($remote['device_name'] ?? ''));
        }
        if ($remotePortNo <= 0) {
            $remotePortNo = (int)($remote['port_no'] ?? 0);
        }
    }

    $linkedEquipmentName = trim((string)($p['equipment_hostname'] ?? ''));
    $linkedEquipmentPort = trim((string)($p['equipment_port'] ?? ''));
    if ($linkedEquipmentName !== '') {
        $positionEquipmentSerial = $remote ? trim((string)($remote['equipment_serial_number'] ?? '')) : '';
        $remoteDeviceName = $linkedEquipmentName !== ''
            ? $linkedEquipmentName
            : ($positionEquipmentSerial !== '' ? $positionEquipmentSerial : $remoteDeviceName);
        if ($linkedEquipmentPort !== '' && ctype_digit($linkedEquipmentPort)) {
            $remotePortNo = (int)$linkedEquipmentPort;
        }
    } elseif ($remote) {
        $positionEquipmentSerial = trim((string)($remote['equipment_serial_number'] ?? ''));
        if ($remoteDeviceName === '' && $positionEquipmentSerial !== '') {
            $remoteDeviceName = $positionEquipmentSerial;
        }
    }

    if ($remoteDeviceName === '' && $remotePositionNo <= 0 && $remotePortNo <= 0) {
        // Why: Equipment-only endpoints may lack idf_positions metadata on the linked side; keep a visible map row anyway.
        $linkedEquipFallback = (int)($p['linked_equipment_id'] ?? 0);
        if ($linkedEquipFallback > 0) {
            $remoteDeviceName = 'Equipment #' . $linkedEquipFallback;
        } else {
            continue;
        }
    }

    $linkNotes = trim((string)($p['link_notes'] ?? ''));
    if ($linkNotes === '') {
        $linkNotes = trim((string)($p['notes'] ?? ''));
    }

    $linkOverview[] = [
        'link_id' => (int)$p['link_id'],
        'local_port_no' => (int)($p['port_no'] ?? 0),
        'local_label' => (string)($p['label'] ?? ''),
        'remote_position_no' => $remotePositionNo,
        'remote_device_name' => $remoteDeviceName,
        'remote_port_no' => $remotePortNo,
        'cable_color_name' => (string)($p['cable_color_name'] ?? ''),
        'cable_hex_color' => (string)($p['cable_hex_color'] ?? ''),
        'cable_label' => (string)($p['cable_label'] ?? ''),
        'link_notes' => $linkNotes,
    ];
}

$destinationPorts = [];
$destinationIdfId = (int)($pos['idf_id'] ?? 0);
if ($destinationIdfId > 0 && $company_id > 0) {
    // Why: Link modal lists ports from every rack position in this IDF; materialize peers (e.g. patch panel SFP) before building DESTINATION_PORTS.
    $stmtPeerPositions = mysqli_prepare(
        $conn,
        "SELECT p.id
         FROM idf_positions p
         INNER JOIN idfs i ON i.id = p.idf_id AND i.company_id = ?
         WHERE p.idf_id = ?"
    );
    if ($stmtPeerPositions) {
        mysqli_stmt_bind_param($stmtPeerPositions, 'ii', $company_id, $destinationIdfId);
        mysqli_stmt_execute($stmtPeerPositions);
        $resPeerPositions = mysqli_stmt_get_result($stmtPeerPositions);
        while ($resPeerPositions && ($peerPositionRow = mysqli_fetch_assoc($resPeerPositions))) {
            $peerPositionId = (int)($peerPositionRow['id'] ?? 0);
            if ($peerPositionId > 0) {
                idf_ensure_ports_for_position($conn, $company_id, $peerPositionId);
            }
        }
        mysqli_stmt_close($stmtPeerPositions);
    }
}

$stmtDestinationPorts = mysqli_prepare(
    $conn,
    "SELECT
        pr.id,
        pr.port_no,
        NULLIF(NULLIF(pr.label, ''), '0') AS label,
        COALESCE(ss.status, 'Unknown') AS status_label,
        COALESCE(NULLIF(NULLIF(pr.cable_color, ''), '0'), NULLIF(cc_status.color_name, ''), 'Gray') AS color_name,
        COALESCE(NULLIF(NULLIF(pr.hex_color, ''), '0'), NULLIF(cc_status.hex_color, ''), '#808080') AS color_hex,
        i.id AS idf_id,
        i.name AS idf_name,
        p.id AS position_id,
        p.position_no,
        p.device_name,
        p.device_type,
        p.equipment_id,
        " . itm_idf_port_type_label_with_switch_sql() . " AS port_type_label,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM idf_links l
                WHERE l.company_id = pr.company_id
                  AND (l.port_id_a = pr.id OR l.port_id_b = pr.id)
            ) THEN 1
            ELSE 0
        END AS is_linked
     FROM idf_ports pr
     JOIN idf_positions p
       ON " . itm_idf_positions_resolve_join_sql('pr', 'p') . "
     JOIN idfs i ON i.id = p.idf_id
     JOIN it_locations l ON l.id = i.location_id
     " . itm_idf_destination_switch_ports_join_sql('pr', 'p') . "
     LEFT JOIN switch_status ss
       ON ss.id = pr.status_id
      AND ss.company_id = pr.company_id
     LEFT JOIN cable_colors cc_status
       ON cc_status.id = ss.color_id
      AND cc_status.company_id = ss.company_id
     LEFT JOIN switch_port_types spt
       ON spt.id = pr.port_type
      AND spt.company_id = pr.company_id
     LEFT JOIN switch_port_types spt_any
       ON spt_any.id = pr.port_type
     LEFT JOIN switch_port_types spt_sp
       ON spt_sp.company_id = pr.company_id
      AND sp_dest.id IS NOT NULL
      AND (
            spt_sp.id = sp_dest.port_type
            OR spt_sp.type = sp_dest.port_type
            OR (
                sp_dest.port_type REGEXP '^[0-9]+$'
                AND spt_sp.id = CAST(sp_dest.port_type AS UNSIGNED)
            )
      )
     WHERE i.company_id = ?
     ORDER BY p.position_no ASC, pr.port_no ASC"
);
if ($stmtDestinationPorts) {
    mysqli_stmt_bind_param($stmtDestinationPorts, 'i', $company_id);
    mysqli_stmt_execute($stmtDestinationPorts);
    $resDestinationPorts = mysqli_stmt_get_result($stmtDestinationPorts);
    while ($resDestinationPorts && ($row = mysqli_fetch_assoc($resDestinationPorts))) {
        $destinationPorts[] = [
            'id' => (int)($row['id'] ?? 0),
            'port_no' => (int)($row['port_no'] ?? 0),
            'label' => (string)($row['label'] ?? ''),
            'idf_id' => (int)($row['idf_id'] ?? 0),
            'idf_name' => (string)($row['idf_name'] ?? ''),
            'position_id' => (int)($row['position_id'] ?? 0),
            'position_no' => (int)($row['position_no'] ?? 0),
            'device_name' => (string)($row['device_name'] ?? ''),
            'device_type' => (string)($row['device_type'] ?? ''),
            'equipment_id' => idf_parse_linked_equipment_id($row['equipment_id'] ?? ''),
            'equipment_asset_id' => trim((string)($row['equipment_id'] ?? '')),
            'status_label' => (string)($row['status_label'] ?? 'Unknown'),
            'color_name' => (string)($row['color_name'] ?? 'Gray'),
            'color_hex' => (string)($row['color_hex'] ?? '#808080'),
            'is_linked' => !empty($row['is_linked']),
            'port_type_label' => (string)($row['port_type_label'] ?? 'RJ45'),
        ];
    }
    mysqli_stmt_close($stmtDestinationPorts);
}

$equipmentOptions = [];
$resEq = mysqli_query(
    $conn,
    "SELECT e.id, e.name, e.hostname, e.serial_number
     FROM equipment e
     WHERE e.company_id=$company_id
     ORDER BY e.name ASC
     LIMIT 500"
);
while ($resEq && ($row = mysqli_fetch_assoc($resEq))) {
    $equipmentOptions[] = $row;
}

$cableColorOptions = [];
$cableColorHexByName = [];
$resCableColors = mysqli_query(
    $conn,
    "SELECT color_name, hex_color
     FROM cable_colors
     WHERE company_id = $company_id
     ORDER BY color_name ASC, hex_color ASC"
);
while ($resCableColors && ($row = mysqli_fetch_assoc($resCableColors))) {
    $color = trim((string)($row['color_name'] ?? ''));
    $hexColor = trim((string)($row['hex_color'] ?? ''));
    if ($color === '') {
        $color = $hexColor;
    }
    if ($color !== '') {
        $cableColorOptions[] = $color;
        if ($hexColor !== '' && !isset($cableColorHexByName[strtolower($color)])) {
            $cableColorHexByName[strtolower($color)] = $hexColor;
        }
    }
}
$hasDefaultGrayCableColor = false;
foreach ($cableColorOptions as $existingCableColorOption) {
    if (strcasecmp($existingCableColorOption, 'Gray') === 0) {
        $hasDefaultGrayCableColor = true;
        break;
    }
}
if (!$hasDefaultGrayCableColor) {
    $cableColorOptions[] = 'Gray';
}
sort($cableColorOptions, SORT_NATURAL | SORT_FLAG_CASE);

$switchStatusOptions = [];
$resSwitchStatuses = mysqli_query(
    $conn,
    "SELECT id, status
     FROM switch_status
     WHERE company_id = $company_id
     ORDER BY status ASC"
);
while ($resSwitchStatuses && ($row = mysqli_fetch_assoc($resSwitchStatuses))) {
    $statusId = (int)($row['id'] ?? 0);
    $statusOption = trim((string)($row['status'] ?? ''));
    if ($statusId > 0 && $statusOption !== '' && !isset($switchStatusOptions[$statusId])) {
        $switchStatusOptions[$statusId] = $statusOption;
    }
}
if (!$switchStatusOptions) {
    $switchStatusOptions = [0 => 'Unknown'];
}

$switchPortTypeOptions = [];
$resSwitchPortTypes = mysqli_query(
    $conn,
    "SELECT id, type
     FROM switch_port_types
     WHERE company_id = $company_id
     ORDER BY type ASC"
);
while ($resSwitchPortTypes && ($row = mysqli_fetch_assoc($resSwitchPortTypes))) {
    $typeId = (int)($row['id'] ?? 0);
    $typeLabel = trim((string)($row['type'] ?? ''));
    if ($typeId > 0 && $typeLabel !== '' && !isset($switchPortTypeOptions[$typeId])) {
        $switchPortTypeOptions[$typeId] = $typeLabel;
    }
}
if (!$switchPortTypeOptions) {
    $switchPortTypeOptions = [0 => 'RJ45'];
}

$vlanOptions = [];
$resVlans = mysqli_query(
    $conn,
    "SELECT id, vlan_number, vlan_name
     FROM vlans
     WHERE company_id = $company_id
     ORDER BY vlan_number ASC, vlan_name ASC"
);
while ($resVlans && ($row = mysqli_fetch_assoc($resVlans))) {
    $vlanId = (int)($row['id'] ?? 0);
    $vlanNumber = trim((string)($row['vlan_number'] ?? ''));
    $vlanName = trim((string)($row['vlan_name'] ?? ''));
    if ($vlanId <= 0) {
        continue;
    }
    $label = $vlanNumber;
    if ($vlanName !== '') {
        $label = $label !== '' ? ($label . ' - ' . $vlanName) : $vlanName;
    }
    if ($label === '') {
        $label = 'VLAN #' . $vlanId;
    }
    $vlanOptions[$vlanId] = $label;
}

$itmVlanAddExtraFields = [
    ['name' => 'vlan_number', 'label' => 'VLAN Number', 'type' => 'number'],
    ['name' => 'vlan_color', 'label' => 'Vlan Color', 'type' => 'color'],
];
$itmRj45SpeedAddExtraFields = [
    ['name' => 'max_speed', 'label' => 'Max Speed', 'type' => 'text', 'required' => true],
    ['name' => 'bandwidth', 'label' => 'Bandwidth', 'type' => 'text', 'required' => true],
    ['name' => 'max_distance_full_speed', 'label' => 'Max Distance (full speed)', 'type' => 'text', 'required' => true],
];
$itmVlanAddExtraFieldsAttr = htmlspecialchars(json_encode($itmVlanAddExtraFields, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$itmAddableSelectConfig = [
    'vlan' => [
        'table' => 'vlans',
        'idCol' => 'id',
        'labelCol' => 'vlan_name',
        'companyScoped' => '1',
        'friendly' => 'vlan',
        'extraFields' => $itmVlanAddExtraFields,
    ],
    'rj45_speed' => [
        'table' => 'rj45_speed',
        'idCol' => 'id',
        'labelCol' => 'cable_type',
        'companyScoped' => '1',
        'friendly' => 'rj45 cable',
        'extraFields' => $itmRj45SpeedAddExtraFields,
    ],
    'equipment_fiber' => [
        'table' => 'equipment_fiber',
        'idCol' => 'id',
        'labelCol' => 'name',
        'companyScoped' => '1',
        'friendly' => 'fiber port type',
    ],
    'equipment_fiber_patch' => [
        'table' => 'equipment_fiber_patch',
        'idCol' => 'id',
        'labelCol' => 'name',
        'companyScoped' => '1',
        'friendly' => 'fiber patch',
    ],
    'equipment_fiber_rack' => [
        'table' => 'equipment_fiber_rack',
        'idCol' => 'id',
        'labelCol' => 'name',
        'companyScoped' => '1',
        'friendly' => 'fiber rack',
    ],
    'equipment_poe' => [
        'table' => 'equipment_poe',
        'idCol' => 'id',
        'labelCol' => 'name',
        'companyScoped' => '1',
        'friendly' => 'poe type',
    ],
];

$fiberSpeedOptions = [];
$resFiberSpeeds = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_fiber
     WHERE company_id = $company_id
     ORDER BY name ASC"
);
while ($resFiberSpeeds && ($row = mysqli_fetch_assoc($resFiberSpeeds))) {
    $fiberId = (int)($row['id'] ?? 0);
    $fiberName = trim((string)($row['name'] ?? ''));
    if ($fiberId > 0 && $fiberName !== '') {
        $fiberSpeedOptions[$fiberId] = $fiberName;
    }
}

$rj45SpeedOptions = [];
$resRj45Speeds = mysqli_query(
    $conn,
    "SELECT id, cable_type
     FROM rj45_speed
     WHERE company_id = $company_id
     ORDER BY cable_type ASC"
);
while ($resRj45Speeds && ($row = mysqli_fetch_assoc($resRj45Speeds))) {
    $speedId = (int)($row['id'] ?? 0);
    $cableType = trim((string)($row['cable_type'] ?? ''));
    if ($speedId > 0 && $cableType !== '') {
        $rj45SpeedOptions[$speedId] = $cableType;
    }
}

$fiberPatchOptions = [];
$resFiberPatches = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_fiber_patch
     WHERE company_id = $company_id
     ORDER BY name ASC"
);
while ($resFiberPatches && ($row = mysqli_fetch_assoc($resFiberPatches))) {
    $fiberPatchId = (int)($row['id'] ?? 0);
    $fiberPatchName = trim((string)($row['name'] ?? ''));
    if ($fiberPatchId > 0 && $fiberPatchName !== '') {
        $fiberPatchOptions[$fiberPatchId] = $fiberPatchName;
    }
}

$fiberRackOptions = [];
$resFiberRacks = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_fiber_rack
     WHERE company_id = $company_id
     ORDER BY name ASC"
);
while ($resFiberRacks && ($row = mysqli_fetch_assoc($resFiberRacks))) {
    $fiberRackId = (int)($row['id'] ?? 0);
    $fiberRackName = trim((string)($row['name'] ?? ''));
    if ($fiberRackId > 0 && $fiberRackName !== '') {
        $fiberRackOptions[$fiberRackId] = $fiberRackName;
    }
}

$poeOptions = itm_equipment_poe_load_options($conn, $company_id);

if (!empty($ports)) {
    foreach ($ports as &$portRowDisplayRelabel) {
        $portRowDisplayRelabel = $idfDeviceHydratePortRow($portRowDisplayRelabel);
        idf_refresh_port_row_display_labels(
            $portRowDisplayRelabel,
            $vlanOptions,
            $rj45SpeedOptions,
            $fiberSpeedOptions,
            $poeOptions,
            $fiberPatchOptions,
            $fiberRackOptions
        );
    }
    unset($portRowDisplayRelabel);
}

$equipmentTypeOptions = [];
$resEqTypes = mysqli_query(
    $conn,
    "SELECT et.id, et.name
     FROM equipment_types et
     WHERE et.company_id=$company_id
     ORDER BY et.name ASC"
);
while ($resEqTypes && ($row = mysqli_fetch_assoc($resEqTypes))) {
    $equipmentTypeOptions[] = [
        'value' => (int)($row['id'] ?? 0),
        'label' => (string)($row['name'] ?? ''),
    ];
}

$equipmentStatusOptions = [];
$resEqStatuses = mysqli_query(
    $conn,
    "SELECT es.id, es.name
     FROM equipment_statuses es
     WHERE es.company_id=$company_id
     ORDER BY es.name ASC"
);
while ($resEqStatuses && ($row = mysqli_fetch_assoc($resEqStatuses))) {
    $equipmentStatusOptions[] = [
        'value' => (int)($row['id'] ?? 0),
        'label' => (string)($row['name'] ?? ''),
    ];
}

$equipmentAddExtraFields = json_encode([
    [
        'name' => 'equipment_type_id',
        'label' => 'Equipment Type',
        'type' => 'select',
        'options' => $equipmentTypeOptions,
    ],
    [
        'name' => 'status_id',
        'label' => 'Status',
        'type' => 'select',
        'options' => $equipmentStatusOptions,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ui_config = itm_get_ui_configuration($conn, $company_id);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Device</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <style>
        .idf-toolbar {
            border:1px solid var(--border);
            border-radius:18px;
            padding:14px;
            margin-bottom:14px;
            background: linear-gradient(120deg, rgba(9,105,218,.14), rgba(9,105,218,.04));
            box-shadow: var(--shadow);
            display:flex;
            justify-content:space-between;
            gap:14px;
            flex-wrap:wrap;
            align-items:center;
        }
        .idf-toolbar .left,
        .idf-toolbar .right {
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }
        .idf-rack-title { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
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
        .idf-ports-table td,
        .idf-ports-table th { vertical-align:middle; }
        .idf-ports-table tr.idf-port-selected {
            background: rgba(9, 105, 218, .14);
            box-shadow: inset 0 0 0 1px rgba(9, 105, 218, .35);
        }
        /* Why: match rack view compact visualizer proportions (grid + SFP dots) instead of full-width stretch. */
        .idf-device-port-visual {
            overflow-x: auto;
        }
        .idf-device-port-visual .itm-port-visualizer-container {
            display: inline-flex;
            width: fit-content;
            max-width: 100%;
            justify-content: flex-start;
            margin: 8px 0 0;
        }
        .idf-swatch {
            display:inline-block;
            width:10px;
            height:10px;
            border-radius:999px;
            border:1px solid rgba(0,0,0,.2);
            margin-right:6px;
            vertical-align:middle;
        }
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
        body.idf-embed-mode {
            margin:0;
            padding:0;
            background:var(--bg-primary);
        }
        body.idf-embed-mode .content {
            padding:12px;
        }
        body.idf-embed-mode .idf-modal-backdrop {
            left:0;
            z-index:2200;
        }
    </style>
</head>
<body class="<?php echo $embed_mode ? 'idf-embed-mode' : ''; ?><?php echo $embed_modal_only ? ' idf-embed-modal-only' : ''; ?>">
<?php if (!$embed_mode): ?>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>
<?php endif; ?>

        <div class="content" id="idfDeviceCaptureRoot">
            <div class="idf-toolbar">
                <div class="left">
                    <?php if ($embed_mode): ?>
                        <button class="btn btn-sm" type="button" onclick="closeEmbeddedDeviceView()">Close</button>
                    <?php else: ?>
                        <?php
                        $deviceBackHref = $return_to !== ''
                            ? $return_to
                            : ('view.php?id=' . (int)$pos['idf_id']);
                        ?>
                        <a class="btn btn-sm" href="<?php echo sanitize($deviceBackHref); ?>">&larr; Back to rack</a>
                    <?php endif; ?>
                    <div style="display:flex; flex-direction:column;">
                        <div style="opacity:.85; font-size:13px; font-weight:600; margin-bottom:2px;">
                            IDF <?php echo sanitize((string)$pos['idf_name']); ?> - <?php echo sanitize((string)$pos['location_name']); ?>
                        </div>
                        <div class="idf-rack-title">
                            <?php echo sanitize($pos['device_name']); ?>
                            <span class="idf-badge">Position <?php echo (int)$pos['position_no']; ?></span>
                            <span class="idf-badge"><?php echo sanitize((string)$pos['device_type']); ?></span>
                            <?php if (!empty($pos['equipment_id'])): ?>
                                <span class="idf-badge">Asset ID <?php echo sanitize((string)$pos['equipment_id']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="right" style="display:flex; gap:8px;">
                    <button class="btn btn-sm" type="button" onclick="idfDeviceExportImage()">Export Image</button>
                    <button class="btn btn-sm" type="button" onclick="idfPortsExportExcel()">Export Ports Excel</button>
                </div>
            </div>

            <div class="card" style="padding:14px; border-radius:18px; margin-bottom:14px;">
                <h3 style="margin-top:0;">Port Visualization</h3>
                <div class="idf-device-port-visual">
                <?php
                $deviceGridPortType = 'rj45';
                if (!empty($sfpPortNumbers)) {
                    $deviceGridPortType = 'all';
                }
                echo itm_render_port_visualizer($ports, [
                    'clickable' => true,
                    'layout' => (string)($pos['layout_name'] ?? 'Vertical'),
                    'show_device_icon' => ((int)($pos['equipment_is_switch'] ?? 0) === 1),
                    'position_id' => (int)($pos['id'] ?? 0),
                    'company_name' => (string)($pos['company_name'] ?? ''),
                    'location_name' => (string)($pos['location_name'] ?? ''),
                    'idf_name' => (string)($pos['idf_name'] ?? ''),
                    'idf_code' => (string)($pos['idf_code'] ?? ''),
                    'rack_name' => (string)($pos['rack_name'] ?? ''),
                    'grid_port_type' => $deviceGridPortType,
                    'rj45_ports' => $rj45PortNumbers,
                    'sfp_ports' => $sfpPortNumbers,
                ]);
                ?>
                </div>
            </div>

            <div class="card" style="padding:14px; border-radius:18px; margin-bottom:14px;">
                <h3 style="margin-top:0;">Equipment Link Map</h3>
                <div style="opacity:.8; margin-bottom:10px; font-size:12px;">
                    Quick view of this device's patch links to other equipment, including selected equipment metadata.
                </div>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Local Port</th>
                        <th>Remote Device</th>
                        <th>Remote Port</th>
                        <th>Cable</th>
                        <th>Notes (optional)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$linkOverview): ?>
                        <tr><td colspan="5" style="opacity:.8;">No links yet for this device.</td></tr>
                    <?php else: ?>
                        <?php foreach ($linkOverview as $row): ?>
                            <tr>
                                <td>
                                    <?php echo (int)$row['local_port_no']; ?>
                                    <?php if ($row['local_label'] !== ''): ?>
                                        <span style="opacity:.75;"> &middot;  <?php echo sanitize($row['local_label']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>Pos <?php echo (int)$row['remote_position_no']; ?>  &middot;  <?php echo sanitize($row['remote_device_name']); ?></td>
                                <td><?php echo (int)$row['remote_port_no']; ?></td>
                                <td>
                                    <?php
                                    $linkCableHex = trim((string)($row['cable_hex_color'] ?? ''));
                                    $linkCableName = trim((string)($row['cable_color_name'] ?? ''));
                                    $linkCableDisplay = $linkCableName !== '' ? $linkCableName : ($linkCableHex !== '' ? $linkCableHex : 'Gray');
                                    $linkCableSwatchColor = $linkCableHex !== '' ? $linkCableHex : $linkCableDisplay;
                                    ?>
                                    <span class="idf-swatch" style="background:<?php echo sanitize($linkCableSwatchColor); ?>"></span>
                                    <?php echo sanitize($linkCableDisplay); ?>
                                    <?php if ($row['cable_label'] !== ''): ?>
                                        <span style="opacity:.75;"> &middot;  <?php echo sanitize($row['cable_label']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['link_notes'] !== '' ? sanitize($row['link_notes']) : '<span style="opacity:.75;">-</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" style="padding:14px; border-radius:18px;">
                <h3 style="margin-top:0;">Ports</h3>

                <?php if (count($ports) === 0): ?>
                    <div class="alert alert-error">Port list is empty. Ports sync from linked equipment on page load; use "Regenerate Ports" if they still do not appear.</div>
                <?php endif; ?>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                    <button class="btn btn-sm" type="button" onclick="regeneratePorts()">Regenerate Ports</button>
                </div>


                <?php
                $buildPortSortLink = static function (string $field) use ($portSortField, $portSortDir): string {
                    $query = $_GET;
                    $query['sort'] = $field;
                    $query['dir'] = ($portSortField === $field && $portSortDir === 'ASC') ? 'desc' : 'asc';
                    return '?' . http_build_query($query);
                };
                $renderPortSortIndicator = static function (string $field) use ($portSortField, $portSortDir): string {
                    if ($portSortField !== $field) {
                        return '';
                    }
                    return $portSortDir === 'ASC' ? ' &#x25B2;' : ' &#x25BC;';
                };
                ?>

                <table id="portsTable" class="table idf-ports-table">
                    <thead>
                    <tr>
                        <th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('port_no')); ?>">#<?php echo $renderPortSortIndicator('port_no'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('port_type')); ?>">Type<?php echo $renderPortSortIndicator('port_type'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('label')); ?>">Label<?php echo $renderPortSortIndicator('label'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('status')); ?>">Status<?php echo $renderPortSortIndicator('status'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('connected_to')); ?>">Connected To<?php echo $renderPortSortIndicator('connected_to'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('vlan')); ?>">VLAN<?php echo $renderPortSortIndicator('vlan'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('rj45_speed')); ?>">RJ45 Speed<?php echo $renderPortSortIndicator('rj45_speed'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('poe')); ?>">PoE<?php echo $renderPortSortIndicator('poe'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('speed')); ?>">Fiber Speed<?php echo $renderPortSortIndicator('speed'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('fiber_patch')); ?>">Fiber Patch<?php echo $renderPortSortIndicator('fiber_patch'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('fiber_rack')); ?>">Fiber Rack<?php echo $renderPortSortIndicator('fiber_rack'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('notes')); ?>">Notes<?php echo $renderPortSortIndicator('notes'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('link')); ?>">Link<?php echo $renderPortSortIndicator('link'); ?></a></th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$ports): ?>
                        <tr><td colspan="14" style="opacity:.8;">No ports yet.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($ports as $p): ?>
                        <?php
                        $linkedEquipmentId = (int)($p['linked_equipment_id'] ?? 0);
                        $linkedEquipmentIsSwitch = (int)($p['linked_equipment_is_switch'] ?? 0) === 1;
                        $canEditLinkedSwitch = $linkedEquipmentId > 0
                            && $linkedEquipmentIsSwitch
                            && !empty($p['link_id']);
                        $editLinkedUrl = '../equipment/index.php?switch_id=' . $linkedEquipmentId . '&spm=1#switch-port-manager';
                        $linkText = '';
                        $connectedToText = trim((string)($p['connected_to'] ?? ''));
                        $unlinkBtn = '';
                        if (!empty($p['link_id'])) {
                            $o = null;
                            if (!empty($p['other_port_id']) && isset($otherMap[(int)$p['other_port_id']])) {
                                $o = $otherMap[(int)$p['other_port_id']];
                            }
                            $remotePos = $o ? (int)$o['position_no'] : (int)($p['remote_position_no'] ?? 0);
                            $remoteDev = $o ? (string)$o['device_name'] : trim((string)($p['remote_device_name'] ?? ''));
                            $remotePort = $o ? (int)$o['port_no'] : (int)($p['remote_port_no'] ?? 0);
                            $equipmentName = trim((string)($p['equipment_hostname'] ?? ''));
                            $equipmentPort = trim((string)($p['equipment_port'] ?? ''));
                            if ($equipmentName !== '') {
                                $remoteDev = $equipmentName;
                            }
                            if ($equipmentPort !== '' && ctype_digit($equipmentPort)) {
                                $remotePort = (int)$equipmentPort;
                            }
                            if ($remoteDev !== '' || $remotePos > 0 || $remotePort > 0) {
                                $color = (string)($p['cable_hex_color'] ?? '#ffff00');
                                $label = !empty($p['cable_label']) ? ('  &middot;  ' . sanitize((string)$p['cable_label'])) : '';
                                $isLoopRisk = $o && ((int)($o['position_id'] ?? 0) === (int)$position_id);
                                $linkText = '<span class="idf-swatch" style="background:' . sanitize($color) . '"></span>'
                                    . 'Pos ' . $remotePos . '  &middot;  ' . sanitize($remoteDev) . '  &middot;  Port ' . $remotePort . $label
                                    . ($isLoopRisk ? ' <span class="badge badge-danger" title="Same-device link detected. This can create a Layer 2 loop on switches without STP.">Loop Risk</span>' : '');
                                if ($connectedToText === '') {
                                    $connectedToText = 'Pos ' . $remotePos . '  &middot;  ' . $remoteDev . '  &middot;  Port ' . $remotePort;
                                }
                                $unlinkBtn = '<button class="btn btn-sm" type="button" onclick="unlinkPort(' . (int)$p['link_id'] . ')">Unlink</button>';
                            }
                        }
                        $connectedToPlain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($connectedToText), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                        if ($connectedToPlain === '') {
                            $connectedToPlain = trim((string)($p['connected_to'] ?? ''));
                        }
                        $speedValueId = isset($p['speed_value_id']) ? (int)$p['speed_value_id'] : (isset($p['speed_id']) ? (int)$p['speed_id'] : 0);
                        $effectiveVlanIdRow = (int)($p['effective_vlan_id'] ?? $p['vlan_id'] ?? 0);
                        $effectiveRj45SpeedIdRow = (int)($p['effective_rj45_speed_id'] ?? $p['rj45_speed_id'] ?? 0);
                        $effectiveFiberPortIdRow = (int)($p['effective_fiber_port_id'] ?? 0);
                        $effectiveFiberPatchIdRow = (int)($p['effective_fiber_patch_id'] ?? 0);
                        $effectiveFiberRackIdRow = (int)($p['effective_fiber_rack_id'] ?? 0);
                        $effectivePoeIdRow = (int)($p['effective_poe_id'] ?? $p['poe_id'] ?? 0);
                        ?>
                        <tr
                            data-port-id="<?php echo (int)$p['id']; ?>"
                            data-port-type-id="<?php echo (int)($p['port_type'] ?? 0); ?>"
                            data-port-type="<?php echo sanitize((string)($p['port_type_label'] ?? 'RJ45')); ?>"
                            data-label="<?php echo sanitize(idf_normalize_label_display($p['label'] ?? '')); ?>"
                            data-status-id="<?php echo (int)($p['status_id'] ?? 0); ?>"
                            data-status="<?php echo sanitize((string)($p['status_label'] ?? 'Unknown')); ?>"
                            data-connected-to="<?php echo sanitize($connectedToPlain); ?>"
                            data-vlan-id="<?php echo $effectiveVlanIdRow > 0 ? (string)$effectiveVlanIdRow : ''; ?>"
                            data-vlan="<?php echo sanitize((string)($p['vlan_label'] ?? '')); ?>"
                            data-rj45-speed-id="<?php echo $effectiveRj45SpeedIdRow > 0 ? (string)$effectiveRj45SpeedIdRow : ''; ?>"
                            data-rj45-speed="<?php echo sanitize((string)($p['rj45_speed_label'] ?? '')); ?>"
                            data-fiber-port-id="<?php echo $effectiveFiberPortIdRow > 0 ? (string)$effectiveFiberPortIdRow : ''; ?>"
                            data-speed-id="<?php echo $effectiveFiberPortIdRow > 0 ? (string)$effectiveFiberPortIdRow : ($speedValueId > 0 ? (string)$speedValueId : ''); ?>"
                            data-speed="<?php echo sanitize((string)($p['speed_label'] ?? '')); ?>"
                            data-poe-id="<?php echo $effectivePoeIdRow > 0 ? (string)$effectivePoeIdRow : ''; ?>"
                            data-poe="<?php echo sanitize((string)($p['poe_label'] ?? '')); ?>"
                            data-fiber-patch-id="<?php echo $effectiveFiberPatchIdRow > 0 ? (string)$effectiveFiberPatchIdRow : ''; ?>"
                            data-fiber-patch="<?php echo sanitize((string)($p['fiber_patch_label'] ?? '')); ?>"
                            data-fiber-rack-id="<?php echo $effectiveFiberRackIdRow > 0 ? (string)$effectiveFiberRackIdRow : ''; ?>"
                            data-fiber-rack="<?php echo sanitize((string)($p['fiber_rack_label'] ?? '')); ?>"
                            data-notes="<?php echo sanitize((string)($p['notes'] ?? '')); ?>"
                        >
                            <td><?php echo (int)$p['port_no']; ?></td>
                            <td><?php echo sanitize((string)($p['port_type_label'] ?? 'RJ45')); ?></td>
                            <td><?php echo sanitize(idf_normalize_label_display($p['label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['status_label'] ?? 'Unknown')); ?></td>
                            <td><?php echo sanitize($connectedToText); ?></td>
                            <td><?php echo sanitize((string)($p['vlan_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['rj45_speed_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['poe_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['speed_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['fiber_patch_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['fiber_rack_label'] ?? '')); ?></td>
                            <td><?php echo sanitize(idf_normalize_port_notes_value($p['notes'] ?? '')); ?></td>
                            <td><?php echo $linkText ?: '<span style="opacity:.75;">-</span>'; ?></td>
                            <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                <button class="btn btn-sm" type="button" onclick="openPortModal(<?php echo (int)$p['id']; ?>)">Edit</button>
                                <?php if ($canEditLinkedSwitch): ?>
                                    <a class="btn btn-sm" href="<?php echo $editLinkedUrl; ?>">Edit Linked</a>
                                <?php endif; ?>
                                <button class="btn btn-sm" type="button" onclick="openLinkModal(<?php echo (int)$p['id']; ?>)">Link</button>
                                <?php echo $unlinkBtn; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <table id="portsExportTable" class="table" style="display:none;">
                <thead>
                <tr>
                    <th>Port No</th><th>Type</th><th>Label</th><th>Status</th><th>Connected To</th><th>VLAN</th><th>RJ45 Speed</th><th>PoE</th><th>Fiber Speed</th><th>Fiber Patch</th><th>Fiber Rack</th><th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ports as $p): ?>
                    <tr>
                        <td><?php echo (int)$p['port_no']; ?></td>
                        <td><?php echo sanitize((string)($p['port_type_label'] ?? 'RJ45')); ?></td>
                        <td><?php echo sanitize(idf_normalize_label_display($p['label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['status_label'] ?? 'Unknown')); ?></td>
                        <td><?php echo sanitize((string)($p['connected_to'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['vlan_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['rj45_speed_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['poe_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['speed_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['fiber_patch_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['fiber_rack_label'] ?? '')); ?></td>
                        <td><?php echo sanitize(idf_normalize_port_notes_value($p['notes'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php if (!$embed_mode): ?>
    </div>
</div>
<?php endif; ?>

<div class="idf-modal-backdrop" id="portBackdrop">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Edit Port</div>
            <button class="btn btn-sm" type="button" onclick="closePortModal()" aria-label="Close">&times;</button>
        </div>

        <form id="portForm" class="idf-grid-2">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
            <input type="hidden" name="port_id" value="">
            <div>
                <label class="label">Type</label>
                <select class="input" name="port_type">
                    <?php foreach ($switchPortTypeOptions as $typeId => $typeLabel): ?>
                        <option value="<?php echo (int)$typeId; ?>" <?php echo strcasecmp($typeLabel, 'RJ45') === 0 ? 'selected' : ''; ?>><?php echo sanitize($typeLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select class="input" name="status">
                    <?php foreach ($switchStatusOptions as $statusId => $statusOption): ?>
                        <option value="<?php echo (int)$statusId; ?>" <?php echo strcasecmp($statusOption, 'Unknown') === 0 ? 'selected' : ''; ?>><?php echo sanitize($statusOption); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">&#x2795;</option>
                </select>
            </div>
            <div>
                <label class="label" id="portPatchPortLabel">Label</label>
                <input class="input" name="label">
            </div>
            <div>
                <label class="label">Connected To</label>
                <input class="input" name="connected_to" placeholder="e.g. PP1-12 / Desk-203 / Core-SW1 Gi1/0/12">
            </div>
            <div>
                <label class="label">VLAN</label>
                <select class="input" name="vlan"
                    data-addable-select="1"
                    data-add-table="vlans"
                    data-add-id-col="id"
                    data-add-label-col="vlan_name"
                    data-add-company-scoped="1"
                    data-add-friendly="vlan"
                    data-add-extra-fields="<?php echo $itmVlanAddExtraFieldsAttr; ?>">
                    <option value="">-- None --</option>
                    <?php foreach ($vlanOptions as $vlanId => $vlanLabel): ?>
                        <option value="<?php echo (int)$vlanId; ?>"><?php echo sanitize($vlanLabel); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">&#x2795;</option>
                </select>
            </div>
            <div>
                <label class="label" id="portSpeedFieldLabel">RJ45 Cable</label>
                <select class="input" name="speed" data-addable-select="1" data-add-company-scoped="1">
                    <option value="">-- None --</option>
                </select>
            </div>
            <div id="portTypeSpecificFields" style="grid-column: 1 / -1;"></div>
            <div id="portPoeField">
                <label class="label">PoE</label>
                <select class="input" name="poe"
                    data-addable-select="1"
                    data-add-table="equipment_poe"
                    data-add-id-col="id"
                    data-add-label-col="name"
                    data-add-company-scoped="1"
                    data-add-friendly="poe type">
                    <option value="">-- None --</option>
                    <?php foreach ($poeOptions as $poeId => $poeLabel): ?>
                        <option value="<?php echo (int)$poeId; ?>"><?php echo sanitize($poeLabel); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">&#x2795;</option>
                </select>
            </div>
            <div>
                <label class="label">Cable color</label>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="portCableColorSwatch" class="idf-swatch" style="width:16px; height:16px; border:1px solid #d9d9d9; background:Gray; flex:0 0 auto;"></span>
                    <select class="input" name="cable_color_id" data-add-table="cable_colors" data-swatch-id="portCableColorSwatch" style="flex:1 1 auto;">
                        <?php
                        $resColors = mysqli_query($conn, "SELECT id, color_name, hex_color FROM cable_colors WHERE company_id=$company_id ORDER BY color_name");
                        while($c = mysqli_fetch_assoc($resColors)): ?>
                            <option value="<?php echo (int)$c['id']; ?>" data-hex="<?php echo sanitize($c['hex_color']); ?>">
                                <?php
                                $cableColorLabel = trim((string)($c['color_name'] ?? ''));
                                if ($cableColorLabel === '') {
                                    $cableColorLabel = trim((string)($c['hex_color'] ?? ''));
                                }
                                echo sanitize($cableColorLabel);
                                ?>
                            </option>
                        <?php endwhile; ?>
                        <option value="__add_new__">&#x2795;</option>
                    </select>
                </div>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="label" id="portCommentsLabel">Notes</label>
                <input class="input" name="notes">
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" type="button" onclick="closePortModal()">Cancel</button>
                <button class="btn" type="button" onclick="savePort()">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="idf-modal-backdrop" id="linkBackdrop">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Create Cable Link</div>
            <button class="btn btn-sm" type="button" onclick="closeLinkModal()" aria-label="Close">&times;</button>
        </div>

        <form id="linkForm" class="idf-grid-2">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
            <input type="hidden" name="port_id_a" value="">
            <div style="grid-column: 1 / -1;">
                <label class="label">Source port</label>
                <input class="input" name="source_display" value="" readonly>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="label">Linked to Equipment (optional)</label>
                <select class="input" name="equipment_id">
                    <option value="">-- None --</option>
                    <?php foreach ($equipmentOptions as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>">
                            <?php
                            $equipmentName = trim((string)($e['name'] ?? ''));
                            $equipmentHostname = trim((string)($e['hostname'] ?? ''));
                            $equipmentLabel = $equipmentName;
                            if ($equipmentHostname !== '') {
                                // Why: sanitize() escapes HTML entities; use a literal separator so the dropdown does not show &amp;middot;.
                                $equipmentLabel .= ' · ' . $equipmentHostname;
                            }
                            echo sanitize($equipmentLabel);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="label">Equipment port (optional)</label>
                <select class="input" name="switch_port_id" disabled>
                    <option value="">Select equipment first</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;" data-link-default-field="destination">
                <label class="label">Destination port</label>
                <select class="input" name="port_id_b">
                    <option value="">Select destination port</option>
                </select>
            </div>
            <div data-link-default-field="cable_color">
                <label class="label">Cable color</label>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="cableColorSwatch" class="idf-swatch" style="width:16px; height:16px; border:1px solid #d9d9d9; background:Gray; flex:0 0 auto;"></span>
                    <select class="input" name="cable_color_id" data-add-table="cable_colors" data-swatch-id="cableColorSwatch" style="flex:1 1 auto;">
                        <?php
                        $resColors = mysqli_query($conn, "SELECT id, color_name, hex_color FROM cable_colors WHERE company_id=$company_id ORDER BY color_name");
                        while($c = mysqli_fetch_assoc($resColors)): ?>
                            <option value="<?php echo (int)$c['id']; ?>" data-hex="<?php echo sanitize($c['hex_color']); ?>">
                                <?php
                                $cableColorLabel = trim((string)($c['color_name'] ?? ''));
                                if ($cableColorLabel === '') {
                                    $cableColorLabel = trim((string)($c['hex_color'] ?? ''));
                                }
                                echo sanitize($cableColorLabel);
                                ?>
                            </option>
                        <?php endwhile; ?>
                        <option value="__add_new__">&#x2795;</option>
                    </select>
                </div>
            </div>
            <div data-link-default-field="cable_label">
                <label class="label" id="linkPatchPortLabel">Label</label>
                <input class="input" name="cable_label" placeholder="e.g. FIB-12 / CAT6-34">
            </div>
            <div data-link-default-field="status" data-link-keep-visible-when-linked="1">
                <label class="label">Status</label>
                <select class="input" name="status">
                    <?php foreach ($switchStatusOptions as $statusId => $statusOption): ?>
                        <option value="<?php echo (int)$statusId; ?>" <?php echo strcasecmp($statusOption, 'Unknown') === 0 ? 'selected' : ''; ?>><?php echo sanitize($statusOption); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">&#x2795;</option>
                </select>
            </div>
            <div data-link-default-field="vlan" data-link-keep-visible-when-linked="1">
                <label class="label">VLAN</label>
                <select class="input" name="vlan"
                    data-addable-select="1"
                    data-add-table="vlans"
                    data-add-id-col="id"
                    data-add-label-col="vlan_name"
                    data-add-company-scoped="1"
                    data-add-friendly="vlan"
                    data-add-extra-fields="<?php echo $itmVlanAddExtraFieldsAttr; ?>">
                    <option value="">-- None --</option>
                    <?php foreach ($vlanOptions as $vlanId => $vlanLabel): ?>
                        <option value="<?php echo (int)$vlanId; ?>"><?php echo sanitize($vlanLabel); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">&#x2795;</option>
                </select>
            </div>
            <div id="linkTypeSpecificFields" style="grid-column: 1 / -1;"></div>
            <div style="grid-column: 1 / -1;" data-link-default-field="notes">
                <label class="label" id="linkCommentsLabel">Notes</label>
                <input class="input" name="notes" placeholder="Optional">
            </div>
            <div style="grid-column: 1 / -1; display:none;" id="linkedEquipmentFields">
                <div class="idf-grid-2">
                    <div>
                        <label class="label">Equipment port</label>
                        <input class="input" name="linked_equipment_port" placeholder="e.g. 12" readonly>
                    </div>
                    <div>
                        <label class="label">Cable color</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span id="linkedCableColorSwatch" class="idf-swatch" style="width:16px; height:16px; border:1px solid #d9d9d9; background:Gray; flex:0 0 auto;"></span>
                            <select class="input" name="linked_cable_color_id" data-add-table="cable_colors" data-swatch-id="linkedCableColorSwatch" style="flex:1 1 auto;">
                                <?php
                                $resLinkedColors = mysqli_query($conn, "SELECT id, color_name, hex_color FROM cable_colors WHERE company_id=$company_id ORDER BY color_name");
                                while($lc = mysqli_fetch_assoc($resLinkedColors)): ?>
                                    <option value="<?php echo (int)$lc['id']; ?>" data-hex="<?php echo sanitize($lc['hex_color']); ?>">
                                        <?php
                                        $linkedColorLabel = trim((string)($lc['color_name'] ?? ''));
                                        if ($linkedColorLabel === '') {
                                            $linkedColorLabel = trim((string)($lc['hex_color'] ?? ''));
                                        }
                                        echo sanitize($linkedColorLabel);
                                        ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">&#x2795;</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label">Label</label>
                        <input class="input" name="linked_cable_label" placeholder="e.g. FIB-12 / CAT6-34">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="label">Notes</label>
                        <input class="input" name="linked_notes" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" type="button" onclick="closeLinkModal()">Cancel</button>
                <button class="btn" type="button" onclick="createLink()">Create link</button>
            </div>
        </form>
    </div>
</div>

<div class="idf-modal-backdrop" id="cableColorBackdrop">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Add Cable Color</div>
            <button class="btn btn-sm" type="button" onclick="closeCableColorModal(false)" aria-label="Close">&times;</button>
        </div>
        <div>
            <label class="label" for="cableColorModalName">Color name</label>
            <input class="input" id="cableColorModalName" type="text" placeholder="Type color name (e.g. Yellow)">
        </div>
        <div style="margin-top:10px;">
            <label class="label" for="cableColorModalInput">Hex color</label>
            <div style="display:flex; align-items:center; gap:8px;">
                <input class="input" id="cableColorModalColorPicker" type="color" value="#808080" style="width:56px; min-width:56px; height:40px; padding:4px;">
                <input class="input" id="cableColorModalInput" type="text" placeholder="Type hex color (e.g. #ffff00)">
            </div>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px;">
            <button class="btn btn-sm" type="button" onclick="closeCableColorModal(false)">Cancel</button>
            <button class="btn btn-sm" type="button" onclick="saveCableColorFromModal()">Save</button>
        </div>
    </div>
</div>

<div class="idf-modal-backdrop" id="statusBackdrop">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Add Status</div>
            <button class="btn btn-sm" type="button" onclick="closeStatusModal(false)" aria-label="Close">&times;</button>
        </div>
        <div>
            <label class="label" for="statusModalInput">Status</label>
            <input class="input" id="statusModalInput" type="text" placeholder="Type new status">
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px;">
            <button class="btn btn-sm" type="button" onclick="closeStatusModal(false)">Cancel</button>
            <button class="btn btn-sm" type="button" onclick="saveStatusFromModal()">Save</button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>js/select-add-option.js"></script>
<script>
window.ITM_BASE_URL = <?php echo json_encode(BASE_URL, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const IDF_BASE = '<?php echo BASE_URL; ?>modules/idfs';
const CSRF = window.ITM_CSRF_TOKEN;
const ITM_ADDABLE_SELECT_CONFIG = <?php echo json_encode($itmAddableSelectConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const POSITION_ID = <?php echo (int)$position_id; ?>;
const POSITION_EQUIPMENT_ID = <?php echo (int)$positionLinkedEquipmentId; ?>;
const AUTO_OPEN_EDIT_PORT_ID = <?php echo (int)$open_edit_port_id; ?>;
const AUTO_OPEN_LINK_PORT_ID = <?php echo (int)$open_link_port_id; ?>;
const AUTO_OPEN_EDIT_PORT_NO = <?php echo (int)$open_edit_port_no; ?>;
const AUTO_OPEN_LINK_PORT_NO = <?php echo (int)$open_link_port_no; ?>;
const RETURN_TO = <?php echo json_encode($return_to, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const EMBED_MODE = <?php echo $embed_mode ? 'true' : 'false'; ?>;
const EMBED_MODAL_ONLY = <?php echo $embed_modal_only ? 'true' : 'false'; ?>;
const FIBER_SPEED_OPTIONS = <?php echo json_encode($fiberSpeedOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const RJ45_SPEED_OPTIONS = <?php echo json_encode($rj45SpeedOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const VLAN_OPTIONS = <?php echo json_encode($vlanOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const FIBER_PATCH_OPTIONS = <?php echo json_encode($fiberPatchOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const FIBER_RACK_OPTIONS = <?php echo json_encode($fiberRackOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const POE_OPTIONS = <?php echo json_encode($poeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const PORTS = <?php
$portsMeta = array_map(static function (array $port): array {
    return [
        'id' => (int)($port['id'] ?? 0),
        'port_no' => (int)($port['port_no'] ?? 0),
        'switch_port_id' => isset($port['switch_port_live_id']) ? (int)$port['switch_port_live_id'] : 0,
        'port_type_id' => isset($port['port_type']) ? (int)$port['port_type'] : 0,
        'label' => idf_normalize_label_display($port['label'] ?? ''),
        'port_type_label' => (string)($port['port_type_label'] ?? 'RJ45'),
        'status_id' => isset($port['status_id']) ? (int)$port['status_id'] : 0,
        'vlan_id' => isset($port['effective_vlan_id']) ? (int)$port['effective_vlan_id'] : (isset($port['vlan_id']) ? (int)$port['vlan_id'] : 0),
        'poe_id' => isset($port['effective_poe_id']) ? (int)$port['effective_poe_id'] : (isset($port['poe_id']) ? (int)$port['poe_id'] : 0),
        'rj45_speed_id' => isset($port['effective_rj45_speed_id']) ? (int)$port['effective_rj45_speed_id'] : (isset($port['rj45_speed_id']) ? (int)$port['rj45_speed_id'] : 0),
        'fiber_port_id' => isset($port['effective_fiber_port_id']) ? (int)$port['effective_fiber_port_id'] : 0,
        'fiber_patch_id' => isset($port['effective_fiber_patch_id']) ? (int)$port['effective_fiber_patch_id'] : 0,
        'fiber_rack_id' => isset($port['effective_fiber_rack_id']) ? (int)$port['effective_fiber_rack_id'] : 0,
        'to_idf_id' => isset($port['effective_to_idf_id']) ? (int)$port['effective_to_idf_id'] : 0,
        'to_rack_id' => isset($port['effective_to_rack_id']) ? (int)$port['effective_to_rack_id'] : 0,
        'to_location_id' => isset($port['effective_to_location_id']) ? (int)$port['effective_to_location_id'] : 0,
        'connected_to' => (string)($port['connected_to'] ?? ''),
        'notes' => (string)($port['notes'] ?? ''),
        'link_id' => isset($port['link_id']) ? (int)$port['link_id'] : 0,
        'other_port_id' => isset($port['other_port_id']) ? (int)$port['other_port_id'] : 0,
        'cable_color_id' => isset($port['cable_color_id']) ? (int)$port['cable_color_id'] : 0,
        'cable_color_name' => (string)($port['cable_color_name'] ?? ''),
        'cable_hex_color' => (string)($port['cable_hex_color'] ?? ''),
        'cable_color' => (string)($port['cable_color'] ?? 'Gray'),
        'is_linked' => !empty($port['link_id']),
    ];
}, $ports);
echo json_encode($portsMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>;
function closeEmbeddedDeviceView(eventType) {
    const payloadType = eventType || 'idf_device_embed_close';
    if (window.parent && window.parent !== window && EMBED_MODE) {
        try {
            window.parent.postMessage({type: payloadType}, '*');
            return;
        } catch (e) {
            // fall through to same-window redirect fallback
        }
    }
    if (RETURN_TO) {
        window.location.href = RETURN_TO;
        return;
    }
    window.location.href = 'view.php?id=<?php echo (int)$pos['idf_id']; ?>';
}
const DESTINATION_PORTS = <?php echo json_encode($destinationPorts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let activeStatusSelect = null;
let activeCableColorSelect = null;

function finishInlineMutationOrReload() {
    if (EMBED_MODE && EMBED_MODAL_ONLY) {
        closeEmbeddedDeviceView('idf_device_embed_updated');
        return;
    }
    if (RETURN_TO) {
        const returnUrl = new URL(RETURN_TO, window.location.href);
        returnUrl.searchParams.set('_itm_refresh', String(Date.now()));
        window.location.href = returnUrl.toString();
        return;
    }
    location.reload();
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

function coercePositiveSelectValue(value) {
    const raw = String(value || '').trim();
    if (!raw || raw === '__add_new__') {
        return '';
    }
    const parsed = Number(raw);
    return Number.isFinite(parsed) && parsed > 0 ? String(Math.trunc(parsed)) : '';
}

function normalizeLabelDisplayValue(value) {
    const normalized = String(value || '').trim();
    if (normalized === '' || normalized === '0' || normalized.toLowerCase() === 'null') {
        return '';
    }
    return normalized;
}

function escapeSelectOptionHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function applyAddableSelectConfig(selectEl, configKey) {
    const config = ITM_ADDABLE_SELECT_CONFIG[configKey];
    if (!selectEl || !config) {
        return;
    }
    selectEl.setAttribute('data-addable-select', '1');
    selectEl.setAttribute('data-add-table', config.table || '');
    selectEl.setAttribute('data-add-id-col', config.idCol || 'id');
    selectEl.setAttribute('data-add-label-col', config.labelCol || 'name');
    selectEl.setAttribute('data-add-company-scoped', config.companyScoped || '1');
    if (config.friendly) {
        selectEl.setAttribute('data-add-friendly', config.friendly);
    } else {
        selectEl.removeAttribute('data-add-friendly');
    }
    if (Array.isArray(config.extraFields) && config.extraFields.length) {
        selectEl.setAttribute('data-add-extra-fields', JSON.stringify(config.extraFields));
    } else {
        selectEl.removeAttribute('data-add-extra-fields');
    }
    if (typeof window.itmInitAddableSelects === 'function') {
        window.itmInitAddableSelects(selectEl);
    }
}

function wireIdfDeviceAddableSelects(root) {
    if (typeof window.itmInitAddableSelects === 'function') {
        window.itmInitAddableSelects(root || document);
    }
}

function addableConfigKeyForSpeedField(portTypeLabel) {
    return normalizePortTypeLabel(portTypeLabel) === 'rj45' ? 'rj45_speed' : 'equipment_fiber';
}

function applySelectValue(selectEl, value) {
    if (!selectEl) {
        return;
    }
    const normalized = coercePositiveSelectValue(value);
    if (normalized !== '' && Array.from(selectEl.options).some((option) => option.value === normalized)) {
        selectEl.value = normalized;
        return;
    }
    selectEl.value = '';
}

function applySelectValueOrLabel(selectEl, valueId, labelText) {
    applySelectValue(selectEl, valueId);
    if (!selectEl || selectEl.value !== '') {
        return;
    }
    const normalizedLabel = String(labelText || '').trim().toLowerCase();
    if (normalizedLabel === '') {
        return;
    }
    const matched = Array.from(selectEl.options).find((option) =>
        option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === normalizedLabel
    );
    if (matched) {
        selectEl.value = matched.value;
    }
}

function firstPositivePortId(...candidates) {
    for (let i = 0; i < candidates.length; i++) {
        const normalized = coercePositiveSelectValue(candidates[i]);
        if (normalized !== '') {
            return normalized;
        }
    }
    return '';
}

function portFormControl(form, fieldName) {
    if (!form || !fieldName) {
        return null;
    }
    if (form.elements && typeof form.elements.namedItem === 'function') {
        const named = form.elements.namedItem(fieldName);
        if (named) {
            return named;
        }
    }
    return form.querySelector('[name="' + String(fieldName).replace(/"/g, '\\"') + '"]');
}

function portFormScopedControl(form, typeFieldsContainerId, fieldName) {
    const containerId = String(typeFieldsContainerId || '').trim();
    if (containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            const scoped = container.querySelector('[name="' + String(fieldName).replace(/"/g, '\\"') + '"]');
            if (scoped) {
                return scoped;
            }
        }
    }
    return portFormControl(form, fieldName);
}

function portFormPositiveInt(form, typeFieldsContainerId, fieldName) {
    const control = portFormScopedControl(form, typeFieldsContainerId, fieldName);
    const normalized = coercePositiveSelectValue(control && control.value);
    return normalized ? Number(normalized) : 0;
}

function collectPortEditSeed(portMeta, rowDataset) {
    const row = rowDataset || {};
    const portTypeLabel = (portMeta && portMeta.port_type_label) ? portMeta.port_type_label : (row.portType || 'RJ45');
    const normalizedType = normalizePortTypeLabel(portTypeLabel);
    return {
        label: normalizeLabelDisplayValue((portMeta && portMeta.label) ? portMeta.label : (row.label || '')),
        notes: String((portMeta && portMeta.notes) ? portMeta.notes : (row.notes || '')).trim(),
        connected_to: String(
            (portMeta && portMeta.connected_to) ? portMeta.connected_to : (row.connectedTo || '')
        ).trim(),
        status_id: firstPositivePortId(portMeta && portMeta.status_id, row.statusId),
        vlan_id: firstPositivePortId(portMeta && portMeta.vlan_id, row.vlanId),
        vlan_label: String(row.vlan || ''),
        poe_id: firstPositivePortId(portMeta && portMeta.poe_id, row.poeId),
        poe_label: String(row.poe || ''),
        rj45_speed_id: firstPositivePortId(portMeta && portMeta.rj45_speed_id, row.rj45SpeedId),
        rj45_speed_label: String(row.rj45Speed || ''),
        fiber_port_id: firstPositivePortId(
            portMeta && portMeta.fiber_port_id,
            row.fiberPortId,
            normalizedType === 'sfp' ? row.speedId : ''
        ),
        fiber_speed_label: String(row.speed || ''),
        fiber_patch_id: firstPositivePortId(portMeta && portMeta.fiber_patch_id, row.fiberPatchId),
        fiber_rack_id: firstPositivePortId(portMeta && portMeta.fiber_rack_id, row.fiberRackId),
        port_type_label: portTypeLabel,
        cable_color_id: portMeta && portMeta.cable_color_id ? portMeta.cable_color_id : 0,
        cable_color_name: portMeta ? (portMeta.cable_color_name || portMeta.cable_color || '') : '',
        cable_hex_color: portMeta ? (portMeta.cable_hex_color || '') : '',
    };
}

function applyPortEditTextFields(form, seed) {
    const labelInput = portFormControl(form, 'label');
    const notesInput = portFormControl(form, 'notes');
    const connectedInput = portFormControl(form, 'connected_to');
    if (labelInput) {
        labelInput.value = normalizeLabelDisplayValue(seed.label || '');
    }
    if (notesInput) {
        notesInput.value = String(seed.notes || '');
    }
    if (connectedInput) {
        connectedInput.value = String(seed.connected_to || '');
    }
}

function mergeSwitchPortApiRowIntoPortMeta(portMeta, switchPort) {
    if (!portMeta || !switchPort) {
        return portMeta;
    }
    const merged = Object.assign({}, portMeta);
    const preservedLabel = normalizeLabelDisplayValue(portMeta.label || '');
    const preservedNotes = String(portMeta.notes || '').trim();
    const preservedConnectedTo = String(portMeta.connected_to || '').trim();
    const pick = (targetKey, sourceKey) => {
        const current = Number(merged[targetKey] || 0);
        const incoming = Number(switchPort[sourceKey] || 0);
        if (current <= 0 && incoming > 0) {
            merged[targetKey] = incoming;
        }
    };
    pick('vlan_id', 'equipment_vlan_id');
    pick('rj45_speed_id', 'equipment_rj45_speed_id');
    pick('fiber_port_id', 'equipment_fiber_port_id');
    pick('poe_id', 'equipment_poe_id');
    pick('fiber_patch_id', 'equipment_fiber_patch_id');
    pick('fiber_rack_id', 'equipment_fiber_rack_id');
    pick('to_idf_id', 'equipment_to_idf_id');
    pick('to_rack_id', 'equipment_to_rack_id');
    pick('to_location_id', 'equipment_to_location_id');
    pick('status_id', 'equipment_status_id');
    pick('cable_color_id', 'equipment_color_id');
    // fallbacks when only legacy switch_ports columns are populated (see device.php COALESCE for effective_*)
    if (Number(merged.to_idf_id || 0) <= 0 && Number(switchPort.idf_id || 0) > 0) {
        merged.to_idf_id = Number(switchPort.idf_id);
    }
    if (Number(merged.to_rack_id || 0) <= 0 && Number(switchPort.rack_id || 0) > 0) {
        merged.to_rack_id = Number(switchPort.rack_id);
    }
    if (Number(merged.to_location_id || 0) <= 0 && Number(switchPort.location_id || 0) > 0) {
        merged.to_location_id = Number(switchPort.location_id);
    }
    if (Number(merged.switch_port_id) <= 0 && Number(switchPort.id) > 0) {
        merged.switch_port_id = Number(switchPort.id);
    }
    const switchLabel = normalizeLabelDisplayValue(switchPort.equipment_label || '');
    merged.label = preservedLabel !== '' ? preservedLabel : (switchLabel !== '' ? switchLabel : normalizeLabelDisplayValue(merged.label || ''));
    const switchComments = String(switchPort.equipment_comments || '').trim();
    merged.notes = preservedNotes !== '' ? preservedNotes : (switchComments !== '' ? switchComments : String(merged.notes || '').trim());
    if (preservedConnectedTo !== '') {
        merged.connected_to = preservedConnectedTo;
    }
    const switchColor = String(switchPort.equipment_color || '').trim();
    if (switchColor !== '' && (!merged.cable_color_name || String(merged.cable_color_name).toLowerCase() === 'gray')) {
        merged.cable_color_name = switchColor;
        merged.cable_color = switchColor;
    }
    const switchHex = String(switchPort.equipment_color_hex || '').trim();
    if (switchHex !== '' && !merged.cable_hex_color) {
        merged.cable_hex_color = switchHex;
    }
    return merged;
}

function findSwitchPortMatchForPortMeta(portMeta, switchPorts) {
    if (!portMeta || !Array.isArray(switchPorts) || !switchPorts.length) {
        return null;
    }
    const switchPortId = Number(portMeta.switch_port_id || 0);
    if (switchPortId > 0) {
        const byId = switchPorts.find((row) => Number(row.id) === switchPortId);
        if (byId) {
            return byId;
        }
    }
    const portNo = Number(portMeta.port_no || 0);
    const portType = String(portMeta.port_type_label || 'RJ45');
    const byCompat = switchPorts.find((row) =>
        Number(row.equipment_port) === portNo
        && portsAreLinkCompatible(portType, String(row.equipment_port_type || ''))
    );
    if (byCompat) {
        return byCompat;
    }
    return switchPorts.find((row) => Number(row.equipment_port) === portNo) || null;
}

let switchPortsByEquipmentCache = null;
let switchPortsByEquipmentCacheId = 0;

async function fetchSwitchPortsForPositionEquipment() {
    if (!POSITION_EQUIPMENT_ID) {
        return [];
    }
    if (switchPortsByEquipmentCacheId === Number(POSITION_EQUIPMENT_ID) && Array.isArray(switchPortsByEquipmentCache)) {
        return switchPortsByEquipmentCache;
    }
    const data = await apiPost('switch_ports_by_equipment.php', {
        csrf_token: CSRF,
        equipment_id: Number(POSITION_EQUIPMENT_ID),
    });
    switchPortsByEquipmentCache = Array.isArray(data.ports) ? data.ports : [];
    switchPortsByEquipmentCacheId = Number(POSITION_EQUIPMENT_ID);
    return switchPortsByEquipmentCache;
}

async function hydratePortMetaFromSwitchPorts(portMeta) {
    if (!portMeta) {
        return portMeta;
    }
    const sid = Number(portMeta.switch_port_id || 0);
    if (sid > 0) {
        try {
            const data = await apiPost('switch_port_row.php', {
                csrf_token: CSRF,
                switch_port_id: sid,
            });
            if (data.port) {
                return mergeSwitchPortApiRowIntoPortMeta(portMeta, data.port);
            }
        } catch (err) {
            // Fall through to equipment-scoped fetch below.
        }
    }
    if (!POSITION_EQUIPMENT_ID) {
        return portMeta;
    }
    try {
        const switchPorts = await fetchSwitchPortsForPositionEquipment();
        const match = findSwitchPortMatchForPortMeta(portMeta, switchPorts);
        if (!match) {
            return portMeta;
        }
        return mergeSwitchPortApiRowIntoPortMeta(portMeta, match);
    } catch (err) {
        return portMeta;
    }
}

function upsertPortMetaInPortsList(portMeta) {
    if (!portMeta || !portMeta.id) {
        return;
    }
    const idx = PORTS.findIndex((item) => Number(item.id) === Number(portMeta.id));
    if (idx >= 0) {
        PORTS[idx] = portMeta;
    }
}

async function openPortModal(portId) {
    let portMeta = findPortMetaByRef(portId);
    const resolvedPortId = portMeta ? Number(portMeta.id) : Number(portId);
    const row = document.querySelector(`tr[data-port-id="${resolvedPortId}"]`);
    const form = document.getElementById('portForm');
    const rowData = (row && row.dataset) ? row.dataset : {};

    if (!portMeta) {
        alert('Port not found. If this device has switch_ports rows but no IDF ports yet, click "Regenerate Ports" first.');
        return;
    }

    let editSeed = collectPortEditSeed(portMeta, rowData);
    if (!Number(portMeta.switch_port_id || 0)) {
        portMeta = await hydratePortMetaFromSwitchPorts(portMeta);
    }
    upsertPortMetaInPortsList(portMeta);
    editSeed = collectPortEditSeed(portMeta, rowData);
    editSeed.label = normalizeLabelDisplayValue(editSeed.label || (portMeta && portMeta.label) || rowData.label || '');
    editSeed.notes = String(editSeed.notes || (portMeta && portMeta.notes) || rowData.notes || '').trim();
    editSeed.connected_to = String(editSeed.connected_to || (portMeta && portMeta.connected_to) || rowData.connectedTo || '').trim();

    const portIdInput = portFormControl(form, 'port_id');
    if (portIdInput) {
        portIdInput.value = String(portMeta.id);
    }
    const portTypeSelect = portFormControl(form, 'port_type');
    const requestedPortTypeId = (portMeta && portMeta.port_type_id) ? String(portMeta.port_type_id) : (rowData.portTypeId || '');
    if (portTypeSelect) {
        if (requestedPortTypeId && Array.from(portTypeSelect.options).some((option) => option.value === requestedPortTypeId)) {
            portTypeSelect.value = requestedPortTypeId;
        } else {
            const requestedPortType = ((portMeta && portMeta.port_type_label) || rowData.portType || 'RJ45').trim();
            const portTypeOption = Array.from(portTypeSelect.options).find((option) =>
                option.textContent.trim().toLowerCase() === requestedPortType.toLowerCase()
            );
            portTypeSelect.value = portTypeOption ? portTypeOption.value : (portTypeSelect.value || '');
        }
    }
    const statusSelect = portFormControl(form, 'status');
    const requestedStatusId = editSeed.status_id || (portMeta ? portMeta.status_id : rowData.statusId);
    if (statusSelect) {
        if (requestedStatusId && Array.from(statusSelect.options).some((option) => option.value === String(requestedStatusId))) {
            statusSelect.value = String(requestedStatusId);
        } else {
            const requestedStatus = (rowData.status || 'Unknown').trim();
            const statusOption = Array.from(statusSelect.options).find((option) =>
                option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === requestedStatus.toLowerCase()
            );
            statusSelect.value = statusOption ? statusOption.value : (statusSelect.value || '');
        }
    }
    applyPortEditTextFields(form, editSeed);
    applySelectValueOrLabel(
        portFormControl(form, 'vlan'),
        editSeed.vlan_id || firstPositivePortId(portMeta && portMeta.vlan_id, rowData.vlanId),
        editSeed.vlan_label || rowData.vlan || ''
    );
    const selectedPortTypeLabel = (portMeta && portMeta.port_type_label)
        ? portMeta.port_type_label
        : (editSeed.port_type_label || ((portTypeSelect && portTypeSelect.selectedOptions && portTypeSelect.selectedOptions[0])
            ? portTypeSelect.selectedOptions[0].textContent
            : 'RJ45'));
    const normalizedType = normalizePortTypeLabel(selectedPortTypeLabel);
    const speedSourceId = normalizedType === 'rj45'
        ? firstPositivePortId(editSeed.rj45_speed_id, portMeta && portMeta.rj45_speed_id, rowData.rj45SpeedId)
        : firstPositivePortId(editSeed.fiber_port_id, portMeta && portMeta.fiber_port_id, rowData.fiberPortId, rowData.speedId);
    const speedLabelHint = normalizedType === 'rj45'
        ? (editSeed.rj45_speed_label || rowData.rj45Speed || '')
        : (editSeed.fiber_speed_label || rowData.speed || '');
    rebuildSpeedOptionsForPortType(selectedPortTypeLabel, coercePositiveSelectValue(speedSourceId));
    applySelectValueOrLabel(portFormControl(form, 'speed'), speedSourceId, speedLabelHint);
    applySelectValueOrLabel(
        portFormControl(form, 'poe'),
        firstPositivePortId(editSeed.poe_id, portMeta && portMeta.poe_id, rowData.poeId),
        editSeed.poe_label || rowData.poe || ''
    );
    togglePoeFieldForPortType(selectedPortTypeLabel);
    const destinationSelect = portFormControl(form, 'port_id_b');
    if (destinationSelect) {
        fillDestinationSelect(destinationSelect, portMeta, true);
        destinationSelect.disabled = Boolean(portMeta && portMeta.is_linked);
    }
    const cableColorSelect = portFormControl(form, 'cable_color_id');
    const requestedCableColorId = editSeed.cable_color_id || (portMeta ? (portMeta.cable_color_id || 0) : 0);
    const requestedCableColorName = editSeed.cable_color_name || (portMeta ? ((portMeta.cable_color_name || portMeta.cable_color || '').trim()) : '');
    if (cableColorSelect) {
        if (requestedCableColorId > 0 && Array.from(cableColorSelect.options).some((option) => Number(option.value) === requestedCableColorId)) {
            cableColorSelect.value = String(requestedCableColorId);
        } else {
            const normalizedRequestedCableColor = requestedCableColorName.toLowerCase();
            const matchedCableColorOption = Array.from(cableColorSelect.options).find((option) =>
                option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === normalizedRequestedCableColor
            );
            const grayCableColorOption = Array.from(cableColorSelect.options).find((option) =>
                option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'gray'
            );
            cableColorSelect.value = matchedCableColorOption
                ? matchedCableColorOption.value
                : (grayCableColorOption ? grayCableColorOption.value : '');
        }
    }
    const requestedCableHex = editSeed.cable_hex_color || (portMeta ? (portMeta.cable_hex_color || '') : '');
    updateCableColorSwatch(
        (cableColorSelect && cableColorSelect.value) || requestedCableColorName || requestedCableHex || 'Gray',
        cableColorSelect
    );
    updatePortFormTypePresentation(portMeta);
    applyPortEditTextFields(form, editSeed);
    const fiberPatchSelect = portFormScopedControl(form, 'portTypeSpecificFields', 'fiber_patch_id');
    const fiberRackSelect = portFormScopedControl(form, 'portTypeSpecificFields', 'fiber_rack_id');
    if (fiberPatchSelect) {
        applySelectValueOrLabel(
            fiberPatchSelect,
            firstPositivePortId(editSeed.fiber_patch_id, portMeta && portMeta.fiber_patch_id),
            ''
        );
    }
    if (fiberRackSelect) {
        applySelectValueOrLabel(
            fiberRackSelect,
            firstPositivePortId(editSeed.fiber_rack_id, portMeta && portMeta.fiber_rack_id),
            ''
        );
    }
    wireIdfDeviceAddableSelects(form);

    document.getElementById('portBackdrop').style.display = 'flex';
}

function normalizePortTypeLabel(value) {
    const normalized = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '')
        .replace(/\+/g, 'plus');
    if (normalized.indexOf('sfp') !== -1) {
        return 'sfp';
    }
    return 'rj45';
}

function portTypeLinkFamily(typeLabel) {
    return normalizePortTypeLabel(typeLabel) === 'rj45' ? 'rj45' : 'fiber';
}

function portsAreLinkCompatible(typeLabelA, typeLabelB) {
    return portTypeLinkFamily(typeLabelA) === portTypeLinkFamily(typeLabelB);
}

function portTypeLinkMismatchMessage(typeLabelA, typeLabelB) {
    const displayA = String(typeLabelA || '').trim()
        || (portTypeLinkFamily(typeLabelA) === 'fiber' ? 'SFP' : 'RJ45');
    const displayB = String(typeLabelB || '').trim()
        || (portTypeLinkFamily(typeLabelB) === 'fiber' ? 'SFP' : 'RJ45');
    return `Cannot link ${displayA} to ${displayB}. RJ45 ports can only connect to RJ45 ports on another device. Fiber (SFP) ports can only connect to fiber ports on another device.`;
}

function filterLinkCompatibleDestinations(source, destinations) {
    if (!source) {
        return [];
    }
    const sourceType = String(source.port_type_label || 'RJ45');
    return (Array.isArray(destinations) ? destinations : []).filter((port) =>
        portsAreLinkCompatible(sourceType, String(port.port_type_label || 'RJ45'))
    );
}

function isEligibleCableLinkDestination(port, source) {
    if (!port || !source) {
        return false;
    }
    if (Number(port.id) <= 0 || Number(port.id) === Number(source.id) || port.is_linked) {
        return false;
    }
    if (Number(port.position_id) > 0 && Number(port.position_id) === Number(POSITION_ID)) {
        return false;
    }
    const positionEquipId = Number(POSITION_EQUIPMENT_ID);
    if (positionEquipId > 0 && Number(port.equipment_id) === positionEquipId) {
        return false;
    }
    return Number(port.position_id) > 0;
}

function buildSelectHtml(name, optionsMap, selectedValue, noneLabel, addableConfigKey) {
    const selectedNormalized = coercePositiveSelectValue(selectedValue);
    let html = `<select class="input" name="${escapeSelectOptionHtml(name)}">`;
    html += `<option value="">${escapeSelectOptionHtml(noneLabel || '-- None --')}</option>`;
    Object.keys(optionsMap || {}).forEach((id) => {
        const safeId = String(id);
        const selectedAttr = safeId === selectedNormalized ? ' selected' : '';
        html += `<option value="${escapeSelectOptionHtml(safeId)}"${selectedAttr}>${escapeSelectOptionHtml(optionsMap[id] || '')}</option>`;
    });
    if (selectedNormalized !== '' && !(optionsMap || {})[selectedNormalized]) {
        html += `<option value="${escapeSelectOptionHtml(selectedNormalized)}" selected>Saved value #${escapeSelectOptionHtml(selectedNormalized)}</option>`;
    }
    if (addableConfigKey) {
        html += '<option value="__add_new__">&#x2795;</option>';
    }
    html += '</select>';
    return html;
}

function bindAddableSelectsInContainer(containerEl) {
    if (!containerEl) {
        return;
    }
    const fieldConfigMap = {
        vlan: 'vlan',
        poe: 'equipment_poe',
        rj45_speed_id: 'rj45_speed',
        fiber_port_id: 'equipment_fiber',
        fiber_patch_id: 'equipment_fiber_patch',
        fiber_rack_id: 'equipment_fiber_rack',
    };
    containerEl.querySelectorAll('select.input[name]').forEach((selectEl) => {
        const configKey = fieldConfigMap[selectEl.name] || '';
        if (configKey) {
            applyAddableSelectConfig(selectEl, configKey);
        }
    });
}

function renderTypeSpecificFields(containerId, typeLabel, values, includePrimaryCableField) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const normalizedType = normalizePortTypeLabel(typeLabel);
    const currentValues = values || {};
    const parts = [];
    if (normalizedType === 'rj45') {
        if (includePrimaryCableField) {
            parts.push('<div><label class="label">RJ45 Cable</label>' + buildSelectHtml('rj45_speed_id', RJ45_SPEED_OPTIONS, currentValues.rj45_speed_id || '', '-- None --', 'rj45_speed') + '</div>');
            parts.push('<div><label class="label">PoE</label>' + buildSelectHtml('poe', POE_OPTIONS, currentValues.poe_id || '', '-- None --', 'equipment_poe') + '</div>');
        }
    } else {
        if (includePrimaryCableField) {
            parts.push('<div><label class="label">Fiber Ports</label>' + buildSelectHtml('fiber_port_id', FIBER_SPEED_OPTIONS, currentValues.fiber_port_id || '', '-- None --', 'equipment_fiber') + '</div>');
        }
        parts.push('<div><label class="label">Fiber Patch</label>' + buildSelectHtml('fiber_patch_id', FIBER_PATCH_OPTIONS, currentValues.fiber_patch_id || '', '-- None --', 'equipment_fiber_patch') + '</div>');
        parts.push('<div><label class="label">Fiber Rack</label>' + buildSelectHtml('fiber_rack_id', FIBER_RACK_OPTIONS, currentValues.fiber_rack_id || '', '-- None --', 'equipment_fiber_rack') + '</div>');
    }
    if (!parts.length) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }
    container.style.display = '';
    container.innerHTML = '<div class="idf-grid-2">' + parts.join('') + '</div>';
    bindAddableSelectsInContainer(container);
}

function routingFkPayloadFromPortMeta(portMeta) {
    if (!portMeta) {
        return {to_idf_id: null, to_rack_id: null, to_location_id: null};
    }
    return {
        to_idf_id: Number(portMeta.to_idf_id || 0) > 0 ? Number(portMeta.to_idf_id) : null,
        to_rack_id: Number(portMeta.to_rack_id || 0) > 0 ? Number(portMeta.to_rack_id) : null,
        to_location_id: Number(portMeta.to_location_id || 0) > 0 ? Number(portMeta.to_location_id) : null,
    };
}

function fillDestinationSelect(selectEl, source, allowLinkedCurrent) {
    if (!selectEl || !source) return;
    const currentOtherPortId = Number(source.other_port_id || 0);
    const destinations = sortDestinationPorts(filterLinkCompatibleDestinations(source, DESTINATION_PORTS.filter((p) =>
        (allowLinkedCurrent && Number(p.id) === currentOtherPortId)
            || (isEligibleCableLinkDestination(p, source) && (!p.is_linked || Number(p.id) === currentOtherPortId))
    )));
    selectEl.innerHTML = '<option value="">Select destination port</option>';
    destinations.forEach((port) => {
        const option = document.createElement('option');
        option.value = String(port.id);
        const idfName = port.idf_name ? `IDF ${port.idf_name}` : (port.idf_id ? `IDF #${port.idf_id}` : 'IDF');
        const portType = String(port.port_type_label || 'RJ45').toUpperCase();
        const rawStatus = String(port.status_label || '').trim();
        const statusText = rawStatus !== '' && rawStatus.toLowerCase() !== 'null' ? rawStatus.toUpperCase() : 'UNKNOWN';
        const rawColorName = String(port.color_name || '').trim();
        const colorNameText = rawColorName !== '' && rawColorName.toLowerCase() !== 'null' ? rawColorName : 'Gray';
        const rawColorHex = String(port.color_hex || '').trim();
        const colorHexText = /^#?[0-9a-f]{6}$/i.test(rawColorHex)
            ? (rawColorHex.charAt(0) === '#' ? rawColorHex.toUpperCase() : ('#' + rawColorHex.toUpperCase()))
            : '#808080';
        option.textContent = `${idfName} | Pos ${port.position_no} | ${port.device_name} | Port ${port.port_no} | ${portType} | Status (${statusText}) | ${colorNameText} (${colorHexText})`;
        selectEl.appendChild(option);
    });
    if (!destinations.length) {
        const option = document.createElement('option');
        option.value = '';
        const sourceFamily = portTypeLinkFamily(source.port_type_label || 'RJ45');
        option.textContent = sourceFamily === 'fiber'
            ? 'No available fiber destination ports'
            : 'No available RJ45 destination ports';
        selectEl.appendChild(option);
    }
    if (currentOtherPortId > 0) {
        selectEl.value = String(currentOtherPortId);
    }
}

function updatePortFormTypePresentation(portMeta) {
    const portTypeLabel = portMeta ? (portMeta.port_type_label || 'RJ45') : 'RJ45';
    const normalizedType = normalizePortTypeLabel(portTypeLabel);
    const speedLabelEl = document.getElementById('portSpeedFieldLabel');
    const patchLabelEl = document.getElementById('portPatchPortLabel');
    const commentsLabelEl = document.getElementById('portCommentsLabel');
    if (speedLabelEl) speedLabelEl.textContent = normalizedType === 'rj45' ? 'RJ45 Speed' : 'Fiber Speed';
    if (patchLabelEl) patchLabelEl.textContent = 'Label';
    if (commentsLabelEl) commentsLabelEl.textContent = 'Notes';
    renderTypeSpecificFields('portTypeSpecificFields', portTypeLabel, portMeta || {}, false);
}

function updateLinkFormTypePresentation(portMeta) {
    const portTypeLabel = portMeta ? (portMeta.port_type_label || 'RJ45') : 'RJ45';
    const patchLabelEl = document.getElementById('linkPatchPortLabel');
    const commentsLabelEl = document.getElementById('linkCommentsLabel');
    if (patchLabelEl) patchLabelEl.textContent = 'Label';
    if (commentsLabelEl) commentsLabelEl.textContent = 'Notes';
    renderTypeSpecificFields('linkTypeSpecificFields', portTypeLabel, portMeta || {}, true);
}

function rebuildSpeedOptionsForPortType(portTypeLabel, selectedValue) {
    const form = document.getElementById('portForm');
    const speedSelect = portFormControl(form, 'speed');
    if (!speedSelect) {
        return;
    }
    const normalizedType = normalizePortTypeLabel(portTypeLabel);
    const sourceMap = normalizedType === 'rj45' ? RJ45_SPEED_OPTIONS : FIBER_SPEED_OPTIONS;
    const desiredValueRaw = String(selectedValue || '').trim();
    const desiredValueNumber = Number(desiredValueRaw);
    const desiredValue = Number.isFinite(desiredValueNumber) && desiredValueNumber > 0
        ? String(Math.trunc(desiredValueNumber))
        : '';

    speedSelect.innerHTML = '';
    const noneOption = document.createElement('option');
    noneOption.value = '';
    noneOption.textContent = '-- None --';
    speedSelect.appendChild(noneOption);

    Object.keys(sourceMap || {}).forEach((id) => {
        const option = document.createElement('option');
        option.value = String(id);
        option.textContent = String(sourceMap[id] || '').trim();
        speedSelect.appendChild(option);
    });

    if (desiredValue !== '' && !Array.from(speedSelect.options).some((option) => option.value === desiredValue)) {
        const fallbackOption = document.createElement('option');
        fallbackOption.value = desiredValue;
        fallbackOption.textContent = `Saved speed #${desiredValue}`;
        speedSelect.appendChild(fallbackOption);
    }

    const addOption = document.createElement('option');
    addOption.value = '__add_new__';
    addOption.textContent = '➕';
    speedSelect.appendChild(addOption);

    speedSelect.value = desiredValue;
    speedSelect.dataset.itmAddableBound = '';
    applyAddableSelectConfig(speedSelect, addableConfigKeyForSpeedField(portTypeLabel));
}

function togglePoeFieldForPortType(portTypeLabel) {
    const form = document.getElementById('portForm');
    const poeWrap = document.getElementById('portPoeField');
    if (!form || !form.poe || !poeWrap) return;
    const normalizedType = normalizePortTypeLabel(portTypeLabel);
    const showPoeField = normalizedType === 'rj45';
    poeWrap.style.display = showPoeField ? '' : 'none';
    form.poe.disabled = !showPoeField;
    if (!showPoeField) {
        form.poe.value = '';
    }
}

function closePortModal(shouldCloseHost) {
    document.getElementById('portBackdrop').style.display = 'none';
    if (EMBED_MODE && EMBED_MODAL_ONLY && shouldCloseHost !== false) {
        closeEmbeddedDeviceView('idf_device_embed_close');
    }
}

function savePort() {
    const f = document.getElementById('portForm');
    const portIdInput = portFormControl(f, 'port_id');
    const labelInput = portFormControl(f, 'label');
    const connectedInput = portFormControl(f, 'connected_to');
    const notesInput = portFormControl(f, 'notes');
    const portTypeSelect = portFormControl(f, 'port_type');
    const sourcePort = findPortMetaByRef(portIdInput ? portIdInput.value : '') || null;
    const normalizedStatus = getNormalizedStatusValue(f);
    if (!normalizedStatus) {
        alert('Please enter a status value.');
        return;
    }
    const selectedPortTypeLabel = (portTypeSelect && portTypeSelect.selectedOptions && portTypeSelect.selectedOptions[0])
        ? portTypeSelect.selectedOptions[0].textContent
        : '';
    const normalizedPortType = normalizePortTypeLabel(selectedPortTypeLabel);
    const routingPreserve = routingFkPayloadFromPortMeta(sourcePort);
    const payload = {
        csrf_token: CSRF,
        port_id: Number(portIdInput ? portIdInput.value : 0),
        port_type_id: Number(portTypeSelect ? portTypeSelect.value : 0),
        port_type_label: selectedPortTypeLabel.trim(),
        label: normalizeLabelDisplayValue(labelInput ? labelInput.value : ''),
        status_id: Number(normalizedStatus),
        connected_to: String(connectedInput ? connectedInput.value : '').trim(),
        vlan_id: (() => {
            const vlanValue = coercePositiveSelectValue(f.vlan && f.vlan.value);
            return vlanValue ? Number(vlanValue) : null;
        })(),
        speed_id: (() => {
            const speedValue = coercePositiveSelectValue(f.speed && f.speed.value);
            return speedValue ? Number(speedValue) : null;
        })(),
        poe_id: normalizedPortType === 'rj45'
            ? (() => {
                const poeValue = coercePositiveSelectValue(f.poe && f.poe.value);
                return poeValue ? Number(poeValue) : null;
            })()
            : null,
        notes: String(notesInput ? notesInput.value : '').trim(),
        cable_color_id: (f.cable_color_id.value && f.cable_color_id.value !== '__add_new__')
            ? Number(f.cable_color_id.value)
            : null,
        rj45_speed_id: normalizedPortType === 'rj45'
            ? (() => {
                const speedValue = coercePositiveSelectValue(f.speed && f.speed.value);
                if (speedValue) {
                    return Number(speedValue);
                }
                const dedicatedValue = coercePositiveSelectValue(f.rj45_speed_id && f.rj45_speed_id.value);
                return dedicatedValue ? Number(dedicatedValue) : null;
            })()
            : null,
        fiber_port_id: normalizedPortType !== 'rj45'
            ? (() => {
                const speedValue = coercePositiveSelectValue(f.speed && f.speed.value);
                if (speedValue) {
                    return Number(speedValue);
                }
                const dedicatedValue = portFormPositiveInt(f, 'portTypeSpecificFields', 'fiber_port_id');
                return dedicatedValue > 0 ? dedicatedValue : 0;
            })()
            : 0,
        fiber_patch_id: normalizedPortType !== 'rj45'
            ? portFormPositiveInt(f, 'portTypeSpecificFields', 'fiber_patch_id')
            : 0,
        fiber_rack_id: normalizedPortType !== 'rj45'
            ? portFormPositiveInt(f, 'portTypeSpecificFields', 'fiber_rack_id')
            : 0,
        ...routingPreserve,
    };
    const destinationPortId = f.port_id_b && f.port_id_b.value ? Number(f.port_id_b.value) : 0;
    if (sourcePort && !sourcePort.is_linked && destinationPortId > 0) {
        const destinationPort = DESTINATION_PORTS.find((port) => Number(port.id) === destinationPortId);
        if (destinationPort && !portsAreLinkCompatible(
            String(sourcePort.port_type_label || 'RJ45'),
            String(destinationPort.port_type_label || 'RJ45')
        )) {
            alert(portTypeLinkMismatchMessage(
                String(sourcePort.port_type_label || 'RJ45'),
                String(destinationPort.port_type_label || 'RJ45')
            ));
            return;
        }
    }

    apiPost('port_update.php', payload)
        .then(() => {
            if (sourcePort && !sourcePort.is_linked && destinationPortId > 0) {
                return apiPost('link_create.php', {
                    csrf_token: CSRF,
                    port_id_a: Number(f.port_id.value),
                    port_id_b: destinationPortId,
                    cable_color_id: payload.cable_color_id,
                    cable_label: payload.label,
                    notes: payload.notes,
                    status_id: payload.status_id,
                    rj45_speed_id: payload.rj45_speed_id,
                    fiber_port_id: payload.fiber_port_id,
                    fiber_patch_id: payload.fiber_patch_id,
                    fiber_rack_id: payload.fiber_rack_id,
                    to_idf_id: payload.to_idf_id,
                    to_rack_id: payload.to_rack_id,
                    to_location_id: payload.to_location_id,
                    vlan_id: payload.vlan_id,
                    poe_id: payload.poe_id
                });
            }
            return null;
        })
        .then(() => finishInlineMutationOrReload())
        .catch(err => (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(err.message) : alert(err.message)));
}

function findPortMetaByRef(portRef) {
    const ref = Number(portRef);
    if (!ref) {
        return null;
    }
    let port = PORTS.find((item) => Number(item.id) === ref);
    if (port) {
        return port;
    }
    port = PORTS.find((item) => Number(item.switch_port_id) === ref);
    if (port) {
        return port;
    }
    return PORTS.find((item) => Number(item.port_no) === ref) || null;
}

function onPortClick(portId) {
    const port = findPortMetaByRef(portId);
    if (!port) {
        alert('Port not found. Use "Regenerate Ports" on this device if switch ports exist but IDF ports are missing.');
        return;
    }
    if (port.is_linked || Number(port.link_id) > 0) {
        openPortModal(port.id);
        return;
    }
    void openLinkModal(port.id);
}

function sortDestinationPorts(ports) {
    let items = Array.isArray(ports) ? ports.slice() : [];

    // Filter out ports on the same device to prevent loop error message after submission
    items = items.filter(p => Number(p.position_id) !== Number(POSITION_ID));

    items.sort((a, b) => {
        const idfA = String((a && a.idf_name) || '').toLowerCase();
        const idfB = String((b && b.idf_name) || '').toLowerCase();
        if (idfA !== idfB) return idfA < idfB ? -1 : 1;

        const posA = Number((a && a.position_no) || 0);
        const posB = Number((b && b.position_no) || 0);
        if (posA !== posB) return posA - posB;

        const devA = String((a && a.device_name) || '').toLowerCase();
        const devB = String((b && b.device_name) || '').toLowerCase();
        if (devA !== devB) return devA < devB ? -1 : 1;

        // Sort RJ45 before SFP
        const typeA = String((a && a.port_type_label) || 'RJ45').toUpperCase();
        const typeB = String((b && b.port_type_label) || 'RJ45').toUpperCase();
        const isSfpA = typeA.includes('SFP');
        const isSfpB = typeB.includes('SFP');
        if (isSfpA !== isSfpB) return isSfpA ? 1 : -1;

        const portA = Number((a && a.port_no) || 0);
        const portB = Number((b && b.port_no) || 0);
        if (portA !== portB) return portA - portB;

        return Number((a && a.id) || 0) - Number((b && b.id) || 0);
    });
    return items;
}

function onPortDotClick(portElement) {
    const portNode = portElement && portElement.dataset ? portElement : null;
    if (!portNode) {
        return;
    }
    const portId = Number(portNode.dataset.portId || 0);
    const portNo = Number(portNode.dataset.portNumber || 0);
    if (portId > 0) {
        onPortClick(portId);
    } else if (portNo > 0) {
        const port = PORTS.find((item) => Number(item.port_no) === portNo);
        if (port) {
            onPortClick(port.id);
        } else {
            alert('Port not found. Click "Regenerate Ports" if switch_ports exist but IDF ports are missing.');
        }
    }
}

function syncPortsFromEquipment() {
    return apiPost('ports_sync.php', {csrf_token: CSRF, position_id: POSITION_ID});
}

function regeneratePorts() {
    if (!confirm('Regenerate ports? This will DELETE and recreate ports 1..port_count.')) return;
    apiPost('ports_regen.php', {csrf_token: CSRF, position_id: POSITION_ID})
        .then(() => finishInlineMutationOrReload())
        .catch(err => (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(err.message) : alert(err.message)));
}

function idfPortsExportExcel() {
    if (typeof XLSX === 'undefined') { alert('XLSX library not loaded.'); return; }
    const table = document.getElementById('portsExportTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Ports'});
    XLSX.writeFile(wb, `ports-position-${POSITION_ID}.xlsx`);
}

async function openLinkModal(portId) {
    let source = findPortMetaByRef(portId);
    if (source) {
        source = await hydratePortMetaFromSwitchPorts(source);
        upsertPortMetaInPortsList(source);
    }
    if (!source) {
        try {
            await syncPortsFromEquipment();
            window.location.reload();
            return;
        } catch (err) {
            alert(String(err.message || err) + ' Click "Regenerate Ports" if switch_ports exist but IDF ports are still missing.');
            return;
        }
    }
    if (source.is_linked) {
        // Why: when a link already exists users should land in the edit flow directly instead of a dead-end alert.
        openPortModal(source.id);
        return;
    }
    const f = document.getElementById('linkForm');
    const destinationSelect = f.port_id_b;
    const destinations = sortDestinationPorts(filterLinkCompatibleDestinations(source, DESTINATION_PORTS.filter((p) =>
        isEligibleCableLinkDestination(p, source)
    )));

    destinationSelect.innerHTML = '<option value="">Select destination port</option>';
    destinations.forEach((port) => {
        const option = document.createElement('option');
        option.value = String(port.id);
        const idfName = port.idf_name ? `IDF ${port.idf_name}` : (port.idf_id ? `IDF #${port.idf_id}` : 'IDF');
        const rawStatus = String(port.status_label || '').trim();
        const statusText = rawStatus !== '' && rawStatus.toLowerCase() !== 'null'
            ? rawStatus.toUpperCase()
            : 'UNKNOWN';
        const rawColorName = String(port.color_name || '').trim();
        const colorNameText = rawColorName !== '' && rawColorName.toLowerCase() !== 'null'
            ? rawColorName
            : 'Gray';
        const rawColorHex = String(port.color_hex || '').trim();
        const colorHexText = /^#?[0-9a-f]{6}$/i.test(rawColorHex)
            ? (rawColorHex.charAt(0) === '#' ? rawColorHex.toUpperCase() : ('#' + rawColorHex.toUpperCase()))
            : '#808080';
        const portType = String(port.port_type_label || 'RJ45').toUpperCase();
        option.textContent = `${idfName} | Pos ${port.position_no} | ${port.device_name} | ${portType} | Port ${port.port_no} | Status (${statusText}) | ${colorNameText} (${colorHexText})`;
        destinationSelect.appendChild(option);
    });
    if (!destinations.length) {
        const option = document.createElement('option');
        option.value = '';
        option.disabled = true;
        const sourceFamily = portTypeLinkFamily(source.port_type_label || 'RJ45');
        const compatibleOnOtherEquipment = DESTINATION_PORTS.filter((p) =>
            isEligibleCableLinkDestination(p, source)
            && portsAreLinkCompatible(source.port_type_label || 'RJ45', p.port_type_label || 'RJ45')
        );
        option.textContent = compatibleOnOtherEquipment.length
            ? 'All matching ports on other equipment are already linked'
            : (sourceFamily === 'fiber'
                ? 'No available fiber ports on other equipment'
                : 'No available RJ45 ports on other equipment');
        destinationSelect.appendChild(option);
    }

    f.port_id_a.value = String(source.id);
    const rawSourceLabel = String(source.label || '').trim();
    const showSourceLabel = rawSourceLabel !== '' && rawSourceLabel !== '0' && rawSourceLabel.toLowerCase() !== 'null';
    const sourcePortType = String(source.port_type_label || 'RJ45').toUpperCase();
    f.source_display.value = `${sourcePortType} Port ${source.port_no}${showSourceLabel ? ` | ${rawSourceLabel}` : ''}`;
    const sourceCableColorId = Number(source.cable_color_id || 0);
    const sourceCableColorName = String(source.cable_color_name || source.cable_color || '').trim();
    if (sourceCableColorId > 0 && Array.from(f.cable_color_id.options).some((option) => Number(option.value) === sourceCableColorId)) {
        f.cable_color_id.value = String(sourceCableColorId);
    } else if (sourceCableColorName !== '') {
        const matchedSourceCableColor = Array.from(f.cable_color_id.options).find((option) =>
            option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === sourceCableColorName.toLowerCase()
        );
        f.cable_color_id.value = matchedSourceCableColor ? matchedSourceCableColor.value : '';
    } else {
        const grayCableColorOption = Array.from(f.cable_color_id.options).find((option) =>
            option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'gray'
        );
        f.cable_color_id.value = grayCableColorOption ? grayCableColorOption.value : '';
    }
    if (f.linked_cable_color_id) {
        f.linked_cable_color_id.value = f.cable_color_id.value || '';
        f.linked_cable_color_id.dataset.previousValue = f.linked_cable_color_id.value || '';
        updateCableColorSwatch(source.cable_hex_color || sourceCableColorName || 'Gray', f.linked_cable_color_id);
    }
    f.cable_label.value = normalizeLabelDisplayValue(source.label || '');
    f.notes.value = source.notes ? String(source.notes).trim() : '';
    const sourceStatusId = Number(source.status_id || 0);
    if (sourceStatusId > 0 && Array.from(f.status.options).some((option) => Number(option.value) === sourceStatusId)) {
        f.status.value = String(sourceStatusId);
    } else {
        const unknownStatusOption = Array.from(f.status.options).find((option) =>
            option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'unknown'
        );
        f.status.value = unknownStatusOption ? unknownStatusOption.value : (f.status.value || '');
    }
    if (f.vlan) {
        applySelectValue(f.vlan, source.vlan_id);
    }
    f.equipment_id.value = '';
    f.switch_port_id.innerHTML = '<option value="">Select equipment first</option>';
    f.switch_port_id.disabled = true;
    toggleLinkedEquipmentFields(false);
    updateCableColorSwatch(
        source.cable_hex_color || sourceCableColorName || f.cable_color_id.value || 'Gray',
        f.cable_color_id
    );
    destinationSelect.value = '';
    updateLinkFormTypePresentation(source);
    wireIdfDeviceAddableSelects(f);
    document.getElementById('linkBackdrop').style.display = 'flex';
}

function closeLinkModal(shouldCloseHost) {
    document.getElementById('linkBackdrop').style.display = 'none';
    if (EMBED_MODE && EMBED_MODAL_ONLY && shouldCloseHost !== false) {
        closeEmbeddedDeviceView('idf_device_embed_close');
    }
}

function createLink() {
    const f = document.getElementById('linkForm');
    const linkedMode = Boolean(f.equipment_id.value && f.switch_port_id.value);
    let destinationPortId = f.port_id_b.value ? Number(f.port_id_b.value) : 0;
    const hasExplicitDestinationSelection = destinationPortId > 0;

    if (linkedMode) {
        const linkedPortRaw = (f.linked_equipment_port.value || '').trim();
        const linkedPortNo = Number(linkedPortRaw);
        const extractedLinkedPortMatch = linkedPortRaw.match(/(\d+)(?!.*\d)/);
        const normalizedLinkedPortNo = (!Number.isNaN(linkedPortNo) && linkedPortNo > 0)
            ? linkedPortNo
            : (extractedLinkedPortMatch ? Number(extractedLinkedPortMatch[1]) : NaN);

        if (!normalizedLinkedPortNo || Number.isNaN(normalizedLinkedPortNo)) {
            alert('Unable to determine destination port from selected equipment port.');
            return;
        }
        const sourcePortForLink = findPortMetaByRef(f.port_id_a.value);
        const selectedEquipmentId = Number(f.equipment_id.value);
        const allAvailableDestinationPorts = filterLinkCompatibleDestinations(sourcePortForLink, DESTINATION_PORTS.filter((port) =>
            Number(port.id) !== Number(f.port_id_a.value)
            && !port.is_linked
        ));
        const allDestinationPorts = filterLinkCompatibleDestinations(sourcePortForLink, DESTINATION_PORTS.filter((port) =>
            Number(port.id) !== Number(f.port_id_a.value)
        ));
        const normalizedAvailableDestinationPorts = allAvailableDestinationPorts.length
            ? allAvailableDestinationPorts
            : allDestinationPorts;
        const selectedEquipmentPorts = normalizedAvailableDestinationPorts.filter((port) => Number(port.equipment_id) === selectedEquipmentId);
        const candidateDestinationPorts = selectedEquipmentPorts.length ? selectedEquipmentPorts : normalizedAvailableDestinationPorts;
        const matchingPort = candidateDestinationPorts.find((port) => Number(port.port_no) === normalizedLinkedPortNo);

        if (!candidateDestinationPorts.length && !hasExplicitDestinationSelection) {
            alert('No available destination ports were found.');
            return;
        }
        let preselectedDestination = candidateDestinationPorts.find((port) => Number(port.id) === destinationPortId);
        if (!preselectedDestination && hasExplicitDestinationSelection) {
            preselectedDestination = allAvailableDestinationPorts.find((port) => Number(port.id) === destinationPortId);
        }
        if (!preselectedDestination && hasExplicitDestinationSelection) {
            preselectedDestination = allDestinationPorts.find((port) => Number(port.id) === destinationPortId);
        }
        if (hasExplicitDestinationSelection) {
            if (!preselectedDestination) {
                alert('The selected destination port is no longer available. Please choose another destination port.');
                return;
            }
        } else if (matchingPort) {
            destinationPortId = Number(matchingPort.id);
        } else if (candidateDestinationPorts.length === 1) {
            destinationPortId = Number(candidateDestinationPorts[0].id);
        } else {
            destinationPortId = Number(candidateDestinationPorts[0].id);
        }
    }

    if (!destinationPortId) {
        alert('Please choose a destination port.');
        return;
    }

    const sourcePort = findPortMetaByRef(f.port_id_a.value);
    const selectedDestinationPort = DESTINATION_PORTS.find((port) => Number(port.id) === Number(destinationPortId));
    if (sourcePort && selectedDestinationPort && !portsAreLinkCompatible(
        String(sourcePort.port_type_label || 'RJ45'),
        String(selectedDestinationPort.port_type_label || 'RJ45')
    )) {
        alert(portTypeLinkMismatchMessage(
            String(sourcePort.port_type_label || 'RJ45'),
            String(selectedDestinationPort.port_type_label || 'RJ45')
        ));
        return;
    }

    if (linkedMode && sourcePort) {
        const selectedSwitchOption = f.switch_port_id.options[f.switch_port_id.selectedIndex];
        if (selectedSwitchOption && selectedSwitchOption.dataset.portJson) {
            const switchPort = JSON.parse(selectedSwitchOption.dataset.portJson);
            const switchPortType = String(switchPort.equipment_port_type || '');
            if (!portsAreLinkCompatible(String(sourcePort.port_type_label || 'RJ45'), switchPortType)) {
                alert(portTypeLinkMismatchMessage(String(sourcePort.port_type_label || 'RJ45'), switchPortType));
                return;
            }
        }
    }

    const activeCableColorSelect = (linkedMode && f.linked_cable_color_id)
        ? f.linked_cable_color_id
        : f.cable_color_id;
    const cableColorId = (activeCableColorSelect && activeCableColorSelect.value && activeCableColorSelect.value !== '__add_new__')
        ? Number(activeCableColorSelect.value)
        : null;
    const selectedCableColorOption = (activeCableColorSelect && activeCableColorSelect.selectedOptions && activeCableColorSelect.selectedOptions[0])
        ? activeCableColorSelect.selectedOptions[0]
        : null;
    const selectedCableColorName = selectedCableColorOption
        ? String(selectedCableColorOption.textContent || '').trim()
        : '';
    const selectedCableColorHex = selectedCableColorOption
        ? String((selectedCableColorOption.dataset && selectedCableColorOption.dataset.hex) || '').trim()
        : '';
    const cableLabel = linkedMode
        ? normalizeLabelDisplayValue(f.linked_cable_label.value)
        : normalizeLabelDisplayValue(f.cable_label.value);
    const notes = linkedMode ? f.linked_notes.value.trim() : f.notes.value.trim();
    const linkedCableColorName = selectedCableColorName;
    const linkedCableColorHex = selectedCableColorHex;
    const linkedDestinationPort = selectedDestinationPort ? String(selectedDestinationPort.port_no || '') : '';

    const payload = {
        csrf_token: CSRF,
        port_id_a: Number(f.port_id_a.value),
        port_id_b: destinationPortId,
        equipment_id: f.equipment_id.value ? Number(f.equipment_id.value) : null,
        switch_port_id: f.switch_port_id.value ? Number(f.switch_port_id.value) : null,
        cable_color_id: cableColorId,
        cable_color_name: linkedCableColorName,
        cable_color_hex: linkedCableColorHex,
        cable_label: cableLabel,
        notes,
        status_id: Number(getNormalizedStatusValue(f) || 0),
        rj45_speed_id: (() => {
            const speedValue = coercePositiveSelectValue(f.rj45_speed_id && f.rj45_speed_id.value);
            return speedValue ? Number(speedValue) : null;
        })(),
        fiber_port_id: portFormPositiveInt(f, 'linkTypeSpecificFields', 'fiber_port_id'),
        fiber_patch_id: portFormPositiveInt(f, 'linkTypeSpecificFields', 'fiber_patch_id'),
        fiber_rack_id: portFormPositiveInt(f, 'linkTypeSpecificFields', 'fiber_rack_id'),
        ...routingFkPayloadFromPortMeta(sourcePort),
        linked_equipment_port: linkedMode ? f.linked_equipment_port.value.trim() : '',
        linked_destination_port: linkedMode ? linkedDestinationPort : '',
        linked_cable_color: linkedCableColorName,
        linked_cable_color_hex: linkedCableColorHex,
        vlan_id: (() => {
            const vlanValue = coercePositiveSelectValue(f.vlan && f.vlan.value);
            return vlanValue ? Number(vlanValue) : null;
        })(),
        poe_id: (() => {
            const poeValue = coercePositiveSelectValue(f.poe && f.poe.value);
            return poeValue ? Number(poeValue) : null;
        })(),
    };

    apiPost('link_create.php', payload)
        .then(() => finishInlineMutationOrReload())
        .catch(err => {
            const message = String(err.message || '');
            if (message.toLowerCase().includes('already linked')) {
                closeLinkModal(false);
                openPortModal(payload.port_id_a);
                return;
            }
            (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(message) : alert(message));
        });
}

function formatSwitchPortOption(port) {
    const equipmentName = (port.equipment_name || '').trim();
    const equipmentHostname = (port.equipment_hostname || '').trim();
    const hostname = (equipmentName && equipmentHostname)
        ? `${equipmentName} | ${equipmentHostname}`
        : (equipmentName || equipmentHostname || '-');
    const portType = String(port.equipment_port_type || '-').toUpperCase();
    const portNumber = port.equipment_port || '-';
    const vlanText = port.equipment_vlan_name
        ? `${port.equipment_vlan_name}${port.equipment_vlan_id ? ` (#${port.equipment_vlan_id})` : ''}`
        : '-';
    const label = port.equipment_label || '-';
    const comments = port.equipment_comments || '-';
    const statusText = port.equipment_status
        ? `${port.equipment_status}${port.equipment_status_id ? ` (#${port.equipment_status_id})` : ''}`
        : '-';
    const colorText = port.equipment_color
        ? `${port.equipment_color}${port.equipment_color_id ? ` (#${port.equipment_color_id})` : ''}`
        : '-';

    return `EQ ${port.equipment_id} | Host: ${hostname} | Type: ${portType} | Port: ${portNumber} | VLAN: ${vlanText} | Label: ${label} | Notes: ${comments} | Status: ${statusText} | Color: ${colorText}`;
}

async function loadEquipmentPorts(equipmentId) {
    const f = document.getElementById('linkForm');
    const select = f.switch_port_id;

    if (!equipmentId) {
        select.innerHTML = '<option value="">Select equipment first</option>';
        select.disabled = true;
        resetLinkedEquipmentPortField(f);
        return;
    }

    select.disabled = true;
    select.innerHTML = '<option value="">Loading...</option>';

    const sourcePort = findPortMetaByRef(f.port_id_a.value);
    const sourcePortType = sourcePort ? String(sourcePort.port_type_label || 'RJ45') : 'RJ45';

    try {
        const data = await apiPost('switch_ports_by_equipment.php', {
            csrf_token: CSRF,
            equipment_id: Number(equipmentId),
        });

        select.innerHTML = '<option value="">-- None --</option>';
        const compatiblePorts = (data.ports || []).filter((port) =>
            portsAreLinkCompatible(sourcePortType, String(port.equipment_port_type || ''))
        );
        compatiblePorts.forEach((port) => {
            const option = document.createElement('option');
            option.value = String(port.id);
            option.textContent = formatSwitchPortOption(port);
            option.dataset.portJson = JSON.stringify(port);
            select.appendChild(option);
        });
        if (!compatiblePorts.length) {
            const option = document.createElement('option');
            option.value = '';
            const sourceFamily = portTypeLinkFamily(sourcePortType);
            option.textContent = sourceFamily === 'fiber'
                ? 'No compatible fiber equipment ports'
                : 'No compatible RJ45 equipment ports';
            select.appendChild(option);
        }
        select.disabled = false;
        resetLinkedEquipmentPortField(f);
        toggleLinkedEquipmentFields(false);
    } catch (err) {
        select.innerHTML = '<option value="">Failed to load equipment ports</option>';
        select.disabled = true;
        (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(err.message) : alert(err.message));
    }
}

function normalizeColorToHex(colorValue) {
    const probe = document.createElement('div');
    probe.style.color = colorValue || 'Gray';
    document.body.appendChild(probe);
    const normalized = getComputedStyle(probe).color;
    document.body.removeChild(probe);
    const match = normalized.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/i);
    if (!match) return '#808080';
    return `#${[match[1], match[2], match[3]].map((part) => Number(part).toString(16).padStart(2, '0')).join('')}`;
}

function deriveColorNameFromHex(hexValue) {
    const normalized = (hexValue || '').trim().toUpperCase();
    const hexMatch = normalized.match(/^#([0-9A-F]{6})$/);
    if (!hexMatch) return '';

    const raw = hexMatch[1];
    const r = parseInt(raw.slice(0, 2), 16) / 255;
    const g = parseInt(raw.slice(2, 4), 16) / 255;
    const b = parseInt(raw.slice(4, 6), 16) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;
    const lightness = (max + min) / 2;
    let saturation = 0;
    if (delta > 0) {
        saturation = delta / (1 - Math.abs((2 * lightness) - 1));
    }

    if (saturation < 0.12) {
        if (lightness < 0.12) return 'Black';
        if (lightness > 0.9) return 'White';
        return 'Gray';
    }

    let hue = 0;
    if (delta > 0) {
        if (max === r) {
            hue = (((g - b) / delta) % 6);
        } else if (max === g) {
            hue = ((b - r) / delta) + 2;
        } else {
            hue = ((r - g) / delta) + 4;
        }
        hue *= 60;
        if (hue < 0) hue += 360;
    }

    let baseColor = 'Color';
    if (hue < 15 || hue >= 345) {
        baseColor = 'Red';
    } else if (hue < 45) {
        baseColor = 'Orange';
    } else if (hue < 70) {
        baseColor = 'Yellow';
    } else if (hue < 165) {
        baseColor = 'Green';
    } else if (hue < 200) {
        baseColor = 'Cyan';
    } else if (hue < 255) {
        baseColor = 'Blue';
    } else if (hue < 290) {
        baseColor = 'Purple';
    } else {
        baseColor = 'Pink';
    }

    if (lightness >= 0.72) return `Light ${baseColor}`;
    if (lightness <= 0.32) return `Dark ${baseColor}`;
    return baseColor;
}

function syncCableColorNameFromHex() {
    const nameInput = document.getElementById('cableColorModalName');
    const hexInput = document.getElementById('cableColorModalInput');
    if (!nameInput || !hexInput) return;
    const hasManualName = nameInput.dataset.manualName === '1';
    if (hasManualName && nameInput.value.trim() !== '') return;
    const derivedName = deriveColorNameFromHex(normalizeColorToHex(hexInput.value || '#ffff00'));
    if (derivedName) {
        nameInput.value = derivedName;
    }
}

function updateCableColorSwatch(colorValue, cableColorSelect = null) {
    const swatchId = (cableColorSelect && cableColorSelect.dataset && cableColorSelect.dataset.swatchId)
        ? cableColorSelect.dataset.swatchId
        : 'cableColorSwatch';
    const swatch = document.getElementById(swatchId);
    if (!swatch) return;
    const selectedOption = (cableColorSelect && cableColorSelect.selectedOptions && cableColorSelect.selectedOptions[0])
        ? cableColorSelect.selectedOptions[0]
        : null;
    const optionHex = (selectedOption && selectedOption.dataset && selectedOption.dataset.hex)
        ? selectedOption.dataset.hex
        : '';
    const swatchColor = (optionHex || colorValue || 'Gray').trim();
    swatch.style.backgroundColor = swatchColor || 'Gray';
}

function toggleLinkedEquipmentFields(isLinked) {
    const f = document.getElementById('linkForm');
    const linkedFields = document.getElementById('linkedEquipmentFields');
    if (linkedFields) {
        linkedFields.style.display = isLinked ? 'block' : 'none';
    }
    if (!isLinked) {
        resetLinkedEquipmentPortField(f);
    }
    document.querySelectorAll('[data-link-default-field]').forEach((field) => {
        const keepVisibleWhenLinked = field.getAttribute('data-link-keep-visible-when-linked') === '1';
        field.style.display = (isLinked && !keepVisibleWhenLinked) ? 'none' : '';
    });
}

function resetLinkedEquipmentPortField(form) {
    if (!form || !form.linked_equipment_port) {
        return;
    }
    form.linked_equipment_port.value = '';
    form.linked_equipment_port.readOnly = false;
}

function populateLinkedEquipmentFields() {
    const f = document.getElementById('linkForm');
    const selectedOption = f.switch_port_id.options[f.switch_port_id.selectedIndex];
    if (!selectedOption || !selectedOption.dataset.portJson) {
        resetLinkedEquipmentPortField(f);
        toggleLinkedEquipmentFields(false);
        return;
    }

    const port = JSON.parse(selectedOption.dataset.portJson);
    const linkedColorName = (port.equipment_color || '').trim() || 'Gray';
    const linkedColorHex = (port.equipment_color_hex || '').trim();
    f.linked_equipment_port.value = port.equipment_port || '';
    f.linked_equipment_port.readOnly = true;
    f.linked_cable_label.value = normalizeLabelDisplayValue(port.equipment_label || '');
    f.linked_notes.value = port.equipment_comments || '';

    const switchPortColorId = Number(port.equipment_color_id || 0);
    const trySelectLinkedColor = (colorSelect) => {
        if (!colorSelect) return;

        let targetOption = null;
        if (switchPortColorId > 0) {
            targetOption = Array.from(colorSelect.options).find((option) =>
                option.value !== '__add_new__' && Number(option.value) === switchPortColorId
            );
        }
        if (!targetOption && linkedColorName !== '') {
            targetOption = Array.from(colorSelect.options).find((option) =>
                option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === linkedColorName.toLowerCase()
            );
        }
        if (!targetOption && linkedColorHex !== '') {
            targetOption = Array.from(colorSelect.options).find((option) =>
                option.value !== '__add_new__' && String((option.dataset && option.dataset.hex) || '').trim().toLowerCase() === linkedColorHex.toLowerCase()
            );
        }
        if (!targetOption) {
            targetOption = Array.from(colorSelect.options).find((option) =>
                option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'gray'
            );
        }

        if (targetOption) {
            colorSelect.value = targetOption.value;
            colorSelect.dataset.previousValue = targetOption.value;
        }
        updateCableColorSwatch(linkedColorHex || linkedColorName || 'Gray', colorSelect);
    };

    trySelectLinkedColor(f.linked_cable_color_id);
    trySelectLinkedColor(f.cable_color_id);

    const switchPortStatusId = Number(port.equipment_status_id || 0);
    if (f.status) {
        if (switchPortStatusId > 0 && Array.from(f.status.options).some((option) => Number(option.value) === switchPortStatusId)) {
            f.status.value = String(switchPortStatusId);
        } else {
            const unknownStatusOption = Array.from(f.status.options).find((option) =>
                option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'unknown'
            );
            if (unknownStatusOption) {
                f.status.value = unknownStatusOption.value;
            }
        }
    }

    if (f.vlan) {
        applySelectValueOrLabel(f.vlan, port.equipment_vlan_id, port.equipment_vlan_name);
    }

    const sourcePort = findPortMetaByRef(f.port_id_a.value);
    if (sourcePort) {
        const enrichedSource = mergeSwitchPortApiRowIntoPortMeta(sourcePort, port);
        upsertPortMetaInPortsList(enrichedSource);
        updateLinkFormTypePresentation(enrichedSource);
    }

    toggleLinkedEquipmentFields(true);
}

document.addEventListener('DOMContentLoaded', () => {
    const bindStatusAddNew = (formId) => {
        const form = document.getElementById(formId);
        const statusSelect = form ? form.querySelector('select[name="status"]') : null;
        if (!statusSelect) return;
        statusSelect.dataset.previousValue = statusSelect.value || '';
        statusSelect.addEventListener('focus', () => {
            if (statusSelect.value !== '__add_new__') {
                statusSelect.dataset.previousValue = statusSelect.value || '';
            }
        });
        statusSelect.addEventListener('change', (event) => {
            const wantsAdd = event.target.value === '__add_new__';
            if (!wantsAdd) {
                statusSelect.dataset.previousValue = statusSelect.value || '';
                return;
            }
            openStatusModal(statusSelect);
        });
    };

    bindStatusAddNew('portForm');
    bindStatusAddNew('linkForm');

    const cableColorModalInput = document.getElementById('cableColorModalInput');
    const cableColorModalPicker = document.getElementById('cableColorModalColorPicker');
    const cableColorModalName = document.getElementById('cableColorModalName');
    if (cableColorModalInput && cableColorModalPicker) {
        cableColorModalPicker.addEventListener('input', (event) => {
            cableColorModalInput.value = event.target.value;
            syncCableColorNameFromHex();
        });
        cableColorModalInput.addEventListener('input', (event) => {
            const nextValue = (event.target.value || '').trim();
            if (!nextValue) return;
            cableColorModalPicker.value = normalizeColorToHex(nextValue);
            syncCableColorNameFromHex();
        });
    }
    if (cableColorModalName) {
        cableColorModalName.dataset.manualName = '0';
        cableColorModalName.addEventListener('input', () => {
            const hasName = cableColorModalName.value.trim() !== '';
            cableColorModalName.dataset.manualName = hasName ? '1' : '0';
        });
    }

    const f = document.getElementById('linkForm');
    if (f && f.equipment_id) {
        f.equipment_id.addEventListener('change', (event) => {
            loadEquipmentPorts(event.target.value);
        });
        f.switch_port_id.addEventListener('change', () => {
            populateLinkedEquipmentFields();
        });
    }
    const initializeCableColorSelect = (cableColorSelect) => {
        if (!cableColorSelect) return;
        updateCableColorSwatch('', cableColorSelect);
        cableColorSelect.dataset.previousValue = cableColorSelect.value || '';
        cableColorSelect.addEventListener('focus', () => {
            if (cableColorSelect.value !== '__add_new__') {
                cableColorSelect.dataset.previousValue = cableColorSelect.value || '';
            }
        });
        cableColorSelect.addEventListener('change', (event) => {
            const selected = event.target.value || '';
            if (selected === '__add_new__') {
                openCableColorModal(event.target);
                return;
            }
            updateCableColorSwatch('', cableColorSelect);
            cableColorSelect.dataset.previousValue = selected || '';
        });
    };
    document.querySelectorAll('select[name="cable_color_id"], select[name="linked_cable_color_id"]').forEach((cableColorSelect) => {
        initializeCableColorSelect(cableColorSelect);
    });

    const portForm = document.getElementById('portForm');
    if (portForm && portForm.port_type) {
        portForm.port_type.addEventListener('change', () => {
            const portMeta = findPortMetaByRef(portForm.port_id.value) || null;
            const selectedTypeLabel = (portForm.port_type.options && portForm.port_type.options[portForm.port_type.selectedIndex])
                ? portForm.port_type.options[portForm.port_type.selectedIndex].textContent
                : '';
            rebuildSpeedOptionsForPortType(selectedTypeLabel, '');
            togglePoeFieldForPortType(selectedTypeLabel);
            updatePortFormTypePresentation(portMeta
                ? Object.assign({}, portMeta, {port_type_label: selectedTypeLabel})
                : {port_type_label: selectedTypeLabel});
        });
    }

    wireIdfDeviceAddableSelects(document.getElementById('portForm'));
    wireIdfDeviceAddableSelects(document.getElementById('linkForm'));

    // Why: Prefer return_idf=4 over return_to=view.php%3Fid%3D4 so rack navigation URLs stay readable.
    (function canonicalizeDevicePageUrl() {
        const pageUrl = new URL(window.location.href);
        let changed = false;
        const legacyReturnTo = String(pageUrl.searchParams.get('return_to') || '').trim();
        if (legacyReturnTo !== '' && !pageUrl.searchParams.has('return_idf')) {
            const legacyMatch = legacyReturnTo.match(/^view\.php\?id=(\d+)/i);
            if (legacyMatch) {
                pageUrl.searchParams.set('return_idf', legacyMatch[1]);
                pageUrl.searchParams.delete('return_to');
                changed = true;
            }
        }
        if (AUTO_OPEN_LINK_PORT_ID > 0 || AUTO_OPEN_EDIT_PORT_ID > 0 || AUTO_OPEN_LINK_PORT_NO > 0 || AUTO_OPEN_EDIT_PORT_NO > 0) {
            pageUrl.searchParams.delete('open_link_port_id');
            pageUrl.searchParams.delete('open_edit_port_id');
            pageUrl.searchParams.delete('open_link_port_no');
            pageUrl.searchParams.delete('open_edit_port_no');
            changed = true;
        }
        if (changed) {
            window.history.replaceState({}, document.title, pageUrl.toString());
        }
    })();

    // Why: Once the deep-link modal is consumed, removing query flags prevents a stale auto-open after save/reload.

    // Why: IDF rack clicks route to this page with an explicit action so users land directly in the expected modal.
    if (AUTO_OPEN_LINK_PORT_ID > 0) {
        void openLinkModal(AUTO_OPEN_LINK_PORT_ID);
    } else if (AUTO_OPEN_LINK_PORT_NO > 0) {
        void openLinkModal(AUTO_OPEN_LINK_PORT_NO);
    } else if (AUTO_OPEN_EDIT_PORT_ID > 0) {
        openPortModal(AUTO_OPEN_EDIT_PORT_ID);
    } else if (AUTO_OPEN_EDIT_PORT_NO > 0) {
        openPortModal(AUTO_OPEN_EDIT_PORT_NO);
    } else if (EMBED_MODE && EMBED_MODAL_ONLY) {
        closeEmbeddedDeviceView('idf_device_embed_close');
    }
});

function getNormalizedStatusValue(form) {
    const statusSelect = portFormControl(form, 'status');
    if (!statusSelect) return '';
    if (statusSelect.value === '__add_new__') return '';
    return (statusSelect.value || '').trim();
}

function openStatusModal(statusSelect) {
    activeStatusSelect = statusSelect || null;
    const statusInput = document.getElementById('statusModalInput');
    if (statusInput) {
        statusInput.value = '';
    }
    document.getElementById('statusBackdrop').style.display = 'flex';
    if (statusInput) {
        setTimeout(() => statusInput.focus(), 0);
    }
}

function closeStatusModal(keepSelection) {
    document.getElementById('statusBackdrop').style.display = 'none';
    if (!activeStatusSelect) return;
    if (!keepSelection) {
        activeStatusSelect.value = activeStatusSelect.dataset.previousValue || '';
    }
    activeStatusSelect = null;
}

function openCableColorModal(cableColorSelect) {
    activeCableColorSelect = cableColorSelect || null;
    const cableColorNameInput = document.getElementById('cableColorModalName');
    const cableColorInput = document.getElementById('cableColorModalInput');
    const cableColorPicker = document.getElementById('cableColorModalColorPicker');
    if (cableColorNameInput) {
        cableColorNameInput.value = '';
        cableColorNameInput.dataset.manualName = '0';
    }
    if (cableColorInput) {
        cableColorInput.value = '';
    }
    if (cableColorPicker) {
        cableColorPicker.value = '#ffff00';
    }
    if (cableColorInput) {
        cableColorInput.value = '#ffff00';
    }
    syncCableColorNameFromHex();
    document.getElementById('cableColorBackdrop').style.display = 'flex';
    if (cableColorNameInput) {
        setTimeout(() => cableColorNameInput.focus(), 0);
    }
}

function closeCableColorModal(keepSelection) {
    document.getElementById('cableColorBackdrop').style.display = 'none';
    if (!activeCableColorSelect) return;
    if (!keepSelection) {
        activeCableColorSelect.value = activeCableColorSelect.dataset.previousValue || 'Gray';
        updateCableColorSwatch(activeCableColorSelect.value || 'Gray', activeCableColorSelect);
    }
    activeCableColorSelect = null;
}

function saveCableColorFromModal() {
    if (!activeCableColorSelect) {
        closeCableColorModal(false);
        return;
    }

    const cableColorNameInput = document.getElementById('cableColorModalName');
    const cableColorInput = document.getElementById('cableColorModalInput');
    const nextCableColorName = cableColorNameInput ? String(cableColorNameInput.value || '').trim() : '';
    const nextHexColor = cableColorInput ? String(cableColorInput.value || '').trim() : '';
    if (!nextCableColorName && !nextHexColor) {
        alert('Please enter a color name or hex color.');
        if (cableColorNameInput) cableColorNameInput.focus();
        return;
    }

    apiPost('cable_color_add.php', {
        csrf_token: CSRF,
        color_name: nextCableColorName,
        hex_color: nextHexColor,
    }).then((response) => {
        const colorId = Number(response.id || 0);
        const cableColorName = String(response.color_name || nextCableColorName).trim();
        const cableColorHex = String(response.hex_color || nextHexColor || '').trim();
        if (!colorId || !cableColorName) {
            throw new Error('Invalid cable color returned from server.');
        }
        document.querySelectorAll('select[name="cable_color_id"], select[name="linked_cable_color_id"]').forEach((selectEl) => {
            const existingOption = Array.from(selectEl.options).find((option) =>
                option.value !== '__add_new__' && Number(option.value) === colorId
            );
            if (!existingOption) {
                const addOption = selectEl.querySelector('option[value="__add_new__"]');
                const option = document.createElement('option');
                option.value = String(colorId);
                option.textContent = cableColorName;
                option.dataset.hex = cableColorHex;
                selectEl.insertBefore(option, addOption || null);
            } else if (cableColorHex) {
                existingOption.dataset.hex = cableColorHex;
            }
        });
        activeCableColorSelect.value = String(colorId);
        activeCableColorSelect.dataset.previousValue = String(colorId);
        updateCableColorSwatch('', activeCableColorSelect);
        closeCableColorModal(true);
    }).catch((error) => {
        (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(error.message) : alert(error.message));
    });
}

function saveStatusFromModal() {
    if (!activeStatusSelect) {
        closeStatusModal(false);
        return;
    }

    const statusInput = document.getElementById('statusModalInput');
    const nextStatus = statusInput ? String(statusInput.value || '').trim() : '';
    if (!nextStatus) {
        alert('Please enter a status value.');
        if (statusInput) statusInput.focus();
        return;
    }

    apiPost('switch_status_add.php', {
        csrf_token: CSRF,
        status: nextStatus,
    }).then((response) => {
        const statusId = Number(response.status_id || 0);
        const statusValue = String(response.status || nextStatus).trim();
        if (!statusValue || statusId <= 0) {
            throw new Error('Invalid status returned from server.');
        }
        document.querySelectorAll('select[name="status"]').forEach((selectEl) => {
            const existingOption = Array.from(selectEl.options).find((option) =>
                option.value !== '__add_new__' && Number(option.value) === statusId
            );
            if (!existingOption) {
                const addOption = selectEl.querySelector('option[value="__add_new__"]');
                const option = document.createElement('option');
                option.value = String(statusId);
                option.textContent = statusValue;
                selectEl.insertBefore(option, addOption || null);
            }
        });
        activeStatusSelect.value = String(statusId);
        activeStatusSelect.dataset.previousValue = String(statusId);
        closeStatusModal(true);
    }).catch((error) => {
        (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(error.message) : alert(error.message));
    });
}

function unlinkPort(linkId) {
    if (!confirm('Remove this cable link?')) return;
    apiPost('link_delete.php', {csrf_token: CSRF, link_id: Number(linkId)})
        .then(() => finishInlineMutationOrReload())
        .catch(err => (window.itmNotifyAjaxError ? window.itmNotifyAjaxError(err.message) : alert(err.message)));
}

async function idfDeviceExportImage() {
    const node = document.getElementById('idfDeviceCaptureRoot');
    const canvas = await html2canvas(node, {scale: 2, useCORS: true});
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `device-<?php echo (int)$position_id; ?>.png`;
    a.click();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</body>
</html>
