<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$position_id = (int)($_GET['position_id'] ?? 0);

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
        'SELECT p.*, i.name AS idf_name, i.id AS idf_id,
                COALESCE(e.is_switch, 0) AS equipment_is_switch
         FROM idf_positions p
         JOIN idfs i ON i.id = p.idf_id
         LEFT JOIN equipment e ON e.id = p.equipment_id
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
$resPorts = mysqli_query(
    $conn,
    "SELECT
       pr.*,
       l.id AS link_id,
       l.cable_color,
       l.cable_label,
       l.notes AS link_notes,
       l.port_id_b AS other_port_id
     FROM idf_ports pr
     LEFT JOIN idf_links l ON l.port_id_a = pr.id
     WHERE pr.position_id=$position_id
     ORDER BY pr.port_no ASC"
);
while ($resPorts && ($row = mysqli_fetch_assoc($resPorts))) {
    $ports[] = $row;
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
        'cable_color' => (string)($p['cable_color'] ?? 'yellow'),
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
     JOIN idfs i ON i.id = p.idf_id
     WHERE p.idf_id = ?
       AND i.company_id = ?
       AND p.id <> ?
     ORDER BY p.position_no ASC, pr.port_no ASC"
);
if ($stmtDestinationPorts) {
    mysqli_stmt_bind_param($stmtDestinationPorts, 'iii', $pos['idf_id'], $company_id, $position_id);
    mysqli_stmt_execute($stmtDestinationPorts);
    $resDestinationPorts = mysqli_stmt_get_result($stmtDestinationPorts);
    while ($resDestinationPorts && ($row = mysqli_fetch_assoc($resDestinationPorts))) {
        $destinationPorts[] = [
            'id' => (int)($row['id'] ?? 0),
            'port_no' => (int)($row['port_no'] ?? 0),
            'label' => (string)($row['label'] ?? ''),
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
$resCableColors = mysqli_query(
    $conn,
    "SELECT color
     FROM switch_cablecolors
     WHERE company_id = $company_id
     ORDER BY color ASC"
);
while ($resCableColors && ($row = mysqli_fetch_assoc($resCableColors))) {
    $color = trim((string)($row['color'] ?? ''));
    if ($color !== '') {
        $cableColorOptions[] = $color;
    }
}
if (!in_array('yellow', $cableColorOptions, true)) {
    $cableColorOptions[] = 'yellow';
}
sort($cableColorOptions, SORT_NATURAL | SORT_FLAG_CASE);

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

        <div class="content">
            <div class="idf-toolbar">
                <div class="left">
                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$pos['idf_id']; ?>">← Back</a>
                    <div style="display:flex; flex-direction:column;">
                        <div class="idf-rack-title">
                            🔧 <?php echo sanitize($pos['device_name']); ?>
                            <span class="idf-badge">Position <?php echo (int)$pos['position_no']; ?></span>
                            <span class="idf-badge"><?php echo sanitize((string)$pos['device_type']); ?></span>
                        </div>
                        <div style="opacity:.85; font-size:12px;">
                            IDF: <?php echo sanitize((string)$pos['idf_name']); ?>
                            <?php if (!empty($pos['equipment_id'])): ?> • Asset ID <?php echo sanitize((string)$pos['equipment_id']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="right">
                    <button class="btn btn-sm" type="button" onclick="idfPortsExportExcel()">Export Ports Excel</button>
                </div>
            </div>

            <div class="card" style="padding:14px; border-radius:18px;">
                <h3 style="margin-top:0;">🔌 Ports</h3>

                <?php if ((int)$pos['port_count'] > 0 && count($ports) === 0): ?>
                    <div class="alert alert-error">Port list is empty. Use “Regenerate Ports”.</div>
                <?php endif; ?>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                    <button class="btn btn-sm" type="button" onclick="regeneratePorts()">Regenerate Ports</button>
                </div>

                <table id="portsTable" class="table idf-ports-table">
                    <thead>
                    <tr>
                        <th>#</th><th>Type</th><th>Label</th><th>Status</th><th>Connected To</th><th>VLAN</th><th>Speed</th><th>PoE</th><th>Notes</th><th>Link</th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$ports): ?>
                        <tr><td colspan="11" style="opacity:.8;">No ports yet.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($ports as $p): ?>
                        <?php
                        $equipmentIsSwitch = (int)($pos['equipment_is_switch'] ?? 0) === 1;
                        $canEditLinkedSwitch = $equipmentIsSwitch
                            && !empty($pos['equipment_id'])
                            && !empty($p['link_id']);
                        $editLinkedUrl = '../equipment/index.php?switch_id=' . (int)$pos['equipment_id'] . '#switch-port-manager';
                        $linkText = '';
                        $connectedToText = trim((string)($p['connected_to'] ?? ''));
                        $unlinkBtn = '';
                        if (!empty($p['link_id']) && !empty($p['other_port_id']) && isset($otherMap[(int)$p['other_port_id']])) {
                            $o = $otherMap[(int)$p['other_port_id']];
                            $color = (string)($p['cable_color'] ?? 'yellow');
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
                        <tr data-port-id="<?php echo (int)$p['id']; ?>">
                            <td><?php echo (int)$p['port_no']; ?></td>
                            <td><?php echo sanitize($p['port_type']); ?></td>
                            <td><?php echo sanitize((string)($p['label'] ?? '')); ?></td>
                            <td><?php echo sanitize($p['status']); ?></td>
                            <td><?php echo sanitize($connectedToText); ?></td>
                            <td><?php echo sanitize((string)($p['vlan'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['speed'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['poe'] ?? '')); ?></td>
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
                                    <span class="idf-swatch" style="background:<?php echo sanitize($row['cable_color']); ?>"></span>
                                    <?php echo sanitize($row['cable_color']); ?>
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
                        <td><?php echo sanitize($p['port_type']); ?></td>
                        <td><?php echo sanitize((string)($p['label'] ?? '')); ?></td>
                        <td><?php echo sanitize($p['status']); ?></td>
                        <td><?php echo sanitize((string)($p['connected_to'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['vlan'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['speed'] ?? '')); ?></td>
                        <td><?php echo sanitize((string)($p['poe'] ?? '')); ?></td>
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
                    <option>RJ45</option><option>SFP</option><option>SFP+</option><option>LC</option><option>SC</option><option>OTHER</option>
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select class="input" name="status">
                    <option>unknown</option><option>free</option><option>used</option><option>reserved</option><option>down</option>
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
                <input class="input" name="vlan" placeholder="e.g. 10 / Voice / 20">
            </div>
            <div>
                <label class="label">Speed</label>
                <input class="input" name="speed" placeholder="e.g. 1G / 10G">
            </div>
            <div>
                <label class="label">PoE</label>
                <input class="input" name="poe" placeholder="e.g. af/at/bt">
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
                    <span id="cableColorSwatch" class="idf-swatch" style="width:16px; height:16px; border:1px solid #d9d9d9; background:yellow; flex:0 0 auto;"></span>
                    <select class="input" name="cable_color" data-add-table="switch_cablecolors" style="flex:1 1 auto;">
                        <?php foreach ($cableColorOptions as $cableColor): ?>
                            <option value="<?php echo sanitize($cableColor); ?>" <?php echo $cableColor === 'yellow' ? 'selected' : ''; ?>>
                                <?php echo sanitize($cableColor); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__add_new__">➕ Add</option>
                    </select>
                </div>
            </div>
            <div data-link-default-field="cable_label">
                <label class="label">Cable label (optional)</label>
                <input class="input" name="cable_label" placeholder="e.g. FIB-12 / CAT6-34">
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
                        <input class="input" type="color" name="linked_cable_color_picker" value="#ffff00" style="height:40px; padding:4px;">
                        <input class="input" name="linked_cable_color" placeholder="e.g. yellow, blue, #ffcc00" value="yellow" style="margin-top:8px;">
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

<script>
const IDF_BASE = '<?php echo BASE_URL; ?>modules/idfs';
const CSRF = '<?php echo sanitize($csrf); ?>';
const POSITION_ID = <?php echo (int)$position_id; ?>;
const PORTS = <?php
$portsMeta = array_map(static function (array $port): array {
    return [
        'id' => (int)($port['id'] ?? 0),
        'port_no' => (int)($port['port_no'] ?? 0),
        'label' => (string)($port['label'] ?? ''),
        'is_linked' => !empty($port['link_id']),
    ];
}, $ports);
echo json_encode($portsMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>;
const DESTINATION_PORTS = <?php echo json_encode($destinationPorts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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
    const cols = row ? row.querySelectorAll('td') : [];
    const form = document.getElementById('portForm');

    form.port_id.value = portId;
    form.port_type.value = cols[1]?.textContent?.trim() || 'RJ45';
    form.label.value = cols[2]?.textContent?.trim() || '';
    form.status.value = cols[3]?.textContent?.trim() || 'unknown';
    form.connected_to.value = cols[4]?.textContent?.trim() || '';
    form.vlan.value = cols[5]?.textContent?.trim() || '';
    form.speed.value = cols[6]?.textContent?.trim() || '';
    form.poe.value = cols[7]?.textContent?.trim() || '';
    form.notes.value = cols[8]?.textContent?.trim() || '';

    document.getElementById('portBackdrop').style.display = 'flex';
}

function closePortModal() {
    document.getElementById('portBackdrop').style.display = 'none';
}

function savePort() {
    const f = document.getElementById('portForm');
    const payload = {
        csrf_token: CSRF,
        port_id: Number(f.port_id.value),
        port_type: f.port_type.value,
        label: f.label.value.trim(),
        status: f.status.value,
        connected_to: f.connected_to.value.trim(),
        vlan: f.vlan.value.trim(),
        speed: f.speed.value.trim(),
        poe: f.poe.value.trim(),
        notes: f.notes.value.trim(),
    };
    apiPost('port_update.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
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
    const destinations = DESTINATION_PORTS.filter(p => !p.is_linked);

    destinationSelect.innerHTML = '<option value="">Select destination port</option>';
    destinations.forEach((port) => {
        const option = document.createElement('option');
        option.value = String(port.id);
        option.textContent = `Pos ${port.position_no} • ${port.device_name} • Port ${port.port_no}${port.label ? ` • ${port.label}` : ''}`;
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
    f.cable_color.value = 'yellow';
    f.cable_label.value = '';
    f.notes.value = '';
    f.equipment_id.value = '';
    f.switch_port_id.innerHTML = '<option value="">Select equipment first</option>';
    f.switch_port_id.disabled = true;
    toggleLinkedEquipmentFields(false);
    updateCableColorSwatch(f.cable_color.value || 'yellow');
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
        const selectedEquipmentPorts = allAvailableDestinationPorts.filter((port) => Number(port.equipment_id) === selectedEquipmentId);
        const candidateDestinationPorts = selectedEquipmentPorts.length ? selectedEquipmentPorts : allAvailableDestinationPorts;
        const matchingPort = candidateDestinationPorts.find((port) => Number(port.port_no) === normalizedLinkedPortNo);

        if (!candidateDestinationPorts.length) {
            alert('No available destination ports were found.');
            return;
        }
        const preselectedDestination = candidateDestinationPorts.find((port) => Number(port.id) === destinationPortId);
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

    const defaultCableColor = (f.cable_color.value && f.cable_color.value !== '__add_new__')
        ? f.cable_color.value.trim()
        : 'yellow';
    const cableColor = linkedMode ? (f.linked_cable_color.value.trim() || 'yellow') : (defaultCableColor || 'yellow');
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
        cable_color: cableColor,
        cable_label: cableLabel,
        notes,
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
    probe.style.color = colorValue || 'yellow';
    document.body.appendChild(probe);
    const normalized = getComputedStyle(probe).color;
    document.body.removeChild(probe);
    const match = normalized.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/i);
    if (!match) return '#ffff00';
    return `#${[match[1], match[2], match[3]].map((part) => Number(part).toString(16).padStart(2, '0')).join('')}`;
}

function updateCableColorSwatch(colorValue) {
    const swatch = document.getElementById('cableColorSwatch');
    if (!swatch) return;
    swatch.style.background = colorValue || 'yellow';
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
    f.linked_cable_color.value = port.equipment_color || 'yellow';
    f.linked_cable_color_picker.value = normalizeColorToHex(port.equipment_color || 'yellow');
    f.linked_cable_label.value = port.equipment_label || '';
    f.linked_notes.value = port.equipment_comments || '';
    toggleLinkedEquipmentFields(true);
}

document.addEventListener('DOMContentLoaded', () => {
    const f = document.getElementById('linkForm');
    if (!f || !f.equipment_id) return;
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
        f.linked_cable_color_picker.value = normalizeColorToHex(event.target.value || 'yellow');
    });
    if (f.cable_color) {
        updateCableColorSwatch(f.cable_color.value || 'yellow');
        f.cable_color.addEventListener('change', (event) => {
            const selected = event.target.value || '';
            if (selected === '__add_new__') {
                window.open(`${IDF_BASE.replace('/idfs', '/switch_cablecolors')}/create.php`, '_blank');
                event.target.value = 'yellow';
                updateCableColorSwatch('yellow');
                return;
            }
            updateCableColorSwatch(selected || 'yellow');
        });
    }
});

function unlinkPort(linkId) {
    if (!confirm('Remove this cable link?')) return;
    apiPost('link_delete.php', {csrf_token: CSRF, link_id: Number(linkId)})
        .then(() => location.reload())
        .catch(err => alert(err.message));
}
</script>
</body>
</html>
