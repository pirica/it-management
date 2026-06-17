<?php
/**
 * Debug resignations weekly filter for a specific termination date.
 *
 * Default probe: 18/06/2026 (ISO week 25). Explains which SQL predicates pass or fail
 * and whether the live employee row would appear in modules/resignations/index.php.
 *
 * Browser: scripts/debug_resignations_termination_date.php?date=18/06/2026&company_id=4&employee_id=432
 * CLI: php scripts/debug_resignations_termination_date.php --date=18/06/2026 --company_id=4 --employee_id=432
 */
declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Resignations Termination Date Debug');

$nl = itm_script_output_nl();

function drd_info(string $message): void
{
    global $nl;
    echo colorText('[INFO] ' . $message, 'info') . $nl;
}

function drd_pass(string $message): void
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function drd_fail(string $message): void
{
    global $nl;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function drd_warn(string $message): void
{
    global $nl;
    echo colorText('[WARN] ' . $message, 'warn') . $nl;
}

/**
 * @return array<string, string>
 */
function drd_parse_args(): array
{
    $args = [
        'date' => '18/06/2026',
        'company_id' => '4',
        'employee_id' => '432',
        'week' => '25',
        'month' => '6',
        'year' => '2026',
    ];

    if (php_sapi_name() === 'cli') {
        global $argv;
        foreach (array_slice($argv ?? [], 1) as $arg) {
            if (strpos($arg, '--') !== 0) {
                continue;
            }
            $pair = explode('=', substr($arg, 2), 2);
            $key = $pair[0] ?? '';
            $value = $pair[1] ?? '';
            if ($key !== '' && array_key_exists($key, $args)) {
                $args[$key] = $value;
            }
        }
        return $args;
    }

    foreach (array_keys($args) as $key) {
        if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
            $args[$key] = trim((string)$_GET[$key]);
        }
    }

    return $args;
}

/**
 * @return list<int>
 */
function drd_default_status_ids(mysqli $conn, int $companyId): array
{
    $ids = [];
    $stmt = mysqli_prepare($conn, "SELECT id, name FROM employee_statuses WHERE company_id = ? AND active = 1 ORDER BY name");
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = (string)($row['name'] ?? '');
        if (in_array($name, ['Active', 'Inactive', 'On Leave', 'Terminated'], true)) {
            $ids[] = (int)$row['id'];
        }
    }
    mysqli_stmt_close($stmt);
    return $ids;
}

/**
 * @return list<int>
 */
function drd_default_type_ids(mysqli $conn, int $companyId): array
{
    $ids = [];
    $stmt = mysqli_prepare($conn, "SELECT id, name_type FROM employee_type WHERE company_id = ? AND active = 1 ORDER BY name_type");
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = (string)($row['name_type'] ?? '');
        if (in_array($name, ['Team member', 'Internship'], true)) {
            $ids[] = (int)$row['id'];
        }
    }
    mysqli_stmt_close($stmt);
    return $ids;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    drd_fail('No database connection.');
    exit(1);
}

$params = drd_parse_args();
$rawDate = (string)$params['date'];
$canonicalDate = itm_parse_date_input($rawDate);
if ($canonicalDate === null) {
    drd_fail('Could not parse termination date: ' . $rawDate . ' (expected dd/mm/yyyy or yyyy-mm-dd).');
    exit(1);
}

$companyId = max(1, (int)$params['company_id']);
$employeeId = (int)$params['employee_id'];
$filterWeek = max(1, min(53, (int)$params['week']));
$filterMonth = max(1, min(12, (int)$params['month']));
$filterYear = (int)$params['year'];
if ($filterYear < 1970 || $filterYear > 2100) {
    $filterYear = (int)date('Y', strtotime($canonicalDate));
}

$ts = strtotime($canonicalDate);
$phpWeek = (int)date('W', $ts);
$phpIsoYear = (int)date('o', $ts);
$phpCalendarYear = (int)date('Y', $ts);
$phpMonth = (int)date('n', $ts);

