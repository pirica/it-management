<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/port_visualizer_helper.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$position_id = (int)($_GET['position_id'] ?? 0);
$open_edit_port_id = (int)($_GET['open_edit_port_id'] ?? 0);
$open_link_port_id = (int)($_GET['open_link_port_id'] ?? 0);

function idf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

$csrf = idf_csrf_token();

$pos = null;
if ($position_id > 0 && $company_id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT p.*, i.name AS idf_name, l.name AS location_name, i.id AS idf_id, spnl.name AS layout_name,
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
         LEFT JOIN equipment e ON e.id = p.equipment_id
         LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id AND er.company_id = p.company_id
         LEFT JOIN idf_device_type dt ON dt.id = p.device_type AND dt.company_id = p.company_id
         LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = p.switch_port_numbering_layout_id
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
$sfpPlusPortNumbers = [];

$portSortField = (string)($_GET['sort'] ?? 'port_type');
$portSortDir = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$portSortMap = [
    'port_no' => 'pr.port_no',
    'port_type' => "COALESCE(spt.type, 'RJ45')",
    'label' => 'pr.label',
    'status' => "COALESCE(ss.status, 'Unknown')",
    'connected_to' => 'pr.connected_to',
    'vlan' => 'CASE WHEN v.id IS NULL THEN "" WHEN TRIM(COALESCE(v.vlan_name, "")) = "" THEN COALESCE(v.vlan_number, "") ELSE CONCAT(COALESCE(v.vlan_number, ""), " - ", v.vlan_name) END',
    'speed' => 'COALESCE(ef.name, "")',
    'poe' => 'COALESCE(ep.name, "")',
    'notes' => 'pr.notes',
    'link' => 'l.id'
];
if (!isset($portSortMap[$portSortField])) {
    $portSortField = 'port_type';
}
$portOrderSql = $portSortMap[$portSortField] . ' ' . $portSortDir . ', pr.port_no ASC';

$stmtPorts = mysqli_prepare(
    $conn,
    "SELECT
       pr.*,
       COALESCE(spt.type, 'RJ45') AS port_type_label,
       COALESCE(ss.status, 'Unknown') AS status_label,
       COALESCE(cc_ss.hex_color, '#adb5bd') AS status_color,
       p_local.position_no AS local_position_no,
       p_local.device_name AS local_device_name,
       p_local.equipment_id AS local_equipment_id,
       i_local.name AS local_idf_name,
       COALESCE(dt_local.idfdevicetype_name, et_local.name, '') AS local_device_type_label,
       CASE
         WHEN v.id IS NULL THEN ''
         WHEN TRIM(COALESCE(v.vlan_name, '')) = '' THEN COALESCE(v.vlan_number, '')
         ELSE CONCAT(COALESCE(v.vlan_number, ''), ' - ', v.vlan_name)
       END AS vlan_label,
       COALESCE(ef.name, '') AS speed_label,
       COALESCE(ep.name, '') AS poe_label,
       l.id AS link_id,
       l.cable_color_id,
       COALESCE(NULLIF(cc_l.color_name, ''), cc_l.hex_color, '') AS cable_color_name,
       cc_l.hex_color AS cable_hex_color,
       l.cable_label,
       l.notes AS link_notes,
       CASE
         WHEN l.port_id_a = pr.id THEN l.port_id_b
         WHEN l.port_id_b = pr.id THEN l.port_id_a
         ELSE NULL
       END AS other_port_id,
       pr_remote.port_no AS remote_port_no,
       pr_remote.status_id AS remote_status_id,
       COALESCE(ss_remote.status, 'Unknown') AS remote_status_label,
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
       ON p_local.id = pr.position_id
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
     LEFT JOIN switch_status ss
       ON ss.id = pr.status_id
      AND ss.company_id = pr.company_id
     LEFT JOIN cable_colors cc_ss
       ON cc_ss.id = ss.color_id
     LEFT JOIN vlans v
       ON v.id = pr.vlan_id
      AND v.company_id = pr.company_id
     LEFT JOIN equipment_fiber ef
       ON ef.id = pr.speed_id
      AND ef.company_id = pr.company_id
     LEFT JOIN equipment_poe ep
       ON ep.id = pr.poe_id
      AND ep.company_id = pr.company_id
     LEFT JOIN idf_links l ON l.id = (
         SELECT l2.id
         FROM idf_links l2
         WHERE l2.port_id_a = pr.id OR l2.port_id_b = pr.id
         ORDER BY l2.id ASC
         LIMIT 1
     )
     LEFT JOIN idf_ports pr_remote
       ON pr_remote.id = CASE
           WHEN l.port_id_a = pr.id THEN l.port_id_b
           WHEN l.port_id_b = pr.id THEN l.port_id_a
           ELSE NULL
       END
     LEFT JOIN switch_status ss_remote
       ON ss_remote.id = pr_remote.status_id
      AND ss_remote.company_id = pr_remote.company_id
     LEFT JOIN idf_positions p_remote
       ON p_remote.id = pr_remote.position_id
     LEFT JOIN equipment e_remote
       ON e_remote.id = p_remote.equipment_id
     LEFT JOIN equipment_types et_remote
       ON et_remote.id = e_remote.equipment_type_id
     LEFT JOIN idf_device_type dt_remote
       ON dt_remote.id = p_remote.device_type
      AND dt_remote.company_id = p_remote.company_id
     LEFT JOIN cable_colors cc_l ON cc_l.id = l.cable_color_id
     LEFT JOIN equipment le ON le.id = l.equipment_id
     LEFT JOIN equipment_types let ON let.id = le.equipment_type_id
     WHERE pr.position_id=?
     ORDER BY " . $portOrderSql
);
if ($stmtPorts) {
    mysqli_stmt_bind_param($stmtPorts, 'i', $position_id);
    mysqli_stmt_execute($stmtPorts);
    $resPorts = mysqli_stmt_get_result($stmtPorts);
    while ($resPorts && ($row = mysqli_fetch_assoc($resPorts))) {
        $ports[] = $row;
    }
    mysqli_stmt_close($stmtPorts);
}

foreach ($ports as $portMeta) {
    $portNo = (int)($portMeta['port_no'] ?? 0);
    if ($portNo <= 0) {
        continue;
    }
    $typeRaw = strtolower(trim((string)($portMeta['port_type_label'] ?? ($portMeta['port_type'] ?? ''))));
    if (strpos($typeRaw, 'sfp+') !== false || strpos($typeRaw, 'sfp plus') !== false) {
        $sfpPlusPortNumbers[] = $portNo;
    } elseif (strpos($typeRaw, 'sfp') !== false) {
        $sfpPortNumbers[] = $portNo;
    } else {
        $rj45PortNumbers[] = $portNo;
    }
}

$rj45PortNumbers = array_values(array_unique($rj45PortNumbers));
sort($rj45PortNumbers);
$sfpPortNumbers = array_values(array_unique($sfpPortNumbers));
sort($sfpPortNumbers);
$sfpPlusPortNumbers = array_values(array_unique($sfpPlusPortNumbers));
sort($sfpPlusPortNumbers);

if (empty($rj45PortNumbers)) {
    $rj45PortLabel = (string)($pos['switch_rj45_name'] ?? '');
    $rj45FallbackCount = 0;
    if ($rj45PortLabel !== '' && preg_match('/(\d+)/', $rj45PortLabel, $rj45Matches)) {
        $rj45FallbackCount = (int)$rj45Matches[1];
    } elseif ((int)($pos['port_count'] ?? 0) > 0) {
        $rj45FallbackCount = (int)$pos['port_count'];
    }
    if ($rj45FallbackCount > 0) {
        $rj45PortNumbers = range(1, $rj45FallbackCount);
    }
}

if (empty($sfpPortNumbers) && empty($sfpPlusPortNumbers)) {
    $fiberFallbackCount = (int)($pos['equipment_fiber_ports_number'] ?? 0);
    if ($fiberFallbackCount > 0) {
        $fiberLabel = strtolower(trim((string)($pos['equipment_fiber_port_label'] ?? '')));
        if (strpos($fiberLabel, 'sfp+') !== false || strpos($fiberLabel, 'sfp plus') !== false) {
            $sfpPlusPortNumbers = range(1, $fiberFallbackCount);
        } else {
            $sfpPortNumbers = range(1, $fiberFallbackCount);
        }
    }
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
         JOIN idf_positions p ON p.id=pr.position_id
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
    if (empty($p['link_id']) || empty($p['other_port_id'])) {
        continue;
    }
    $otherPortId = (int)$p['other_port_id'];
    if (!isset($otherMap[$otherPortId])) {
        continue;
    }
    $remote = $otherMap[$otherPortId];
    $linkedEquipmentName = trim((string)($p['equipment_hostname'] ?? ''));
    $linkedEquipmentPort = trim((string)($p['equipment_port'] ?? ''));
    $positionEquipmentSerial = trim((string)($remote['equipment_serial_number'] ?? ''));
    $linkedRemoteDeviceName = $linkedEquipmentName !== ''
        ? $linkedEquipmentName
        : ($positionEquipmentSerial !== '' ? $positionEquipmentSerial : (string)($remote['device_name'] ?? ''));
    $linkedRemotePortNo = ctype_digit($linkedEquipmentPort)
        ? (int)$linkedEquipmentPort
        : (int)($remote['port_no'] ?? 0);
    $linkOverview[] = [
        'link_id' => (int)$p['link_id'],
        'local_port_no' => (int)($p['port_no'] ?? 0),
        'local_label' => (string)($p['label'] ?? ''),
        'remote_position_no' => (int)($remote['position_no'] ?? 0),
        'remote_device_name' => $linkedRemoteDeviceName,
        'remote_port_no' => $linkedRemotePortNo,
        'cable_color_name' => (string)($p['cable_color_name'] ?? ''),
        'cable_hex_color' => (string)($p['cable_hex_color'] ?? ''),
        'cable_label' => (string)($p['cable_label'] ?? ''),
        'link_notes' => (string)($p['link_notes'] ?? ''),
    ];
}

$destinationPorts = [];
$stmtDestinationPorts = mysqli_prepare(
    $conn,
    "SELECT
        pr.id,
        pr.port_no,
        pr.label,
        i.id AS idf_id,
        i.name AS idf_name,
        p.id AS position_id,
        p.position_no,
        p.device_name,
        p.device_type,
        p.equipment_id,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM idf_links l
                WHERE l.port_id_a = pr.id
                   OR l.port_id_b = pr.id
            ) THEN 1
            ELSE 0
        END AS is_linked
     FROM idf_ports pr
     JOIN idf_positions p ON p.id = pr.position_id
     JOIN idfs i ON i.id = p.idf_id JOIN it_locations l ON l.id = i.location_id
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
            'equipment_id' => isset($row['equipment_id']) ? (int)$row['equipment_id'] : 0,
            'is_linked' => !empty($row['is_linked']),
        ];
    }
    mysqli_stmt_close($stmtDestinationPorts);
}

