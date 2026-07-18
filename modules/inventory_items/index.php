<?php
/**
 * Inventory Items Module - Index
 * 
 * Displays a sortable, searchable list of inventory items.
 * Includes category relationships and tracks quantity on hand (QOH) versus minimum thresholds.
 * Status badges indicate active/inactive items.
 */
$crud_table = 'inventory_items';
$crud_title = 'Inventory Items';
$crud_action = $crud_action ?? 'index';

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
        OR i.serial LIKE '{$searchEsc}'
        OR CAST(i.storage_date AS CHAR) LIKE '{$searchEsc}'
        OR c.name LIKE '{$searchEsc}'
        OR CAST(i.quantity_on_hand AS CHAR) LIKE '{$searchEsc}'
        OR CAST(i.quantity_minimum AS CHAR) LIKE '{$searchEsc}'
        OR CAST(i.price_eur AS CHAR) LIKE '{$searchEsc}'
        OR i.comments LIKE '{$searchEsc}'
        OR CAST(i.updated_at AS CHAR) LIKE '{$searchEsc}'
        OR CAST(i.active AS CHAR) LIKE '{$searchEsc}'
    )";
}

// HANDLE SORTING
$sortableColumns = ['id', 'name', 'serial', 'storage_date', 'category_name', 'quantity_on_hand', 'quantity_minimum', 'price_eur', 'comments', 'updated_at', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}
$orderByMap = [
    'id' => 'i.id',
    'name' => 'i.name',
    'serial' => 'i.serial',
    'storage_date' => 'i.storage_date',
    'category_name' => 'c.name',
    'quantity_on_hand' => 'i.quantity_on_hand',
    'quantity_minimum' => 'i.quantity_minimum',
    'price_eur' => 'i.price_eur',
    'comments' => 'i.comments',
    'updated_at' => 'i.updated_at',
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
// Why: List h1 must use Settings sidebar label so per-user emoji overrides apply in the list header.
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;

function itm_inventory_items_list_url(array $overrides = []): string
{
    global $searchRaw, $sort, $dir, $page;
    $query = [
        'search' => $searchRaw,
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
    ];
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return 'index.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Inventory';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php echo itm_render_alert_errors($inventoryError ?? ''); ?>

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
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all inventory records? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- DATA TABLE -->
            <div class="card">
                <table data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <?php if ($showBulkActions): ?><th>Select</th><?php endif; ?>
                        <?php foreach ([
                            'id' => 'ID',
                            'name' => 'Name',
                            'serial' => 'Serial',
                            'storage_date' => 'Storage Date',
                            'category_name' => 'Category',
                            'quantity_on_hand' => 'QOH',
                            'quantity_minimum' => 'Min',
                            'price_eur' => 'Price (€)',
                            'comments' => 'Comments',
                            'updated_at' => 'Updated At',
                            'active' => 'Status'
                        ] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th>
                                <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($i = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$i['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                            <td><?php echo (int)$i['id']; ?></td>
                            <td><?php echo sanitize((string)$i['name']); ?></td>
                            <td><?php echo sanitize((string)($i['serial'] ?? '-')); ?></td>
                            <td><?php echo sanitize((string)($i['storage_date'] ?? '-')); ?></td>
                            <td><?php echo sanitize((string)($i['category_name'] ?? '-')); ?></td>
                            <td><?php echo (int)$i['quantity_on_hand']; ?></td>
                            <td><?php echo (int)$i['quantity_minimum']; ?></td>
                            <td>€<?php echo number_format((float)($i['price_eur'] ?? 0), 2); ?></td>
                            <td>
                                <?php if (trim((string)($i['comments'] ?? '')) !== ''): ?>
                                    <span title="<?php echo sanitize((string)$i['comments']); ?>">💬</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize((string)($i['updated_at'] ?? '-')); ?></td>
                            <td>
                                <span class="badge <?php echo (int)$i['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$i['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
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
                        <tr><td colspan="<?php echo $showBulkActions ? 13 : 12; ?>" style="text-align:center;opacity:.8;">No records found.</td></tr>
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
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo (int)$totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="<?php echo sanitize(itm_inventory_items_list_url(['page' => $page - 1])); ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="<?php echo sanitize(itm_inventory_items_list_url(['page' => $page + 1])); ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/bulk-delete-selection.js"></script>
</body>
</html>
