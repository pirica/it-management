<?php
require '../../config/config.php';

$hasSwitchFiberPortLabelColumn = false;
$hasSwitchFiberPortLabelColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment` LIKE 'switch_fiber_port_label'");
if ($hasSwitchFiberPortLabelColumnRes && mysqli_num_rows($hasSwitchFiberPortLabelColumnRes) > 0) {
    $hasSwitchFiberPortLabelColumn = true;
}
$switchFiberPortLabelSelect = $hasSwitchFiberPortLabelColumn
    ? "COALESCE(e.switch_fiber_port_label, '')"
    : "''";

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (
        CAST(e.id AS CHAR) LIKE '{$searchEsc}'
        OR e.name LIKE '{$searchEsc}'
        OR e.serial_number LIKE '{$searchEsc}'
        OR e.model LIKE '{$searchEsc}'
        OR e.hostname LIKE '{$searchEsc}'
        OR e.ip_address LIKE '{$searchEsc}'
        OR c.company LIKE '{$searchEsc}'
        OR et.name LIKE '{$searchEsc}'
        OR m.name LIKE '{$searchEsc}'
        OR l.name LIKE '{$searchEsc}'
        OR es.name LIKE '{$searchEsc}'
    )";
}

$sql = "SELECT e.id, e.name, e.serial_number, e.model, e.hostname, e.ip_address,
               c.company AS company_name,
               et.name AS equipment_type_name,
               m.name AS manufacturer_name,
               l.name AS location_name,
               es.name AS status_name
        FROM equipment e
        LEFT JOIN companies c ON c.id = e.company_id
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
        LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
        LEFT JOIN it_locations l ON l.id = e.location_id
        LEFT JOIN equipment_statuses es ON es.id = e.status_id
        WHERE e.company_id = $company_id
        {$searchSql}";
$sortableColumns = ['id', 'name', 'equipment_type_name', 'hostname', 'manufacturer_name', 'location_name', 'status_name', 'ip_address', 'serial_number'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$orderByMap = [
    'id' => 'e.id',
    'name' => 'e.name',
    'equipment_type_name' => 'et.name',
    'hostname' => 'e.hostname',
    'manufacturer_name' => 'm.name',
    'location_name' => 'l.name',
    'status_name' => 'es.name',
    'ip_address' => 'e.ip_address',
    'serial_number' => 'e.serial_number',
];
$sql .= ' ORDER BY ' . $orderByMap[$sort] . ' ' . $dir;
$result = mysqli_query($conn, $sql);

$switches = [];
$switchResult = mysqli_query(
    $conn,
    "SELECT e.id, e.name, COALESCE(e.hostname, '') AS hostname,
            COALESCE(er.name, '24 ports') AS rj45_name,
            COALESCE(ef.name, '') AS fiber_name,
            COALESCE(efc.name, '0') AS fiber_count,
            COALESCE(e.switch_fiber_ports_number, 0) AS fiber_ports_number,
            {$switchFiberPortLabelSelect} AS fiber_port_label,
            COALESCE(spnl.name, 'Vertical') AS port_numbering_layout
     FROM equipment e
     INNER JOIN equipment_types et ON et.id = e.equipment_type_id
     LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
     LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
     LEFT JOIN equipment_fiber_count efc ON efc.id = e.switch_fiber_count_id
     LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = e.switch_port_numbering_layout_id
     WHERE e.company_id = $company_id
       AND LOWER(TRIM(et.name)) LIKE '%switch%'
     ORDER BY e.name ASC"
);
while ($switchResult && ($row = mysqli_fetch_assoc($switchResult))) {
    $switches[] = $row;
}

$selectedSwitchId = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
$hasSelectedSwitch = false;
if ($selectedSwitchId > 0) {
    foreach ($switches as $switchItem) {
        if ((int)$switchItem['id'] === $selectedSwitchId) {
            $hasSelectedSwitch = true;
            break;
        }
    }
}
if (!$hasSelectedSwitch && !empty($switches)) {
    $selectedSwitchId = (int)$switches[0]['id'];
    $hasSelectedSwitch = true;
}
$selectedSwitchData = null;
if ($hasSelectedSwitch) {
    foreach ($switches as $switchItem) {
        if ((int)$switchItem['id'] === $selectedSwitchId) {
            $selectedSwitchData = $switchItem;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
<?php
if (!empty($_SESSION['crud_error'])) {
    echo '<div class="crud_error">' . htmlspecialchars($_SESSION['crud_error']) . '</div>';
    unset($_SESSION['crud_error']);
}

if (!empty($_SESSION['crud_success'])) {
    echo '<div class="crud_success">' . htmlspecialchars($_SESSION['crud_success']) . '</div>';
    unset($_SESSION['crud_success']);
}
?>
                <h1>🖥️ Equipment</h1>
                <a href="create.php" class="btn btn-primary">➕</a>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <?php if ($hasSelectedSwitch): ?>
                        <input type="hidden" name="switch_id" value="<?php echo (int)$selectedSwitchId; ?>">
                    <?php endif; ?>
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="equipmentSearch">Search (all fields)</label>
                        <input type="text" id="equipmentSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%switch%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php<?php echo $hasSelectedSwitch ? '?switch_id=' . (int)$selectedSwitchId : ''; ?>" class="btn btn-sm">Clear</a>
                    </div>
                </form>
            </div>

            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ([
                            'id' => 'ID',
                            'name' => 'Name',
                            'equipment_type_name' => 'Type',
                            'hostname' => 'Hostname',
                            'manufacturer_name' => 'Manufacturer',
                            'location_name' => 'Location',
                            'status_name' => 'Status',
                            'ip_address' => 'IP Address',
                            'serial_number' => 'Serial Number',
                        ] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?switch_id=<?php echo (int)$selectedSwitchId; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php $isSwitch = str_contains(strtolower(trim((string)($row['equipment_type_name'] ?? ''))), 'switch'); ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['name']); ?></td>
                                <td><?php echo sanitize($row['equipment_type_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['hostname'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['manufacturer_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['location_name'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $statusText = trim((string)($row['status_name'] ?? ''));
                                    $statusLower = strtolower($statusText);
                                    $statusBadgeClass = 'badge-warning';
                                    if ($statusLower === '' || $statusLower === '-') {
                                        $statusText = '-';
                                    } elseif (str_contains($statusLower, 'active') || str_contains($statusLower, 'online') || str_contains($statusLower, 'up')) {
                                        $statusBadgeClass = 'badge-success';
                                    } elseif (str_contains($statusLower, 'inactive') || str_contains($statusLower, 'offline') || str_contains($statusLower, 'down') || str_contains($statusLower, 'fail') || str_contains($statusLower, 'error')) {
                                        $statusBadgeClass = 'badge-danger';
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusBadgeClass; ?>"><?php echo sanitize($statusText); ?></span>
                                </td>
                                <td><?php echo sanitize($row['ip_address'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['serial_number'] ?? '-'); ?></td>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <?php if ($isSwitch): ?>
                                        <a class="btn btn-sm btn-primary" href="index.php?switch_id=<?php echo (int)$row['id']; ?><?php echo $searchRaw !== '' ? '&search=' . urlencode($searchRaw) : ''; ?>#switch-port-manager">Switch Port Manager</a>
                                    <?php endif; ?>
                                    <?php $deleteUrl = './delete.php?id=' . (int)$row['id']; ?>
<a class="btn btn-sm btn-danger" href="<?php echo $deleteUrl; ?>" onclick="return confirm('Delete this equipment?');">🗑️</a>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align:center;">No equipment records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($hasSelectedSwitch): ?>
                <div class="card" id="switch-port-manager" style="margin-top:20px;">
                    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                        <h2>Switch Port Manager</h2>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <button class="btn btn-sm" id="switchExportImageBtn" type="button" title="Export switch layout as image">Export Image</button>
                            <form method="get" style="display:flex;align-items:center;gap:8px;">
                                <?php if ($searchRaw !== ''): ?>
                                    <input type="hidden" name="search" value="<?php echo sanitize($searchRaw); ?>">
                                <?php endif; ?>
                                <label for="switchPicker" style="margin-bottom:0;">Switch:</label>
                                <select id="switchPicker" name="switch_id" onchange="this.form.submit()" style="min-width:240px;">
                                    <?php foreach ($switches as $switchItem): ?>
                                        <option value="<?php echo (int)$switchItem['id']; ?>" <?php echo (int)$switchItem['id'] === $selectedSwitchId ? 'selected' : ''; ?>>
                                            <?php echo sanitize($switchItem['name']) . ' (#' . (int)$switchItem['id'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="switch-manager" id="switchManager">
                        <div class="card" style="margin-bottom:14px;padding:12px;">
                            <strong>Switch layout:</strong>
                            <span id="switchLayoutSummary">Loading…</span>
                        </div>
                        <div class="switch-grid">
                            <div class="switch-row" id="switchRow1"></div>
                            <div class="switch-row" id="switchRow2"></div>
                        </div>
                        <div class="switch-grid" id="fiberGrid" style="margin-top:10px;">
                            <div>
                                <div id="switchSfpLabel" style="margin-bottom:6px;font-weight:600;">SFP Ports</div>
                                <div class="switch-row" id="switchSfpRow"></div>
                            </div>
                            <div>
                                <div id="switchSfpPlusLabel" style="margin-bottom:6px;font-weight:600;">SFP+ Ports</div>
                                <div class="switch-row" id="switchSfpPlusRow"></div>
                            </div>
                        </div>

                        <div class="switch-controls">
                            <label><strong>Selected port:</strong> <span id="selectedPort">None</span></label>
                            <label>
                                Color:
                                <div class="switch-control-input">
                                    <select id="colorSelect"
                                        data-addable-select="1"
                                        data-add-table="switch_cablecolors"
                                        data-add-id-col="id"
                                        data-add-label-col="color"
                                        data-add-company-scoped="1"
                                        data-add-friendly="cable color">
                                        <option value="">-- choose color --</option>
                                    </select>
                                </div>
                            </label>
                            <label>
                                Status:
                                <div class="switch-control-input">
                                    <select id="statusSelect"
                                        data-addable-select="1"
                                        data-add-table="switch_status"
                                        data-add-id-col="id"
                                        data-add-label-col="status"
                                        data-add-company-scoped="1"
                                        data-add-friendly="switch status">
                                        <option value="">-- choose status --</option>
                                    </select>
                                </div>
                            </label>
                            <label>
                                Patch port:
                                <input type="text" id="labelInput" placeholder="Patch port">
                            </label>
                            <label>
                                VLAN:
                                <div class="switch-control-input">
                                    <select id="vlanSelect"
                                        data-addable-select="1"
                                        data-add-table="vlans"
                                        data-add-id-col="id"
                                        data-add-label-col="vlan_name"
                                        data-add-company-scoped="1"
                                        data-add-friendly="vlan"
                                        data-add-extra-fields='[{"name":"vlan_number","label":"VLAN Number","type":"number"},{"name":"vlan_color","label":"Vlan Color","type":"color"}]'>
                                        <option value="">-- choose VLAN --</option>
                                    </select>
                                </div>
                            </label>
                            <label>
                                Comments:
                                <input type="text" id="commentsInput" placeholder="Comments">
                            </label>
                            <button class="btn btn-primary" id="savePortBtn" type="button">💾</button>
                        </div>

                        <div class="switch-legend" id="switchLegend"></div>
                    </div>
                    <div class="switch-tooltip" id="switchTooltip"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<?php if ($hasSelectedSwitch): ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
    (function () {
        const apiGet = '<?php echo BASE_URL; ?>get_ports.php';
        const apiUpdate = '<?php echo BASE_URL; ?>update_port.php';
        const selectedSwitchId = <?php echo (int)$selectedSwitchId; ?>;
        const selectedSwitchMeta = <?php echo json_encode($selectedSwitchData ?? []); ?>;
        let ports = [];
        let colorOptions = [];
        let statusOptions = [];
        let vlanOptions = [];
        let availablePortTypes = ['rj45', 'sfp', 'sfp_plus'];
        let selected = null;
        const tooltip = document.getElementById('switchTooltip');
        const csrfToken = window.ITM_CSRF_TOKEN
            || (document.querySelector('input[name="csrf_token"]') || {}).value
            || '';

        function hasPortType(type) {
            return availablePortTypes.indexOf(type) !== -1;
        }

        function buildLayoutSummary(layoutLabel, rj45Count, sfpCount, sfpPlusCount) {
            const parts = ['Layout: ' + layoutLabel, 'RJ45: ' + rj45Count];
            if (hasPortType('sfp')) {
                parts.push('SFP: ' + sfpCount);
            }
            if (hasPortType('sfp_plus')) {
                parts.push('SFP+: ' + sfpPlusCount);
            }
            return parts.join(' | ');
        }

        function normalizePortType(portType) {
            const normalized = String(portType || 'rj45')
                .trim()
                .toLowerCase()
                .replace(/\+/g, '_plus')
                .replace(/\s+/g, '_');

            if (normalized === 'sfpplus') {
                return 'sfp_plus';
            }
            if (normalized === 'sfp_plus') {
                return 'sfp_plus';
            }
            if (normalized === 'sfp') {
                return 'sfp';
            }
            return 'rj45';
        }

        async function exportSwitchImage() {
            const node = document.getElementById('switchManager');
            if (!node) return;

            if (typeof html2canvas === 'undefined') {
                alert('Image export library is not loaded.');
                return;
            }

            try {
                const canvas = await html2canvas(node, {scale: 2, backgroundColor: '#ffffff'});
                const anchor = document.createElement('a');
                const rawHostname = String((selectedSwitchMeta && selectedSwitchMeta.hostname) || '').trim();
                const safeHostname = rawHostname
                    .toLowerCase()
                    .replace(/[^a-z0-9._-]+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                const fileBase = safeHostname !== '' ? safeHostname : `switch-${selectedSwitchId}`;
                anchor.href = canvas.toDataURL('image/png');
                anchor.download = `${fileBase}.png`;
                anchor.click();
            } catch (error) {
                console.error(error);
                alert('Unable to export image right now. Please try again.');
            }
        }

        const switchExportImageBtn = document.getElementById('switchExportImageBtn');
        if (switchExportImageBtn) {
            switchExportImageBtn.addEventListener('click', exportSwitchImage);
        }

        function getColorCss(color) {
            switch (color) {
                case 'green': return 'green';
                case 'red': return 'red';
                case 'yellow': return 'gold';
                case 'black': return '#111';
                case 'blue': return 'royalblue';
                case 'white': return '#fff';
                case 'orange': return 'orange';
                case 'purple': return 'purple';
                case 'grey':
                case 'gray': return '#9ca3af';
                case 'other': return 'lightgray';
                default: return 'transparent';
            }
        }

        function getPortNumberColor(color) {
            switch (color) {
                case 'black':
                case 'blue':
                case 'purple':
                case 'red':
                case 'green':
                    return '#fff';
                case 'grey':
                case 'gray':
                    return '#0b1220';
                default:
                    return '#111';
            }
        }

        function hasUpStatus(status) {
            return String(status || '').trim().toLowerCase() === 'up';
        }

        function paintPort(el, color) {
            const normalizedColor = color || 'black';
            const indicator = el.querySelector('.switch-color-indicator');
            const number = el.querySelector('.switch-port-num');
            indicator.style.background = getColorCss(normalizedColor);
            number.style.color = getPortNumberColor(normalizedColor);
            el.dataset.color = normalizedColor;
        }

        function paintVlan(el, vlanId) {
            const vlanIndicator = el.querySelector('.switch-vlan-indicator');
            if (!vlanIndicator) {
                return;
            }
            const hasVlan = String(vlanId || '').trim() !== '';
            const vlanColor = String(el.dataset.vlanColor || '').trim();
            const isHexColor = /^#[A-Fa-f0-9]{6}$/.test(vlanColor);
            vlanIndicator.style.background = hasVlan ? (isHexColor ? vlanColor : '#FFED00') : 'transparent';
        }

        function paintStatusTag(el, status) {
            const tag = el.querySelector('.switch-status-tag');
            if (!tag) {
                return;
            }
            tag.style.display = hasUpStatus(status) ? 'inline-block' : 'none';
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (m) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m];
            });
        }

        function moveTooltip(ev) {
            const pad = 12;
            let x = ev.clientX + pad;
            let y = ev.clientY + pad;
            const rect = tooltip.getBoundingClientRect();
            if (x + rect.width > window.innerWidth) x = ev.clientX - rect.width - pad;
            if (y + rect.height > window.innerHeight) y = ev.clientY - rect.height - pad;
            tooltip.style.left = x + 'px';
            tooltip.style.top = y + 'px';
        }

        function showTooltip(ev, el) {
            const label = el.dataset.label || '—';
            const status = el.dataset.status || 'unknown';
            const comments = el.dataset.comments || '';
            const vlanName = el.dataset.vlanName || '—';
            const portType = normalizePortType(el.dataset.portType).replace('_', '+').toUpperCase();
            tooltip.innerHTML = '<strong>' + escapeHtml(portType) + ' Port ' + el.dataset.portNumber + '</strong><br>'
                + 'Patch port: ' + escapeHtml(label) + '<br>'
                + 'Status: ' + escapeHtml(status) + '<br>'
                + 'VLAN: ' + escapeHtml(vlanName) + '<br>'
                + 'Comments: ' + escapeHtml(comments);
            tooltip.style.opacity = '1';
            moveTooltip(ev);
        }

        function hideTooltip() {
            tooltip.style.opacity = '0';
        }

        function selectPort(el) {
            document.querySelectorAll('.switch-port').forEach(function (p) {
                p.style.outline = '';
            });
            el.style.outline = '3px solid rgba(9, 105, 218, 0.35)';
            selected = el;
            document.getElementById('selectedPort').textContent = el.dataset.portNumber;
            document.getElementById('colorSelect').value = el.dataset.color || '';
            document.getElementById('statusSelect').value = el.dataset.status || '';
            document.getElementById('labelInput').value = el.dataset.label || '';
            document.getElementById('vlanSelect').value = el.dataset.vlanId || '';
            document.getElementById('commentsInput').value = el.dataset.comments || '';
        }

        function createPortElement(p) {
            const el = document.createElement('div');
            el.className = 'switch-port';
            el.dataset.id = p.id;
            el.dataset.portNumber = p.port_number;
            el.dataset.portType = normalizePortType(p.port_type);
            el.dataset.label = p.label || '';
            el.dataset.status = p.status || 'unknown';
            el.dataset.comments = p.comments || '';
            el.dataset.vlanId = p.vlan_id || '';
            el.dataset.vlanName = p.vlan_name || '';
            el.dataset.vlanColor = p.vlan_color || '';
            el.dataset.color = p.color || 'black';

            const num = document.createElement('div');
            num.className = 'switch-port-num';
            num.textContent = p.port_number;
            el.appendChild(num);

            const vlanDiv = document.createElement('div');
            vlanDiv.className = 'switch-vlan-indicator';
            el.appendChild(vlanDiv);

            const frameDiv = document.createElement('div');
            frameDiv.className = 'switch-port-frame';
            el.appendChild(frameDiv);

            const colorDiv = document.createElement('div');
            colorDiv.className = 'switch-color-indicator';
            colorDiv.style.background = getColorCss(p.color);
            num.style.color = getPortNumberColor(p.color);
            el.appendChild(colorDiv);

            const statusTag = document.createElement('div');
            statusTag.className = 'switch-status-tag';
            statusTag.textContent = 'UP';
            statusTag.style.display = hasUpStatus(p.status) ? 'inline-block' : 'none';
            el.appendChild(statusTag);

            paintVlan(el, p.vlan_id);

            el.addEventListener('mouseenter', function (ev) { showTooltip(ev, el); });
            el.addEventListener('mousemove', moveTooltip);
            el.addEventListener('mouseleave', hideTooltip);
            el.addEventListener('click', function () { selectPort(el); });

            return el;
        }

        function renderPorts() {
            const row1 = document.getElementById('switchRow1');
            const row2 = document.getElementById('switchRow2');
            const sfpRow = document.getElementById('switchSfpRow');
            const sfpPlusRow = document.getElementById('switchSfpPlusRow');
            row1.innerHTML = '';
            row2.innerHTML = '';
            sfpRow.innerHTML = '';
            sfpPlusRow.innerHTML = '';

            const rj45Ports = ports
                .filter(function (p) { return normalizePortType(p.port_type) === 'rj45'; })
                .sort(function (a, b) { return Number(a.port_number) - Number(b.port_number); });

            rj45Ports.forEach(function (p) {
                const el = createPortElement(p);
                const portNumber = Number(p.port_number);
                const portLayout = String((selectedSwitchMeta && selectedSwitchMeta.port_numbering_layout) || 'Vertical').trim().toLowerCase();
                if (portLayout === 'horizontal') {
                    const splitPoint = Math.ceil(rj45Ports.length / 2);
                    if (portNumber <= splitPoint) {
                        row1.appendChild(el);
                        return;
                    }
                    row2.appendChild(el);
                    return;
                }
                if (portNumber % 2 === 1) {
                    row1.appendChild(el);
                    return;
                }
                row2.appendChild(el);
            });

            const switchManager = document.getElementById('switchManager');
            switchManager.style.setProperty('--rj45-row-count', '12');
            switchManager.style.setProperty('--switch-port-size', rj45Ports.length > 25 ? '55.5px' : '74px');
            switchManager.classList.remove('switch-manager-compact', 'switch-manager-half');

            const sfpPorts = ports.filter(function (p) { return normalizePortType(p.port_type) === 'sfp'; });
            sfpPorts.forEach(function (p) { sfpRow.appendChild(createPortElement(p)); });
            const sfpPlusPorts = ports.filter(function (p) { return normalizePortType(p.port_type) === 'sfp_plus'; });
            sfpPlusPorts.forEach(function (p) { sfpPlusRow.appendChild(createPortElement(p)); });
            document.getElementById('switchSfpLabel').style.display = hasPortType('sfp') ? 'block' : 'none';
            sfpRow.style.display = hasPortType('sfp') ? 'flex' : 'none';
            document.getElementById('switchSfpPlusLabel').style.display = hasPortType('sfp_plus') ? 'block' : 'none';
            sfpPlusRow.style.display = hasPortType('sfp_plus') ? 'flex' : 'none';

            const layoutLabel = String((selectedSwitchMeta && selectedSwitchMeta.port_numbering_layout) || 'Vertical');
            document.getElementById('switchLayoutSummary').textContent = buildLayoutSummary(layoutLabel, rj45Ports.length, sfpPorts.length, sfpPlusPorts.length);
            document.getElementById('fiberGrid').style.display = (hasPortType('sfp') && sfpPorts.length) || (hasPortType('sfp_plus') && sfpPlusPorts.length) ? 'grid' : 'none';
        }


        function hydrateLookups(statuses, colors, vlans) {
            statusOptions = Array.isArray(statuses) ? statuses : [];
            colorOptions = Array.isArray(colors) ? colors : [];
            vlanOptions = Array.isArray(vlans) ? vlans : [];

            const statusSelect = document.getElementById('statusSelect');
            const colorSelect = document.getElementById('colorSelect');
            const vlanSelect = document.getElementById('vlanSelect');
            const legend = document.getElementById('switchLegend');

            statusSelect.innerHTML = '<option value="">-- choose status --</option>';
            colorSelect.innerHTML = '<option value="">-- choose color --</option>';
            vlanSelect.innerHTML = '<option value="">-- choose VLAN --</option>';
            legend.innerHTML = '';

            statusOptions.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.name;
                option.textContent = item.name;
                statusSelect.appendChild(option);
            });
            statusSelect.appendChild(new Option('➕ Add', '__add_new__'));

            colorOptions.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.name;
                option.textContent = item.name;
                colorSelect.appendChild(option);

                const legendItem = document.createElement('div');
                legendItem.className = 'switch-legend-item';
                const swatch = document.createElement('span');
                swatch.className = 'switch-color-swatch';
                swatch.style.background = getColorCss(item.name);
                legendItem.appendChild(swatch);
                legendItem.appendChild(document.createTextNode(item.name));
                legend.appendChild(legendItem);
            });

            vlanOptions.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                vlanSelect.appendChild(option);
            });
            colorSelect.appendChild(new Option('➕ Add', '__add_new__'));
            vlanSelect.appendChild(new Option('➕ Add', '__add_new__'));
        }

        function fallbackLayout() {
            const rj45 = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.rj45_name) || '').replace(/\D+/g, ''), 10) || 24;
            const fiberPortsNumber = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.fiber_ports_number) || '').replace(/\D+/g, ''), 10) || 0;
            const legacyFiberCount = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.fiber_count) || '').replace(/\D+/g, ''), 10) || 0;
            const fiberCount = fiberPortsNumber > 0 ? fiberPortsNumber : legacyFiberCount;
            const fiberName = String((selectedSwitchMeta && selectedSwitchMeta.fiber_name) || '').toLowerCase();
            const fiberLabel = String((selectedSwitchMeta && selectedSwitchMeta.fiber_port_label) || '').toLowerCase();
            const fiberHint = (fiberLabel + ' ' + fiberName).trim();
            const sfpPlus = hasPortType('sfp_plus') && fiberHint.includes('sfp+') ? fiberCount : 0;
            const sfp = hasPortType('sfp') && !fiberHint.includes('sfp+') && fiberHint.includes('sfp') ? fiberCount : 0;
            return { rj45: rj45, sfp: sfp, sfp_plus: sfpPlus };
        }

        function savePort(payload, showMessage) {
            if (!csrfToken) {
                return Promise.reject(new Error('Missing CSRF token'));
            }

            function parseApiResponse(response) {
                return response.text().then(function (text) {
                    const raw = String(text || '').trim();
                    if (!raw) {
                        throw new Error('Empty response from server');
                    }
                    try {
                        return JSON.parse(raw);
                    } catch (e) {
                        throw new Error('Invalid server JSON response');
                    }
                });
            }

            return fetch(apiUpdate, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({}, payload, { csrf_token: csrfToken }))
            })
                .then(parseApiResponse)
                .then(function (resp) {
                    if (!resp.success) {
                        throw new Error(resp.error || 'Save failed');
                    }
                    if (showMessage) {
                        alert('Saved');
                    }
                });
        }

        function loadPorts() {
            if (!csrfToken) {
                alert('Missing CSRF token. Please refresh the page and try again.');
                return;
            }
            const localLayout = fallbackLayout();
            fetch(apiGet, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    switch_id: selectedSwitchId,
                    csrf_token: csrfToken
                })
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        const raw = String(text || '').trim();
                        if (!raw) {
                            throw new Error('Empty response from server');
                        }
                        try {
                            return JSON.parse(raw);
                        } catch (e) {
                            throw new Error('Invalid server JSON response');
                        }
                    });
                })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load ports');
                    }
                    availablePortTypes = Array.isArray(data.port_types) && data.port_types.length
                        ? data.port_types.map(normalizePortType).filter(function (type, idx, all) { return all.indexOf(type) === idx; })
                        : ['rj45', 'sfp', 'sfp_plus'];
                    if (!hasPortType('rj45')) {
                        availablePortTypes.push('rj45');
                    }
                    ports = data.ports || [];
                    hydrateLookups(data.statuses || [], data.colors || [], data.vlans || []);
                    const layout = data.layout || localLayout;
                    const layoutLabel = String((selectedSwitchMeta && selectedSwitchMeta.port_numbering_layout) || 'Vertical');
                    document.getElementById('switchLayoutSummary').textContent = buildLayoutSummary(layoutLabel, (layout.rj45 || 0), (layout.sfp || 0), (layout.sfp_plus || 0));
                    renderPorts();
                })
                .catch(function (err) {
                    alert(err && err.message ? ('Unable to load switch ports: ' + err.message) : 'Unable to load switch ports.');
                });
        }

        document.getElementById('savePortBtn').addEventListener('click', function () {
            if (!selected) {
                alert('Select a port first');
                return;
            }

            const payload = {
                id: selected.dataset.id,
                switch_id: selectedSwitchId,
                color: document.getElementById('colorSelect').value || null,
                status: document.getElementById('statusSelect').value || null,
                label: document.getElementById('labelInput').value || null,
                vlan: document.getElementById('vlanSelect').value || null,
                comments: document.getElementById('commentsInput').value
            };

            savePort(payload, true)
                .then(function () {
                    selected.dataset.status = payload.status || selected.dataset.status;
                    selected.dataset.label = payload.label || selected.dataset.label;
                    selected.dataset.vlanId = payload.vlan || '';
                    const selectedVlan = vlanOptions.find(function (item) { return String(item.id) === String(payload.vlan || ''); });
                    selected.dataset.vlanName = selectedVlan ? selectedVlan.name : '';
                    selected.dataset.vlanColor = selectedVlan ? (selectedVlan.color || '') : '';
                    selected.dataset.comments = payload.comments || '';
                    paintPort(selected, payload.color || selected.dataset.color);
                    paintVlan(selected, payload.vlan || '');
                    paintStatusTag(selected, payload.status || selected.dataset.status);
                })
                .catch(function (err) {
                    alert(err.message || 'Network error');
                });
        });

        document.getElementById('colorSelect').addEventListener('change', function () {
            if (!selected) {
                return;
            }
            if (this.value === '__add_new__') {
                return;
            }
            if (String(selected.dataset.id).indexOf('virtual-') === 0) {
                alert('This port is not saved in database yet. Reload the switch and try again.');
                return;
            }
            const chosenColor = this.value || null;
            const oldColor = selected.dataset.color;
            paintPort(selected, chosenColor || oldColor);

            savePort({ id: selected.dataset.id, switch_id: selectedSwitchId, color: chosenColor }, false)
                .catch(function () {
                    paintPort(selected, oldColor);
                    alert('Unable to auto-save color.');
                });
        });

        loadPorts();
    })();
</script>
<script src="../../js/select-add-option.js"></script>
<?php endif; ?>
</body>
</html>
