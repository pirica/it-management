<?php
/**
 * Bookmarks Module - List All (Table View)
 */
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
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = (strtolower($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$crud_table = 'bookmarks';
$crud_title = 'Bookmarks List';

$sort = $_GET['sort'] ?? 'title';
$dir = (strtoupper($_GET['dir'] ?? '') === 'DESC') ? 'DESC' : 'ASC';

$allowedSorts = ['title', 'url', 'folder', 'shared'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'title';
}

// Extra safety check as per plan
if (function_exists('itm_is_safe_identifier') && !itm_is_safe_identifier($sort)) {
    $sort = 'title';
}

$orderByMap = [
    'title'  => 'b.title',
    'url'    => 'b.url',
    'folder' => 'f.name',
    'shared' => 'b.shared'
];
$orderBy = $orderByMap[$sort] ?? 'b.title';

$searchRaw = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = "b.company_id = $company_id AND b.active = 1 AND (b.user_id = $user_id OR b.shared = 1)";
if ($searchRaw !== '') {
    $s = mysqli_real_escape_string($conn, $searchRaw);
    $where .= " AND (b.title LIKE '%$s%' OR b.url LIKE '%$s%' OR b.notes LIKE '%$s%')";
}

$sql = "SELECT b.*, f.name as folder_display_name
        FROM bookmarks b
        LEFT JOIN bookmark_folders f ON b.folder_id = f.id
        WHERE $where ORDER BY $orderBy $dir LIMIT $offset, $perPage";
$res = mysqli_query($conn, $sql);

$countSql = "SELECT COUNT(*) as total FROM bookmarks b WHERE $where";
$countRes = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countRes)['total'];
$totalPages = ceil($totalRows / $perPage);

$csrfToken = itm_get_csrf_token();
$showBulkActions = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($crud_title); ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
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
            <h1><?php echo sanitize($crud_title); ?></h1>
            <div style="display: flex; gap: 8px; align-items: center;">
                <div class="dropdown" style="position: relative;">
                    
                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show'); event.stopPropagation();">Tools ⚙️</button>
                    <div class="dropdown-menu" style="right: 0; min-width: 180px;">
                        <a class="dropdown-item" href="index.php">📂 Tree View</a>
                        <a class="dropdown-item" href="import.php">📤 Import</a>
                    </div>
                </div>
                <a href="create.php" class="btn btn-primary">➕</a>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear table?');">Clear Table</button>
            </form>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <form method="GET" style="display:flex; gap:10px; align-items:flex-end;">
                <div class="form-group" style="margin:0; flex:1;">
                    <label>Search</label>
                    <input type="text" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search...">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="list_all.php" class="btn">Clear</a>
            </form>
        </div>

        <div class="card" style="overflow:auto;">
            <table data-itm-db-import-endpoint="list_all.php">
                <thead>
                    <tr>
                        <?php if ($showBulkActions): ?>
                            <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                        <?php endif; ?>
                        <?php
                        $columns = [
                            'title'  => 'Title',
                            'favicon' => 'Favicon',
                            'url'    => 'URL',
                            'notes'  => 'Notes',
                            'folder' => 'Folder',
                            'shared' => 'Shared'
                        ];
                        foreach ($columns as $colKey => $colLabel):
                            if ($colKey === "favicon") {
                                echo "<th>" . sanitize($colLabel) . "</th>";
                                continue;
                            }
                            $nextDir = ($sort === $colKey && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $arrow = ($sort === $colKey) ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
                        ?>
                            <th>
                                <a href="?sort=<?php echo urlencode($colKey); ?>&dir=<?php echo $nextDir; ?>&search=<?php echo urlencode($searchRaw); ?>" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize($colLabel) . $arrow; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && mysqli_num_rows($res) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($res)): ?>
                            <tr>
                                <?php if ($showBulkActions): ?>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                <?php endif; ?>
                                <td><?php echo sanitize($row['title']); ?></td>
                                <td style="text-align:center;">
                                    <img src="<?php echo bkm_get_favicon_url($row['url']); ?>"
                                         alt="favicon"
                                         style="width:16px; height:16px; vertical-align:middle;"
                                         onerror="this.style.display='none';">
                                </td>
                                <td><?php echo sanitize($row['notes']); ?></td>
                                <td><a href="<?php echo sanitize($row['url']); ?>" rel="nofollow noreferrer noopener" target="_blank" style="color:var(--accent); text-decoration:none;"><?php echo sanitize($row['url']); ?></a></td>
                                <td><?php echo sanitize($row['folder_display_name'] ?? 'Root'); ?></td>
                                <td><?php echo $row['shared'] ? '✅' : '❌'; ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo $row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo $showBulkActions ? 8 : 7; ?>" style="text-align:center;">No bookmarks found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>" class="btn btn-sm">Previous</a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;"><?php echo "Page $page of $totalPages"; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>" class="btn btn-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/bulk-delete-selection.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (event.target.id === 'select-all-rows') {
        const checkboxes = document.querySelectorAll('input[name="ids[]"]');
        checkboxes.forEach(cb => cb.checked = event.target.checked);
    }
});

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu').forEach(function(el) {
        el.classList.remove('show');
    });
});
</script>
</body>
</html>
