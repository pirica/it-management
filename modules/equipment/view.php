<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit();
}

$query = "SELECT e.*, et.name as type_name, m.name as manufacturer_name, l.name as location_name
          FROM equipment e 
          LEFT JOIN equipment_types et ON e.equipment_type_id = et.id 
          LEFT JOIN manufacturers m ON e.manufacturer_id = m.id 
          LEFT JOIN it_locations l ON e.location_id = l.id 
          WHERE e.id = $id AND e.company_id = $company_id";

$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    die("Equipment not found");
}

$equipment = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($equipment['name']); ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .detail-row { display: flex; gap: 30px; margin-bottom: 20px; }
        .detail-col { flex: 1; }
        .detail-label { font-weight: 600; color: var(--text-secondary); font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }
        .detail-value { font-size: 16px; color: var(--text-primary); }
        .photo-container { text-align: center; margin-bottom: 20px; }
        .photo-container img { max-width: 300px; border-radius: 8px; box-shadow: var(--shadow-lg); }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            
            <div class="content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1><?php echo sanitize($equipment['name']); ?></h1>
                    <div>
                        <a href="edit.php?id=<?php echo $equipment['id']; ?>" class="btn btn-primary">✏️ Edit</a>
                        <a href="delete.php?id=<?php echo $equipment['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete?')">🗑️ Delete</a>
                        <a href="index.php" class="btn">← Back</a>
                    </div>
                </div>

                <div class="card">
                    <?php if ($equipment['photo_filename']): ?>
                        <div class="photo-container">
                            <img src="../../equipment/<?php echo htmlspecialchars($equipment['photo_filename']); ?>" alt="Equipment Photo">
                        </div>
                    <?php endif; ?>

                    <h3>Basic Information</h3>
                    <div class="detail-row">
                        <div class="detail-col">
                            <div class="detail-label">Name</div>
                            <div class="detail-value"><?php echo sanitize($equipment['name']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="badge badge-<?php echo $equipment['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo sanitize($equipment['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <h3>Equipment Details</h3>
                    <div class="detail-row">
                        <div class="detail-col">
                            <div class="detail-label">Type</div>
                            <div class="detail-value"><?php echo sanitize($equipment['type_name']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">Manufacturer</div>
                            <div class="detail-value"><?php echo sanitize($equipment['manufacturer_name']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">Model</div>
                            <div class="detail-value"><?php echo sanitize($equipment['model']); ?></div>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-col">
                            <div class="detail-label">Serial Number</div>
                            <div class="detail-value"><?php echo sanitize($equipment['serial_number']); ?></div>
                        </div>
                    </div>

                    <h3>Network Information</h3>
                    <div class="detail-row">
                        <div class="detail-col">
                            <div class="detail-label">Hostname</div>
                            <div class="detail-value"><?php echo sanitize($equipment['hostname']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">IP Address</div>
                            <div class="detail-value"><?php echo sanitize($equipment['ip_address']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">MAC Address</div>
                            <div class="detail-value"><?php echo sanitize($equipment['mac_address']); ?></div>
                        </div>
                    </div>

                    <h3>Location & Warranty</h3>
                    <div class="detail-row">
                        <div class="detail-col">
                            <div class="detail-label">Location</div>
                            <div class="detail-value"><?php echo sanitize($equipment['location_name']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">Warranty Type</div>
                            <div class="detail-value"><?php echo sanitize($equipment['warranty_type']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">Warranty Expiry</div>
                            <div class="detail-value"><?php echo format_date($equipment['warranty_expiry']); ?></div>
                        </div>
                    </div>

                    <h3>Financial Information</h3>
                    <div class="detail-row">
                        <div class="detail-col">
                            <div class="detail-label">Purchase Date</div>
                            <div class="detail-value"><?php echo format_date($equipment['purchase_date']); ?></div>
                        </div>
                        <div class="detail-col">
                            <div class="detail-label">Purchase Cost</div>
                            <div class="detail-value"><?php echo format_currency($equipment['purchase_cost']); ?></div>
                        </div>
                    </div>

                    <?php if ($equipment['notes']): ?>
                        <h3>Notes</h3>
                        <p><?php echo nl2br(sanitize($equipment['notes'])); ?></p>
                    <?php endif; ?>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); color: var(--text-tertiary); font-size: 12px;">
                        Created: <?php echo date('M d, Y H:i', strtotime($equipment['created_at'])); ?> | 
                        Updated: <?php echo date('M d, Y H:i', strtotime($equipment['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/theme.js"></script>
</body>
</html>