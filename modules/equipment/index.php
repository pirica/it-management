<?php
require '../../config/config.php';
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'equipment', (int)($company_id ?? 0));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ((int)$company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: index.php');
        exit;
    }

    $countSql = 'SELECT COUNT(*) AS total_rows FROM `equipment` WHERE company_id=' . (int)$company_id;
    $countResult = mysqli_query($conn, $countSql);
    $existingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $existingRows = (int)($countRow['total_rows'] ?? 0);
    }

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: index.php');
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, 'equipment', (int)$company_id, $seedError);
    if ($insertedRows > 0) {
        $_SESSION['crud_success'] = 'Sample data added successfully.';
    } else {
        $_SESSION['crud_error'] = $seedError !== '' ? $seedError : 'No sample data was inserted.';
    }

    header('Location: index.php');
    exit;
}

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
        OR e.mac_address LIKE '{$searchEsc}'
        OR c.company LIKE '{$searchEsc}'
        OR et.name LIKE '{$searchEsc}'
        OR m.name LIKE '{$searchEsc}'
        OR l.name LIKE '{$searchEsc}'
        OR r.name LIKE '{$searchEsc}'
        OR idf.name LIKE '{$searchEsc}'
        OR es.name LIKE '{$searchEsc}'
    )";
}

$equipmentTypeNameFilter = isset($equipmentTypeNameFilter) ? trim((string)$equipmentTypeNameFilter) : '';
$moduleFilterSql = '';
if ($equipmentTypeNameFilter !== '') {
    $equipmentTypeNameFilterEsc = mysqli_real_escape_string($conn, strtolower($equipmentTypeNameFilter));
    $moduleFilterSql = " AND (LOWER(TRIM(et.name)) = '{$equipmentTypeNameFilterEsc}')";
}
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sql = "SELECT e.id, e.name, e.serial_number, e.model, e.hostname, e.ip_address, e.mac_address,
               c.company AS company_name,
               et.name AS equipment_type_name,
               m.name AS manufacturer_name,
               l.name AS location_name,
               r.name AS rack_name,
               idf.name AS idf_name,
               es.name AS status_name
        FROM equipment e
        LEFT JOIN companies c ON c.id = e.company_id
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
        LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
        LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
        LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
        LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id
        LEFT JOIN equipment_statuses es ON es.id = e.status_id
        WHERE e.company_id = $company_id
        {$moduleFilterSql}
        {$searchSql}";
$sortableColumns = ['id', 'name', 'equipment_type_name', 'hostname', 'ip_address', 'idf_name', 'rack_name', 'location_name', 'manufacturer_name', 'mac_address', 'status_name'];
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
    'ip_address' => 'e.ip_address',
    'idf_name' => 'idf.name',
    'rack_name' => 'r.name',
    'location_name' => 'l.name',
    'manufacturer_name' => 'm.name',
    'mac_address' => 'e.mac_address',
    'status_name' => 'es.name',
];
$countSql = "SELECT COUNT(*) AS total
             FROM equipment e
             LEFT JOIN companies c ON c.id = e.company_id
             LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
             LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
             LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
             LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
             LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id
             LEFT JOIN equipment_statuses es ON es.id = e.status_id
             WHERE e.company_id = $company_id
             {$moduleFilterSql}
             {$searchSql}";
$countResult = mysqli_query($conn, $countSql);
$countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
$totalRows = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql .= ' ORDER BY ' . $orderByMap[$sort] . ' ' . $dir . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$result = mysqli_query($conn, $sql);
$equipmentRows = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $equipmentRows[] = $row;
    }
}

