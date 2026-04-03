<?php
require '../../config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$originalData = null;
$csrfToken = itm_get_csrf_token();

function fetch_options($conn, $table, $label = 'name', $where = '') {
    $items = [];
    if (!equipment_table_exists($conn, $table)) {
        return $items;
    }
    $hasCompanyColumn = equipment_table_has_column($conn, $table, 'company_id');
    $companyScope = ($hasCompanyColumn && isset($GLOBALS['company_id']) && (int)$GLOBALS['company_id'] > 0)
        ? 'company_id = ' . (int)$GLOBALS['company_id']
        : '';

    $where = trim((string)$where);
    if ($companyScope !== '') {
        if ($where === '') {
            $where = 'WHERE ' . $companyScope;
        } elseif (stripos($where, 'where') === 0) {
            $where .= ' AND ' . $companyScope;
        } else {
            $where = 'WHERE ' . $where . ' AND ' . $companyScope;
        }
    }

    $res = mysqli_query($conn, "SELECT id, $label AS label FROM $table $where ORDER BY $label");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    return $items;
}

function equipment_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query(
        $conn,
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableEsc}' LIMIT 1"
    );

    $cache[$table] = $res && mysqli_num_rows($res) > 0;
    return $cache[$table];
}

function equipment_table_has_column(mysqli $conn, string $table, string $column): bool
{
    if (!equipment_table_exists($conn, $table)) {
        return false;
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res && mysqli_num_rows($res) > 0;
}

function equipment_table_varchar_length(mysqli $conn, string $table, string $column): int
{
    static $cache = [];

    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!equipment_table_exists($conn, $table)) {
        $cache[$cacheKey] = 0;
        return 0;
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $type = strtolower((string)($row['Type'] ?? ''));
    if (preg_match('/^varchar\((\d+)\)$/', $type, $matches) === 1) {
        $cache[$cacheKey] = (int)$matches[1];
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = 0;
    return 0;
}

function equipment_delete_idf_data(mysqli $conn, int $companyId, int $equipmentId): void
{
    if ($equipmentId <= 0 || $companyId <= 0) {
        return;
    }

    $hasCompanyColumn = equipment_table_has_column($conn, 'idf_positions', 'company_id');
    $companyFilter = $hasCompanyColumn ? " AND company_id = {$companyId}" : '';
    mysqli_query(
        $conn,
        "DELETE FROM idf_positions WHERE equipment_id = {$equipmentId}{$companyFilter}"
    );
}

function equipment_detect_upload_mime(array $file): string
{
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        return '';
    }

    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)@finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if ($mime !== '') {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = (string)@mime_content_type($tmpName);
        if ($mime !== '') {
            return strtolower($mime);
        }
    }

    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
        return strtolower((string)$imageInfo['mime']);
    }

    $extension = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $extensionMimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    return $extensionMimeMap[$extension] ?? '';
}

$types = fetch_options($conn, 'equipment_types');
$manufacturers = fetch_options($conn, 'manufacturers');
$locations = fetch_options($conn, 'it_locations', 'name', "WHERE company_id = $company_id");
$locationTypes = fetch_options($conn, 'location_types', 'name', "WHERE company_id = $company_id");
$racks = fetch_options($conn, 'racks', 'name', "WHERE company_id = $company_id");
$rackStatuses = fetch_options($conn, 'rack_statuses');
$statuses = fetch_options($conn, 'equipment_statuses');
$defaultStatusId = '';
foreach ($statuses as $statusItem) {
    if (strcasecmp((string)$statusItem['label'], 'Active') === 0) {
        $defaultStatusId = (string)$statusItem['id'];
        break;
    }
}
if ($defaultStatusId === '' && !empty($statuses)) {
    $defaultStatusId = (string)$statuses[0]['id'];
}
if ($defaultStatusId === '') {
    $defaultStatusId = '1';
}
$warrantyTypes = fetch_options($conn, 'warranty_types');
$printerTypes = fetch_options($conn, 'printer_device_types');
$workstationDeviceTypes = fetch_options($conn, 'workstation_device_types');
$workstationOsTypes = fetch_options($conn, 'workstation_os_types');
$workstationOsBuilds = fetch_options($conn, 'workstation_os_builds');
$workstationOsVersions = fetch_options($conn, 'workstation_os_versions');
$workstationRamOptions = fetch_options($conn, 'workstation_ram');
$workstationOfficeOptions = fetch_options($conn, 'workstation_office');
$switchRj45Options = fetch_options($conn, 'equipment_rj45');
$switchFiberOptions = fetch_options($conn, 'equipment_fiber');
$switchPoeOptions = fetch_options($conn, 'equipment_poe');
$switchEnvironmentOptions = fetch_options($conn, 'equipment_environment');
$switchFiberCountOptions = fetch_options($conn, 'equipment_fiber_count');
$switchPortNumberingLayoutOptions = fetch_options($conn, 'switch_port_numbering_layout');
$hasWorkstationOfficeIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_office_id');
$hasWorkstationOsBuildIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_os_build_id');
$hasWorkstationOsVersionIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_os_version_id');
$hasWorkstationRamIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_ram_id');
$hasWorkstationStorageColumn = equipment_table_has_column($conn, 'equipment', 'workstation_storage');
$hasWorkstationOsInstalledOnColumn = equipment_table_has_column($conn, 'equipment', 'workstation_os_installed_on');

