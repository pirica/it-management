<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (
        CAST(id AS CHAR) LIKE '{$searchEsc}'
        OR code LIKE '{$searchEsc}'
        OR name LIKE '{$searchEsc}'
        OR CAST(active AS CHAR) LIKE '{$searchEsc}'
    )";
}

$sortableColumns = ['code', 'name', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;
$items = mysqli_query($conn, "SELECT id, code, name, active FROM system_access WHERE company_id = $company_id{$searchSql} ORDER BY {$sortSql}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🛡️ System Access</h1>
                <a class="btn btn-primary" href="create.php">➕</a>
            </div>
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="systemAccessSearch">Search (all fields)</label>
                        <input type="text" id="systemAccessSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%network%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-sm">Clear</a>
                    </div>
                </form>
            </div>
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <?php foreach (['code' => 'Code', 'name' => 'Name', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($item = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><?php echo sanitize($item['code']); ?></td>
                            <td><?php echo sanitize($item['name']); ?></td>
                            <td>
                                <span class="badge <?php echo (int)$item['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$item['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$item['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$item['id']; ?>">✏️</a>
                                <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$item['id']; ?>" onclick="return confirm('Delete system access record?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center;">No system access records found.</td></tr>
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
