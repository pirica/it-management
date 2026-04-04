<?php
require '../../config/config.php';

$searchRaw = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
$page = (int)($_GET['page'] ?? 1);
$perPage = itm_resolve_records_per_page($ui_config ?? null);

$allowedSort = ['name', 'description', 'active', 'id'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'name';
}
if ($dir !== 'ASC' && $dir !== 'DESC') {
    $dir = 'ASC';
}
if ($page < 1) {
    $page = 1;
}

$searchPattern = '';
$where = ' WHERE company_id = ?';
if ($searchRaw !== '') {
    $hasWildcards = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false);
    $searchPattern = $hasWildcards ? $searchRaw : '%' . $searchRaw . '%';
    $where .= ' AND (CAST(id AS CHAR) LIKE ? OR name LIKE ? OR description LIKE ? OR CAST(active AS CHAR) LIKE ?)';
}

$totalRows = 0;
$countSql = 'SELECT COUNT(*) AS total FROM departments' . $where;
$countStmt = mysqli_prepare($conn, $countSql);
if ($countStmt) {
    if ($searchPattern !== '') {
        mysqli_stmt_bind_param($countStmt, 'issss', $company_id, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
    } else {
        mysqli_stmt_bind_param($countStmt, 'i', $company_id);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$orderBy = '`' . str_replace('`', '``', $sort) . '` ' . $dir;
$listSql = 'SELECT id, name, description, active FROM departments' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ?, ?';
$listStmt = mysqli_prepare($conn, $listSql);
$departments = [];
if ($listStmt) {
    if ($searchPattern !== '') {
        mysqli_stmt_bind_param($listStmt, 'issssii', $company_id, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $offset, $perPage);
    } else {
        mysqli_stmt_bind_param($listStmt, 'iii', $company_id, $offset, $perPage);
    }
    mysqli_stmt_execute($listStmt);
    $listResult = mysqli_stmt_get_result($listStmt);
    while ($listResult && ($row = mysqli_fetch_assoc($listResult))) {
        $departments[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$crudError = (string)($_SESSION['crud_error'] ?? '');
unset($_SESSION['crud_error']);
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
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
                <h1>🏢 Departments</h1>
                <a class="btn btn-primary" href="create.php">➕ Add Department</a>
            </div>

            <?php if ($crudError !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?php echo sanitize($crudError); ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:12px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:240px;flex:1;">
                        <label for="departmentSearch">Search departments</label>
                        <input id="departmentSearch" type="text" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Name, description, or active status">
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <a class="btn" href="index.php">Clear</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all-departments" aria-label="Select all departments"></th>
                        <?php foreach (['name' => 'Name', 'description' => 'Description', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th>
                                <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo urlencode($nextDir); ?>&page=1" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    <tr>
                        <th colspan="5" style="text-align:left;">
                            <form id="department-bulk-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_post_csrf_token()); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" name="bulk_action" value="bulk_delete" id="bulk-delete-toggle">Select to Delete</button>
                                <button type="submit" class="btn btn-sm btn-danger" name="bulk_action" value="clear_table" onclick="return confirm('Clear all departments for this company?');">Clear Table</button>
                            </form>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $department): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$department['id']; ?>" form="department-bulk-form"></td>
                                <td><?php echo sanitize($department['name']); ?></td>
                                <td><?php echo sanitize((string)($department['description'] ?? '')); ?></td>
                                <td><span class="badge <?php echo (int)$department['active'] === 1 ? 'badge-success' : 'badge-danger'; ?>"><?php echo (int)$department['active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$department['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$department['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this department?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_post_csrf_token()); ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$department['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No departments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="opacity:.8;pointer-events:none;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
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
    const form = document.getElementById('department-bulk-form');
    const toggle = document.getElementById('bulk-delete-toggle');
    const selectAll = document.getElementById('select-all-departments');
    const checkboxes = form ? Array.from(document.querySelectorAll('input[name="ids[]"][form="department-bulk-form"]')) : [];
    const rowCells = checkboxes.map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectHeader = selectAll ? selectAll.closest('th') : null;
    let selecting = false;

    function setSelectionMode(enabled) {
        if (selectHeader) {
            selectHeader.style.display = enabled ? '' : 'none';
        }
        rowCells.forEach(function (cell) {
            cell.style.display = enabled ? '' : 'none';
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    if (form && toggle) {
        setSelectionMode(false);
        form.addEventListener('submit', function (event) {
            if (!event.submitter || event.submitter.value !== 'bulk_delete') {
                return;
            }

            if (!selecting) {
                event.preventDefault();
                selecting = true;
                setSelectionMode(true);
                toggle.textContent = 'Delete Selected';
                return;
            }

            const selected = checkboxes.some(function (checkbox) { return checkbox.checked; });
            if (!selected) {
                event.preventDefault();
                alert('Please select at least one department.');
                return;
            }

            if (!confirm('Delete selected departments?')) {
                event.preventDefault();
            }
        });
    }
})();
</script>
<script src="../../js/theme.js"></script>
</body>
</html>
