<?php
/**
 * Passwords Module
 * 
 * Provides a private password manager with folder organization,
 * encryption at rest, and import/export capabilities.
 */

require_once '../../config/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    return;
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$user_id = (int)$_SESSION['user_id'];
$company_id = (int)$_SESSION['company_id'];
$csrfToken = itm_get_csrf_token();

// Vault Lock/Unlock Actions
if (isset($_GET['action']) && $_GET['action'] === 'lock') {
    unset($_SESSION['vault_key']);
    header('Location: index.php');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_key'])) {
    if (itm_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['vault_key'] = $_POST['master_key'];
        header('Location: index.php');
        return;
    }
}

// Module Configuration
$module_title = "Passwords";
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Page content starts
include '../../includes/header.php';
?>

<div class="content-wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <?php if (empty($_SESSION['vault_key'])): ?>
                <!-- UNLOCK VAULT VIEW -->
                <div style="max-width: 400px; margin: 100px auto; text-align: center;" class="card">
                    <h2>🔒 Vault Locked</h2>
                    <p>Enter your master key to access your passwords.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <div class="form-group">
                            <input type="password" name="master_key" class="form-control" placeholder="Master Key" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">🔓 Unlock Vault</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- MAIN PASSWORD MANAGER UI -->
                <div class="row">
                    <!-- LEFT COLUMN: PASSWORD GENERATOR -->
                    <div class="col-md-3">
                        <div class="card">
                            <h3>⚡ Password Generator</h3>
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="text" id="gen-password" class="form-control" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('gen-password')" title="Copy">🗐</button>
                                        <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()" title="Regenerate">🔄</button>
                                    </div>
                                </div>
                            </div>
                            <div id="password-strength-meter" style="height: 5px; margin-bottom: 10px; background: #eee;">
                                <div id="strength-bar" style="height: 100%; width: 0%; transition: width 0.3s;"></div>
                            </div>
                            
                            <div class="form-group">
                                <label>Length: <span id="length-val">12</span></label>
                                <input type="range" class="custom-range" id="gen-length" min="4" max="50" value="12" oninput="document.getElementById('length-val').innerText = this.value; generatePassword();">
                            </div>
                            
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="gen-upper" checked onchange="generatePassword()">
                                <label class="custom-control-label" for="gen-upper">Uppercase (A-Z)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="gen-lower" checked onchange="generatePassword()">
                                <label class="custom-control-label" for="gen-lower">Lowercase (a-z)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="gen-numbers" checked onchange="generatePassword()">
                                <label class="custom-control-label" for="gen-numbers">Numbers (0-9)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="gen-symbols" checked onchange="generatePassword()">
                                <label class="custom-control-label" for="gen-symbols">Symbols (!@#$%^&*)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="gen-exclude-similar" onchange="generatePassword()">
                                <label class="custom-control-label" for="gen-exclude-similar">Exclude Similar (i, l, 1, L, o, 0, O)</label>
                            </div>
                            
                            <hr>
                            <button class="btn btn-success btn-block" onclick="saveGeneratedPassword()">Save to Vault</button>
                        </div>
                        
                        <div class="card mt-3">
                            <h3>📁 Folders</h3>
                            <div id="folder-tree">
                                <!-- Folder tree will be loaded here via AJAX -->
                                Loading...
                            </div>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="openFolderModal(0)">➕ New Folder</button>
                        </div>
                    </div>

                    <!-- MAIN AREA: SEARCH AND ENTRIES -->
                    <div class="col-md-9">
                        <div class="card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="flex-grow-1 mr-3">
                                    <form method="GET" class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Search account, login, website, or comments..." value="<?php echo sanitize($search_query); ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="submit">🔍</button>
                                        </div>
                                    </form>
                                </div>
                                <div>
                                    <button class="btn btn-primary" onclick="openEntryModal()">➕ Add Password</button>
                                    <div class="btn-group ml-2">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown">Tools</button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" onclick="openImportModal(); return false;">Import CSV</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" onclick="exportVault('xlsx'); return false;">Export XLSX</a>
                                            <a class="dropdown-item" href="#" onclick="exportVault('csv'); return false;">Export CSV</a>
                                            <a class="dropdown-item" href="#" onclick="exportVault('pdf'); return false;">Export PDF</a>
                                            <a class="dropdown-item" href="#" onclick="exportVault('txt'); return false;">Export TXT</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="entries-container">
                                <!-- Entries table will be loaded here via AJAX -->
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Account</th>
                                            <th>Login Name</th>
                                            <th>Password</th>
                                            <th>Website</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="entries-body">
                                        <!-- Rows loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- MODALS -->
<!-- Entry Modal -->
<div class="modal fade" id="entryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entryModalLabel">Add Password</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="entryForm">
                <input type="hidden" name="id" id="entry-id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Account</label>
                                <input type="text" name="account" id="entry-account" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Folder</label>
                                <select name="folder_id" id="entry-folder_id" class="form-control">
                                    <!-- Options loaded via AJAX -->
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Login Name</label>
                                <input type="text" name="login_name" id="entry-login_name" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="entry-password" class="form-control" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('entry-password')">👁️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Website (URL)</label>
                        <input type="url" name="website" id="entry-website" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Comments</label>
                        <textarea name="comments" id="entry-comments" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Folder Modal -->
<div class="modal fade" id="folderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="folderModalLabel">New Folder</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="folderForm">
                <input type="hidden" name="id" id="folder-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Folder Name</label>
                        <input type="text" name="name" id="folder-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Parent Folder</label>
                        <select name="parent_id" id="folder-parent_id" class="form-control">
                            <option value="">-- Root --</option>
                            <!-- Options loaded via AJAX -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Passwords</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label>CSV File</label>
                        <input type="file" name="csv_file" class="form-control-file" accept=".csv" required>
                        <small class="form-text text-muted">Supports Microsoft Edge and KeePass CSV formats.</small>
                    </div>
                    <div class="form-group">
                        <label>Target Folder</label>
                        <select name="target_folder_id" id="import-folder_id" class="form-control">
                            <!-- Options loaded via AJAX -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Start Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
let currentFolderId = <?php echo $current_folder_id; ?>;
let searchQuery = '<?php echo addslashes($search_query); ?>';

/**
 * Password Generator Logic
 */
function generatePassword() {
    const length = parseInt(document.getElementById('gen-length').value);
    const useUpper = document.getElementById('gen-upper').checked;
    const useLower = document.getElementById('gen-lower').checked;
    const useNumbers = document.getElementById('gen-numbers').checked;
    const useSymbols = document.getElementById('gen-symbols').checked;
    const excludeSimilar = document.getElementById('gen-exclude-similar').checked;

    let charset = "";
    if (useUpper) charset += "ABCDEFGHJKLMNPQRSTUVWXYZ";
    if (useLower) charset += "abcdefghijkmnopqrstuvwxyz";
    if (useNumbers) charset += "23456789";
    if (useSymbols) charset += "!@#$%^&*";
    
    if (!excludeSimilar) {
        if (useUpper) charset += "I LO";
        if (useLower) charset += "l o";
        if (useNumbers) charset += "1 0";
    }

    let password = "";
    if (charset.length > 0) {
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
    }
    
    document.getElementById('gen-password').value = password;
    updateStrengthMeter(password);
}

function updateStrengthMeter(password) {
    const bar = document.getElementById('strength-bar');
    let strength = 0;
    if (password.length > 8) strength += 20;
    if (password.length > 12) strength += 20;
    if (/[A-Z]/.test(password)) strength += 20;
    if (/[0-9]/.test(password)) strength += 20;
    if (/[!@#$%^&*]/.test(password)) strength += 20;
    
    bar.style.width = strength + '%';
    if (strength < 40) bar.style.backgroundColor = 'red';
    else if (strength < 80) bar.style.backgroundColor = 'orange';
    else bar.style.backgroundColor = 'green';
}

function copyToClipboard(id) {
    const el = document.getElementById(id);
    el.select();
    document.execCommand('copy');
    alert('Copied to clipboard!');
}

function saveGeneratedPassword() {
    const pwd = document.getElementById('gen-password').value;
    if (!pwd) return;
    openEntryModal();
    document.getElementById('entry-password').value = pwd;
    togglePasswordVisibility('entry-password', true);
}

function togglePasswordVisibility(id, forceShow = false) {
    const el = document.getElementById(id);
    if (forceShow || el.type === 'password') {
        el.type = 'text';
    } else {
        el.type = 'password';
    }
}

/**
 * Vault Logic (AJAX)
 */
async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', action);
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    const response = await fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    });
    return await response.json();
}

