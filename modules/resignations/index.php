<?php
/**
 * Resignations Module
 *
 * Read-only weekly resignation list sourced from employees.termination_date.
 */

require_once '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'view', 'resignations');

$company_id = (int)($_SESSION['company_id'] ?? 0);

$selectedYear = (int)($_GET['year'] ?? date('Y'));
if ($selectedYear < 1970 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}

$selectedMonth = (int)($_GET['month'] ?? date('n'));
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}

$selectedWeek = (int)($_GET['week'] ?? (int)date('W'));
if ($selectedWeek < 1 || $selectedWeek > 53) {
    $selectedWeek = (int)date('W');
}

$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'termination_date'));
$dir = strtoupper(trim((string)($_GET['dir'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';

$sortMap = [
    'external_id' => 'e.external_id',
    'name' => 'e.last_name, e.first_name',
    'employee_type' => 'et.name_type',
    'department' => 'd.name',
    'start_date' => 'e.start_date',
    'termination_date' => 'e.termination_date',
    'resignation_week' => 'e.termination_date',
];
if (!isset($sortMap[$sort])) {
    $sort = 'termination_date';
}
$orderSql = $sortMap[$sort] . ' ' . $dir;

// Why: Tenant-scoped status options drive the Employment Status multi-select and SQL IN filter.
$statusOptions = [];
$statusOptionsById = [];
$statusStmt = mysqli_prepare($conn, 'SELECT id, name FROM employee_statuses WHERE company_id = ? AND active = 1 ORDER BY name');
if ($statusStmt) {
    mysqli_stmt_bind_param($statusStmt, 'i', $company_id);
    mysqli_stmt_execute($statusStmt);
    $statusRes = mysqli_stmt_get_result($statusStmt);
    while ($statusRes && ($statusRow = mysqli_fetch_assoc($statusRes))) {
        $statusId = (int)$statusRow['id'];
        $statusOptions[] = ['id' => $statusId, 'name' => (string)$statusRow['name']];
        $statusOptionsById[$statusId] = (string)$statusRow['name'];
    }
    mysqli_stmt_close($statusStmt);
}

$defaultStatusIds = [];
foreach ($statusOptions as $statusOption) {
    if (in_array($statusOption['name'], ['Active', 'Inactive', 'On Leave', 'Terminated'], true)) {
        $defaultStatusIds[] = (int)$statusOption['id'];
    }
}
if ($defaultStatusIds === []) {
    foreach ($statusOptions as $statusOption) {
        $defaultStatusIds[] = (int)$statusOption['id'];
    }
}

$selectedStatusIds = [];
if (isset($_GET['employment_status_id']) && is_array($_GET['employment_status_id'])) {
    foreach ($_GET['employment_status_id'] as $rawStatusId) {
        $statusId = (int)$rawStatusId;
        if ($statusId > 0 && isset($statusOptionsById[$statusId])) {
            $selectedStatusIds[] = $statusId;
        }
    }
    $selectedStatusIds = array_values(array_unique($selectedStatusIds));
} else {
    $selectedStatusIds = $defaultStatusIds;
}

$selectedStatusNames = [];
foreach ($selectedStatusIds as $selectedStatusId) {
    $selectedStatusNames[] = $statusOptionsById[$selectedStatusId] ?? '';
}
$selectedStatusNames = array_values(array_filter($selectedStatusNames, static function ($name) {
    return $name !== '';
}));

// Why: Employee Type multi-select defaults to Team member + Internship for the weekly report.
$typeOptions = [];
$typeOptionsById = [];
$typeStmt = mysqli_prepare($conn, 'SELECT id, name_type FROM employee_type WHERE company_id = ? AND active = 1 ORDER BY name_type');
if ($typeStmt) {
    mysqli_stmt_bind_param($typeStmt, 'i', $company_id);
    mysqli_stmt_execute($typeStmt);
    $typeRes = mysqli_stmt_get_result($typeStmt);
    while ($typeRes && ($typeRow = mysqli_fetch_assoc($typeRes))) {
        $typeId = (int)$typeRow['id'];
        $typeOptions[] = ['id' => $typeId, 'name_type' => (string)$typeRow['name_type']];
        $typeOptionsById[$typeId] = (string)$typeRow['name_type'];
    }
    mysqli_stmt_close($typeStmt);
}

$defaultTypeIds = [];
foreach ($typeOptions as $typeOption) {
    if (in_array($typeOption['name_type'], ['Team member', 'Internship'], true)) {
        $defaultTypeIds[] = (int)$typeOption['id'];
    }
}
if ($defaultTypeIds === []) {
    foreach ($typeOptions as $typeOption) {
        $defaultTypeIds[] = (int)$typeOption['id'];
    }
}

$selectedTypeIds = [];
if (isset($_GET['employee_type_id']) && is_array($_GET['employee_type_id'])) {
    foreach ($_GET['employee_type_id'] as $rawTypeId) {
        $typeId = (int)$rawTypeId;
        if ($typeId > 0 && isset($typeOptionsById[$typeId])) {
            $selectedTypeIds[] = $typeId;
        }
    }
    $selectedTypeIds = array_values(array_unique($selectedTypeIds));
} else {
    $selectedTypeIds = $defaultTypeIds;
}

$selectedTypeNames = [];
foreach ($selectedTypeIds as $selectedTypeId) {
    $selectedTypeNames[] = $typeOptionsById[$selectedTypeId] ?? '';
}
$selectedTypeNames = array_values(array_filter($selectedTypeNames, static function ($name) {
    return $name !== '';
}));

$rows = [];
$isoWeekBounds = itm_iso_week_bounds($selectedYear, $selectedWeek);
if ($selectedStatusIds !== [] && $selectedTypeIds !== [] && $isoWeekBounds !== null) {
    $statusPlaceholders = implode(',', array_fill(0, count($selectedStatusIds), '?'));
    $typePlaceholders = implode(',', array_fill(0, count($selectedTypeIds), '?'));
    $sql = 'SELECT e.id, e.external_id, e.first_name, e.last_name, e.start_date, e.termination_date, '
        . 'et.name_type AS employee_type_name, d.name AS department_name '
        . 'FROM employees e '
        . 'INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id '
        . 'LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id '
        . 'LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id '
        . 'WHERE e.company_id = ? '
        . 'AND e.termination_date IS NOT NULL '
        . 'AND ' . itm_sql_valid_date_predicate('e.termination_date') . ' '
        . 'AND e.termination_date >= ? '
        . 'AND e.termination_date <= ? '
        . 'AND MONTH(e.termination_date) = ? '
        . 'AND es.id IN (' . $statusPlaceholders . ') '
        . 'AND (e.employee_type_id IS NULL OR e.employee_type_id IN (' . $typePlaceholders . '))';
    $types = 'issi' . str_repeat('i', count($selectedStatusIds)) . str_repeat('i', count($selectedTypeIds));
    $params = array_merge(
        [$company_id, $isoWeekBounds['start'], $isoWeekBounds['end'], $selectedMonth],
        $selectedStatusIds,
        $selectedTypeIds
    );

    if ($search !== '') {
        $searchPattern = (strpos($search, '%') !== false || strpos($search, '_') !== false) ? $search : '%' . $search . '%';
        $sql .= ' AND (
            e.external_id LIKE ?
            OR e.first_name LIKE ?
            OR e.last_name LIKE ?
            OR CONCAT(e.first_name, \' \', e.last_name) LIKE ?
            OR COALESCE(et.name_type, \'\') LIKE ?
            OR COALESCE(d.name, \'\') LIKE ?
            OR COALESCE(d.code, \'\') LIKE ?
            OR CAST(e.start_date AS CHAR) LIKE ?
            OR CAST(e.termination_date AS CHAR) LIKE ?
            OR CAST(WEEK(e.termination_date, 3) AS CHAR) LIKE ?
        )';
        $types .= 'ssssssssss';
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
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
}

function resign_sort_url($column, $currentSort, $currentDir, $year, $month, $week, $search, $selectedStatusIds, $selectedTypeIds) {
    $nextDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $query = [
        'year' => $year,
        'month' => $month,
        'week' => $week,
        'sort' => $column,
        'dir' => $nextDir,
    ];
    if ($search !== '') {
        $query['search'] = $search;
    }
    if (!empty($selectedStatusIds)) {
        $query['employment_status_id'] = $selectedStatusIds;
    }
    if (!empty($selectedTypeIds)) {
        $query['employee_type_id'] = $selectedTypeIds;
    }
    return 'index.php?' . http_build_query($query);
}

function resign_sort_indicator($column, $currentSort, $currentDir) {
    if ($currentSort !== $column) {
        return '';
    }
    return $currentDir === 'ASC' ? ' ▲' : ' ▼';
}

function resign_format_week_label($terminationDate) {
    $dateText = trim((string)$terminationDate);
    if ($dateText === '' || $dateText === '0000-00-00') {
        return '—';
    }
    $timestamp = strtotime($dateText);
    if ($timestamp === false) {
        return '—';
    }
    return (string)(int)date('W', $timestamp) . '/' . date('y', $timestamp);
}

$yearShort = substr((string)$selectedYear, -2);
$reportTitle = 'Weekly Resignations Report - Week ' . $selectedWeek . '/' . $yearShort;
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Resignations';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .resign-controls { display:flex; gap:10px; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap; }
        .resign-controls .resign-multi-select { min-width: 180px; }
        .resign-table thead a { text-decoration: none; color: inherit; }
        @media print {
            .resign-controls, .table-tools, .table-search-inline { display:none !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                <h1 style="margin:0;">📋 <?= sanitize($reportTitle) ?></h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="../employees/index.php" class="btn">👤 Employees</a>
                </div>
            </div>

            <div class="card resign-controls" data-itm-no-export-pdf="1" data-itm-no-export-excel="1">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label for="week">Week</label>
                        <select name="week" id="week" class="form-control">
                            <?php for ($w = 1; $w <= 53; $w++): ?>
                                <option value="<?= $w ?>" <?= $w === $selectedWeek ? 'selected' : '' ?>><?= $w ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="month">Month</label>
                        <select name="month" id="month" class="form-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="year">Year</label>
                        <select name="year" id="year" class="form-control">
                            <?php for ($y = (int)date('Y') - 5; $y <= (int)date('Y') + 2; $y++): ?>
                                <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="employment_status_id">Employment Status</label>
                        <select name="employment_status_id[]" id="employment_status_id" class="form-control resign-multi-select" multiple size="<?= max(3, min(6, count($statusOptions))) ?>">
                            <?php foreach ($statusOptions as $statusOption): ?>
                                <option value="<?= (int)$statusOption['id'] ?>" <?= in_array((int)$statusOption['id'], $selectedStatusIds, true) ? 'selected' : '' ?>><?= sanitize($statusOption['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="employee_type_id">Employee Type</label>
                        <select name="employee_type_id[]" id="employee_type_id" class="form-control resign-multi-select" multiple size="<?= max(2, min(4, count($typeOptions))) ?>">
                            <?php foreach ($typeOptions as $typeOption): ?>
                                <option value="<?= (int)$typeOption['id'] ?>" <?= in_array((int)$typeOption['id'], $selectedTypeIds, true) ? 'selected' : '' ?>><?= sanitize($typeOption['name_type']) ?></option>
                            <?php endforeach; ?>
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
                        <?php
                        $clearQuery = [
                            'year' => $selectedYear,
                            'month' => $selectedMonth,
                            'week' => $selectedWeek,
                            'sort' => $sort,
                            'dir' => $dir,
                        ];
                        if (!empty($selectedStatusIds)) {
                            $clearQuery['employment_status_id'] = $selectedStatusIds;
                        }
                        if (!empty($selectedTypeIds)) {
                            $clearQuery['employee_type_id'] = $selectedTypeIds;
                        }
                        ?>
                        <a class="btn" href="index.php?<?= sanitize(http_build_query($clearQuery)) ?>">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card" style="margin-bottom:8px;">
                <p style="margin:0;" class="muted">Showing resignations <strong><?= (int)$selectedWeek ?></strong><?= !empty($selectedStatusNames) ? ' for <strong>' . sanitize(implode(', ', $selectedStatusNames)) . '</strong> employees' : '' ?><?= $search !== '' ? ' matching <strong>' . sanitize($search) . '</strong>' : '' ?>.</p>
            </div>

            <div class="card" style="overflow:auto;">
                <table class="table resign-table" data-itm-no-import-excel="1">
                    <thead>
                        <tr>
                            <th><a href="<?= sanitize(resign_sort_url('external_id', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">ID TM<?= resign_sort_indicator('external_id', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(resign_sort_url('name', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">Name<?= resign_sort_indicator('name', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(resign_sort_url('employee_type', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">Team member / Internship<?= resign_sort_indicator('employee_type', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(resign_sort_url('department', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">Department<?= resign_sort_indicator('department', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(resign_sort_url('start_date', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">Admission date<?= resign_sort_indicator('start_date', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(resign_sort_url('termination_date', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">Last work day<?= resign_sort_indicator('termination_date', $sort, $dir) ?></a></th>
                            <th><a href="<?= sanitize(resign_sort_url('resignation_week', $sort, $dir, $selectedYear, $selectedMonth, $selectedWeek, $search, $selectedStatusIds, $selectedTypeIds)) ?>">Official Resignation Week<?= resign_sort_indicator('resignation_week', $sort, $dir) ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7">No resignations found for this week.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php $fullName = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']); ?>
                            <tr>
                                <td><?= sanitize((string)($row['external_id'] ?? '—')) ?></td>
                                <td><?= sanitize($fullName) ?></td>
                                <td><?= sanitize((string)($row['employee_type_name'] ?? '—')) ?></td>
                                <td><?= sanitize((string)($row['department_name'] ?? '—')) ?></td>
                                <td><?= sanitize(itm_format_date_display($row['start_date'] ?? '')) ?: '—' ?></td>
                                <td><?= sanitize(itm_format_date_display($row['termination_date'] ?? '')) ?: '—' ?></td>
                                <td><?= sanitize(resign_format_week_label($row['termination_date'] ?? null)) ?></td>
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
