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
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'bookmarks', (int)($_SESSION['company_id'] ?? 0));
    }
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = (($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$crud_table = 'bookmarks';
$crud_title = 'Bookmarks List';

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'title';
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';
$searchRaw = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = "company_id = $company_id AND active = 1 AND (user_id = $user_id OR shared = 1)";
if ($searchRaw !== '') {
    $s = mysqli_real_escape_string($conn, $searchRaw);
    $where .= " AND (title LIKE '%$s%' OR url LIKE '%$s%' OR notes LIKE '%$s%')";
}

$sql = "SELECT b.*, f.name as folder_display_name
        FROM bookmarks b
        LEFT JOIN bookmark_folders f ON b.folder_name = f.id
        WHERE b.$where ORDER BY b.$sort $dir LIMIT $offset, $perPage";
$res = mysqli_query($conn, $sql);

$countSql = "SELECT COUNT(*) as total FROM bookmarks WHERE $where";
$countRes = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countRes)['total'];
$totalPages = ceil($totalRows / $perPage);

$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($crud_title); ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="main-container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1><?php echo sanitize($crud_title); ?></h1>
            <div>
                <a href="index.php" class="btn">📂 Tree View</a>
                <a href="create.php" class="btn btn-primary">➕ Add Bookmark</a>
            </div>
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
                        <th>Title</th>
                        <th>URL</th>
                        <th>Folder</th>
                        <th>Shared</th>
                        <th class="itm-actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && mysqli_num_rows($res) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($res)): ?>
                            <tr>
                                <td><?php echo sanitize($row['title']); ?></td>
                                <td><a href="<?php echo sanitize($row['url']); ?>" target="_blank" rel="nofollow noreferrer"><?php echo sanitize($row['url']); ?></a></td>
                                <td><?php echo sanitize($row['folder_display_name'] ?? 'Root'); ?></td>
                                <td><?php echo $row['shared'] ? '✅' : '❌'; ?></td>
                                <td class="itm-actions-cell">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo $row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No bookmarks found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="margin-top:20px; display:flex; gap:5px; justify-content:center;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchRaw); ?>" class="btn btn-sm">Prev</a>
                <?php endif; ?>
                <span class="btn btn-sm" style="pointer-events:none;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchRaw); ?>" class="btn btn-sm">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
