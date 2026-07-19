<?php
/**
 * Bookmarks Module - List All (Table View)
 */
require '../../config/config.php';
require './helpers.php';
require './bkm_vault_bootstrap.php';

// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = (string)@file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);

    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        if (!itm_validate_csrf_token($itmImportJsonBody['csrf_token'] ?? '')) {
            http_response_code(403);
            die('CSRF validation failed');
        }
        if (empty($_SESSION['vault_key'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Unlock your vault before importing bookmarks.']);
            exit;
        }
        itm_handle_json_table_import($conn, 'bookmarks', (int)($_SESSION['company_id'] ?? 0));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'move_bookmarks') {
        if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
            die('CSRF validation failed.');
        }
        $target_folder_id = (int)($_POST['target_folder_id'] ?? 0) ?: null;
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        foreach ($ids as $bookmark_id) {
            $check_res = mysqli_query(
                $conn,
                'SELECT * FROM bookmarks WHERE id = ' . (int)$bookmark_id . ' AND company_id = ' . (int)$company_id . ' AND active = 1 LIMIT 1'
            );
            $bookmark_row = $check_res ? mysqli_fetch_assoc($check_res) : null;
            if (!$bookmark_row || !bkm_can_edit_bookmark($bookmark_row, $user_id, $is_admin)) {
                continue;
            }
            if ($target_folder_id === null) {
                $stmt = mysqli_prepare($conn, 'UPDATE bookmarks SET folder_id = NULL WHERE id = ? AND company_id = ?');
                mysqli_stmt_bind_param($stmt, 'ii', $bookmark_id, $company_id);
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE bookmarks SET folder_id = ? WHERE id = ? AND company_id = ?');
                mysqli_stmt_bind_param($stmt, 'iii', $target_folder_id, $bookmark_id, $company_id);
            }
            mysqli_stmt_execute($stmt);
        }
        header('Location: list_all.php');
        exit;
    }
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));
$bkmVaultState = bkm_handle_vault_requests($conn, $user_id);

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$crud_table = 'bookmarks';
$crud_title = 'Bookmarks List';

$sort = $_GET['sort'] ?? 'title';
$dir = (strtoupper($_GET['dir'] ?? '') === 'DESC') ? 'DESC' : 'ASC';

$allowedSorts = ['title', 'url', 'folder', 'shared'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'title';
}

// Extra safety check as per plan
if (function_exists('itm_is_safe_identifier') && !itm_is_safe_identifier($sort)) {
    $sort = 'title';
}

// Sorting handled in PHP after decrypting private titles/URLs.

$searchRaw = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

$rows = [];
$totalRows = 0;
$totalPages = 1;

if (!empty($bkmVaultState['unlocked'])) {
$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);
$folderNameById = bkm_folder_name_map($all_folders);

$listResult = bkm_query_bookmarks_for_list($conn, [
    'company_id' => $company_id,
    'user_id' => $user_id,
    'view_mode' => 'all',
    'folder_scope' => 'any',
    'selected_folder_id' => null,
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
    'per_page' => $perPage,
    'folder_name_by_id' => $folderNameById,
]);
$rows = $listResult['rows'];
$totalRows = $listResult['totalRows'];
$totalPages = $listResult['totalPages'];
$page = $listResult['page'];

foreach ($rows as &$listRow) {
    $folderId = (int)($listRow['folder_id'] ?? 0);
    $listRow['folder_display_name'] = $folderId > 0 ? ($folderNameById[$folderId] ?? '') : '';
}
unset($listRow);
}

