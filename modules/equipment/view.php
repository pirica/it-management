<?php
require '../../config/config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function equipment_view_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res && mysqli_num_rows($res) > 0;
}

$hasWorkstationOfficeIdColumn = equipment_view_table_has_column($conn, 'equipment', 'workstation_office_id');
$hasWorkstationOsVersionIdColumn = equipment_view_table_has_column($conn, 'equipment', 'workstation_os_version_id');
$hasWorkstationRamIdColumn = equipment_view_table_has_column($conn, 'equipment', 'workstation_ram_id');
$workstationOfficeSelect = $hasWorkstationOfficeIdColumn ? ', wo.name workstation_office_name' : '';
$workstationOfficeJoin = $hasWorkstationOfficeIdColumn
    ? ' LEFT JOIN workstation_office wo ON wo.id = e.workstation_office_id AND wo.company_id = e.company_id'
    : '';
$workstationOsVersionSelect = $hasWorkstationOsVersionIdColumn ? ', wov.name workstation_os_version_name' : '';
$workstationOsVersionJoin = $hasWorkstationOsVersionIdColumn
    ? ' LEFT JOIN workstation_os_versions wov ON wov.id = e.workstation_os_version_id AND wov.company_id = e.company_id'
    : '';
$workstationRamSelect = $hasWorkstationRamIdColumn ? ', wr.name workstation_ram_name' : '';
$workstationRamJoin = $hasWorkstationRamIdColumn
    ? ' LEFT JOIN workstation_ram wr ON wr.id = e.workstation_ram_id AND wr.company_id = e.company_id'
    : '';

$sql = "SELECT e.*, c.company company_name, et.name equipment_type_name, m.name manufacturer_name, l.name location_name,
               r.name rack_name, es.name status_name, wt.name warranty_type_name,
               pdt.name printer_device_type_name, wdt.name workstation_device_type_name, wot.name workstation_os_type_name$workstationOfficeSelect$workstationOsVersionSelect$workstationRamSelect
        FROM equipment e
        LEFT JOIN companies c ON c.id = e.company_id
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id AND et.company_id = e.company_id
        LEFT JOIN manufacturers m ON m.id = e.manufacturer_id AND m.company_id = e.company_id
        LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
        LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
        LEFT JOIN equipment_statuses es ON es.id = e.status_id AND es.company_id = e.company_id
        LEFT JOIN warranty_types wt ON wt.id = e.warranty_type_id AND wt.company_id = e.company_id
        LEFT JOIN printer_device_types pdt ON pdt.id = e.printer_device_type_id AND pdt.company_id = e.company_id
        LEFT JOIN workstation_device_types wdt ON wdt.id = e.workstation_device_type_id AND wdt.company_id = e.company_id
        LEFT JOIN workstation_os_types wot ON wot.id = e.workstation_os_type_id AND wot.company_id = e.company_id
        $workstationOfficeJoin
        $workstationOsVersionJoin
        $workstationRamJoin
        WHERE e.id = $id AND e.company_id = $company_id LIMIT 1";
$res = mysqli_query($conn, $sql);
$item = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
$equipmentViewBackPath = (string)($equipmentViewBackPath ?? 'index.php');
$equipmentViewEditPath = (string)($equipmentViewEditPath ?? 'edit.php');
$equipmentRequiredFlagField = (string)($equipmentRequiredFlagField ?? '');

if ($item && $equipmentRequiredFlagField !== '') {
    $allowedFlags = [
        'is_workstation' => "workstation",
        'is_server' => "server",
        'is_switch' => "switch",
        'is_printer' => "printer",
        'is_pos' => "pos",
    ];
    if (array_key_exists($equipmentRequiredFlagField, $allowedFlags)) {
        $hasFlag = (int)($item[$equipmentRequiredFlagField] ?? 0) === 1;
        $typeName = strtolower(trim((string)($item['equipment_type_name'] ?? '')));
        $typeMatch = false;
        if ($equipmentRequiredFlagField === 'is_switch') {
            $typeMatch = str_contains($typeName, 'switch');
        } elseif ($equipmentRequiredFlagField === 'is_pos') {
            $typeMatch = in_array($typeName, ['pos', 'point of sale', 'point-of-sale'], true);
        } else {
            $typeMatch = $typeName === $allowedFlags[$equipmentRequiredFlagField];
        }
        if (!$hasFlag && !$typeMatch) {
            $item = null;
        }
    }
}

