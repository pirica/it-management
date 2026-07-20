<?php
/**
 * Explorer Module
 *
 * Provides a web-based file explorer with UK English localisation.
 * Integrated with the multi-tenant IT Management System, featuring:
 * - Company-scoped storage (Common, Department-specific, and User-private).
 * - Multi-tab and breadcrumb navigation.
 * - Real-time synchronisation with the 'explorer' database table.
 * - Dark mode support and contextual actions.
 */

$crud_table = 'explorer';
$crud_title = 'Explorer';
$crud_action = $crud_action ?? 'index';

require_once '../../config/config.php';

// Why: User must be logged in with a company selected.
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    die();
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['employee_id'];

// Why: Fallback to database if session username is missing (e.g. legacy session).
if (!isset($_SESSION['username'])) {
    $stmt = mysqli_prepare($conn, "SELECT username FROM employees WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($user_row = mysqli_fetch_assoc($res)) {
            $_SESSION['username'] = $user_row['username'];
        }
        mysqli_stmt_close($stmt);
    }
}

$username = $_SESSION['username'] ?? 'User';
// Why: Sanitise username for filesystem safety to prevent path traversal or separator issues.
$safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
$user_private_dir = "{$safe_username}_{$user_id}";
$user_private_dir_json = json_encode($user_private_dir, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

// Why: Department scope for sidebar and folder navigation (Private/Departments roots are API-blocked).
$dept_code = '';
$dept_stmt = mysqli_prepare($conn, "SELECT d.code FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = ? AND e.company_id = ? LIMIT 1");
if ($dept_stmt) {
    mysqli_stmt_bind_param($dept_stmt, "ii", $user_id, $company_id);
    mysqli_stmt_execute($dept_stmt);
    $dept_res = mysqli_stmt_get_result($dept_stmt);
    if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
        $dept_code = trim((string)($dept_row['code'] ?? ''));
    }
    mysqli_stmt_close($dept_stmt);
}
// Why: Sanitise department code for filesystem safety to match api.php.
$safe_dept_code = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $dept_code);
$user_dept_code_json = json_encode($safe_dept_code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Why: Ensure the root /files/{company_id} directory exists with deny_http on every segment.
$storage_root = ROOT_PATH . 'files/' . $company_id;
itm_ensure_files_storage_directory($storage_root);

?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Explorer';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
<link rel="icon" type="image/png" href="<?= sanitize($favicon_url) ?>">
<link rel="stylesheet" href="../../css/styles.css">

<style>
body {
    background: var(--bg-secondary);
    margin: 0;
    padding: 0;
    transition: background 0.3s, color 0.3s;
}

#main {
    margin: 20px;
    transition: margin 0.3s;
}

/* SIDEBAR */
#sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 240px;
    height: 100%;
    background: var(--bg-primary);
    border-right: 1px solid var(--border);
    padding: 20px;
    z-index: 9999;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
}
#sidebar.open { transform: translateX(0); }
#sidebar h3, #sidebar h4 { margin-top:0; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
#sidebar div { margin-bottom:12px; cursor:pointer; padding: 6px; border-radius: 4px; }
#sidebar div:hover { background: var(--bg-tertiary); }
#sidebar a { text-decoration: none; color: inherit; display: block; }

/* TOPBAR */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

#sidebarToggle {
    background: var(--accent);
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:18px;
    padding:6px 10px;
}
#sidebarToggle:hover { background: var(--accent-hover); }

/* SEARCH */
.search {
    padding:8px 12px;
    width:250px;
    border-radius:6px;
    border:1px solid var(--border);
    font-size: 13px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

/* TABS */
.tabs {
    display:flex;
    gap:5px;
    margin-bottom:0;
    border-bottom: 1px solid var(--border);
}
.tab {
    padding:8px 16px;
    background: var(--bg-tertiary);
    border-radius:8px 8px 0 0;
    cursor:pointer;
    font-size:13px;
    border: 1px solid transparent;
    border-bottom: none;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display:flex;
    align-items:center;
    gap:8px;
    color: var(--text-secondary);
}
.tab.active { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border); border-bottom: none; }
.tab .close { font-size:10px; opacity:0.6; }
.tab .close:hover { opacity:1; }

