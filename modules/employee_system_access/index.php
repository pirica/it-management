<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

$abilityFields = esa_ability_fields();
$columns = array_merge(['employee_name', 'email'], array_keys($abilityFields));

$sort = (string)($_GET['sort'] ?? 'employee_name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
$searchRaw = trim((string)($_GET['search'] ?? ''));

if (!in_array($sort, $columns, true)) {
    $sort = 'employee_name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}

function esa_module_build_query($params) {
    $normalized = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $normalized[$key] = $value;
    }
    return http_build_query($normalized);
}

$where = ' WHERE e.company_id=' . (int)$company_id;
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchValue = mysqli_real_escape_string($conn, $searchPattern);
    $conditions = [
        "CAST(COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) AS CHAR) LIKE '{$searchValue}'",
        "CAST(COALESCE(e.email, '') AS CHAR) LIKE '{$searchValue}'",
    ];
    foreach ($abilityFields as $field => $label) {
        $fieldEsc = esa_escape_identifier($field);
        $conditions[] = "CAST(esa.{$fieldEsc} AS CHAR) LIKE '{$searchValue}'";
        if (stripos($label, $searchRaw) !== false || stripos($field, $searchRaw) !== false) {
            $conditions[] = "esa.{$fieldEsc}=1";
        }
    }
    $where .= ' AND (' . implode(' OR ', $conditions) . ')';
}

$sortSql = $sort === 'employee_name'
    ? "COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) {$dir}"
    : ($sort === 'email'
        ? "e.email {$dir}"
        : ('esa.' . esa_escape_identifier($sort) . ' ' . $dir));

$sql = "SELECT e.id AS employee_id,
            COALESCE(NULLIF(e.display_name, ''), CONCAT(e.first_name, ' ', e.last_name)) AS employee_name,
            e.email,
            esa.network_access, esa.micros_emc, esa.opera_username, esa.micros_card, esa.pms_id, esa.synergy_mms,
            esa.hu_the_lobby, esa.navision, esa.onq_ri, esa.birchstreet, esa.delphi, esa.omina,
            esa.vingcard_system, esa.digital_rev, esa.office_key_card
        FROM employees e
        LEFT JOIN employee_system_access esa ON esa.company_id=e.company_id AND esa.employee_id=e.id"
        . $where .
        ' ORDER BY ' . $sortSql . ' LIMIT 1000';
$rows = mysqli_query($conn, $sql);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=employee_system_access.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['Employee Name', 'Email'], array_values($abilityFields)));
    while ($rows && ($row = mysqli_fetch_assoc($rows))) {
        $line = [(string)($row['employee_name'] ?? ''), (string)($row['email'] ?? '')];
        foreach (array_keys($abilityFields) as $field) {
            $line[] = ((int)($row[$field] ?? 0) === 1) ? 'Yes' : 'No';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
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
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
                <h1 style="margin:0;">Employee System Access</h1>
                <a href="?<?php echo sanitize(esa_module_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'export' => 'csv'])); ?>" class="btn btn-primary">⬇ Export CSV</a>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="abilitySearch">Search (employee + ability)</label>
                        <input type="text" id="abilitySearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Try: opera, network, %yes%">
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
                        <?php foreach ($columns as $column): ?>
                            <?php $nextDir = ($sort === $column && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th>
                                <a href="?<?php echo sanitize(esa_module_build_query(['search' => $searchRaw, 'sort' => $column, 'dir' => $nextDir])); ?>" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize($column === 'employee_name' ? 'Employee Name' : ($column === 'email' ? 'Email' : ($abilityFields[$column] ?? $column))); ?>
                                    <?php if ($sort === $column): ?><?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                        <tr>
                            <td><?php echo sanitize((string)($row['employee_name'] ?? '')); ?></td>
                            <td>
                                <?php if (!empty($row['email'])): ?>
                                    <a href="mailto:<?php echo sanitize((string)$row['email']); ?>"><?php echo sanitize((string)$row['email']); ?></a>
                                <?php endif; ?>
                            </td>
                            <?php foreach (array_keys($abilityFields) as $field): ?>
                                <td><?php echo ((int)($row[$field] ?? 0) === 1) ? '✔️' : '❌'; ?></td>
                            <?php endforeach; ?>
                            <td><a class="btn btn-sm" href="edit.php?employee_id=<?php echo (int)$row['employee_id']; ?>">✏️</a></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="<?php echo count($columns) + 1; ?>" style="text-align:center;">No rows found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
