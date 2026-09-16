<?php
/**
 * Compares db/ CREATE TABLE names with modules/ folders and $crud_table mappings.
 *
 * Why: db/ is the schema source of truth; modules/* should align for CRUD screens.
 *
 * Browser: open while logged in (read-only report on load).
 * CLI: php scripts/compare_database_sql_modules.php [--json]
 */


declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Log in first.</strong> Open <a href="compare_database_sql_modules.php" target="_blank" rel="nofollow noreferrer">compare_database_sql_modules.php</a> or <a href="compare_database_sql_modules.php?format=json">?format=json</a>.<br> CLI: <code>php scripts/compare_database_sql_modules.php</code> · JSON: <code>--json</code> · exit code <code>1</code> when gaps exist.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_database_tables_modules_report.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

if (!function_exists('itm_single_line_text')) {
    function itm_single_line_text(string $text): string
    {
        return itm_database_tables_modules_single_line_text($text);
    }
}

$sqlPath = itm_database_sql_schema_path();
$report = itm_compare_database_sql_modules_report($sqlPath);
$cliArgv = $argv ?? [];
$asJson = $itmIsCli
    ? in_array('--json', $cliArgv, true)
    : isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';
$cliShowAll = $itmIsCli && in_array('--all', $cliArgv, true);

require_once __DIR__ . '/lib/script_cli_output.php';

if ($itmIsCli) {
    itm_script_output_begin();

    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . $nl;
        exit(($report['summary']['tables_without_module'] + $report['summary']['modules_without_table']) > 0 ? 1 : 0);
    }

    echo "db/01_schema.sql tables vs modules/ comparison" . $nl;
    echo str_repeat('=', 120) . $nl;
    echo 'SQL file: ' . $report['sql_path'] . $nl;
    echo 'Tables In db/01_schema.sql: ' . (int)$report['table_count'] . $nl;
    echo 'Module folders scanned: ' . (int)$report['module_count'] . $nl . $nl;

    echo "Tables without a module: " . (int)$report['summary']['tables_without_module'] . $nl;
    echo "Modules without a db/ table: " . (int)$report['summary']['modules_without_table'] . $nl . $nl;

    echo "TABLES" . ($cliShowAll ? '' : ' (missing or mismatch only)') . $nl;
    echo str_repeat('-', 120) . $nl;
    echo "table | status | module_folder | crud_table | columns" . $nl;
    foreach ($report['tables'] as $row) {
        if (!$cliShowAll && ($row['status'] === 'matched' || $row['status'] === 'expected_internal')) {
            continue;
        }
        printf(
            "%-26s | %-18s | %-22s | %s | %s" . $nl,
            $row['table'],
            $row['status'],
            $row['module_folder'] !== '' ? $row['module_folder'] : '-',
            $row['crud_table'] !== '' ? $row['crud_table'] : '-',
            $row['columns_inline'] !== '' ? $row['columns_inline'] : '-'
        );
    }

    echo $nl . "MODULES" . ($cliShowAll ? '' : ' (issues only)') . $nl;
    echo str_repeat('-', 120) . $nl;
    echo "module | status | crud_table | in_sql | columns | notes" . $nl;
    foreach ($report['modules'] as $row) {
        if (!$cliShowAll && $row['status'] === 'matched') {
            continue;
        }
        printf(
            "%-26s | %-18s | %-22s | %s | %s | %s" . $nl,
            $row['module'],
            $row['status'],
            $row['crud_table'] !== '' ? $row['crud_table'] : '-',
            !empty($row['table_in_sql']) ? 'yes' : 'no',
            $row['columns_inline'] !== '' ? $row['columns_inline'] : '-',
            itm_single_line_text((string)$row['notes'])
        );
    }

    exit(($report['summary']['tables_without_module'] + $report['summary']['modules_without_table']) > 0 ? 1 : 0);
}

