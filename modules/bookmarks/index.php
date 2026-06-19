<?php


$crud_title = $crud_title ?? 'Bookmarks';


require '../../config/config.php';
require './helpers.php';

// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = (string)@file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);

    // Explicit CSRF check for JSON payload
    if (!itm_validate_csrf_token($itmImportJsonBody['csrf_token'] ?? '')) {
        http_response_code(403);
        die('CSRF validation failed');
    }

    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'bookmarks', (int)($_SESSION['company_id'] ?? 0));
    }

    // Handle Folder Reordering/Reparenting via POST
    if (isset($_POST['action']) && $_POST['action'] === 'move_folder') {
        $fid = (int)$_POST['folder_id'];
        $new_parent = (int)$_POST['new_parent_id'] ?: null;

        $check_res = mysqli_query($conn, "SELECT employee_id FROM bookmark_folders WHERE id = $fid");
        $f_data = mysqli_fetch_assoc($check_res);
        if ($f_data && itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0)) || (int)$f_data['employee_id'] === (int)($_SESSION['employee_id'] ?? 0)) {
            $stmt = mysqli_prepare($conn, "UPDATE bookmark_folders SET parent_folder_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $new_parent, $fid);
            mysqli_stmt_execute($stmt);
        }
    }
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$selected_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch folders
$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);

// Fetch bookmarks for current context
$where = "company_id = $company_id AND active = 1 AND (employee_id = $user_id OR shared = 1)";
if ($view_mode === 'private') $where .= " AND shared = 0";
if ($view_mode === 'shared') $where .= " AND shared = 1";

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (title LIKE '%$s%' OR url LIKE '%$s%' OR notes LIKE '%$s%')";
} elseif ($selected_folder_id) {
    $where .= " AND folder_id = $selected_folder_id";
} else {
    $where .= " AND folder_id IS NULL";
}

$sql = "SELECT * FROM bookmarks WHERE $where ORDER BY title ASC";
$res = mysqli_query($conn, $sql);
$bookmarks = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $bookmarks[] = $row;
}

