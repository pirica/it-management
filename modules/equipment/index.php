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
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
