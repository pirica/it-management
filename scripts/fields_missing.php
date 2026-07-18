<?php
/**
 * All-module schema/UI audit: database.sql columns vs live MySQL vs module screens.
 *
 * Why: employee_fields_missing.php covers employees only; this script generalizes the same
 * checks across every module with a resolvable $crud_table (plus dynamic scaffold modules).
 *
 * Browser: scripts/fields_missing.php (Admin). Optional ?module=slug filter.
 * CLI: php scripts/fields_missing.php [--module=slug] [--json]
 */
declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$moduleFilter = '';
$jsonOutput = false;
if ($itmIsCli) {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (strpos((string) $arg, '--module=') === 0) {
            $moduleFilter = trim(substr((string) $arg, 9));
        }
        if ((string) $arg === '--json') {
            $jsonOutput = true;
        }
    }
} else {
    if (isset($_GET['module'])) {
        $moduleFilter = trim((string) $_GET['module']);
    }
    if (isset($_GET['json'])) {
        $jsonOutput = true;
    }
}

if (!$conn instanceof mysqli) {
    fwrite(STDERR, "[FAIL] No database connection.\n");
    exit(1);
}

$report = itm_fields_missing_collect_report($conn, $moduleFilter !== '' ? $moduleFilter : null);
$nl = itm_script_output_nl();

if ($jsonOutput) {
    if (!$itmIsCli) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit($report['failure_count'] > 0 ? 1 : 0);
}

itm_script_output_begin('Fields missing — schema/UI audit');

if (!$itmIsCli) {
    itm_script_output_close_pre();
    echo '<h1>Fields missing — schema/UI audit</h1>';
    echo '<p>Compares <code>database.sql</code> columns, live MySQL schema, and module UI coverage for every '
        . 'discoverable <code>$crud_table</code> module. Employees uses the same critical-field list as '
        . '<a href="employee_fields_missing.php">employee_fields_missing.php</a>. '
        . 'Flattened scaffold modules with <code>$uiColumns</code> pass schema checks and are marked dynamic scaffold.</p>';
    echo '<form method="get" style="margin:16px 0;padding:12px;border:1px solid #d0d7de;border-radius:8px;max-width:720px;">';
    echo '<label for="module" style="display:block;margin-bottom:8px;font-weight:600;">Module filter (optional)</label>';
    echo '<input type="text" name="module" id="module" value="' . htmlspecialchars($moduleFilter, ENT_QUOTES, 'UTF-8') . '" placeholder="e.g. employees" style="width:100%;padding:8px;margin-bottom:12px;">';
    echo '<button type="submit" style="padding:8px 12px;">Run audit</button>';
    echo ' <a href="fields_missing.php?json=1' . ($moduleFilter !== '' ? '&amp;module=' . rawurlencode($moduleFilter) : '') . '" style="margin-left:8px;">JSON</a>';
    echo '</form><pre>';
}

echo 'Schema tables (database.sql): ' . (int) $report['schema_table_count'] . $nl;
echo 'Modules audited: ' . (int) $report['module_count'] . $nl;
if ($moduleFilter !== '') {
    echo 'Module filter: ' . $moduleFilter . $nl;
}
echo str_repeat('-', 72) . $nl . $nl;

foreach ($report['modules'] as $moduleReport) {
    $moduleSlug = (string) $moduleReport['module'];
    $table = (string) $moduleReport['table'];
    $moduleLink = function_exists('itm_script_format_modules_file_link')
        ? itm_script_format_modules_file_link('modules/' . $moduleSlug . '/index.php')
        : $moduleSlug;

    echo $moduleLink . ' (table: ' . (function_exists('itm_script_format_table_link')
        ? itm_script_format_table_link($table)
        : $table) . ', ui: ' . (string) $moduleReport['ui_mode'] . ')' . $nl;

    foreach ($moduleReport['passes'] as $passLine) {
        echo colorText('[PASS] ' . $passLine, 'pass') . $nl;
    }
    foreach ($moduleReport['failures'] as $failure) {
        echo colorText('[FAIL] ' . (string) ($failure['message'] ?? ''), 'fail') . $nl;
    }
    foreach ($moduleReport['infos'] as $infoLine) {
        echo colorText('[INFO] ' . $infoLine, 'info') . $nl;
    }
    echo $nl;
}

if ($moduleFilter === '' && $report['tables_without_module'] !== []) {
    echo 'Tables in database.sql without a discoverable module folder (' . count($report['tables_without_module']) . ')' . $nl;
    foreach ($report['tables_without_module'] as $tableName) {
        $label = function_exists('itm_script_format_table_link')
            ? itm_script_format_table_link($tableName)
            : $tableName;
        echo '  - ' . $label . $nl;
    }
    echo $nl;
}

if ((int) $report['failure_count'] > 0) {
    echo colorText('Result: ' . (int) $report['failure_count'] . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('Result: all checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