$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($crud_title); ?> Management</title>
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
        .bookmarks-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px; }
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
        @media (max-width: 480px) {
            .bookmarks-list { grid-template-columns: 1fr; }
        }

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
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1>🔗<?php echo sanitize($crud_title); ?></h1>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="create.php" class="btn btn-primary">➕</a>
            </div>
        </div>
        <div class="bookmarks-layout">
            <div class="bookmarks-sidebar">
                <div style="margin-bottom: 15px;">
                    <form method="GET" style="display: flex; gap: 5px;">
                        <input type="text" name="search" placeholder="Search..." value="<?php echo sanitize($search); ?>" style="flex: 1; padding: 8px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); border-radius: 4px;">
                        <button type="submit" class="btn btn-primary">🔍</button>
                    </form>
                </div>

                <div class="view-filters">
                    <a href="index.php?view=all" class="<?php echo $view_mode === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="index.php?view=private" class="<?php echo $view_mode === 'private' ? 'active' : ''; ?>">🔒 Private</a>
                    <a href="index.php?view=shared" class="<?php echo $view_mode === 'shared' ? 'active' : ''; ?>">🔓 Shared</a>
                </div>

                <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <a href="create.php<?php echo $selected_folder_id ? "?folder_id=$selected_folder_id" : ""; ?>" class="btn btn-primary" style="flex: 1; text-align: center;">➕ Add Bookmark</a>
                    <a href="create_folder.php" class="btn" title="Add Folder" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border);">➕📁</a>
                </div>
                <ul class="folder-tree">
                    <li class="<?php echo ($selected_folder_id === null && $search === '') ? 'active' : ''; ?>" ondrop="drop(event)" ondragover="allowDrop(event)" data-folder-id="0">
                        <div><a href="index.php?view=<?php echo $view_mode; ?>">🏠 Root Bookmarks</a></div>
                    </li>
                    <?php echo bkm_render_folder_tree_html($folder_tree, $selected_folder_id); ?>
                </ul>
            </div>
            <div class="bookmarks-main">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; color: var(--text-primary);">
                            <?php
                            if ($search) {
                                echo "Search Results for '" . sanitize($search) . "'";
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
                                <a href="edit_folder.php?id=<?php echo $selected_folder_id; ?>" class="btn btn-sm">✏️ Edit Folder</a>
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

                <div class="bulk-delete-bar">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    </form>
                </div>

                <div class="bookmarks-list">
    <?php if (empty($bookmarks)): ?>
        <div class="card" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
            <p>No bookmarks found here.</p>
            <a href="create.php<?php echo $selected_folder_id ? "?folder_id=$selected_folder_id" : ""; ?>" class="btn btn-primary">➕</a>
        </div>
    <?php else: ?>

        <table style="width:100%; border-collapse:collapse;">
<thead>
    <tr style="background:var(--bg-secondary);">

        <th style="padding:8px; cursor:pointer;" onclick="sortTable(1, this)">
            Title <span class="sort-arrow"></span>
        </th>
        <th style="padding:8px;">
            Favicon
        </th>
        </th>
        <th style="padding:8px; cursor:pointer;" onclick="sortTable(5, this)">
            URL <span class="sort-arrow"></span>
        </th>

        <th style="padding:8px; cursor:pointer;" onclick="sortTable(5, this)">
            Notes <span class="sort-arrow"></span>
        </th>

        <th style="padding:8px; cursor:pointer; width:120px;" onclick="sortTable(5, this)">
            Visibility <span class="sort-arrow"></span>
        </th>
		<th style="padding:8px; width:120px;" class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
    </tr>
</thead>


            <tbody>
                <?php foreach ($bookmarks as $b): ?>
                    <tr style="border-bottom:1px solid var(--border);">

    <!-- Checkbox -->
    <td style="padding:8px;">
        <input type="checkbox" name="ids[]" value="<?php echo (int)$b['id']; ?>" form="bulk-delete-form">
    </td>

    <!-- Title -->
    <td style="padding:8px;">
        <?php echo sanitize($b['title']); ?>
    </td>

    <!-- Favicon -->
    <td style="padding:8px; text-align:center;">
        <img src="<?php echo bkm_get_favicon_url($b['url']); ?>"
             alt="favicon"
             style="width:16px; height:16px; vertical-align:middle;"
             onerror="this.style.display='none';">
    </td>

    <!-- URL -->
    <td style="padding:8px;">
        <a href="<?php echo sanitize($b['url']); ?>"
           target="_blank"
           rel="nofollow noreferrer noopener"
           style="color:var(--accent); text-decoration:none;">
            <?php echo sanitize($b['url']); ?>
        </a>
    </td>

    <!-- Notes -->
    <td style="padding:8px;">
        <?php echo sanitize($b['notes']); ?>
    </td>

    <!-- Visibility -->
    <td style="padding:8px;">
        <?php if ($b['shared']): ?>
            🔓 Shared
        <?php else: ?>
            🔒 Private
        <?php endif; ?>
    </td>

    <!-- Actions -->
    <td style="padding:8px;">
        <button class="btn btn-sm copy-btn"
                onclick="copyUrl('<?php echo addslashes($b['url']); ?>')"
                title="Copy URL">🗐</button>

        <?php if (bkm_can_edit_bookmark($b, $user_id, $is_admin)): ?>
            <a href="edit.php?id=<?php echo $b['id']; ?>" class="btn btn-sm">✏️</a>

            <form method="POST" action="delete.php"
                  style="display:inline;"
                  onsubmit="return confirm('Delete?');">
                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                <input type="hidden" name="bulk_action" value="single_delete">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
            </form>
        <?php endif; ?>
    </td>

</tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>


            </div>
        </div>
    </div>
</div>

<form id="move-folder-form" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
    <input type="hidden" name="action" value="move_folder">
    <input type="hidden" name="folder_id" id="move-folder-id">
    <input type="hidden" name="new_parent_id" id="move-new-parent-id">
</form>

<script src="../../js/theme.js"></script>
<script src="./export.js"></script>
<script>
/**
 * Custom logic for bulk delete in tree view because shared script expects table rows.
 */
(function() {
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const toggleButton = document.getElementById('bulk-delete-toggle');
    const checkboxWraps = document.querySelectorAll('.bookmark-checkbox-wrap');
    const checkboxes = document.querySelectorAll('input[name="ids[]"]');
    let selectionMode = false;

    let cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'btn btn-sm';
    cancelButton.textContent = 'Cancel';
    cancelButton.style.display = 'none';
    toggleButton.insertAdjacentElement('afterend', cancelButton);

    function setSelectionVisibility(visible) {
        checkboxWraps.forEach(w => w.style.display = visible ? 'block' : 'none');
    }

    function exitSelectionMode() {
        selectionMode = false;
        setSelectionVisibility(false);
        toggleButton.textContent = 'Select to Delete';
        cancelButton.style.display = 'none';
        checkboxes.forEach(cb => cb.checked = false);
    }

    cancelButton.addEventListener('click', exitSelectionMode);

    bulkDeleteForm.addEventListener('submit', function(e) {
        if (e.submitter !== toggleButton) return;

        if (!selectionMode) {
            e.preventDefault();
            selectionMode = true;
            setSelectionVisibility(true);
            toggleButton.textContent = 'Delete Selected';
            cancelButton.style.display = 'inline-block';
            return;
        }

        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Please select at least one bookmark to delete.');
            return;
        }

        if (!confirm('Delete selected bookmarks?')) {
            e.preventDefault();
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(function(el) {
            el.classList.remove('show');
        });
    });
})();

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

function drop(ev) {
    ev.preventDefault();
    const target = ev.target.closest('[data-folder-id]');
    if (target) {
        target.removeAttribute('drag-over');
        const folderId = ev.dataTransfer.getData("folder_id");
        const newParentId = target.getAttribute('data-folder-id');

        if (folderId !== newParentId) {
            document.getElementById('move-folder-id').value = folderId;
            document.getElementById('move-new-parent-id').value = newParentId === "0" ? "" : newParentId;
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
<script>
let sortState = {};

function sortTable(colIndex, headerEl) {
    const table = document.querySelector(".bookmarks-list table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    // Toggle direction
    sortState[colIndex] = sortState[colIndex] === "asc" ? "desc" : "asc";

    // Reset all arrows
    document.querySelectorAll(".sort-arrow").forEach(a => a.textContent = "");

    // Set arrow on clicked column
    headerEl.querySelector(".sort-arrow").textContent =
        sortState[colIndex] === "asc" ? "▲" : "▼";

    rows.sort((a, b) => {
        const A = a.children[colIndex].innerText.trim().toLowerCase();
        const B = b.children[colIndex].innerText.trim().toLowerCase();

        if (A < B) return sortState[colIndex] === "asc" ? -1 : 1;
        if (A > B) return sortState[colIndex] === "asc" ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>

</body>
</html>
