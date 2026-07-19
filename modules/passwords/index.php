<?php

$crud_table = 'password_entries';
$crud_title = 'Passwords';
$crud_action = $crud_action ?? 'index';

require_once '../../config/config.php';

// Auth Check (Custom for Passwords Module)
if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    die();
}

$csrfToken = itm_get_csrf_token();
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) { die('Connection failed: ' . mysqli_connect_error()); }
$user_id = (int)$_SESSION['employee_id'];

// Fetch user's vault status
$user_stmt = mysqli_prepare($conn, 'SELECT vault_key_hash FROM employees WHERE id = ?');
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);

$has_vault_configured = !empty($user_data['vault_key_hash']);

// Handle Vault Unlock if master_key is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_key'])) {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $master_key = $_POST['master_key'];
    
    if (!$has_vault_configured) {
        header('Location: ../../user-config.php#vault-security');
        die();
    }

    if (password_verify($master_key, $user_data['vault_key_hash'])) {
        $_SESSION['vault_key'] = hash('sha256', (string)$master_key);
        header('Location: index.php');
        die();
    } else {
        $error_message = 'Incorrect Master Key.';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'lock') {
    unset($_SESSION['vault_key']);
    header('Location: index.php');
    die();
}

// Module Configuration
$module_title = 'Passwords';
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$searchRaw = trim((string)($_GET['search'] ?? ''));
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}

require_once __DIR__ . '/passwords_list_helpers.php';

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$sortableColumns = pwd_list_sortable_columns();
$sort = (string)($_GET['sort'] ?? 'account');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'account';
}
$sortSql = pwd_resolve_list_sort_sql($sort);
$page = max(1, (int)($_GET['page'] ?? 1));

$searchConditions = [];
if ($searchRaw !== '') {
    $searchPattern = '%' . $searchRaw . '%';
    $searchConditions[] = 'account LIKE ?';
    $searchConditions[] = 'login_name LIKE ?';
    $searchConditions[] = 'website LIKE ?';
    $searchConditions[] = 'comments LIKE ?';
}

$entries = [];
$totalRows = 0;
$totalPages = 1;
$offset = 0;
$listOrderClause = 'ORDER BY ' . $sortSql . ' ' . $dir;
$listLimitClause = 'LIMIT ' . (int)$offset . ', ' . (int)$perPage;

if (!empty($_SESSION['vault_key'])) {
    $listResult = pwd_query_entries_for_list($conn, [
        'employee_id' => $user_id,
        'folder_id' => $current_folder_id,
        'search' => $searchRaw,
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'per_page' => $perPage,
        'vault_key' => (string)$_SESSION['vault_key'],
    ]);
    $entries = $listResult['rows'];
    $totalRows = (int)$listResult['totalRows'];
    $totalPages = (int)$listResult['totalPages'];
    $page = (int)$listResult['page'];
    $offset = (int)$listResult['offset'];
    $listLimitClause = 'LIMIT ' . $offset . ', ' . (int)$perPage;
}

$showBulkActions = ($totalRows >= $perPage);

