<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$idf_id = (int)($_GET['id'] ?? 0);

function idf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function idf_type_badge(string $t): string {
    return match ($t) {
        'switch' => '🔀 Switch',
        'patch_panel' => '➰ Patch Panel',
        'ups' => '🔋 UPS',
        'server' => '🖥️ Server',
        default => '📦 Other',
    };
}

$csrf = idf_csrf_token();

$idf = null;
if ($idf_id > 0 && $company_id > 0) {
    $res = mysqli_query(
        $conn,
        "SELECT i.*, l.name AS location_name, c.company AS company_name
         FROM idfs i
         JOIN it_locations l ON l.id=i.location_id
         LEFT JOIN companies c ON c.id=i.company_id
         WHERE i.id=$idf_id AND i.company_id=$company_id
         LIMIT 1"
    );
    $idf = $res ? mysqli_fetch_assoc($res) : null;
}

if (!$idf) {
    $_SESSION['crud_error'] = 'IDF not found.';
    header('Location: index.php');
    exit;
}

$positions = array_fill(1, 10, null);
$resPos = mysqli_query($conn, "SELECT * FROM idf_positions WHERE idf_id=$idf_id ORDER BY position_no ASC");
while ($resPos && ($row = mysqli_fetch_assoc($resPos))) {
    $positions[(int)$row['position_no']] = $row;
}

$equipmentOptions = [];
$resEq = mysqli_query(
    $conn,
    "SELECT e.id, e.name, e.serial_number, e.notes, e.switch_rj45_id, er.name AS switch_rj45_name
     FROM equipment e
     LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
     WHERE e.company_id=$company_id
     ORDER BY e.name ASC
     LIMIT 500"
);
while ($resEq && ($row = mysqli_fetch_assoc($resEq))) {
    $equipmentOptions[] = $row;
}

$switchRj45Options = [];
$resRj45 = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_rj45
     WHERE company_id=$company_id
     ORDER BY name ASC"
);
while ($resRj45 && ($row = mysqli_fetch_assoc($resRj45))) {
    $switchRj45Options[] = $row;
}

$equipmentTypeOptions = [];
$switchEquipmentTypeId = 0;
$resEqTypes = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_types
     WHERE company_id=$company_id
     ORDER BY name ASC"
);
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

$equipmentStatusFieldOptions = [];
$resEquipmentStatuses = mysqli_query(
    $conn,
    "SELECT id, name
     FROM equipment_statuses
     WHERE company_id=$company_id
     ORDER BY name ASC"
);
while ($resEquipmentStatuses && ($row = mysqli_fetch_assoc($resEquipmentStatuses))) {
    $equipmentStatusFieldOptions[] = [
        'value' => (int)($row['id'] ?? 0),
        'label' => (string)($row['name'] ?? ''),
    ];
}

