<?php
require '../../config/config.php';

$searchRaw = trim((string)($_GET['search'] ?? ''));
$sortableColumns = ['id', 'company', 'incode', 'city', 'country', 'phone', 'active'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}

$whereSql = ' WHERE id > 0';
$params = [];
$types = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $whereSql = ' WHERE id > 0 AND (CAST(id AS CHAR) LIKE ? OR company LIKE ? OR incode LIKE ? OR city LIKE ? OR country LIKE ? OR phone LIKE ? OR CAST(active AS CHAR) LIKE ?)';
    $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern];
    $types = 'sssssss';
}

$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$sql = 'SELECT * FROM companies' . $whereSql . ' ORDER BY ' . $sortSql . ' LIMIT ' . (int)$perPage;
$stmt = mysqli_prepare($conn, $sql);
$rows = null;
if ($stmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt);
}

$csrfToken = itm_get_csrf_token();
$error = (string)($_SESSION['crud_error'] ?? '');
unset($_SESSION['crud_error']);
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1>🏢 Companies</h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;">
                    <div class="form-group" style="margin:0;flex:1;">
                        <label for="companySearch">Search (all fields)</label>
                        <input type="text" id="companySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%abc%%">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn">Clear</a>
                </form>
            </div>

            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th><a href="?sort=id&dir=<?php echo $sort === 'id' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">ID</a></th>
                        <th><a href="?sort=company&dir=<?php echo $sort === 'company' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">Company</a></th>
                        <th><a href="?sort=incode&dir=<?php echo $sort === 'incode' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">InCode</a></th>
                        <th><a href="?sort=city&dir=<?php echo $sort === 'city' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">City</a></th>
                        <th><a href="?sort=country&dir=<?php echo $sort === 'country' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">Country</a></th>
                        <th><a href="?sort=phone&dir=<?php echo $sort === 'phone' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">Phone</a></th>
                        <th><a href="?sort=active&dir=<?php echo $sort === 'active' && $dir === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($searchRaw); ?>">Status</a></th>
                        <th class="itm-actions-cell">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && mysqli_num_rows($rows) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['company']); ?></td>
                                <td><?php echo sanitize($row['incode']); ?></td>
                                <td><?php echo sanitize($row['city']); ?></td>
                                <td><?php echo sanitize($row['country']); ?></td>
                                <td><?php echo sanitize($row['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo (int)$row['active'] === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo (int)$row['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="itm-actions-cell">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this company?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;">No companies found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
<?php if ($stmt) { mysqli_stmt_close($stmt); } ?>
