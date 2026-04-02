<?php
require '../../config/config.php';

$moduleTitle = $equipmentModuleTitle ?? '🖥️ Equipment';
$moduleFlagField = $equipmentFlagField ?? null;
$moduleSearchPlaceholder = $equipmentSearchPlaceholder ?? 'Use SQL wildcards, e.g. %%desk%%';
$moduleBasePath = $equipmentModuleBasePath ?? '';
$moduleViewPath = $equipmentViewPath ?? $moduleBasePath;
$moduleEditPath = $equipmentEditPath ?? $moduleBasePath;
$moduleDeletePath = $equipmentDeletePath ?? $moduleBasePath;
$allowCreate = isset($equipmentAllowCreate) ? (bool)$equipmentAllowCreate : true;
$allowDelete = isset($equipmentAllowDelete) ? (bool)$equipmentAllowDelete : true;
$allowImport = isset($equipmentAllowImport) ? (bool)$equipmentAllowImport : true;

if ($moduleFlagField !== null && !preg_match('/^is_[a-z0-9_]+$/', $moduleFlagField)) {
    die('Invalid equipment filter configuration');
}

function eq_build_query(array $params): string {
    $clean = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $clean[$k] = $v;
    }
    return http_build_query($clean);
}

$messages = [];
$errors = [];
$csrfToken = itm_get_csrf_token();
$isSwitchListing = ($moduleFlagField === 'is_switch');
$selectedSwitchId = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
$showSwitchPortManager = $isSwitchListing && $selectedSwitchId > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'import_equipment') && $allowImport) {
    itm_require_post_csrf();
    if (!isset($_FILES['excel_file']) || (int)($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a CSV file first.';
    } else {
        $tmpPath = (string)($_FILES['excel_file']['tmp_name'] ?? '');
        $handle = @fopen($tmpPath, 'r');
        if (!$handle) {
            $errors[] = 'Unable to read uploaded file.';
        } else {
            $created = 0;
            $updated = 0;
            $headerRead = false;
            while (($row = fgetcsv($handle)) !== false) {
                if (!$headerRead) {
                    $headerRead = true;
                    if (isset($row[0]) && strtolower(trim((string)$row[0])) === 'name') {
                        continue;
                    }
                }

                $name = trim((string)($row[0] ?? ''));
                if ($name === '') {
                    continue;
                }

                $serial = trim((string)($row[1] ?? ''));
                $model = trim((string)($row[2] ?? ''));
                $hostname = trim((string)($row[3] ?? ''));
                $ip = trim((string)($row[4] ?? ''));

                $nameEsc = mysqli_real_escape_string($conn, $name);
                $serialEsc = mysqli_real_escape_string($conn, $serial);
                $modelEsc = mysqli_real_escape_string($conn, $model);
                $hostnameEsc = mysqli_real_escape_string($conn, $hostname);
                $ipEsc = mysqli_real_escape_string($conn, $ip);

                $flagSetSql = '';
                if ($moduleFlagField !== null) {
                    $flagSetSql = ', `' . $moduleFlagField . '` = 1';
                }

                $exists = mysqli_query($conn, "SELECT id FROM equipment WHERE company_id={$company_id} AND name='{$nameEsc}' LIMIT 1");
                if ($exists && mysqli_num_rows($exists) === 1) {
                    $current = mysqli_fetch_assoc($exists);
                    $id = (int)($current['id'] ?? 0);
                    if ($id > 0 && mysqli_query($conn, "UPDATE equipment SET serial_number='{$serialEsc}', model='{$modelEsc}', hostname='{$hostnameEsc}', ip_address='{$ipEsc}' {$flagSetSql} WHERE id={$id} AND company_id={$company_id}")) {
                        $updated++;
                    }
                } else {
                    $flagInsertCols = '';
                    $flagInsertVals = '';
                    if ($moduleFlagField !== null) {
                        $flagInsertCols = ', `' . $moduleFlagField . '`';
                        $flagInsertVals = ', 1';
                    }
                    if (mysqli_query($conn, "INSERT INTO equipment (company_id, name, serial_number, model, hostname, ip_address{$flagInsertCols}) VALUES ({$company_id}, '{$nameEsc}', '{$serialEsc}', '{$modelEsc}', '{$hostnameEsc}', '{$ipEsc}'{$flagInsertVals})")) {
                        $created++;
                    }
                }
            }
            fclose($handle);
            $messages[] = "Import completed. Created: {$created}, Updated: {$updated}.";
        }
    }
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (
        e.name LIKE '{$searchEsc}'
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

$flagSql = '';
if ($moduleFlagField !== null) {
    $flagSql = ' AND e.`' . $moduleFlagField . '` = 1';
}

$sortableColumns = ['name', 'equipment_type_name', 'hostname', 'manufacturer_name', 'location_name', 'status_name', 'ip_address', 'serial_number', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}
$orderByMap = [
    'name' => 'e.name',
    'equipment_type_name' => 'et.name',
    'hostname' => 'e.hostname',
    'manufacturer_name' => 'm.name',
    'location_name' => 'l.name',
    'status_name' => 'es.name',
    'ip_address' => 'e.ip_address',
    'serial_number' => 'e.serial_number',
    'active' => 'e.active',
];

$baseSql = "FROM equipment e
        LEFT JOIN companies c ON c.id = e.company_id
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
        LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
        LEFT JOIN it_locations l ON l.id = e.location_id
        LEFT JOIN equipment_statuses es ON es.id = e.status_id
        WHERE e.company_id = {$company_id}{$flagSql}{$searchSql}";

if (($_GET['export'] ?? '') === 'excel') {
    $rows = mysqli_query($conn, "SELECT e.name, et.name AS type_name, e.hostname, m.name AS manufacturer_name, l.name AS location_name, es.name AS status_name, e.ip_address, e.serial_number {$baseSql} ORDER BY " . $orderByMap[$sort] . " {$dir}");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="equipment_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Type', 'Hostname', 'Manufacturer', 'Location', 'Status', 'IP Address', 'Serial Number']);
    while ($rows && ($row = mysqli_fetch_assoc($rows))) {
        fputcsv($out, [$row['name'] ?? '', $row['type_name'] ?? '', $row['hostname'] ?? '', $row['manufacturer_name'] ?? '', $row['location_name'] ?? '', $row['status_name'] ?? '', $row['ip_address'] ?? '', $row['serial_number'] ?? '']);
    }
    fclose($out);
    exit;
}

if (($_GET['export'] ?? '') === 'pdf') {
    $rows = mysqli_query($conn, "SELECT e.name, et.name AS type_name, e.hostname, m.name AS manufacturer_name, l.name AS location_name, es.name AS status_name, e.ip_address, e.serial_number {$baseSql} ORDER BY " . $orderByMap[$sort] . " {$dir}");
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><title>' . sanitize($moduleTitle) . ' Export</title></head><body>';
    echo '<h2>' . sanitize($moduleTitle) . ' Export</h2>';
    echo '<table border="1" cellspacing="0" cellpadding="5"><tr><th>Name</th><th>Type</th><th>Hostname</th><th>Manufacturer</th><th>Location</th><th>Status</th><th>IP Address</th><th>Serial Number</th></tr>';
    while ($rows && ($row = mysqli_fetch_assoc($rows))) {
        echo '<tr>';
        foreach (['name', 'type_name', 'hostname', 'manufacturer_name', 'location_name', 'status_name', 'ip_address', 'serial_number'] as $col) {
            echo '<td>' . sanitize((string)($row[$col] ?? '')) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

$sql = "SELECT e.id, e.name, e.serial_number, e.model, e.hostname, e.ip_address, e.active,
               c.company AS company_name,
               et.name AS equipment_type_name,
               m.name AS manufacturer_name,
               l.name AS location_name,
               es.name AS status_name
        {$baseSql}
        ORDER BY " . $orderByMap[$sort] . " {$dir}";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($moduleTitle); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1><?php echo sanitize($moduleTitle); ?></h1>
                <?php if ($allowCreate): ?>
                    <a href="<?php echo sanitize($moduleBasePath); ?>create.php" class="btn btn-primary">➕</a>
                <?php endif; ?>
            </div>

            <?php foreach ($messages as $message): ?>
                <div class="alert alert-success" style="margin-bottom:10px;"><?php echo sanitize($message); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger" style="margin-bottom:10px;"><?php echo sanitize($error); ?></div>
            <?php endforeach; ?>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="equipmentSearch">Search (all fields)</label>
                        <input type="text" id="equipmentSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="<?php echo sanitize($moduleSearchPlaceholder); ?>">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-sm">Clear</a>
                        <a href="?<?php echo sanitize(eq_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'export' => 'excel'])); ?>" class="btn btn-sm">📗 Export Excel</a>
                        <a href="?<?php echo sanitize(eq_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'export' => 'pdf'])); ?>" class="btn btn-sm">📄 Export PDF</a>
                    </div>
                </form>
            </div>

            <?php if ($allowImport): ?>
                <div class="card" style="margin-bottom:16px;">
                    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="action" value="import_equipment">
                        <label for="excelFile">📥 Import Excel</label>
                        <input type="file" id="excelFile" name="excel_file" accept=".csv,text/csv">
                        <button type="submit" class="btn btn-sm">Upload</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ([
                            'name' => 'Name',
                            'equipment_type_name' => 'Type',
                            'hostname' => 'Hostname',
                            'manufacturer_name' => 'Manufacturer',
                            'location_name' => 'Location',
                            'status_name' => 'Status',
                            'ip_address' => 'IP Address',
                            'serial_number' => 'Serial Number',
                            'active' => 'Active',
                        ] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo sanitize($row['name']); ?></td>
                                <td><?php echo sanitize($row['equipment_type_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['hostname'] ?? '-'); ?></td>
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
                                    <a class="btn btn-sm" href="<?php echo sanitize($moduleViewPath); ?>view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="<?php echo sanitize($moduleEditPath); ?>edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <?php if ($allowDelete): ?>
                                        <a class="btn btn-sm btn-danger" href="<?php echo sanitize($moduleDeletePath); ?>delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this equipment?');">🗑️</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align:center;">No equipment records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($showSwitchPortManager): ?>
                <div id="switch-port-manager" class="card" style="margin-top:16px;overflow:auto;">
                    <h2 style="margin-top:0;">🔌 Switch Port Manager</h2>
                    <p style="margin-top:0;opacity:.8;">Switch ID: <?php echo (int)$selectedSwitchId; ?></p>
                    <div id="switchPortManagerStatus" style="margin-bottom:10px;opacity:.85;">Loading ports…</div>
                    <table>
                        <thead>
                        <tr>
                            <th>Port</th>
                            <th>Label</th>
                            <th>Status</th>
                            <th>Color</th>
                            <th>VLAN</th>
                            <th>Comments</th>
                        </tr>
                        </thead>
                        <tbody id="switchPortManagerBody">
                        <tr><td colspan="6" style="opacity:.75;">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if ($showSwitchPortManager): ?>
<script>
(function () {
    const switchId = <?php echo (int)$selectedSwitchId; ?>;
    const statusEl = document.getElementById('switchPortManagerStatus');
    const tbody = document.getElementById('switchPortManagerBody');

    function setStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.style.color = isError ? '#b42318' : '';
    }

    fetch('../../get_ports.php?switch_id=' + encodeURIComponent(String(switchId)), {
        credentials: 'same-origin'
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data || !data.success || !Array.isArray(data.ports)) {
                throw new Error((data && data.error) ? data.error : 'Unable to load ports');
            }

            if (data.ports.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="opacity:.75;">No ports found.</td></tr>';
                setStatus('No ports found for this switch.', false);
                return;
            }

            const rows = data.ports.map((port) => {
                const portType = (port.port_type || 'rj45').toString().toUpperCase().replace('_PLUS', '+');
                const portNumber = Number(port.port_number || 0);
                const vlan = (port.vlan_name || '').toString().trim();
                const safe = (value) => {
                    const text = (value ?? '').toString();
                    return text
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };
                return '<tr>'
                    + '<td>' + safe(portType + ' ' + portNumber) + '</td>'
                    + '<td>' + safe(port.label || '') + '</td>'
                    + '<td>' + safe(port.status || '') + '</td>'
                    + '<td>' + safe(port.color || '') + '</td>'
                    + '<td>' + safe(vlan) + '</td>'
                    + '<td>' + safe(port.comments || '') + '</td>'
                    + '</tr>';
            }).join('');

            tbody.innerHTML = rows;
            setStatus('Loaded ' + data.ports.length + ' port(s).', false);
        })
        .catch((error) => {
            tbody.innerHTML = '<tr><td colspan="6" style="opacity:.75;">Failed to load ports.</td></tr>';
            setStatus('Could not load ports: ' + (error && error.message ? error.message : 'Unknown error'), true);
        });
})();
</script>
<?php endif; ?>
<script src="../../js/theme.js"></script>
</body>
</html>
