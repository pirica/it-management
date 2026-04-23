<?php
/**
 * Inventory Module - Index
 * 
 * Displays a sortable, searchable list of inventory items.
 * Includes category relationships and tracks quantity on hand (QOH) versus minimum thresholds.
 * Status badges indicate active/inactive items.
 */

require '../../config/config.php';
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'inventory_items', (int)($company_id ?? 0));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ((int)$company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: index.php');
        exit;
    }

    $existingRowsResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM inventory_items WHERE company_id=' . (int)$company_id);
    $existingRowsData = $existingRowsResult ? mysqli_fetch_assoc($existingRowsResult) : null;
    $existingRows = (int)($existingRowsData['total_rows'] ?? 0);
    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: index.php');
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, 'inventory_items', (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: index.php');
    exit;
}

$inventoryError = (string)($_SESSION['crud_error'] ?? '');
unset($_SESSION['crud_error']);


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
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM inventory_items i
     LEFT JOIN inventory_categories c ON c.id = i.category_id
     WHERE i.company_id = $company_id{$searchSql}"
);
$countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
$totalRows = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch items joined with categories.
$showBulkActions = $totalRows >= $perPage;

$items = mysqli_query(
    $conn,
    "SELECT i.*, c.name AS category_name
     FROM inventory_items i
     LEFT JOIN inventory_categories c ON c.id = i.category_id
     WHERE i.company_id = $company_id{$searchSql}
     ORDER BY {$orderByMap[$sort]} {$dir}
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
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

            <?php if ($inventoryError !== ''): ?>
                <div class="alert alert-danger"><?php echo sanitize($inventoryError); ?></div>
            <?php endif; ?>

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


            <?php if ($showBulkActions): ?>
                <!-- Bulk controls appear only when enough records exist to justify batch operations. -->
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all inventory records? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- DATA TABLE -->
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <?php if ($showBulkActions): ?><th>Select</th><?php endif; ?>
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
                            <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$i['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
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
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$i['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$i['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete item?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="11" style="text-align:center;">No inventory items found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page - 1; ?>">« Prev</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.85;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page + 1; ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
