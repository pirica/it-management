<?php
require '../../config/config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT e.*, c.name company_name, et.name equipment_type_name, m.name manufacturer_name, l.name location_name,
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
        WHERE e.id = $id AND e.company_id = $company_id LIMIT 1";
$res = mysqli_query($conn, $sql);
$item = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
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
<?php foreach ($item as $k => $v): ?><tr><th style="width:240px;"><?php echo sanitize($k); ?></th><td><?php echo sanitize((string)$v); ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php if (!empty($item['photo_filename'])): ?><p style="margin-top:16px;"><img src="<?php echo UPLOAD_URL . sanitize($item['photo_filename']); ?>" alt="Equipment Photo" style="max-width:300px;border:1px solid var(--border);border-radius:8px;"></p><?php endif; ?>
<p style="margin-top:16px;"><a class="btn" href="index.php">Back</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)$item['id']; ?>">✏️</a></p>
</div>
<?php endif; ?>
</div></div></div><script src="../../js/theme.js"></script></body></html>
