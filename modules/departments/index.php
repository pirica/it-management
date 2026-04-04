<?php
require '../../config/config.php';
$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
$bindSearch = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $bindSearch = $searchPattern;
    $searchSql = " AND (
        CAST(id AS CHAR) LIKE ?
        OR name LIKE ?
        OR description LIKE ?
        OR CAST(active AS CHAR) LIKE ?
    )";
}
$sortableColumns = ['name', 'description', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countSql = "SELECT COUNT(*) AS total_rows FROM departments WHERE company_id = ?{$searchSql}";
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    if ($bindSearch !== '') {
        mysqli_stmt_bind_param($countStmt, 'issss', $company_id, $bindSearch, $bindSearch, $bindSearch, $bindSearch);
    } else {
        mysqli_stmt_bind_param($countStmt, 'i', $company_id);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)($countRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$items = false;
$sql = "SELECT * FROM departments WHERE company_id = ?{$searchSql} ORDER BY {$sortSql} LIMIT ?, ?";
$stmt = mysqli_prepare($conn, $sql);
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

$allDepartmentsCount = 0;
$allCountResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM departments');
if ($allCountResult && ($allCountRow = mysqli_fetch_assoc($allCountResult))) {
    $allDepartmentsCount = (int)($allCountRow['total_rows'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🏢 Departments</h1>
                <a class="btn btn-primary" href="create.php">➕</a>
            </div>
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="departmentSearch">Search (all fields)</label>
                        <input type="text" id="departmentSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%ops%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-sm">Clear</a>
                    </div>
                </form>
            </div>
            <div class="card">
                <?php if ($totalRows === 0 && $allDepartmentsCount > 0): ?>
                    <div class="alert alert-danger" style="margin-bottom:16px;">
                        No departments are assigned to your active company (ID <?php echo (int)$company_id; ?>).
                        Total departments in database: <?php echo (int)$allDepartmentsCount; ?>.
                    </div>
                <?php endif; ?>
                <table>
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all-departments" aria-label="Select all rows"></th>
                        <?php foreach (['name' => 'Name', 'description' => 'Description', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    <tr>
                        <th colspan="5" style="text-align:left;">
                            <form id="department-bulk-form" method="POST" action="delete.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_post_csrf_token()); ?>">
                                <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                                <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all departments for this company? This cannot be undone.');">Clear Table</button>
                            </form>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($d = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$d['id']; ?>" form="department-bulk-form"></td>
                            <td><?php echo sanitize($d['name']); ?></td>
                            <td><?php echo sanitize($d['description'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo (int)$d['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$d['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$d['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$d['id']; ?>">✏️</a>
                                <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete department?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_post_csrf_token()); ?>">
                                    <input type="hidden" name="bulk_action" value="single_delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                    <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center;">No departments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    const selectAllRows = document.getElementById('select-all-rows') || document.getElementById('select-all-departments');
    const bulkDeleteForm = document.querySelector('form[id="bulk-delete-form"], form[id="department-bulk-form"]');
    const toggleButton = bulkDeleteForm ? bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
    const rowCheckboxes = bulkDeleteForm ? document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]') : [];
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) {
            selectAllHeaderCell.style.display = visible ? '' : 'none';
        }
        deleteCells.forEach(function (cell) {
            cell.style.display = visible ? '' : 'none';
        });
    }

    if (selectAllRows) {
        selectAllRows.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllRows.checked;
            });
        });
    }

    if (bulkDeleteForm && toggleButton) {
        setSelectionVisibility(false);

        bulkDeleteForm.addEventListener('submit', function (event) {
            if (event.submitter !== toggleButton) {
                return;
            }

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

            if (!confirm('Delete selected records?')) {
                event.preventDefault();
            }
        });
    }
})();
</script>
<script src="../../js/theme.js"></script>
</body>
</html>