$isGeneralEquipmentModule = $equipmentTypeNameFilter === '';
$isSwitchTypeFilter = strtolower($equipmentTypeNameFilter) === 'switch';
$enableSwitchPortManager = $isGeneralEquipmentModule || $isSwitchTypeFilter;
$switches = [];
if ($enableSwitchPortManager) {
    $switchResult = mysqli_query(
        $conn,
        "SELECT e.id, e.name, COALESCE(e.hostname, '') AS hostname,
                COALESCE(er.name, '24 ports') AS rj45_name,
                COALESCE(ef.name, '') AS fiber_name,
                COALESCE(e.switch_fiber_id, 0) AS fiber_id,
                COALESCE(efp.name, '') AS fiber_patch_name,
                COALESCE(e.switch_fiber_patch_id, 0) AS fiber_patch_id,
                COALESCE(efr.name, '') AS fiber_rack_name,
                COALESCE(e.switch_fiber_rack_id, 0) AS fiber_rack_id,
                COALESCE(e.switch_fiber_ports_number, 0) AS fiber_ports_number,
                {$switchFiberPortLabelSelect} AS fiber_port_label,
                COALESCE(spnl.name, 'Vertical') AS port_numbering_layout,
                COALESCE(r.name, '') AS rack_name,
                COALESCE(e.rack_id, 0) AS rack_id,
                COALESCE(idf.name, '') AS idf_name,
                COALESCE(l.name, '') AS location_name,
                COALESCE(e.location_id, 0) AS location_id
         FROM equipment e
         INNER JOIN equipment_types et ON et.id = e.equipment_type_id
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
         LEFT JOIN equipment_fiber_patch efp ON efp.id = e.switch_fiber_patch_id
         LEFT JOIN equipment_fiber_rack efr ON efr.id = e.switch_fiber_rack_id
         LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = e.switch_port_numbering_layout_id
         LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
         LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
         LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id
         WHERE e.company_id = $company_id
           AND LOWER(TRIM(et.name)) LIKE '%switch%'
         ORDER BY e.name ASC"
    );
    while ($switchResult && ($row = mysqli_fetch_assoc($switchResult))) {
        $switches[] = $row;
    }
}
$switchIds = array_map(static fn(array $switchItem): int => (int)($switchItem['id'] ?? 0), $switches);
$visibleSwitchIds = [];
foreach ($equipmentRows as $equipmentRow) {
    $equipmentId = (int)($equipmentRow['id'] ?? 0);
    $isSwitchType = str_contains(strtolower(trim((string)($equipmentRow['equipment_type_name'] ?? ''))), 'switch');
    if ($equipmentId > 0 && $isSwitchType && in_array($equipmentId, $switchIds, true)) {
        $visibleSwitchIds[] = $equipmentId;
    }
}
$visibleSwitchIds = array_values(array_unique($visibleSwitchIds));