/* BREADCRUMBS */
.breadcrumbs-bar {
    background: var(--bg-secondary);
    padding: 10px 0;
    margin-bottom: 10px;
    font-size: 13px;
}
.breadcrumbs { display:flex; gap:5px; align-items:center; }
.breadcrumbs span { cursor:pointer; color: var(--accent); }
.breadcrumbs span:hover { text-decoration:underline; }
.breadcrumbs .sep { color: var(--text-muted); cursor:default; }

/* GRID */
.desktop {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 20px;
    padding: 20px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    min-height: 400px;
}
.icon {
    text-align: center;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: background 0.2s;
    position: relative;
    font-size: 12px;
}
.icon:hover { background: var(--bg-tertiary); }
.icon.selected { background: var(--accent-muted); border: 1px solid var(--accent); }
.icon span { display: block; font-size: 40px; margin-bottom: 8px; }

/* CONTEXT MENU */
.context-menu {
    position: absolute;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    display: none;
    border-radius: 6px;
    padding: 5px 0;
    min-width: 160px;
}
.context-menu div { padding: 8px 15px; cursor: pointer; font-size: 13px; }
.context-menu div:hover { background: var(--accent); color: #fff; }
.context-menu hr { border: 0; border-top: 1px solid var(--border); margin: 5px 0; }

/* PREVIEW */
.preview {
    background: #000;
    color: #0f0;
    padding: 15px;
    font-family: monospace;
    white-space: pre-wrap;
    max-height: 500px;
    overflow-y: auto;
    border-radius: 6px;
}
.preview-media {
    background: var(--bg-primary);
    color: var(--text-primary);
    padding: 10px;
    font-family: inherit;
    white-space: normal;
    text-align: center;
}
.preview-media img {
    max-width: 100%;
    max-height: 480px;
    object-fit: contain;
    border-radius: 4px;
}
.preview-media embed {
    width: 100%;
    height: 480px;
    border: 0;
    border-radius: 4px;
}

/* UPLOAD */
.upload-area {
    margin-bottom: 15px;
}

/* BADGES */
.badge-private { font-size: 10px; background: #ff4757; color: #fff; padding: 2px 4px; border-radius: 3px; position: absolute; top: 5px; right: 5px; }

@media (max-width: 768px) {
    #main { margin: 10px; }
    .topbar { flex-wrap: wrap; gap: 10px; align-items: stretch; }
    .topbar > div { width: 100%; justify-content: space-between; }
    .search { width: 100%; max-width: none; flex: 1; min-width: 0; }
    .tabs { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; }
    .tab { flex-shrink: 0; max-width: none; }
    .desktop { grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 12px; padding: 12px; }
    .preview-media embed { height: min(480px, 60vh); }
}

</style>
</head>
<body>

<div id="sidebar">
    <h3>📌 Quick Access</h3>
    <div onclick="closeSidebar()"><a href="../../dashboard.php">📊 Dashboard</a></div>
    <div onclick="loadFolder(''); closeSidebar();">🏠 Home (Company Root)</div>
    <div onclick="loadFolder('Common'); closeSidebar();">🌐 Common Area</div>
    <div onclick="loadFolder('Departments'); closeSidebar();">🏢 Department Area</div>
    <div onclick="loadFolder('Private'); closeSidebar();">🔒 Private Area</div>
    <div onclick="openRecycle(); closeSidebar();">🗑️ Trash</div>
    <hr>
    <h3>👤 Employee</h3>
    <div onclick="openEmployeeProfileFolder(); closeSidebar();">🌐 Profile Storage</div>
    <h4>⭐ Favourites</h4>
    <div id="favorites"></div>
</div>

<div id="main">
    <div class="topbar">
        <div style="display:flex; gap:10px;">
            <button onclick="toggleTheme()" class="btn btn-sm" title="Toggle Dark/Light Mode">🌙</button>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <input id="searchBox" class="search" placeholder="Search files..." oninput="filterIcons()">
            <span style="font-size: 13px; color: var(--text-secondary);"><a href="../../dashboard.php" style="color: inherit; text-decoration: none;"><?= sanitize($username) ?></a></span>
            <button id="sidebarToggle">☰</button>
        </div>
    </div>

    <div id="tabs" class="tabs"></div>
    <div class="breadcrumbs-bar">
        <div id="breadcrumbs" class="breadcrumbs"></div>
    </div>

    <div id="uploadArea" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload files">
        <p class="itm-dropzone-hint">Drag and drop files here, or click to browse.</p>
        <input type="file" multiple onchange="uploadFiles(this.files)" style="display:none;" id="explorerFileInput">
    </div>

    <div id="desktop" class="desktop"></div>

    <div id="previewContainer" style="display:none;">
        <h4>Preview</h4>
        <div id="preview" class="preview"></div>
    </div>

    <div id="contextMenu" class="context-menu"></div>
</div>

<script>
let tabs = [{ path: "", title: "Home" }];
let activeTab = 0;
let currentPath = "";
let selected = new Set();
let clipboard = { type: null, path: null, items: [] };
const CLIPBOARD_STORAGE_KEY = "itm_explorer_clipboard";
let contextItem = null;
let inRecycle = false;
let userPrivateDir = <?= $user_private_dir_json ?>;
let userDeptCode = <?= $user_dept_code_json ?>;
let favourites = JSON.parse(localStorage.getItem("itm_explorer_favourites") || "[]");

/* Why: API blocks Private/Departments roots; UI must open the user's scoped subfolder instead. */
function resolveScopedFolderPath(path) {
    const normalized = String(path || "").replace(/\\/g, "/").replace(/^\/+|\/+$/g, "");
    if (normalized === "Private") {
        return "Private/" + userPrivateDir;
    }
    if (normalized === "Departments") {
        if (userDeptCode === "") {
            alert("You are not assigned to a department. Department files are unavailable.");
            return null;
        }
        return "Departments/" + userDeptCode;
    }
    return normalized;
}

function openEmployeeProfileFolder() {
    const profilePath = resolveScopedFolderPath("Private");
    if (!profilePath) {
        return;
    }
    loadFolder(profilePath + "/profile");
}

const sidebar   = document.getElementById("sidebar");
const btnToggle = document.getElementById("sidebarToggle");
const desktop   = document.getElementById("desktop");
const ctxMenu   = document.getElementById("contextMenu");
const preview   = document.getElementById("preview");
const previewCont = document.getElementById("previewContainer");

/* THEME */
function toggleTheme() {
    const current = document.body.getAttribute("data-theme") || "light";
    const next = current === "light" ? "dark" : "light";
    document.body.setAttribute("data-theme", next);
    localStorage.setItem("itm_explorer_theme", next);
}
if (localStorage.getItem("itm_explorer_theme") === "dark") {
    document.body.setAttribute("data-theme", "dark");
}

/* SIDEBAR */
btnToggle.onclick = () => sidebar.classList.toggle("open");
function closeSidebar() { sidebar.classList.remove("open"); }

/* API WRAPPER */
function api(action, data = {}) {
    data.action = action;
    data.path = currentPath;
    data.csrf_token = "<?= itm_get_csrf_token() ?>";
    return fetch("api.php", {
        method: "POST",
        body: new URLSearchParams(data)
    }).then(r => r.json());
}

/* TABS */
function renderTabs() {
    const el = document.getElementById("tabs");
    el.innerHTML = "";
    tabs.forEach((tab, i) => {
        const d = document.createElement("div");
        d.className = "tab" + (i === activeTab ? " active" : "");
        d.innerHTML = `
            <span>${tab.title}</span>
            <span class="close" onclick="closeTab(${i}, event)">✕</span>
        `;
        d.onclick = () => {
            activeTab = i;
            currentPath = tab.path;
            loadFolder(currentPath);
        };
        el.appendChild(d);
    });
    const add = document.createElement("div");
    add.className = "tab";
    add.innerHTML = "+";
    add.onclick = () => {
        tabs.push({ path: "", title: "Home" });
        activeTab = tabs.length - 1;
        currentPath = "";
        loadFolder("");
    };
    el.appendChild(add);
}
function closeTab(i, e) {
    e.stopPropagation();
    if (tabs.length === 1) return;
    tabs.splice(i, 1);
    if (activeTab >= tabs.length) activeTab = tabs.length - 1;
    currentPath = tabs[activeTab].path;
    loadFolder(currentPath);
}

/* BREADCRUMBS */
function renderBreadcrumbs() {
    const el = document.getElementById("breadcrumbs");
    el.innerHTML = "";

    let rootSpan = document.createElement("span");
    rootSpan.textContent = "Home";
    rootSpan.onclick = () => loadFolder("");
    el.appendChild(rootSpan);

    if (inRecycle) {
        const sep = document.createElement("span");
        sep.className = "sep";
        sep.textContent = " / ";
        el.appendChild(sep);

        const trashSpan = document.createElement("span");
        trashSpan.textContent = "Trash";
        trashSpan.onclick = () => openRecycle();
        el.appendChild(trashSpan);
        return;
    }

    const parts = currentPath ? currentPath.split("/") : [];

    let current = "";
    parts.forEach(p => {
        const sep = document.createElement("span");
        sep.className = "sep";
        sep.textContent = " / ";
        el.appendChild(sep);

        current = current ? current + "/" + p : p;
        const s = document.createElement("span");
        s.textContent = p;
        const target = current;
        s.onclick = () => loadFolder(target);
        el.appendChild(s);
    });
}

/* ICONS */
function renderIcons(items) {
    desktop.innerHTML = "";
    items.forEach(item => {
        const div = document.createElement("div");
        div.className = "icon";
        div.dataset.name = item.name;
        div.dataset.type = item.type;
        div.draggable = item.type !== "trash";

        let icon = "📄";
        if (item.type === "trash") {
            icon = "🗑️";
        } else if (item.type === "folder") {
            icon = "📁";
        }
        // Why: visual differentiation for special areas.
        if (item.name === "Common") icon = "🌐";
        if (item.name === "Departments") icon = "🏢";
        if (item.name === "Private") icon = "🔒";
        if (item.name === userPrivateDir) icon = "👤";

        let label = item.name;
        if (inRecycle) {
            const normalized = String(item.name).replace(/\\/g, "/");
            const parts = normalized.split("/").filter(Boolean);
            label = parts.length ? parts[parts.length - 1] : normalized;
            if (parts.length > 1) {
                div.title = normalized;
            }
        }

        div.innerHTML = `
            <span>${icon}</span>
            <div style="word-break:break-all;">${label}</div>
        `;

        div.ondblclick = () => openItem(item.name, item.type);
        div.oncontextmenu = (e) => showContextMenu(e, item);

        div.addEventListener("click", (e) => {
            if (!e.ctrlKey) selected.clear();
            if (selected.has(item.name)) selected.delete(item.name);
            else selected.add(item.name);
            updateSelection();
        });

        desktop.appendChild(div);
    });
}

function updateSelection() {
    document.querySelectorAll(".icon").forEach(el => {
        if (selected.has(el.dataset.name)) el.classList.add("selected");
        else el.classList.remove("selected");
    });
}

/* CONTEXT MENU */
function loadClipboard() {
    try {
        const raw = sessionStorage.getItem(CLIPBOARD_STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && Array.isArray(parsed.items) && parsed.items.length) {
            clipboard = {
                type: parsed.type || null,
                path: typeof parsed.path === "string" ? parsed.path : "",
                items: parsed.items.filter(Boolean),
            };
        }
    } catch (err) {
        clipboard = { type: null, path: null, items: [] };
    }
}

function saveClipboard() {
    sessionStorage.setItem(CLIPBOARD_STORAGE_KEY, JSON.stringify(clipboard));
}

function clearClipboard() {
    clipboard = { type: null, path: null, items: [] };
    sessionStorage.removeItem(CLIPBOARD_STORAGE_KEY);
}

function clipboardHasItems() {
    loadClipboard();
    return Array.isArray(clipboard.items) && clipboard.items.length > 0 && !!clipboard.type;
}

function getClipboardItemNames() {
    if (selected.size > 0) {
        return Array.from(selected);
    }
    if (contextItem && contextItem.name) {
        return [contextItem.name];
    }
    return [];
}

function clearContextMenu() {
    ctxMenu.innerHTML = "";
}

function appendContextSeparator() {
    const hr = document.createElement("hr");
    ctxMenu.appendChild(hr);
}

function appendContextAction(label, handler, enabled = true) {
    const div = document.createElement("div");
    div.textContent = label;
    if (!enabled) {
        div.style.opacity = "0.5";
        div.style.pointerEvents = "none";
        ctxMenu.appendChild(div);
        return;
    }
    div.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        ctxMenu.style.display = "none";
        handler();
    });
    ctxMenu.appendChild(div);
}

