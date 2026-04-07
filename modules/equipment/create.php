<?php
/**
 * Equipment Module - Create/Edit
 * 
 * A highly dynamic form for managing IT equipment records.
 * Features:
 * - Conditional fields based on Equipment Type (Switch, Server, Printer, etc.)
 * - Multiple photo upload and management gallery
 * - Automatic role flag synchronization (e.g., selecting 'Switch' type toggles 'is_switch' flag)
 * - Support for inline creation of locations, racks, and types
 * - Robust validation for numeric ranges and character lengths
 */

require '../../config/config.php';

// Route Parameters
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$originalData = null;
$csrfToken = itm_get_csrf_token();

/**
 * Fetches options for form select boxes, scoped to the current company where applicable
 */
function fetch_options($conn, $table, $label = 'name', $where = '') {
    $items = [];
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($label)) { return $items; }
    if (!equipment_table_exists($conn, $table)) { return $items; }
    
    $hasCompanyColumn = equipment_table_has_column($conn, $table, 'company_id');
    $companyId = (isset($GLOBALS['company_id']) && (int)$GLOBALS['company_id'] > 0) ? (int)$GLOBALS['company_id'] : 0;
    $companyScope = ($hasCompanyColumn && $companyId > 0) ? 'company_id = ?' : '';

    $where = trim((string)$where);
    if ($companyScope !== '') {
        if ($where === '') { $where = 'WHERE ' . $companyScope; }
        elseif (str_starts_with(strtoupper($where), 'WHERE')) { $where .= ' AND ' . $companyScope; }
        else { $where = 'WHERE ' . $where . ' AND ' . $companyScope; }
    }

    $sql = "SELECT id, `{$label}` AS label FROM `{$table}` $where ORDER BY `{$label}`";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($hasCompanyColumn && $companyId > 0) { mysqli_stmt_bind_param($stmt, 'i', $companyId); }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) { $items[] = $row; }
        mysqli_stmt_close($stmt);
    }
    return $items;
}

/**
 * Checks if a database table exists
 */
function equipment_table_exists(mysqli $conn, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) { return $cache[$table]; }
    if (!itm_is_safe_identifier($table)) { $cache[$table] = false; return false; }

    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cache[$table] = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);
    } else { $cache[$table] = false; }
    return $cache[$table];
}

/**
 * Checks if a table has a specific column
 */
function equipment_table_has_column(mysqli $conn, string $table, string $column): bool {
    if (!equipment_table_exists($conn, $table)) { return false; }
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) { return false; }

    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $has = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);
        return $has;
    }
    return false;
}

/**
 * Retrieves the maximum character length for a VARCHAR column
 */
function equipment_table_varchar_length(mysqli $conn, string $table, string $column): int {
    static $cache = [];
    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) { return $cache[$cacheKey]; }
    if (!equipment_table_exists($conn, $table) || !itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) { return 0; }

    $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $length = isset($row['CHARACTER_MAXIMUM_LENGTH']) ? (int)$row['CHARACTER_MAXIMUM_LENGTH'] : 0;
        $cache[$cacheKey] = $length > 0 ? $length : 0;
        return $cache[$cacheKey];
    }
    return 0;
}

/**
 * Cleanup function for switch-specific data when equipment type changes
 */
function equipment_delete_idf_data(mysqli $conn, int $companyId, int $equipmentId): void {
    if ($equipmentId <= 0 || $companyId <= 0) { return; }
    $hasCompanyColumn = equipment_table_has_column($conn, 'idf_positions', 'company_id');
    $sql = 'DELETE FROM idf_positions WHERE equipment_id = ?';
    $types = 'i'; $params = [$equipmentId];
    if ($hasCompanyColumn) { $sql .= ' AND company_id = ?'; $types .= 'i'; $params[] = $companyId; }
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) { mysqli_stmt_bind_param($stmt, $types, ...$params); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
}

/**
 * Securely detects the MIME type of an uploaded file
 */
function equipment_detect_upload_mime(array $file): string {
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) { return ''; }
    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)@finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if ($mime !== '') { return strtolower($mime); }
        }
    }
    // Fallbacks
    if (function_exists('mime_content_type')) {
        $mime = (string)@mime_content_type($tmpName);
        if ($mime !== '') { return strtolower($mime); }
    }
    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') { return strtolower((string)$imageInfo['mime']); }
    return '';
}

/**
 * Parses the comma-separated or JSON list of photo filenames stored in the DB
 */
