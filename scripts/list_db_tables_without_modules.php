<?php
/**
 * Lists live database tables that have no modules/ folder and no $crud_table mapping.
 *
 * Why: compare_database_sql_modules.php is a full bidirectional audit; this script is a
 * focused inventory for schema tables missing module entry points.
 *
 * Browser: Admin session (read-only report on load).
 * CLI: php scripts/list_db_tables_without_modules.php [--json]
 */


declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required.</strong> Open <a href="list_db_tables_without_modules.php" target="_blank" rel="nofollow noreferrer">list_db_tables_without_modules.php</a> or <a href="list_db_tables_without_modules.php?format=json">?format=json</a>.<br> CLI: <code>php scripts/list_db_tables_without_modules.php</code> · JSON: <code>--json</code> · exit <code>1</code> when any table lacks a module
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_database_tables_modules_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$report = itm_list_db_tables_without_modules_report($conn);
$cliArgv = $argv ?? [];
$asJson = $itmIsCli
    ? in_array('--json', $cliArgv, true)
    : isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json';

if ($itmIsCli) {
    itm_script_output_begin('DB tables without modules');

    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $nl;
        exit(((int) ($report['summary']['tables_without_module'] ?? 0)) > 0 ? 1 : 0);
    }

    echo 'Live database tables without modules/' . $nl;
    echo str_repeat('=', 96) . $nl;
    echo 'Schema: ' . (string) ($report['schema'] ?? '') . $nl;
    echo 'Tables in database: ' . (int) ($report['table_count'] ?? 0) . $nl;
    echo 'Module folders scanned: ' . (int) ($report['module_count'] ?? 0) . $nl;
    echo 'Tables without module: ' . (int) ($report['summary']['tables_without_module'] ?? 0) . $nl;
    echo 'Expected internal (no module): ' . (int) ($report['summary']['tables_expected_internal'] ?? 0) . $nl;
    echo 'Tables matched to a module: ' . (int) ($report['summary']['tables_matched'] ?? 0) . $nl . $nl;

    if (($report['tables_without_module'] ?? []) === []) {
        echo '[OK] Every live table is covered by a module folder or $crud_table mapping.' . $nl;
        exit(0);
    }

    echo 'table | columns' . $nl;
    echo str_repeat('-', 96) . $nl;
    foreach ($report['tables_without_module'] as $row) {
        printf(
            "%-32s | %s" . $nl,
            (string) ($row['table'] ?? ''),
            (string) (($row['columns_inline'] ?? '') !== '' ? $row['columns_inline'] : '-')
        );
    }

    exit(1);
}

if (!isset($company_id) || (int) $company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
itm_enforce_maintenance_script_admin_browser($conn);

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$esc = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

require_once __DIR__ . '/lib/script_browser_nav.php';
$itmListBaseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DB tables without modules</title>
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
<?php itm_script_browser_nav_echo($itmListBaseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">Live DB tables without modules/</h1>
        <p class="report-muted">
            Lists every table in <code><?php echo $esc((string) ($report['schema'] ?? '')); ?></code> that has no
            <code>modules/{table}/index.php</code> and no module <code>$crud_table</code> mapping. Policy-hidden internal
            tables are excluded (same rules as
            <a href="compare_database_sql_modules.php">compare_database_sql_modules.php</a>).
        </p>
        <div class="report-summary">
            <span>Tables in database: <strong><?php echo (int) ($report['table_count'] ?? 0); ?></strong></span>
            <span>Modules scanned: <strong><?php echo (int) ($report['module_count'] ?? 0); ?></strong></span>
            <span>Without module: <strong><?php echo (int) ($report['summary']['tables_without_module'] ?? 0); ?></strong></span>
            <span>Expected internal: <strong><?php echo (int) ($report['summary']['tables_expected_internal'] ?? 0); ?></strong></span>
            <span>Matched: <strong><?php echo (int) ($report['summary']['tables_matched'] ?? 0); ?></strong></span>
        </div>
        <p>
            <a class="btn btn-sm" href="?format=json">JSON</a>
            <a class="btn btn-sm" href="compare_database_sql_modules.php">Full SQL vs modules audit</a>
            <a class="btn btn-sm" href="../index.php">Home</a>
        </p>
    </div>

    <div class="report-card">
        <h2 style="margin-top:0;">Tables without module</h2>
        <?php if (($report['tables_without_module'] ?? []) === []): ?>
            <p class="report-muted">Every live table is covered by a module folder or <code>$crud_table</code> mapping.</p>
        <?php else: ?>
        <div class="report-table-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Columns</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['tables_without_module'] as $row): ?>
                    <tr>
                        <td><?php echo itm_script_format_table_link((string) ($row['table'] ?? '')); ?></td>
                        <td class="itm-cell-columns"><?php echo ($row['columns_inline'] ?? '') !== '' ? $esc(itm_database_tables_modules_single_line_text((string) $row['columns_inline'])) : '—'; ?></td>
                        <td><?php echo $esc(itm_database_tables_modules_single_line_text((string) ($row['notes'] ?? ''))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