$switchRj45FieldOptions = [];
foreach ($switchRj45Options as $switchRj45Option) {
    $switchRj45FieldOptions[] = [
        'value' => (int)($switchRj45Option['id'] ?? 0),
        'label' => (string)($switchRj45Option['name'] ?? ''),
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
                                🗄️ <?php echo sanitize($idf['name']); ?>
                                <?php if (!empty($idf['idf_code'])): ?><span class="idf-badge"><?php echo sanitize($idf['idf_code']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div style="opacity:.85; font-size:12px;">
                            📍 <?php echo sanitize($idf['location_name']); ?> · <span class="idf-badge idf-drag-hint">Drag &amp; drop enabled</span>
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
                                <div class="idf-rack-title">Rack Face (10 positions)</div>
                                <div style="font-size:12px; opacity:.8; margin-top:2px;">
                                    <?php echo sanitize((string)($idf['company_name'] ?? 'Unknown Company')); ?>
                                    · Location: <?php echo sanitize((string)($idf['location_name'] ?? 'Unknown Location')); ?>
                                    · Name: <?php echo sanitize((string)($idf['name'] ?? '')); ?>
                                    · IDF Code: <?php echo sanitize((string)($idf['idf_code'] ?? 'N/A')); ?>
                                </div>
                            </div>
                            <span class="idf-badge">Move ↑ ↓ • Drag • Copy • Ports</span>
                        </div>

                        <div class="idf-slots" id="idfSlots">
                            <?php for ($i = 1; $i <= 10; $i++): $pos = $positions[$i]; ?>
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
                                                    <span class="idf-badge"><?php echo sanitize(idf_type_badge((string)$pos['device_type'])); ?></span>
                                                    <?php if ((int)$pos['port_count'] > 0): ?>
                                                        <span class="idf-badge">🔌 <?php echo (int)$pos['port_count']; ?> ports</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($pos['equipment_id'])): ?>
                                                        <span class="idf-badge">🧾 Asset ID <?php echo (int)$pos['equipment_id']; ?></span>
                                                    <?php endif; ?>
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
                <?php for ($i = 1; $i <= 10; $i++): $pos = $positions[$i]; ?>
                    <tr>
                        <td><?php echo $i; ?></td>
                        <td><?php echo $pos ? sanitize((string)$pos['device_type']) : ''; ?></td>
                        <td><?php echo $pos ? sanitize((string)$pos['device_name']) : ''; ?></td>
                        <td><?php echo $pos ? (int)$pos['port_count'] : 0; ?></td>
                        <td><?php echo $pos ? (int)($pos['equipment_id'] ?? 0) : 0; ?></td>
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
                    <option value="switch">Switch</option>
                    <option value="patch_panel">Patch Panel</option>
                    <option value="ups">UPS</option>
                    <option value="server">Server</option>
                    <option value="other">Other</option>
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
                            <?php echo sanitize($e['name'] . (!empty($e['serial_number']) ? (' • SN ' . $e['serial_number']) : '')); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__add_new__">➕</option>
                </select>
            </div>

            <div>
                <label class="label">Port Count</label>
                <input class="input" name="port_count" type="number" min="0" max="9999" step="1">
                <div id="idfSwitchRj45Wrap" style="display:none; margin-top:8px;">
                    <label class="label" style="margin-bottom:4px;">RJ45 Ports *</label>
                    <select class="input" name="switch_rj45_id" data-addable-select="1" data-add-table="equipment_rj45" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rj45 port option">
                        <option value="">-- Select --</option>
                        <?php foreach ($switchRj45Options as $switchRj45Option): ?>
                            <option value="<?php echo (int)$switchRj45Option['id']; ?>"><?php echo sanitize((string)$switchRj45Option['name']); ?></option>
                        <?php endforeach; ?>
                        <option value="__add_new__">➕</option>
                    </select>
                </div>
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
                    <?php for ($i = 1; $i <= 10; $i++): ?>
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

    if (positionId) {
        apiPost('position_get.php', {csrf_token: CSRF, position_id: positionId})
            .then(({position}) => {
                form.device_type.value = position.device_type;
                form.device_name.value = position.device_name;
                form.equipment_id.value = position.equipment_id || '';
                form.equipment_id.dataset.previousValue = form.equipment_id.value || '';
                form.switch_rj45_id.value = position.switch_rj45_id || '';
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
    form.port_count.value = meta.port_count ? String(meta.port_count) : '';
    form.notes.value = meta.notes || '';
    refreshPortCountInputs(form);

    if (shouldAlert) {
        alert('Device Name, Port Count, and Notes were filled from linked equipment.');
    }
}

function refreshPortCountInputs(form) {
    const isSwitch = form.device_type.value === 'switch';
    const hasLinkedEquipment = String(form.equipment_id.value || '') !== '';
    const switchWrap = document.getElementById('idfSwitchRj45Wrap');
    if (switchWrap) switchWrap.style.display = isSwitch ? 'block' : 'none';
    form.switch_rj45_id.required = isSwitch;
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
        for (let i = 1; i <= 10; i++) {
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

function idfExportExcel() {
    if (typeof XLSX === 'undefined') { alert('XLSX library not loaded.'); return; }
    const table = document.getElementById('idfExportTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: 'IDF'});
    XLSX.writeFile(wb, `idf-<?php echo (int)$idf_id; ?>.xlsx`);
}

async function idfExportImage() {
    const node = document.getElementById('idfCaptureRoot');
    const canvas = await html2canvas(node, {scale: 2});
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `idf-<?php echo (int)$idf_id; ?>.png`;
    a.click();
}

async function idfExportPdf() {
    const node = document.getElementById('idfCaptureRoot');
    const canvas = await html2canvas(node, {scale: 2});
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