function equipment_parse_photo_filenames($rawValue): array {
    if ($rawValue === null) { return []; }
    $value = trim((string)$rawValue);
    if ($value === '') { return []; }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) { $items = $decoded; }
    elseif (str_contains($value, ',')) { $items = explode(',', $value); }
    else { $items = [$value]; }
    $filenames = [];
    foreach ($items as $item) {
        $filename = basename((string)$item);
        if ($filename !== '') { $filenames[$filename] = $filename; }
    }
    return array_values($filenames);
}

/**
 * Serializes photo filenames for storage
 */
function equipment_encode_photo_filenames(array $filenames): string {
    $clean = [];
    foreach ($filenames as $filename) {
        $base = basename((string)$filename);
        if ($base !== '') { $clean[$base] = $base; }
    }
    $clean = array_values($clean);
    if (count($clean) === 0) { return ''; }
    if (count($clean) === 1) { return $clean[0]; }
    return json_encode($clean, JSON_UNESCAPED_SLASHES);
}

// PRE-FETCH FORM OPTIONS
$types = fetch_options($conn, 'equipment_types');
$manufacturers = fetch_options($conn, 'manufacturers');
$locations = fetch_options($conn, 'it_locations', 'name');
$locationTypes = fetch_options($conn, 'location_types', 'name');
$racks = fetch_options($conn, 'racks', 'name');
$rackStatuses = fetch_options($conn, 'rack_statuses');
$statuses = fetch_options($conn, 'equipment_statuses');
$defaultStatusId = '1';
foreach ($statuses as $statusItem) { if (strcasecmp((string)$statusItem['label'], 'Active') === 0) { $defaultStatusId = (string)$statusItem['id']; break; } }
$warrantyTypes = fetch_options($conn, 'warranty_types');
$printerTypes = fetch_options($conn, 'printer_device_types');
$workstationDeviceTypes = fetch_options($conn, 'workstation_device_types');
$workstationOsTypes = fetch_options($conn, 'workstation_os_types');
$workstationRamOptions = fetch_options($conn, 'workstation_ram');
$workstationOfficeOptions = fetch_options($conn, 'workstation_office');
$switchRj45Options = fetch_options($conn, 'equipment_rj45');
$switchFiberOptions = fetch_options($conn, 'equipment_fiber');
$switchFiberPatchOptions = fetch_options($conn, 'equipment_fiber_patch');
$switchFiberRackOptions = fetch_options($conn, 'equipment_fiber_rack');
$switchPoeOptions = fetch_options($conn, 'equipment_poe');
$switchEnvironmentOptions = fetch_options($conn, 'equipment_environment');
$switchFiberCountOptions = fetch_options($conn, 'equipment_fiber_count');
$switchPortNumberingLayoutOptions = fetch_options($conn, 'switch_port_numbering_layout');

// Identify internal ID mapping for key equipment types to drive frontend logic
$switchTypeId = 0; $serverTypeId = 0; $printerTypeId = 0; $workstationTypeId = 0; $posTypeId = 0;
foreach ($types as $typeItem) {
    if (strcasecmp((string)$typeItem['label'], 'Switch') === 0) { $switchTypeId = (int)$typeItem['id']; }
    if (strcasecmp((string)$typeItem['label'], 'Server') === 0) { $serverTypeId = (int)$typeItem['id']; }
    if (strcasecmp((string)$typeItem['label'], 'Printer') === 0) { $printerTypeId = (int)$typeItem['id']; }
    if (strcasecmp((string)$typeItem['label'], 'Workstation') === 0) { $workstationTypeId = (int)$typeItem['id']; }
    if (strcasecmp((string)$typeItem['label'], 'POS') === 0) { $posTypeId = (int)$typeItem['id']; }
}

$data = [
    'equipment_type_id' => '', 'manufacturer_id' => '', 'location_id' => '', 'rack_id' => '', 'name' => '',
    'serial_number' => '', 'model' => '', 'hostname' => '', 'ip_address' => '', 'patch_port' => '', 'mac_address' => '',
    'status_id' => $defaultStatusId, 'purchase_date' => '', 'purchase_cost' => '', 'warranty_expiry' => '', 'certificate_expiry' => '', 'warranty_type_id' => '',
    'is_printer' => 0, 'printer_device_type_id' => '', 'printer_color_capable' => 0, 'printer_scan' => 0,
    'is_workstation' => 0, 'is_server' => 0, 'is_pos' => 0, 'is_switch' => 0, 'workstation_device_type_id' => '', 'workstation_os_type_id' => '',
    'workstation_office_id' => '', 'workstation_os_version_id' => '', 'workstation_ram_id' => '',
    'workstation_processor' => '', 'workstation_storage' => '', 'workstation_os_installed_on' => '',
    'switch_rj45_id' => '', 'switch_port_numbering_layout_id' => '1', 'switch_fiber_id' => '', 'switch_fiber_patch_id' => '', 'switch_fiber_rack_id' => '', 'switch_fiber_count_id' => '', 'switch_fiber_ports_number' => '', 'switch_fiber_port_label' => '', 'switch_poe_id' => '', 'switch_environment_id' => '',
    'notes' => '', 'photo_filename' => '', 'active' => 1
];

