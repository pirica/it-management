<?php
/**
 * Employee System Access Module - Index
 * 
 * Provides a matrix-style overview of system access permissions for all employees.
 * Each column represents a system, and each row an employee.
 * Supports sorting by any system to quickly see who has access.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Ensure the required permission tables exist
esa_ensure_table($conn);

// Load the catalog of available systems to build matrix columns
$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, false);
$accessIds = array_map(static fn($row) => (int)($row['id'] ?? 0), $systemAccessCatalog);
$accessLabelsById = [];
foreach ($systemAccessCatalog as $access) {
    $accessId = (int)($access['id'] ?? 0);
    if ($accessId <= 0) { continue; }
    $accessLabelsById[$accessId] = (string)($access['name'] ?? '');
}

// Define available columns for sorting
$columns = array_merge(['employee_name', 'email'], array_map(static fn($id) => 'access_' . $id, array_keys($accessLabelsById)));

// Extract sort and search parameters
$sort = (string)($_GET['sort'] ?? 'employee_name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
$searchRaw = trim((string)($_GET['search'] ?? ''));

if (!in_array($sort, $columns, true)) { $sort = 'employee_name'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'ASC'; }

/**
 * Helper to build query strings
 */
function esa_module_build_query($params) {
    $normalized = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') { continue; }
        $normalized[$key] = $value;
    }
    return http_build_query($normalized);
}

// Build the search filter
$where = ' WHERE e.company_id=' . (int)$company_id;
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchValue = mysqli_real_escape_string($conn, $searchPattern);
    $where .= " AND (COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) LIKE '{$searchValue}' OR COALESCE(e.email, '') LIKE '{$searchValue}')";
}

// Build the order by clause
$sortSql = $sort === 'employee_name'
    ? "COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) {$dir}"
    : ($sort === 'email' ? "e.email {$dir}" : "COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) ASC");

// Pagination logic
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$totalRows = 0;
$countSql = "SELECT COUNT(*) AS total_rows FROM employees e{$where}";
$countRes = mysqli_query($conn, $countSql);
if ($countRes) {
    $countRow = mysqli_fetch_assoc($countRes);
    $totalRows = (int)($countRow['total_rows'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

// Fetch employee records for the current page
$sql = "SELECT e.id AS employee_id,
            COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) AS employee_name,
            e.email
        FROM employees e"
        . $where
        . ' ORDER BY ' . $sortSql . " LIMIT {$offset}, {$perPage}";
$rows = mysqli_query($conn, $sql);

$employees = [];
$employeeIds = [];
while ($rows && ($row = mysqli_fetch_assoc($rows))) {
    $employeeId = (int)($row['employee_id'] ?? 0);
    if ($employeeId <= 0) { continue; }
    $row['grants'] = [];
    $employees[] = $row;
    $employeeIds[] = $employeeId;
}

// Bulk fetch permission grants for all displayed employees
if (!empty($employeeIds) && !empty($accessIds)) {
    $employeeIdSql = implode(',', array_map('intval', $employeeIds));
    $accessIdSql = implode(',', array_map('intval', $accessIds));
    $mapSql = 'SELECT employee_id, system_access_id FROM employee_system_access_relations WHERE company_id=' . (int)$company_id
        . ' AND granted=1 AND employee_id IN (' . $employeeIdSql . ') AND system_access_id IN (' . $accessIdSql . ')';
    $mapRes = mysqli_query($conn, $mapSql);
    $grantsByEmployee = [];
    while ($mapRes && ($mapRow = mysqli_fetch_assoc($mapRes))) {
        $eid = (int)($mapRow['employee_id'] ?? 0);
        $aid = (int)($mapRow['system_access_id'] ?? 0);
        if ($eid > 0 && $aid > 0) {
            $grantsByEmployee[$eid][$aid] = true;
        }
    }

    // Map grants back to the employee objects
    foreach ($employees as $idx => $employee) {
        $eid = (int)($employee['employee_id'] ?? 0);
        $employees[$idx]['grants'] = $grantsByEmployee[$eid] ?? [];
    }
}

// Perform client-side sorting for permission columns
if ($sort !== 'employee_name' && $sort !== 'email' && str_starts_with($sort, 'access_')) {
    $sortAccessId = (int)substr($sort, strlen('access_'));
    usort($employees, static function ($a, $b) use ($sortAccessId, $dir) {
        $aHas = isset($a['grants'][$sortAccessId]) ? 1 : 0;
        $bHas = isset($b['grants'][$sortAccessId]) ? 1 : 0;
        if ($aHas === $bHas) {
            return strcasecmp((string)($a['employee_name'] ?? ''), (string)($b['employee_name'] ?? ''));
        }
        return $dir === 'ASC' ? ($aHas <=> $bHas) : ($bHas <=> $aHas);
    });
}

// Handle CSV export request
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=employee_system_access.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['Employee Name', 'Email'], array_values($accessLabelsById)));
    foreach ($employees as $row) {
        $line = [(string)($row['employee_name'] ?? ''), (string)($row['email'] ?? '')];
        foreach (array_keys($accessLabelsById) as $accessId) {
            $line[] = isset($row['grants'][$accessId]) ? 'Yes' : 'No';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: '🔐 Employee System Access';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="position:relative;display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-bottom:20px;min-height:40px;flex-wrap:wrap;">
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <a href="?<?php echo sanitize(esa_module_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'export' => 'csv'])); ?>" class="btn btn-primary">⬇ Export CSV</a>
            </div>

            <!-- Search Filter -->
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="abilitySearch">Search (employee + email)</label>
                        <input type="text" id="abilitySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Try: john@ or jane">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>

            <!-- Permission Matrix Table -->
            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <?php $nextDir = ($sort === $column && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th>
                                <a href="?<?php echo sanitize(esa_module_build_query(['search' => $searchRaw, 'sort' => $column, 'dir' => $nextDir])); ?>" style="text-decoration:none;color:inherit;">
                                    <?php
                                    if ($column === 'employee_name') {
                                        echo 'Employee Name';
                                    } elseif ($column === 'email') {
                                        echo 'Email';
                                    } else {
                                        $accessId = (int)substr($column, strlen('access_'));
                                        echo sanitize($accessLabelsById[$accessId] ?? $column);
                                    }
                                    ?>
                                    <?php if ($sort === $column): ?><?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($employees)): foreach ($employees as $row): ?>
                        <tr>
                            <td><?php echo sanitize((string)($row['employee_name'] ?? '')); ?></td>
                            <td>
                                <?php if (!empty($row['email'])): ?>
                                    <a href="mailto:<?php echo sanitize((string)$row['email']); ?>"><?php echo sanitize((string)$row['email']); ?></a>
                                <?php endif; ?>
                            </td>
                            <?php foreach (array_keys($accessLabelsById) as $accessId): ?>
                                <td><?php echo isset($row['grants'][$accessId]) ? '✅' : '❌'; ?></td>
                            <?php endforeach; ?>
                            <td>
                                <a class="btn btn-sm" href="view.php?employee_id=<?php echo (int)$row['employee_id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?employee_id=<?php echo (int)$row['employee_id']; ?>">✏️</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="<?php echo count($columns) + 1; ?>" style="text-align:center;">No rows found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(esa_module_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'page' => $page - 1])); ?>">Previous</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(esa_module_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'page' => $page + 1])); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