$pwdListQueryState = [
    'folder_id' => $current_folder_id > 0 ? $current_folder_id : null,
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($crud_action ?? 'index') === 'delete') {
    itm_require_post_csrf();
    $bulkAction = (string)($_POST['bulk_action'] ?? '');

    if ($bulkAction === 'clear_table') {
        $clearStmt = mysqli_prepare($conn, 'DELETE FROM password_entries WHERE employee_id = ?');
        if ($clearStmt) {
            mysqli_stmt_bind_param($clearStmt, 'i', $user_id);
            mysqli_stmt_execute($clearStmt);
            mysqli_stmt_close($clearStmt);
        }
        header('Location: index.php?msg=deleted');
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM password_entries WHERE id = ? AND employee_id = ?');
        if ($deleteStmt) {
            foreach ($ids as $entryId) {
                if ($entryId <= 0) {
                    continue;
                }
                mysqli_stmt_bind_param($deleteStmt, 'ii', $entryId, $user_id);
                mysqli_stmt_execute($deleteStmt);
            }
            mysqli_stmt_close($deleteStmt);
        }
        header('Location: index.php?msg=deleted');
        exit;
    }

    header('Location: index.php');
    exit;
}

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
    $crud_title = 'Passwords';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <!-- Bootstrap CSS removed to avoid theme conflicts -->
    <style>        .dropdown-item, .folder-item a { text-decoration: none !important; }
        .modal-backdrop { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: #000; opacity: 0.5; z-index: 1040; display: none; }
        .modal-backdrop.show { display: block; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; z-index: 1050; }
        .modal.show { display: block; }
        .modal-dialog { position: relative; width: auto; margin: 0.5rem; pointer-events: none; }
        @media (min-width: 576px) { .modal-dialog { max-width: 500px; margin: 1.75rem auto; } .modal-dialog.modal-lg { max-width: 800px; } }
        .modal.fade .modal-dialog { transform: translate(0, -50px); transition: transform 0.3s ease-out; }
        .modal.show .modal-dialog { transform: none; }
        .modal-content { position: relative; display: flex; flex-direction: column; width: 100%; pointer-events: auto; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 0.3rem; color: var(--text-primary); margin-top: 30px; outline: 0; }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border); border-top-left-radius: 0.3rem; border-top-right-radius: 0.3rem; }
        .modal-body { position: relative; flex: 1 1 auto; padding: 1rem; }
        .modal-footer { display: flex; align-items: center; justify-content: flex-end; padding: 1rem; border-top: 1px solid var(--border); border-bottom-right-radius: 0.3rem; border-bottom-left-radius: 0.3rem; gap: 8px; }
        .close { padding: 1rem; margin: -1rem -1rem -1rem auto; background-color: transparent; border: 0; font-size: 1.5rem; font-weight: 700; line-height: 1; color: var(--text-primary); text-shadow: 0 1px 0 #fff; opacity: .5; cursor: pointer; }
        .close:hover { opacity: .75; }
        .dropdown-menu { display: none; position: absolute; background: var(--bg-primary); border: 1px solid var(--border); z-index: 1000; }
        .dropdown-menu.show { display: block; }
        .dropdown-item { color: var(--text-primary); }
        .dropdown-item:hover { background: var(--bg-tertiary); }
        .passwords-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 992px) {
            .passwords-layout { grid-template-columns: 1fr; }
        }
        .folder-tree-container {
            max-height: 450px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-secondary);
            margin-bottom: 12px;
        }
        .folder-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            border-radius: 4px;
            margin-bottom: 2px;
        }
        .folder-item:hover { background: var(--bg-tertiary); }
        .folder-item.active { background: var(--accent); color: #fff; }
        .folder-item.active a { color: #fff; font-weight: bold; }
        .folder-item a { text-decoration: none; color: inherit; flex: 1; }
        .pwd-folder-tree { list-style: none; padding: 0; margin: 0; }
        .pwd-folder-tree-children { list-style: none; padding: 0; margin: 0 0 0 12px; }
        .pwd-folder-tree-item[drag-over="true"] > .folder-item {
            background: rgba(9, 105, 218, 0.1) !important;
            border: 1px dashed var(--accent);
        }
        .strength-meter {
            height: 6px;
            background: var(--bg-tertiary);
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }
        .strength-bar { height: 100%; width: 0; transition: width 0.3s, background-color 0.3s; }
        .pwd-actions-wrap .btn img { display: block; flex-shrink: 0; }
        .pwd-inline-field {
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 4px;
            max-width: 100%;
        }
        .pwd-inline-field input.form-control {
            flex: 1 1 auto;
            min-width: 72px;
            max-width: 180px;
        }
        .pwd-inline-field .btn {
            flex-shrink: 0;
            white-space: nowrap;
        }
        .pwd-search-row {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            width: 100%;
        }
        .pwd-search-row input.form-control {
            flex: 1 1 auto;
            min-width: 0;
        }
        .pwd-search-row .btn {
            flex-shrink: 0;
            white-space: nowrap;
        }
        .pwd-toolbar-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: nowrap;
        }
        .pwd-toolbar-row .pwd-search-wrap {
            flex: 1 1 auto;
            min-width: 0;
        }
        .pwd-toolbar-tools {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .pwd-toolbar-row { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">

            <?php if (empty($_SESSION['vault_key'])): ?>
                <div style="max-width: 400px; margin: 80px auto; text-align: center;" class="card">
                    <div style="font-size: 48px; margin-bottom: 16px;">🔒</div>
                    <h2>Vault Locked</h2>
                    <p>Enter your master key to access your passwords.</p>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <div class="form-group">
                            <input type="password" name="master_key" class="form-control" placeholder="Master Key" required autofocus style="text-align: center;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Unlock Vault</button>
                    </form>
                    <div style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 15px; display: flex; flex-direction: column; gap: 8px;">
                        <?php if (!$has_vault_configured): ?>
                            <a href="../../user-config.php#vault-security" class="btn btn-success btn-sm">Create Vault Key</a>
                        <?php endif; ?>
                        <a href="../../user-config.php#vault-security" class="btn btn-sm">Change Master Key</a>
                    </div>
                </div>
            <?php else: ?>
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <div style="display:flex;gap:8px;">
                            <a href="#" class="btn btn-primary itm-list-new-button" title="Create" onclick="openEntryModal(); return false;">➕</a>
                        </div>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <div style="display:flex;gap:8px;">
                            <a href="#" class="btn btn-primary itm-list-new-button" title="Create" onclick="openEntryModal(); return false;">➕</a>
                        </div>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
                <div class="passwords-layout">
                    <div class="side-panel">
                        <div class="card">
                            <h3>🔐 Generator</h3>
                            <div style="display: flex; gap: 4px; margin-bottom: 10px;">
                                <input type="text" id="gen-password" class="form-control" style="flex: 1; font-family: monospace;">
                                <button class="btn btn-sm" type="button" onclick="copyToClipboard('gen-password')" title="Copy">🗐</button>
                                <button class="btn btn-sm" type="button" onclick="generatePassword()" title="Regenerate">🔄</button>
                            </div>
                            <div class="strength-meter"><div id="strength-bar" class="strength-bar"></div></div>
                            <div class="form-group">
                                <label style="display: flex; justify-content: space-between;"><span>Length</span><strong id="length-val">12</strong></label>
                                <input type="range" class="form-control-range" id="gen-length" min="4" max="50" value="12" oninput="document.getElementById('length-val').innerText = this.value; generatePassword();">
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <label class="itm-checkbox-control"><input type="checkbox" id="gen-upper" checked onchange="generatePassword()"><span>Uppercase</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" id="gen-lower" checked onchange="generatePassword()"><span>Lowercase</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" id="gen-numbers" checked onchange="generatePassword()"><span>Numbers</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" id="gen-symbols" checked onchange="generatePassword()"><span>Symbols</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" id="gen-exclude-similar" onchange="generatePassword()"><span>Exclude Similar</span></label>
                            </div>
                            <button class="btn btn-primary" style="width: 100%; margin-top: 15px;" onclick="saveGeneratedPassword()">Save to Vault</button>
                        </div>
                        <div class="card" style="margin-top: 20px;">
                            <h3>📁 Folders</h3>
                            <div id="folder-tree" class="folder-tree-container"><div class="text-muted">Loading...</div></div>
                            <button class="btn btn-sm btn-primary" style="width: 100%;" onclick="openFolderModal(0)" title="New folder">➕</button>
                        </div>
                    </div>
                    <div class="main-panel">
                        <div class="card">
                            <?php if ($showBulkActions): ?>
                            <div style="margin-bottom:16px;">
                                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                                    <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                                </form>
                            </div>
                            <?php endif; ?>
                            <div class="pwd-toolbar-row">
                                <div class="pwd-search-wrap">
                                    <form method="GET" action="index.php" class="pwd-search-row">
                                        <?php if ($current_folder_id > 0): ?>
                                            <input type="hidden" name="folder_id" value="<?php echo (int)$current_folder_id; ?>">
                                        <?php endif; ?>
                                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                                        <input type="text" name="search" id="entry-search" class="form-control" placeholder="Search entries..." value="<?php echo sanitize($searchRaw); ?>">
                                        <button type="submit" class="btn btn-primary" title="Search">🔍</button>
                                        <a href="<?php echo sanitize(pwd_build_list_url(['folder_id' => $current_folder_id > 0 ? $current_folder_id : null])); ?>" class="btn" title="Clear">🔙</a>
                                    </form>
                                </div>
                                <div class="pwd-toolbar-tools">
                                    <div class="btn-group">
                                        <button type="button" class="btn dropdown-toggle" onclick="$(this).next('.dropdown-menu').toggleClass('show'); event.stopPropagation();">Tools ⚙️</button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#" onclick="openImportModal(); $('.dropdown-menu').removeClass('show'); return false;">📥 Import CSV</a><br />
                                            <a class="dropdown-item" href="#" onclick="openImportExcelModal(); $('.dropdown-menu').removeClass('show'); return false;">📥 Import Excel</a><br />
                                        <!--    <div class="dropdown-divider"></div> -->
                                            <a class="dropdown-item" href="#" onclick="exportVault('xlsx'); $('.dropdown-menu').removeClass('show'); return false;">📊 Export XLSX</a><br />
                                            <a class="dropdown-item" href="#" onclick="exportVault('xlsx'); $('.dropdown-menu').removeClass('show'); return false;">📗 Export Excel</a><br />
                                            <a class="dropdown-item" href="#" onclick="exportVault('csv'); $('.dropdown-menu').removeClass('show'); return false;">📄 Export CSV</a><br />
                                            <a class="dropdown-item" href="#" onclick="exportVault('pdf'); $('.dropdown-menu').removeClass('show'); return false;">📕 Export PDF</a><br />
                                            <a class="dropdown-item" href="#" onclick="exportVault('txt'); $('.dropdown-menu').removeClass('show'); return false;">📝 Export TXT</a><br />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                                    <thead>
                                        <tr>
                                            <?php if ($showBulkActions): ?>
                                                <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                                            <?php endif; ?>
                                            <th class="itm-actions-cell" data-itm-actions-origin="1" style="min-width: 220px; text-align: center;">Actions</th>
                                            <?php
                                            $pwdListColumns = [
                                                'account' => 'Account',
                                                'login_name' => 'Login Name',
                                            ];
                                            foreach ($pwdListColumns as $colKey => $colLabel):
                                                $nextDir = ($sort === $colKey && $dir === 'ASC') ? 'DESC' : 'ASC';
                                                $sortHref = pwd_build_list_url(array_merge($pwdListQueryState, ['sort' => $colKey, 'dir' => $nextDir, 'page' => 1]));
                                            ?>
                                            <th>
                                                <a href="<?php echo sanitize($sortHref); ?>" style="text-decoration:none;color:inherit;">
                                                    <?php echo sanitize($colLabel); ?>
                                                    <?php if ($sort === $colKey): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                                </a>
                                            </th>
                                            <?php endforeach; ?>
                                            <th>Password</th>
                                            <?php
                                            $colKey = 'website';
                                            $colLabel = 'Website';
                                            $nextDir = ($sort === $colKey && $dir === 'ASC') ? 'DESC' : 'ASC';
                                            $sortHref = pwd_build_list_url(array_merge($pwdListQueryState, ['sort' => $colKey, 'dir' => $nextDir, 'page' => 1]));
                                            ?>
                                            <th>
                                                <a href="<?php echo sanitize($sortHref); ?>" style="text-decoration:none;color:inherit;">
                                                    <?php echo sanitize($colLabel); ?>
                                                    <?php if ($sort === $colKey): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                                </a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="entries-body">
                                        <?php if (empty($entries)): ?>
                                            <tr><td colspan="<?php echo $showBulkActions ? 6 : 5; ?>" class="text-center" style="padding: 40px;">No entries found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($entries as $entryRow):
                                                $entryId = (int)$entryRow['id'];
                                                $entryAccount = (string)($entryRow['account'] ?? '');
                                                $entryLogin = (string)($entryRow['login_name'] ?? '');
                                                $entryPassword = (string)($entryRow['password_plain'] ?? '');
                                                $entryWebsite = (string)($entryRow['website'] ?? '');
                                                $websiteLabel = $entryWebsite !== '' ? preg_replace('#^https?://#i', '', $entryWebsite) : '';
                                            ?>
                                            <tr>
                                                <?php if ($showBulkActions): ?>
                                                    <td><input type="checkbox" name="ids[]" value="<?php echo $entryId; ?>" form="bulk-delete-form"></td>
                                                <?php endif; ?>
                                                <td class="itm-actions-cell" data-itm-actions-origin="1" style="text-align: center;">
                                                    <div class="itm-actions-wrap pwd-actions-wrap">
                                                        <button class="btn btn-sm" type="button" onclick="itmOpenQrShareModal('ajax_handler.php', <?php echo $entryId; ?>, { action: 'create_share_session' })" title="Share to device">📱</button>
                                                        <button class="btn btn-sm" type="button" onclick="itmOpenWhatsAppShare('ajax_handler.php', <?php echo $entryId; ?>, { action: 'create_share_session' }, 'password')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
                                                        <button class="btn btn-sm" type="button" onclick="itmOpenOutlookShare('ajax_handler.php', <?php echo $entryId; ?>, { action: 'create_share_session' }, 'password')" title="Share on Outlook">📨</button>
                                                        <a class="btn btn-sm" href="view.php?id=<?php echo $entryId; ?>" title="View">🔎</a>
                                                        <button class="btn btn-sm" type="button" onclick="openEntryModal(<?php echo $entryId; ?>)" title="Edit">✏️</button>
                                                        <button class="btn btn-sm btn-danger" type="button" onclick="deleteEntry(<?php echo $entryId; ?>)" title="Delete">🗑️</button>
                                                    </div>
                                                </td>
                                                <td><?php echo sanitize($entryAccount); ?> <button class="btn btn-link btn-sm p-0" type="button" onclick="copyText(<?php echo json_encode($entryAccount, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)" title="Copy">🗐</button></td>
                                                <td><?php echo sanitize($entryLogin); ?> <button class="btn btn-link btn-sm p-0" type="button" onclick="copyText(<?php echo json_encode($entryLogin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)" title="Copy">🗐</button></td>
                                                <td>
                                                    <div class="pwd-inline-field">
                                                        <input type="password" value="<?php echo sanitize($entryPassword); ?>" class="form-control" readonly id="pwd-<?php echo $entryId; ?>">
                                                        <button class="btn btn-sm" type="button" onclick="togglePasswordVisibility('pwd-<?php echo $entryId; ?>')" title="Toggle visibility">👁️</button>
                                                        <button class="btn btn-sm" type="button" onclick="copyText(<?php echo json_encode($entryPassword, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)" title="Copy">🗐</button>
                                                    </div>
                                                </td>
                                                <td><?php if ($entryWebsite !== ''): ?><a href="<?php echo sanitize($entryWebsite); ?>" target="_blank" rel="nofollow noreferrer noopener" style="text-decoration:none !important;"><?php echo sanitize($websiteLabel); ?></a><?php else: ?>—<?php endif; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($totalPages > 1): ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;gap:8px;flex-wrap:wrap;">
                                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <?php if ($page > 1): ?>
                                            <a class="btn btn-sm" href="<?php echo sanitize(pwd_build_list_url(array_merge($pwdListQueryState, ['page' => $page - 1]))); ?>" title="◀️ Previous">Previous</a>
                                        <?php endif; ?>
                                        <span class="btn btn-sm" style="pointer-events:none;"><?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span>
                                        <?php if ($page < $totalPages): ?>
                                            <a class="btn btn-sm" href="<?php echo sanitize(pwd_build_list_url(array_merge($pwdListQueryState, ['page' => $page + 1]))); ?>" title="▶️ Next">Next</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Password Entry Modal -->
<div class="modal fade" id="entryModal" tabindex="-1" role="dialog" aria-labelledby="entryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entryModalLabel">Add Password</h5>
                <button type="button" class="close" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <!-- 
                <button type="button" class="close" onclick=".removeClass('show').hide(); .remove(); .removeClass('modal-open');" aria-label="Close"><span aria-hidden="true">&times;</span></button>                          
                <button type="button" class="close" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                -->
            
            </div>
            <form id="entryForm">
                <input type="hidden" name="id" id="entry-id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Account</label><input type="text" name="account" id="entry-account" class="form-control" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Folder</label><select name="folder_id" id="entry-folder_id" class="form-control"></select></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Login Name</label><input type="text" name="login_name" id="entry-login_name" class="form-control"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Password</label><div class="pwd-inline-field"><input type="password" name="password" id="entry-password" class="form-control" required style="max-width:none;"><button class="btn btn-sm" type="button" onclick="togglePasswordVisibility('entry-password')" title="Toggle visibility">👁️</button><button class="btn btn-sm" type="button" onclick="copyText(document.getElementById('entry-password').value)" title="Copy">🗐</button></div></div></div>
                    </div>
                    <div class="form-group"><label>Website</label><input type="url" name="website" id="entry-website" class="form-control" placeholder="https://"></div>
                    <div class="form-group"><label>Comments</label><textarea name="comments" id="entry-comments" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" title="Cancel">🔙</button><button type="submit" class="btn btn-primary" title="Save">💾</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Folder Modal -->
<div class="modal fade" id="folderModal" tabindex="-1" role="dialog" aria-labelledby="folderModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="folderModalLabel">New Folder</h5>
                <button type="button" class="close" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="folderForm">
                <input type="hidden" name="id" id="folder-id">
                <div class="modal-body">
                    <div class="form-group"><label>Folder Name</label><input type="text" name="name" id="folder-name" class="form-control" required></div>
                    <div class="form-group"><label>Parent Folder</label><select name="parent_id" id="folder-parent_id" class="form-control"><option value="0">-- Root --</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" title="Cancel">🔙</button><button type="submit" class="btn btn-primary" title="Save">💾</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Passwords</h5>
                <button type="button" class="close" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group"><label>CSV File</label><input type="file" name="csv_file" class="form-control-file" accept=".csv" required></div>
                    <div class="form-group"><label>Target Folder</label><select name="target_folder_id" id="import-folder_id" class="form-control"><option value="0">-- Root --</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');">Close</button><button type="submit" class="btn btn-primary">Import</button></div>
            </form>
        </div>
    </div>
</div>
<!-- Import Excel Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-labelledby="importExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importExcelModalLabel">Import Passwords (Excel)</h5>
                <button type="button" class="close" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label>Excel File (.xlsx, .xls)</label><input type="file" id="excel-file-input" class="form-control-file" accept=".xlsx, .xls" required></div>
                <div class="form-group"><label>Target Folder</label><select id="import-excel-folder_id" class="form-control"><option value="0">-- Root --</option></select></div>
                <p class="text-muted small">Excel should have headers: Account, Login Name, Password, Website, Comments</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="$(this).closest('.modal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');">Close</button><button type="button" class="btn btn-primary" onclick="handleExcelImport()">Import</button></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
const pwdShowBulkActions = <?php echo $showBulkActions ? 'true' : 'false'; ?>;
const currentFolderId = <?php echo (int)$current_folder_id; ?>;
let pwdFoldersData = [];

async function apiCall(action, data = {}) {
    data.action = action;
    data.csrf_token = CSRF_TOKEN;
    const params = new URLSearchParams();
    for (const key in data) params.append(key, data[key]);
    try {
        const r = await fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });
        return await r.json();
    } catch(e) { return {ok: false, message: 'Server error'}; }
}

function generatePassword() {
    const length = parseInt(document.getElementById('gen-length').value) || 12;
    const upper = document.getElementById('gen-upper').checked;
    const lower = document.getElementById('gen-lower').checked;
    const numbers = document.getElementById('gen-numbers').checked;
    const symbols = document.getElementById('gen-symbols').checked;
    const excludeSimilar = document.getElementById('gen-exclude-similar').checked;
    let chars = '';
    if (upper) chars += 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    if (lower) chars += 'abcdefghijkmnopqrstuvwxyz';
    if (numbers) chars += '23456789';
    if (symbols) chars += '!@#$%^&*';
    if (!excludeSimilar) {
        if (upper) chars += 'IOL';
        if (lower) chars += 'ilo';
        if (numbers) chars += '01';
    }
    if (chars.length === 0) return;
    let password = '';
    const array = new Uint32Array(length);
    window.crypto.getRandomValues(array);
    for (let i = 0; i < length; i++) password += chars.charAt(array[i] % chars.length);
    document.getElementById('gen-password').value = password;
    const lengthVal = document.getElementById('length-val');
    if (lengthVal) {
        lengthVal.textContent = String(password.length);
    }
    updateStrengthMeter(password);
}

function syncGeneratorFromManualPassword() {
    const input = document.getElementById('gen-password');
    const lengthSlider = document.getElementById('gen-length');
    const lengthVal = document.getElementById('length-val');
    if (!input) {
        return;
    }
    const password = input.value || '';
    const len = password.length;
    if (lengthVal) {
        lengthVal.textContent = String(len);
    }
    if (lengthSlider && len > 0) {
        lengthSlider.value = String(Math.max(4, Math.min(50, len)));
    }
    updateStrengthMeter(password);
}

function updateStrengthMeter(password) {
    let s = 0;
    if (password.length > 8) s += 20;
    if (password.length > 12) s += 20;
    if (/[A-Z]/.test(password)) s += 20;
    if (/[0-9]/.test(password)) s += 20;
    if (/[^A-Za-z0-9]/.test(password)) s += 20;
    const bar = document.getElementById('strength-bar');
    if (bar) {
        bar.style.width = s + '%';
        bar.style.backgroundColor = s < 40 ? '#da3633' : (s < 80 ? '#d1540d' : '#1a7f37');
    }
}

function copyToClipboard(id) {
    const el = document.getElementById(id);
    if (el) { el.select(); document.execCommand('copy'); alert('Copied!'); }
}

function copyText(text) {
    const el = document.createElement('textarea');
    el.value = text; document.body.appendChild(el); el.select(); document.execCommand('copy'); document.body.removeChild(el);
    alert('Copied!');
}

function togglePasswordVisibility(id, forceShow = false) {
    const el = document.getElementById(id);
    if (el) el.type = (forceShow || el.type === 'password') ? 'text' : 'password';
}

function saveGeneratedPassword() {
    const pwd = document.getElementById('gen-password').value;
    if (pwd) {
        openEntryModal(0);
        setTimeout(() => {
            const el = document.getElementById('entry-password');
            if (el) { el.value = pwd; togglePasswordVisibility('entry-password', true); }
        }, 200);
    } else alert('Generate a password first');
}

function loadFolderTree() {
    apiCall('list_folders').then(data => {
        pwdFoldersData = Array.isArray(data) ? data : [];
        const tree = document.getElementById('folder-tree');
        if (!tree) return;
        const selectEntry = document.getElementById('entry-folder_id');
        const selectFolder = document.getElementById('folder-parent_id');
        const selectImport = document.getElementById('import-folder_id');
        const selectImportExcel = document.getElementById('import-excel-folder_id');
        let optionsHtml = '<option value="0">-- Root --</option>';

        const buildOptions = (parentId, level = 0) => {
            const children = pwdFoldersData.filter(f => (f.parent_id == parentId) || (parentId === 0 && !f.parent_id));
            children.forEach(f => {
                optionsHtml += `<option value="${f.id}">${'&nbsp;'.repeat(level * 2)}${sanitizeHtml(f.name)}</option>`;
                buildOptions(f.id, level + 1);
            });
        };

        const renderBranch = (parentId) => {
            const children = pwdFoldersData.filter(f => (f.parent_id == parentId) || (parentId === 0 && !f.parent_id));
            if (!children.length) {
                return '';
            }
            let html = '<ul class="pwd-folder-tree-children">';
            children.forEach(f => {
                const isActive = parseInt(f.id, 10) === parseInt(currentFolderId, 10);
                const nameAttr = escapeHtmlAttr(f.name || '');
                html += `<li class="pwd-folder-tree-item" data-folder-id="${f.id}" data-folder-name="${nameAttr}" draggable="true" ondragstart="pwdFolderDrag(event)" ondrop="pwdFolderDrop(event)" ondragover="pwdFolderAllowDrop(event)">
                    <div class="folder-item ${isActive ? 'active' : ''}">
                        <a href="#" onclick="selectFolder(${f.id}); return false;">📁 ${sanitizeHtml(f.name)}</a>
                        <div>
                            <button class="btn btn-link btn-sm p-0" type="button" onclick="openFolderModal(${f.id}, '${addslashes(f.name)}', ${f.parent_id || 0})" title="Edit">✏️</button>
                            <button class="btn btn-link btn-sm p-0 text-danger" type="button" onclick="deleteFolder(${f.id})" title="Delete">🗑️</button>
                        </div>
                    </div>
                    ${renderBranch(f.id)}
                </li>`;
            });
            html += '</ul>';
            return html;
        };

        buildOptions(0);
        const rootActive = parseInt(currentFolderId, 10) === 0;
        let treeHtml = `<ul class="pwd-folder-tree">
            <li class="pwd-folder-tree-item pwd-folder-tree-root" data-folder-id="0" data-folder-name="" ondrop="pwdFolderDrop(event)" ondragover="pwdFolderAllowDrop(event)">
                <div class="folder-item ${rootActive ? 'active' : ''}">
                    <a href="#" onclick="selectFolder(0); return false;">📁 All entries</a>
                </div>
                ${renderBranch(0)}
            </li>
        </ul>`;

        tree.innerHTML = pwdFoldersData.length ? treeHtml : '<div class="text-muted text-center">No folders.</div>';
        if (selectEntry) selectEntry.innerHTML = optionsHtml;
        if (selectFolder) selectFolder.innerHTML = optionsHtml;
        if (selectImport) selectImport.innerHTML = optionsHtml;
        if (selectImportExcel) selectImportExcel.innerHTML = optionsHtml;
    });
}

function escapeHtmlAttr(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function pwdNormalizedFolderName(name) {
    return (name || '').trim().toLowerCase();
}

function pwdGetFolderName(folderId) {
    if (folderId === 0 || folderId === '0') {
        return '';
    }
    const row = pwdFoldersData.find(f => String(f.id) === String(folderId));
    return row ? (row.name || '') : '';
}

function pwdFindSiblingFolderWithName(parentFolderId, sourceFolderId, sourceName) {
    const normalized = pwdNormalizedFolderName(sourceName);
    if (!normalized) {
        return null;
    }
    for (let i = 0; i < pwdFoldersData.length; i++) {
        const row = pwdFoldersData[i];
        if (String(row.id) === String(sourceFolderId)) {
            continue;
        }
        const parentId = row.parent_id ? parseInt(row.parent_id, 10) : 0;
        if (parentId !== parseInt(parentFolderId, 10)) {
            continue;
        }
        if (pwdNormalizedFolderName(row.name) === normalized) {
            return parseInt(row.id, 10);
        }
    }
    return null;
}

function pwdIsDescendantFolder(folderId, ancestorId) {
    if (!folderId || !ancestorId || folderId === '0' || ancestorId === '0') {
        return false;
    }
    if (String(folderId) === String(ancestorId)) {
        return true;
    }
    const seen = {};
    let current = parseInt(folderId, 10);
    while (current > 0 && !seen[current]) {
        seen[current] = true;
        const row = pwdFoldersData.find(f => parseInt(f.id, 10) === current);
        if (!row) {
            break;
        }
        const parent = row.parent_id ? parseInt(row.parent_id, 10) : 0;
        if (parent === parseInt(ancestorId, 10)) {
            return true;
        }
        current = parent;
    }
    return false;
}

function pwdFolderAllowDrop(ev) {
    ev.preventDefault();
    const target = ev.target.closest('[data-folder-id]');
    if (target) {
        target.setAttribute('drag-over', 'true');
    }
}

function pwdFolderDrag(ev) {
    const item = ev.target.closest('[data-folder-id]');
    if (!item || item.getAttribute('data-folder-id') === '0') {
        return;
    }
    ev.dataTransfer.setData('folder_id', item.getAttribute('data-folder-id'));
}

function pwdFolderDrop(ev) {
    ev.preventDefault();
    const target = ev.target.closest('[data-folder-id]');
    if (!target) {
        return;
    }
    target.removeAttribute('drag-over');
    const folderId = ev.dataTransfer.getData('folder_id');
    const newParentId = target.getAttribute('data-folder-id');
    if (!folderId || folderId === newParentId) {
        return;
    }
    if (pwdIsDescendantFolder(folderId, newParentId)) {
        alert('Cannot move a folder into itself or one of its subfolders.');
        return;
    }
    const sourceName = pwdGetFolderName(folderId);
    const siblingId = pwdFindSiblingFolderWithName(newParentId, folderId, sourceName);
    const payload = {
        folder_id: folderId,
        new_parent_id: newParentId === '0' ? '' : newParentId,
        merge_into_folder_id: ''
    };
    if (siblingId) {
        const merge = confirm(
            'A folder named "' + sourceName + '" already exists in this location.\n\nMerge the moved folder into it?\n\nOK = merge contents\nCancel = keep both folders with the same name'
        );
        if (merge) {
            payload.merge_into_folder_id = String(siblingId);
        }
    }
    apiCall('move_folder', payload).then(res => {
        if (res.ok) {
            if (parseInt(currentFolderId, 10) === parseInt(folderId, 10) && payload.merge_into_folder_id) {
                currentFolderId = parseInt(payload.merge_into_folder_id, 10);
            }
            loadFolderTree();
        } else {
            alert(res.message || 'Could not move folder.');
        }
    });
}

document.addEventListener('dragleave', function(ev) {
    const target = ev.target.closest('[data-folder-id]');
    if (target) {
        target.removeAttribute('drag-over');
    }
});

function pwdBuildListUrl(params) {
    const query = new URLSearchParams();
    Object.keys(params || {}).forEach((key) => {
        const value = params[key];
        if (value === null || value === undefined || value === '') {
            return;
        }
        if (key === 'folder_id' && String(value) === '0') {
            return;
        }
        query.set(key, String(value));
    });
    const qs = query.toString();
    return 'index.php' + (qs ? '?' + qs : '');
}

function selectFolder(id) {
    window.location.href = pwdBuildListUrl({ folder_id: id, page: 1 });
}

function pwdReloadList() {
    window.location.reload();
}

function openEntryModal(id = 0) {
    const form = document.getElementById('entryForm');
    if (!form) return;
    form.reset();
    document.getElementById('entry-id').value = id;
    document.getElementById('entryModalLabel').innerText = id ? 'Edit Password' : 'Add Password';
    if (id) {
        apiCall('get_entry', { id }).then(data => {
            document.getElementById('entry-account').value = data.account || '';
            document.getElementById('entry-login_name').value = data.login_name || '';
            document.getElementById('entry-password').value = data.password || '';
            document.getElementById('entry-website').value = data.website || '';
            document.getElementById('entry-comments').value = data.comments || '';
            document.getElementById('entry-folder_id').value = data.folder_id || '0';
            $('body').append('<div class="modal-backdrop show"></div>'); $('#entryModal').addClass('show').show(); $('body').addClass('modal-open');
        });
    } else {
        document.getElementById('entry-folder_id').value = currentFolderId;
        $('body').append('<div class="modal-backdrop show"></div>'); $('#entryModal').addClass('show').show(); $('body').addClass('modal-open');
    }
}

function deleteEntry(id) {
    if (confirm('Delete entry?')) apiCall('delete_entry', { id }).then(res => { if (res.ok) pwdReloadList(); });
}

function openFolderModal(id = 0, name = '', parentId = 0) {
    const form = document.getElementById('folderForm');
    if (!form) return;
    form.reset();
    document.getElementById('folder-id').value = id;
    document.getElementById('folder-name').value = name;
    document.getElementById('folder-parent_id').value = parentId || '0';
    form.dataset.originalParentId = id ? String(parentId || 0) : '0';
    document.getElementById('folderModalLabel').innerText = id ? 'Rename Folder' : 'New Folder';
    $('body').append('<div class="modal-backdrop show"></div>'); $('#folderModal').addClass('show').show(); $('body').addClass('modal-open');
}

function deleteFolder(id) {
    if (confirm('Delete folder and contents?')) apiCall('delete_folder', { id }).then(res => { if (res.ok) pwdReloadList(); });
}

function openImportModal() { $('body').append('<div class="modal-backdrop show"></div>'); $('#importModal').addClass('show').show(); $('body').addClass('modal-open'); }
function openImportExcelModal() {
    const select = document.getElementById('import-excel-folder_id');
    const source = document.getElementById('import-folder_id');
    if (select && source) select.innerHTML = source.innerHTML;
    $('body').append('<div class="modal-backdrop show"></div>'); $('#importExcelModal').addClass('show').show(); $('body').addClass('modal-open');
}

function handleExcelImport() {
    const fileInput = document.getElementById('excel-file-input');
    const folderId = document.getElementById('import-excel-folder_id').value;
    if (!fileInput.files[0]) { alert('Select a file'); return; }

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(firstSheet, {header: 1});

        if (rows.length < 2) { alert('No data found'); return; }

        apiCall('import_rows', {
            folder_id: folderId,
            rows: JSON.stringify(rows)
        }).then(res => {
            if (res.ok) {
                alert('Imported ' + res.imported + ' entries!');
                $('#importExcelModal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');
                pwdReloadList();
            } else alert(res.message);
        });
    };
    reader.readAsArrayBuffer(fileInput.files[0]);
}
function exportVault(format) { window.location.href = `export_handler.php?format=${format}&folder_id=${currentFolderId}&csrf_token=${CSRF_TOKEN}`; }


document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-toggle')) {
        $('.dropdown-menu').removeClass('show');
    }
});
document.addEventListener('DOMContentLoaded', () => {
    const genPasswordInput = document.getElementById('gen-password');
    if (genPasswordInput) {
        genPasswordInput.addEventListener('input', syncGeneratorFromManualPassword);
    }
    generatePassword(); loadFolderTree();
    const editEntryParam = new URLSearchParams(window.location.search).get('edit_entry');
    if (editEntryParam) {
        const editId = parseInt(editEntryParam, 10);
        if (editId > 0) {
            openEntryModal(editId);
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', 'index.php');
            }
        }
    }
    document.getElementById('entryForm').onsubmit = function(e) {
        e.preventDefault();
        const data = {};
        new FormData(this).forEach((v, k) => data[k] = v);
        apiCall('save_entry', data).then(res => {
            if (res.ok) {
                $('#entryModal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');
                pwdReloadList();
            } else {
                alert(res.message);
            }
        });
    };
    document.getElementById('folderForm').onsubmit = function(e) {
        e.preventDefault();
        const data = {};
        new FormData(this).forEach((v, k) => data[k] = v);
        const folderId = parseInt(data.id || '0', 10) || 0;
        const newParentId = parseInt(data.parent_id || '0', 10) || 0;
        const originalParentId = parseInt(this.dataset.originalParentId || '0', 10) || 0;
        if (folderId > 0 && newParentId !== originalParentId) {
            const siblingId = pwdFindSiblingFolderWithName(newParentId, folderId, data.name || '');
            if (siblingId) {
                const merge = confirm(
                    'A folder named "' + (data.name || '') + '" already exists in this location.\n\nMerge this folder into it?\n\nOK = merge contents\nCancel = keep both folders with the same name'
                );
                if (merge) {
                    data.merge_into_folder_id = String(siblingId);
                }
            }
        }
        apiCall('save_folder', data).then(res => {
            if (res.ok) {
                $('#folderModal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');
                loadFolderTree();
            } else {
                alert(res.message);
            }
        });
    };
    document.getElementById('importForm').onsubmit = function(e) {
        e.preventDefault();
        const fd = new FormData(this); fd.append('csrf_token', CSRF_TOKEN); fd.append('action', 'import_csv');
        fetch('ajax_handler.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.ok) { alert('Imported!'); $('#importModal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); pwdReloadList(); } else alert(res.message);
        });
    };
});

function sanitizeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function addslashes(s) { if (!s) return ''; return s.replace(/[\\\'\"]/g, "\\$&").replace(/\n/g, "\\n").replace(/\r/g, "\\r"); }
</script>
<?php require_once ROOT_PATH . 'includes/itm_qr_share_modal.php'; ?>
<script src="../../js/bulk-delete-selection.js"></script>
<script>window.ITM_CSRF_TOKEN = CSRF_TOKEN;</script>
</body>
</html>