function positionContextMenu(e) {
    ctxMenu.style.left = e.pageX + "px";
    ctxMenu.style.top = e.pageY + "px";
    ctxMenu.style.display = "block";
}

document.addEventListener("click", (e) => {
    if (e.target.closest("#contextMenu")) {
        return;
    }
    ctxMenu.style.display = "none";
});

desktop.addEventListener("contextmenu", e => {
    if (!e.target.closest(".icon")) {
        e.preventDefault();
        showEmptyContextMenu(e);
    }
});

function showContextMenu(e, item) {
    e.preventDefault();
    contextItem = item;
    loadClipboard();

    // Why: Restrict actions on top-level system folders (no Compress/zip of Private from Home).
    const isSystemFolder = (currentPath === "" && ["Common", "Departments", "Private", "Trash"].includes(item.name))
        || item.type === "trash";

    clearContextMenu();
    appendContextAction("Open", () => openItem(item.name, item.type));

    if (!isSystemFolder && !inRecycle) {
        appendContextAction("Copy", () => copyItem());
        appendContextAction("Cut", () => cutItem());
        appendContextAction("Paste", () => pasteItem(), clipboardHasItems());
        appendContextAction("Rename", () => renameItem());
        appendContextAction("Delete", () => deleteItem());
        appendContextSeparator();
        appendContextAction("Compress 🗜️", () => zipItem());
        appendContextAction("Move to…", () => moveTo());
        appendContextAction("⭐ Favourite", () => toggleFavourite(item.name));
    } else if (inRecycle) {
        appendContextAction("Restore", () => restoreFromRecycle(item.name));
    }

    positionContextMenu(e);
}


