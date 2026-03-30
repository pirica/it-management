<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
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
    $res = mysqli_query(
        $conn,
        "SELECT p.*, i.name AS idf_name, i.id AS idf_id
         FROM idf_positions p
         JOIN idfs i ON i.id=p.idf_id
         WHERE p.id=$position_id AND i.company_id=$company_id
         LIMIT 1"
    );
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
       CASE
         WHEN l.port_id_a = pr.id THEN l.port_id_b
         WHEN l.port_id_b = pr.id THEN l.port_id_a
         ELSE NULL
       END AS other_port_id
     FROM idf_ports pr
     LEFT JOIN idf_links l ON (l.port_id_a = pr.id OR l.port_id_b = pr.id)
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
           p.position_no,
           p.device_name,
           i.id AS idf_id
         FROM idf_ports pr
         JOIN idf_positions p ON p.id=pr.position_id
         JOIN idfs i ON i.id=p.idf_id
         WHERE pr.id IN ($list) AND i.company_id=$company_id"
    );
    while ($resOther && ($r = mysqli_fetch_assoc($resOther))) {
        $otherMap[(int)$r['port_id']] = $r;
    }
}

$ui_config = itm_get_ui_config($conn, $company_id);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Device</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/idf.css">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="container">
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
                            IDF: <?php echo sanitize($pos['idf_name']); ?>
                            <?php if (!empty($pos['equipment_id'])): ?> • Asset #<?php echo (int)$pos['equipment_id']; ?><?php endif; ?>
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
                        $linkText = '';
                        $unlinkBtn = '';
                        if (!empty($p['link_id']) && !empty($p['other_port_id']) && isset($otherMap[(int)$p['other_port_id']])) {
                            $o = $otherMap[(int)$p['other_port_id']];
                            $color = (string)($p['cable_color'] ?? 'yellow');
                            $label = !empty($p['cable_label']) ? (' • ' . sanitize((string)$p['cable_label'])) : '';
                            $linkText = '<span class="idf-swatch" style="background:' . sanitize($color) . '"></span>'
                                . 'Pos ' . (int)$o['position_no'] . ' • ' . sanitize($o['device_name']) . ' • Port ' . (int)$o['port_no'] . $label;
                            $unlinkBtn = '<button class="btn btn-sm" type="button" onclick="unlinkPort(' . (int)$p['link_id'] . ')">Unlink</button>';
                        }
                        ?>
                        <tr data-port-id="<?php echo (int)$p['id']; ?>">
                            <td><?php echo (int)$p['port_no']; ?></td>
                            <td><?php echo sanitize($p['port_type']); ?></td>
                            <td><?php echo sanitize((string)($p['label'] ?? '')); ?></td>
                            <td><?php echo sanitize($p['status']); ?></td>
                            <td><?php echo sanitize((string)($p['connected_to'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['vlan'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['speed'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['poe'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($p['notes'] ?? '')); ?></td>
                            <td><?php echo $linkText ?: '<span style="opacity:.75;">—</span>'; ?></td>
                            <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                <button class="btn btn-sm" type="button" onclick="openPortModal(<?php echo (int)$p['id']; ?>)">Edit</button>
                                <button class="btn btn-sm" type="button" onclick="selectForLink(<?php echo (int)$p['id']; ?>)">Link</button>
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
            <input type="hidden" name="port_id_b" value="">
            <div>
                <label class="label">Cable color</label>
                <input class="input" name="cable_color" placeholder="e.g. yellow, blue, #ffcc00" value="yellow">
            </div>
            <div>
                <label class="label">Cable label (optional)</label>
                <input class="input" name="cable_label" placeholder="e.g. FIB-12 / CAT6-34">
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="label">Notes (optional)</label>
                <input class="input" name="notes" placeholder="Optional">
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

let linkFirstPortId = null;

function rowByPortId(pid) {
    return document.querySelector(`tr[data-port-id="${pid}"]`);
}

function clearLinkSelection() {
    if (linkFirstPortId) {
        const r = rowByPortId(linkFirstPortId);
        if (r) r.classList.remove('idf-port-selected');
    }
    linkFirstPortId = null;
}

function selectForLink(portId) {
    const row = rowByPortId(portId);
    if (!row) return;

    if (!linkFirstPortId) {
        clearLinkSelection();
        linkFirstPortId = portId;
        row.classList.add('idf-port-selected');
        return;
    }

    if (linkFirstPortId === portId) {
        clearLinkSelection();
        return;
    }

    const f = document.getElementById('linkForm');
    f.port_id_a.value = linkFirstPortId;
    f.port_id_b.value = portId;
    f.cable_color.value = 'yellow';
    f.cable_label.value = '';
    f.notes.value = '';
    document.getElementById('linkBackdrop').style.display = 'flex';
}

function closeLinkModal() {
    document.getElementById('linkBackdrop').style.display = 'none';
    clearLinkSelection();
}

function createLink() {
    const f = document.getElementById('linkForm');
    const payload = {
        csrf_token: CSRF,
        port_id_a: Number(f.port_id_a.value),
        port_id_b: Number(f.port_id_b.value),
        cable_color: f.cable_color.value.trim() || 'yellow',
        cable_label: f.cable_label.value.trim(),
        notes: f.notes.value.trim(),
    };

    apiPost('link_create.php', payload)
        .then(() => location.reload())
        .catch(err => alert(err.message));
}

function unlinkPort(linkId) {
    if (!confirm('Remove this cable link?')) return;
    apiPost('link_delete.php', {csrf_token: CSRF, link_id: Number(linkId)})
        .then(() => location.reload())
        .catch(err => alert(err.message));
}
</script>
</body>
</html>
