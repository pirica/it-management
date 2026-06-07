<?php
require '../../config/config.php';
require './helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = (($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $folder_name = (int)($_POST['folder_name'] ?? 0) ?: null;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($title === '') $errors[] = 'Title is required.';
    if ($url === '') $errors[] = 'URL is required.';

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO bookmarks (company_id, user_id, folder_name, title, url, notes, shared, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiisssii', $company_id, $user_id, $folder_name, $title, $url, $notes, $shared, $active);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php' . ($folder_name ? "?folder_name=$folder_name" : ""));
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
    <title>Add Bookmark - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="main-container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="content">
        <h1>Add New Bookmark</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required value="<?php echo sanitize($_POST['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>URL</label>
                <input type="url" name="url" required value="<?php echo sanitize($_POST['url'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Folder</label>
                <select name="folder_name">
                    <option value="">-- Root --</option>
                    <?php echo bkm_render_folder_options($folder_tree, $_GET['folder_name'] ?? null); ?>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes"><?php echo sanitize($_POST['notes'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="shared" value="1" <?php echo isset($_POST['shared']) ? 'checked' : ''; ?>>
                    <span>Shared 🔓</span>
                </label>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1" checked>
                    <span>Active ✅</span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Save</button>
                <a href="index.php" class="btn">🔙 Back</a>
            </div>
        </form>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