drd_info('Input termination date (raw): ' . $rawDate);
drd_info('Canonical SQL date: ' . $canonicalDate);
drd_info('PHP calendar year (Y): ' . $phpCalendarYear . ', ISO year (o): ' . $phpIsoYear . ', month (n): ' . $phpMonth . ', ISO week (W): ' . $phpWeek);
drd_info('Report filter selectors: year=' . $filterYear . ', month=' . $filterMonth . ', week=' . $filterWeek);

$boundsCalendarYear = itm_iso_week_bounds($filterYear, $filterWeek);
$boundsIsoYear = itm_iso_week_bounds($phpIsoYear, $filterWeek);
if ($boundsCalendarYear !== null) {
    drd_info('ISO bounds using filter year ' . $filterYear . ' week ' . $filterWeek . ': ' . $boundsCalendarYear['start'] . ' .. ' . $boundsCalendarYear['end']);
} else {
    drd_fail('Could not resolve ISO bounds for filter year/week.');
}
if ($boundsIsoYear !== null && $phpIsoYear !== $filterYear) {
    drd_warn('ISO year for this date (' . $phpIsoYear . ') differs from filter year (' . $filterYear . '). Alternate bounds: ' . $boundsIsoYear['start'] . ' .. ' . $boundsIsoYear['end']);
}

$inRangeCalendar = $boundsCalendarYear !== null
    && $canonicalDate >= $boundsCalendarYear['start']
    && $canonicalDate <= $boundsCalendarYear['end'];
$inRangeIso = $boundsIsoYear !== null
    && $canonicalDate >= $boundsIsoYear['start']
    && $canonicalDate <= $boundsIsoYear['end'];
$monthMatches = ($phpMonth === $filterMonth);

if ($inRangeCalendar) {
    drd_pass('Date falls inside ISO week bounds for filter year/week.');
} else {
    drd_fail('Date is outside ISO week bounds for filter year=' . $filterYear . ' week=' . $filterWeek . '.');
}

if ($monthMatches) {
    drd_pass('MONTH(termination_date) matches filter month ' . $filterMonth . '.');
} else {
    drd_fail('MONTH(termination_date) is ' . $phpMonth . ' but filter month is ' . $filterMonth . ' (cross-month ISO weeks hide rows).');
}

$escapedDate = mysqli_real_escape_string($conn, $canonicalDate);
$mysqlMetaRes = mysqli_query(
    $conn,
    "SELECT YEAR('{$escapedDate}') AS y, MONTH('{$escapedDate}') AS m, WEEK('{$escapedDate}', 3) AS w"
);
$mysqlMeta = ($mysqlMetaRes) ? mysqli_fetch_assoc($mysqlMetaRes) : null;
if ($mysqlMeta) {
    drd_info('MySQL YEAR=' . (int)$mysqlMeta['y'] . ', MONTH=' . (int)$mysqlMeta['m'] . ', WEEK(...,3)=' . (int)$mysqlMeta['w']);
    if ((int)$mysqlMeta['w'] !== $phpWeek) {
        drd_warn('PHP date(W)=' . $phpWeek . ' differs from MySQL WEEK(...,3)=' . (int)$mysqlMeta['w'] . ' (legacy filter would be unreliable).');
    }
}

$legacySql = "SELECT 1 AS ok FROM employees e
    WHERE e.termination_date = '{$escapedDate}'
      AND YEAR(e.termination_date) = {$filterYear}
      AND MONTH(e.termination_date) = {$filterMonth}
      AND WEEK(e.termination_date, 3) = {$filterWeek}
    LIMIT 1";
$legacyRes = mysqli_query($conn, $legacySql);
if ($legacyRes && mysqli_num_rows($legacyRes) === 1) {
    drd_pass('Legacy YEAR + MONTH + WEEK(...,3) predicate matches this date.');
} else {
    drd_fail('Legacy YEAR + MONTH + WEEK(...,3) predicate does NOT match this date.');
}

