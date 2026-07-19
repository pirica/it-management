<?php

$crud_table = 'bookmarks';
$crud_title = 'Bookmarks';
$crud_action = $crud_action ?? 'index';

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

// Handle Excel/CSV database import requests from table-tools.js and form POST mutations.
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

    itm_require_post_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'move_folder') {
        $fid = (int)($_POST['folder_id'] ?? 0);
        $new_parent = (int)($_POST['new_parent_id'] ?? 0) ?: null;
        $merge_into = (int)($_POST['merge_into_folder_id'] ?? 0);

        bkm_move_folder($conn, $company_id, $user_id, $fid, $new_parent, $merge_into, $is_admin);

        header('Location: index.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'rename_folder') {
        $fid = (int)($_POST['folder_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $redirectView = trim((string)($_POST['redirect_view'] ?? 'all'));
        if (!in_array($redirectView, ['all', 'private', 'shared'], true)) {
            $redirectView = 'all';
        }

        $folder = bkm_get_folder_row_by_id($conn, $fid, $company_id, $user_id);
        $redirectParams = ['view' => $redirectView, 'folder_id' => $fid];
        if (!$folder || !bkm_can_edit_folder($folder, $user_id, $is_admin)) {
            $redirectParams['rename_error'] = 'access';
        } elseif ($name === '') {
            $redirectParams['rename_error'] = 'empty';
        } else {
            $parentId = !empty($folder['parent_folder_id']) ? (int)$folder['parent_folder_id'] : null;
            $result = bkm_update_folder_row(
                $conn,
                $fid,
                $company_id,
                $parentId,
                $name,
                (int)($folder['shared'] ?? 0),
                (int)($folder['active'] ?? 1)
            );
            if (!$result['ok']) {
                $redirectParams['rename_error'] = 'save';
            }
        }

        header('Location: index.php?' . http_build_query($redirectParams));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'move_bookmarks') {
        $target_folder_id = (int)($_POST['target_folder_id'] ?? 0) ?: null;
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        foreach ($ids as $bookmark_id) {
            $check_res = mysqli_query(
                $conn,
                'SELECT * FROM bookmarks WHERE id = ' . (int)$bookmark_id . ' AND company_id = ' . (int)$company_id . ' AND active = 1 LIMIT 1'
            );
            $bookmark_row = $check_res ? mysqli_fetch_assoc($check_res) : null;
            if (!$bookmark_row || !bkm_can_edit_bookmark($bookmark_row, (int)($_SESSION['employee_id'] ?? 0), itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0)))) {
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
        $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    }
}

$selected_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'all';
$searchRaw = trim((string)($_GET['search'] ?? ''));
$bkmVaultUnlocked = !empty($bkmVaultState['unlocked']);
$bkmRenameError = trim((string)($_GET['rename_error'] ?? ''));

// Fetch folders
$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);
$folderNameById = bkm_folder_name_map($all_folders);

$bookmarks = [];
$totalRows = 0;
$totalPages = 1;
$page = 1;
$offset = 0;
$showBulkActions = false;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$sort = 'title';
$dir = 'ASC';

if (!empty($bkmVaultState['unlocked'])) {

$bkmSortableColumns = ['title', 'url', 'notes', 'shared'];
$sort = (string)($_GET['sort'] ?? 'title');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
if (!in_array($sort, $bkmSortableColumns, true)) {
    $sort = 'title';
}

$listResult = bkm_query_bookmarks_for_list($conn, [
    'company_id' => $company_id,
    'user_id' => $user_id,
    'view_mode' => $view_mode,
    'selected_folder_id' => $selected_folder_id,
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
    'per_page' => $perPage,
    'folder_name_by_id' => $folderNameById,
]);
$bookmarks = $listResult['rows'];
$totalRows = $listResult['totalRows'];
$totalPages = $listResult['totalPages'];
$page = $listResult['page'];
$offset = ($page - 1) * $perPage;
$showBulkActions = ($totalRows > 0);
}

$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}

$bkmListQueryState = [
    'view' => $view_mode,
    'folder_id' => $selected_folder_id,
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
];

