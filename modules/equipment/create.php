<?php
require '../../config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

function fetch_options($conn, $table, $label = 'name', $where = '') {
    $items = [];
    $res = mysqli_query($conn, "SELECT id, $label AS label FROM $table $where ORDER BY $label");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    return $items;
}

$types = fetch_options($conn, 'equipment_types');
$manufacturers = fetch_options($conn, 'manufacturers');
$locations = fetch_options($conn, 'it_locations', 'name', "WHERE company_id = $company_id");
$racks = fetch_options($conn, 'racks', 'name', "WHERE company_id = $company_id");
$statuses = fetch_options($conn, 'equipment_statuses');
$warrantyTypes = fetch_options($conn, 'warranty_types');
$printerTypes = fetch_options($conn, 'printer_device_types');
$workstationDeviceTypes = fetch_options($conn, 'workstation_device_types');
$workstationOsTypes = fetch_options($conn, 'workstation_os_types');
$switchRj45Options = fetch_options($conn, 'equipment_rj45');
$switchFiberOptions = fetch_options($conn, 'equipment_fiber');
$switchPoeOptions = fetch_options($conn, 'equipment_poe');
$switchEnvironmentOptions = fetch_options($conn, 'equipment_environment');
$switchFiberCountOptions = fetch_options($conn, 'equipment_fiber_count');

$switchTypeId = 0;
foreach ($types as $typeItem) {
    if (strcasecmp((string)$typeItem['label'], 'Switch') === 0) {
        $switchTypeId = (int)$typeItem['id'];
        break;
    }
}

$data = [
    'equipment_type_id' => '', 'manufacturer_id' => '', 'location_id' => '', 'rack_id' => '', 'name' => '',
    'serial_number' => '', 'model' => '', 'hostname' => '', 'ip_address' => '', 'mac_address' => '',
    'status_id' => '', 'purchase_date' => '', 'purchase_cost' => '', 'warranty_expiry' => '', 'warranty_type_id' => '',
    'is_printer' => 0, 'printer_device_type_id' => '', 'printer_color_capable' => 0, 'printer_print_speed_ppm' => '',
    'is_workstation' => 0, 'workstation_device_type_id' => '', 'workstation_os_type_id' => '',
    'workstation_processor' => '', 'workstation_memory_gb' => '',
    'switch_rj45_id' => '', 'switch_fiber_id' => '', 'switch_fiber_count_id' => '', 'switch_poe_id' => '', 'switch_environment_id' => '',
    'notes' => '', 'photo_filename' => '', 'active' => 1
];