if ($boundsCalendarYear !== null) {
    $rangeSql = "SELECT 1 AS ok FROM employees e
        WHERE e.termination_date = '{$escapedDate}'
          AND e.termination_date >= '" . mysqli_real_escape_string($conn, $boundsCalendarYear['start']) . "'
          AND e.termination_date <= '" . mysqli_real_escape_string($conn, $boundsCalendarYear['end']) . "'
          AND MONTH(e.termination_date) = {$filterMonth}
        LIMIT 1";
    $rangeRes = mysqli_query($conn, $rangeSql);
    if ($rangeRes && mysqli_num_rows($rangeRes) === 1) {
        drd_pass('Current range + MONTH predicate matches this date.');
    } else {
        drd_fail('Current range + MONTH predicate does NOT match this date.');
    }
}

if ($employeeId > 0) {
    drd_info('--- Employee row (id=' . $employeeId . ') ---');
    $empSql = "SELECT e.id, e.company_id, e.external_id, e.first_name, e.last_name, e.termination_date,
            e.employment_status_id, e.employee_type_id, es.name AS status_name, et.name_type AS type_name
        FROM employees e
        LEFT JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
        LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id
        WHERE e.id = {$employeeId}
        LIMIT 1";
    $empRes = mysqli_query($conn, $empSql);
    $emp = ($empRes) ? mysqli_fetch_assoc($empRes) : null;
    if (!$emp) {
        drd_fail('Employee id ' . $employeeId . ' not found.');
    } else {
        drd_info('company_id=' . (int)$emp['company_id'] . ', external_id=' . (string)($emp['external_id'] ?? '') . ', name=' . trim((string)$emp['first_name'] . ' ' . (string)$emp['last_name']));
        drd_info('termination_date=' . (string)($emp['termination_date'] ?? '') . ' (' . itm_format_date_display($emp['termination_date'] ?? '') . ')');
        drd_info('employment_status_id=' . (int)$emp['employment_status_id'] . ' (' . (string)($emp['status_name'] ?? '—') . '), employee_type_id=' . (int)($emp['employee_type_id'] ?? 0) . ' (' . (string)($emp['type_name'] ?? '—') . ')');
        if ((int)$emp['company_id'] !== $companyId) {
            drd_warn('Employee company_id=' . (int)$emp['company_id'] . ' but debug company_id=' . $companyId . ' — switch company in the app to match.');
        }
        if ((string)($emp['termination_date'] ?? '') !== $canonicalDate) {
            drd_warn('Employee termination_date in DB differs from probe date ' . $canonicalDate . '.');
        }
    }
}

drd_info('--- Resignations module filter (company_id=' . $companyId . ') ---');
$statusIds = drd_default_status_ids($conn, $companyId);
$typeIds = drd_default_type_ids($conn, $companyId);
drd_info('Default status ids: ' . (count($statusIds) ? implode(',', $statusIds) : '(none)'));
drd_info('Default type ids: ' . (count($typeIds) ? implode(',', $typeIds) : '(none)'));

if ($boundsCalendarYear === null || $statusIds === [] || $typeIds === []) {
    drd_fail('Cannot run module filter simulation (missing bounds or reference rows).');
    exit(1);
}

$statusPlaceholders = implode(',', array_fill(0, count($statusIds), '?'));
$typePlaceholders = implode(',', array_fill(0, count($typeIds), '?'));
$moduleSql = 'SELECT e.id, e.external_id, e.first_name, e.last_name, e.termination_date, et.name_type AS employee_type_name, es.name AS status_name
    FROM employees e
    INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
    LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id
    WHERE e.company_id = ?
      AND e.termination_date IS NOT NULL
      AND e.termination_date <> \'0000-00-00\'
      AND e.termination_date >= ?
      AND e.termination_date <= ?
      AND MONTH(e.termination_date) = ?
      AND es.id IN (' . $statusPlaceholders . ')
      AND (e.employee_type_id IS NULL OR e.employee_type_id IN (' . $typePlaceholders . '))';
