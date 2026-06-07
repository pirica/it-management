<?php
require '../../config/config.php';
require './helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = (($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$selected_folder_id = isset($_GET['folder_name']) ? (int)$_GET['folder_name'] : null;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'all'; // 'all', 'shared', 'private'
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle Folder Reordering/Reparenting via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_folder') {
    if (itm_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $fid = (int)$_POST['folder_id'];
        $new_parent = (int)$_POST['new_parent_id'] ?: null;

        $check_res = mysqli_query($conn, "SELECT user_id FROM bookmark_folders WHERE id = $fid");
        $f_data = mysqli_fetch_assoc($check_res);
        if ($f_data && ($is_admin || (int)$f_data['user_id'] === $user_id)) {
            $stmt = mysqli_prepare($conn, "UPDATE bookmark_folders SET parent_folder_name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $new_parent, $fid);
            mysqli_stmt_execute($stmt);
        }
    }
}

// Fetch folders for the sidebar tree
$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);

// Build bookmarks query
$bookmarks_where = "company_id = $company_id AND active = 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $bookmarks_where .= " AND (title LIKE '%$search_esc%' OR url LIKE '%$search_esc%' OR notes LIKE '%$search_esc%')";
    $bookmarks_where .= " AND (user_id = $user_id OR shared = 1)";
} elseif ($view_mode === 'shared') {
    $bookmarks_where .= " AND shared = 1";
    if ($selected_folder_id) $bookmarks_where .= " AND folder_name = $selected_folder_id";
} elseif ($view_mode === 'private') {
    $bookmarks_where .= " AND user_id = $user_id AND shared = 0";
    if ($selected_folder_id) $bookmarks_where .= " AND folder_name = $selected_folder_id";
} elseif ($selected_folder_id !== null) {
    $bookmarks_where .= " AND folder_name = $selected_folder_id";
    // Check access to selected folder
    $folder_check_res = mysqli_query($conn, "SELECT user_id, shared FROM bookmark_folders WHERE id = $selected_folder_id");
    $folder_data = mysqli_fetch_assoc($folder_check_res);
    if (!$folder_data || (!$is_admin && $folder_data['user_id'] != $user_id && $folder_data['shared'] == 0)) {
        $bookmarks_where .= " AND 1=0";
    }
} else {
    // Root level (unfiled)
    $bookmarks_where .= " AND (user_id = $user_id OR shared = 1) AND folder_name IS NULL";
}

$bookmarks_sql = "SELECT * FROM bookmarks WHERE $bookmarks_where ORDER BY position ASC, title ASC";
$bookmarks_res = mysqli_query($conn, $bookmarks_sql);
$bookmarks = [];
while ($bookmarks_res && ($row = mysqli_fetch_assoc($bookmarks_res))) {
    $bookmarks[] = $row;
}

