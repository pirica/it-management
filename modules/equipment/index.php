<?php
require '../../config/config.php';

$sql = "SELECT e.id, e.name, e.serial_number, e.model, e.hostname, e.ip_address, e.active,
               c.name AS company_name,
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
        ORDER BY e.id DESC";
$result = mysqli_query($conn, $sql);

$switches = [];
$switchResult = mysqli_query(
    $conn,
    "SELECT e.id, e.name
     FROM equipment e
     INNER JOIN equipment_types et ON et.id = e.equipment_type_id
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
                <a href="create.php" class="btn btn-primary">+ New</a>
            </div>

            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Manufacturer</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Serial Number</th>
                        <th>Active</th>
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
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">View</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                                    <?php if ($isSwitch): ?>
                                        <a class="btn btn-sm btn-primary" href="index.php?switch_id=<?php echo (int)$row['id']; ?>#switch-port-manager">Switch Port Manager</a>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this equipment?');">Delete</a>
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
                        <div class="switch-grid">
                            <div class="switch-row" id="switchRow1"></div>
                            <div class="switch-row" id="switchRow2"></div>
                        </div>

                        <div class="switch-controls">
                            <label><strong>Selected port:</strong> <span id="selectedPort">None</span></label>
                            <label>
                                Color:
                                <select id="colorSelect">
                                    <option value="">-- choose color --</option>
                                    <option value="green">Green</option>
                                    <option value="red">Red</option>
                                    <option value="yellow">Yellow</option>
                                    <option value="black">Black</option>
                                    <option value="blue">Blue</option>
                                    <option value="white">White</option>
                                    <option value="orange">Orange</option>
                                    <option value="purple">Purple</option>
                                </select>
                            </label>
                            <label>
                                Status:
                                <select id="statusSelect">
                                    <option value="">-- choose status --</option>
                                    <option value="uplink">uplink</option>
                                    <option value="empty">empty</option>
                                    <option value="down">down</option>
                                    <option value="unknown">unknown</option>
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
                            <button class="btn btn-primary" id="savePortBtn" type="button">Save</button>
                        </div>

                        <div class="switch-legend" id="switchLegend">
                            <div class="switch-legend-item"><span class="switch-color-swatch c-green"></span>Green</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-red"></span>Red</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-yellow"></span>Yellow</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-black"></span>Black</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-blue"></span>Blue</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-white"></span>White</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-orange"></span>Orange</div>
                            <div class="switch-legend-item"><span class="switch-color-swatch c-purple"></span>Purple</div>
                        </div>
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
        let ports = [];
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
            tooltip.innerHTML = '<strong>Port ' + el.dataset.portNumber + '</strong><br>'
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
            row1.innerHTML = '';
            row2.innerHTML = '';

            ports.forEach(function (p) {
                const el = createPortElement(p);
                if ((+p.port_number) <= 24) {
                    row1.appendChild(el);
                } else {
                    row2.appendChild(el);
                }
            });
        }

        function savePort(payload, showMessage) {
            return fetch(apiUpdate, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
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
            fetch(apiGet)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load ports');
                    }
                    ports = data.ports || [];
                    renderPorts();
                })
                .catch(function () {
                    alert('Unable to load switch ports.');
                });
        }

        document.getElementById('savePortBtn').addEventListener('click', function () {
            if (!selected) {
                alert('Select a port first');
                return;
            }

            const payload = {
                id: selected.dataset.id,
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
            const chosenColor = this.value || null;
            const oldColor = selected.dataset.color;
            selected.dataset.color = chosenColor || oldColor;
            selected.querySelector('.switch-color-indicator').style.background = getColorCss(selected.dataset.color);

            savePort({ id: selected.dataset.id, color: chosenColor }, false)
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
