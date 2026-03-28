<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit();
}

$equipment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM equipment WHERE id = $id AND company_id = $company_id"));

if (!$equipment) {
    die("Equipment not found");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape_sql($_POST['name'], $conn);
    $asset_tag = escape_sql($_POST['asset_tag'], $conn);
    $serial_number = escape_sql($_POST['serial_number'] ?? '', $conn);
    $model = escape_sql($_POST['model'] ?? '', $conn);
    $status = escape_sql($_POST['status'] ?? 'Active', $conn);
    $purchase_date = $_POST['purchase_date'] ?? 'NULL';
    $purchase_cost = (float)($_POST['purchase_cost'] ?? 0);
    $warranty_type = escape_sql($_POST['warranty_type'] ?? 'Standard', $conn);
    $photo_filename = $equipment['photo_filename'];

    if (!$name || !$asset_tag) {
        $error = '❌ Name and Asset Tag are required';
    } else {
        // Handle photo upload
        if (!empty($_FILES['photo']['name'])) {
            if ($equipment['photo_filename'] && file_exists('../../equipment/' . $equipment['photo_filename'])) {
                unlink('../../equipment/' . $equipment['photo_filename']);
            }
            
            $file = $_FILES['photo'];
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $upload_path = '../../equipment/' . $filename;
            
            if ($file['size'] > 5242880) {
                $error = '❌ File too large (max 5MB)';
            } elseif (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
                $error = '❌ Invalid file type';
            } elseif (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $photo_filename = $filename;
            }
        }

        if (!$error) {
            $purchase_date_sql = $purchase_date === 'NULL' ? 'NULL' : "'" . $purchase_date . "'";
            
            $query = "UPDATE equipment SET 
                name = '$name',
                asset_tag = '$asset_tag',
                serial_number = '$serial_number',
                model = '$model',
                status = '$status',
                purchase_date = $purchase_date_sql,
                purchase_cost = $purchase_cost,
                warranty_type = '$warranty_type',
                photo_filename = '$photo_filename'
            WHERE id = $id";

            if (mysqli_query($conn, $query)) {
                $success = '✅ Equipment updated successfully!';
                header('refresh:2;url=view.php?id=' . $id);
            } else {
                $error = '❌ Database error: ' . mysqli_error($conn);
            }
        }
    }
}

$types = mysqli_query($conn, "SELECT * FROM equipment_types WHERE active = 1");
$manufacturers = mysqli_query($conn, "SELECT * FROM manufacturers WHERE active = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Equipment - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            
            <div class="content">
                <h1>✏️ Edit Equipment: <?php echo sanitize($equipment['name']); ?></h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" value="<?php echo sanitize($equipment['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Asset Tag *</label>
                                <input type="text" name="asset_tag" value="<?php echo sanitize($equipment['asset_tag']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Model</label>
                                <input type="text" name="model" value="<?php echo sanitize($equipment['model']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Serial Number</label>
                                <input type="text" name="serial_number" value="<?php echo sanitize($equipment['serial_number']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="Active" <?php echo $equipment['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $equipment['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Maintenance" <?php echo $equipment['status'] === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="Faulty" <?php echo $equipment['status'] === 'Faulty' ? 'selected' : ''; ?>>Faulty</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Warranty Type</label>
                                <select name="warranty_type">
                                    <option value="Standard" <?php echo $equipment['warranty_type'] === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                    <option value="Extended" <?php echo $equipment['warranty_type'] === 'Extended' ? 'selected' : ''; ?>>Extended</option>
                                    <option value="Premium" <?php echo $equipment['warranty_type'] === 'Premium' ? 'selected' : ''; ?>>Premium</option>
                                    <option value="Enterprise" <?php echo $equipment['warranty_type'] === 'Enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                                    <option value="None" <?php echo $equipment['warranty_type'] === 'None' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Purchase Date</label>
                                <input type="date" name="purchase_date" value="<?php echo $equipment['purchase_date']; ?>">
                            </div>
                            <div class="form-group">
                                <label>Purchase Cost</label>
                                <input type="number" name="purchase_cost" step="0.01" value="<?php echo $equipment['purchase_cost']; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Current Photo</label>
                            <?php if ($equipment['photo_filename']): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="../../equipment/<?php echo htmlspecialchars($equipment['photo_filename']); ?>" style="max-width: 200px; border-radius: 6px;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Update Photo</label>
                            <input type="file" name="photo" accept="image/*">
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">💾 Update Equipment</button>
                            <a href="view.php?id=<?php echo $id; ?>" class="btn">← Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/theme.js"></script>
</body>
</html>