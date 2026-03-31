<?php
require '../../config/config.php';

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
$params = [];
$types = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchSql = " AND (
        i.name LIKE ?
        OR i.item_code LIKE ?
        OR i.serial LIKE ?
        OR c.name LIKE ?
        OR CAST(i.quantity_on_hand AS CHAR) LIKE ?
        OR CAST(i.quantity_minimum AS CHAR) LIKE ?
        OR CAST(i.price_eur AS CHAR) LIKE ?
        OR i.comments LIKE ?
        OR CAST(i.active AS CHAR) LIKE ?
    )";
    $params = array_fill(0, 9, $searchPattern);
    $types = 'sssssssss';
}

$sortableColumns = ['name', 'item_code', 'serial', 'category_name', 'quantity_on_hand', 'quantity_minimum', 'price_eur', 'comments', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}
$orderByMap = [
    'name' => 'i.name',
    'item_code' => 'i.item_code',
    'serial' => 'i.serial',
    'category_name' => 'c.name',
    'quantity_on_hand' => 'i.quantity_on_hand',
    'quantity_minimum' => 'i.quantity_minimum',
    'price_eur' => 'i.price_eur',
    'comments' => 'i.comments',
    'active' => 'i.active',
];

$sql = "SELECT i.*, c.name AS category_name
        FROM inventory_items i
        LEFT JOIN inventory_categories c ON c.id = i.category_id
        WHERE i.company_id = ?{$searchSql}
        ORDER BY {$orderByMap[$sort]} {$dir}";

$stmt = mysqli_prepare($conn, $sql);
$items = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i' . $types, $company_id, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
}
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
                        <input type="text" id="inventorySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%serial%%">
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
                        <?php foreach ([
                            'name' => 'Name',
                            'item_code' => 'Code',
                            'serial' => 'Serial',
                            'category_name' => 'Category',
                            'quantity_on_hand' => 'QOH',
                            'quantity_minimum' => 'Min',
                            'price_eur' => 'Price (€)',
                            'comments' => 'Comments',
                            'active' => 'Status'
                        ] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($items)): foreach ($items as $i): ?>
                        <tr>
                            <td><?php echo sanitize((string)$i['name']); ?></td>
                            <td><?php echo sanitize((string)($i['item_code'] ?? '-')); ?></td>
                            <td><?php echo sanitize((string)($i['serial'] ?? '-')); ?></td>
                            <td><?php echo sanitize((string)($i['category_name'] ?? '-')); ?></td>
                            <td><?php echo (int)$i['quantity_on_hand']; ?></td>
                            <td><?php echo (int)$i['quantity_minimum']; ?></td>
                            <td>€<?php echo number_format((float)($i['price_eur'] ?? 0), 2); ?></td>
                            <td>
                                <?php if (trim((string)($i['comments'] ?? '')) !== ''): ?>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$i['id']; ?>">✏️</a>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo (int)$i['active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo (int)$i['active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td class="itm-actions-cell">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$i['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$i['id']; ?>">✏️</a>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$i['id']; ?>" onclick="return confirm('Delete item?');">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="10" style="text-align:center;">No inventory items found.</td></tr>
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