function equipment_parse_photo_filenames($rawValue): array
{
    if ($rawValue === null) {
        return [];
    }

    $value = trim((string)$rawValue);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $items = $decoded;
    } elseif (str_contains($value, ',')) {
        $items = explode(',', $value);
    } else {
        $items = [$value];
    }

    $filenames = [];
    foreach ($items as $item) {
        $filename = basename((string)$item);
        if ($filename !== '') {
            $filenames[$filename] = $filename;
        }
    }

    return array_values($filenames);
}

function equipment_field_label($key) {
    $labels = [
        'is_printer' => 'Is Printer',
        'printer_device_type_name' => 'Printer Type',
        'printer_color_capable' => 'Printer Color Capable',
        'printer_scan' => 'Printer Scan',
        'is_workstation' => 'Is Workstation',
        'is_server' => 'Is Server',
        'is_pos' => 'Is POS',
        'is_switch' => 'Is Switch',
        'workstation_office_name' => 'Workstation Office',
        'workstation_os_version_name' => 'Workstation OS Version',
        'workstation_ram_name' => 'RAM',
        'workstation_storage' => 'Storage (GB/TB)',
        'workstation_os_installed_on' => 'Workstation OS Installed On',
        'switch_fiber_port_label' => 'Fiber Port Label',
        'notes' => 'Comments',
    ];

    return $labels[$key] ?? ucwords(str_replace('_', ' ', (string)$key));
}

function equipment_field_value($key, $value) {
    if (in_array($key, ['is_printer', 'printer_scan', 'is_workstation', 'is_server', 'is_pos', 'is_switch', 'active'], true)) {
        return (int)$value === 1 ? 'Yes' : 'No';
    }

    return (string)$value;
}

function equipment_field_is_populated($key, $value) {
    if ($value === null) {
        return false;
    }

    if (in_array($key, ['is_printer', 'printer_scan', 'is_workstation', 'is_server', 'is_pos', 'is_switch', 'active'], true)) {
        return (int)$value === 1;
    }

    if (is_string($value)) {
        return trim($value) !== '';
    }

    return true;
}

function equipment_field_should_display($key) {
    if ($key === 'id') {
        return true;
    }
    if ($key === 'photo_filename') {
        return false;
    }

    return !preg_match('/_id$/', (string)$key);
}

function equipment_field_matches_context($key, $item) {
    if (!in_array($key, ['printer_color_capable', 'printer_scan', 'printer_device_type_name'], true)) {
        return true;
    }

    $equipmentTypeName = strtolower(trim((string)($item['equipment_type_name'] ?? '')));
    $isPrinter = (int)($item['is_printer'] ?? 0) === 1;

    return $equipmentTypeName === 'printer' || $isPrinter;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>View Equipment</title><link rel="stylesheet" href="../../css/styles.css"></head>
<body><div class="container"><?php include '../../includes/sidebar.php'; ?><div class="main-content"><?php include '../../includes/header.php'; ?><div class="content">
<h1>View Equipment</h1>
<?php if (!$item): ?>
<div class="alert alert-danger">Equipment not found.</div>
<?php else: ?>
<div class="card">
<table><tbody>
<?php foreach ($item as $k => $v): ?>
    <?php if (!equipment_field_should_display($k)) { continue; } ?>
    <?php if (!equipment_field_matches_context($k, $item)) { continue; } ?>
    <?php if (!equipment_field_is_populated($k, $v)) { continue; } ?>
    <tr>
        <th style="width:240px;"><?php echo sanitize(equipment_field_label($k)); ?></th>
        <td><?php echo sanitize(equipment_field_value($k, $v)); ?></td>
    </tr>
<?php endforeach; ?>
<?php $photoFilenames = equipment_parse_photo_filenames($item['photo_filename'] ?? ''); ?>
<tr>
    <th style="width:240px;">Photos</th>
    <td>
        <?php if (empty($photoFilenames)): ?>
            <span>—</span>
        <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($photoFilenames as $photoFilename): ?>
                    <?php $photoUrl = UPLOAD_URL . sanitize($photoFilename); ?>
                    <a href="<?php echo $photoUrl; ?>" target="_blank" rel="noopener noreferrer" title="Open full-size image in a new tab">
                        <img
                            src="<?php echo $photoUrl; ?>"
                            alt="Ticket photo"
                            style="width:96px;height:96px;object-fit:cover;border:1px solid #d0d7de;border-radius:6px;"
                        >
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </td>
</tr>
</tbody></table>
<p style="margin-top:16px;"><a class="btn" href="<?php echo sanitize($equipmentViewBackPath); ?>">Back</a> <a class="btn btn-primary" href="<?php echo sanitize($equipmentViewEditPath); ?>?id=<?php echo (int)$item['id']; ?>">✏️</a></p>
</div>
<?php endif; ?>
</div></div></div><script src="../../js/theme.js"></script></body></html>