$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bookmarks - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .bookmarks-layout { display: flex; gap: 20px; min-height: calc(100vh - 120px); }
        .bookmarks-sidebar { width: 320px; border-right: 1px solid #ddd; padding-right: 20px; overflow-y: auto; }
        .bookmarks-main { flex: 1; overflow-y: auto; padding-left: 10px; }
        .folder-tree { list-style: none; padding: 0; }
        .folder-tree li { margin: 2px 0; }
        .folder-tree a { text-decoration: none; color: #333; display: block; padding: 5px; border-radius: 4px; }
        .folder-tree a:hover { background: #f0f0f0; }
        .folder-tree .active > div > a { background: #e0e0e0; font-weight: bold; }
        .bookmark-card { border: 1px solid #ddd; padding: 12px; margin-bottom: 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: flex-start; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .bookmark-info { flex: 1; }
        .bookmark-actions { display: flex; gap: 8px; margin-left: 15px; }
        .copy-btn { cursor: pointer; }
        .shared-badge { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; }
        .private-badge { background: #fff3e0; color: #e65100; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; }
        .itm-folder-tree-item[drag-over] { border-top: 2px solid #2196F3; }
        .view-filters { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .view-filters a { text-decoration: none; font-size: 0.9em; padding: 5px 10px; border-radius: 4px; color: #666; }
        .view-filters a.active { background: #eee; color: #333; font-weight: bold; }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="main-container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="content">
        <div class="bookmarks-layout">
            <div class="bookmarks-sidebar">
                <div style="margin-bottom: 15px;">
                    <form method="GET" style="display: flex; gap: 5px;">
                        <input type="text" name="search" placeholder="Search bookmarks..." value="<?php echo sanitize($search); ?>" style="flex: 1; padding: 8px;">
                        <button type="submit" class="btn btn-primary">🔍</button>
                    </form>
                </div>

                <div class="view-filters">
                    <a href="index.php?view=all" class="<?php echo $view_mode === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="index.php?view=private" class="<?php echo $view_mode === 'private' ? 'active' : ''; ?>">🔒 Private</a>
                    <a href="index.php?view=shared" class="<?php echo $view_mode === 'shared' ? 'active' : ''; ?>">🔓 Shared</a>
                </div>

                <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <a href="create.php<?php echo $selected_folder_id ? "?folder_name=$selected_folder_id" : ""; ?>" class="btn btn-primary" style="flex: 1; text-align: center;">➕ Add Bookmark</a>
                    <a href="create_folder.php" class="btn" title="Add Folder">➕📁</a>
                </div>
                <ul class="folder-tree">
                    <li class="<?php echo ($selected_folder_id === null && $search === '') ? 'active' : ''; ?>" ondrop="drop(event)" ondragover="allowDrop(event)" data-folder-id="0">
                        <div><a href="index.php?view=<?php echo $view_mode; ?>">🏠 Root Bookmarks</a></div>
                    </li>
                    <?php echo bkm_render_folder_tree_html($folder_tree, $selected_folder_id); ?>
                </ul>
                <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;">
                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                        <a href="import.php" class="btn btn-sm">📤 Import</a>
                        <div style="position: relative; display: inline-block;">
                            <button class="btn btn-sm" onclick="toggleExportMenu(event)">📥 Export</button>
                            <div id="export-dropdown" style="display: none; position: absolute; background: white; border: 1px solid #ccc; z-index: 100; min-width: 100px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                <button onclick="exportBookmarks('xlsx', '<?php echo $selected_folder_id; ?>')" class="btn btn-sm" style="display: block; border: none; border-radius: 0; width: 100%; text-align: left;">Excel</button>
                                <button onclick="exportBookmarks('csv', '<?php echo $selected_folder_id; ?>')" class="btn btn-sm" style="display: block; border: none; border-radius: 0; width: 100%; text-align: left;">CSV</button>
                                <button onclick="exportBookmarks('pdf', '<?php echo $selected_folder_id; ?>')" class="btn btn-sm" style="display: block; border: none; border-radius: 0; width: 100%; text-align: left;">PDF</button>
                                <button onclick="exportBookmarks('txt', '<?php echo $selected_folder_id; ?>')" class="btn btn-sm" style="display: block; border: none; border-radius: 0; width: 100%; text-align: left;">TXT</button>
                                <button onclick="exportBookmarks('html', '<?php echo $selected_folder_id; ?>')" class="btn btn-sm" style="display: block; border: none; border-radius: 0; width: 100%; text-align: left;">HTML</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bookmarks-main">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">
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
                    <?php if ($selected_folder_id): ?>
                         <a href="edit_folder.php?id=<?php echo $selected_folder_id; ?>" class="btn btn-sm">✏️ Edit Folder</a>
                    <?php endif; ?>
                </div>
                <div class="bookmarks-list">
                    <?php if (empty($bookmarks)): ?>
                        <div class="card" style="text-align: center; padding: 40px; color: #666;">
                            <p>No bookmarks found in this view.</p>
                            <a href="create.php<?php echo $selected_folder_id ? "?folder_name=$selected_folder_id" : ""; ?>" class="btn btn-primary">➕ Create a bookmark</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookmarks as $b): ?>
                            <div class="bookmark-card">
                                <div class="bookmark-info">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                        <strong style="font-size: 1.1em;"><?php echo sanitize($b['title']); ?></strong>
                                        <?php if ($b['shared']): ?>
                                            <span class="shared-badge">🔓 Shared</span>
                                        <?php else: ?>
                                            <span class="private-badge">🔒 Private</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?php echo sanitize($b['url']); ?>" target="_blank" rel="nofollow noreferrer" style="color: #2196F3; text-decoration: none; word-break: break-all;">🔗 <?php echo sanitize($b['url']); ?></a>
                                    <?php if ($b['notes']): ?>
                                        <p style="font-size: 0.9em; color: #555; margin: 8px 0 0 0; line-height: 1.4;"><?php echo sanitize($b['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="bookmark-actions">
                                    <button class="btn btn-sm copy-btn" onclick="copyUrl('<?php echo addslashes($b['url']); ?>')" title="Copy URL">🗐</button>
                                    <?php if (bkm_can_edit_bookmark($b, $user_id, $is_admin)): ?>
                                        <a href="edit.php?id=<?php echo $b['id']; ?>" class="btn btn-sm">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this bookmark?');">
                                            <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
</body>
</html>
