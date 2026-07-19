<?php
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'create', 'bookmarks');

require './helpers.php';
require './bkm_vault_bootstrap.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));
$bkmVaultState = bkm_handle_vault_requests($conn, $user_id);

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['master_key'])) {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $folder_id = (int)($_POST['folder_id'] ?? 0) ?: null;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($url === '') {
        $errors[] = 'URL is required.';
    } elseif (!bkm_import_url_is_allowed($url)) {
        $errors[] = 'Invalid URL. Only http://, https://, and ftp:// protocols are allowed.';
    } elseif (bkm_bookmark_url_exists_for_employee($conn, $company_id, $user_id, $url)) {
        $errors[] = 'A bookmark with this URL already exists for your account.';
    } elseif ($shared === 0 && empty($bkmVaultState['unlocked'])) {
        $errors[] = 'Unlock your vault to save private bookmarks.';
    }

    if (empty($errors)) {
        $result = bkm_insert_bookmark_row($conn, $company_id, $user_id, $folder_id, $title, $url, $notes, $shared, $active);
        if ($result['ok']) {
            header('Location: index.php' . ($folder_id ? "?folder_id=$folder_id" : ''));
            return;
        }
        $errors[] = $result['message'] !== '' ? $result['message'] : 'Database error.';
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
    $crud_title = 'Add Bookmark';
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
        <h1>Add New Bookmark</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <p style="margin-bottom:16px;color:var(--text-secondary);">Private bookmark URLs are encrypted in the database. Shared bookmarks store the URL as plaintext for team access.</p>
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required value="<?php echo sanitize($_POST['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>URL</label>
                <input type="url" name="url" required placeholder="https://..." value="<?php echo sanitize($_POST['url'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Folder</label>
                <select name="folder_id">
                    <option value="">-- Root --</option>
                    <?php echo bkm_render_folder_options($folder_tree, $_GET['folder_id'] ?? null); ?>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes"><?php echo sanitize($_POST['notes'] ?? ''); ?></textarea>
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
                <?php if (empty($bkmVaultState['unlocked'])): ?>
                    <a href="../../user-config.php#vault-security" class="btn btn-sm">Unlock vault</a>
                <?php endif; ?>
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