$switchTypeId = 0;
$serverTypeId = 0;
$printerTypeId = 0;
foreach ($types as $typeItem) {
    if (strcasecmp((string)$typeItem['label'], 'Switch') === 0) {
        $switchTypeId = (int)$typeItem['id'];
    }
    if (strcasecmp((string)$typeItem['label'], 'Server') === 0) {
        $serverTypeId = (int)$typeItem['id'];
    }
    if (strcasecmp((string)$typeItem['label'], 'Printer') === 0) {
        $printerTypeId = (int)$typeItem['id'];
    }
}

$data = [
    'equipment_type_id' => '', 'manufacturer_id' => '', 'location_id' => '', 'rack_id' => '', 'name' => '',
    'serial_number' => '', 'model' => '', 'hostname' => '', 'ip_address' => '', 'mac_address' => '',
    'status_id' => $defaultStatusId, 'purchase_date' => '', 'purchase_cost' => '', 'warranty_expiry' => '', 'certificate_expiry' => '', 'warranty_type_id' => '',
    'is_printer' => 0, 'printer_device_type_id' => '', 'printer_color_capable' => 0,
    'is_workstation' => 0, 'is_server' => 0, 'is_pos' => 0, 'is_switch' => 0, 'workstation_device_type_id' => '', 'workstation_os_type_id' => '',
    'workstation_office_id' => '', 'workstation_os_build_id' => '', 'workstation_os_version_id' => '', 'workstation_ram_id' => '',
    'workstation_processor' => '', 'workstation_storage' => '', 'workstation_os_installed_on' => '',
    'switch_rj45_id' => '', 'switch_port_numbering_layout_id' => '1', 'switch_fiber_id' => '', 'switch_fiber_count_id' => '', 'switch_poe_id' => '', 'switch_environment_id' => '',
    'notes' => '', 'photo_filename' => '', 'active' => 1
];

