<?php
require '../../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape_sql($_POST['name'], $conn);
    $asset_tag = escape_sql($_POST['asset_tag'], $conn);
    $asset_code = escape_sql($_POST['asset_code'] ?? '', $conn);
    $serial_number = escape_sql($_POST['serial_number'] ?? '', $conn);
    $equipment_type_id = (int)($_POST['equipment_type_id'] ?? 0);
    $manufacturer_id = (int)($_POST['manufacturer_id'] ?? 0) ?: 'NULL';
    $model = escape_sql($_POST['model'] ?? '', $conn);
    $hostname = escape_sql($_POST['hostname'] ?? '', $conn);
    $ip_address = escape_sql($_POST['ip_address'] ?? '', $conn);
    $mac_address = escape_sql($_POST['mac_address'] ?? '', $conn);
    $status = escape_sql($_POST['status'] ?? 'Active', $conn);
    $location_id = (int)($_POST['location_id'] ?? 0) ?: 'NULL';
    $purchase_date = $_POST['purchase_date'] ?? 'NULL';
    $purchase_cost = (float)($_POST['purchase_cost'] ?? 0);
    $warranty_expiry = $_POST['warranty_expiry'] ?? 'NULL';
    $warranty_type = escape_sql($_POST['warranty_type'] ?? 'Standard', $conn);
    $notes = escape_sql($_POST['notes'] ?? '', $conn);
    $photo_filename = '';

    // Validate required fields
    if (!$name || !$asset_tag || !$equipment_type_id) {
        $error = '❌ Please fill in all required fields (Name, Asset Tag, Equipment Type)';
    } else {
        // Handle file upload
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $upload_path = '../../equipment/' . $filename;
            
            if ($file['size'] > 5242880) { // 5MB
                $error = '❌ File too large (max 5MB)';
            } elseif (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
                $error = '❌ Invalid file type (allowed: JPG, PNG, GIF)';
            } elseif (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $photo_filename = $filename;
            }
        }

        if (!$error) {
            $purchase_date_sql = $purchase_date === 'NULL' ? 'NULL' : "'" . $purchase_date . "'";
            $warranty_expiry_sql = $warranty_expiry === 'NULL' ? 'NULL' : "'" . $warranty_expiry . "'";
            
            $query = "INSERT INTO equipment 
            (company_id, equipment_type_id, manufacturer_id, location_id, name, asset_tag, asset_code, 
             serial_number, model, hostname, ip_address, mac_address, status, purchase_date, purchase_cost, 
             warranty_expiry, warranty_type, notes, photo_filename) 
            VALUES 
            ($company_id, $equipment_type_id, $manufacturer_id, $location_id, '$name', '$asset_tag', '$asset_code', 
             '$serial_number', '$model', '$hostname', '$ip_address', '$mac_address', '$status', $purchase_date_sql, $purchase_cost, 
             $warranty_expiry_sql, '$warranty_type', '$notes', '$photo_filename')";

            if (mysqli_query($conn, $query)) {
                $success = '✅ Equipment created successfully!';
                header('refresh:2;url=index.php');
            } else {
                $error = '❌ Database error: ' . mysqli_error($conn);
            }
        }
    }
}

// Get reference data
$types = mysqli_query($conn, "SELECT * FROM equipment_types WHERE active = 1 ORDER BY name");
$manufacturers = mysqli_query($conn, "SELECT * FROM manufacturers WHERE active = 1 ORDER BY name");
$locations = mysqli_query($conn, "SELECT * FROM it_locations WHERE company_id = $company_id AND active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Equipment - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            
            <div class="content">
                <h1>➕ Add New Equipment</h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST" enctype="multipart/form-data">
                        <h3>Basic Information *</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Equipment Name *</label>
                                <input type="text" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Asset Tag *</label>
                                <input type="text" name="asset_tag" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Equipment Type *</label>
                                <select name="equipment_type_id" required>
                                    <option value="">-- Select Type --</option>
                                    <?php while ($type = mysqli_fetch_assoc($types)): ?>
                                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <select name="manufacturer_id">
                                    <option value="">-- Select Manufacturer --</option>
                                    <?php while ($mfg = mysqli_fetch_assoc($manufacturers)): ?>
                                        <option value="<?php echo $mfg['id']; ?>"><?php echo htmlspecialchars($mfg['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <h3>Technical Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Model</label>
                                <input type="text" name="model">
                            </div>
                            <div class="form-group">
                                <label>Serial Number</label>
                                <input type="text" name="serial_number">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Asset Code</label>
                                <input type="text" name="asset_code">
                            </div>
                            <div class="form-group">
                                <label>Hostname</label>
                                <input type="text" name="hostname">
                            </div>
                        </div>

                        <h3>Network Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>IP Address</label>
                                <input type="text" name="ip_address" placeholder="192.168.1.1">
                            </div>
                            <div class="form-group">
                                <label>MAC Address</label>
                                <input type="text" name="mac_address" placeholder="00:1A:2B:3C:4D:5E">
                            </div>
                        </div>

                        <h3>Location & Status</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Location</label>
                                <select name="location_id">
                                    <option value="">-- Select Location --</option>
                                    <?php while ($loc = mysqli_fetch_assoc($locations)): ?>
                                        <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option>Active</option>
                                    <option>Inactive</option>
                                    <option>Maintenance</option>
                                    <option>Faulty</option>
                                    <option>Reserved</option>
                                    <option>Decommissioned</option>
                                </select>
                            </div>
                        </div>

                        <h3>Purchase & Warranty</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Purchase Date</label>
                                <input type="date" name="purchase_date">
                            </div>
                            <div class="form-group">
                                <label>Purchase Cost ($)</label>
                                <input type="number" name="purchase_cost" step="0.01" min="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Warranty Expiry</label>
                                <input type="date" name="warranty_expiry">
                            </div>
                            <div class="form-group">
                                <label>Warranty Type</label>
                                <select name="warranty_type">
                                    <option>Standard</option>
                                    <option>Extended</option>
                                    <option>Premium</option>
                                    <option>Enterprise</option>
                                    <option>None</option>
                                </select>
                            </div>
                        </div>

                        <h3>Documentation</h3>
                        <div class="form-group">
                            <label>Equipment Photo</label>
                            <input type="file" name="photo" accept="image/*">
                            <div class="form-hint">Max 5MB, formats: JPG, PNG, GIF</div>
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" placeholder="Any additional information..."></textarea>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">✅ Save Equipment</button>
                            <a href="index.php" class="btn">❌ Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/theme.js"></script>
</body>
</html>