function showEmptyContextMenu(e) {
    e.preventDefault();
    loadClipboard();

    // Why: Prevent creating files in restricted top-levels.
    if (currentPath === "" || currentPath === "Private" || currentPath === "Departments") {
        return;
    }

    clearContextMenu();
    appendContextAction("Create New Folder 📁", () => createFolder());
    appendContextAction("Upload Files ⬆️", () => triggerUpload());
    if (canDownloadPrivateZipBackup()) {
        appendContextAction("Download as ZIP 🗜️", () => downloadZip());
    }
    appendContextAction("Paste 📋", () => pasteItem(), clipboardHasItems());
    appendContextAction("Refresh 🔄", () => refreshFolder());
    appendContextSeparator();
    appendContextAction("Create Year/Month/Day Structure 🗓️", () => createYearMonthDay());
    appendContextAction("Create Year 🗓️", () => createYear());
    appendContextAction("Create Months 🗓️", () => createMonths());
    appendContextAction("Create Days 📅", () => createDays());

    positionContextMenu(e);
}


/* FAVOURITES */
function renderFavourites() {
    const el = document.getElementById("favorites");
    el.innerHTML = "";
    if (favourites.length === 0) {
        el.innerHTML = '<div style="font-size:11px; color:#888;">No favourites yet.</div>';
    }
    favourites.forEach(f => {
        const d = document.createElement("div");
        d.innerHTML = "⭐ " + f;
        d.onclick = () => {
            currentPath = f;
            inRecycle = false;
            tabs[activeTab].path = currentPath;
            tabs[activeTab].title = f.split('/').pop() || "Home";
            loadFolder(currentPath);
        };
        el.appendChild(d);
    });
}
function toggleFavourite(name) {
    const full = currentPath ? currentPath + "/" + name : name;
    if (favourites.includes(full)) {
        favourites = favourites.filter(f => f !== full);
    } else {
        favourites.push(full);
    }
    localStorage.setItem("itm_explorer_favourites", JSON.stringify(favourites));
    renderFavourites();
}

