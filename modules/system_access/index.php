<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

$searchRaw = trim((string)($_GET['search'] ?? ''));
$sortableColumns = ['code', 'name', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));

if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}

$where = 'WHERE company_id=' . (int)$company_id;
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $where .= " AND (CAST(id AS CHAR) LIKE '{$searchEsc}' OR code LIKE '{$searchEsc}' OR name LIKE '{$searchEsc}' OR CAST(active AS CHAR) LIKE '{$searchEsc}')";
}

$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;
$sql = "SELECT id, code, name, active FROM system_access {$where} ORDER BY {$sortSql}";
$rows = mysqli_query($conn, $sql);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=system_access.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'Name', 'Status']);
    while ($rows && ($row = mysqli_fetch_assoc($rows))) {
        fputcsv($output, [
            (int)$row['id'],
            (string)$row['code'],
            (string)$row['name'],
            ((int)$row['active'] === 1) ? 'Active' : 'Inactive',
        ]);
    }
    fclose($output);
    exit;
}

function sa_query(array $params): string {
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $filtered[$key] = $value;
    }
    return http_build_query($filtered);
}
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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
                <h1 style="margin:0;">🛡️ System Access</h1>
                <div style="display:flex;gap:8px;">
                    <a href="?<?php echo sanitize(sa_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'export' => 'csv'])); ?>" class="btn btn-primary">⬇ Export CSV</a>
                    <a class="btn btn-primary" href="create.php">➕</a>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="systemAccessSearch">Search (all fields)</label>
                        <input type="text" id="systemAccessSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Try: opera, network, active">
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
                            <?php foreach (['code' => 'Code', 'name' => 'Name', 'active' => 'Status'] as $field => $label): ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?<?php echo sanitize(sa_query(['search' => $searchRaw, 'sort' => $field, 'dir' => $nextDir])); ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <td><?php echo sanitize((string)$row['code']); ?></td>
                                <td><?php echo sanitize((string)$row['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo ((int)$row['active'] === 1) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ((int)$row['active'] === 1) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align:center;">No records found.</td></tr>
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
