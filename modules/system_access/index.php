<?php
/**
 * System Access Module - Index
 * 
 * Manages the catalog of available system access types (e.g., 'OPERA', 'Network Access').
 * Features:
 * - Lazy Schema Initialization: Calls `esa_ensure_table()` to setup modern relation tables.
 * - Bulk Import: Supports CSV-style text import for batch creating/updating access types.
 * - Export: Provides CSV exportation of the access catalog.
 * - Multi-tenant Filtering: All actions are scoped to the active `company_id`.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Ensure database schema is ready before any logic.
esa_ensure_table($conn);

/**
 * Strips empty/null values from a query parameter array before encoding.
 */
function sa_build_query($params) {
    $normalized = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') { continue; }
        $normalized[$k] = $v;
    }
    return http_build_query($normalized);
}

$messages = [];
$errors = [];
$csrfToken = itm_get_csrf_token();

// HANDLE BULK IMPORT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'import_system_access')) {
    itm_require_post_csrf();

    $importText = trim((string)($_POST['import_text'] ?? ''));
    if ($importText === '') {
        $errors[] = 'Import text is empty.';
    } else {
        $lines = preg_split('/\r\n|\n|\r/', $importText);
        $created = 0; $updated = 0;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') { continue; }
            $parts = str_getcsv($line);
            if (count($parts) < 2) { continue; }

            $code = trim((string)$parts[0]);
            $name = trim((string)$parts[1]);
            $activeRaw = strtolower(trim((string)($parts[2] ?? '1')));
            $active = in_array($activeRaw, ['1', 'true', 'active', 'yes', 'y'], true) ? 1 : 0;

            if ($code === '' || $name === '') { continue; }

            // UPSERT logic using prepared statements.
            $find = mysqli_prepare($conn, 'SELECT id FROM system_access WHERE company_id = ? AND code = ? LIMIT 1');
            $findResult = false;
            if ($find) {
                mysqli_stmt_bind_param($find, 'is', $company_id, $code);
                mysqli_stmt_execute($find);
                $findResult = mysqli_stmt_get_result($find);
            }
            if ($findResult && mysqli_num_rows($findResult) === 1) {
                $row = mysqli_fetch_assoc($findResult);
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $update = mysqli_prepare($conn, 'UPDATE system_access SET name = ?, active = ? WHERE id = ? AND company_id = ?');
                    if ($update) {
                        mysqli_stmt_bind_param($update, 'siii', $name, $active, $id, $company_id);
                        if (mysqli_stmt_execute($update)) { $updated += 1; }
                        mysqli_stmt_close($update);
                    }
                }
            } else {
                $insert = mysqli_prepare($conn, 'INSERT INTO system_access (company_id, code, name, active) VALUES (?, ?, ?, ?)');
                if ($insert) {
                    mysqli_stmt_bind_param($insert, 'issi', $company_id, $code, $name, $active);
                    if (mysqli_stmt_execute($insert)) { $created += 1; }
                    mysqli_stmt_close($insert);
                }
            }
            if ($find) { mysqli_stmt_close($find); }
        }
        $messages[] = "Import completed. Created: {$created}, Updated: {$updated}.";
    }
}

// HANDLE SEARCH
$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
$bindSearch = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $bindSearch = $searchPattern;
    $searchSql = " AND (
        CAST(id AS CHAR) LIKE ?
        OR code LIKE ?
        OR name LIKE ?
        OR CAST(active AS CHAR) LIKE ?
    )";
}

// HANDLE SORTING
$sortableColumns = ['code', 'name', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) { $sort = 'name'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }
$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;

