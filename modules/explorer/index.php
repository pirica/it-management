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

require_once '../../config/config.php';

// Why: Protection Zone - User needs to be logged in and have a company selected.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Why: Ensure the root /files/{company_id} directory exists via an API call or logic.
// Handled by api.php on first list, but we can do a quick check here too.
$storage_root = ROOT_PATH . 'files/' . $company_id;
if (!is_dir($storage_root)) {
    @mkdir($storage_root, 0777, true);
}

?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Explorer - <?= sanitize($app_name) ?></title>
<link rel="icon" type="image/png" href="<?= sanitize($favicon_url) ?>">

<style>
body {
    font-family: "Segoe UI", "Tahoma", sans-serif;
    background: #f3f6fd;
    margin: 0;
    padding: 0;
    transition: background 0.3s, color 0.3s;
}
.dark { background: #1e1e1e; color: #eee; }

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
    background: #ffffff;
    border-right: 1px solid #ccc;
    padding: 20px;
    z-index: 9998;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
}
#sidebar.open { transform: translateX(0); }
.dark #sidebar { background:#2a2a2a; border-color:#444; color:#eee; }
#sidebar h3, #sidebar h4 { margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 8px; }
.dark #sidebar h3, .dark #sidebar h4 { border-color: #444; }
#sidebar div { margin-bottom:12px; cursor:pointer; padding: 6px; border-radius: 4px; }
#sidebar div:hover { background: #f0f0f0; }
.dark #sidebar div:hover { background: #3a3a3a; }

/* TOPBAR */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}
button {
    padding:8px 16px;
    border:none;
    background:#2a4d9b;
    color:white;
    border-radius:6px;
    cursor:pointer;
    font-size: 13px;
    font-weight: 500;
}
button:hover { background:#1d3570; }
#sidebarToggle {
    background:#2a4d9b;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:18px;
    padding:6px 10px;
}
#sidebarToggle:hover { background:#1d3570; }
.dark #sidebarToggle { background:#444; }

