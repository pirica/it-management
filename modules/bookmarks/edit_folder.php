<?php
require '../../config/config.php';
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

$id = (int)($_GET['id'] ?? 0);
$sql = "SELECT * FROM bookmark_folders WHERE id = $id AND company_id = $company_id";
$res = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($res);

if (!$data || !bkm_can_edit_folder($data, $user_id, $is_admin)) {
    die('Folder not found or access denied.');
}

$isPrivateFolder = (int)($data['shared'] ?? 0) === 0;
$needsVaultForForm = $isPrivateFolder && empty($bkmVaultState['unlocked']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['master_key'])) {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $name = trim($_POST['name'] ?? '');
    $parent_folder_id = (int)($_POST['parent_folder_id'] ?? 0) ?: null;
    $merge_into_folder_id = (int)($_POST['merge_into_folder_id'] ?? 0);
    $shared = isset($_POST['shared']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Folder name is required.';
    }
    if ($parent_folder_id == $id) {
        $errors[] = 'A folder cannot be its own parent.';
    }
    if ($parent_folder_id !== null && bkm_folder_is_descendant_of($conn, $parent_folder_id, $id)) {
        $errors[] = 'A folder cannot be moved into one of its subfolders.';
    }
    if ($shared === 0 && empty($bkmVaultState['unlocked'])) {
        $errors[] = 'Unlock your vault to save private folders.';
    }

    if (empty($errors)) {
        if ($merge_into_folder_id > 0) {
            $result = bkm_move_folder($conn, $company_id, $user_id, $id, $parent_folder_id, $merge_into_folder_id, $is_admin);
        } else {
            $result = bkm_update_folder_row($conn, $id, $company_id, $parent_folder_id, $name, $shared, $active);
        }
        if ($result['ok']) {
            header('Location: index.php');
            return;
        }
        $errors[] = $result['message'] !== '' ? $result['message'] : 'Database error.';
    }

    $data['name'] = $name;
    $data['parent_folder_id'] = $parent_folder_id;
    $data['shared'] = $shared;
    $data['active'] = $active;
} elseif (!$needsVaultForForm) {
    bkm_hydrate_folder_row($data, $user_id);
}

$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);
$bkmFolderChildrenByParent = [];
foreach ($all_folders as $folderRow) {
    if ((int)$folderRow['id'] === $id) {
        continue;
    }
    $parentKey = $folderRow['parent_folder_id'] ? (int)$folderRow['parent_folder_id'] : 0;
    if (!isset($bkmFolderChildrenByParent[$parentKey])) {
        $bkmFolderChildrenByParent[$parentKey] = [];
    }
    $bkmFolderChildrenByParent[$parentKey][] = [
        'id' => (int)$folderRow['id'],
        'name' => (string)$folderRow['name'],
    ];
}
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
    $crud_title = 'Edit Folder';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .danger-card {
            width: 300px;
            border: 1px solid var(--danger);
            background: var(--bg-tertiary);
            padding: 20px;
            border-radius: 6px;
        }
        .danger-card h3 { color: var(--danger); margin-top: 0; }
        .danger-card p { font-size: 0.9em; color: var(--text-secondary); }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
        <h1>Edit Folder</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
            <?php if ($needsVaultForForm): ?>
                <?php bkm_render_vault_lock_screen($csrfToken, $bkmVaultState, 'edit_folder.php?id=' . $id); ?>
            <?php else: ?>
            <form method="POST" class="form-grid" style="flex: 1; min-width: 300px;" id="edit-folder-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="merge_into_folder_id" id="edit-folder-merge-into" value="">
                <div class="form-group">
                    <label>Folder Name</label>
                    <input type="text" name="name" required value="<?php echo sanitize($data['name']); ?>">
                </div>
                <div class="form-group">
                    <label>Parent Folder</label>
                    <select name="parent_folder_id">
                        <option value="">-- None --</option>
                        <?php echo bkm_render_folder_options($folder_tree, $data['parent_folder_id']); ?>
                    </select>
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
                        <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo $data['active'] ? '✅' : '❌'; ?></span></span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </form>

            <?php if (bkm_can_edit_folder($data, $user_id, $is_admin)): ?>
                <div class="danger-card">
                    <h3>Danger Zone</h3>
                    <p>Deleting this folder will move all its bookmarks and subfolders to the root level.</p>
                    <form method="POST" action="delete_folder.php" onsubmit="return confirm('Are you sure you want to delete this folder? Bookmarks will be moved to root.');">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button class="btn btn-danger" type="submit" style="width: 100%;" title="Delete">🗑️</button>
                    </form>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
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

const bkmFolderChildrenByParent = <?php echo json_encode($bkmFolderChildrenByParent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const bkmEditFolderId = <?php echo (int)$id; ?>;
const bkmEditFolderOriginalParentId = <?php echo $data['parent_folder_id'] ? (int)$data['parent_folder_id'] : 0; ?>;

function bkmFindSiblingFolderInMap(parentId, sourceFolderId, folderName) {
    const normalized = (folderName || '').trim().toLowerCase();
    if (!normalized) {
        return null;
    }
    const children = bkmFolderChildrenByParent[parentId] || bkmFolderChildrenByParent[String(parentId)] || [];
    for (let i = 0; i < children.length; i++) {
        if (String(children[i].id) === String(sourceFolderId)) {
            continue;
        }
        if ((children[i].name || '').trim().toLowerCase() === normalized) {
            return children[i].id;
        }
    }
    return null;
}

const editFolderForm = document.getElementById('edit-folder-form');
if (editFolderForm) {
    editFolderForm.addEventListener('submit', function () {
        const parentSelect = editFolderForm.querySelector('select[name="parent_folder_id"]');
        const nameInput = editFolderForm.querySelector('input[name="name"]');
        const mergeInput = document.getElementById('edit-folder-merge-into');
        if (!parentSelect || !nameInput || !mergeInput) {
            return;
        }
        const newParent = parentSelect.value === '' ? 0 : parseInt(parentSelect.value, 10);
        mergeInput.value = '';
        if (newParent === bkmEditFolderOriginalParentId) {
            return;
        }
        const siblingId = bkmFindSiblingFolderInMap(newParent, bkmEditFolderId, nameInput.value);
        if (!siblingId) {
            return;
        }
        const merge = confirm(
            'A folder named "' + nameInput.value.trim() + '" already exists in this location.\n\nMerge this folder into it?\n\nOK = merge contents\nCancel = keep both folders with the same name'
        );
        if (merge) {
            mergeInput.value = siblingId;
        }
    });
}
</script>
</body>
</html>
