<?php
/**
 * Birthdays Module
 *
 * Read-only monthly birthday list sourced from employees.birthday.
 */

require_once '../../config/config.php';
require_once '../../includes/employee_profile_photo.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);

$selectedMonth = (int)($_GET['month'] ?? date('n'));
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'birth_day'));
$dir = strtoupper(trim((string)($_GET['dir'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';

$sortMap = [
    'birth_day' => 'DAY(e.birthday)',
    'name' => 'e.last_name, e.first_name',
    'department_code' => 'd.code',
];
if (!isset($sortMap[$sort])) {
    $sort = 'birth_day';
}
$orderSql = $sortMap[$sort] . ' ' . $dir;

$rows = [];
$sql = 'SELECT e.id, e.first_name, e.last_name, e.birthday, e.hide_year, e.username, e.user_id, e.photo, d.code AS department_code '
    . 'FROM employees e '
    . 'INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id '
    . 'LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id '
    . 'WHERE e.company_id = ? AND e.birthday IS NOT NULL AND MONTH(e.birthday) = ? '
    . "AND es.name IN ('Active', 'On Leave')";
$types = 'ii';
$params = [$company_id, $selectedMonth];

if ($search !== '') {
    $searchPattern = (strpos($search, '%') !== false || strpos($search, '_') !== false) ? $search : '%' . $search . '%';
    $sql .= ' AND (
        e.first_name LIKE ?
        OR e.last_name LIKE ?
        OR CONCAT(e.first_name, \' \', e.last_name) LIKE ?
        OR CAST(DAY(e.birthday) AS CHAR) LIKE ?
        OR COALESCE(d.code, \'\') LIKE ?
        OR COALESCE(d.name, \'\') LIKE ?
    )';
    $types .= 'ssssss';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$sql .= ' ORDER BY ' . $orderSql;

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
}

function bdays_sort_url($column, $currentSort, $currentDir, $month, $search) {
    $nextDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $query = [
        'month' => $month,
        'sort' => $column,
        'dir' => $nextDir,
    ];
    if ($search !== '') {
        $query['search'] = $search;
    }
    return 'index.php?' . http_build_query($query);
}

function bdays_sort_indicator($column, $currentSort, $currentDir) {
    if ($currentSort !== $column) {
        return '';
    }
    return $currentDir === 'ASC' ? ' ▲' : ' ▼';
}

$monthLabel = date('F', mktime(0, 0, 0, $selectedMonth, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birthdays - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .bdays-controls { display:flex; gap:10px; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap; }
        .bdays-table thead a {
            text-decoration: none;
            color: inherit;
        }
        @media print {
            .bdays-controls, .table-tools, .table-search-inline { display:none !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
                <h1 style="margin:0;">🎉 Birthdays</h1>
                <a href="../employees/index.php" class="btn">👤 Employees</a>
            </div>

            <div class="card bdays-controls" data-itm-no-export-pdf="1" data-itm-no-export-excel="1">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label for="month">Month</label>
                        <select name="month" id="month" class="form-control" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="search">Search (all fields)</label>
                        <input type="search" name="search" id="search" class="form-control" value="<?= sanitize($search) ?>" placeholder="Type to search...">
                    </div>
                    <input type="hidden" name="sort" value="<?= sanitize($sort) ?>">
                    <input type="hidden" name="dir" value="<?= sanitize($dir) ?>">
                    <button type="submit" class="btn btn-primary" title="🔎 Search">Search</button>
                    <?php if ($search !== ''): ?>
                        <a class="btn" href="index.php?month=<?= (int)$selectedMonth ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card" style="margin-bottom:8px;">
                <p style="margin:0;" class="muted">Showing birthdays in <strong><?= sanitize($monthLabel) ?></strong> for <strong>Active</strong> and <strong>On Leave</strong> employees<?= $search !== '' ? ' matching <strong>' . sanitize($search) . '</strong>' : '' ?>.</p>
            </div>

            <div class="card" style="overflow:auto;">
                <table class="table bdays-table" data-itm-no-import-excel="1">
                    <thead>
                        <tr>
                            <th><a href="<?= sanitize(bdays_sort_url('name', $sort, $dir, $selectedMonth, $search)) ?>">Name<?= bdays_sort_indicator('name', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(bdays_sort_url('birth_day', $sort, $dir, $selectedMonth, $search)) ?>">Day<?= bdays_sort_indicator('birth_day', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(bdays_sort_url('department_code', $sort, $dir, $selectedMonth, $search)) ?>">Department<?= bdays_sort_indicator('department_code', $sort, $dir) ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="3">No birthdays found for this month.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $fullName = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
                            $photoUrl = emp_profile_photo_url($row);
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <?php if ($photoUrl !== ''): ?>
                                            <img src="<?= sanitize($photoUrl) ?>" alt="" width="28" height="28" class="rounded-circle" style="object-fit:cover;" onerror="this.style.display='none';">
                                        <?php endif; ?>
                                        <?= sanitize($fullName) ?>
                                    </div>
                                </td>
                                <td><?= sanitize(emp_format_birthday_day_only($row['birthday'] ?? null)) ?></td>
                                <td><?= sanitize((string)($row['department_code'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
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
