<?php
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'edit', 'bookmarks');

require './helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$id = (int)($_GET['id'] ?? 0);
$sql = "SELECT * FROM bookmarks WHERE id = $id AND company_id = $company_id";
$res = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($res);

if (!$data || !bkm_can_edit_bookmark($data, $user_id, $is_admin)) {
    die('Record not found or access denied.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $folder_id = (int)($_POST['folder_id'] ?? 0) ?: null;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($title === '') $errors[] = 'Title is required.';
    if ($url === '') {
        $errors[] = 'URL is required.';
    } elseif (!bkm_import_url_is_allowed($url)) {
        $errors[] = 'Invalid URL. Only http://, https://, and ftp:// protocols are allowed.';
    } elseif (bkm_bookmark_url_exists_for_employee($conn, $company_id, $user_id, $url, $id)) {
        $errors[] = 'A bookmark with this URL already exists for your account.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE bookmarks SET folder_id = ?, title = ?, url = ?, notes = ?, shared = ?, active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'isssiii', $folder_id, $title, $url, $notes, $shared, $active, $id);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php' . ($folder_id ? "?folder_name=$folder_id" : ""));
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
    $crud_title = 'Edit Bookmark';
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
        <h1>Edit Bookmark</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required value="<?php echo sanitize($data['title']); ?>">
            </div>
            <div class="form-group">
                <label>URL</label>
                <input type="url" name="url" required value="<?php echo sanitize($data['url']); ?>">
            </div>
            <div class="form-group">
                <label>Folder</label>
                <select name="folder_id">
                    <option value="">-- Root --</option>
                    <?php echo bkm_render_folder_options($folder_tree, $data['folder_id']); ?>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes"><?php echo sanitize($data['notes']); ?></textarea>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="shared" value="1" <?php echo $data['shared'] ? 'checked' : ''; ?>>
                    <span>Shared <span class="itm-shared-indicator" aria-hidden="true"><?php echo $data['shared'] ? '🔓' : '🔒'; ?></span></span>
                </label>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1" <?php echo $data['active'] ? 'checked' : ''; ?>>
                    <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo ($data['active']) ? '✅' : '❌'; ?></span></span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾</button>
                <a href="index.php" class="btn">🔙</a>
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
