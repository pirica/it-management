<?php
/**
 * Inventory Module - Index
 * 
 * Displays a sortable, searchable list of inventory items.
 * Includes category relationships and tracks quantity on hand (QOH) versus minimum thresholds.
 * Status badges indicate active/inactive items.
 */

require '../../config/config.php';

// HANDLE SEARCH LOGIC
$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    // Support both explicit wildcards and partial matching.
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (
        i.name LIKE '{$searchEsc}'
        OR i.item_code LIKE '{$searchEsc}'
        OR i.serial LIKE '{$searchEsc}'
        OR c.name LIKE '{$searchEsc}'
        OR CAST(i.quantity_on_hand AS CHAR) LIKE '{$searchEsc}'
        OR CAST(i.quantity_minimum AS CHAR) LIKE '{$searchEsc}'
        OR CAST(i.price_eur AS CHAR) LIKE '{$searchEsc}'
        OR i.comments LIKE '{$searchEsc}'
        OR CAST(i.active AS CHAR) LIKE '{$searchEsc}'
    )";
}

// HANDLE SORTING
$sortableColumns = ['name', 'item_code', 'serial', 'category_name', 'quantity_on_hand', 'quantity_minimum', 'price_eur', 'comments', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
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

// Determine pagination limit based on global UI config.
$perPage = itm_resolve_records_per_page($ui_config ?? null);

// Fetch items joined with categories.
$items = mysqli_query(
    $conn,
    "SELECT i.*, c.name AS category_name
     FROM inventory_items i
     LEFT JOIN inventory_categories c ON c.id = i.category_id
     WHERE i.company_id = $company_id{$searchSql}
     ORDER BY {$orderByMap[$sort]} {$dir}
     LIMIT " . (int)$perPage
);

// Determine position of the "Add New" button based on UI preferences.
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
    <title>Inventory</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div data-itm-new-button-managed="server" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1>📦 Inventory Items</h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <!-- SEARCH BAR -->
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="inventorySearch">Search (all fields)</label>
                        <input type="text" id="inventorySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%serial%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>

            <!-- DATA TABLE -->
            <div class="card">
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
                            <th>
                                <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($i = mysqli_fetch_assoc($items)): ?>
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
                                    <span title="<?php echo sanitize((string)$i['comments']); ?>">💬</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo (int)$i['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$i['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="itm-actions-cell">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$i['id']; ?>">👀</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$i['id']; ?>">✏️</a>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$i['id']; ?>" onclick="return confirm('Delete item?');">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
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