if (!isset($company_id) || (int)$company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
itm_enforce_maintenance_script_admin_browser($conn);

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

function itm_compare_status_badge_class(string $status): string
{
    if ($status === 'matched' || $status === 'expected_internal') {
        return 'badge-success';
    }
    if ($status === 'table_no_module' || $status === 'module_no_table') {
        return 'badge-danger';
    }
    return 'badge-warning';
}

require_once __DIR__ . '/lib/script_browser_nav.php';
$itmCompareBaseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>db/01_schema.sql vs modules</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .report-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 48px; }
        .report-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
        .report-table-scroll { overflow-x: auto; max-width: 100%; }
        .report-table { width: max-content; min-width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .report-table th, .report-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px 10px; text-align: left; vertical-align: middle; white-space: nowrap; }
        .report-table th { background: var(--table-header-bg, #f6f8fa); }
        .report-table .itm-cell-columns { white-space: nowrap; }
        .report-muted { color: var(--text-muted, #57606a); margin: 0 0 12px; line-height: 1.5; }
        .report-summary { display: flex; flex-wrap: wrap; gap: 12px 20px; margin: 0 0 12px; }
    </style>
</head>
<body>
<div class="report-wrap">
<?php itm_script_browser_nav_echo($itmCompareBaseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">db/01_schema.sql tables vs modules/</h1>
        <p class="report-muted">
            Compares every <code>CREATE TABLE</code> in <code>db/</code> split bundle with module folders under
            <code>modules/</code> and each module’s <code>$crud_table</code> in <code>index.php</code>.
        </p>
        <div class="report-summary">
            <span>Tables in SQL: <strong><?php echo (int)$report['table_count']; ?></strong></span>
            <span>Modules scanned: <strong><?php echo (int)$report['module_count']; ?></strong></span>
            <span>Tables without module: <strong><?php echo (int)$report['summary']['tables_without_module']; ?></strong></span>
            <span>Expected internal (no module): <strong><?php echo (int)($report['summary']['tables_expected_internal'] ?? 0); ?></strong></span>
            <span>Modules without SQL table: <strong><?php echo (int)$report['summary']['modules_without_table']; ?></strong></span>
        </div>
        <p>
            <a class="btn btn-sm" href="?format=json">JSON</a>
            <a class="btn btn-sm" href="../index.php">Home</a>
        </p>
    </div>

    <div class="report-card">
        <h2 style="margin-top:0;">Tables</h2>
        <div class="report-table-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Status</th>
                    <th>Module folder</th>
                    <th>$crud_table</th>
                    <th>Columns</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['tables'] as $row): ?>
                    <tr>
                        <td><?php echo itm_script_format_table_link((string)$row['table']); ?></td>
                        <td><span class="badge <?php echo $esc(itm_compare_status_badge_class((string)$row['status'])); ?>"><?php echo $esc(itm_single_line_text((string)$row['status'])); ?></span></td>
                        <td><?php echo $row['module_folder'] !== '' ? itm_script_format_module_link((string)$row['module_folder'], $itmCompareBaseUrl) : '—'; ?></td>
                        <td><?php echo $row['crud_table'] !== '' ? itm_script_format_table_link((string)$row['crud_table']) : '—'; ?></td>
                        <td class="itm-cell-columns"><?php echo $row['columns_inline'] !== '' ? $esc(itm_single_line_text((string)$row['columns_inline'])) : '—'; ?></td>
                        <td><?php echo $esc(itm_single_line_text((string)$row['notes'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="report-card">
        <h2 style="margin-top:0;">Modules</h2>
        <div class="report-table-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Status</th>
                    <th>$crud_table</th>
                    <th>In db/01_schema.sql</th>
                    <th>Columns</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['modules'] as $row): ?>
                    <tr>
                        <td><?php echo itm_script_format_module_link((string)$row['module'], $itmCompareBaseUrl); ?></td>
                        <td><span class="badge <?php echo $esc(itm_compare_status_badge_class((string)$row['status'])); ?>"><?php echo $esc(itm_single_line_text((string)$row['status'])); ?></span></td>
                        <td><?php echo $row['crud_table'] !== '' ? itm_script_format_table_link((string)$row['crud_table']) : '—'; ?></td>
                        <td><?php echo !empty($row['table_in_sql']) ? 'Yes' : 'No'; ?></td>
                        <td class="itm-cell-columns"><?php echo $row['columns_inline'] !== '' ? $esc(itm_single_line_text((string)$row['columns_inline'])) : '—'; ?></td>
                        <td><?php echo $esc(itm_single_line_text((string)$row['notes'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