// Load existing record
if ($isEdit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) === 1) {
            $data = array_merge($data, mysqli_fetch_assoc($res));
            $originalData = $data;
        } else { $error = 'Equipment record not found.'; }
        mysqli_stmt_close($stmt);
    }
}

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    
    // Process input data
    foreach ($data as $k => $v) {
        if (in_array($k, ['is_printer', 'printer_color_capable', 'printer_scan', 'is_workstation', 'is_server', 'is_pos', 'is_switch'], true)) {
            $data[$k] = isset($_POST[$k]) ? 1 : 0;
        } elseif ($k === 'photo_filename') {
            $data[$k] = $isEdit ? (string)($originalData['photo_filename'] ?? $v) : '';
        } else { $data[$k] = trim($_POST[$k] ?? ''); }
    }

    // Required fields check
    if ($data['name'] === '' || (int)$data['equipment_type_id'] <= 0) {
        $error = 'Please fill required fields: Name, Type.';
    }

    // --- PHOTO PROCESSING ---
    $photoFilenames = equipment_parse_photo_filenames($data['photo_filename']);
    $photoFilenamesToDeleteAfterSave = [];
    
    // Handle photo removal
    if (!$error && $isEdit && isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1') {
        $photoFilenamesToDeleteAfterSave = $photoFilenames; $photoFilenames = [];
    }

    // Handle new photo uploads
    if (!$error && isset($_FILES['photo']) && is_array($_FILES['photo']['error'])) {
        foreach ($_FILES['photo']['error'] as $index => $fileError) {
            if ($fileError === UPLOAD_ERR_NO_FILE) continue;
            if ($fileError !== UPLOAD_ERR_OK) { $error = 'One of the photo uploads failed.'; break; }
            
            $currentFile = ['tmp_name' => $_FILES['photo']['tmp_name'][$index], 'name' => $_FILES['photo']['name'][$index]];
            if (in_array(equipment_detect_upload_mime($currentFile), ALLOWED_TYPES, true)) {
                $ext = pathinfo((string)$currentFile['name'], PATHINFO_EXTENSION);
                $photoFilename = 'equipment_' . time() . '_' . mt_rand(1000, 9999) . '.' . strtolower((string)$ext);
                if (move_uploaded_file($currentFile['tmp_name'], UPLOAD_PATH . $photoFilename)) {
                    $photoFilenames[] = $photoFilename;
                }
            }
        }
    }

    // --- DATABASE UPDATE ---
    if (!$error) {
        // Build dynamic query based on available columns
        // ... (Logic for building query fields, types, and params)

        // Execute prepared INSERT or UPDATE
        // ...
        
        // After success: cleanup deleted physical files and handle switch port re-generation if config changed
        if ($execOk) {
            // ... (Post-save cleanup logic)
            header('Location: index.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'New'; ?> Equipment</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $isEdit ? '✏️ Edit' : '➕ New'; ?> Equipment</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
            
            <div class="card">
                <form id="equipmentForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    
                    <!-- BASIC INFO -->
                    <div class="form-row form-row-3">
                        <div class="form-group"><label>Name *</label><input required name="name" value="<?php echo sanitize($data['name']); ?>"></div>
                        <div class="form-group"><label>Type *</label><select name="equipment_type_id" required data-addable-select="1" data-add-table="equipment_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1"><option value="">-- Select --</option><?php render_options($types, $data['equipment_type_id']); ?><option value="__add_new__">➕</option></select></div>
                        <div class="form-group"><label>Location</label><select name="location_id" data-addable-select="1" data-add-table="it_locations" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1"><option value="">-- None --</option><?php render_options($locations, $data['location_id']); ?><option value="__add_new__">➕</option></select></div>
                    </div>

                    <!-- PHOTO MANAGEMENT -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Photo Upload</label>
                            <input type="file" name="photo[]" accept="image/*" multiple>
                            <div class="form-hint">Selected: <?php echo count($currentPhotoFilenames); ?></div>
                        </div>
                    </div>

                    <!-- CONDITIONAL FIELDS (PRINTER/SERVER/SWITCH) -->
                    <!-- ... -->

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾 Save</button>
                        <a href="index.php" class="btn">✖️ Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JS Logic for Dynamic Toggles and Photo Previews -->
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script>
(function () {
    // ... (Logic for showing/hiding fields based on selected type)
})();
</script>
</body>
</html>
