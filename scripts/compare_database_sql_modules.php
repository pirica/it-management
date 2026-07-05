<?php
/**
 * Compares database.sql CREATE TABLE names with modules/ folders and $crud_table mappings.
 *
 * Why: database.sql is the schema source of truth; modules/* should align for CRUD screens.
 *
 * Browser: open while logged in (read-only report on load).
 * CLI: php scripts/compare_database_sql_modules.php [--json]
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/database_sql_unique_audit.php';

function itm_single_line_text(string $text): string
{
    return preg_replace('/\s+/', ' ', trim($text)) ?? '';
}

/**
 * @return array<string, array<int, string>>
 */
function itm_parse_database_sql_table_columns(string $sqlPath): array
{
    if (!is_readable($sqlPath)) {
        return [];
    }

    $sql = (string)file_get_contents($sqlPath);
    if (!preg_match_all(
        '/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is',
        $sql,
        $matches,
        PREG_SET_ORDER
    )) {
        return [];
    }

    $map = [];
    foreach ($matches as $match) {
        $tableName = (string)$match[1];
        if ($tableName === '' || !itm_is_safe_identifier($tableName)) {
            continue;
        }
        $map[$tableName] = itm_database_sql_unique_audit_parse_column_defs((string)$match[2]);
    }

    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    return $map;
}

/**
 * @param array<int, string> $columns
 */
function itm_columns_inline(array $columns): string
{
    return implode(', ', $columns);
}

/**
 * @return array<int, string>
 */
function itm_parse_database_sql_table_names(string $sqlPath): array
{
    return array_keys(itm_parse_database_sql_table_columns($sqlPath));
}

/**
 * Detects the database table a module targets from index.php and sibling entry files.
 */
function itm_detect_module_crud_table(string $moduleName, string $moduleDir): ?string
{
    $candidates = [];
    $indexPath = $moduleDir . '/index.php';
    if (is_file($indexPath)) {
        $candidates[] = $indexPath;
    }
    foreach (['create.php', 'list_all.php', 'view.php', 'edit.php'] as $entryFile) {
        $entryPath = $moduleDir . '/' . $entryFile;
        if (is_file($entryPath)) {
            $candidates[] = $entryPath;
        }
    }

    foreach ($candidates as $filePath) {
        $content = @file_get_contents($filePath);
        if (!is_string($content) || $content === '') {
            continue;
        }
        if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $m)) {
            return (string)$m[1];
        }
        if (preg_match('/\$crud_table\s*=\s*\$crud_table\s*\?\?\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $m)) {
            return (string)$m[1];
        }
        if (preg_match('/itm_handle_json_table_import\s*\(\s*\$conn\s*,\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $m)) {
            return (string)$m[1];
        }
    }

    if (is_file($indexPath)) {
        return $moduleName;
    }

    return null;
}

/**
 * @return array<string, array{module:string,crud_table:?string,has_index:bool}>
 */
function itm_scan_module_crud_map(): array
{
    $modulesRoot = ROOT_PATH . 'modules/';
    $map = [];
    $entries = scandir($modulesRoot) ?: [];

    foreach ($entries as $moduleName) {
        if ($moduleName === '.' || $moduleName === '..') {
            continue;
        }
        $moduleDir = $modulesRoot . $moduleName;
        if (!is_dir($moduleDir)) {
            continue;
        }

        $hasIndex = is_file($moduleDir . '/index.php');
        $crudTable = itm_detect_module_crud_table($moduleName, $moduleDir);

        $map[$moduleName] = [
            'module' => $moduleName,
            'crud_table' => $crudTable,
            'has_index' => $hasIndex,
        ];
    }

    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    return $map;
}

/**
 * @return array{
 *   sql_path:string,
 *   table_count:int,
 *   module_count:int,
 *   summary:array{matched:int,table_no_module:int,module_no_table:int,mismatch:int,no_index:int},
 *   tables:array<int,array<string,mixed>>,
 *   modules:array<int,array<string,mixed>>
 * }
 */
