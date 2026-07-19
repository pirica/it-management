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
itm_fields_missing_apply_reviewed_flags_to_report($report);
$auditNl = "\n";

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
        . 'Flattened scaffold modules with <code>$uiColumns</code> pass UI via dynamic scaffold. '
        . 'Bespoke modules print gated results as <code>[SKIP][pass]</code> / <code>[SKIP][fail]</code> / <code>[SKIP][fail][reviewed]</code> (informational gate only — not counted in the Result failure total). '
        . 'Reviewed exceptions: <a href="fields_missing_reviewed.php">fields_missing_reviewed.php</a> · <code>scripts/data/fields_missing_reviewed.json</code>.</p>';
    echo '<details style="margin:12px 0;max-width:900px;"><summary style="cursor:pointer;font-weight:600;">Section legend</summary>';
    echo '<pre style="margin:8px 0;padding:12px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:6px;">';
    echo htmlspecialchars(itm_fields_missing_format_legend(''), ENT_QUOTES, 'UTF-8');
    echo '</pre></details>';
    echo '<form method="get" style="margin:16px 0;padding:12px;border:1px solid #d0d7de;border-radius:8px;max-width:720px;">';
    echo '<label for="module" style="display:block;margin-bottom:8px;font-weight:600;">Module filter (optional)</label>';
    echo '<input type="text" name="module" id="module" value="' . htmlspecialchars($moduleFilter, ENT_QUOTES, 'UTF-8') . '" placeholder="e.g. employees" style="width:100%;padding:8px;margin-bottom:12px;">';
    echo '<button type="submit" style="padding:8px 12px;">Run audit</button>';
    echo ' <a href="fields_missing.php?json=1' . ($moduleFilter !== '' ? '&amp;module=' . rawurlencode($moduleFilter) : '') . '" style="margin-left:8px;">JSON</a>';
    echo '</form><pre>';
}

echo itm_script_escape_browser_pre_text('Schema tables (database.sql): ' . (int) $report['schema_table_count']) . $auditNl;
echo itm_script_escape_browser_pre_text('Modules audited: ' . (int) $report['module_count']) . $auditNl;
if ($moduleFilter !== '') {
    echo itm_script_escape_browser_pre_text('Module filter: ' . $moduleFilter) . $auditNl;
}
echo itm_script_escape_browser_pre_text(itm_fields_missing_format_legend($auditNl));
echo itm_script_escape_browser_pre_text(str_repeat('-', 72) . $auditNl . $auditNl);

foreach ($report['modules'] as $moduleReport) {
    $uiMode = (string) ($moduleReport['ui_mode'] ?? '');
    $moduleSlug = (string) $moduleReport['module'];
    $table = itm_fields_missing_format_report_table_label((string) ($moduleReport['table'] ?? ''));
    $moduleLink = function_exists('itm_script_format_modules_file_link')
        ? itm_script_format_modules_file_link('modules/' . $moduleSlug . '/index.php')
        : $moduleSlug;

    $tableRef = $table;
    if ($table !== '-' && function_exists('itm_script_format_table_link')) {
        $tableRef = itm_script_format_table_link($table);
    }

    echo $moduleLink . ' (table: ' . $tableRef . ', ui: ' . htmlspecialchars($uiMode, ENT_QUOTES, 'UTF-8') . ')' . $auditNl;
    echo itm_script_escape_browser_pre_text(itm_fields_missing_format_columns_block($moduleReport, $auditNl));

    itm_fields_missing_echo_module_check_lines($moduleReport, $auditNl);
    echo $auditNl;
}

if ($moduleFilter === '' && $report['tables_without_module'] !== []) {
    echo itm_script_escape_browser_pre_text(
        'Tables in database.sql without a discoverable module folder (' . count($report['tables_without_module']) . ')'
    ) . $auditNl;
    foreach ($report['tables_without_module'] as $tableName) {
        $label = function_exists('itm_script_format_table_link')
            ? itm_script_format_table_link($tableName, '', true)
            : htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8');
        if (function_exists('itm_script_format_table_link')) {
            echo '  - ' . $label . $auditNl;
        } else {
            echo itm_script_escape_browser_pre_text('  - ' . $label) . $auditNl;
        }
    }
    echo $auditNl;
}

$skipGateSummary = itm_fields_missing_format_skip_gate_failure_summary_block($report, $auditNl);
if ($skipGateSummary !== '') {
    foreach (preg_split('/\r\n|\r|\n/', trim($skipGateSummary)) as $summaryLine) {
        if ($summaryLine === '') {
            continue;
        }
        if (strpos($summaryLine, '---') === 0 || strpos($summaryLine, 'Bespoke gate failure summary') === 0) {
            echo itm_script_escape_browser_pre_text($summaryLine) . $auditNl;
            continue;
        }
        itm_fields_missing_echo_status_line($summaryLine, $auditNl);
    }
    echo $auditNl;
}

echo itm_script_escape_browser_pre_text(itm_fields_missing_format_audit_summary($report, $auditNl));

if ((int) $report['failure_count'] > 0) {
    echo colorText(itm_script_escape_browser_pre_text('Result: ' . (int) $report['failure_count'] . ' failure(s).'), 'fail') . $auditNl;
    itm_script_output_end();
    exit(1);
}

echo colorText(itm_script_escape_browser_pre_text('Result: all checks passed.'), 'pass') . $auditNl;
$skipGateFailures = (int) ($report['skip_gate_failure_count'] ?? 0);
if ($skipGateFailures > 0) {
    echo colorText(
        itm_script_escape_browser_pre_text(
            'Note: ' . $skipGateFailures . ' bespoke [SKIP][fail] line(s) above are informational only (not counted here).'
        ),
        'warn'
    ) . $auditNl;
}
itm_script_output_end();
exit(0);
