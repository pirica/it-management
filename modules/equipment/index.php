<?php
require '../../config/config.php';

// Get all equipment for this company
$query = "SELECT e.*, et.name as type_name, m.name as manufacturer_name 
          FROM equipment e 
          LEFT JOIN equipment_types et ON e.equipment_type_id = et.id 
          LEFT JOIN manufacturers m ON e.manufacturer_id = m.id 
          WHERE e.company_id = $company_id 
          ORDER BY e.created_at DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$equipment_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .action-btns { display: flex; gap: 5px; }
        .action-btns a { padding: 4px 8px; font-size: 11px; }
        img { max-width: 50px; height: auto; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            
            <div class="content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1>🖥️ Equipment Management</h1>
                    <a href="create.php" class="btn btn-primary">+ New Equipment</a>
                </div>

                <div class="card">
                    <?php if (count($equipment_list) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Manufacturer</th>
                                    <th>Model</th>
                                    <th>Serial#</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_list as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['photo_filename']): ?>
                                                <img src="../../equipment/<?php echo htmlspecialchars($item['photo_filename']); ?>" alt="Photo">
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo sanitize($item['name']); ?></strong></td>
                                        <td><?php echo sanitize($item['type_name']); ?></td>
                                        <td><?php echo sanitize($item['manufacturer_name']); ?></td>
                                        <td><?php echo sanitize($item['model']); ?></td>
                                        <td><?php echo sanitize($item['serial_number']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $item['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                                <?php echo sanitize($item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm">View</a>
                                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm">Edit</a>
                                                <a href="delete.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this equipment?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px;">
                            <p style="color: var(--text-secondary);">📭 No equipment found</p>
                            <a href="create.php" class="btn btn-primary" style="margin-top: 20px;">New First Equipment</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/theme.js"></script>
</body>
</html>