function itm_compare_database_sql_modules_report(string $sqlPath): array
{
    $tableColumnsMap = itm_parse_database_sql_table_columns($sqlPath);
    $tableNames = array_keys($tableColumnsMap);
    $moduleMap = itm_scan_module_crud_map();
    $tableSet = array_fill_keys($tableNames, true);

    $modulesByCrudTable = [];
    foreach ($moduleMap as $moduleName => $meta) {
        $crud = (string)($meta['crud_table'] ?? '');
        if ($crud !== '') {
            if (!isset($modulesByCrudTable[$crud])) {
                $modulesByCrudTable[$crud] = [];
            }
            $modulesByCrudTable[$crud][] = $moduleName;
        }
    }

    $tableRows = [];
    $summary = [
        'matched' => 0,
        'table_no_module' => 0,
        'expected_internal' => 0,
        'mismatch' => 0,
    ];
    $policyHiddenTables = function_exists('itm_sidebar_excluded_module_ids')
        ? array_fill_keys(itm_sidebar_excluded_module_ids(), true)
        : [];

    foreach ($tableNames as $tableName) {
        $sqlColumns = $tableColumnsMap[$tableName] ?? [];
        $columnsInline = itm_columns_inline($sqlColumns);

        if (isset($policyHiddenTables[$tableName])) {
            $tableRows[] = [
                'table' => $tableName,
                'status' => 'expected_internal',
                'module_folder' => '',
                'crud_table' => $tableName,
                'columns' => $sqlColumns,
                'columns_inline' => $columnsInline,
                'notes' => itm_single_line_text('Internal support table (managed inside Floor Plans gallery; no modules/ folder expected).'),
            ];
            $summary['expected_internal']++;
            continue;
        }
        $moduleDir = $moduleMap[$tableName] ?? null;
        $moduleByName = $moduleDir !== null && !empty($moduleDir['has_index']);
        $crudOnNameModule = $moduleDir !== null ? (string)($moduleDir['crud_table'] ?? '') : '';
        $linkedModules = $modulesByCrudTable[$tableName] ?? [];

        $status = 'table_no_module';
        $moduleFolder = '';
        $crudTable = '';
        $notes = itm_single_line_text('No modules/' . $tableName . '/index.php and no module maps $crud_table here.');

        if ($moduleByName && $crudOnNameModule === $tableName) {
            $status = 'matched';
            $moduleFolder = $tableName;
            $crudTable = $tableName;
            $notes = itm_single_line_text('modules/' . $tableName . '/ maps this table.');
            $summary['matched']++;
        } elseif ($moduleByName && $crudOnNameModule !== '' && $crudOnNameModule !== $tableName) {
            $status = 'mismatch';
            $moduleFolder = $tableName;
            $crudTable = $crudOnNameModule;
            $notes = itm_single_line_text('Folder name matches table but module maps a different table.');
            $summary['mismatch']++;
        } elseif ($moduleByName) {
            $status = 'matched';
            $moduleFolder = $tableName;
            $crudTable = $crudOnNameModule !== '' ? $crudOnNameModule : $tableName;
            $notes = itm_single_line_text('modules/' . $tableName . '/index.php present (inferred mapping).');
            $summary['matched']++;
        } elseif (!empty($linkedModules)) {
            $status = 'matched';
            $moduleFolder = implode(', ', $linkedModules);
            $crudTable = $tableName;
            $notes = itm_single_line_text('Mapped via $crud_table from: ' . $moduleFolder . '.');
            $summary['matched']++;
        } else {
            $summary['table_no_module']++;
        }

        $tableRows[] = [
            'table' => $tableName,
            'status' => $status,
            'module_folder' => $moduleFolder,
            'crud_table' => $crudTable,
            'columns' => $sqlColumns,
            'columns_inline' => $columnsInline,
            'notes' => itm_single_line_text($notes),
        ];
    }

    $moduleRows = [];
    $moduleSummary = [
        'matched' => 0,
        'module_no_table' => 0,
        'mismatch' => 0,
        'no_index' => 0,
    ];

    foreach ($moduleMap as $moduleName => $meta) {
        $hasIndex = !empty($meta['has_index']);
        $crudTable = (string)($meta['crud_table'] ?? '');
        $status = 'matched';
        $notes = '';

        if (!$hasIndex) {
            $status = 'no_index';
            $notes = itm_single_line_text('Module folder exists without index.php.');
            $moduleSummary['no_index']++;
        } elseif ($crudTable === '') {
            $status = 'mismatch';
            $notes = itm_single_line_text('index.php has no $crud_table assignment.');
            $moduleSummary['mismatch']++;
        } elseif (!isset($tableSet[$crudTable])) {
            $status = 'module_no_table';
            $notes = itm_single_line_text('$crud_table not found in database.sql CREATE TABLE list.');
            $moduleSummary['module_no_table']++;
        } elseif ($crudTable !== $moduleName && !isset($tableSet[$moduleName])) {
            $status = 'matched';
            $notes = itm_single_line_text('Module name differs from table; $crud_table exists in database.sql.');
            $moduleSummary['matched']++;
        } elseif ($crudTable !== $moduleName && isset($tableSet[$moduleName])) {
            $status = 'matched';
            $notes = itm_single_line_text('Module folder matches a table name; $crud_table maps another existing table.');
            $moduleSummary['matched']++;
        } else {
            $moduleSummary['matched']++;
            $notes = itm_single_line_text('Module folder and $crud_table align with database.sql.');
        }

        $mappedColumns = ($crudTable !== '' && isset($tableColumnsMap[$crudTable])) ? $tableColumnsMap[$crudTable] : [];
        $moduleRows[] = [
            'module' => $moduleName,
            'status' => $status,
            'crud_table' => $crudTable,
            'table_in_sql' => $crudTable !== '' && isset($tableSet[$crudTable]),
            'columns' => $mappedColumns,
            'columns_inline' => itm_columns_inline($mappedColumns),
            'notes' => itm_single_line_text($notes),
        ];
    }

    return [
        'sql_path' => $sqlPath,
        'table_count' => count($tableNames),
        'module_count' => count($moduleMap),
        'summary' => [
            'tables_matched' => $summary['matched'],
            'tables_without_module' => $summary['table_no_module'],
            'tables_expected_internal' => $summary['expected_internal'],
            'tables_mismatch' => $summary['mismatch'],
            'modules_matched' => $moduleSummary['matched'],
            'modules_without_table' => $moduleSummary['module_no_table'],
            'modules_mismatch' => $moduleSummary['mismatch'],
            'modules_no_index' => $moduleSummary['no_index'],
        ],
        'tables' => $tableRows,
        'modules' => $moduleRows,
    ];
}