$csrfToken = itm_get_csrf_token();
$showBulkActions = ($totalRows > 0);
$folder_tree = $folder_tree ?? [];
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
    $crud_title = 'Bookmarks';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>

    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .dropdown-menu { display: none; position: absolute; background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; padding: 5px 0; margin-top: 5px; }
        .dropdown-menu.show { display: block; }
        .dropdown-item { display: block; width: 100%; padding: 8px 15px; border: none; background: none; text-align: left; cursor: pointer; color: var(--text-primary); text-decoration: none; font-size: 0.9em; }
        .dropdown-item:hover { background: var(--bg-secondary); }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
        <?php if (empty($bkmVaultState['unlocked'])): ?>
            <h1><?php echo sanitize($crud_title); ?></h1>
            <?php bkm_render_vault_lock_screen($csrfToken, $bkmVaultState, 'list_all.php'); ?>
        <?php else: ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1><?php echo sanitize($crud_title); ?></h1>
            <div style="display: flex; gap: 8px; align-items: center;">
                <div class="dropdown" style="position: relative;">
                    
                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show'); event.stopPropagation();">Tools ⚙️</button>
                    <div class="dropdown-menu" style="right: 0; min-width: 180px;">
                        <a class="dropdown-item" href="index.php">📂 Tree View</a>
                        <a class="dropdown-item" href="import.php">📤 Import</a>
                    </div>
                </div>
                <a href="create.php" class="btn btn-primary">➕</a>
            </div>
        </div>

        <?php if ($showBulkActions): ?>
        <div class="card" style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <button type="button" class="btn btn-sm" id="bulk-select-toggle" data-itm-bulk-select="1" title="Select All">Select All</button>
                <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
            </form>
            <form id="bulk-move-form" method="POST" action="list_all.php" style="display:none;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="action" value="move_bookmarks">
                <label for="bulk-move-folder" style="margin:0;">Move to</label>
                <select id="bulk-move-folder" name="target_folder_id" class="form-control" style="min-width:180px;">
                    <option value="0">Root</option>
                    <?php echo bkm_render_folder_options($folder_tree); ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary" id="bulk-move-submit">Move to</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:16px;">
            <form method="GET" style="display:flex; gap:10px; align-items:flex-end;">
                <div class="form-group" style="margin:0; flex:1;">
                    <label>Search</label>
                    <input type="text" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search...">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="list_all.php" class="btn">Clear</a>
            </form>
        </div>

        <div class="card" style="overflow:auto;">
            <table data-itm-db-import-endpoint="list_all.php">
                <thead>
                    <tr>
                        <?php if ($showBulkActions): ?>
                            <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                        <?php endif; ?>
                        <?php
                        $columns = [
                            'title'  => 'Title',
                            'favicon' => 'Favicon',
                            'url'    => 'URL',
                            'notes'  => 'Notes',
                            'folder' => 'Folder',
                            'shared' => 'Shared'
                        ];
                        foreach ($columns as $colKey => $colLabel):
                            if ($colKey === "favicon") {
                                echo "<th>" . sanitize($colLabel) . "</th>";
                                continue;
                            }
                            $nextDir = ($sort === $colKey && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $arrow = ($sort === $colKey) ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
                        ?>
                            <th>
                                <a href="?sort=<?php echo urlencode($colKey); ?>&dir=<?php echo $nextDir; ?>&search=<?php echo urlencode($searchRaw); ?>" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize($colLabel) . $arrow; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php if ($showBulkActions): ?>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                <?php endif; ?>
                                <td><?php echo sanitize($row['title_display'] ?? $row['title'] ?? ''); ?></td>
                                <td style="text-align:center;">
                                    <?php if (empty($row['url_locked']) && !empty($row['url_display'])): ?>
                                    <img src="<?php echo bkm_get_favicon_url($row['url_display']); ?>"
                                         alt="favicon"
                                         style="width:16px; height:16px; vertical-align:middle;"
                                         onerror="this.style.display='none';">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['url_locked'])): ?>
                                        <span style="color:var(--text-tertiary);"><?php echo sanitize($row['url_locked_label'] ?: '🔒 URL hidden'); ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo sanitize($row['url_display']); ?>" rel="nofollow noreferrer noopener" target="_blank" style="color:var(--accent); text-decoration:none;"><?php echo sanitize($row['url_display']); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize($row['notes_display'] ?? $row['notes'] ?? ''); ?></td>
                                <td><?php echo sanitize($row['folder_display_name'] ?? 'Root'); ?></td>
                                <td><?php echo $row['shared'] ? '✅' : '❌'; ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <?php if (bkm_can_edit_bookmark($row, $user_id, $is_admin)): ?>
                                        <button type="button" class="btn btn-sm" onclick="itmOpenQrShareModal('index.php?ajax_action=create_share_session', <?php echo (int)$row['id']; ?>)" title="Share to device">📱</button>
                                        <button type="button" class="btn btn-sm" onclick="itmOpenWhatsAppShare('index.php?ajax_action=create_share_session', <?php echo (int)$row['id']; ?>, null, 'bookmark')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
                                        <button type="button" class="btn btn-sm" onclick="itmOpenOutlookShare('index.php?ajax_action=create_share_session', <?php echo (int)$row['id']; ?>, null, 'bookmark')" title="Share on Outlook">📨</button>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo $row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo $showBulkActions ? 8 : 7; ?>" style="text-align:center;">No bookmarks found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>" class="btn btn-sm">Previous</a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;"><?php echo "Page $page of $totalPages"; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>" class="btn btn-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/bulk-delete-selection.js"></script>
<script>
const bulkMoveForm = document.getElementById('bulk-move-form');
function updateBulkMoveFormVisibility() {
    if (!bulkMoveForm) {
        return;
    }
    const checked = document.querySelectorAll('input[name="ids[]"][form="bulk-delete-form"]:checked');
    bulkMoveForm.style.display = checked.length > 0 ? 'flex' : 'none';
}
if (bulkMoveForm) {
    document.addEventListener('itm-bulk-selection-change', updateBulkMoveFormVisibility);
    bulkMoveForm.addEventListener('submit', function(event) {
        const checked = document.querySelectorAll('input[name="ids[]"][form="bulk-delete-form"]:checked');
        if (!checked.length) {
            event.preventDefault();
            alert('Please select at least one bookmark to move.');
            return;
        }
        bulkMoveForm.querySelectorAll('input[name="ids[]"]').forEach(function(input) {
            input.remove();
        });
        checked.forEach(function(checkbox) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'ids[]';
            hidden.value = checkbox.value;
            bulkMoveForm.appendChild(hidden);
        });
    });
    updateBulkMoveFormVisibility();
}

document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu').forEach(function(el) {
        el.classList.remove('show');
    });
});
</script>
<?php require_once ROOT_PATH . 'includes/itm_qr_share_modal.php'; ?>
<script>window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>
</body>
</html>
