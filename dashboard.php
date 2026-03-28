<?php
require 'config/config.php';

$company = mysqli_query($conn, "SELECT * FROM companies WHERE id = $company_id");
$company_data = mysqli_fetch_assoc($company);

$equipment_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM equipment WHERE company_id = $company_id AND active = 1"))['count'];
$workstations_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM workstations WHERE company_id = $company_id AND active = 1"))['count'];
$tickets_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tickets WHERE company_id = $company_id"))['count'];
$users_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE company_id = $company_id AND active = 1"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IT Management</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <h1>📊 Dashboard</h1>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">Welcome to <?php echo sanitize($company_data['name']); ?></p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Equipment</div>
                        <div class="stat-number"><?php echo $equipment_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Workstations</div>
                        <div class="stat-number"><?php echo $workstations_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Tickets</div>
                        <div class="stat-number"><?php echo $tickets_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Users</div>
                        <div class="stat-number"><?php echo $users_count; ?></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>System Information</h2>
                    </div>
                    <div class="card-body">
                        <p><strong>Company:</strong> <?php echo sanitize($company_data['name']); ?></p>
                        <p><strong>Industry:</strong> <?php echo sanitize($company_data['industry']); ?></p>
                        <p><strong>Location:</strong> <?php echo sanitize($company_data['city'] . ', ' . $company_data['country']); ?></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Switch Port Manager</h2></div>
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
            </div>
        </div>
    </div>

    <script src="js/theme.js"></script>
    <script src="js/script.js"></script>
    <script>
        (function () {
            const apiGet = 'get_ports.php';
            const apiUpdate = 'update_port.php';
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

                fetch(apiUpdate, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        if (!resp.success) {
                            throw new Error(resp.error || 'Save failed');
                        }

                        selected.dataset.color = payload.color || selected.dataset.color;
                        selected.dataset.status = payload.status || selected.dataset.status;
                        selected.dataset.label = payload.label || selected.dataset.label;
                        selected.dataset.comments = payload.comments || selected.dataset.comments;
                        selected.querySelector('.switch-color-indicator').style.background = getColorCss(selected.dataset.color);
                        alert('Saved');
                    })
                    .catch(function (err) {
                        alert(err.message || 'Network error');
                    });
            });

            loadPorts();
        })();
    </script>
</body>
</html>