$sqlPath = ROOT_PATH . 'database.sql';
$report = itm_compare_database_sql_modules_report($sqlPath);
$cliArgv = $argv ?? [];
$asJson = $itmIsCli
    ? in_array('--json', $cliArgv, true)
    : isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';
$cliShowAll = $itmIsCli && in_array('--all', $cliArgv, true);

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


if ($itmIsCli) {
    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . $nl;
        exit(($report['summary']['tables_without_module'] + $report['summary']['modules_without_table']) > 0 ? 1 : 0);
    }

    echo "database.sql tables vs modules/ comparison" . $nl;
    echo str_repeat('=', 120) . $nl;
    echo 'SQL file: ' . $report['sql_path'] . $nl;
    echo 'Tables in database.sql: ' . (int)$report['table_count'] . $nl;
    echo 'Module folders scanned: ' . (int)$report['module_count'] . $nl . $nl;

    echo "Tables without a module: " . (int)$report['summary']['tables_without_module'] . $nl;
    echo "Modules without a database.sql table: " . (int)$report['summary']['modules_without_table'] . $nl . $nl;

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
    <title>database.sql vs modules</title>
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
        <h1 style="margin-top:0;">database.sql tables vs modules/</h1>
        <p class="report-muted">
            Compares every <code>CREATE TABLE</code> in <code>database.sql</code> with module folders under
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
                    <th>In database.sql</th>
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
