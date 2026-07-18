<?php
require '../../config/config.php';
require './helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $name = trim($_POST['name'] ?? '');
    $parent_folder_id = (int)($_POST['parent_folder_id'] ?? 0) ?: null;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '') $errors[] = 'Folder name is required.';

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO bookmark_folders (company_id, employee_id, parent_folder_id, name, shared, active) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiisii', $company_id, $user_id, $parent_folder_id, $name, $shared, $active);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php');
            return;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
    }
}

$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);
$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Add Folder';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
        <h1>Add New Folder</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <div class="form-group">
                <label>Folder Name</label>
                <input type="text" name="name" required value="<?php echo sanitize($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Parent Folder</label>
                <select name="parent_folder_id">
                    <option value="">-- None --</option>
                    <?php echo bkm_render_folder_options($folder_tree); ?>
                </select>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="shared" value="1" <?php echo isset($_POST['shared']) ? 'checked' : ''; ?>>
                    <span>Shared <span class="itm-shared-indicator" aria-hidden="true"><?php echo isset($_POST['shared']) ? '🔓' : '🔒'; ?></span></span>
                </label>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1" checked>
                    <span>Active <span class="itm-check-indicator" aria-hidden="true">✅</span></span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                <a href="index.php" class="btn" title="Back">🔙</a>
            </div>
        </form>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const control = event.target.closest('.itm-checkbox-control');
    if (!control) return;
    const activeIndicator = control.querySelector('.itm-check-indicator');
    if (activeIndicator) {
        activeIndicator.textContent = event.target.checked ? '✅' : '❌';
        return;
    }
    const sharedIndicator = control.querySelector('.itm-shared-indicator');
    if (sharedIndicator) {
        sharedIndicator.textContent = event.target.checked ? '🔓' : '🔒';
    }
});
</script>
</body>
</html>
