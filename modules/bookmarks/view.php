<?php
/**
 * Bookmarks Module - View
 *
 * Read-only bookmark detail with vault-aware private fields and scaffold audit meta.
 */

require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'view', 'bookmarks');

require './helpers.php';
require './bkm_vault_bootstrap.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, $user_id);
$bkmVaultState = bkm_handle_vault_requests($conn, $user_id);

if ($company_id <= 0) {
    header('Location: ../../index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$data = null;
$error = '';

if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM bookmarks WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = ($result && mysqli_num_rows($result) === 1) ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Failed to load bookmark.';
    }
}

if ($data !== null) {
    $ownerId = (int)($data['employee_id'] ?? 0);
    $isShared = (int)($data['shared'] ?? 0) === 1;
    if ($ownerId !== $user_id && !$isShared) {
        $data = null;
        $error = 'Bookmark not found or access denied.';
    }
}

if ($data === null && $error === '') {
    $error = $id > 0 ? 'Bookmark not found for ID ' . $id . '.' : 'Invalid bookmark id.';
}

$needsVaultForView = false;
if (is_array($data)) {
    $needsVaultForView = (int)($data['shared'] ?? 0) === 0 && empty($bkmVaultState['unlocked']);
    if (!$needsVaultForView) {
        bkm_hydrate_bookmark_row($data, $user_id);
    }
}

$folderLabel = '—';
if (is_array($data) && !empty($data['folder_id'])) {
    $folderRow = bkm_get_folder_row_by_id($conn, (int)$data['folder_id'], $company_id, $user_id);
    if (is_array($folderRow)) {
        $folderLabel = (string)($folderRow['name_plain'] ?? $folderRow['name'] ?? '—');
    }
}

$canEdit = is_array($data) && bkm_can_edit_bookmark($data, $user_id, $is_admin);
$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'View Bookmark';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="View bookmark">🔎</h1>
            <div class="card">
                <?php if (!is_array($data)): ?>
                    <?php echo itm_render_alert_errors($error); ?>
                <?php elseif ($needsVaultForView): ?>
                    <?php bkm_render_vault_lock_screen($csrfToken, $bkmVaultState, 'view.php?id=' . $id); ?>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)($data['id'] ?? 0); ?></td></tr>
                        <tr><th>Title</th><td><?php echo sanitize((string)($data['title_display'] ?? $data['title_plain'] ?? $data['title'] ?? '')); ?></td></tr>
                        <tr><th>URL</th><td>
                            <?php if (!empty($data['url_locked'])): ?>
                                <span style="color:var(--text-tertiary);"><?php echo sanitize((string)($data['url_locked_label'] ?? '🔒 URL hidden')); ?></span>
                            <?php else: ?>
                                <a href="<?php echo sanitize((string)($data['url_display'] ?? '')); ?>" target="_blank" rel="nofollow noreferrer noopener"><?php echo sanitize((string)($data['url_display'] ?? '')); ?></a>
                            <?php endif; ?>
                        </td></tr>
                        <tr><th>Folder</th><td><?php echo sanitize($folderLabel); ?></td></tr>
                        <tr><th>Notes</th><td><?php echo nl2br(sanitize((string)($data['notes_display'] ?? $data['notes_plain'] ?? $data['notes'] ?? ''))); ?></td></tr>
                        <tr><th>Visibility</th><td><?php echo ((int)($data['shared'] ?? 0) === 1) ? '🔓 Shared' : '🔒 Private'; ?></td></tr>
                        <tr><th>Active</th><td><?php echo ((int)($data['active'] ?? 0) === 1) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
                        <tr><th>Deleted By</th><td><?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'deleted_by', $data['deleted_by'] ?? null); ?></td></tr>
                        <tr><th>Deleted At</th><td><?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'deleted_at', $data['deleted_at'] ?? null); ?></td></tr>
                        <tr><th>Created By</th><td><?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'created_by', $data['created_by'] ?? null); ?></td></tr>
                        <tr><th>Created At</th><td><?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'created_at', $data['created_at'] ?? null); ?></td></tr>
                        <tr><th>Updated By</th><td><?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'updated_by', $data['updated_by'] ?? null); ?></td></tr>
                        <tr><th>Updated At</th><td><?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'updated_at', $data['updated_at'] ?? null); ?></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn" title="Back">🔙</a>
                    <?php if ($canEdit && !$needsVaultForView): ?>
                        <a href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>" class="btn btn-primary" title="Edit">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
