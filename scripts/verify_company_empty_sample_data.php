<?php
/**
 * Verifies Add sample data seeding for empty tenant tables (CLI).
 *
 * Usage:
 *   php scripts/verify_company_empty_sample_data.php --company=4
 *   php scripts/verify_company_empty_sample_data.php --company=4 --module=monthly_budgets
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
define('ITM_LIST_EMPTY_TABLES_LIB_ONLY', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/list_empty_tables.php';

itm_script_output_begin('Company empty-table sample data verification');

$nl = itm_script_output_nl();

if (!$conn instanceof mysqli) {
    fwrite(STDERR, '[FAIL] Database connection is required.' . $nl);
    exit(1);
}

/**
 * Modules with an Add sample data button (table slug === module folder).
 *
 * @return array<string, true>
 */
function vcesd_sample_data_button_modules(): array
{
    $slugs = [
        'monthly_budgets',
        'patches_updates',
        'visitors_access_log',
        'todo_categories',
        'switch_ports',
        'ops_report_butler',
        'ops_report_courtesy_call',
        'ops_report_guest_experience',
        'ops_report_hotel_figure',
        'ops_report_night_shift',
        'note_labels',
        'idf_positions',
        'idf_ports',
        'idf_links',
        'floor_plans',
        'floor_designer_points',
    ];

    $map = [];
    foreach ($slugs as $slug) {
        $map[$slug] = true;
    }

    return $map;
}

/**
 * @return array<int, string>
 */
function vcesd_parse_argv(array $argv): array
{
    $companyId = 0;
    $moduleFilter = '';

    foreach ($argv as $arg) {
        if (preg_match('/^--company=(\d+)$/', (string)$arg, $match)) {
            $companyId = (int)$match[1];
        } elseif (preg_match('/^--module=(.+)$/', (string)$arg, $match)) {
            $moduleFilter = trim((string)$match[1]);
        }
    }

    return [$companyId, $moduleFilter];
}

/**
 * Resolve a tenant admin employee id for vault / employee-scoped seeds.
 */
function vcesd_resolve_admin_employee_id(mysqli $conn, int $companyId): int
{
    if ($companyId <= 0) {
        return 0;
    }

    $sql = "SELECT e.id FROM employees e
        INNER JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id
        WHERE e.company_id = ? AND LOWER(er.name) = 'admin' AND e.active = 1
        ORDER BY e.id ASC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            return (int)$row['id'];
        }
    }

    // Why: Some tenants (e.g. company 4 Admin4) may lack role_id; seed username matches import bundle.
    $fallbackUsername = 'Admin' . ($companyId === 1 ? '' : (string)$companyId);
    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE company_id = ? AND username = ? AND active = 1 ORDER BY id ASC LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $fallbackUsername);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            return (int)$row['id'];
        }
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE company_id = ? AND active = 1 ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return is_array($row) ? (int)($row['id'] ?? 0) : 0;
}

[$companyId, $moduleFilter] = vcesd_parse_argv($GLOBALS['argv'] ?? []);

if ($companyId <= 0) {
    fwrite(STDERR, 'Company id is required: --company=N' . $nl);
    exit(1);
}

$buttonModules = vcesd_sample_data_button_modules();
$modulesRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules';
$report = itm_list_empty_tables_collect_report($conn, $companyId, $modulesRoot);

$candidates = [];
foreach ($report['empty_tables'] as $row) {
    $table = (string)($row['table'] ?? '');
    if ($table === '' || !isset($buttonModules[$table])) {
        continue;
    }
    if ($moduleFilter !== '' && $table !== $moduleFilter) {
        continue;
    }
    if (!(bool)($row['has_module'] ?? false)) {
        continue;
    }
    $candidates[] = $table;
}

sort($candidates, SORT_NATURAL | SORT_FLAG_CASE);

if ($candidates === []) {
    if ($moduleFilter !== '') {
        echo '[PASS] Module ' . $moduleFilter . ' is not empty for company ' . $companyId . ' (or not in scope).' . $nl;
        itm_script_output_end();
        exit(0);
    }
    echo '[PASS] No in-scope empty tables with Add sample data for company ' . $companyId . '.' . $nl;
    itm_script_output_end();
    exit(0);
}

$employeeId = vcesd_resolve_admin_employee_id($conn, $companyId);
if ($employeeId <= 0) {
    fwrite(STDERR, '[FAIL] Could not resolve Admin employee for company ' . $companyId . '.' . $nl);
    exit(1);
}

$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = $employeeId;

echo 'Company id: ' . $companyId . $nl;
echo 'Admin employee id: ' . $employeeId . $nl;
echo 'Empty in-scope modules: ' . count($candidates) . $nl . $nl;

$failures = 0;

foreach ($candidates as $table) {
    $before = itm_list_empty_tables_tenant_live_row_count($conn, $table, $companyId);
    if ($before < 0) {
        echo '[FAIL] ' . $table . ' — could not count rows.' . $nl;
        $failures++;
        continue;
    }
    if ($before > 0) {
        echo '[PASS] ' . $table . ' — already has ' . $before . ' row(s).' . $nl;
        continue;
    }

    if (!function_exists('itm_seed_lookup_parents_for_table') || !function_exists('itm_seed_table_from_database_sql')) {
        echo '[FAIL] ' . $table . ' — sample seed helpers unavailable.' . $nl;
        $failures++;
        continue;
    }

    itm_seed_lookup_parents_for_table($conn, $table, $companyId);
    $seedErr = '';
    $inserted = itm_seed_table_from_database_sql($conn, $table, $companyId, $seedErr);
    $after = itm_list_empty_tables_tenant_live_row_count($conn, $table, $companyId);

    if ($after > $before) {
        $note = $inserted > 0 ? ('inserted ' . $inserted) : ('rows now ' . $after);
        echo '[PASS] ' . $table . ' — ' . $note . '.' . $nl;
        continue;
    }

    $failures++;
    $detail = $seedErr !== '' ? $seedErr : 'No rows inserted (before=' . $before . ', after=' . $after . ', inserted=' . $inserted . ')';
    echo '[FAIL] ' . $table . ' — ' . $detail . '.' . $nl;
}

echo $nl;
if ($failures > 0) {
    echo 'Result: FAIL (' . $failures . ' module(s))' . $nl;
    itm_script_output_end();
    exit(1);
}

echo 'Result: PASS (all in-scope empty modules seeded)' . $nl;
itm_script_output_end();
exit(0);