if ($isEdit) {
    $res = mysqli_query($conn, "SELECT * FROM equipment WHERE id=$id AND company_id=$company_id LIMIT 1");
    if ($res && mysqli_num_rows($res) === 1) {
        $data = mysqli_fetch_assoc($res);
    } else {
        $error = 'Equipment record not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $v) {
        if (in_array($k, ['is_printer', 'printer_color_capable', 'is_workstation', 'active'], true)) {
            $data[$k] = isset($_POST[$k]) ? 1 : 0;
        } else {
            $data[$k] = trim($_POST[$k] ?? '');
        }
    }

    foreach (['equipment_type_id','manufacturer_id','location_id','rack_id','status_id','warranty_type_id','printer_device_type_id','workstation_device_type_id','workstation_os_type_id','switch_rj45_id','switch_fiber_id','switch_fiber_count_id','switch_poe_id','switch_environment_id'] as $fkField) {
        if (($data[$fkField] ?? '') === '__add_new__') {
            $data[$fkField] = '';
        }
    }

    if ($data['name'] === '' || (int)$data['equipment_type_id'] <= 0 || (int)$data['status_id'] <= 0 || (int)$data['warranty_type_id'] <= 0) {
        $error = 'Please fill required fields: Name, Type, Status, Warranty Type.';
    }

    $photoFilename = $data['photo_filename'];
    if (!$error && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed.';
        } elseif ($_FILES['photo']['size'] > MAX_FILE_SIZE) {
            $error = 'Photo exceeds max allowed size.';
        } else {
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
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
        $status_id = (int)$data['status_id'];
        $purchase_date = $data['purchase_date'] === '' ? 'NULL' : "'" . escape_sql($data['purchase_date'], $conn) . "'";
        $purchase_cost = $data['purchase_cost'] === '' ? 'NULL' : (float)$data['purchase_cost'];
        $warranty_expiry = $data['warranty_expiry'] === '' ? 'NULL' : "'" . escape_sql($data['warranty_expiry'], $conn) . "'";
        $warranty_type_id = (int)$data['warranty_type_id'];
        $is_printer = (int)$data['is_printer'];
        $printer_device_type_id = (int)$data['printer_device_type_id'] ?: 'NULL';
        $printer_color_capable = (int)$data['printer_color_capable'];
        $printer_print_speed_ppm = $data['printer_print_speed_ppm'] === '' ? 'NULL' : (int)$data['printer_print_speed_ppm'];
        $is_workstation = (int)$data['is_workstation'];
        $workstation_device_type_id = (int)$data['workstation_device_type_id'] ?: 'NULL';
        $workstation_os_type_id = (int)$data['workstation_os_type_id'] ?: 'NULL';
        $workstation_processor = $data['workstation_processor'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_processor'], $conn) . "'";
        $workstation_memory_gb = $data['workstation_memory_gb'] === '' ? 'NULL' : (int)$data['workstation_memory_gb'];
        $switch_rj45_id = (int)$data['switch_rj45_id'] ?: 'NULL';
        $switch_fiber_id = (int)$data['switch_fiber_id'] ?: 'NULL';
        $switch_fiber_count_id = (int)$data['switch_fiber_count_id'] ?: 'NULL';
        $switch_poe_id = (int)$data['switch_poe_id'] ?: 'NULL';
        $switch_environment_id = (int)$data['switch_environment_id'] ?: 'NULL';
        $notes = $data['notes'] === '' ? 'NULL' : "'" . escape_sql($data['notes'], $conn) . "'";
        $photo = $photoFilename === '' ? 'NULL' : "'" . escape_sql($photoFilename, $conn) . "'";
        $active = (int)$data['active'];

        if ($isEdit) {
            $sql = "UPDATE equipment SET equipment_type_id=$equipment_type_id, manufacturer_id=$manufacturer_id, location_id=$location_id, rack_id=$rack_id,
                    name=$name, serial_number=$serial_number, model=$model, hostname=$hostname, ip_address=$ip_address, mac_address=$mac_address,
                    status_id=$status_id, purchase_date=$purchase_date, purchase_cost=$purchase_cost, warranty_expiry=$warranty_expiry,
                    warranty_type_id=$warranty_type_id, is_printer=$is_printer, printer_device_type_id=$printer_device_type_id,
                    printer_color_capable=$printer_color_capable, printer_print_speed_ppm=$printer_print_speed_ppm,
                    is_workstation=$is_workstation, workstation_device_type_id=$workstation_device_type_id, workstation_os_type_id=$workstation_os_type_id,
                    workstation_processor=$workstation_processor, workstation_memory_gb=$workstation_memory_gb,
                    switch_rj45_id=$switch_rj45_id, switch_fiber_id=$switch_fiber_id, switch_fiber_count_id=$switch_fiber_count_id, switch_poe_id=$switch_poe_id, switch_environment_id=$switch_environment_id,
                    notes=$notes,
                    photo_filename=$photo, active=$active
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO equipment (company_id, equipment_type_id, manufacturer_id, location_id, rack_id, name, serial_number, model, hostname,
                    ip_address, mac_address, status_id, purchase_date, purchase_cost, warranty_expiry, warranty_type_id, is_printer,
                    printer_device_type_id, printer_color_capable, printer_print_speed_ppm, is_workstation, workstation_device_type_id,
                    workstation_os_type_id, workstation_processor, workstation_memory_gb, switch_rj45_id, switch_fiber_id, switch_fiber_count_id, switch_poe_id, switch_environment_id, notes, photo_filename, active)
                    VALUES ($company_id, $equipment_type_id, $manufacturer_id, $location_id, $rack_id, $name, $serial_number, $model, $hostname,
                    $ip_address, $mac_address, $status_id, $purchase_date, $purchase_cost, $warranty_expiry, $warranty_type_id, $is_printer,
                    $printer_device_type_id, $printer_color_capable, $printer_print_speed_ppm, $is_workstation, $workstation_device_type_id,
                    $workstation_os_type_id, $workstation_processor, $workstation_memory_gb, $switch_rj45_id, $switch_fiber_id, $switch_fiber_count_id, $switch_poe_id, $switch_environment_id, $notes, $photo, $active)";
        }

        if (mysqli_query($conn, $sql)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Database error: ' . mysqli_error($conn);
    }
}

function render_options($items, $selected = '') {
    foreach ($items as $i) {
        $sel = ((string)$selected === (string)$i['id']) ? 'selected' : '';
        echo '<option value="' . (int)$i['id'] . '" ' . $sel . '>' . sanitize($i['label']) . '</option>';
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
<div class="container"><?php include '../../includes/sidebar.php'; ?><div class="main-content"><?php include '../../includes/header.php'; ?><div class="content">
    <h1><?php echo $isEdit ? '✏️ Edit' : '➕ New'; ?> Equipment</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group"><label>Name *</label><input required name="name" value="<?php echo sanitize($data['name']); ?>"></div>
                <div class="form-group"><label>Type *</label><select name="equipment_type_id" required data-addable-select="1" data-add-table="equipment_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="equipment type"><option value="">-- Select --</option><?php render_options($types, $data['equipment_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Manufacturer</label><select name="manufacturer_id" data-addable-select="1" data-add-table="manufacturers" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="manufacturer"><option value="">-- None --</option><?php render_options($manufacturers, $data['manufacturer_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Location</label><select name="location_id" data-addable-select="1" data-add-table="it_locations" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="location"><option value="">-- None --</option><?php render_options($locations, $data['location_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Rack</label><select name="rack_id" data-addable-select="1" data-add-table="racks" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rack"><option value="">-- None --</option><?php render_options($racks, $data['rack_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Status *</label><select name="status_id" required data-addable-select="1" data-add-table="equipment_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="status"><option value="">-- Select --</option><?php render_options($statuses, $data['status_id']); ?><option value="__add_new__">➕</option></select></div>
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
                <div class="form-group"><label>Warranty Type *</label><select name="warranty_type_id" required data-addable-select="1" data-add-table="warranty_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="warranty type"><option value="">-- Select --</option><?php render_options($warrantyTypes, $data['warranty_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?php echo sanitize($data['purchase_date']); ?>"></div>
                <div class="form-group"><label>Purchase Cost</label><input type="number" step="0.01" name="purchase_cost" value="<?php echo sanitize($data['purchase_cost']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Warranty Expiry</label><input type="date" name="warranty_expiry" value="<?php echo sanitize($data['warranty_expiry']); ?>"></div>
                <div class="form-group"><label>Photo Upload</label><input type="file" name="photo" accept="image/*"><?php if (!empty($data['photo_filename'])): ?><div class="form-hint">Current: <?php echo sanitize($data['photo_filename']); ?></div><?php endif; ?></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label><input type="checkbox" name="is_printer" <?php echo (int)$data['is_printer'] === 1 ? 'checked' : ''; ?>> Is Printer</label></div>
                <div class="form-group"><label><input type="checkbox" name="is_workstation" <?php echo (int)$data['is_workstation'] === 1 ? 'checked' : ''; ?>> Is Workstation</label></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Printer Type</label><select name="printer_device_type_id" data-addable-select="1" data-add-table="printer_device_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="printer type"><option value="">-- None --</option><?php render_options($printerTypes, $data['printer_device_type_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Printer Speed (PPM)</label><input type="number" name="printer_print_speed_ppm" value="<?php echo sanitize($data['printer_print_speed_ppm']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label><input type="checkbox" name="printer_color_capable" <?php echo (int)$data['printer_color_capable'] === 1 ? 'checked' : ''; ?>> Color Capable Printer</label></div>
                <div class="form-group"><label>Workstation Processor</label><input name="workstation_processor" value="<?php echo sanitize($data['workstation_processor']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation Device Type</label><select name="workstation_device_type_id" data-addable-select="1" data-add-table="workstation_device_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="workstation device type"><option value="">-- None --</option><?php render_options($workstationDeviceTypes, $data['workstation_device_type_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Workstation OS Type</label><select name="workstation_os_type_id" data-addable-select="1" data-add-table="workstation_os_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="workstation os type"><option value="">-- None --</option><?php render_options($workstationOsTypes, $data['workstation_os_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Workstation Memory (GB)</label><input type="number" name="workstation_memory_gb" value="<?php echo sanitize($data['workstation_memory_gb']); ?>"></div>
                <div class="form-group"><label><input type="checkbox" name="active" <?php echo (int)$data['active'] === 1 ? 'checked' : ''; ?>> Active</label></div>
            </div>
            <div id="switch-fields" style="display:none;">
                <h3 style="margin-top:20px;">Switch Details</h3>
                <div class="form-row">
                    <div class="form-group"><label>RJ45 Ports</label><select name="switch_rj45_id"><option value="">-- None --</option><?php render_options($switchRj45Options, $data['switch_rj45_id']); ?></select></div>
                    <div class="form-group"><label>Fiber Ports</label><select name="switch_fiber_id"><option value="">-- None --</option><?php render_options($switchFiberOptions, $data['switch_fiber_id']); ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Fiber Count</label><select name="switch_fiber_count_id"><option value="">-- None --</option><?php render_options($switchFiberCountOptions, $data['switch_fiber_count_id']); ?></select></div>
                    <div class="form-group"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>PoE Type</label><select name="switch_poe_id"><option value="">-- None --</option><?php render_options($switchPoeOptions, $data['switch_poe_id']); ?></select></div>
                    <div class="form-group"><label>Management</label><select name="switch_environment_id"><option value="">-- None --</option><?php render_options($switchEnvironmentOptions, $data['switch_environment_id']); ?></select></div>
                </div>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" rows="5"><?php echo sanitize($data['notes']); ?></textarea></div>
            <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">✖️</a></div>
        </form>
    </div>
</div></div></div>
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script>
(function () {
    var typeSelect = document.querySelector('select[name="equipment_type_id"]');
    var switchFields = document.getElementById('switch-fields');
    var switchTypeId = '<?php echo (int)$switchTypeId; ?>';

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

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleSwitchFields);
        toggleSwitchFields();
    }
})();
</script>
</body>
</html>
