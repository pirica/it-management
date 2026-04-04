<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$error = '';
$data = ['name' => '', 'description' => '', 'active' => 1];

if ($isEdit) {
    $stmt = mysqli_prepare($conn, 'SELECT id, name, description, active FROM departments WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $data = mysqli_fetch_assoc($result);
        } else {
            $error = 'Department not found.';
            $isEdit = false;
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Unable to load department.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;

    $data = [
        'name' => $name,
        'description' => $description,
        'active' => $active,
    ];

    if ($name === '') {
        $error = 'Department name is required.';
    } else {
        if ($isEdit) {
            $stmt = mysqli_prepare($conn, 'UPDATE departments SET name = ?, description = ?, active = ? WHERE id = ? AND company_id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssiii', $name, $description, $active, $id, $company_id);
                $ok = mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($ok) {
                    if ($affected === 0) {
                        $_SESSION['crud_error'] = 'No changes were made or department was not found.';
                    }
                    header('Location: index.php');
                    exit;
                }
            }
            $error = 'Failed to update department.';
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO departments (company_id, name, description, active) VALUES (?, ?, ?, ?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'issi', $company_id, $name, $description, $active);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if ($ok) {
                    header('Location: index.php');
                    exit;
                }
            }
            $error = 'Failed to create department.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Department</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $isEdit ? '✏️ Edit' : '➕ Create'; ?> Department</h1>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_post_csrf_token()); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="department-name">Name *</label>
                            <input id="department-name" name="name" required value="<?php echo sanitize($data['name']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="department-description">Description</label>
                        <textarea id="department-description" name="description"><?php echo sanitize($data['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="active" <?php echo (int)$data['active'] === 1 ? 'checked' : ''; ?>>
                            <span>Active</span>
                        </label>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="btn btn-primary">💾 Save</button>
                        <a class="btn" href="index.php">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