if (isset($_GET['ajax_action']) && (string)$_GET['ajax_action'] === 'create_share_session') {
    header('Content-Type: application/json; charset=utf-8');
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    require_once __DIR__ . '/bookmarks_share_helpers.php';
    $bookmarkId = (int)($_POST['id'] ?? 0);
    $ownerUsername = (string)($_SESSION['username'] ?? '');
    $vaultUnlocked = !empty($bkmVaultState['unlocked']);
    $result = bookmarks_share_create_session($conn, $bookmarkId, $company_id, $user_id, $ownerUsername, $is_admin, $vaultUnlocked);
    if (!$result['ok']) {
        $httpCode = !empty($result['error']) && stripos((string)$result['error'], 'vault') !== false ? 403 : 400;
        http_response_code($httpCode);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Unable to create share session.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $session = $result['session'];
    echo json_encode([
        'ok' => true,
        'share_code' => (string)$session['share_code'],
        'join_url' => bookmarks_share_build_join_url((string)$session['access_token']),
        'expires_at' => (string)$session['expires_at'],
        'ttl_seconds' => itm_qr_share_session_ttl_seconds(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    $crud_title = 'Bookmarks';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .bookmarks-layout { display: flex; gap: 24px; }
        .bookmarks-sidebar { width: 300px; flex-shrink: 0; background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .bookmarks-main { flex: 1; min-width: 0; }
        .folder-tree { list-style: none; padding: 0; margin: 0; }
        .folder-tree li { margin: 4px 0; }
        .folder-tree li div { display: flex; align-items: center; padding: 6px 10px; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
        .folder-tree li div:hover { background: var(--bg-secondary); }
        .folder-tree li.active > div { background: var(--accent); color: #fff; }
        .folder-tree li.active > div a { color: #fff; }
        .folder-tree a { text-decoration: none; color: var(--text-primary); flex: 1; }
        .view-filters { margin-bottom: 20px; display: flex; gap: 5px; flex-wrap: wrap; }
        .view-filters a { padding: 6px 12px; border-radius: 4px; background: var(--bg-secondary); text-decoration: none; color: var(--text-secondary); font-size: 0.9em; border: 1px solid var(--border); }
        .view-filters a.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        /* Why: table list is full-width; grid card columns pushed pagination beside the table. */
        .bookmarks-list { display: block; width: 100%; }
        .bookmark-card { background: var(--bg-card); border: 1px solid var(--border); padding: 16px; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; }
        .bookmark-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .bookmark-info { margin-bottom: 12px; }
        .bookmark-actions { display: flex; gap: 8px; justify-content: flex-end; align-items: center; }
        .shared-badge { font-size: 0.75em; padding: 2px 6px; border-radius: 4px; background: #e1f5fe; color: #01579b; font-weight: 600; }
        .private-badge { font-size: 0.75em; padding: 2px 6px; border-radius: 4px; background: #f5f5f5; color: #616161; font-weight: 600; }
        li[drag-over="true"] > div { background: var(--accent-alpha, rgba(9, 105, 218, 0.1)) !important; border: 1px dashed var(--accent); }

        #export-dropdown { display: none; position: absolute; background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 100; margin-top: 5px; }
        #export-dropdown button { display: block; width: 100%; padding: 8px 15px; border: none; background: none; text-align: left; cursor: pointer; color: var(--text-primary); }
        #export-dropdown button:hover { background: var(--bg-secondary); }

        /* Bulk delete styles */
        .bulk-delete-bar { margin-bottom: 16px; display: flex; gap: 8px; align-items: center; }
        .bookmark-checkbox-wrap { display: none; margin-right: 10px; }

        @media (max-width: 1200px) {
            .bookmarks-layout { flex-direction: column; }
            .bookmarks-sidebar { width: 100%; }
        }
        .dropdown-menu { display: none; position: absolute; background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; padding: 5px 0; margin-top: 5px; }
        .dropdown-menu.show { display: block; }
        .dropdown-item { display: block; width: 100%; padding: 8px 15px; border: none; background: none; text-align: left; cursor: pointer; color: var(--text-primary); text-decoration: none; font-size: 0.9em; }
        .dropdown-item:hover { background: var(--bg-secondary); }

        .bkm-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(0, 0, 0, 0.55);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .bkm-modal-overlay.is-open { display: flex; }
        .bkm-modal-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            background-color: var(--bg-primary, #ffffff);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow-lg, 0 8px 24px rgba(0, 0, 0, 0.25));
        }
        .bkm-modal-card .form-group input[type="text"] {
            background-color: var(--bg-primary, #ffffff);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .bkm-modal-card h3 { margin: 0 0 16px; }
        .itm-folder-tree-actions .btn { flex-shrink: 0; }
        .folder-tree li.active > div .itm-folder-tree-actions .btn { color: inherit; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
        <?php if ($bkmRenameError !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <?php
                if ($bkmRenameError === 'empty') {
                    echo 'Folder name is required.';
                } elseif ($bkmRenameError === 'access') {
                    echo 'You do not have permission to rename this folder.';
                } else {
                    echo 'Unable to rename folder. Unlock your vault for private folders and try again.';
                }
                ?>
            </div>
        <?php endif; ?>
            <?php if (empty($bkmVaultState['unlocked'])): ?>
                <?php bkm_render_vault_lock_screen($csrfToken, $bkmVaultState, 'index.php'); ?>
            <?php else: ?>
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>
        <div class="bookmarks-layout">
            <div class="bookmarks-sidebar">
                <div style="margin-bottom: 15px;">
                    <form method="GET" style="display: flex; gap: 5px;">
                        <input type="hidden" name="view" value="<?php echo sanitize($view_mode); ?>">
                        <?php if ($selected_folder_id): ?><input type="hidden" name="folder_id" value="<?php echo (int)$selected_folder_id; ?>"><?php endif; ?>
                        <input type="text" name="search" placeholder="Search..." value="<?php echo sanitize($searchRaw); ?>" style="flex: 1; padding: 8px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); border-radius: 4px;">
                        <button type="submit" class="btn btn-primary">🔍</button>
                    </form>
                </div>

                <div class="view-filters">
                    <a href="index.php?view=all" class="<?php echo $view_mode === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="index.php?view=private" class="<?php echo $view_mode === 'private' ? 'active' : ''; ?>">🔒 Private</a>
                    <a href="index.php?view=shared" class="<?php echo $view_mode === 'shared' ? 'active' : ''; ?>">🔓 Shared</a>
                </div>

                <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <a href="create.php<?php echo $selected_folder_id ? "?folder_id=$selected_folder_id" : ""; ?>" class="btn btn-primary" style="flex: 1; text-align: center;" title="Add bookmark">➕</a>
                    <a href="create_folder.php" class="btn" title="Add Folder" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border);">➕📁</a>
                </div>
                <ul class="folder-tree">
                    <li class="<?php echo ($selected_folder_id === null && $searchRaw === '') ? 'active' : ''; ?>" ondrop="drop(event)" ondragover="allowDrop(event)" data-folder-id="0">
                        <div><a href="index.php?view=<?php echo $view_mode; ?>">🏠 Root Bookmarks</a></div>
                    </li>
                    <?php echo bkm_render_folder_tree_html($conn, $folder_tree, $selected_folder_id, $company_id, 0, $user_id, $is_admin, $bkmVaultUnlocked); ?>
                </ul>
            </div>
            <div class="bookmarks-main">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; color: var(--text-primary);">
                            <?php
                            if ($searchRaw) {
                                echo "Search Results for '" . sanitize($searchRaw) . "'";
                            } elseif ($selected_folder_id) {
                                $folder_name = 'Folder';
                                foreach ($all_folders as $f) { if ($f['id'] == $selected_folder_id) { $folder_name = $f['name']; break; } }
                                echo "Folder: " . sanitize($folder_name);
                            } else {
                                echo $view_mode === 'shared' ? "Shared Bookmarks" : ($view_mode === 'private' ? "Private Bookmarks" : "All Bookmarks");
                            }
                            ?>
                        </h2>
                        <div style="display: flex; gap: 8px;">
                            <?php if ($selected_folder_id): ?>
                                <a href="edit_folder.php?id=<?php echo $selected_folder_id; ?>" class="btn btn-sm" title="Edit folder">✏️</a>
                            <?php endif; ?>
                            <div class="dropdown" style="position: relative;">
                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" onclick="this.parentNode.querySelector('.dropdown-menu').classList.toggle('show'); event.stopPropagation();">Tools ⚙️</button>
                                <div class="dropdown-menu" style="right: 0; min-width: 180px;">
                                    <a class="dropdown-item" href="list_all.php">📋 Table View</a>
                                    <a class="dropdown-item" href="import.php">📤 Import</a>
                                    <hr style="margin: 4px 0; border-top: 1px solid var(--border);">
                                    <button class="dropdown-item" onclick="exportBookmarks('xlsx', '<?php echo $selected_folder_id; ?>')">📥 Export Excel</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('csv', '<?php echo $selected_folder_id; ?>')">📥 Export CSV</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('pdf', '<?php echo $selected_folder_id; ?>')">📥 Export PDF</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('txt', '<?php echo $selected_folder_id; ?>')">📥 Export TXT</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('html', '<?php echo $selected_folder_id; ?>')">📥 Export HTML</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="view" value="<?php echo sanitize($view_mode); ?>">
                        <?php if ($selected_folder_id): ?><input type="hidden" name="folder_id" value="<?php echo (int)$selected_folder_id; ?>"><?php endif; ?>
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="bkmListSearch">Search (all fields)</label>
                            <input type="text" id="bkmListSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Search bookmarks...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="<?php echo sanitize(bkm_build_index_query(['search' => '', 'page' => 1])); ?>" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

                <?php if ($showBulkActions): ?>
                <div class="bulk-delete-bar card" style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="button" class="btn btn-sm" id="bulk-select-toggle" data-itm-bulk-select="1" title="Select All">Select All</button>
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
                    <form id="bulk-move-form" method="POST" action="index.php" style="display:none;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="action" value="move_bookmarks">
                        <label for="bulk-move-folder" style="margin:0;">Move to</label>
                        <select id="bulk-move-folder" name="target_folder_id" class="form-control" style="min-width:180px;">
                            <option value="0">Root</option>
                            <?php echo bkm_render_folder_options($folder_tree, $selected_folder_id); ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary" id="bulk-move-submit">Move to</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="bookmarks-list">
    <?php if (empty($bookmarks)): ?>
        <div class="card" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
            <p>No bookmarks found here.</p>
            <a href="create.php<?php echo $selected_folder_id ? "?folder_id=$selected_folder_id" : ""; ?>" class="btn btn-primary">➕</a>
        </div>
    <?php else: ?>

        <div class="card" style="overflow:auto;">
        <table style="width:100%; border-collapse:collapse;" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
    <tr style="background:var(--bg-secondary);">
        <?php if ($showBulkActions): ?>
        <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
        <?php endif; ?>
        <?php
        $bkmListColumns = [
            'title' => 'Title',
            'url' => 'URL',
            'notes' => 'Notes',
            'shared' => 'Visibility',
        ];
        foreach ($bkmListColumns as $colKey => $colLabel):
            $nextDir = ($sort === $colKey && $dir === 'ASC') ? 'DESC' : 'ASC';
            $sortHref = bkm_build_index_query(array_merge($bkmListQueryState, ['sort' => $colKey, 'dir' => $nextDir]));
        ?>
        <th style="padding:8px;">
            <a href="<?php echo sanitize($sortHref); ?>" style="text-decoration:none;color:inherit;">
                <?php echo sanitize($colLabel); ?>
                <?php if ($sort === $colKey): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
            </a>
        </th>
        <?php endforeach; ?>
        <th style="padding:8px;">Favicon</th>
		<th style="padding:8px; width:120px;" class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
    </tr>
</thead>


            <tbody>
                <?php foreach ($bookmarks as $b): ?>
                    <tr style="border-bottom:1px solid var(--border);">

    <?php if ($showBulkActions): ?>
    <td style="padding:8px;">
        <input type="checkbox" name="ids[]" value="<?php echo (int)$b['id']; ?>" form="bulk-delete-form">
    </td>
    <?php endif; ?>

    <!-- Title -->
    <td style="padding:8px;">
        <?php echo sanitize($b['title_display'] ?? $b['title'] ?? ''); ?>
    </td>

    <!-- URL -->
    <td style="padding:8px;">
        <?php if (!empty($b['url_locked'])): ?>
            <span style="color:var(--text-tertiary);"><?php echo sanitize($b['url_locked_label'] ?: '🔒 URL hidden'); ?></span>
        <?php else: ?>
        <a href="<?php echo sanitize($b['url_display']); ?>"
           target="_blank"
           rel="nofollow noreferrer noopener"
           style="color:var(--accent); text-decoration:none;">
            <?php echo sanitize($b['url_display']); ?>
        </a>
        <?php endif; ?>
    </td>

    <!-- Notes -->
    <td style="padding:8px;">
        <?php echo sanitize($b['notes_display'] ?? $b['notes'] ?? ''); ?>
    </td>

    <!-- Visibility -->
    <td style="padding:8px;">
        <?php if ($b['shared']): ?>
            🔓 Shared
        <?php else: ?>
            🔒 Private
        <?php endif; ?>
    </td>

    <!-- Favicon -->
    <td style="padding:8px; text-align:center;">
        <?php if (empty($b['url_locked']) && !empty($b['url_display'])): ?>
        <img src="<?php echo bkm_get_favicon_url($b['url_display']); ?>"
             alt="favicon"
             style="width:16px; height:16px; vertical-align:middle;"
             onerror="this.style.display='none';">
        <?php endif; ?>
    </td>

    <!-- Actions -->
    <td class="itm-actions-cell" data-itm-actions-origin="1" style="padding:8px;">
        <div class="itm-actions-wrap">
        <?php if (empty($b['url_locked']) && !empty($b['url_display'])): ?>
        <button class="btn btn-sm copy-btn"
                onclick="copyUrl('<?php echo addslashes($b['url_display']); ?>')"
                title="Copy URL">🗐</button>
        <?php endif; ?>

        <?php if (bkm_can_edit_bookmark($b, $user_id, $is_admin)): ?>
            <button type="button" class="btn btn-sm" onclick="itmOpenQrShareModal('index.php?ajax_action=create_share_session', <?php echo (int)$b['id']; ?>)" title="Share to device">📱</button>
            <button type="button" class="btn btn-sm" onclick="itmOpenWhatsAppShare('index.php?ajax_action=create_share_session', <?php echo (int)$b['id']; ?>, null, 'bookmark')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
            <button type="button" class="btn btn-sm" onclick="itmOpenOutlookShare('index.php?ajax_action=create_share_session', <?php echo (int)$b['id']; ?>, null, 'bookmark')" title="Share on Outlook">📩</button>
            <a href="edit.php?id=<?php echo $b['id']; ?>" class="btn btn-sm" title="Edit">✏️</a>

            <form method="POST" action="delete.php"
                  style="display:inline;"
                  onsubmit="return confirm('Delete?');">
                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                <input type="hidden" name="bulk_action" value="single_delete">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
            </form>
        <?php endif; ?>
        </div>
    </td>

</tr>

                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>
</div>

                <?php if (!empty($bookmarks) && $totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="<?php echo sanitize(bkm_build_index_query(array_merge($bkmListQueryState, ['page' => $page - 1]))); ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="<?php echo sanitize(bkm_build_index_query(array_merge($bkmListQueryState, ['page' => $page + 1]))); ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="move-folder-form" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
    <input type="hidden" name="action" value="move_folder">
    <input type="hidden" name="folder_id" id="move-folder-id">
    <input type="hidden" name="new_parent_id" id="move-new-parent-id">
    <input type="hidden" name="merge_into_folder_id" id="move-merge-into-folder-id" value="">
</form>

<div id="bkm-edit-folder-modal" class="bkm-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="bkm-edit-folder-modal-title">
    <div class="bkm-modal-card" onclick="event.stopPropagation();">
        <h3 id="bkm-edit-folder-modal-title" title="Edit folder">✏️</h3>
        <form id="bkm-rename-folder-form" method="POST" action="index.php">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="action" value="rename_folder">
            <input type="hidden" name="folder_id" id="bkm-rename-folder-id" value="">
            <input type="hidden" name="redirect_view" value="<?php echo sanitize($view_mode); ?>">
            <div class="form-group">
                <label for="bkm-rename-folder-name">Folder Name</label>
                <input type="text" name="name" id="bkm-rename-folder-name" required autocomplete="off">
            </div>
            <div class="form-actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn" id="bkm-rename-folder-cancel" title="Cancel">🔙</button>
                <button type="submit" class="btn btn-primary" title="Save">💾</button>
            </div>
        </form>
    </div>
</div>

<script src="../../js/theme.js"></script>
<script src="./export.js"></script>
<script src="../../js/bulk-delete-selection.js"></script>
<script>
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu').forEach(function(el) {
        el.classList.remove('show');
    });
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.delete-folder-btn');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const id = btn.dataset.id;
    const hasBookmarks = btn.dataset.hasBookmarks === '1';
    let deleteContents = '0';

    if (hasBookmarks) {
        if (!confirm('This action will delete all bookmarks in the folder. Continue?')) {
            return;
        }
        if (confirm('Delete all bookmarks? Click OK to delete everything, or Cancel to move bookmarks to the Root.')) {
            deleteContents = '1';
        } else {
            deleteContents = '0';
        }
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete_folder.php';

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo sanitize($csrfToken); ?>';

    const delContentInput = document.createElement('input');
    delContentInput.type = 'hidden';
    delContentInput.name = 'delete_contents';
    delContentInput.value = deleteContents;

    form.appendChild(idInput);
    form.appendChild(csrfInput);
    form.appendChild(delContentInput);
    document.body.appendChild(form);
    form.submit();
});

const bkmEditFolderModal = document.getElementById('bkm-edit-folder-modal');
const bkmRenameFolderNameInput = document.getElementById('bkm-rename-folder-name');
const bkmRenameFolderIdInput = document.getElementById('bkm-rename-folder-id');

function bkmCloseEditFolderModal() {
    if (!bkmEditFolderModal) {
        return;
    }
    bkmEditFolderModal.classList.remove('is-open');
}

function bkmOpenEditFolderModal(folderId, folderName) {
    if (!bkmEditFolderModal || !bkmRenameFolderNameInput || !bkmRenameFolderIdInput) {
        return;
    }
    bkmRenameFolderIdInput.value = folderId;
    bkmRenameFolderNameInput.value = folderName;
    bkmEditFolderModal.classList.add('is-open');
    bkmRenameFolderNameInput.focus();
    bkmRenameFolderNameInput.select();
}

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.edit-folder-btn');
    if (!btn) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    bkmOpenEditFolderModal(btn.dataset.id, btn.dataset.name || '');
});

if (bkmEditFolderModal) {
    bkmEditFolderModal.addEventListener('click', function() {
        bkmCloseEditFolderModal();
    });
}

const bkmRenameFolderCancel = document.getElementById('bkm-rename-folder-cancel');
if (bkmRenameFolderCancel) {
    bkmRenameFolderCancel.addEventListener('click', function(e) {
        e.preventDefault();
        bkmCloseEditFolderModal();
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && bkmEditFolderModal && bkmEditFolderModal.classList.contains('is-open')) {
        bkmCloseEditFolderModal();
    }
});

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

function copyUrl(text) {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    alert('URL copied to clipboard!');
}

function allowDrop(ev) {
    ev.preventDefault();
    const target = ev.target.closest('[data-folder-id]');
    if (target) {
        target.setAttribute('drag-over', 'true');
    }
}

function drag(ev) {
    const folderId = ev.target.closest('[data-folder-id]').getAttribute('data-folder-id');
    ev.dataTransfer.setData("folder_id", folderId);
}

function bkmNormalizedFolderName(name) {
    return (name || '').trim().toLowerCase();
}

function bkmGetFolderTreeItem(folderId) {
    return document.querySelector('.folder-tree [data-folder-id="' + folderId + '"]');
}

function bkmGetFolderName(folderId) {
    const item = bkmGetFolderTreeItem(folderId);
    if (!item) {
        return '';
    }
    return item.getAttribute('data-folder-name') || '';
}

function bkmListChildFolderItems(parentFolderId) {
    if (parentFolderId === '0' || parentFolderId === 0) {
        const tree = document.querySelector('.folder-tree');
        if (!tree) {
            return [];
        }
        return Array.from(tree.querySelectorAll(':scope > li.itm-folder-tree-item[data-folder-id]'));
    }
    const parent = bkmGetFolderTreeItem(parentFolderId);
    if (!parent) {
        return [];
    }
    const childList = parent.querySelector(':scope > ul.itm-folder-tree-children');
    if (!childList) {
        return [];
    }
    return Array.from(childList.querySelectorAll(':scope > li.itm-folder-tree-item[data-folder-id]'));
}

function bkmFindSiblingFolderWithName(parentFolderId, sourceFolderId, sourceName) {
    const normalized = bkmNormalizedFolderName(sourceName);
    if (!normalized) {
        return null;
    }
    const children = bkmListChildFolderItems(parentFolderId);
    for (let i = 0; i < children.length; i++) {
        const child = children[i];
        const childId = child.getAttribute('data-folder-id');
        if (childId === sourceFolderId) {
            continue;
        }
        if (bkmNormalizedFolderName(bkmGetFolderName(childId)) === normalized) {
            return childId;
        }
    }
    return null;
}

function bkmIsDescendantFolder(folderId, ancestorId) {
    if (!folderId || !ancestorId || folderId === '0' || ancestorId === '0') {
        return false;
    }
    if (folderId === ancestorId) {
        return true;
    }
    let current = ancestorId;
    const seen = {};
    while (current && current !== '0') {
        if (current === folderId) {
            return true;
        }
        if (seen[current]) {
            break;
        }
        seen[current] = true;
        const item = bkmGetFolderTreeItem(current);
        if (!item) {
            break;
        }
        const parentList = item.parentElement;
        if (!parentList) {
            break;
        }
        const parentItem = parentList.closest('li.itm-folder-tree-item[data-folder-id]');
        current = parentItem ? parentItem.getAttribute('data-folder-id') : '0';
    }
    return false;
}

function drop(ev) {
    ev.preventDefault();
    const target = ev.target.closest('[data-folder-id]');
    if (target) {
        target.removeAttribute('drag-over');
        const folderId = ev.dataTransfer.getData('folder_id');
        const newParentId = target.getAttribute('data-folder-id');

        if (folderId !== newParentId) {
            if (bkmIsDescendantFolder(folderId, newParentId)) {
                alert('Cannot move a folder into itself or one of its subfolders.');
                return;
            }

            const sourceName = bkmGetFolderName(folderId);
            const siblingId = bkmFindSiblingFolderWithName(newParentId, folderId, sourceName);
            document.getElementById('move-folder-id').value = folderId;
            document.getElementById('move-new-parent-id').value = newParentId === '0' ? '' : newParentId;
            document.getElementById('move-merge-into-folder-id').value = '';

            if (siblingId) {
                const merge = confirm(
                    'A folder named "' + sourceName + '" already exists in this location.\n\nMerge the moved folder into it?\n\nOK = merge contents\nCancel = keep both folders with the same name'
                );
                if (merge) {
                    document.getElementById('move-merge-into-folder-id').value = siblingId;
                }
            }

            document.getElementById('move-folder-form').submit();
        }
    }
}

document.addEventListener('dragleave', function(ev) {
    const target = ev.target.closest('[data-folder-id]');
    if (target) {
        target.removeAttribute('drag-over');
    }
});

function toggleExportMenu(e) {
    const dropdown = document.getElementById('export-dropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    e.stopPropagation();
}

document.addEventListener('click', function() {
    const dropdown = document.getElementById('export-dropdown');
    if (dropdown) dropdown.style.display = 'none';
});
</script>
<?php require_once ROOT_PATH . 'includes/itm_qr_share_modal.php'; ?>
<script>window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>

</body>
</html>