if ($employeeId > 0) {
    $moduleSql .= ' AND e.id = ' . $employeeId;
}
$moduleSql .= ' ORDER BY e.termination_date ASC';

$stmt = mysqli_prepare($conn, $moduleSql);
if (!$stmt) {
    drd_fail('Could not prepare module filter SQL: ' . mysqli_error($conn));
    exit(1);
}

$types = 'issi' . str_repeat('i', count($statusIds)) . str_repeat('i', count($typeIds));
$bindParams = array_merge(
    [$companyId, $boundsCalendarYear['start'], $boundsCalendarYear['end'], $filterMonth],
    $statusIds,
    $typeIds
);
mysqli_stmt_bind_param($stmt, $types, ...$bindParams);
mysqli_stmt_execute($stmt);
$moduleRes = mysqli_stmt_get_result($stmt);
$moduleRows = [];
while ($moduleRes && ($row = mysqli_fetch_assoc($moduleRes))) {
    $moduleRows[] = $row;
}
mysqli_stmt_close($stmt);

if ($employeeId > 0) {
    if (count($moduleRows) === 1) {
        drd_pass('Module filter returns employee id ' . $employeeId . ' for week=' . $filterWeek . ' month=' . $filterMonth . ' year=' . $filterYear . '.');
    } else {
        drd_fail('Module filter does NOT return employee id ' . $employeeId . ' with default status/type filters.');
        if (!empty($emp)) {
            $statusOk = in_array((int)$emp['employment_status_id'], $statusIds, true);
            $typeOk = ((int)($emp['employee_type_id'] ?? 0) === 0) || in_array((int)$emp['employee_type_id'], $typeIds, true);
            if (!$statusOk) {
                drd_fail('employment_status_id ' . (int)$emp['employment_status_id'] . ' (' . (string)($emp['status_name'] ?? '') . ') is not in default status filter.');
            }
            if (!$typeOk) {
                drd_fail('employee_type_id ' . (int)$emp['employee_type_id'] . ' (' . (string)($emp['type_name'] ?? '') . ') is not in default type filter.');
            }
        }
    }
} else {
  drd_info('Module filter matched ' . count($moduleRows) . ' row(s) for company ' . $companyId . '.');
}

drd_info('--- Today probe (verify_employee_type_resignations.php) ---');
$today = date('Y-m-d');
$todayWeek = (int)date('W');
$todayMonth = (int)date('n');
$todayYear = (int)date('Y');
$todayIsoYear = (int)date('o');
$todayBoundsCal = itm_iso_week_bounds($todayYear, $todayWeek);
$todayBoundsIso = itm_iso_week_bounds($todayIsoYear, $todayWeek);
drd_info('Today ' . $today . ': calendar Y=' . $todayYear . ', ISO o=' . $todayIsoYear . ', month=' . $todayMonth . ', week=' . $todayWeek);
if ($todayBoundsCal !== null) {
    drd_info('Probe bounds (calendar year): ' . $todayBoundsCal['start'] . ' .. ' . $todayBoundsCal['end']);
    $todayInCal = ($today >= $todayBoundsCal['start'] && $today <= $todayBoundsCal['end'] && $todayMonth === (int)date('n', strtotime($today)));
    if ($todayInCal) {
        drd_pass('Today fits verify script bounds using calendar year.');
    } else {
        drd_fail('Today does NOT fit verify script bounds using calendar year (explains weekly probe FAIL).');
    }
}
if ($todayBoundsIso !== null && $todayIsoYear !== $todayYear) {
    drd_warn('Today ISO year differs from calendar year — use ISO year ' . $todayIsoYear . ' for week bounds: ' . $todayBoundsIso['start'] . ' .. ' . $todayBoundsIso['end']);
}

drd_info('Done. Open resignations: modules/resignations/index.php?week=' . $filterWeek . '&month=' . $filterMonth . '&year=' . $filterYear);

exit(0);