/* SEARCH */
.search {
    padding:8px 12px;
    width:250px;
    border-radius:6px;
    border:1px solid #ccc;
    font-size: 13px;
}
.dark .search { background: #333; color: #fff; border-color: #555; }

/* TABS */
.tabs {
    display:flex;
    gap:5px;
    margin-bottom:0;
    border-bottom: 1px solid #ccc;
}
.dark .tabs { border-color: #444; }
.tab {
    padding:8px 16px;
    background:#e1e7f5;
    border-radius:8px 8px 0 0;
    cursor:pointer;
    font-size:13px;
    border: 1px solid transparent;
    border-bottom: none;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tab.active {
    background:#ffffff;
    font-weight:bold;
    border-color: #ccc;
}
.dark .tab { background:#333; color: #aaa; }
.dark .tab.active { background:#2a2a2a; border-color: #444; color: #fff; }

/* BREADCRUMBS */
.breadcrumbs-bar {
    background: #fff;
    padding: 10px 15px;
    border-radius: 0 0 8px 8px;
    border: 1px solid #ccc;
    border-top: none;
    margin-bottom: 15px;
    font-size: 13px;
    display: flex;
    align-items: center;
}
.dark .breadcrumbs-bar { background: #2a2a2a; border-color: #444; }
.breadcrumbs span {
    cursor:pointer;
    color:#2a4d9b;
}
.dark .breadcrumbs span { color: #5a82e8; }
.breadcrumbs span:hover { text-decoration:underline; }

/* DESKTOP (FILE GRID) */
.desktop {
    display:flex;
    flex-wrap:wrap;
    gap:20px;
    padding:25px;
    background:#ffffff;
    border-radius:10px;
    min-height:400px;
    position:relative;
    border: 1px solid #eee;
}
.dark .desktop { background:#212121; border-color: #333; }

.icon {
    width:100px;
    text-align:center;
    cursor:pointer;
    font-size:12px;
    user-select:none;
    padding: 10px;
    border-radius: 8px;
    transition: background 0.2s;
}
.icon:hover { background: #f0f7ff; }
.dark .icon:hover { background: #2d2d2d; }

.icon-emoji {
    font-size:48px;
    margin-bottom:8px;
    display: block;
}
.icon.selected {
    background: #e5f1ff;
    outline:1px solid #4a90e2;
}
.dark .icon.selected { background: #334; outline-color: #5a82e8; }

/* CONTEXT MENU */
.context-menu {
    position:absolute;
    display:none;
    background:#ffffff;
    border:1px solid #ccc;
    border-radius:8px;
    width:200px;
    z-index:9999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 4px 0;
}
.context-menu div {
    padding:10px 15px;
    cursor:pointer;
    font-size:13px;
}
.context-menu div:hover { background:#e5f1ff; }
.dark .context-menu { background:#333; color:#fff; border-color:#555; }
.dark .context-menu div:hover { background: #444; }
.context-menu hr { border: none; border-top: 1px solid #eee; margin: 4px 0; }
.dark .context-menu hr { border-color: #444; }

/* PREVIEW */
.preview {
    margin-top:20px;
    padding:15px;
    background:#ffffff;
    border-radius:8px;
    max-height:300px;
    overflow:auto;
    font-size:13px;
    border: 1px solid #eee;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.dark .preview { background:#2a2a2a; color:#eee; border-color: #333; }

/* UPLOAD */
.upload-area {
    border:2px dashed #2a4d9b;
    padding:20px;
    text-align:center;
    border-radius:10px;
    margin-bottom:15px;
    background:rgba(255,255,255,0.8);
    transition: background 0.3s;
}
.upload-area.dragover { background:#e1e7f5; border-style: solid; }
.dark .upload-area { background:#2a2a2a; border-color:#444; }
.upload-area input[type="file"] { cursor: pointer; }

/* BADGES */
.badge-private { font-size: 10px; background: #ff4757; color: #fff; padding: 2px 4px; border-radius: 3px; position: absolute; top: 5px; right: 5px; }

</style>
</head>
<body>

<div id="sidebar">
    <h3>📌 Quick Access</h3>
    <div onclick="loadFolder('')">🏠 Home (Company Root)</div>
    <div onclick="loadFolder('common')">🌐 Common Area</div>
    <div onclick="loadFolder('department')">🏢 Department Area</div>
    <div onclick="loadFolder('private')">🔒 Private Area</div>
    <div onclick="openRecycle()">🗑 Recycle Bin</div>

    <h4>⭐ Favourites</h4>
    <div id="favourites"></div>
</div>

<div id="main">
    <div class="topbar">
        <div style="display:flex; gap:10px;">
            <button id="sidebarToggle">☰</button>
            <button onclick="toggleDark()">🌙 Toggle Theme</button>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <input id="searchBox" class="search" placeholder="Search files..." oninput="filterIcons()">
            <span style="font-size: 13px; color: #666;"><?= sanitize($username) ?></span>
        </div>
    </div>

    <div id="tabs" class="tabs"></div>
    <div class="breadcrumbs-bar">
        <div id="breadcrumbs" class="breadcrumbs"></div>
    </div>

    <div id="uploadArea" class="upload-area">
        Drop files here or <input type="file" multiple onchange="uploadFiles(this.files)">
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
let clipboard = { type: null, items: [] };
let contextItem = null;
let inRecycle = false;
let favourites = JSON.parse(localStorage.getItem("itm_explorer_favourites") || "[]");

const sidebar   = document.getElementById("sidebar");
const btnToggle = document.getElementById("sidebarToggle");
const desktop   = document.getElementById("desktop");
const ctxMenu   = document.getElementById("contextMenu");
const preview   = document.getElementById("preview");
const previewCont = document.getElementById("previewContainer");

btnToggle.onclick = () => sidebar.classList.toggle("open");

function applyDarkMode() {
    if (localStorage.getItem("itm_dark_mode") === "true") {
        document.body.classList.add("dark");
    }
}
applyDarkMode();

function toggleDark(){
    document.body.classList.toggle("dark");
    localStorage.setItem("itm_dark_mode", document.body.classList.contains("dark"));
}

/* API WRAPPER */
function api(action, data = {}) {
    data.action = action;
    data.path = currentPath;
    return fetch("api.php", {
        method: "POST",
        body: new URLSearchParams(data)
    }).then(r => r.json());
}

/* TABS */
function renderTabs() {
    const el = document.getElementById("tabs");
    el.innerHTML = "";
    tabs.forEach((t, i) => {
        const div = document.createElement("div");
        div.className = "tab" + (i === activeTab ? " active" : "");
        div.textContent = t.title || "Explorer";
        div.title = t.path || "/";
        div.onclick = () => {
            activeTab = i;
            currentPath = tabs[i].path;
            inRecycle = false;
            loadFolder(currentPath);
        };
        el.appendChild(div);
    });
}

/* BREADCRUMBS */
function renderBreadcrumbs() {
    const el = document.getElementById("breadcrumbs");
    if (inRecycle) {
        el.textContent = "🗑 Recycle Bin";
        return;
    }
    const parts = currentPath.split("/").filter(Boolean);
    let html = `<span onclick="goToBreadcrumb(0)">Home</span>`;
    let acc = "";
    parts.forEach((p, i) => {
        acc += "/" + p;
        // Why: Make department IDs and usernames more readable in breadcrumbs if possible.
        let label = p;
        html += " / " + `<span onclick="goToBreadcrumb(${i+1})">${label}</span>`;
    });
    el.innerHTML = html;
}
function goToBreadcrumb(index) {
    if (index === 0) {
        currentPath = "";
    } else {
        const parts = currentPath.split("/").filter(Boolean).slice(0, index);
        currentPath = parts.join("/");
    }
    inRecycle = false;
    tabs[activeTab].path = currentPath;
    loadFolder(currentPath);
}

/* RENDER ICONS */
let currentList = [];

function renderIcons(list) {
    currentList = list;
    desktop.innerHTML = "";
    selected.clear();

    if (list.length === 0) {
        desktop.innerHTML = '<div style="width:100%; text-align:center; color:#888; padding-top:50px;">This folder is empty.</div>';
        return;
    }

    list.forEach(item => {
        let emoji = "📄";
        if (item.type === "folder") {
            emoji = "📁";
            // Special emojis for top-level access areas
            if (currentPath === "") {
                if (item.name === "common") emoji = "🌐";
                if (item.name === "department") emoji = "🏢";
                if (item.name === "private") emoji = "🔒";
            } else if (currentPath === "private") {
                emoji = "👤";
            }
        }
        if (item.type === "zip")    emoji = "🗜️";
        if (item.type === "txt")    emoji = "📝";

        const div = document.createElement("div");
        div.className = "icon";
        div.draggable = true;
        div.dataset.name = item.name;
        div.dataset.type = item.type;

        div.innerHTML = `
            <span class="icon-emoji">${emoji}</span>
            <div style="word-break:break-all;">${item.name}</div>
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
document.addEventListener("click", () => {
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

    // Why: Restrict actions on top-level system folders.
    const isSystemFolder = (currentPath === "" && ["common", "department", "private"].includes(item.name));

    let html = `<div onclick="openItem('${item.name}', '${item.type}')">Open</div>`;

    if (!isSystemFolder && !inRecycle) {
        html += `
            <div onclick="copyItem()">Copy</div>
            <div onclick="cutItem()">Cut</div>
            <div onclick="pasteItem()">Paste</div>
            <div onclick="renameItem()">Rename</div>
            <div onclick="deleteItem()">Delete</div>
            <hr>
            <div onclick="zipItem()">Compress (Zip)</div>
            <div onclick="moveTo()">Move to…</div>
            <div onclick="toggleFavourite('${item.name}')">⭐ Favourite</div>
        `;
    } else if (inRecycle) {
        html += `
            <div onclick="restoreFromRecycle('${item.name}')">Restore</div>
        `;
    }

    ctxMenu.innerHTML = html;
    ctxMenu.style.left = e.pageX + "px";
    ctxMenu.style.top  = e.pageY + "px";
    ctxMenu.style.display = "block";
}


function showEmptyContextMenu(e) {
    e.preventDefault();

    // Why: Prevent creating files in restricted top-levels.
    if (currentPath === "" || currentPath === "private" || currentPath === "department") {
        return;
    }

    ctxMenu.innerHTML = `
        <div onclick="createFolder()">Create New Folder</div>
        <div onclick="triggerUpload()">Upload Files</div>
        <div onclick="downloadZip()">Download as ZIP</div>
        <div onclick="pasteItem()">Paste</div>
        <hr>
        <div onclick="createYearMonthDay()">Create Year/Month/Day Structure</div>
    `;

    ctxMenu.style.left = e.pageX + "px";
    ctxMenu.style.top  = e.pageY + "px";
    ctxMenu.style.display = "block";
}


/* FAVOURITES */
function renderFavourites() {
    const el = document.getElementById("favourites");
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
function openItem(name, type) {
    if (type === "folder") {
        currentPath = currentPath ? currentPath + "/" + name : name;
        tabs[activeTab].path = currentPath;
        tabs[activeTab].title = name;
        inRecycle = false;
        loadFolder(currentPath);
    } else {
        api("open", { item: name }).then(res => {
            if (res.content !== undefined) {
                previewCont.style.display = "block";
                preview.textContent = res.content || "(File is empty or not readable as text)";
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
    if (!contextItem) return;
    clipboard.type = "copy";
    clipboard.items = [contextItem.name];
}

function cutItem() {
    if (!contextItem) return;
    clipboard.type = "move";
    clipboard.items = [contextItem.name];
}

function pasteItem() {
    if (!clipboard.items.length) {
        alert("Clipboard is empty.");
        return;
    }

    const item = clipboard.items[0];

    if (clipboard.type === "copy") {
        api("copy", { item }).then(() => loadFolder(currentPath));
    } else if (clipboard.type === "move") {
        api("move", { 
            item,
            dest: currentPath
        }).then(res => {
            if (res.ok) {
                clipboard.items = [];
                clipboard.type = null;
                loadFolder(currentPath);
            } else {
                alert(res.error || "Move failed.");
            }
        });
    }
}


function moveTo() {
    if (!contextItem) return;
    clipboard.type = "move";
    clipboard.items = [contextItem.name];
    alert("Now navigate to the destination folder and use PASTE from the context menu.");
}

function zipItem() {
    if (!contextItem) return;
    api("zip", { item: contextItem.name }).then(() => loadFolder(currentPath));
}

function downloadZip() {
    window.location = "api.php?downloadZip=1&path=" + encodeURIComponent(currentPath);
}

/* CRIAR ESTRUTURAS DE DATA */
function createYearMonthDay() {
    api("createYearMonthDay", {}).then(() => loadFolder(currentPath));
}

/* UPLOAD */
function triggerUpload() {
    const input = document.createElement("input");
    input.type = "file";
    input.multiple = true;
    input.onchange = () => uploadFiles(input.files);
    input.click();
}
function uploadFiles(files) {
    const form = new FormData();
    form.append("action", "upload");
    form.append("path", currentPath);
    for (let f of files) form.append("files[]", f);
    fetch("api.php", { method:"POST", body:form })
        .then(() => loadFolder(currentPath));
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
    e.preventDefault();
    const target = e.target.closest(".icon");

    if (!draggedItem) return;

    if (!target) {
        api("move", { item: draggedItem, dest: currentPath }).then(() => loadFolder(currentPath));
        return;
    }

    if (target.dataset.type === "folder") {
        api("move", { item: draggedItem, dest: (currentPath ? currentPath + "/" : "") + target.dataset.name })
            .then(() => loadFolder(currentPath));
    }
});

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
    currentPath = path;
    api("list", { path }).then(res => {
        renderIcons(res.items || []);
        renderTabs();
        renderBreadcrumbs();
        renderFavourites();
        preview.textContent = "";
        previewCont.style.display = "none";
    });
}

/* INIT */
currentPath = "";
loadFolder("");
</script>
</body>
</html>
