<?php
require '../../config/config.php';

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (\n        CAST(i.id AS CHAR) LIKE '{$searchEsc}'\n        OR i.name LIKE '{$searchEsc}'\n        OR i.item_code LIKE '{$searchEsc}'\n        OR c.name LIKE '{$searchEsc}'\n        OR CAST(i.quantity_on_hand AS CHAR) LIKE '{$searchEsc}'\n        OR CAST(i.active AS CHAR) LIKE '{$searchEsc}'\n    )";
}

$sortableColumns = ['id', 'name', 'item_code', 'category_name', 'quantity_on_hand', 'active'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$orderByMap = [
    'id' => 'i.id',
    'name' => 'i.name',
    'item_code' => 'i.item_code',
    'category_name' => 'c.name',
    'quantity_on_hand' => 'i.quantity_on_hand',
    'active' => 'i.active',
];
$items = mysqli_query(
    $conn,
    "SELECT i.*, c.name AS category_name
     FROM inventory_items i
     LEFT JOIN inventory_categories c ON c.id = i.category_id
     WHERE i.company_id = $company_id{$searchSql}
     ORDER BY {$orderByMap[$sort]} {$dir}"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>📦 Inventory Items</h1>
                <a class="btn btn-primary" href="create.php">➕</a>
            </div>
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="inventorySearch">Search (all fields)</label>
                        <input type="text" id="inventorySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%printer%%">
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
                        <?php foreach (['id' => 'ID', 'name' => 'Name', 'item_code' => 'Code', 'category_name' => 'Category', 'quantity_on_hand' => 'QOH', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($i = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><?php echo (int)$i['id']; ?></td>
                            <td><?php echo sanitize($i['name']); ?></td>
                            <td><?php echo sanitize($i['item_code'] ?? '-'); ?></td>
                            <td><?php echo sanitize($i['category_name'] ?? '-'); ?></td>
                            <td><?php echo (int)$i['quantity_on_hand']; ?></td>
                            <td><span class="badge <?php echo (int)$i['active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo (int)$i['active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$i['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$i['id']; ?>">✏️</a>
                                <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$i['id']; ?>" onclick="return confirm('Delete item?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" style="text-align:center;">No inventory items found.</td></tr>
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