$equipmentOptions = [];
$resEq = mysqli_query(
    $conn,
    "SELECT e.id, e.name, e.serial_number
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

$poeOptions = [];
$resPoeOptions = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_poe
     WHERE company_id = $company_id
     ORDER BY name ASC"
);
while ($resPoeOptions && ($row = mysqli_fetch_assoc($resPoeOptions))) {
    $poeId = (int)($row['id'] ?? 0);
    $poeName = trim((string)($row['name'] ?? ''));
    if ($poeId > 0 && $poeName !== '') {
        $poeOptions[$poeId] = $poeName;
    }
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
        .idf-device-visualizer .itm-port-grid { display:none; }
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
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="content" id="idfDeviceCaptureRoot">
            <div class="idf-toolbar">
                <div class="left">
                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$pos['idf_id']; ?>">← Back</a>
                    <div style="display:flex; flex-direction:column;">
                        <div style="opacity:.85; font-size:13px; font-weight:600; margin-bottom:2px;">
                            🗄️ IDF <?php echo sanitize((string)$pos['idf_name']); ?> - <?php echo sanitize((string)$pos['location_name']); ?>
                        </div>
                        <div class="idf-rack-title">
                            🔧 <?php echo sanitize($pos['device_name']); ?>
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

            <div class="card idf-device-visualizer" style="padding:14px; border-radius:18px; margin-bottom:14px;">
                <h3 style="margin-top:0;">👁️ Port Visualization</h3>
                <?php
                echo itm_render_port_visualizer($ports, [
                    'clickable' => true,
                    'layout' => (string)($pos['layout_name'] ?? 'Vertical'),
                    'show_device_icon' => ((int)($pos['equipment_is_switch'] ?? 0) === 1),
                    'rj45_ports' => $rj45PortNumbers,
                    'sfp_ports' => $sfpPortNumbers,
                    'sfp_plus_ports' => $sfpPlusPortNumbers
                ]);
                ?>
            </div>

            <div class="card" style="padding:14px; border-radius:18px;">
                <h3 style="margin-top:0;">🔌 Ports</h3>

                <?php if ((int)$pos['port_count'] > 0 && count($ports) === 0): ?>
                    <div class="alert alert-error">Port list is empty. Use “Regenerate Ports”.</div>
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
                    return $portSortDir === 'ASC' ? ' ▲' : ' ▼';
                };
                ?>

                <table id="portsTable" class="table idf-ports-table">
                    <thead>
                    <tr>
                        <th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('port_no')); ?>">#<?php echo $renderPortSortIndicator('port_no'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('port_type')); ?>">Type<?php echo $renderPortSortIndicator('port_type'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('label')); ?>">Label<?php echo $renderPortSortIndicator('label'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('status')); ?>">Status<?php echo $renderPortSortIndicator('status'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('connected_to')); ?>">Connected To<?php echo $renderPortSortIndicator('connected_to'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('vlan')); ?>">VLAN<?php echo $renderPortSortIndicator('vlan'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('speed')); ?>">Speed<?php echo $renderPortSortIndicator('speed'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('poe')); ?>">PoE<?php echo $renderPortSortIndicator('poe'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('notes')); ?>">Notes<?php echo $renderPortSortIndicator('notes'); ?></a></th><th><a style="color:inherit; text-decoration:none;" href="<?php echo sanitize($buildPortSortLink('link')); ?>">Link<?php echo $renderPortSortIndicator('link'); ?></a></th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$ports): ?>
                        <tr><td colspan="11" style="opacity:.8;">No ports yet.</td></tr>
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
                        if (!empty($p['link_id']) && !empty($p['other_port_id']) && isset($otherMap[(int)$p['other_port_id']])) {
                            $o = $otherMap[(int)$p['other_port_id']];
                            $color = (string)($p['cable_hex_color'] ?? '#ffff00');
                            $label = !empty($p['cable_label']) ? (' • ' . sanitize((string)$p['cable_label'])) : '';
                            $isLoopRisk = ((int)($o['position_id'] ?? 0) === (int)$position_id);
                            $linkText = '<span class="idf-swatch" style="background:' . sanitize($color) . '"></span>'
                                . 'Pos ' . (int)$o['position_no'] . ' • ' . sanitize($o['device_name']) . ' • Port ' . (int)$o['port_no'] . $label
                                . ($isLoopRisk ? ' <span class="badge badge-danger" title="Same-device link detected. This can create a Layer 2 loop on switches without STP.">Loop Risk</span>' : '');
                            if ($connectedToText === '') {
                                $connectedToText = 'Pos ' . (int)$o['position_no'] . ' • ' . (string)$o['device_name'] . ' • Port ' . (int)$o['port_no'];
                            }
                            $unlinkBtn = '<button class="btn btn-sm" type="button" onclick="unlinkPort(' . (int)$p['link_id'] . ')">Unlink</button>';
                        }
                        ?>
                        <tr
                            data-port-id="<?php echo (int)$p['id']; ?>"
                            data-port-type-id="<?php echo (int)($p['port_type'] ?? 0); ?>"
                            data-port-type="<?php echo sanitize((string)($p['port_type_label'] ?? 'RJ45')); ?>"
                            data-label="<?php echo sanitize((string)($p['label'] ?? '')); ?>"
                            data-status-id="<?php echo (int)($p['status_id'] ?? 0); ?>"
                            data-status="<?php echo sanitize((string)($p['status_label'] ?? 'Unknown')); ?>"
                            data-connected-to="<?php echo sanitize((string)($p['connected_to'] ?? '')); ?>"
                            data-vlan-id="<?php echo (int)($p['vlan_id'] ?? 0); ?>"
                            data-vlan="<?php echo sanitize((string)($p['vlan_label'] ?? '')); ?>"
                            data-speed-id="<?php echo (int)($p['speed_id'] ?? 0); ?>"
                            data-speed="<?php echo sanitize((string)($p['speed_label'] ?? '')); ?>"
                            data-poe-id="<?php echo (int)($p['poe_id'] ?? 0); ?>"
                            data-poe="<?php echo sanitize((string)($p['poe_label'] ?? '')); ?>"
                            data-notes="<?php echo sanitize((string)($p['notes'] ?? '')); ?>"
                        >
                            <td><?php echo (int)$p['port_no']; ?></td>
                            <td><?php echo sanitize((string)($p['port_type_label'] ?? 'RJ45')); ?></td>
                            <td><?php echo sanitize((string)($p['label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['status_label'] ?? 'Unknown')); ?></td>
                            <td><?php echo sanitize($connectedToText); ?></td>
                            <td><?php echo sanitize((string)($p['vlan_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['speed_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['poe_label'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['notes'] ?? '')); ?></td>
                            <td><?php echo $linkText ?: '<span style="opacity:.75;">—</span>'; ?></td>
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

            <div class="card" style="padding:14px; border-radius:18px; margin-top:14px;">
                <h3 style="margin-top:0;">🔗 Equipment Link Map</h3>
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
                                        <span style="opacity:.75;">• <?php echo sanitize($row['local_label']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>Pos <?php echo (int)$row['remote_position_no']; ?> • <?php echo sanitize($row['remote_device_name']); ?></td>
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
                                        <span style="opacity:.75;">• <?php echo sanitize($row['cable_label']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['link_notes'] !== '' ? sanitize($row['link_notes']) : '<span style="opacity:.75;">—</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <table id="portsExportTable" class="table" style="display:none;">
                <thead>
                <tr>
                    <th>Port No</th><th>Type</th><th>Label</th><th>Status</th><th>Connected To</th><th>VLAN</th><th>Speed</th><th>PoE</th><th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ports as $p): ?>
                    <tr>
                        <td><?php echo (int)$p['port_no']; ?></td>
                        <td><?php echo sanitize((string)($p['port_type_label'] ?? 'RJ45')); ?></td>
                        <td><?php echo sanitize((string)($p['label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['status_label'] ?? 'Unknown')); ?></td>
                        <td><?php echo sanitize((string)($p['connected_to'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['vlan_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['speed_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['poe_label'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['notes'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="idf-modal-backdrop" id="portBackdrop" onclick="if(event.target.id==='portBackdrop') closePortModal()">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Edit Port</div>
            <button class="btn btn-sm" type="button" onclick="closePortModal()">✖</button>
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
                    <option value="__add_new__">➕</option>
                </select>
            </div>
            <div>
                <label class="label">Label</label>
                <input class="input" name="label">
            </div>
            <div>
                <label class="label">Connected To</label>
                <input class="input" name="connected_to" placeholder="e.g. PP1-12 / Desk-203 / Core-SW1 Gi1/0/12">
            </div>
            <div>
                <label class="label">VLAN</label>
                <select class="input" name="vlan">
                    <option value="">-- None --</option>
                    <?php foreach ($vlanOptions as $vlanId => $vlanLabel): ?>
                        <option value="<?php echo (int)$vlanId; ?>"><?php echo sanitize($vlanLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label">Speed</label>
                <select class="input" name="speed">
                    <option value="">-- None --</option>
                    <?php foreach ($fiberSpeedOptions as $fiberId => $fiberLabel): ?>
                        <option value="<?php echo (int)$fiberId; ?>"><?php echo sanitize($fiberLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label">PoE</label>
                <select class="input" name="poe">
                    <option value="">-- None --</option>
                    <?php foreach ($poeOptions as $poeId => $poeLabel): ?>
                        <option value="<?php echo (int)$poeId; ?>"><?php echo sanitize($poeLabel); ?></option>
                    <?php endforeach; ?>
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
                        <option value="__add_new__">➕</option>
                    </select>
                </div>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="label">Notes</label>
                <input class="input" name="notes">
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" type="button" onclick="savePort()">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="idf-modal-backdrop" id="linkBackdrop" onclick="if(event.target.id==='linkBackdrop') closeLinkModal()">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Create Cable Link</div>
            <button class="btn btn-sm" type="button" onclick="closeLinkModal()">✖</button>
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
                            <?php echo sanitize($e['name'] . (!empty($e['serial_number']) ? (' • SN ' . $e['serial_number']) : '')); ?>
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
                        <option value="__add_new__">➕</option>
                    </select>
                </div>
            </div>
            <div data-link-default-field="cable_label">
                <label class="label">Cable label (optional)</label>
                <input class="input" name="cable_label" placeholder="e.g. FIB-12 / CAT6-34">
            </div>
            <div data-link-default-field="status">
                <label class="label">Status</label>
                <select class="input" name="status">
                    <?php foreach ($switchStatusOptions as $statusId => $statusOption): ?>
                        <option value="<?php echo (int)$statusId; ?>" <?php echo strcasecmp($statusOption, 'Unknown') === 0 ? 'selected' : ''; ?>><?php echo sanitize($statusOption); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">➕</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;" data-link-default-field="notes">
                <label class="label">Notes (optional)</label>
                <input class="input" name="notes" placeholder="Optional">
            </div>
            <div style="grid-column: 1 / -1; display:none;" id="linkedEquipmentFields">
                <div class="idf-grid-2">
                    <div>
                        <label class="label">Equipment port</label>
                        <input class="input" name="linked_equipment_port" placeholder="e.g. 12">
                    </div>
                    <div>
                        <label class="label">Cable color</label>
                        <input class="input" type="color" name="linked_cable_color_picker" value="#808080" style="height:40px; padding:4px;">
                        <input class="input" name="linked_cable_color" placeholder="e.g. gray, blue, #808080" value="Gray" style="margin-top:8px;">
                    </div>
                    <div>
                        <label class="label">Cable label</label>
                        <input class="input" name="linked_cable_label" placeholder="e.g. FIB-12 / CAT6-34">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="label">Notes</label>
                        <input class="input" name="linked_notes" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" type="button" onclick="createLink()">Create link</button>
            </div>
        </form>
    </div>
</div>

<div class="idf-modal-backdrop" id="cableColorBackdrop" onclick="if(event.target.id==='cableColorBackdrop') closeCableColorModal(false)">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Add Cable Color</div>
            <button class="btn btn-sm" type="button" onclick="closeCableColorModal(false)">✖</button>
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

<div class="idf-modal-backdrop" id="statusBackdrop" onclick="if(event.target.id==='statusBackdrop') closeStatusModal(false)">
    <div class="idf-modal" onclick="event.stopPropagation()">
        <div class="idf-modal-header">
            <div class="idf-modal-title">Add Status</div>
            <button class="btn btn-sm" type="button" onclick="closeStatusModal(false)">✖</button>
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

<script>
const IDF_BASE = '<?php echo BASE_URL; ?>modules/idfs';
const CSRF = '<?php echo sanitize($csrf); ?>';
const POSITION_ID = <?php echo (int)$position_id; ?>;
const AUTO_OPEN_EDIT_PORT_ID = <?php echo (int)$open_edit_port_id; ?>;
const AUTO_OPEN_LINK_PORT_ID = <?php echo (int)$open_link_port_id; ?>;
const PORTS = <?php
$portsMeta = array_map(static function (array $port): array {
    return [
        'id' => (int)($port['id'] ?? 0),
        'port_no' => (int)($port['port_no'] ?? 0),
        'label' => (string)($port['label'] ?? ''),
        'cable_color_id' => isset($port['cable_color_id']) ? (int)$port['cable_color_id'] : 0,
        'cable_color_name' => (string)($port['cable_color_name'] ?? ''),
        'cable_hex_color' => (string)($port['cable_hex_color'] ?? ''),
        'cable_color' => (string)($port['cable_color'] ?? 'Gray'),
        'is_linked' => !empty($port['link_id']),
    ];
}, $ports);
echo json_encode($portsMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>;
const DESTINATION_PORTS = <?php echo json_encode($destinationPorts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let activeStatusSelect = null;
let activeCableColorSelect = null;

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

function openPortModal(portId) {
    const row = document.querySelector(`tr[data-port-id="${portId}"]`);
    const portMeta = PORTS.find((port) => Number(port.id) === Number(portId)) || null;
    const form = document.getElementById('portForm');
    const rowData = row?.dataset || {};

    form.port_id.value = portId;
    const requestedPortTypeId = rowData.portTypeId || '';
    if (requestedPortTypeId && Array.from(form.port_type.options).some((option) => option.value === requestedPortTypeId)) {
        form.port_type.value = requestedPortTypeId;
    } else {
        const requestedPortType = (rowData.portType || 'RJ45').trim();
        const portTypeOption = Array.from(form.port_type.options).find((option) =>
            option.textContent.trim().toLowerCase() === requestedPortType.toLowerCase()
        );
        form.port_type.value = portTypeOption ? portTypeOption.value : (form.port_type.value || '');
    }
    form.label.value = rowData.label || '';
    const requestedStatusId = rowData.statusId || '';
    if (requestedStatusId && Array.from(form.status.options).some((option) => option.value === requestedStatusId)) {
        form.status.value = requestedStatusId;
    } else {
        const requestedStatus = (rowData.status || 'Unknown').trim();
        const statusOption = Array.from(form.status.options).find((option) =>
            option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === requestedStatus.toLowerCase()
        );
        form.status.value = statusOption ? statusOption.value : (form.status.value || '');
    }
    form.connected_to.value = rowData.connectedTo || '';
    form.vlan.value = rowData.vlanId || '';
    form.speed.value = rowData.speedId || '';
    form.poe.value = rowData.poeId || '';
    form.notes.value = rowData.notes || '';
    const requestedCableColorId = (portMeta?.cable_color_id || 0);
    const requestedCableColorName = (portMeta?.cable_color_name || portMeta?.cable_color || '').trim();
    if (requestedCableColorId > 0 && Array.from(form.cable_color_id.options).some((option) => Number(option.value) === requestedCableColorId)) {
        form.cable_color_id.value = String(requestedCableColorId);
    } else {
        const normalizedRequestedCableColor = requestedCableColorName.toLowerCase();
        const matchedCableColorOption = Array.from(form.cable_color_id.options).find((option) =>
            option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === normalizedRequestedCableColor
        );
        const grayCableColorOption = Array.from(form.cable_color_id.options).find((option) =>
            option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'gray'
        );
        form.cable_color_id.value = matchedCableColorOption
            ? matchedCableColorOption.value
            : (grayCableColorOption ? grayCableColorOption.value : '');
    }
    updateCableColorSwatch(
        form.cable_color_id.value || requestedCableColorName || portMeta?.cable_hex_color || 'Gray',
        form.cable_color_id
    );

    document.getElementById('portBackdrop').style.display = 'flex';
}

function closePortModal() {
    document.getElementById('portBackdrop').style.display = 'none';
}

function savePort() {
    const f = document.getElementById('portForm');
    const normalizedStatus = getNormalizedStatusValue(f);
    if (!normalizedStatus) {
        alert('Please enter a status value.');
        return;
    }
    const payload = {
        csrf_token: CSRF,
        port_id: Number(f.port_id.value),
        port_type_id: Number(f.port_type.value),
        label: f.label.value.trim(),
        status_id: Number(normalizedStatus),
        connected_to: f.connected_to.value.trim(),
        vlan_id: f.vlan.value ? Number(f.vlan.value) : null,
        speed_id: f.speed.value ? Number(f.speed.value) : null,
        poe_id: f.poe.value ? Number(f.poe.value) : null,
        notes: f.notes.value.trim(),
        cable_color_id: (f.cable_color_id.value && f.cable_color_id.value !== '__add_new__')
            ? Number(f.cable_color_id.value)
            : null,
    };
    apiPost('port_update.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function onPortClick(portId) {
    openPortModal(portId);
}

function regeneratePorts() {
    if (!confirm('Regenerate ports? This will DELETE and recreate ports 1..port_count.')) return;
    apiPost('ports_regen.php', {csrf_token: CSRF, position_id: POSITION_ID})
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function idfPortsExportExcel() {
    if (typeof XLSX === 'undefined') { alert('XLSX library not loaded.'); return; }
    const table = document.getElementById('portsExportTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Ports'});
    XLSX.writeFile(wb, `ports-position-${POSITION_ID}.xlsx`);
}

function openLinkModal(portId) {
    const source = PORTS.find(p => Number(p.id) === Number(portId));
    if (!source) {
        alert('Source port not found.');
        return;
    }
    if (source.is_linked) {
        alert('This port is already linked. Unlink it first.');
        return;
    }
    const f = document.getElementById('linkForm');
    const destinationSelect = f.port_id_b;
    const destinations = DESTINATION_PORTS.filter((p) =>
        Number(p.id) !== Number(source.id) && !p.is_linked
    );

    destinationSelect.innerHTML = '<option value="">Select destination port</option>';
    destinations.forEach((port) => {
        const option = document.createElement('option');
        option.value = String(port.id);
        const idfName = port.idf_name ? `IDF ${port.idf_name}` : (port.idf_id ? `IDF #${port.idf_id}` : 'IDF');
        option.textContent = `${idfName} • Pos ${port.position_no} • ${port.device_name} • Port ${port.port_no}${port.label ? ` • ${port.label}` : ''}`;
        destinationSelect.appendChild(option);
    });
    if (!destinations.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No available ports on other equipment';
        destinationSelect.appendChild(option);
    }

    f.port_id_a.value = String(source.id);
    f.source_display.value = `Port ${source.port_no}${source.label ? ` • ${source.label}` : ''}`;
    const grayCableColorOption = Array.from(f.cable_color_id.options).find((option) =>
        option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'gray'
    );
    f.cable_color_id.value = grayCableColorOption ? grayCableColorOption.value : '';
    f.cable_label.value = '';
    f.notes.value = '';
    const unknownStatusOption = Array.from(f.status.options).find((option) =>
        option.value !== '__add_new__' && option.textContent.trim().toLowerCase() === 'unknown'
    );
    f.status.value = unknownStatusOption ? unknownStatusOption.value : (f.status.value || '');
    f.equipment_id.value = '';
    f.switch_port_id.innerHTML = '<option value="">Select equipment first</option>';
    f.switch_port_id.disabled = true;
    toggleLinkedEquipmentFields(false);
    updateCableColorSwatch(f.cable_color_id.value || 'Gray', f.cable_color_id);
    destinationSelect.value = '';
    document.getElementById('linkBackdrop').style.display = 'flex';
}

function closeLinkModal() {
    document.getElementById('linkBackdrop').style.display = 'none';
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
        const selectedEquipmentId = Number(f.equipment_id.value);
        const allAvailableDestinationPorts = DESTINATION_PORTS.filter((port) =>
            Number(port.id) !== Number(f.port_id_a.value)
            && !port.is_linked
        );
        const allDestinationPorts = DESTINATION_PORTS.filter((port) =>
            Number(port.id) !== Number(f.port_id_a.value)
        );
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

    const cableColorId = (f.cable_color_id.value && f.cable_color_id.value !== '__add_new__')
        ? Number(f.cable_color_id.value)
        : null;
    const cableLabel = linkedMode ? f.linked_cable_label.value.trim() : f.cable_label.value.trim();
    const notes = linkedMode ? f.linked_notes.value.trim() : f.notes.value.trim();
    const selectedDestinationPort = DESTINATION_PORTS.find((port) => Number(port.id) === Number(destinationPortId));
    const linkedDestinationPort = selectedDestinationPort ? String(selectedDestinationPort.port_no || '') : '';

    const payload = {
        csrf_token: CSRF,
        port_id_a: Number(f.port_id_a.value),
        port_id_b: destinationPortId,
        equipment_id: f.equipment_id.value ? Number(f.equipment_id.value) : null,
        switch_port_id: f.switch_port_id.value ? Number(f.switch_port_id.value) : null,
        cable_color_id: cableColorId,
        cable_label: cableLabel,
        notes,
        status_id: Number(getNormalizedStatusValue(f) || 0),
        linked_equipment_port: linkedMode ? f.linked_equipment_port.value.trim() : '',
        linked_destination_port: linkedMode ? linkedDestinationPort : '',
    };

    apiPost('link_create.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function formatSwitchPortOption(port) {
    const hostname = port.equipment_hostname || '-';
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

    return `EQ ${port.equipment_id} | Host: ${hostname} | Type: ${portType} | Port: ${portNumber} | VLAN: ${vlanText} | Label: ${label} | Comments: ${comments} | Status: ${statusText} | Color: ${colorText}`;
}

async function loadEquipmentPorts(equipmentId) {
    const f = document.getElementById('linkForm');
    const select = f.switch_port_id;

    if (!equipmentId) {
        select.innerHTML = '<option value="">Select equipment first</option>';
        select.disabled = true;
        return;
    }

    select.disabled = true;
    select.innerHTML = '<option value="">Loading...</option>';

    try {
        const data = await apiPost('switch_ports_by_equipment.php', {
            csrf_token: CSRF,
            equipment_id: Number(equipmentId),
        });

        select.innerHTML = '<option value="">-- None --</option>';
        (data.ports || []).forEach((port) => {
            const option = document.createElement('option');
            option.value = String(port.id);
            option.textContent = formatSwitchPortOption(port);
            option.dataset.portJson = JSON.stringify(port);
            select.appendChild(option);
        });
        select.disabled = false;
        toggleLinkedEquipmentFields(false);
    } catch (err) {
        select.innerHTML = '<option value="">Failed to load equipment ports</option>';
        select.disabled = true;
        alert(err.message);
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
    const swatchId = cableColorSelect?.dataset?.swatchId || 'cableColorSwatch';
    const swatch = document.getElementById(swatchId);
    if (!swatch) return;
    const selectedOption = cableColorSelect?.selectedOptions?.[0];
    const swatchColor = (selectedOption?.dataset?.hex || colorValue || 'Gray').trim();
    swatch.style.backgroundColor = swatchColor || 'Gray';
}

function toggleLinkedEquipmentFields(isLinked) {
    const linkedFields = document.getElementById('linkedEquipmentFields');
    if (linkedFields) {
        linkedFields.style.display = isLinked ? 'block' : 'none';
    }
    document.querySelectorAll('[data-link-default-field]').forEach((field) => {
        field.style.display = isLinked ? 'none' : '';
    });
}

function populateLinkedEquipmentFields() {
    const f = document.getElementById('linkForm');
    const selectedOption = f.switch_port_id.options[f.switch_port_id.selectedIndex];
    if (!selectedOption || !selectedOption.dataset.portJson) {
        toggleLinkedEquipmentFields(false);
        return;
    }

    const port = JSON.parse(selectedOption.dataset.portJson);
    f.linked_equipment_port.value = port.equipment_port || '';
    f.linked_cable_color.value = port.equipment_color || 'Gray';
    f.linked_cable_color_picker.value = normalizeColorToHex(port.equipment_color || 'Gray');
    f.linked_cable_label.value = port.equipment_label || '';
    f.linked_notes.value = port.equipment_comments || '';
    toggleLinkedEquipmentFields(true);
}

document.addEventListener('DOMContentLoaded', () => {
    const bindStatusAddNew = (formId) => {
        const form = document.getElementById(formId);
        const statusSelect = form?.querySelector('select[name="status"]');
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
        f.linked_cable_color_picker.addEventListener('input', (event) => {
            f.linked_cable_color.value = event.target.value;
        });
        f.linked_cable_color.addEventListener('input', (event) => {
            f.linked_cable_color_picker.value = normalizeColorToHex(event.target.value || 'Gray');
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
    document.querySelectorAll('select[name="cable_color_id"]').forEach((cableColorSelect) => {
        initializeCableColorSelect(cableColorSelect);
    });

    // Why: Once the deep-link modal is consumed, removing query flags prevents a stale auto-open after save/reload.
    if (AUTO_OPEN_LINK_PORT_ID > 0 || AUTO_OPEN_EDIT_PORT_ID > 0) {
        const deepLinkUrl = new URL(window.location.href);
        deepLinkUrl.searchParams.delete('open_link_port_id');
        deepLinkUrl.searchParams.delete('open_edit_port_id');
        window.history.replaceState({}, document.title, deepLinkUrl.toString());
    }

    // Why: IDF rack clicks route to this page with an explicit action so users land directly in the expected modal.
    if (AUTO_OPEN_LINK_PORT_ID > 0) {
        openLinkModal(AUTO_OPEN_LINK_PORT_ID);
    } else if (AUTO_OPEN_EDIT_PORT_ID > 0) {
        openPortModal(AUTO_OPEN_EDIT_PORT_ID);
    }
});

function getNormalizedStatusValue(form) {
    if (!form || !form.status) return '';
    if (form.status.value === '__add_new__') return '';
    return (form.status.value || '').trim();
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
    const nextCableColorName = (cableColorNameInput?.value || '').trim();
    const nextHexColor = (cableColorInput?.value || '').trim();
    if (!nextCableColorName && !nextHexColor) {
        alert('Please enter a color name or hex color.');
        cableColorNameInput?.focus();
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
        document.querySelectorAll('select[name="cable_color_id"]').forEach((selectEl) => {
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
        alert(error.message);
    });
}

function saveStatusFromModal() {
    if (!activeStatusSelect) {
        closeStatusModal(false);
        return;
    }

    const statusInput = document.getElementById('statusModalInput');
    const nextStatus = (statusInput?.value || '').trim();
    if (!nextStatus) {
        alert('Please enter a status value.');
        statusInput?.focus();
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
        alert(error.message);
    });
}

function unlinkPort(linkId) {
    if (!confirm('Remove this cable link?')) return;
    apiPost('link_delete.php', {csrf_token: CSRF, link_id: Number(linkId)})
        .then(() => location.reload())
        .catch(err => alert(err.message));
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
