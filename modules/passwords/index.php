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
$module_title = "Passwords";
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

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
        .strength-meter {
            height: 6px;
            background: var(--bg-tertiary);
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }
        .strength-bar { height: 100%; width: 0; transition: width 0.3s, background-color 0.3s; }
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
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 250px;">
                                    <div class="input-group">
                                        <input type="text" id="entry-search" class="form-control" placeholder="Search entries..." value="<?php echo sanitize($search_query); ?>" onkeyup="if(event.key==='Enter') performSearch()">
                                        <div class="input-group-append"><button class="btn btn-primary" onclick="performSearch()">🔍</button></div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-primary" onclick="openEntryModal()">➕</button>
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
                                    <thead><tr><th style="width: 100px; text-align: center;" class="itm-actions-cell" data-itm-actions-origin="1">Actions</th><th>Account</th><th>Login Name</th><th>Password</th><th>Website</th></tr></thead>
                                    <tbody id="entries-body"><tr><td colspan="5" class="text-center">Loading entries...</td></tr></tbody>
                                </table>
                            </div>
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
                        <div class="col-md-6"><div class="form-group"><label>Password</label><div class="input-group"><input type="password" name="password" id="entry-password" class="form-control" required><div class="input-group-append"><button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('entry-password')">👁️</button></div></div></div></div>
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
let currentFolderId = 0;
let searchQuery = '';

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
        const tree = document.getElementById('folder-tree');
        if (!tree) return;
        const selectEntry = document.getElementById('entry-folder_id');
        const selectFolder = document.getElementById('folder-parent_id');
        const selectImport = document.getElementById('import-folder_id');
        const selectImportExcel = document.getElementById('import-excel-folder_id');
        let treeHtml = '';
        let optionsHtml = '<option value="0">-- Root --</option>';
        const buildTree = (parentId, level = 0) => {
            const children = Array.isArray(data) ? data.filter(f => (f.parent_id == parentId) || (parentId === 0 && !f.parent_id)) : [];
            children.forEach(f => {
                const isActive = f.id == currentFolderId;
                treeHtml += `<div class="folder-item ${isActive ? 'active' : ''}" style="margin-left: ${level * 15}px">
                    <a href="#" onclick="selectFolder(${f.id}); return false;">📁 ${sanitizeHtml(f.name)}</a>
                    <div>
                        <button class="btn btn-link btn-sm p-0" onclick="openFolderModal(${f.id}, '${addslashes(f.name)}', ${f.parent_id})">✏️</button>
                        <button class="btn btn-link btn-sm p-0 text-danger" onclick="deleteFolder(${f.id})">🗑️</button>
                    </div>
                </div>`;
                optionsHtml += `<option value="${f.id}">${'&nbsp;'.repeat(level * 2)}${sanitizeHtml(f.name)}</option>`;
                buildTree(f.id, level + 1);
            });
        };
        buildTree(0);
        tree.innerHTML = treeHtml || '<div class="text-muted text-center">No folders.</div>';
        if (selectEntry) selectEntry.innerHTML = optionsHtml;
        if (selectFolder) selectFolder.innerHTML = optionsHtml;
        if (selectImport) selectImport.innerHTML = optionsHtml;
        if (selectImportExcel) selectImportExcel.innerHTML = optionsHtml;
    });
}

function selectFolder(id) { currentFolderId = id; loadEntries(); loadFolderTree(); }
function performSearch() { searchQuery = document.getElementById('entry-search').value; loadEntries(); }

function loadEntries() {
    apiCall('list_entries', { folder_id: currentFolderId, search: searchQuery }).then(data => {
        const body = document.getElementById('entries-body');
        if (!body) return;
        body.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="text-center" style="padding: 40px;">No entries found.</td></tr>';
            return;
        }
        data.forEach(e => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="itm-actions-cell" data-itm-actions-origin="1" style="text-align: center;"><div class="itm-actions-wrap"><button class="btn btn-sm btn-outline-primary" onclick="openEntryModal(${e.id})" title="Edit">✏️</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteEntry(${e.id})" title="Delete">🗑️</button></div></td>
                <td>${sanitizeHtml(e.account)} <button class="btn btn-link btn-sm p-0" onclick="copyText('${addslashes(e.account)}')">🗐</button></td>
                <td>${sanitizeHtml(e.login_name)} <button class="btn btn-link btn-sm p-0" onclick="copyText('${addslashes(e.login_name)}')">🗐</button></td>
                <td><div class="input-group input-group-sm" style="width: 140px;"><input type="password" value="${sanitizeHtml(e.password)}" class="form-control" readonly id="pwd-${e.id}"><div class="input-group-append"><button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('pwd-${e.id}')">👁️</button><button class="btn btn-outline-secondary" type="button" onclick="copyText('${addslashes(e.password)}')">🗐</button></div></div></td>
                <td>${e.website ? `<a href="${sanitizeHtml(e.website)}" target="_blank" rel="nofollow noreferrer noopener" style="text-decoration: none !important;">${sanitizeHtml(e.website.replace(/^https?:\/\//, ''))}</a>` : '—'}</td>
            `;
            body.appendChild(row);
        });
    });
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
    if (confirm('Delete entry?')) apiCall('delete_entry', { id }).then(res => { if (res.ok) loadEntries(); });
}

function openFolderModal(id = 0, name = '', parentId = 0) {
    const form = document.getElementById('folderForm');
    if (!form) return;
    form.reset();
    document.getElementById('folder-id').value = id;
    document.getElementById('folder-name').value = name;
    document.getElementById('folder-parent_id').value = parentId || '0';
    document.getElementById('folderModalLabel').innerText = id ? 'Rename Folder' : 'New Folder';
    $('body').append('<div class="modal-backdrop show"></div>'); $('#folderModal').addClass('show').show(); $('body').addClass('modal-open');
}

function deleteFolder(id) {
    if (confirm('Delete folder and contents?')) apiCall('delete_folder', { id }).then(res => { if (res.ok) { loadFolderTree(); loadEntries(); } });
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
                loadEntries();
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
    generatePassword(); loadFolderTree(); loadEntries();
    document.getElementById('entryForm').onsubmit = function(e) {
        e.preventDefault();
        const data = {};
        new FormData(this).forEach((v, k) => data[k] = v);
        apiCall('save_entry', data).then(res => {
            if (res.ok) {
                $('#entryModal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open');
                loadEntries();
            } else {
                alert(res.message);
            }
        });
    };
    document.getElementById('folderForm').onsubmit = function(e) {
        e.preventDefault();
        const data = {};
        new FormData(this).forEach((v, k) => data[k] = v);
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
            if (res.ok) { alert('Imported!'); $('#importModal').removeClass('show').hide(); $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); loadEntries(); } else alert(res.message);
        });
    };
});

function sanitizeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function addslashes(s) { if (!s) return ''; return s.replace(/[\\\'\"]/g, "\\$&").replace(/\n/g, "\\n").replace(/\r/g, "\\r"); }
</script>
</body>
</html>
