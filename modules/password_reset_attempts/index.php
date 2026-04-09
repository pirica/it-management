<?php
/**
 * Password Reset Attempts - Index listing.
 * Why: expose support tooling for troubleshooting reset lockouts with scoped data,
 * searchable rows, and consistent bulk-delete ergonomics.
 */
require '../../config/config.php';

$scopeSql = '(u.company_id = ? OR (pra.user_id IS NULL AND EXISTS (SELECT 1 FROM users ux WHERE ux.company_id = ? AND ux.email = pra.email)))';
$error = (string)($_SESSION['crud_error'] ?? '');
unset($_SESSION['crud_error']);

$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, (int)($settings['records_per_page'] ?? 10));
$offset = ($page - 1) * $perPage;

$whereSql = "WHERE {$scopeSql}";
$paramTypes = 'ii';
$paramValues = [$company_id, $company_id];

if ($search !== '') {
    $whereSql .= ' AND (u.username LIKE ? OR pra.email LIKE ? OR pra.ip_address LIKE ? OR pra.attempt_type LIKE ?)';
    $like = '%' . $search . '%';
    $paramTypes .= 'ssss';
    array_push($paramValues, $like, $like, $like, $like);
}

$countSql = "SELECT COUNT(*) AS total FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id {$whereSql}";
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, $paramTypes, ...$paramValues);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)$countRow['total'];
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "SELECT pra.*, COALESCE(u.username, '') AS username FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id {$whereSql} ORDER BY pra.created_at DESC, pra.id DESC LIMIT ? OFFSET ?";
$listStmt = mysqli_prepare($conn, $listSql);
$rows = [];
if ($listStmt) {
    $listTypes = $paramTypes . 'ii';
    $listValues = $paramValues;
    $listValues[] = $perPage;
    $listValues[] = $offset;
    mysqli_stmt_bind_param($listStmt, $listTypes, ...$listValues);
    mysqli_stmt_execute($listStmt);
    $listResult = mysqli_stmt_get_result($listStmt);
    while ($listResult && ($row = mysqli_fetch_assoc($listResult))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Attempts</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔐 Password Reset Attempts</h1>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom: 10px;">
                <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <input type="text" name="search" placeholder="Search user, email, IP, type" value="<?= sanitize($search) ?>">
                    <button class="btn btn-primary" type="submit">🔎 Search</button>
                    <a class="btn" href="index.php">🔄 Reset</a>
                    <a class="btn btn-primary" href="create.php">➕ Add</a>
                </form>
            </div>

            <form method="POST" action="delete.php" id="bulk-delete-form">
                <input type="hidden" name="csrf_token" value="<?= sanitize(itm_get_csrf_token()) ?>">
                <?php if ($totalRows >= $perPage): ?>
                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger">Select to Delete</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all visible company-scoped records?');">Clear Table</button>
                    </div>
                <?php endif; ?>

                <table class="table">
                    <thead>
                    <tr>
                        <th><input type="checkbox" onclick="document.querySelectorAll('.itm-row-check').forEach(cb => cb.checked = this.checked)"></th>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>IP Address</th>
                        <th>Created At</th>
                        <th data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><input class="itm-row-check" type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>"></td>
                                <td><?= (int)$row['id'] ?></td>
                                <td><?= sanitize((string)($row['username'] ?: ($row['email'] ?: 'N/A'))) ?></td>
                                <td><?= sanitize((string)$row['email']) ?></td>
                                <td><?= sanitize((string)$row['attempt_type']) ?></td>
                                <td><?= sanitize((string)$row['ip_address']) ?></td>
                                <td><?= sanitize((string)$row['created_at']) ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?= (int)$row['id'] ?>">👁️</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?= (int)$row['id'] ?>">✏️</a>
                                        <button class="btn btn-sm btn-danger" type="submit" name="id" value="<?= (int)$row['id'] ?>" onclick="this.form.bulk_action.value='single_delete'; return confirm('Delete this record?');">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <input type="hidden" name="bulk_action" value="single_delete">
            </form>

            <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                <span>Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page > 1): ?>
                    <a class="btn btn-sm" href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">◀ Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-sm" href="?search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next ▶</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
