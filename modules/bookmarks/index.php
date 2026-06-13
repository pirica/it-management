<?php
/**
 * Bookmarks Tree View
 */
require_once '../../config/config.php';
// require_once '../../includes/auth.php';
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit;
}
require_once './helpers.php';

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$is_admin = (strtolower($_SESSION['role_name'] ?? '') === 'admin');

$selected_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

// Tree logic
$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);

// Fetch bookmarks for current view
$where = "b.company_id = $company_id AND b.active = 1 AND (b.user_id = $user_id OR b.shared = 1)";
if ($selected_folder_id) {
    $where .= " AND b.folder_id = $selected_folder_id";
} else {
    $where .= " AND (b.folder_id IS NULL OR b.folder_id = 0)";
}

$sql = "SELECT b.* FROM bookmarks b WHERE $where ORDER BY b.title ASC";
$res = mysqli_query($conn, $sql);
$bookmarks = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $bookmarks[] = $row;
}

$csrfToken = itm_get_csrf_token();

// Handle Folder Move (AJAX or Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_folder') {
    $move_fid = (int)$_POST['folder_id'];
    $new_parent = $_POST['new_parent_id'] === "" ? "NULL" : (int)$_POST['new_parent_id'];

    // Simple validation: Ensure folder belongs to company
    $checkSql = "SELECT id FROM bookmark_folders WHERE id = $move_fid AND company_id = $company_id";
    $checkRes = mysqli_query($conn, $checkSql);
    if (mysqli_num_rows($checkRes) > 0) {
        $upd = "UPDATE bookmark_folders SET parent_folder_id = $new_parent WHERE id = $move_fid";
        mysqli_query($conn, $upd);
    }
    header("Location: index.php" . ($selected_folder_id ? "?folder_id=$selected_folder_id" : ""));
    exit;
}

