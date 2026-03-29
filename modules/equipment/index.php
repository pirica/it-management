<?php
require '../../config/config.php';

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
        OR CAST(e.active AS CHAR) LIKE '{$searchEsc}'
    )";
}

$sql = "SELECT e.id, e.name, e.serial_number, e.model, e.hostname, e.ip_address, e.active,
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
$sortableColumns = ['id', 'name', 'equipment_type_name', 'manufacturer_name', 'location_name', 'status_name', 'ip_address', 'serial_number', 'active'];
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
    'manufacturer_name' => 'm.name',
    'location_name' => 'l.name',
    'status_name' => 'es.name',
    'ip_address' => 'e.ip_address',
    'serial_number' => 'e.serial_number',
    'active' => 'e.active',
];
$sql .= ' ORDER BY ' . $orderByMap[$sort] . ' ' . $dir;
$result = mysqli_query($conn, $sql);

$switches = [];
$switchResult = mysqli_query(
    $conn,
    "SELECT e.id, e.name,
            COALESCE(er.name, '24 ports') AS rj45_name,
            COALESCE(ef.name, '') AS fiber_name,
            COALESCE(efc.name, '0') AS fiber_count
     FROM equipment e
     INNER JOIN equipment_types et ON et.id = e.equipment_type_id
     LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
     LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
     LEFT JOIN equipment_fiber_count efc ON efc.id = e.switch_fiber_count_id
     WHERE e.company_id = $company_id
       AND e.active = 1
       AND LOWER(TRIM(et.name)) = 'switch'
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
                            'manufacturer_name' => 'Manufacturer',
                            'location_name' => 'Location',
                            'status_name' => 'Status',
                            'ip_address' => 'IP Address',
                            'serial_number' => 'Serial Number',
                            'active' => 'Active',
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
                            <?php $isSwitch = strtolower(trim((string)($row['equipment_type_name'] ?? ''))) === 'switch'; ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['name']); ?></td>
                                <td><?php echo sanitize($row['equipment_type_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['manufacturer_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['location_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['status_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['ip_address'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['serial_number'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo (int)$row['active'] === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo (int)$row['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <?php if ($isSwitch): ?>
                                        <a class="btn btn-sm btn-primary" href="index.php?switch_id=<?php echo (int)$row['id']; ?><?php echo $searchRaw !== '' ? '&search=' . urlencode($searchRaw) : ''; ?>#switch-port-manager">Switch Port Manager</a>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this equipment?');">🗑️</a>
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
                    <div class="switch-manager" id="switchManager">
                        <div class="card" style="margin-bottom:14px;padding:12px;">
                            <strong>Live switch layout:</strong>
                            <span id="switchLayoutSummary">Loading…</span>
                        </div>
                        <div class="switch-grid">
                            <div class="switch-row" id="switchRow1"></div>
                            <div class="switch-row" id="switchRow2"></div>
                        </div>
                        <div class="switch-grid" id="fiberGrid" style="margin-top:10px;">
                            <div>
                                <div style="margin-bottom:6px;font-weight:600;">SFP Ports</div>
                                <div class="switch-row" id="switchSfpRow"></div>
                            </div>
                            <div>
                                <div style="margin-bottom:6px;font-weight:600;">SFP+ Ports</div>
                                <div class="switch-row" id="switchSfpPlusRow"></div>
                            </div>
                        </div>

                        <div class="switch-controls">
                            <label><strong>Selected port:</strong> <span id="selectedPort">None</span></label>
                            <label>
                                Color:
                                <select id="colorSelect">
                                    <option value="">-- choose color --</option>
                                </select>
                            </label>
                            <label>
                                Status:
                                <select id="statusSelect">
                                    <option value="">-- choose status --</option>
                                </select>
                            </label>
                            <label>
                                Label:
                                <input type="text" id="labelInput" placeholder="Port label">
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
<script>
    (function () {
        const apiGet = '<?php echo BASE_URL; ?>get_ports.php';
        const apiUpdate = '<?php echo BASE_URL; ?>update_port.php';
        const selectedSwitchId = <?php echo (int)$selectedSwitchId; ?>;
        const selectedSwitchMeta = <?php echo json_encode($selectedSwitchData ?? []); ?>;
        let ports = [];
        let colorOptions = [];
        let statusOptions = [];
        let selected = null;
        const tooltip = document.getElementById('switchTooltip');

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
                case 'gray': return 'grey';
                case 'other': return 'lightgray';
                default: return 'transparent';
            }
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
            const portType = (el.dataset.portType || 'rj45').replace('_', '+').toUpperCase();
            tooltip.innerHTML = '<strong>' + escapeHtml(portType) + ' Port ' + el.dataset.portNumber + '</strong><br>'
                + 'Label: ' + escapeHtml(label) + '<br>'
                + 'Status: ' + escapeHtml(status) + '<br>'
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
            document.getElementById('commentsInput').value = el.dataset.comments || '';
        }

        function createPortElement(p) {
            const el = document.createElement('div');
            el.className = 'switch-port';
            el.dataset.id = p.id;
            el.dataset.portNumber = p.port_number;
            el.dataset.portType = p.port_type || 'rj45';
            el.dataset.label = p.label || '';
            el.dataset.status = p.status || 'unknown';
            el.dataset.comments = p.comments || '';
            el.dataset.color = p.color || 'black';

            const num = document.createElement('div');
            num.className = 'switch-port-num';
            num.textContent = p.port_number;
            el.appendChild(num);

            const colorDiv = document.createElement('div');
            colorDiv.className = 'switch-color-indicator';
            colorDiv.style.background = getColorCss(p.color);
            el.appendChild(colorDiv);

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

            const rj45Ports = ports.filter(function (p) { return (p.port_type || 'rj45') === 'rj45'; });
            const splitAt = Math.ceil(rj45Ports.length / 2);
            rj45Ports.forEach(function (p, idx) {
                const el = createPortElement(p);
                if (idx < splitAt) { row1.appendChild(el); } else { row2.appendChild(el); }
            });

            const sfpPorts = ports.filter(function (p) { return p.port_type === 'sfp'; });
            sfpPorts.forEach(function (p) { sfpRow.appendChild(createPortElement(p)); });
            const sfpPlusPorts = ports.filter(function (p) { return p.port_type === 'sfp_plus'; });
            sfpPlusPorts.forEach(function (p) { sfpPlusRow.appendChild(createPortElement(p)); });

            document.getElementById('switchLayoutSummary').textContent = 'RJ45: ' + rj45Ports.length + ' | SFP: ' + sfpPorts.length + ' | SFP+: ' + sfpPlusPorts.length;
            document.getElementById('fiberGrid').style.display = (sfpPorts.length || sfpPlusPorts.length) ? 'grid' : 'none';
        }


        function hydrateLookups(statuses, colors) {
            statusOptions = Array.isArray(statuses) ? statuses : [];
            colorOptions = Array.isArray(colors) ? colors : [];

            const statusSelect = document.getElementById('statusSelect');
            const colorSelect = document.getElementById('colorSelect');
            const legend = document.getElementById('switchLegend');

            statusSelect.innerHTML = '<option value="">-- choose status --</option>';
            colorSelect.innerHTML = '<option value="">-- choose color --</option>';
            legend.innerHTML = '';

            statusOptions.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.name;
                option.textContent = item.name;
                statusSelect.appendChild(option);
            });

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
        }

        function fallbackLayout() {
            const rj45 = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.rj45_name) || '').replace(/\D+/g, ''), 10) || 24;
            const fiberCount = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.fiber_count) || '').replace(/\D+/g, ''), 10) || 0;
            const fiberName = String((selectedSwitchMeta && selectedSwitchMeta.fiber_name) || '').toLowerCase();
            const sfpPlus = fiberName.includes('sfp+') ? fiberCount : 0;
            const sfp = fiberName.includes('sfp+') ? 0 : (fiberName.includes('sfp') ? fiberCount : 0);
            return { rj45: rj45, sfp: sfp, sfp_plus: sfpPlus };
        }

        function savePort(payload, showMessage) {
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
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
            const localLayout = fallbackLayout();
            fetch(apiGet + '?switch_id=' + encodeURIComponent(selectedSwitchId))
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
                    ports = data.ports || [];
                    hydrateLookups(data.statuses || [], data.colors || []);
                    const layout = data.layout || localLayout;
                    document.getElementById('switchLayoutSummary').textContent = 'RJ45: ' + (layout.rj45 || 0) + ' | SFP: ' + (layout.sfp || 0) + ' | SFP+: ' + (layout.sfp_plus || 0);
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
                comments: document.getElementById('commentsInput').value || null
            };

            savePort(payload, true)
                .then(function () {
                    selected.dataset.color = payload.color || selected.dataset.color;
                    selected.dataset.status = payload.status || selected.dataset.status;
                    selected.dataset.label = payload.label || selected.dataset.label;
                    selected.dataset.comments = payload.comments || selected.dataset.comments;
                    selected.querySelector('.switch-color-indicator').style.background = getColorCss(selected.dataset.color);
                })
                .catch(function (err) {
                    alert(err.message || 'Network error');
                });
        });

        document.getElementById('colorSelect').addEventListener('change', function () {
            if (!selected) {
                return;
            }
            if (String(selected.dataset.id).indexOf('virtual-') === 0) {
                alert('This port is not saved in database yet. Reload the switch and try again.');
                return;
            }
            const chosenColor = this.value || null;
            const oldColor = selected.dataset.color;
            selected.dataset.color = chosenColor || oldColor;
            selected.querySelector('.switch-color-indicator').style.background = getColorCss(selected.dataset.color);

            savePort({ id: selected.dataset.id, switch_id: selectedSwitchId, color: chosenColor }, false)
                .catch(function () {
                    selected.dataset.color = oldColor;
                    selected.querySelector('.switch-color-indicator').style.background = getColorCss(oldColor);
                    alert('Unable to auto-save color.');
                });
        });

        loadPorts();
    })();
</script>
<?php endif; ?>
</body>
</html>
