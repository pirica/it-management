<?php
require '../../config/config.php';

$searchRaw = trim((string)($_GET['search'] ?? ''));
$where = ' WHERE 1=1';
$params = [];
$types = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $where .= " AND (CAST(id AS CHAR) LIKE ?
               OR company LIKE ?
               OR incode LIKE ?
               OR city LIKE ?
               OR country LIKE ?
               OR phone LIKE ?
               OR CAST(active AS CHAR) LIKE ?)";
    $params = array_fill(0, 7, $searchPattern);
    $types = 'sssssss';
}
$sortableColumns = ['id', 'company', 'incode', 'city', 'country', 'phone', 'active'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;

$sql = "SELECT * FROM companies{$where} ORDER BY {$sortSql}";
$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🌍 Companies</h1>
                <a href="create.php" class="btn btn-primary">➕</a>
            </div>
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="companySearch">Search (all fields)</label>
                        <input type="text" id="companySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%abc%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-sm">Clear</a>
                    </div>
                </form>
            </div>
            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <?php foreach (['id' => 'ID', 'company' => 'Company', 'incode' => 'InCode', 'city' => 'City', 'country' => 'Country', 'phone' => 'Phone', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['company']); ?></td>
                                <td><?php echo sanitize($row['incode'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['city'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['country'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['phone'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo (int)$row['active'] === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo (int)$row['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="itm-actions-cell">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a> <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this company?');">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