// HANDLE PAGINATION
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$totalRows = 0;
$countSql = "SELECT COUNT(*) AS total_rows FROM system_access WHERE company_id = ?{$searchSql}";
$countStmt = mysqli_prepare($conn, $countSql);
if ($countStmt) {
    if ($bindSearch !== '') {
        mysqli_stmt_bind_param($countStmt, 'issss', $company_id, $bindSearch, $bindSearch, $bindSearch, $bindSearch);
    } else {
        mysqli_stmt_bind_param($countStmt, 'i', $company_id);
    }
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    if ($countRes) {
        $countRow = mysqli_fetch_assoc($countRes);
        $totalRows = (int)($countRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

// HANDLE CSV EXPORT
if (($_GET['export'] ?? '') === 'csv') {
    $rows = false;
    $exportSql = "SELECT code, name, active FROM system_access WHERE company_id = ?{$searchSql} ORDER BY {$sortSql}";
    $stmt = mysqli_prepare($conn, $exportSql);
    if ($stmt) {
        if ($bindSearch !== '') {
            mysqli_stmt_bind_param($stmt, 'issss', $company_id, $bindSearch, $bindSearch, $bindSearch, $bindSearch);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
        }
        mysqli_stmt_execute($stmt);
        $rows = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="system_access_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'Active']);
    while ($rows && ($row = mysqli_fetch_assoc($rows))) {
        fputcsv($output, [$row['code'] ?? '', $row['name'] ?? '', (int)($row['active'] ?? 0)]);
    }
    fclose($output);
    exit;
}

// FETCH DATA
$items = false;
$itemsSql = "SELECT id, code, name, active FROM system_access WHERE company_id = ?{$searchSql} ORDER BY {$sortSql} LIMIT ?, ?";
$stmt = mysqli_prepare($conn, $itemsSql);
if ($stmt) {
    if ($bindSearch !== '') {
        mysqli_stmt_bind_param($stmt, 'issssii', $company_id, $bindSearch, $bindSearch, $bindSearch, $bindSearch, $offset, $perPage);
    } else {
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $offset, $perPage);
    }
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}

$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) { $newButtonPosition = 'left_right'; }
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
            <div data-itm-new-button-managed="server" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1>🛡️ System Access</h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php foreach ($messages as $message): ?>
                <div class="alert alert-success" style="margin-bottom:10px;"><?php echo sanitize($message); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger" style="margin-bottom:10px;"><?php echo sanitize($error); ?></div>
            <?php endforeach; ?>

            <!-- TABLE MAINTENANCE -->
            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                </form>
            </div>

            <!-- DATA TABLE -->
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                        <?php foreach (['code' => 'Code', 'name' => 'Name', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(sa_build_query(['search' => $searchRaw, 'sort' => $field, 'dir' => $nextDir, 'page' => $page])); ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($item = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$item['id']; ?>" form="bulk-delete-form"></td>
                            <td><?php echo sanitize($item['code']); ?></td>
                            <td><?php echo sanitize($item['name']); ?></td>
                            <td>
                                <span class="badge <?php echo (int)$item['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$item['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$item['id']; ?>">👀</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$item['id']; ?>">✏️</a>
                                <form method="POST" action="delete.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                    <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete system access record?');">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center;">No system access records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(sa_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'page' => $page - 1])); ?>">Previous</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(sa_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'page' => $page + 1])); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
/**
 * Bulk deletion selection management.
 */
(function () {
    const selectAllRows = document.getElementById('select-all-rows');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const toggleButton = bulkDeleteForm ? bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
    const rowCheckboxes = bulkDeleteForm ? document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]') : [];
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) { selectAllHeaderCell.style.display = visible ? '' : 'none'; }
        deleteCells.forEach(function (cell) { cell.style.display = visible ? '' : 'none'; });
    }

    if (selectAllRows) {
        selectAllRows.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) { checkbox.checked = selectAllRows.checked; });
        });
    }

    if (bulkDeleteForm && toggleButton) {
        setSelectionVisibility(false);
        bulkDeleteForm.addEventListener('submit', function (event) {
            if (event.submitter !== toggleButton) { return; }
            if (!selectionMode) {
                event.preventDefault();
                selectionMode = true;
                setSelectionVisibility(true);
                toggleButton.textContent = 'Delete Selected';
                return;
            }
            const anySelected = Array.from(rowCheckboxes).some(function (checkbox) { return checkbox.checked; });
            if (!anySelected) {
                event.preventDefault();
                alert('Please select at least one record to delete.');
                return;
            }
            if (!confirm('Delete selected records?')) { event.preventDefault(); }
        });
    }
})();
</script>
</body>
</html>
