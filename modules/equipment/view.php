<?php
require '../../config/config.php';

$viewTitle = $equipmentViewTitle ?? 'View Equipment';
$requiredFlagField = $equipmentRequiredFlagField ?? null;
$backPath = $equipmentViewBackPath ?? 'index.php';
$editPath = $equipmentViewEditPath ?? 'edit.php';

if ($requiredFlagField !== null && !preg_match('/^is_[a-z0-9_]+$/', $requiredFlagField)) {
    die('Invalid equipment view filter configuration');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flagSql = '';
if ($requiredFlagField !== null) {
    $flagSql = ' AND e.`' . $requiredFlagField . '` = 1';
}

$sql = "SELECT e.*, c.company company_name, et.name equipment_type_name, m.name manufacturer_name, l.name location_name,
               r.name rack_name, es.name status_name, wt.name warranty_type_name,
               pdt.name printer_device_type_name, wdt.name workstation_device_type_name, wot.name workstation_os_type_name
        FROM equipment e
        LEFT JOIN companies c ON c.id = e.company_id
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
        LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
        LEFT JOIN it_locations l ON l.id = e.location_id
        LEFT JOIN racks r ON r.id = e.rack_id
        LEFT JOIN equipment_statuses es ON es.id = e.status_id
        LEFT JOIN warranty_types wt ON wt.id = e.warranty_type_id
        LEFT JOIN printer_device_types pdt ON pdt.id = e.printer_device_type_id
        LEFT JOIN workstation_device_types wdt ON wdt.id = e.workstation_device_type_id
        LEFT JOIN workstation_os_types wot ON wot.id = e.workstation_os_type_id
        WHERE e.id = $id AND e.company_id = $company_id {$flagSql} LIMIT 1";
$res = mysqli_query($conn, $sql);
$item = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;

function equipment_field_label($key) {
    $labels = [
        'company_name' => 'Company',
        'equipment_type_name' => 'Equipment Type',
        'manufacturer_name' => 'Manufacturer',
        'location_name' => 'Location',
        'rack_name' => 'Rack',
        'status_name' => 'Status',
        'warranty_type_name' => 'Warranty Type',
        'printer_device_type_name' => 'Printer Device Type',
        'workstation_device_type_name' => 'Workstation Device Type',
        'workstation_os_type_name' => 'Workstation OS Type',
        'is_printer' => 'Is Printer',
        'is_workstation' => 'Is Workstation',
        'is_server' => 'Is Server',
        'is_pos' => 'Is POS',
        'is_switch' => 'Is Switch',
        'notes' => 'Comments',
    ];

    return $labels[$key] ?? ucwords(str_replace('_', ' ', (string)$key));
}

function equipment_field_value($key, $value) {
    if (in_array($key, ['is_printer', 'is_workstation', 'is_server', 'is_pos', 'is_switch', 'active'], true)) {
        return (int)$value === 1 ? 'Yes' : 'No';
    }

    return (string)$value;
}

function equipment_show_field($key, $value) {
    $hiddenFields = [
        'id', 'company_id', 'equipment_type_id', 'manufacturer_id', 'location_id', 'rack_id', 'status_id',
        'warranty_type_id', 'printer_device_type_id', 'workstation_device_type_id', 'workstation_os_type_id',
        'switch_rj45_id', 'switch_port_numbering_layout_id', 'switch_fiber_id', 'switch_fiber_count_id',
        'switch_poe_id', 'switch_environment_id',
    ];

    if (in_array($key, $hiddenFields, true)) {
        return false;
    }

    if (in_array($key, ['created_at', 'updated_at'], true)) {
        return false;
    }

    if (in_array($key, ['is_printer', 'is_workstation', 'is_server', 'is_pos', 'is_switch'], true)) {
        return (int)$value === 1;
    }

    if ($value === null) {
        return false;
    }

    $text = trim((string)$value);
    if ($text === '' || $text === '0000-00-00' || $text === '0000-00-00 00:00:00') {
        return false;
    }

    return true;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo sanitize($viewTitle); ?></title><link rel="stylesheet" href="../../css/styles.css"></head>
<body><div class="container"><?php include '../../includes/sidebar.php'; ?><div class="main-content"><?php include '../../includes/header.php'; ?><div class="content">
<h1><?php echo sanitize($viewTitle); ?></h1>
<?php if (!$item): ?>
<div class="alert alert-danger">Equipment not found.</div>
<?php else: ?>
<div class="card">
<table><tbody>
<?php foreach ($item as $k => $v): ?>
    <?php if (!equipment_show_field($k, $v)) { continue; } ?>
    <tr>
        <th style="width:240px;"><?php echo sanitize(equipment_field_label($k)); ?></th>
        <td><?php echo sanitize(equipment_field_value($k, $v)); ?></td>
    </tr>
<?php endforeach; ?>
</tbody></table>
<?php if (!empty($item['photo_filename'])): ?><p style="margin-top:16px;"><img src="<?php echo UPLOAD_URL . sanitize($item['photo_filename']); ?>" alt="Equipment Photo" style="max-width:300px;border:1px solid var(--border);border-radius:8px;"></p><?php endif; ?>
<p style="margin-top:16px;"><a class="btn" href="<?php echo sanitize($backPath); ?>">Back</a> <a class="btn btn-primary" href="<?php echo sanitize($editPath); ?>?id=<?php echo (int)$item['id']; ?>">✏️</a></p>
</div>
<?php endif; ?>
</div></div></div><script src="../../js/theme.js"></script></body></html>
