<?php
/**
 * Companies Module - Index
 * 
 * Provides a management interface for all companies in the system.
 * Includes search, sorting, and status badges.
 */

require '../../config/config.php';

/**
 * Helper to build a clean query string for sorting and filtering
 */
function companies_build_query($params) {
    $normalized = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $normalized[$key] = $value;
    }

    return http_build_query($normalized);
}

// Extraction of filtering and sorting parameters
$searchRaw = trim((string)($_GET['search'] ?? ''));
$sortableColumns = ['id', 'company', 'incode', 'city', 'country', 'phone', 'active'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}

// Construction of the SQL WHERE clause for search
$whereSql = ' WHERE id > 0';
$params = [];
$types = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $whereSql = ' WHERE id > 0 AND (CAST(id AS CHAR) LIKE ? OR company LIKE ? OR incode LIKE ? OR city LIKE ? OR country LIKE ? OR phone LIKE ? OR CAST(active AS CHAR) LIKE ?)';
    $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern];
    $types = 'sssssss';
}

// Build the final sort SQL
$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) AS total FROM companies' . $whereSql;
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Execute the secure prepared query
$sql = 'SELECT * FROM companies' . $whereSql . ' ORDER BY ' . $sortSql . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$stmt = mysqli_prepare($conn, $sql);
$rows = null;
if ($stmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt);
}

$csrfToken = itm_get_csrf_token();
$error = (string)($_SESSION['crud_error'] ?? '');
unset($_SESSION['crud_error']);

// Resolve UI layout preferences
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
    <title>Companies Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <!-- Header with dynamic Add button positioning -->
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1>🏢 Companies</h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <!-- Search Filter Bar -->
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;">
                    <div class="form-group" style="margin:0;flex:1;">
                        <label for="companySearch">Search (all fields)</label>
                        <input type="text" id="companySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%abc%%">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn">🔙</a>
                </form>
            </div>

            <!-- Companies Data Table -->
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <?php foreach (['id' => 'ID', 'company' => 'Company', 'incode' => 'InCode', 'city' => 'City', 'country' => 'Country', 'phone' => 'Phone', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(companies_build_query(['search' => $searchRaw, 'sort' => $field, 'dir' => $nextDir])); ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && mysqli_num_rows($rows) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['company']); ?></td>
                                <td><?php echo sanitize($row['incode']); ?></td>
                                <td><?php echo sanitize($row['city']); ?></td>
                                <td><?php echo sanitize($row['country']); ?></td>
                                <td><?php echo sanitize($row['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo (int)$row['active'] === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo (int)$row['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="itm-actions-cell">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this company?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;">No companies found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(companies_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'page' => $page - 1])); ?>">« Prev</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.85;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(companies_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'page' => $page + 1])); ?>">Next »</a>
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
<?php if ($stmt) { mysqli_stmt_close($stmt); } ?>
