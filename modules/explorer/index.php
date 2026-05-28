<?php
$base = __DIR__ . "/data";
$recycle = __DIR__ . "/recycle_bin";
if (!is_dir($base)) mkdir($base, 0777, true);
if (!is_dir($recycle)) mkdir($recycle, 0777, true);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Windows 11 Web Explorer</title>

<style>
body {
    font-family: "Segoe UI", sans-serif;
    background: #f3f6fd;
    margin: 0;
    padding: 0;
    transition: .3s;
}
.dark { background: #1e1e1e; color: #eee; }

#main {
    margin: 20px;
    transition: .3s;
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
    transition: transform .25s ease;
}
#sidebar.open { transform: translateX(0); }
.dark #sidebar { background:#2a2a2a; border-color:#444; color:#eee; }
#sidebar h3, #sidebar h4 { margin-top:0; }
#sidebar div { margin-bottom:10px; cursor:pointer; }

/* TOPBAR */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
}
button {
    padding:6px 12px;
    border:none;
    background:#2a4d9b;
    color:white;
    border-radius:6px;
    cursor:pointer;
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
    padding:6px;
    width:250px;
    border-radius:6px;
    border:1px solid #ccc;
}

/* TABS */
.tabs {
    display:flex;
    gap:5px;
    margin-bottom:10px;
}
.tab {
    padding:6px 12px;
    background:#e1e7f5;
    border-radius:6px 6px 0 0;
    cursor:pointer;
    font-size:13px;
}
.tab.active {
    background:#ffffff;
    font-weight:bold;
}
.dark .tab { background:#333; }
.dark .tab.active { background:#444; }

/* BREADCRUMBS */
.breadcrumbs {
    margin-bottom:10px;
    font-size:13px;
}
.breadcrumbs span {
    cursor:pointer;
    color:#2a4d9b;
}
.breadcrumbs span:hover { text-decoration:underline; }

/* DESKTOP */
.desktop {
    display:flex;
    flex-wrap:wrap;
    gap:25px;
    padding:20px;
    background:#e7ecf7;
    border-radius:10px;
    min-height:300px;
    position:relative;
}
.dark .desktop { background:#2a2a2a; }

.icon {
    width:90px;
    text-align:center;
    cursor:pointer;
    font-size:12px;
    user-select:none;
}
.icon-emoji {
    font-size:40px;
    margin-bottom:4px;
}
.icon.selected {
    outline:2px solid #4a90e2;
    border-radius:6px;
}

/* CONTEXT MENU */
.context-menu {
    position:absolute;
    display:none;
    background:#ffffff;
    border:1px solid #ccc;
    border-radius:6px;
    width:200px;
    z-index:9999;
}
.context-menu div {
    padding:8px;
    cursor:pointer;
    font-size:13px;
}
.context-menu div:hover { background:#e5f1ff; }
.dark .context-menu { background:#333; color:#fff; border-color:#555; }

/* PREVIEW */
.preview {
    margin-top:10px;
    padding:10px;
    background:#ffffff;
    border-radius:6px;
    max-height:250px;
    overflow:auto;
    font-size:13px;
}
.dark .preview { background:#333; color:#fff; }

/* UPLOAD */
.upload-area {
    border:2px dashed #2a4d9b;
    padding:15px;
    text-align:center;
    border-radius:10px;
    margin-bottom:10px;
    background:rgba(255,255,255,0.7);
}
.upload-area.dragover { background:#dce7ff; }
.dark .upload-area { background:#333; border-color:#777; }
</style>
</head>
<body>

<div id="sidebar">
    <h3>📌 Quick Access</h3>
    <div onclick="loadFolder('')">🏠 Data</div>
    <div onclick="openRecycle()">🗑 Recycle Bin</div>

    <h4>⭐ Favoritos</h4>
    <div id="favorites"></div>
</div>

<div id="main">
    <div class="topbar">
        <div>
            <button onclick="toggleDark()">🌙 Dark</button>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <input id="searchBox" class="search" placeholder="Search..." oninput="filterIcons()">
            <button id="sidebarToggle">☰</button>
        </div>
    </div>

    <div id="tabs" class="tabs"></div>
    <div id="breadcrumbs" class="breadcrumbs"></div>

    <div id="uploadArea" class="upload-area">
        Drop files here or <input type="file" multiple onchange="uploadFiles(this.files)">
    </div>

    <div id="desktop" class="desktop"></div>
    <div id="preview" class="preview"></div>
    <div id="contextMenu" class="context-menu"></div>
</div>

<script>
let tabs = [{ path: "", title: "Data" }];
let activeTab = 0;
let currentPath = "";
let selected = new Set();
let clipboard = { type: null, items: [] };
let contextItem = null;
let inRecycle = false;
let favorites = JSON.parse(localStorage.getItem("favorites") || "[]");

const sidebar   = document.getElementById("sidebar");
const btnToggle = document.getElementById("sidebarToggle");
const desktop   = document.getElementById("desktop");
const ctxMenu   = document.getElementById("contextMenu");
const preview   = document.getElementById("preview");

btnToggle.onclick = () => sidebar.classList.toggle("open");

function applyDarkMode() {
    if (localStorage.getItem("dark") === "true") {
        document.body.classList.add("dark");
    }
}
applyDarkMode();

function toggleDark(){
    document.body.classList.toggle("dark");
    localStorage.setItem("dark", document.body.classList.contains("dark"));
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
        div.textContent = t.title || "Tab";
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
    let html = `<span onclick="goToBreadcrumb(0)">Data</span>`;
    let acc = "";
    parts.forEach((p, i) => {
        acc += "/" + p;
        html += " / " + `<span onclick="goToBreadcrumb(${i+1})">${p}</span>`;
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

/* RENDER ICONS (EMOJIS) */
let currentList = [];

function renderIcons(list) {
    currentList = list;
    desktop.innerHTML = "";
    selected.clear();
    list.forEach(item => {
        let emoji = "📄";
        if (item.type === "folder") emoji = "📁";
        if (item.type === "zip")    emoji = "🗜️";
        if (item.type === "txt")    emoji = "📝";

        const div = document.createElement("div");
        div.className = "icon";
        div.draggable = true;
        div.dataset.name = item.name;
        div.dataset.type = item.type;

        div.innerHTML = `
            <div class="icon-emoji">${emoji}</div>
            <div>${item.name}</div>
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

    ctxMenu.innerHTML = `
        <div onclick="openItem('${item.name}', '${item.type}')">Open</div>
        <div onclick="copyItem()">Copy</div>
        <div onclick="cutItem()">Cut</div>
        <div onclick="pasteItem()">Paste</div>
        <div onclick="renameItem()">Rename</div>
        <div onclick="deleteItem()">Delete</div>
        <div onclick="zipItem()">Zip</div>
        <div onclick="unzipItem()">Unzip</div>
        <div onclick="moveTo()">Move to…</div>
        <div onclick="toggleFavorite('${item.name}')">⭐ Favorito</div>
    `;

    ctxMenu.style.left = e.pageX + "px";
    ctxMenu.style.top  = e.pageY + "px";
    ctxMenu.style.display = "block";
}


function showEmptyContextMenu(e) {
    e.preventDefault();

    ctxMenu.innerHTML = `
        <div onclick="createFolder()">Criar Pasta</div>
        <div onclick="triggerUpload()">Upload</div>
        <div onclick="downloadZip()">Download ZIP</div>
        <div onclick="pasteItem()">Paste</div>
        <hr>
        <div onclick="createYearMonthDay()">Criar Ano/Mês/Dia</div>
        <div onclick="createYears()">Criar Anos</div>
        <div onclick="createMonths()">Criar Meses</div>
        <div onclick="createDays()">Criar Dias</div>
    `;

    ctxMenu.style.left = e.pageX + "px";
    ctxMenu.style.top  = e.pageY + "px";
    ctxMenu.style.display = "block";
}


/* FAVORITOS */
function renderFavorites() {
    const el = document.getElementById("favorites");
    el.innerHTML = "";
    favorites.forEach(f => {
        const d = document.createElement("div");
        d.textContent = "⭐ " + f;
        d.onclick = () => {
            currentPath = f;
            inRecycle = false;
            tabs[activeTab].path = currentPath;
            loadFolder(currentPath);
        };
        el.appendChild(d);
    });
}
function toggleFavorite(name) {
    const full = currentPath ? currentPath + "/" + name : name;
    if (favorites.includes(full)) {
        favorites = favorites.filter(f => f !== full);
    } else {
        favorites.push(full);
    }
    localStorage.setItem("favorites", JSON.stringify(favorites));
    renderFavorites();
}

/* FILE OPS */
function openItem(name, type) {
    if (type === "folder") {
        currentPath = currentPath ? currentPath + "/" + name : name;
        tabs[activeTab].path = currentPath;
        inRecycle = false;
        loadFolder(currentPath);
    } else {
        api("open", { item: name }).then(res => {
            preview.textContent = res.content || "";
        });
    }
}

function createFolder() {
    const name = prompt("Folder name:");
    if (!name) return;
    api("createFolder", { name }).then(() => loadFolder(currentPath));
}

function deleteItem() {
    if (!contextItem) return;
    if (!confirm("Delete " + contextItem.name + "?")) return;
    api("delete", { item: contextItem.name }).then(() => loadFolder(currentPath));
}

function renameItem() {
    if (!contextItem) return;
    const name = prompt("New name:", contextItem.name);
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
        alert("Clipboard vazio");
        return;
    }

    const item = clipboard.items[0];

    if (clipboard.type === "copy") {
        api("copy", { item }).then(() => loadFolder(currentPath));
        return;
    }

    if (clipboard.type === "move") {
        api("move", { 
            item,
            dest: currentPath   // ← AQUI ESTÁ A CORREÇÃO
        }).then(() => {
            clipboard.items = [];
            clipboard.type = null;
            loadFolder(currentPath);
        });
    }
}


function moveTo() {
    if (!contextItem) return;
    clipboard.type = "move";
    clipboard.items = [contextItem.name];
    alert("Agora navega até à pasta destino e usa PASTE.");
}

function zipItem() {
    if (!contextItem) return;
    api("zip", { item: contextItem.name }).then(() => loadFolder(currentPath));
}
function unzipItem() {
    if (!contextItem) return;
    api("unzip", { item: contextItem.name }).then(() => loadFolder(currentPath));
}

function downloadZip() {
    window.location = "api.php?downloadZip=1&path=" + encodeURIComponent(currentPath);
}

/* CRIAR ESTRUTURAS DE DATA */
function createYearMonthDay() {
    api("createYearMonthDay", {}).then(() => loadFolder(currentPath));
}
function createYears() {
    api("createYears", {}).then(() => loadFolder(currentPath));
}
function createMonths() {
    api("createMonths", {}).then(() => loadFolder(currentPath));
}
function createDays() {
    api("createDays", {}).then(() => loadFolder(currentPath));
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

/* DRAG & DROP (Opção B) */
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
        // soltar no vazio → mover para pasta atual
        api("move", { item: draggedItem }).then(() => loadFolder(currentPath));
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
    });
}
function restoreFromRecycle(name) {
    api("restore", { item: name }).then(openRecycle);
}
function emptyRecycle() {
    if (!confirm("Esvaziar recycle bin?")) return;
    api("emptyRecycle", {}).then(openRecycle);
}

/* LOAD FOLDER */
function loadFolder(path) {
    inRecycle = false;
    api("list", { path }).then(res => {
        renderIcons(res.items || []);
        renderTabs();
        renderBreadcrumbs();
        renderFavorites();
        preview.textContent = "";
    });
}

/* INIT */
currentPath = "";
loadFolder("");
</script>
</body>
</html>
