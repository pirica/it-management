<?php
/**
 * Tickets Module - Index
 * 
 * Provides a central list of support tickets.
 * Features:
 * - Filterable and sortable ticket grid
 * - Priority and Status color coding
 * - Direct links to ticket details and editing
 */

require '../../config/config.php';
itm_handle_json_table_import($conn, 'tickets', (int)($company_id ?? 0));


/**
 * Validates if a string is a valid Hex Color code
 */
function ticket_is_valid_hex_color(string $value): bool
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ($company_id <= 0) {
        $_SESSION['error_message'] = 'Sample data requires an active company.';
        header('Location: index.php');
        exit;
    }

    $companyCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tickets WHERE company_id = " . (int)$company_id);
    $companyCountRow = $companyCountResult ? mysqli_fetch_assoc($companyCountResult) : null;
    $companyTotalRows = (int)($companyCountRow['total'] ?? 0);

    if ($companyTotalRows > 0) {
        $_SESSION['error_message'] = 'Sample data can only be added when no records exist.';
        header('Location: index.php');
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, 'tickets', (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['error_message'] = $seedError;
    } else {
        $_SESSION['success_message'] = 'Sample data added successfully.';
    }

    header('Location: index.php');
    exit;
}

// Extraction of search and sorting parameters
$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (
        CAST(t.id AS CHAR) LIKE '{$searchEsc}'
        OR t.ticket_external_code LIKE '{$searchEsc}'
        OR t.title LIKE '{$searchEsc}'
        OR ts.name LIKE '{$searchEsc}'
        OR tp.name LIKE '{$searchEsc}'
        OR CAST(t.created_at AS CHAR) LIKE '{$searchEsc}'
    )";
}

// Sorting logic
$sortableColumns = ['id', 'ticket_external_code', 'title', 'status_name', 'priority_name', 'created_at'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) { $sort = 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }

$orderByMap = [
    'id' => 't.id', 'ticket_external_code' => 't.ticket_external_code',
    'title' => 't.title', 'status_name' => 'ts.name',
    'priority_name' => 'tp.name', 'created_at' => 't.created_at',
];

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tickets t
     LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
     LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
     WHERE t.company_id = $company_id{$searchSql}"
);
$countRow = $countQuery ? mysqli_fetch_assoc($countQuery) : null;
$totalRows = (int)($countRow['total'] ?? 0);
$companyCountQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tickets WHERE company_id = " . (int)$company_id);
$companyCountRow = $companyCountQuery ? mysqli_fetch_assoc($companyCountQuery) : null;
$companyTotalRows = (int)($companyCountRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Primary data fetch with joins for status and priority labels
$items = mysqli_query(
    $conn,
    "SELECT t.*, ts.name AS status_name, ts.color AS status_color, tp.name AS priority_name
     FROM tickets t
     LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
     LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
     WHERE t.company_id = $company_id{$searchSql}
     ORDER BY {$orderByMap[$sort]} {$dir}
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);

$showBulkActions = $totalRows >= $perPage;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <!-- HEADER SECTION -->
            <div data-itm-new-button-managed="server" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php else: ?><span></span><?php endif; ?>
                <h1>🎟️ Tickets</h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php endif; ?>
            </div>

            <!-- SEARCH BAR -->
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="ticketSearch">Search (all fields)</label>
                        <input type="text" id="ticketSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search...">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>


            <?php if ($showBulkActions): ?>
                <!-- Guard bulk actions behind per-page threshold to align with global UX settings. -->
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all tickets? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- DATA TABLE -->
            <div class="card">
                <table data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <?php if ($showBulkActions): ?><th>Select</th><?php endif; ?>
                        <?php foreach (['id' => 'ID', 'ticket_external_code' => 'External Code', 'title' => 'Title', 'status_name' => 'Status', 'priority_name' => 'Priority', 'created_at' => 'Created'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($t = mysqli_fetch_assoc($items)): ?>
                        <?php 
                        // Allow for custom row colorization based on UI configuration
                        $rowColor = (isset($t['ui_color']) && ticket_is_valid_hex_color((string)$t['ui_color'])) ? (string)$t['ui_color'] : ''; 
                        ?>
                        <tr<?php echo $rowColor !== '' ? ' style="border-left:4px solid ' . sanitize($rowColor) . ';"' : ''; ?>>
                            <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$t['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                            <td><?php echo (int)$t['id']; ?></td>
                            <td><?php echo sanitize($t['ticket_external_code'] ?? '-'); ?></td>
                            <td><?php echo sanitize($t['title']); ?></td>
                            <td><span class="badge" style="background-color:<?php echo sanitize($t['status_color'] ?: '#9aa4b2'); ?>33;color:<?php echo sanitize($t['status_color'] ?: '#9aa4b2'); ?>;"><?php echo sanitize($t['status_name'] ?: 'Open'); ?></span></td>
                            <td><?php echo sanitize($t['priority_name'] ?: '-'); ?></td>
                            <td><?php echo sanitize($t['created_at']); ?></td>
                            <td>
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$t['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$t['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete ticket?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" style="text-align:center;">No tickets found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
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
            <?php if ($company_id > 0 && $companyTotalRows === 0): ?>
                <div style="margin-top:16px;text-align:center;">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