/* FILE OPS */
function renderPreview(res) {
    previewCont.style.display = "block";
    preview.innerHTML = "";
    preview.className = "preview";

    if (res.preview === "image") {
        preview.className = "preview preview-media";
        const img = document.createElement("img");
        img.src = res.url;
        img.alt = "Image preview";
        preview.appendChild(img);
        return;
    }

    if (res.preview === "pdf") {
        preview.className = "preview preview-media";
        const embed = document.createElement("embed");
        embed.src = res.url;
        embed.type = "application/pdf";
        preview.appendChild(embed);
        return;
    }

    if (res.preview === "text") {
        preview.textContent = res.content || "(File is empty or not readable as text)";
        return;
    }

    preview.textContent = res.message || "Preview is not available for this file type.";
}

function openItem(name, type) {
    if (type === "trash" || (name === "Trash" && !currentPath)) {
        openRecycle();
        return;
    }
    if (type === "folder") {
        let nextPath = currentPath ? currentPath + "/" + name : name;
        nextPath = resolveScopedFolderPath(nextPath);
        if (nextPath === null) return;
        currentPath = nextPath;
        tabs[activeTab].path = currentPath;
        tabs[activeTab].title = name;
        inRecycle = false;
        loadFolder(currentPath);
    } else {
        api("open", { item: name }).then(res => {
            if (res.preview !== undefined || res.content !== undefined) {
                renderPreview(res);
            }
        });
    }
}