$crud_title = "Bookmarks";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($crud_title); ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .itm-bookmarks-container { display: flex; gap: 20px; }
        .itm-folder-sidebar { width: 280px; flex-shrink: 0; }
        .itm-bookmarks-main { flex: 1; }

        .itm-folder-tree { list-style: none; padding: 0; margin: 0; }
        .itm-folder-tree-item { margin: 2px 0; }
        .itm-folder-tree-row { display: flex; align-items: center; padding: 6px 10px; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
        .itm-folder-tree-row:hover { background: var(--bg-secondary); }
        .itm-folder-tree-item.active > .itm-folder-tree-row { background: var(--bg-secondary); font-weight: bold; }
        .itm-folder-tree-row a { text-decoration: none; color: var(--text-primary); flex: 1; display: block; }

        .itm-folder-tree-children { list-style: none; padding: 0; margin: 0; }

        [drag-over="true"] { outline: 2px dashed var(--accent); background: rgba(var(--accent-rgb), 0.1); }

        .bookmarks-list table { width: 100%; border-collapse: collapse; }
        .bookmarks-list th, .bookmarks-list td { padding: 12px 8px; text-align: left; border-bottom: 1px solid var(--border); }
        .bookmarks-list tr:hover { background: rgba(0,0,0,0.02); }

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
            <div class="itm-bookmarks-container">

                <div class="itm-folder-sidebar">
                    <div class="card" style="padding: 15px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 style="margin:0;">Folders</h3>
                            <a href="create_folder.php" class="btn btn-sm" title="New Folder">📁➕</a>
                        </div>

                        <ul class="itm-folder-tree">
                            <li class="itm-folder-tree-item <?php echo $selected_folder_id === null ? 'active' : ''; ?>" data-folder-id="0" ondrop="drop(event)" ondragover="allowDrop(event)">
                                <div class="itm-folder-tree-row">
                                    <a href="index.php">🏠 Root</a>
                                </div>
                            </li>
                            <?php echo bkm_render_folder_tree_html($folder_tree, $selected_folder_id); ?>
                        </ul>
                    </div>
                </div>

                <div class="itm-bookmarks-main">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h1 style="margin:0;">
                            <?php
                            if ($selected_folder_id) {
                                $current_folder = null;
                                foreach($all_folders as $f) { if($f['id'] == $selected_folder_id) $current_folder = $f; }
                                echo sanitize($current_folder['name'] ?? 'Folder');
                            } else {
                                echo "Root Bookmarks";
                            }
                            ?>
                        </h1>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <div class="dropdown" style="position: relative;">
                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show'); event.stopPropagation();">Tools ⚙️</button>
                                <div class="dropdown-menu" style="right: 0; min-width: 180px;">
                                    <a class="dropdown-item" href="list_all.php">📋 List View</a>
                                    <a class="dropdown-item" href="import.php">📤 Import</a>
                                    <div style="border-top: 1px solid var(--border); margin: 5px 0;"></div>
                                    <button class="dropdown-item" onclick="exportBookmarks('csv', '<?php echo $selected_folder_id; ?>')">📥 Export CSV</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('pdf', '<?php echo $selected_folder_id; ?>')">📥 Export PDF</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('txt', '<?php echo $selected_folder_id; ?>')">📥 Export TXT</button>
                                    <button class="dropdown-item" onclick="exportBookmarks('html', '<?php echo $selected_folder_id; ?>')">📥 Export HTML</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bulk-delete-bar" style="margin-bottom:15px;">
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
                                    <th style="width:40px;"></th>
                                    <th style="padding:8px; cursor:pointer;" onclick="sortTable(1, this)">
                                        Title <span class="sort-arrow"></span>
                                    </th>
                                    <th style="padding:8px;">
                                        Favicon
                                    </th>
                                    <th style="padding:8px; cursor:pointer;" onclick="sortTable(3, this)">
                                        URL <span class="sort-arrow"></span>
                                    </th>
                                    <th style="padding:8px; cursor:pointer;" onclick="sortTable(4, this)">
                                        Notes <span class="sort-arrow"></span>
                                    </th>
                                    <th style="padding:8px; cursor:pointer; width:120px;" onclick="sortTable(5, this)">
                                        Shared <span class="sort-arrow"></span>
                                    </th>
                                    <th style="padding:8px; width:120px;" class="itm-actions-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookmarks as $b): ?>
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:8px; text-align:center;">
                                            <input type="checkbox" name="ids[]" value="<?php echo (int)$b['id']; ?>" form="bulk-delete-form">
                                        </td>
                                        <td style="padding:8px;">
                                            <?php echo sanitize($b['title']); ?>
                                        </td>
                                        <td style="padding:8px; text-align:center;">
                                            <img src="<?php echo bkm_get_favicon_url($b['url']); ?>"
                                                 alt="favicon"
                                                 style="width:16px; height:16px; vertical-align:middle;"
                                                 onerror="this.style.display='none';">
                                        </td>
                                        <td style="padding:8px;">
                                            <a href="<?php echo sanitize($b['url']); ?>"
                                               target="_blank"
                                               rel="nofollow noreferrer noopener"
                                               style="color:var(--accent); text-decoration:none;">
                                                <?php echo sanitize($b['url']); ?>
                                            </a>
                                        </td>
                                        <td style="padding:8px;">
                                            <?php echo sanitize($b['notes']); ?>
                                        </td>
                                        <td style="padding:8px;">
                                            <?php if ($b['shared']): ?>
                                                🔓 Shared
                                            <?php else: ?>
                                                🔒 Private
                                            <?php endif; ?>
                                        </td>
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
    const checkboxes = document.querySelectorAll('input[name="ids[]"]');
    let selectionMode = false;

    let cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'btn btn-sm';
    cancelButton.textContent = 'Cancel';
    cancelButton.style.display = 'none';
    toggleButton.insertAdjacentElement('afterend', cancelButton);

    function exitSelectionMode() {
        selectionMode = false;
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
