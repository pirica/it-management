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
        'patch_panel' => '🧷 Patch Panel',
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
        "SELECT i.*, l.name AS location_name
         FROM idfs i
         JOIN it_locations l ON l.id=i.location_id
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
    "SELECT id, name, serial_number, notes, switch_rj45_id
     FROM equipment
     WHERE company_id=$company_id
     ORDER BY name ASC
     LIMIT 500"
);
while ($resEq && ($row = mysqli_fetch_assoc($resEq))) {
    $equipmentOptions[] = $row;
}

$ui_config = itm_get_ui_configuration($conn, $company_id);
$equipmentById = [];
foreach ($equipmentOptions as $equipment) {
    $equipmentById[(int)$equipment['id']] = [
        'name' => (string)($equipment['name'] ?? ''),
        'notes' => (string)($equipment['notes'] ?? ''),
        'switch_rj45_id' => (int)($equipment['switch_rj45_id'] ?? 0),
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>IDF View</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="container">
            <div class="idf-toolbar">
                <div class="left">
                    <a class="btn btn-sm" href="index.php">← Back</a>
                    <div style="display:flex; flex-direction:column;">
                        <div class="idf-rack-title">
                            🗄️ <?php echo sanitize($idf['name']); ?>
                            <?php if (!empty($idf['idf_code'])): ?><span class="idf-badge"><?php echo sanitize($idf['idf_code']); ?></span><?php endif; ?>
                        </div>
                        <div style="opacity:.85; font-size:12px;">
                            📍 <?php echo sanitize($idf['location_name']); ?>
                        </div>
                    </div>
                </div>
                <div class="right">
                    <span class="idf-badge idf-drag-hint">Drag &amp; drop to reorder (auto-save)</span>
                    <button class="btn btn-sm" type="button" onclick="idfExportExcel()">Export Excel</button>
                    <button class="btn btn-sm" type="button" onclick="idfExportImage()">Export Image</button>
                    <button class="btn btn-sm" type="button" onclick="idfExportPdf()">Export PDF</button>
                </div>
            </div>

            <div id="idfCaptureRoot" class="idf-rack-wrap">
                <div class="idf-rack">
                    <div class="idf-rack-header">
                        <div class="idf-rack-title">Rack Face (10 positions)</div>
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
                                                    <span class="idf-badge">🧾 Asset #<?php echo (int)$pos['equipment_id']; ?></span>
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
                <label class="label">Link to Equipment (optional)</label>
                <select class="input" name="equipment_id" data-addable-select="1" data-add-table="equipment" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="equipment" data-previous-value="">
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
                <input class="input" name="port_count" type="number" min="0" max="128" value="0">
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
const EQUIPMENT_BY_ID = <?php echo json_encode($equipmentById, JSON_UNESCAPED_UNICODE); ?>;

function closeModalIfBackdrop(e){ if(e.target.id === 'idfModalBackdrop') closeModal(); }
function closeModal(){ document.getElementById('idfModalBackdrop').style.display = 'none'; }
function openModal(){ document.getElementById('idfModalBackdrop').style.display = 'flex'; }

function closeCopyIfBackdrop(e){ if(e.target.id === 'idfCopyBackdrop') closeCopy(); }
function closeCopy(){ document.getElementById('idfCopyBackdrop').style.display = 'none'; }
function openCopy(){ document.getElementById('idfCopyBackdrop').style.display = 'flex'; }

async function apiPost(path, body) {
    const res = await fetch(`${IDF_BASE}/api/${path}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.error || `Request failed: ${res.status}`);
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
    form.equipment_id.dataset.previousValue = '';

    if (positionId) {
        apiPost('position_get.php', {csrf_token: CSRF, position_id: positionId})
            .then(({position}) => {
                form.device_type.value = position.device_type;
                form.device_name.value = position.device_name;
                form.equipment_id.value = position.equipment_id || '';
                form.equipment_id.dataset.previousValue = form.equipment_id.value || '';
                form.port_count.value = position.port_count || 0;
                form.notes.value = position.notes || '';
                applyEquipmentLink(form);
                openModal();
            })
            .catch(err => alert(err.message));
    } else {
        applyEquipmentLink(form);
        openModal();
    }
}

function applyEquipmentLink(form) {
    const equipmentId = Number(form.equipment_id.value || 0);
    if (equipmentId > 0 && EQUIPMENT_BY_ID[equipmentId]) {
        const equipment = EQUIPMENT_BY_ID[equipmentId];
        form.device_name.value = equipment.name || '';
        form.port_count.value = Number(equipment.switch_rj45_id || 0);
        form.notes.value = equipment.notes || '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('idfDeviceForm');
    if (!form) return;
    form.equipment_id.addEventListener('change', () => {
        if (form.equipment_id.value === '__add_new__') return;
        applyEquipmentLink(form);
    });
});

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
        port_count: Number(form.port_count.value || 0),
        notes: form.notes.value.trim(),
    };
    apiPost('position_save.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function openCopyModal(positionNo, positionId) {
    document.getElementById('idfCopyPositionId').value = positionId;
    document.getElementById('idfCopyTarget').value = positionNo;
    openCopy();
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
</body>
</html>