function createFolder() {
    const name = prompt("Enter folder name:");
    if (!name) return;
    api("createFolder", { name }).then(() => loadFolder(currentPath));
}

function deleteItem() {
    if (!contextItem) return;
    if (!confirm("Are you sure you want to delete '" + contextItem.name + "'?")) return;
    api("delete", { item: contextItem.name }).then(() => loadFolder(currentPath));
}

function renameItem() {
    if (!contextItem) return;
    const name = prompt("Enter new name:", contextItem.name);
    if (!name) return;
    api("rename", { item: contextItem.name, name }).then(() => loadFolder(currentPath));
}

function copyItem() {
    const items = getClipboardItemNames();
    if (!items.length) {
        return;
    }
    clipboard.type = "copy";
    clipboard.path = currentPath;
    clipboard.items = items;
    saveClipboard();
}

function cutItem() {
    const items = getClipboardItemNames();
    if (!items.length) {
        return;
    }
    clipboard.type = "move";
    clipboard.path = currentPath;
    clipboard.items = items;
    saveClipboard();
}

function pasteItem() {
    loadClipboard();
    if (!clipboard.items.length || !clipboard.type) {
        alert("Clipboard is empty. Right-click an item, choose Copy or Cut, open the destination folder, then choose Paste.");
        return;
    }

    const items = clipboard.items.slice();
    const srcPath = clipboard.path;
    const action = clipboard.type;

    const runNext = (index) => {
        if (index >= items.length) {
            if (action === "move") {
                clearClipboard();
            }
            loadFolder(currentPath);
            return;
        }

        const item = items[index];
        const payload = {
            item,
            src_path: srcPath,
            dest: currentPath,
        };
        const apiAction = action === "copy" ? "copy" : "move";
        api(apiAction, payload).then(res => {
            if (!res.ok) {
                alert(res.error || (action === "copy" ? "Copy failed." : "Move failed."));
                return;
            }
            runNext(index + 1);
        });
    };

    runNext(0);
}


function moveTo() {
    if (!contextItem) return;
    clipboard.type = "move";
    clipboard.path = currentPath;
    clipboard.items = [contextItem.name];
    saveClipboard();
    alert("Now navigate to the destination folder and use Paste from the context menu.");
}

function zipItem() {
    if (!contextItem) return;
    api("zip", { item: contextItem.name }).then(() => loadFolder(currentPath));
}

function canDownloadPrivateZipBackup() {
    const normalized = String(currentPath || "").replace(/\\/g, "/").replace(/^\/+|\/+$/g, "");
    // Why: Never offer ZIP at Home or system roots — employees cannot zip Private from explorer root.
    const blockedRoots = ["", "Private", "Common", "Departments", "Trash"];
    if (blockedRoots.includes(normalized)) {
        return false;
    }
    const allowed = "Private/" + userPrivateDir;
    return normalized === allowed;
}

function downloadZip() {
    if (!canDownloadPrivateZipBackup()) {
        alert("ZIP backup is only available for your own Private folder.");
        return;
    }
    const allowed = "Private/" + userPrivateDir;
    window.location = "api.php?downloadZip=1&path=" + encodeURIComponent(allowed);
}

/* CRIAR ESTRUTURAS DE DATA */
function createYearMonthDay() {
    api("createYearMonthDay", {}).then(() => loadFolder(currentPath));
}

function createYear() {
    api("createYear", {}).then(() => loadFolder(currentPath));
}

function createMonths() {
    api("createMonths", {}).then(() => loadFolder(currentPath));
}

function createDays() {
    api("createDays", {}).then(() => loadFolder(currentPath));
}

function refreshFolder() {
    loadFolder(currentPath);
}