$selectedSwitchId = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
$hasSelectedSwitch = in_array($selectedSwitchId, $visibleSwitchIds, true);
if (!$hasSelectedSwitch && !empty($visibleSwitchIds)) {
    $selectedSwitchId = (int)$visibleSwitchIds[0];
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
$showSwitchPortManager = $hasSelectedSwitch && (string)($_GET['spm'] ?? '') === '1';
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
$moduleSearchPlaceholder = (string)($equipmentSearchPlaceholder ?? 'Use SQL wildcards, e.g. %%switch%%');
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
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
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
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($equipmentModuleTitle ?? '🖥️ Equipment'); ?></h1>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($showSwitchPortManager): ?>
                        <button type="button" class="btn btn-sm" id="exportEquipmentPdfBtn">Export PDF</button>
                    <?php endif; ?>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <?php if ($hasSelectedSwitch): ?>
                        <input type="hidden" name="switch_id" value="<?php echo (int)$selectedSwitchId; ?>">
                    <?php endif; ?>
                    <?php if ($showSwitchPortManager): ?>
                        <input type="hidden" name="spm" value="1">
                    <?php endif; ?>
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="equipmentSearch">Search (all fields)</label>
                        <input type="text" id="equipmentSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="<?php echo sanitize($moduleSearchPlaceholder); ?>">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php<?php echo $hasSelectedSwitch ? '?switch_id=' . (int)$selectedSwitchId . ($showSwitchPortManager ? '&spm=1' : '') : ''; ?>" class="btn btn-sm">Clear</a>
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
                            'ip_address' => 'IP Address',
                            'idf_name' => 'IDF',
                            'rack_name' => 'RACK',
                            'location_name' => 'Location',
                            'manufacturer_name' => 'Manufacturer',
                            'mac_address' => 'MAC Address',
                            'status_name' => 'Status',
                        ] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?switch_id=<?php echo (int)$selectedSwitchId; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?><?php echo $showSwitchPortManager ? '&spm=1' : ''; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($equipmentRows)): ?>
                        <?php foreach ($equipmentRows as $row): ?>
                            <?php
                            $isSwitch = str_contains(strtolower(trim((string)($row['equipment_type_name'] ?? ''))), 'switch');
                            $showSwitchPortManagerAction = $isSwitch && in_array((int)$row['id'], $visibleSwitchIds, true);
                            ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['name']); ?></td>
                                <td><?php echo sanitize($row['equipment_type_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['hostname'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['ip_address'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['idf_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['rack_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['location_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['manufacturer_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['mac_address'] ?? '-'); ?></td>
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
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <?php if ($showSwitchPortManagerAction): ?>
                                        <?php
                                        $isSelectedSwitchManagerOpen = $showSwitchPortManager && (int)$row['id'] === $selectedSwitchId;
                                        $switchPortManagerHref = 'index.php?switch_id=' . (int)$row['id']
                                            . ($isSelectedSwitchManagerOpen ? '' : '&spm=1')
                                            . ($searchRaw !== '' ? '&search=' . urlencode($searchRaw) : '')
                                            . ($isSelectedSwitchManagerOpen ? '' : '#switch-port-manager');
                                        ?>
                                        <a class="btn btn-sm btn-primary" href="<?php echo $switchPortManagerHref; ?>"><?php echo $isSelectedSwitchManagerOpen ? 'Hide Switch Port Manager' : 'Switch Port Manager'; ?></a>
                                    <?php endif; ?>
                                    <?php
                                    $deleteUrl = './delete.php?id=' . (int)$row['id'];
                                    $deleteConfirmText = $isSwitch
                                        ? 'Delete this switch and all related switch port data? This action cannot be undone.'
                                        : 'Delete this equipment?';
                                    ?>
                                    <a class="btn btn-sm btn-danger" href="<?php echo $deleteUrl; ?>" data-confirm="<?php echo sanitize($deleteConfirmText); ?>">🗑️</a>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="12" style="text-align:center;">No equipment records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ((int)$company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?switch_id=<?php echo (int)$selectedSwitchId; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page - 1; ?><?php echo $showSwitchPortManager ? '&spm=1' : ''; ?>">« Prev</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.85;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?switch_id=<?php echo (int)$selectedSwitchId; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page + 1; ?><?php echo $showSwitchPortManager ? '&spm=1' : ''; ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($showSwitchPortManager): ?>
                <div class="card" id="switch-port-manager" style="margin-top:20px;">
                    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                        <h2>Switch Port Manager</h2>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <button class="btn btn-sm" id="switchExportImageBtn" type="button" title="Export switch layout as image">Export Image</button>
                            <form method="get" style="display:flex;align-items:center;gap:8px;">
                                <?php if ($searchRaw !== ''): ?>
                                    <input type="hidden" name="search" value="<?php echo sanitize($searchRaw); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="spm" value="1">
                                <input type="hidden" id="rackIdInput" value="<?php echo (int)($selectedSwitchData['rack_id'] ?? 0); ?>">
                                <input type="hidden" id="locationIdInput" value="<?php echo (int)($selectedSwitchData['location_id'] ?? 0); ?>">
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
                                <div class="switch-row" id="switchSfpRowAlt" style="margin-top:8px;"></div>
                            </div>
                            <div>
                                <div id="switchSfpPlusLabel" style="margin-bottom:6px;font-weight:600;">SFP+ Ports</div>
                                <div class="switch-row" id="switchSfpPlusRow"></div>
                                <div class="switch-row" id="switchSfpPlusRowAlt" style="margin-top:8px;"></div>
                            </div>
                        </div>

                        <div id="switchControlsHint" style="margin-top:10px;color:#6b7280;">Click a port to view and edit options.</div>
                        <div class="switch-controls" id="switchControls" style="display:none;">
                            <label><strong>Selected port:</strong> <span id="selectedPort">None</span></label>
                            <label>
                                Color:
                                <div class="switch-control-input">
                                    <select id="colorSelect"
                                        data-addable-select="1"
                                        data-add-table="cable_colors"
                                        data-add-id-col="id"
                                        data-add-label-col="color_name"
                                        data-add-company-scoped="1"
                                        data-add-friendly="cable color"
                                        data-add-extra-fields='[{"name":"hex_color","label":"Color Picker","type":"color"}]'>
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
                            <label id="patchPortRow">
                                Patch port:
                                <input type="text" id="labelInput" placeholder="Patch port">
                            </label>
                            <label id="vlanRow">
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
                            <label id="fiberPortsRow" style="display:none;">
                                Fiber Ports:
                                <div class="switch-control-input">
                                    <select id="fiberPortsSelect"
                                        data-addable-select="1"
                                        data-add-table="equipment_fiber"
                                        data-add-id-col="id"
                                        data-add-label-col="name"
                                        data-add-company-scoped="1"
                                        data-add-friendly="fiber port type">
                                        <option value="">-- choose fiber ports --</option>
                                    </select>
                                </div>
                            </label>
                            <label id="fiberPatchRow" style="display:none;">
                                Fiber Patch:
                                <div class="switch-control-input">
                                    <select id="fiberPatchSelect"
                                        data-addable-select="1"
                                        data-add-table="equipment_fiber_patch"
                                        data-add-id-col="id"
                                        data-add-label-col="name"
                                        data-add-company-scoped="1"
                                        data-add-friendly="fiber patch">
                                        <option value="">-- choose fiber patch --</option>
                                    </select>
                                </div>
                            </label>
                            <label id="fiberRackRow" style="display:none;">
                                Fiber Rack:
                                <div class="switch-control-input">
                                    <select id="fiberRackSelect"
                                        data-addable-select="1"
                                        data-add-table="equipment_fiber_rack"
                                        data-add-id-col="id"
                                        data-add-label-col="name"
                                        data-add-company-scoped="1"
                                        data-add-friendly="fiber rack">
                                        <option value="">-- choose fiber rack --</option>
                                    </select>
                                </div>
                            </label>
                            <label id="idfRow" style="display:none;">
                                IDF:
                                <div class="switch-control-input">
                                    <select id="idfSelect"
                                        data-addable-select="1"
                                        data-add-table="idfs"
                                        data-add-id-col="id"
                                        data-add-label-col="idf_code"
                                        data-add-company-scoped="1"
                                        data-add-extra-fields='[{"name":"name","label":"Code","type":"text"}]'
                                        data-add-friendly="idf">
                                        <option value="">-- choose IDF --</option>
                                    </select>
                                </div>
                            </label>
                            <label>
                                Comments:
                                <input type="text" id="commentsInput" placeholder="Comments">
                            </label>
                            <button class="btn btn-primary" id="savePortBtn" type="button">💾</button>
                        </div>

                    </div>
                    <div class="switch-tooltip" id="switchTooltip"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<?php if ($showSwitchPortManager): ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
    (function () {
        const apiGet = '<?php echo BASE_URL; ?>includes/get_ports.php';
        const apiUpdate = '<?php echo BASE_URL; ?>includes/update_port.php';
        const selectedSwitchId = <?php echo (int)$selectedSwitchId; ?>;
        const selectedSwitchMeta = <?php echo json_encode($selectedSwitchData ?? []); ?>;
        let ports = [];
        let colorOptions = [];
        let statusOptions = [];
        let vlanOptions = [];
        let availablePortTypes = ['rj45', 'sfp', 'sfp_plus'];
        let fiberPortOptions = [];
        let fiberPatchOptions = [];
        let fiberRackOptions = [];
        let idfOptions = [];
        let selected = null;
        const tooltip = document.getElementById('switchTooltip');
        const csrfToken = window.ITM_CSRF_TOKEN
            || (document.querySelector('input[name="csrf_token"]') || {}).value
            || '';

        function hasPortType(type) {
            return availablePortTypes.indexOf(type) !== -1;
        }

        function readSwitchMeta(key) {
            return String((selectedSwitchMeta && selectedSwitchMeta[key]) || '').trim();
        }

        function buildLayoutSummary(layoutLabel, rj45Count, sfpCount, sfpPlusCount) {
            const switchHostname = readSwitchMeta('hostname');
            const parts = [switchHostname !== '' ? ('Hostname: ' + switchHostname) : 'Hostname: N/A', 'RJ45: ' + rj45Count];
            if (hasPortType('sfp') && Number(sfpCount) > 0) {
                parts.push('SFP: ' + sfpCount);
            }
            if (hasPortType('sfp_plus') && Number(sfpPlusCount) > 0) {
                parts.push('SFP+: ' + sfpPlusCount);
            }
            const rackName = readSwitchMeta('rack_name');
            if (rackName !== '') {
                parts.push('Rack: ' + rackName);
            }
            const idfName = readSwitchMeta('idf_name');
            if (idfName !== '') {
                parts.push('IDF: ' + idfName);
            }
            const locationName = readSwitchMeta('location_name');
            if (locationName !== '') {
                parts.push('Location: ' + locationName);
            }
            return parts.join(' | ');
        }

        function sortPortsByLayout(portList) {
            const ordered = Array.isArray(portList) ? portList.slice() : [];
            ordered.sort(function (a, b) { return Number(a.port_number) - Number(b.port_number); });

            const portLayout = String((selectedSwitchMeta && selectedSwitchMeta.port_numbering_layout) || 'Vertical').trim().toLowerCase();
            if (portLayout !== 'vertical') {
                return ordered;
            }

            const oddPorts = [];
            const evenPorts = [];
            ordered.forEach(function (port) {
                if (Number(port.port_number) % 2 === 1) {
                    oddPorts.push(port);
                    return;
                }
                evenPorts.push(port);
            });
            return oddPorts.concat(evenPorts);
        }

        function renderFiberPortsByLayout(primaryRow, secondaryRow, portList) {
            const portLayout = String((selectedSwitchMeta && selectedSwitchMeta.port_numbering_layout) || 'Vertical').trim().toLowerCase();
            primaryRow.innerHTML = '';
            secondaryRow.innerHTML = '';

            const ordered = sortPortsByLayout(portList);
            if (portLayout !== 'vertical') {
                ordered.forEach(function (p) { primaryRow.appendChild(createPortElement(p)); });
                secondaryRow.style.display = 'none';
                return;
            }

            ordered.forEach(function (p) {
                if (Number(p.port_number) % 2 === 1) {
                    primaryRow.appendChild(createPortElement(p));
                    return;
                }
                secondaryRow.appendChild(createPortElement(p));
            });
            secondaryRow.style.display = secondaryRow.children.length ? 'flex' : 'none';
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

        async function exportEquipmentPdf() {
            const contentNode = document.querySelector('.main-content .content');
            if (!contentNode) {
                alert('Unable to find page content for PDF export.');
                return;
            }
            if (typeof html2canvas === 'undefined' || !window.jspdf || !window.jspdf.jsPDF) {
                alert('PDF export libraries are not loaded. Please refresh and try again.');
                return;
            }

            const previousOverflow = contentNode.style.overflow;
            const previousHeight = contentNode.style.height;
            contentNode.style.overflow = 'visible';
            contentNode.style.height = 'auto';

            try {
                const canvas = await html2canvas(contentNode, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    useCORS: true,
                    windowWidth: Math.max(document.documentElement.scrollWidth, contentNode.scrollWidth),
                    windowHeight: Math.max(document.documentElement.scrollHeight, contentNode.scrollHeight)
                });

                const pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                const canvasWidth = canvas.width;
                const canvasHeight = canvas.height;
                const imgHeight = (canvasHeight * pdfWidth) / canvasWidth;
                let heightLeft = imgHeight;
                let position = 0;

                const imageData = canvas.toDataURL('image/png');
                pdf.addImage(imageData, 'PNG', 0, position, pdfWidth, imgHeight);
                heightLeft -= pdfHeight;

                while (heightLeft > 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imageData, 'PNG', 0, position, pdfWidth, imgHeight);
                    heightLeft -= pdfHeight;
                }

                const rawHostname = String((selectedSwitchMeta && selectedSwitchMeta.hostname) || '').trim();
                const safeHostname = rawHostname
                    .toLowerCase()
                    .replace(/[^a-z0-9._-]+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                const fileBase = safeHostname !== '' ? safeHostname : 'equipment';
                pdf.save(fileBase + '-layout.pdf');
            } catch (error) {
                console.error(error);
                alert('Unable to export PDF right now. Please try again.');
            } finally {
                contentNode.style.overflow = previousOverflow;
                contentNode.style.height = previousHeight;
            }
        }

        const exportEquipmentPdfBtn = document.getElementById('exportEquipmentPdfBtn');
        if (exportEquipmentPdfBtn) {
            exportEquipmentPdfBtn.addEventListener('click', exportEquipmentPdf);
        }

        const switchExportImageBtn = document.getElementById('switchExportImageBtn');
        if (switchExportImageBtn) {
            switchExportImageBtn.addEventListener('click', exportSwitchImage);
        }

        function normalizeColorToken(color) {
            return String(color || '').trim().toLowerCase();
        }

        function resolveColorToken(color) {
            const normalized = normalizeColorToken(color);
            if (/^#[0-9a-f]{6}$/i.test(normalized)) {
                return normalized;
            }

            const colorMap = {
                green: '#22c55e',
                red: '#ef4444',
                yellow: '#facc15',
                black: '#111111',
                blue: '#4169e1',
                white: '#ffffff',
                orange: '#f97316',
                purple: '#9333ea',
                pink: '#ec4899',
                'dark pink': '#be185d',
                cyan: '#06b6d4',
                grey: '#9ca3af',
                gray: '#9ca3af',
                other: '#d1d5db'
            };

            if (colorMap[normalized]) {
                return colorMap[normalized];
            }

            const parts = normalized.split(/\s+/).filter(Boolean);
            const hasLight = parts.includes('light');
            const hasDark = parts.includes('dark');
            const base = parts.filter((p) => p !== 'light' && p !== 'dark').join(' ');
            if (base !== '' && colorMap[base]) {
                if (hasLight) {
                    return 'color-mix(in srgb, ' + colorMap[base] + ' 55%, white)';
                }
                if (hasDark) {
                    return 'color-mix(in srgb, ' + colorMap[base] + ' 60%, black)';
                }
                return colorMap[base];
            }

            return 'transparent';
        }

        function getColorCss(color, colorHex) {
            const normalizedHex = String(colorHex || '').trim();
            if (/^#[0-9a-f]{6}$/i.test(normalizedHex)) {
                return normalizedHex;
            }
            return resolveColorToken(color);
        }

        function getPortNumberColor(color, colorHex) {
            const normalizedHex = String(colorHex || '').trim();
            if (/^#[0-9a-f]{6}$/i.test(normalizedHex)) {
                const r = parseInt(normalizedHex.slice(1, 3), 16);
                const g = parseInt(normalizedHex.slice(3, 5), 16);
                const b = parseInt(normalizedHex.slice(5, 7), 16);
                const luma = (0.299 * r) + (0.587 * g) + (0.114 * b);
                return luma > 155 ? '#0b1220' : '#fff';
            }
            const normalized = normalizeColorToken(color);
            if (normalized.includes('light')) {
                return '#0b1220';
            }
            if (normalized.includes('white') || normalized.includes('yellow') || normalized.includes('gray') || normalized.includes('grey')) {
                return '#0b1220';
            }
            return '#fff';
        }

        function hasUpStatus(status) {
            return String(status || '').trim().toLowerCase() === 'up';
        }

        function paintPort(el, color, colorHex) {
            const normalizedColor = color || 'black';
            const indicator = el.querySelector('.switch-color-indicator');
            const number = el.querySelector('.switch-port-num');
            const normalizedHex = String(colorHex || '').trim();
            indicator.style.background = getColorCss(normalizedColor, normalizedHex);
            number.style.color = getPortNumberColor(normalizedColor, normalizedHex);
            el.dataset.color = normalizedColor;
            el.dataset.colorHex = normalizedHex;
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
            const tooltipParts = [
                '<strong>' + escapeHtml(portType) + ' Port ' + el.dataset.portNumber + '</strong>',
                'Patch port: ' + escapeHtml(label),
                'Status: ' + escapeHtml(status),
                'VLAN: ' + escapeHtml(vlanName),
                'Comments: ' + escapeHtml(comments)
            ];

            const isFiberPort = portType === 'SFP' || portType === 'SFP+';
            if (isFiberPort) {
                const fiberPorts = el.dataset.fiberPorts || '';
                const fiberPatch = el.dataset.fiberPatch || '';
                const fiberRack = el.dataset.fiberRack || '';
                const fiberPortLabel = el.dataset.fiberPortLabel || '';
                if (fiberPorts !== '') {
                    tooltipParts.push('Fiber Ports: ' + escapeHtml(fiberPorts));
                }
                if (fiberPatch !== '') {
                    tooltipParts.push('Fiber Patch: ' + escapeHtml(fiberPatch));
                }
                if (fiberRack !== '') {
                    tooltipParts.push('Fiber Rack: ' + escapeHtml(fiberRack));
                }
                if (fiberPortLabel !== '') {
                    tooltipParts.push('Fiber Port Label: ' + escapeHtml(fiberPortLabel));
                }
            }

            tooltip.innerHTML = tooltipParts.join('<br>');
            tooltip.style.opacity = '1';
            moveTooltip(ev);
        }

        function hideTooltip() {
            tooltip.style.opacity = '0';
        }

        function isFiberPortType(portType) {
            const normalized = normalizePortType(portType);
            return normalized === 'sfp' || normalized === 'sfp_plus';
        }

        function togglePortControlSections(portType) {
            const isFiber = isFiberPortType(portType);
            document.getElementById('vlanRow').style.display = '';
            document.getElementById('fiberPortsRow').style.display = isFiber ? '' : 'none';
            document.getElementById('fiberPatchRow').style.display = isFiber ? '' : 'none';
            document.getElementById('fiberRackRow').style.display = isFiber ? '' : 'none';
            document.getElementById('idfRow').style.display = isFiber ? '' : 'none';
        }

        function selectPort(el) {
            document.querySelectorAll('.switch-port').forEach(function (p) {
                p.style.outline = '';
            });
            el.style.outline = '3px solid rgba(9, 105, 218, 0.35)';
            selected = el;
            document.getElementById('switchControls').style.display = '';
            document.getElementById('switchControlsHint').style.display = 'none';
            togglePortControlSections(el.dataset.portType || '');
            document.getElementById('selectedPort').textContent = el.dataset.portNumber;
            document.getElementById('colorSelect').value = el.dataset.color || '';
            document.getElementById('statusSelect').value = el.dataset.status || '';
            document.getElementById('labelInput').value = el.dataset.label || '';
            document.getElementById('vlanSelect').value = el.dataset.vlanId || '';
            document.getElementById('fiberPortsSelect').value = String((selectedSwitchMeta && selectedSwitchMeta.fiber_id) || '');
            document.getElementById('fiberPatchSelect').value = String((selectedSwitchMeta && selectedSwitchMeta.fiber_patch_id) || '');
            document.getElementById('fiberRackSelect').value = String((selectedSwitchMeta && selectedSwitchMeta.fiber_rack_id) || '');
            document.getElementById('idfSelect').value = String(el.dataset.idfId || '');
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
            el.dataset.colorHex = p.color_hex || '';
            el.dataset.fiberPorts = readSwitchMeta('fiber_name');
            el.dataset.fiberPatch = readSwitchMeta('fiber_patch_name');
            el.dataset.fiberRack = readSwitchMeta('fiber_rack_name');
            el.dataset.idfId = p.idf_id || '';
            el.dataset.idfCode = p.idf_code || '';
            el.dataset.fiberPortLabel = readSwitchMeta('fiber_port_label');

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
            colorDiv.style.background = getColorCss(p.color, p.color_hex || '');
            num.style.color = getPortNumberColor(p.color, p.color_hex || '');
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
            const sfpRowAlt = document.getElementById('switchSfpRowAlt');
            const sfpPlusRow = document.getElementById('switchSfpPlusRow');
            const sfpPlusRowAlt = document.getElementById('switchSfpPlusRowAlt');
            row1.innerHTML = '';
            row2.innerHTML = '';
            sfpRow.innerHTML = '';
            sfpRowAlt.innerHTML = '';
            sfpPlusRow.innerHTML = '';
            sfpPlusRowAlt.innerHTML = '';

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
            const sfpPlusPorts = ports.filter(function (p) { return normalizePortType(p.port_type) === 'sfp_plus'; });
            renderFiberPortsByLayout(sfpRow, sfpRowAlt, sfpPorts);
            renderFiberPortsByLayout(sfpPlusRow, sfpPlusRowAlt, sfpPlusPorts);
            const showSfp = hasPortType('sfp') && sfpPorts.length > 0;
            const showSfpPlus = hasPortType('sfp_plus') && sfpPlusPorts.length > 0;
            document.getElementById('switchSfpLabel').textContent = 'SFP Ports';
            document.getElementById('switchSfpLabel').style.display = showSfp ? 'block' : 'none';
            sfpRow.style.display = showSfp ? 'flex' : 'none';
            sfpRowAlt.style.display = showSfp ? sfpRowAlt.style.display : 'none';
            document.getElementById('switchSfpPlusLabel').style.display = showSfpPlus ? 'block' : 'none';
            sfpPlusRow.style.display = showSfpPlus ? 'flex' : 'none';
            sfpPlusRowAlt.style.display = showSfpPlus ? sfpPlusRowAlt.style.display : 'none';

            const layoutLabel = String((selectedSwitchMeta && selectedSwitchMeta.port_numbering_layout) || 'Vertical');
            document.getElementById('switchLayoutSummary').textContent = buildLayoutSummary(layoutLabel, rj45Ports.length, sfpPorts.length, sfpPlusPorts.length);
            document.getElementById('fiberGrid').style.display = (showSfp || showSfpPlus) ? 'grid' : 'none';
        }


        function hydrateLookups(statuses, colors, vlans, fiberPorts, fiberPatches, fiberRacks, idfs) {
            statusOptions = Array.isArray(statuses) ? statuses : [];
            colorOptions = Array.isArray(colors) ? colors : [];
            vlanOptions = Array.isArray(vlans) ? vlans : [];
            fiberPortOptions = Array.isArray(fiberPorts) ? fiberPorts : [];
            fiberPatchOptions = Array.isArray(fiberPatches) ? fiberPatches : [];
            fiberRackOptions = Array.isArray(fiberRacks) ? fiberRacks : [];
            idfOptions = Array.isArray(idfs) ? idfs : [];

            const statusSelect = document.getElementById('statusSelect');
            const colorSelect = document.getElementById('colorSelect');
            const vlanSelect = document.getElementById('vlanSelect');
            const fiberPortsSelect = document.getElementById('fiberPortsSelect');
            const fiberPatchSelect = document.getElementById('fiberPatchSelect');
            const fiberRackSelect = document.getElementById('fiberRackSelect');
            const idfSelect = document.getElementById('idfSelect');

            statusSelect.innerHTML = '<option value="">-- choose status --</option>';
            colorSelect.innerHTML = '<option value="">-- choose color --</option>';
            vlanSelect.innerHTML = '<option value="">-- choose VLAN --</option>';
            fiberPortsSelect.innerHTML = '<option value="">-- choose fiber ports --</option>';
            fiberPatchSelect.innerHTML = '<option value="">-- choose fiber patch --</option>';
            fiberRackSelect.innerHTML = '<option value="">-- choose fiber rack --</option>';
            idfSelect.innerHTML = '<option value="">-- choose IDF --</option>';

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
                option.dataset.hex = item.hex_color || '';
                colorSelect.appendChild(option);

            });

            vlanOptions.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                vlanSelect.appendChild(option);
            });
            fiberPortOptions.forEach(function (item) {
                fiberPortsSelect.appendChild(new Option(item.name, item.id));
            });
            fiberPatchOptions.forEach(function (item) {
                fiberPatchSelect.appendChild(new Option(item.name, item.id));
            });
            fiberRackOptions.forEach(function (item) {
                fiberRackSelect.appendChild(new Option(item.name, item.id));
            });
            idfOptions.forEach(function (item) {
                idfSelect.appendChild(new Option(item.name, item.id));
            });
            colorSelect.appendChild(new Option('➕ Add', '__add_new__'));
            vlanSelect.appendChild(new Option('➕ Add', '__add_new__'));
            fiberPortsSelect.appendChild(new Option('➕ Add', '__add_new__'));
            fiberPatchSelect.appendChild(new Option('➕ Add', '__add_new__'));
            fiberRackSelect.appendChild(new Option('➕ Add', '__add_new__'));
            idfSelect.appendChild(new Option('➕ Add', '__add_new__'));
        }

        function fallbackLayout() {
            const rj45 = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.rj45_name) || '').replace(/\D+/g, ''), 10) || 24;
            const fiberPortsNumber = parseInt(String((selectedSwitchMeta && selectedSwitchMeta.fiber_ports_number) || '').replace(/\D+/g, ''), 10) || 0;
            const fiberCount = fiberPortsNumber;
            const fiberName = String((selectedSwitchMeta && selectedSwitchMeta.fiber_name) || '').toLowerCase();
            const fiberLabel = String((selectedSwitchMeta && selectedSwitchMeta.fiber_port_label) || '').toLowerCase();
            const fiberHint = (fiberLabel + ' ' + fiberName).trim();
            const sfpPlus = hasPortType('sfp_plus') && fiberHint.includes('sfp+') ? fiberCount : 0;
            const sfp = hasPortType('sfp') && !fiberHint.includes('sfp+') && fiberHint.includes('sfp') ? fiberCount : 0;
            return { rj45: rj45, sfp: sfp, sfp_plus: sfpPlus };
        }

        function parseJsonResponseText(text) {
            const raw = String(text || '').trim();
            if (!raw) {
                throw new Error('Empty response from server');
            }

            try {
                return JSON.parse(raw);
            } catch (e) {
                // Fallback: tolerate PHP notices/warnings before JSON payload.
                const firstBrace = raw.indexOf('{');
                const lastBrace = raw.lastIndexOf('}');
                if (firstBrace !== -1 && lastBrace > firstBrace) {
                    const candidate = raw.slice(firstBrace, lastBrace + 1);
                    try {
                        return JSON.parse(candidate);
                    } catch (ignored) {
                        // Keep original error below.
                    }
                }
                throw new Error('Invalid server JSON response');
            }
        }

        function savePort(payload, showMessage) {
            if (!csrfToken) {
                return Promise.reject(new Error('Missing CSRF token'));
            }

            function parseApiResponse(response) {
                return response.text().then(function (text) {
                    return parseJsonResponseText(text);
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
                        return parseJsonResponseText(text);
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
                    hydrateLookups(
                        data.statuses || [],
                        data.colors || [],
                        data.vlans || [],
                        data.fiber_ports || [],
                        data.fiber_patches || [],
                        data.fiber_racks || [],
                        data.idfs || []
                    );
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
            if (isFiberPortType(selected.dataset.portType || '')) {
                payload.fiber_port_id = document.getElementById('fiberPortsSelect').value || null;
                payload.fiber_patch_id = document.getElementById('fiberPatchSelect').value || null;
                payload.fiber_rack_id = document.getElementById('fiberRackSelect').value || null;
                payload.idf_id = document.getElementById('idfSelect').value || null;
            }
            const rackIdInput = document.getElementById('rackIdInput');
            if (rackIdInput && rackIdInput.value !== '') {
                payload.rack_id = rackIdInput.value;
            }
            const locationIdInput = document.getElementById('locationIdInput');
            if (locationIdInput && locationIdInput.value !== '') {
                payload.location_id = locationIdInput.value;
            }

            savePort(payload, true)
                .then(function () {
                    selected.dataset.status = payload.status || selected.dataset.status;
                    selected.dataset.label = payload.label || selected.dataset.label;
                    selected.dataset.vlanId = payload.vlan || '';
                    const selectedVlan = vlanOptions.find(function (item) { return String(item.id) === String(payload.vlan || ''); });
                    selected.dataset.vlanName = selectedVlan ? selectedVlan.name : '';
                    selected.dataset.vlanColor = selectedVlan ? (selectedVlan.color || '') : '';
                    selected.dataset.comments = payload.comments || '';
                    if (isFiberPortType(selected.dataset.portType || '')) {
                        const selectedFiberPortOption = document.getElementById('fiberPortsSelect').selectedOptions[0] || null;
                        const selectedFiberPatchOption = document.getElementById('fiberPatchSelect').selectedOptions[0] || null;
                        const selectedFiberRackOption = document.getElementById('fiberRackSelect').selectedOptions[0] || null;
                        const selectedIdfOption = document.getElementById('idfSelect').selectedOptions[0] || null;
                        selected.dataset.fiberPorts = selectedFiberPortOption ? (selectedFiberPortOption.text || '') : '';
                        selected.dataset.fiberPatch = selectedFiberPatchOption ? (selectedFiberPatchOption.text || '') : '';
                        selected.dataset.fiberRack = selectedFiberRackOption ? (selectedFiberRackOption.text || '') : '';
                        selected.dataset.idfId = payload.idf_id || '';
                        selected.dataset.idfCode = selectedIdfOption ? (selectedIdfOption.text || '') : '';
                    }
                    paintPort(selected, payload.color || selected.dataset.color, selected.dataset.colorHex || '');
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
            const oldColorHex = selected.dataset.colorHex || '';
            const selectedColorOption = this.selectedOptions[0] || null;
            const chosenColorHex = selectedColorOption ? String(selectedColorOption.dataset.hex || '') : '';
            paintPort(selected, chosenColor || oldColor, chosenColorHex || oldColorHex);

            savePort({ id: selected.dataset.id, switch_id: selectedSwitchId, color: chosenColor }, false)
                .catch(function () {
                    paintPort(selected, oldColor, oldColorHex);
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