function loadFolderTree() {
    apiCall('list_folders').then(data => {
        const tree = document.getElementById('folder-tree');
        const selectEntry = document.getElementById('entry-folder_id');
        const selectFolder = document.getElementById('folder-parent_id');
        const selectImport = document.getElementById('import-folder_id');
        
        let treeHtml = '<ul class="list-unstyled">';
        let optionsHtml = '<option value="0">-- Root --</option>';
        
        const buildTree = (parentId, level = 0) => {
            const children = data.filter(f => f.parent_id == parentId);
            children.forEach(f => {
                const isActive = f.id == currentFolderId;
                treeHtml += `<li style="padding-left: ${level * 15}px">
                    <div class="d-flex justify-content-between align-items-center ${isActive ? 'bg-light font-weight-bold' : ''}">
                        <a href="#" onclick="selectFolder(${f.id}); return false;">📁 ${f.name}</a>
                        <div>
                            <button class="btn btn-link btn-sm p-0" onclick="openFolderModal(${f.id}, '${f.name.replace(/'/g, "\\'")}', ${f.parent_id})">✏️</button>
                            <button class="btn btn-link btn-sm p-0 text-danger" onclick="deleteFolder(${f.id})">🗑️</button>
                        </div>
                    </div>
                </li>`;
                optionsHtml += `<option value="${f.id}">${'&nbsp;'.repeat(level * 2)}${f.name}</option>`;
                buildTree(f.id, level + 1);
            });
        };
        
        buildTree(0);
        treeHtml += '</ul>';
        
        tree.innerHTML = treeHtml;
        selectEntry.innerHTML = optionsHtml;
        selectFolder.innerHTML = optionsHtml;
        selectImport.innerHTML = optionsHtml;
    });
}