/* UPLOAD */
function triggerUpload() {
    const fileInput = document.getElementById("explorerFileInput");
    if (fileInput) fileInput.click();
}
function uploadFiles(files) {
    if (!files || files.length === 0) return;
    const form = new FormData();
    form.append("action", "upload");
    form.append("path", currentPath);
    form.append("csrf_token", "<?= itm_get_csrf_token() ?>");
    for (let f of files) form.append("files[]", f);
    fetch("api.php", { method:"POST", body:form })
        .then((res) => res.json().catch(() => ({})))
        .then((data) => {
            if (data && data.error) {
                alert(data.error);
            }
            loadFolder(currentPath);
        })
        .catch(() => loadFolder(currentPath));
}

/* DRAG & DROP */
let draggedItem = null;

document.addEventListener("dragstart", e => {
    const icon = e.target.closest(".icon");
    if (icon) {
        draggedItem = icon.dataset.name;
        e.dataTransfer.effectAllowed = "move";
    }
});
document.addEventListener("dragover", e => e.preventDefault());
document.addEventListener("drop", e => {
    const target = e.target.closest(".icon");

    // Handle external file drops
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        e.preventDefault();
        e.stopPropagation();
        const uploadArea = document.getElementById("uploadArea");
        if (uploadArea) uploadArea.classList.remove("is-dragover");
        uploadFiles(e.dataTransfer.files);
        return;
    }

    if (!draggedItem) return;
    e.preventDefault();

    if (!target) {
        api("move", { item: draggedItem, dest: currentPath }).then(() => loadFolder(currentPath));
        return;
    }

    if (target.dataset.type === "folder") {
        api("move", { item: draggedItem, dest: (currentPath ? currentPath + "/" : "") + target.dataset.name })
            .then(() => loadFolder(currentPath));
    }
});

(function() {
    const uploadArea = document.getElementById("uploadArea");
    const desktop = document.getElementById("desktop");
    const fileInput = document.getElementById("explorerFileInput");

    if (uploadArea && fileInput) {
        uploadArea.addEventListener("dragover", (e) => {
            if (e.dataTransfer.types && Array.from(e.dataTransfer.types).includes("Files")) {
                e.preventDefault();
                e.stopPropagation();
                uploadArea.classList.add("is-dragover");
            }
        });
        uploadArea.addEventListener("dragleave", (e) => {
            uploadArea.classList.remove("is-dragover");
        });
        uploadArea.addEventListener("click", () => {
            fileInput.click();
        });
        uploadArea.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                fileInput.click();
            }
        });
    }

    if (desktop) {
        desktop.addEventListener("dragover", (e) => {
            if (e.dataTransfer.types && Array.from(e.dataTransfer.types).includes("Files")) {
                e.preventDefault();
                if (uploadArea) uploadArea.classList.add("is-dragover");
            }
        });
    }
})();

/* SEARCH */
function filterIcons() {
    const q = document.getElementById("searchBox").value.toLowerCase();
    document.querySelectorAll(".icon").forEach(el => {
        const name = el.dataset.name.toLowerCase();
        el.style.display = name.includes(q) ? "" : "none";
    });
}

/* RECYCLE BIN */
function openRecycle() {
    inRecycle = true;
    tabs[activeTab].title = "Trash";
    api("listRecycle", {}).then(res => {
        renderIcons(res.items || []);
        renderBreadcrumbs();
        renderTabs();
    });
}
function restoreFromRecycle(name) {
    api("restore", { item: name }).then(openRecycle);
}

/* LOAD FOLDER */
function loadFolder(path) {
    inRecycle = false;
    const scopedPath = resolveScopedFolderPath(path);
    if (scopedPath === null) return;
    currentPath = scopedPath;
    tabs[activeTab].path = currentPath;
    api("list", { path: currentPath }).then(res => {
        renderIcons(res.items || []);
        renderTabs();
        renderBreadcrumbs();
        renderFavourites();
        preview.textContent = "";
        previewCont.style.display = "none";
    });
}

/* INIT */
document.addEventListener("keydown", (e) => {
    if (e.target && e.target.closest("input, textarea")) {
        return;
    }
    if (!e.ctrlKey) {
        return;
    }
    const key = (e.key || "").toLowerCase();
    if (key === "c") {
        e.preventDefault();
        copyItem();
    } else if (key === "x") {
        e.preventDefault();
        cutItem();
    } else if (key === "v") {
        e.preventDefault();
        pasteItem();
    }
});

loadClipboard();
currentPath = "";
loadFolder("");
</script>
<script src="../../js/theme.js"></script>
</body>
</html>