if ($isEdit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) === 1) {
            $data = array_merge($data, mysqli_fetch_assoc($res));
            if (empty($data['switch_port_numbering_layout_id'])) {
                $data['switch_port_numbering_layout_id'] = '1';
            }
            $originalData = $data;
        } else {
            $error = 'Equipment record not found.';
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    foreach ($data as $k => $v) {
        if (in_array($k, ['is_printer', 'printer_color_capable', 'is_workstation', 'is_server', 'is_pos', 'is_switch'], true)) {
            $data[$k] = isset($_POST[$k]) ? 1 : 0;
        } elseif ($k === 'active') {
            $postedActive = $_POST['active'] ?? $data['active'];
            $data[$k] = (int)$postedActive === 1 ? 1 : 0;
        } else {
            $data[$k] = trim($_POST[$k] ?? '');
        }
    }

    foreach (['equipment_type_id','manufacturer_id','location_id','rack_id','status_id','warranty_type_id','printer_device_type_id','workstation_device_type_id','workstation_os_type_id','workstation_office_id','workstation_os_build_id','workstation_os_version_id','workstation_ram_id','switch_rj45_id','switch_port_numbering_layout_id','switch_fiber_id','switch_fiber_count_id','switch_poe_id','switch_environment_id'] as $fkField) {
        if (($data[$fkField] ?? '') === '__add_new__') {
            $data[$fkField] = '';
        }
    }

    if ((int)$data['status_id'] <= 0) {
        $data['status_id'] = $defaultStatusId;
    }

    $isSwitchEquipment = $switchTypeId > 0 && (int)$data['equipment_type_id'] === $switchTypeId;
    $isServerEquipment = $serverTypeId > 0 && (int)$data['equipment_type_id'] === $serverTypeId;

    if ($data['name'] === '' || (int)$data['equipment_type_id'] <= 0) {
        $error = 'Please fill required fields: Name, Type.';
    } elseif ($isSwitchEquipment && (int)$data['switch_rj45_id'] <= 0) {
        $error = 'Please fill required field: RJ45 Ports for switch equipment.';
    }

    if (!$error && $data['mac_address'] !== '') {
        $macColumnLength = equipment_table_varchar_length($conn, 'equipment', 'mac_address');
        if ($macColumnLength > 0 && strlen($data['mac_address']) > $macColumnLength) {
            $error = 'MAC Address is too long. Maximum allowed is ' . $macColumnLength . ' characters.';
        }
    }

    $photoFilename = $data['photo_filename'];
    $deleteCurrentPhoto = isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1';
    if (!$error && $isEdit && $deleteCurrentPhoto && $photoFilename !== '') {
        $existingPhotoPath = UPLOAD_PATH . $photoFilename;
        if (is_file($existingPhotoPath)) {
            @unlink($existingPhotoPath);
        }
        $photoFilename = '';
    }

    if (!$error && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed.';
        } elseif ($_FILES['photo']['size'] > MAX_FILE_SIZE) {
            $error = 'Photo exceeds max allowed size.';
        } else {
            $mime = equipment_detect_upload_mime($_FILES['photo']);
            if (!in_array($mime, ALLOWED_TYPES, true)) {
                $error = 'Unsupported image type.';
            } else {
                if (!is_dir(UPLOAD_PATH)) {
                    @mkdir(UPLOAD_PATH, 0775, true);
                }
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photoFilename = 'equipment_' . time() . '_' . mt_rand(1000, 9999) . '.' . strtolower($ext);
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_PATH . $photoFilename)) {
                    $error = 'Unable to save photo file.';
                } elseif ($isEdit && !empty($data['photo_filename']) && $data['photo_filename'] !== $photoFilename) {
                    $oldPhotoPath = UPLOAD_PATH . $data['photo_filename'];
                    if (is_file($oldPhotoPath)) {
                        @unlink($oldPhotoPath);
                    }
                }
            }
        }
    }

    if (!$error) {
        $equipment_type_id = (int)$data['equipment_type_id'];
        $manufacturer_id = (int)$data['manufacturer_id'] ?: 'NULL';
        $location_id = (int)$data['location_id'] ?: 'NULL';
        $rack_id = (int)$data['rack_id'] ?: 'NULL';
        $name = "'" . escape_sql($data['name'], $conn) . "'";
        $serial_number = $data['serial_number'] === '' ? 'NULL' : "'" . escape_sql($data['serial_number'], $conn) . "'";
        $model = $data['model'] === '' ? 'NULL' : "'" . escape_sql($data['model'], $conn) . "'";
        $hostname = $data['hostname'] === '' ? 'NULL' : "'" . escape_sql($data['hostname'], $conn) . "'";
        $ip_address = $data['ip_address'] === '' ? 'NULL' : "'" . escape_sql($data['ip_address'], $conn) . "'";
        $mac_address = $data['mac_address'] === '' ? 'NULL' : "'" . escape_sql($data['mac_address'], $conn) . "'";
        $status_id = (int)$data['status_id'] ?: 'NULL';
        $purchase_date = $data['purchase_date'] === '' ? 'NULL' : "'" . escape_sql($data['purchase_date'], $conn) . "'";
        $purchase_cost = $data['purchase_cost'] === '' ? 'NULL' : (float)$data['purchase_cost'];
        $warranty_expiry = $data['warranty_expiry'] === '' ? 'NULL' : "'" . escape_sql($data['warranty_expiry'], $conn) . "'";
        $certificate_expiry = ($isServerEquipment && $data['certificate_expiry'] !== '')
            ? "'" . escape_sql($data['certificate_expiry'], $conn) . "'"
            : 'NULL';
        $warranty_type_id = (int)$data['warranty_type_id'] ?: 'NULL';
        $is_printer = (int)$data['is_printer'];
        $printer_device_type_id = (int)$data['printer_device_type_id'] ?: 'NULL';
        $printer_color_capable = (int)$data['printer_color_capable'];
        $is_workstation = (int)$data['is_workstation'];
        $is_server = (int)$data['is_server'];
        $is_pos = (int)$data['is_pos'];
        $is_switch = (int)$data['is_switch'];
        $workstation_device_type_id = (int)$data['workstation_device_type_id'] ?: 'NULL';
        $workstation_os_type_id = (int)$data['workstation_os_type_id'] ?: 'NULL';
        $workstation_office_id = (int)$data['workstation_office_id'] ?: 'NULL';
        $workstation_os_build_id = (int)$data['workstation_os_build_id'] ?: 'NULL';
        $workstation_os_version_id = (int)$data['workstation_os_version_id'] ?: 'NULL';
        $workstation_ram_id = (int)$data['workstation_ram_id'] ?: 'NULL';
        $workstation_processor = $data['workstation_processor'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_processor'], $conn) . "'";
        $workstation_storage = $data['workstation_storage'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_storage'], $conn) . "'";
        $workstation_os_installed_on = $data['workstation_os_installed_on'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_os_installed_on'], $conn) . "'";
        $switch_rj45_id = (int)$data['switch_rj45_id'] ?: 'NULL';
        $switch_port_numbering_layout_id = (int)$data['switch_port_numbering_layout_id'] ?: '1';
        $switch_fiber_id = (int)$data['switch_fiber_id'] ?: 'NULL';
        $switch_fiber_count_id = (int)$data['switch_fiber_count_id'] ?: 'NULL';
        $switch_poe_id = (int)$data['switch_poe_id'] ?: 'NULL';
        $switch_environment_id = (int)$data['switch_environment_id'] ?: 'NULL';
        $notes = $data['notes'] === '' ? 'NULL' : "'" . escape_sql($data['notes'], $conn) . "'";
        $photo = $photoFilename === '' ? 'NULL' : "'" . escape_sql($photoFilename, $conn) . "'";
        $active = (int)$data['active'];

        $workstationOfficeUpdateSql = $hasWorkstationOfficeIdColumn ? "workstation_office_id=$workstation_office_id,\n                    " : '';
        $workstationOfficeInsertColumns = $hasWorkstationOfficeIdColumn ? ', workstation_office_id' : '';
        $workstationOfficeInsertValues = $hasWorkstationOfficeIdColumn ? ", $workstation_office_id" : '';
        $workstationOsBuildUpdateSql = $hasWorkstationOsBuildIdColumn ? "workstation_os_build_id=$workstation_os_build_id,\n                    " : '';
        $workstationOsBuildInsertColumns = $hasWorkstationOsBuildIdColumn ? ', workstation_os_build_id' : '';
        $workstationOsBuildInsertValues = $hasWorkstationOsBuildIdColumn ? ", $workstation_os_build_id" : '';
        $workstationOsVersionUpdateSql = $hasWorkstationOsVersionIdColumn ? "workstation_os_version_id=$workstation_os_version_id,\n                    " : '';
        $workstationOsVersionInsertColumns = $hasWorkstationOsVersionIdColumn ? ', workstation_os_version_id' : '';
        $workstationOsVersionInsertValues = $hasWorkstationOsVersionIdColumn ? ", $workstation_os_version_id" : '';
        $workstationRamUpdateSql = $hasWorkstationRamIdColumn ? "workstation_ram_id=$workstation_ram_id,\n                    " : '';
        $workstationRamInsertColumns = $hasWorkstationRamIdColumn ? ', workstation_ram_id' : '';
        $workstationRamInsertValues = $hasWorkstationRamIdColumn ? ", $workstation_ram_id" : '';
        $workstationStorageUpdateSql = $hasWorkstationStorageColumn ? "workstation_storage=$workstation_storage,\n                    " : '';
        $workstationStorageInsertColumns = $hasWorkstationStorageColumn ? ', workstation_storage' : '';
        $workstationStorageInsertValues = $hasWorkstationStorageColumn ? ", $workstation_storage" : '';
        $workstationOsInstalledOnUpdateSql = $hasWorkstationOsInstalledOnColumn ? "workstation_os_installed_on=$workstation_os_installed_on,\n                    " : '';
        $workstationOsInstalledOnInsertColumns = $hasWorkstationOsInstalledOnColumn ? ', workstation_os_installed_on' : '';
        $workstationOsInstalledOnInsertValues = $hasWorkstationOsInstalledOnColumn ? ", $workstation_os_installed_on" : '';

        if ($isEdit) {
            $sql = "UPDATE equipment SET equipment_type_id=$equipment_type_id, manufacturer_id=$manufacturer_id, location_id=$location_id, rack_id=$rack_id,
                    name=$name, serial_number=$serial_number, model=$model, hostname=$hostname, ip_address=$ip_address, mac_address=$mac_address,
                    status_id=$status_id, purchase_date=$purchase_date, purchase_cost=$purchase_cost, warranty_expiry=$warranty_expiry, certificate_expiry=$certificate_expiry,
                    warranty_type_id=$warranty_type_id, is_printer=$is_printer, printer_device_type_id=$printer_device_type_id,
                    printer_color_capable=$printer_color_capable,
                    is_workstation=$is_workstation, is_server=$is_server, is_pos=$is_pos, is_switch=$is_switch,
                    workstation_device_type_id=$workstation_device_type_id, workstation_os_type_id=$workstation_os_type_id,
                    $workstationOfficeUpdateSql$workstationOsBuildUpdateSql$workstationOsVersionUpdateSql$workstationRamUpdateSql
                    workstation_processor=$workstation_processor, $workstationStorageUpdateSql$workstationOsInstalledOnUpdateSql
                    switch_rj45_id=$switch_rj45_id, switch_port_numbering_layout_id=$switch_port_numbering_layout_id, switch_fiber_id=$switch_fiber_id, switch_fiber_count_id=$switch_fiber_count_id, switch_poe_id=$switch_poe_id, switch_environment_id=$switch_environment_id,
                    notes=$notes,
                    photo_filename=$photo, active=$active
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO equipment (company_id, equipment_type_id, manufacturer_id, location_id, rack_id, name, serial_number, model, hostname,
                    ip_address, mac_address, status_id, purchase_date, purchase_cost, warranty_expiry, certificate_expiry, warranty_type_id, is_printer,
                    printer_device_type_id, printer_color_capable, is_workstation, is_server, is_pos, is_switch, workstation_device_type_id,
                    workstation_os_type_id$workstationOfficeInsertColumns$workstationOsBuildInsertColumns$workstationOsVersionInsertColumns$workstationRamInsertColumns, workstation_processor$workstationStorageInsertColumns$workstationOsInstalledOnInsertColumns, switch_rj45_id, switch_port_numbering_layout_id, switch_fiber_id, switch_fiber_count_id, switch_poe_id, switch_environment_id, notes, photo_filename, active)
                    VALUES ($company_id, $equipment_type_id, $manufacturer_id, $location_id, $rack_id, $name, $serial_number, $model, $hostname,
                    $ip_address, $mac_address, $status_id, $purchase_date, $purchase_cost, $warranty_expiry, $certificate_expiry, $warranty_type_id, $is_printer,
                    $printer_device_type_id, $printer_color_capable, $is_workstation, $is_server, $is_pos, $is_switch, $workstation_device_type_id,
                    $workstation_os_type_id$workstationOfficeInsertValues$workstationOsBuildInsertValues$workstationOsVersionInsertValues$workstationRamInsertValues, $workstation_processor$workstationStorageInsertValues$workstationOsInstalledOnInsertValues, $switch_rj45_id, $switch_port_numbering_layout_id, $switch_fiber_id, $switch_fiber_count_id, $switch_poe_id, $switch_environment_id, $notes, $photo, $active)";
        }

        if (mysqli_query($conn, $sql)) {
            if ($isEdit && $originalData) {
                $switchConfigChanged = (int)$originalData['switch_rj45_id'] !== (int)$data['switch_rj45_id']
                    || (int)$originalData['switch_fiber_id'] !== (int)$data['switch_fiber_id']
                    || (int)$originalData['switch_fiber_count_id'] !== (int)$data['switch_fiber_count_id'];
                $changedAwayFromSwitch = (int)$originalData['equipment_type_id'] === $switchTypeId && $equipment_type_id !== $switchTypeId;

                if ($switchConfigChanged || $changedAwayFromSwitch) {
                    $hasEquipmentId = equipment_table_has_column($conn, 'switch_ports', 'equipment_id');
                    if ($hasEquipmentId) {
                        mysqli_query(
                            $conn,
                            "DELETE FROM switch_ports WHERE company_id = $company_id AND equipment_id = $id"
                        );
                    } else {
                        mysqli_query(
                            $conn,
                            "DELETE FROM switch_ports WHERE company_id = $company_id"
                        );
                    }
                }

                if ($changedAwayFromSwitch) {
                    equipment_delete_idf_data($conn, (int)$company_id, $id);
                }
            }

            if ($isEdit) {
                if ($equipment_type_id === $switchTypeId) {
                    header('Location: index.php?switch_id=' . $id . '&saved=1#switch-port-manager');
                    exit;
                }
                header('Location: edit.php?id=' . $id . '&saved=1');
                exit;
            }
            header('Location: index.php');
            exit;
        }
        $dbErrorCode = (int)mysqli_errno($conn);
        $dbErrorMessage = (string)mysqli_error($conn);
        $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}

function render_options($items, $selected = '') {
    foreach ($items as $i) {
        $sel = ((string)$selected === (string)$i['id']) ? 'selected' : '';
        echo '<option value="' . (int)$i['id'] . '" ' . $sel . '>' . sanitize($i['label']) . '</option>';
    }
}

$locationExtraFieldsConfig = [
    [
        'name' => 'type_id',
        'label' => 'Location Type',
        'type' => 'select',
        'options' => array_map(static function ($type) {
            return [
                'value' => (string)((int)($type['id'] ?? 0)),
                'label' => (string)($type['label'] ?? ''),
            ];
        }, $locationTypes),
    ],
];
$locationExtraFieldsJson = htmlspecialchars(
    json_encode($locationExtraFieldsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$rackExtraFieldsConfig = [
    [
        'name' => 'location_id',
        'label' => 'Location',
        'type' => 'select',
        'options' => array_map(static function ($location) {
            return [
                'value' => (string)((int)($location['id'] ?? 0)),
                'label' => (string)($location['label'] ?? ''),
            ];
        }, $locations),
    ],
    [
        'name' => 'status_id',
        'label' => 'Rack Status',
        'type' => 'select',
        'options' => array_map(static function ($status) {
            return [
                'value' => (string)((int)($status['id'] ?? 0)),
                'label' => (string)($status['label'] ?? ''),
            ];
        }, $rackStatuses),
    ],
];
$rackExtraFieldsJson = htmlspecialchars(
    json_encode($rackExtraFieldsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$currentPhotoUrl = '';
if (!empty($data['photo_filename'])) {
    $currentPhotoUrl = UPLOAD_URL . rawurlencode((string)$data['photo_filename']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'New'; ?> Equipment</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
    .photo-preview-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.65);
        z-index: 1200;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .photo-preview-content {
        background: var(--surface, #ffffff);
        border: 1px solid var(--border, #ddd);
        border-radius: 10px;
        max-width: min(90vw, 900px);
        max-height: 90vh;
        overflow: auto;
        padding: 12px;
        text-align: center;
    }
    .photo-preview-content img {
        max-width: 100%;
        max-height: calc(90vh - 120px);
        border-radius: 8px;
    }
    .photo-preview-actions {
        margin-bottom: 10px;
        text-align: right;
    }
    .photo-preview-link {
        margin-left: 8px;
    }
    </style>
</head>
<body>
<div class="container"><?php include '../../includes/sidebar.php'; ?><div class="main-content"><?php include '../../includes/header.php'; ?><div class="content">
    <h1><?php echo $isEdit ? '✏️ Edit' : '➕ New'; ?> Equipment</h1>
    <?php if ($success): ?><div class="alert alert-success">Equipment updated successfully.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <div class="form-row">
                <div class="form-group"><label>Name *</label><input required name="name" value="<?php echo sanitize($data['name']); ?>"></div>
                <div class="form-group"><label>Type *</label><select name="equipment_type_id" required data-addable-select="1" data-add-table="equipment_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="equipment type"><option value="">-- Select --</option><?php render_options($types, $data['equipment_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Manufacturer</label><select name="manufacturer_id" data-addable-select="1" data-add-table="manufacturers" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="manufacturer"><option value="">-- None --</option><?php render_options($manufacturers, $data['manufacturer_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Location</label><select name="location_id" data-addable-select="1" data-add-table="it_locations" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="location" data-add-extra-fields="<?php echo $locationExtraFieldsJson; ?>"><option value="">-- None --</option><?php render_options($locations, $data['location_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Rack</label><select name="rack_id" data-addable-select="1" data-add-table="racks" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rack" data-add-extra-fields="<?php echo $rackExtraFieldsJson; ?>"><option value="">-- None --</option><?php render_options($racks, $data['rack_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Serial Number</label><input name="serial_number" value="<?php echo sanitize($data['serial_number']); ?>"></div>
                <div class="form-group"><label>Model</label><input name="model" value="<?php echo sanitize($data['model']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Hostname</label><input name="hostname" value="<?php echo sanitize($data['hostname']); ?>"></div>
                <div class="form-group"><label>IP Address</label><input name="ip_address" value="<?php echo sanitize($data['ip_address']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>MAC Address</label><input name="mac_address" value="<?php echo sanitize($data['mac_address']); ?>"></div>
                <div class="form-group"><label>Warranty Type</label><select name="warranty_type_id" data-addable-select="1" data-add-table="warranty_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="warranty type"><option value="">-- Select --</option><?php render_options($warrantyTypes, $data['warranty_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?php echo sanitize($data['purchase_date']); ?>"></div>
                <div class="form-group"><label>Purchase Cost</label><input type="number" step="0.01" name="purchase_cost" value="<?php echo sanitize($data['purchase_cost']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Warranty Expiry</label><input type="date" name="warranty_expiry" value="<?php echo sanitize($data['warranty_expiry']); ?>"></div>
                <div class="form-group"><label>Photo Upload</label><input type="file" name="photo" accept="image/*"><?php if (!empty($data['photo_filename'])): ?><input type="hidden" name="delete_photo" id="deletePhotoInput" value="0"><div class="form-hint" id="currentPhotoHint">Current: <?php echo sanitize($data['photo_filename']); ?><a href="#" class="photo-preview-link" id="openPhotoPreview">View</a><button type="button" class="btn btn-sm" id="deletePhotoButton" style="margin-left:8px;">Delete</button></div><?php endif; ?></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation Office</label><select name="workstation_office_id" data-addable-select="1" data-add-table="workstation_office" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation office"><option value="">-- None --</option><?php render_options($workstationOfficeOptions, $data['workstation_office_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"></div>
            </div>
            <div id="server-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group"><label>Certificate Expiry</label><input type="date" name="certificate_expiry" value="<?php echo sanitize($data['certificate_expiry']); ?>"></div>
                    <div class="form-group"></div>
                </div>
            </div>
            <div id="printer-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group"><label>Printer Type</label><select name="printer_device_type_id" data-addable-select="1" data-add-table="printer_device_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="printer type"><option value="">-- None --</option><?php render_options($printerTypes, $data['printer_device_type_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label><input type="checkbox" name="printer_color_capable" <?php echo (int)$data['printer_color_capable'] === 1 ? 'checked' : ''; ?>> Printer Color Capable</label></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation Processor</label><input name="workstation_processor" value="<?php echo sanitize($data['workstation_processor']); ?>"></div>
                <div class="form-group"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation Device Type</label><select name="workstation_device_type_id" data-addable-select="1" data-add-table="workstation_device_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation device type"><option value="">-- None --</option><?php render_options($workstationDeviceTypes, $data['workstation_device_type_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Workstation OS Type</label><select name="workstation_os_type_id" data-addable-select="1" data-add-table="workstation_os_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation os type"><option value="">-- None --</option><?php render_options($workstationOsTypes, $data['workstation_os_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>RAM</label><select name="workstation_ram_id" data-addable-select="1" data-add-table="workstation_ram" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="ram"><option value="">-- None --</option><?php render_options($workstationRamOptions, $data['workstation_ram_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Storage (GB/TB)</label><input name="workstation_storage" value="<?php echo sanitize($data['workstation_storage']); ?>" placeholder="e.g. 512 GB / 1 TB"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation OS Build</label><select name="workstation_os_build_id" data-addable-select="1" data-add-table="workstation_os_builds" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation os build"><option value="">-- None --</option><?php render_options($workstationOsBuilds, $data['workstation_os_build_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Workstation OS Version</label><select name="workstation_os_version_id" data-addable-select="1" data-add-table="workstation_os_versions" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation os version"><option value="">-- None --</option><?php render_options($workstationOsVersions, $data['workstation_os_version_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation OS Installed On</label><input type="date" name="workstation_os_installed_on" value="<?php echo sanitize($data['workstation_os_installed_on']); ?>"></div>
                <div class="form-group"></div>
            </div>
            <div id="switch-fields" style="display:none;">
                <h3 style="margin-top:20px;">Switch Details</h3>
                <div class="form-row">
                    <div class="form-group"><label>RJ45 Ports *</label><select name="switch_rj45_id" data-addable-select="1" data-add-table="equipment_rj45" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rj45 port option"><option value="">-- Select --</option><?php render_options($switchRj45Options, $data['switch_rj45_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>Port Numbering Layout</label><select name="switch_port_numbering_layout_id" data-addable-select="1" data-add-table="switch_port_numbering_layout" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="port numbering layout"><option value="">-- Select --</option><?php render_options($switchPortNumberingLayoutOptions, $data['switch_port_numbering_layout_id']); ?><option value="__add_new__">➕</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Fiber Ports</label><select name="switch_fiber_id" data-addable-select="1" data-add-table="equipment_fiber" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="fiber port option"><option value="">-- None --</option><?php render_options($switchFiberOptions, $data['switch_fiber_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Fiber Count</label><select name="switch_fiber_count_id" data-addable-select="1" data-add-table="equipment_fiber_count" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="fiber count option"><option value="">-- None --</option><?php render_options($switchFiberCountOptions, $data['switch_fiber_count_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>PoE Type</label><select name="switch_poe_id" data-addable-select="1" data-add-table="equipment_poe" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="poe type"><option value="">-- None --</option><?php render_options($switchPoeOptions, $data['switch_poe_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>Management</label><select name="switch_environment_id" data-addable-select="1" data-add-table="equipment_environment" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="management type"><option value="">-- None --</option><?php render_options($switchEnvironmentOptions, $data['switch_environment_id']); ?><option value="__add_new__">➕</option></select></div>
                </div>
            </div>
            <div class="form-group"><label>Comments</label><textarea name="notes" rows="5"><?php echo sanitize($data['notes']); ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label><input type="checkbox" name="is_printer" <?php echo (int)$data['is_printer'] === 1 ? 'checked' : ''; ?>> Is Printer</label></div>
                <div class="form-group"><label><input type="checkbox" name="is_workstation" <?php echo (int)$data['is_workstation'] === 1 ? 'checked' : ''; ?>> Is Workstation</label></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label><input type="checkbox" name="is_switch" <?php echo (int)$data['is_switch'] === 1 ? 'checked' : ''; ?>> Is Switch</label></div>
                <div class="form-group"><label><input type="checkbox" name="is_server" <?php echo (int)$data['is_server'] === 1 ? 'checked' : ''; ?>> Is Server</label></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label><input type="checkbox" name="is_pos" <?php echo (int)$data['is_pos'] === 1 ? 'checked' : ''; ?>> Is POS</label></div>
                <div class="form-group"><label>Status</label><select name="status_id" data-addable-select="1" data-add-table="equipment_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="status"><option value="">-- Select --</option><?php render_options($statuses, $data['status_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <input type="hidden" name="active" value="<?php echo (int)$data['active']; ?>">
            <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">✖️</a></div>
        </form>
    </div>
</div></div></div>
<?php if ($currentPhotoUrl !== ''): ?>
<div class="photo-preview-modal" id="photoPreviewModal" aria-hidden="true">
    <div class="photo-preview-content" role="dialog" aria-modal="true" aria-label="Current equipment photo" onclick="event.stopPropagation()">
        <div class="photo-preview-actions">
            <button type="button" class="btn btn-sm" id="closePhotoPreview">Close</button>
        </div>
        <img src="<?php echo sanitize($currentPhotoUrl); ?>" alt="Current equipment photo">
    </div>
</div>
<?php endif; ?>
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script>
(function () {
    var typeSelect = document.querySelector('select[name="equipment_type_id"]');
    var switchFields = document.getElementById('switch-fields');
    var serverFields = document.getElementById('server-fields');
    var printerFields = document.getElementById('printer-fields');
    var isPrinterCheckbox = document.querySelector('input[name="is_printer"]');
    var switchTypeId = '<?php echo (int)$switchTypeId; ?>';
    var serverTypeId = '<?php echo (int)$serverTypeId; ?>';
    var printerTypeId = '<?php echo (int)$printerTypeId; ?>';

    function toggleSwitchFields() {
        if (!typeSelect || !switchFields) {
            return;
        }
        var show = switchTypeId !== '0' && typeSelect.value === switchTypeId;
        switchFields.style.display = show ? 'block' : 'none';
        if (!show) {
            switchFields.querySelectorAll('select').forEach(function (el) {
                el.value = '';
            });
        }
    }

    function toggleServerFields() {
        if (!typeSelect || !serverFields) {
            return;
        }
        var show = serverTypeId !== '0' && typeSelect.value === serverTypeId;
        serverFields.style.display = show ? 'block' : 'none';
        if (!show) {
            var certificateInput = serverFields.querySelector('input[name="certificate_expiry"]');
            if (certificateInput) {
                certificateInput.value = '';
            }
        }
    }

    function togglePrinterFields() {
        if (!typeSelect || !printerFields) {
            return;
        }

        var matchesPrinterType = printerTypeId !== '0' && typeSelect.value === printerTypeId;
        var matchesPrinterFlag = !!(isPrinterCheckbox && isPrinterCheckbox.checked);
        var show = matchesPrinterType || matchesPrinterFlag;
        printerFields.style.display = show ? 'block' : 'none';

        if (!show) {
            var printerTypeSelect = printerFields.querySelector('select[name="printer_device_type_id"]');
            if (printerTypeSelect) {
                printerTypeSelect.value = '';
            }
            var colorCapableInput = printerFields.querySelector('input[name="printer_color_capable"]');
            if (colorCapableInput) {
                colorCapableInput.checked = false;
            }
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleSwitchFields);
        typeSelect.addEventListener('change', toggleServerFields);
        typeSelect.addEventListener('change', togglePrinterFields);
        toggleSwitchFields();
        toggleServerFields();
        togglePrinterFields();
    }

    if (isPrinterCheckbox) {
        isPrinterCheckbox.addEventListener('change', togglePrinterFields);
        togglePrinterFields();
    }

    var openPhotoPreview = document.getElementById('openPhotoPreview');
    var photoPreviewModal = document.getElementById('photoPreviewModal');
    var closePhotoPreview = document.getElementById('closePhotoPreview');
    var deletePhotoButton = document.getElementById('deletePhotoButton');
    var deletePhotoInput = document.getElementById('deletePhotoInput');
    var currentPhotoHint = document.getElementById('currentPhotoHint');
    var photoInput = document.querySelector('input[name="photo"]');

    function hidePhotoModal() {
        if (!photoPreviewModal) {
            return;
        }
        photoPreviewModal.style.display = 'none';
        photoPreviewModal.setAttribute('aria-hidden', 'true');
    }

    if (openPhotoPreview && photoPreviewModal) {
        openPhotoPreview.addEventListener('click', function (event) {
            event.preventDefault();
            photoPreviewModal.style.display = 'flex';
            photoPreviewModal.setAttribute('aria-hidden', 'false');
        });

        photoPreviewModal.addEventListener('click', hidePhotoModal);

        if (closePhotoPreview) {
            closePhotoPreview.addEventListener('click', hidePhotoModal);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hidePhotoModal();
            }
        });
    }

    if (deletePhotoButton && deletePhotoInput) {
        deletePhotoButton.addEventListener('click', function () {
            deletePhotoInput.value = '1';
            hidePhotoModal();
            if (currentPhotoHint) {
                currentPhotoHint.textContent = 'Current photo will be deleted after you save.';
            }
            if (photoInput) {
                photoInput.value = '';
            }
            deletePhotoButton.disabled = true;
        });
    }
})();
</script>
</body>
</html>