function selectFolder(id) {
    currentFolderId = id;
    loadEntries();
    loadFolderTree(); // Refresh to update active class
}

function loadEntries() {
    apiCall('list_entries', { folder_id: currentFolderId, search: searchQuery }).then(data => {
        const body = document.getElementById('entries-body');
        body.innerHTML = '';
        
        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="text-center">No passwords found.</td></tr>';
            return;
        }
        
        data.forEach(e => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    ${sanitizeHtml(e.account)}
                    <button class="btn btn-link btn-sm p-0 ml-1" onclick="copyText('${addslashes(e.account)}')">🗐</button>
                </td>
                <td>
                    ${sanitizeHtml(e.login_name)}
                    <button class="btn btn-link btn-sm p-0 ml-1" onclick="copyText('${addslashes(e.login_name)}')">🗐</button>
                </td>
                <td>
                    <div class="input-group input-group-sm" style="width: 150px;">
                        <input type="password" value="${sanitizeHtml(e.password)}" class="form-control" readonly id="pwd-${e.id}">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('pwd-${e.id}')">👁️</button>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyText('${addslashes(e.password)}')">🗐</button>
                        </div>
                    </div>
                </td>
                <td>
                    ${e.website ? `<a href="${e.website}" target="_blank" rel="nofollow noreferrer">${sanitizeHtml(e.website)}</a>` : ''}
                    ${e.website ? `<button class="btn btn-link btn-sm p-0 ml-1" onclick="copyText('${addslashes(e.website)}')">🗐</button>` : ''}
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="openEntryModal(${e.id})">✏️</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEntry(${e.id})">🗑️</button>
                </td>
            `;
            body.appendChild(row);
        });
    });
}

function copyText(text) {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    alert('Copied to clipboard!');
}

function sanitizeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function addslashes(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('gen-password')) {
        generatePassword();
        loadFolderTree();
        loadEntries();
    }
});

function openEntryModal(id = 0) {
    document.getElementById('entryForm').reset();
    document.getElementById('entry-id').value = id;
    document.getElementById('entryModalLabel').innerText = id ? 'Edit Password' : 'Add Password';
    
    if (id) {
        apiCall('get_entry', { id }).then(data => {
            document.getElementById('entry-account').value = data.account;
            document.getElementById('entry-login_name').value = data.login_name;
            document.getElementById('entry-password').value = data.password;
            document.getElementById('entry-website').value = data.website;
            document.getElementById('entry-comments').value = data.comments;
            document.getElementById('entry-folder_id').value = data.folder_id;
            $('#entryModal').modal('show');
        });
    } else {
        document.getElementById('entry-folder_id').value = currentFolderId;
        $('#entryModal').modal('show');
    }
}

document.getElementById('entryForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);
    
    apiCall('save_entry', data).then(res => {
        if (res.ok) {
            $('#entryModal').modal('hide');
            loadEntries();
        } else {
            alert('Error: ' + res.message);
        }
    });
};

function deleteEntry(id) {
    if (confirm('Delete this password entry?')) {
        apiCall('delete_entry', { id }).then(res => {
            if (res.ok) loadEntries();
        });
    }
}

function openFolderModal(id = 0, name = '', parentId = 0) {
    document.getElementById('folderForm').reset();
    document.getElementById('folder-id').value = id;
    document.getElementById('folder-name').value = name;
    document.getElementById('folder-parent_id').value = parentId || '0';
    document.getElementById('folderModalLabel').innerText = id ? 'Rename Folder' : 'New Folder';
    $('#folderModal').modal('show');
}

document.getElementById('folderForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);
    
    apiCall('save_folder', data).then(res => {
        if (res.ok) {
            $('#folderModal').modal('hide');
            loadFolderTree();
        } else {
            alert('Error: ' + res.message);
        }
    });
};

function deleteFolder(id) {
    if (confirm('Delete this folder and all its contents?')) {
        apiCall('delete_folder', { id }).then(res => {
            if (res.ok) {
                if (currentFolderId == id) currentFolderId = 0;
                loadFolderTree();
                loadEntries();
            }
        });
    }
}

function openImportModal() {
    $('#importModal').modal('show');
}

document.getElementById('importForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', 'import_csv');
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if (res.ok) {
            alert(`Import successful!\nRows: ${res.total}\nImported: ${res.imported}\nSkipped: ${res.skipped}\nErrors: ${res.failed}`);
            $('#importModal').modal('hide');
            loadEntries();
        } else {
            alert('Error: ' + res.message);
        }
    });
};

function exportVault(format) {
    const url = `export_handler.php?format=${format}&folder_id=${currentFolderId}&csrf_token=${CSRF_TOKEN}`;
    window.location.href = url;
}
</script>

<?php include '../../includes/header.php'; ?>
</body>
</html>
