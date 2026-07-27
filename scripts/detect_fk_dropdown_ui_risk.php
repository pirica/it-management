<?php

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
declare(strict_types=1);


require_once __DIR__ . '/lib/itm_script_stdio.php';
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: opens the UI. CLI: <code>php scripts/detect_fk_dropdown_ui_risk.php [--company=N] [--json] [--data-only] [--code-only] [--repair-catalogs] [--help]</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/detect_fk_dropdown_ui_risk_ui.php';
    return;
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    itm_script_write_stderr( "Unable to resolve project root.\n");
    exit(2);
}

require_once $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'detect_fk_dropdown_ui_risk_lib.php';

$options = [
    'company' => 0,
    'json' => false,
    'code_only' => false,
    'data_only' => false,
    'help' => false,
    'scan_scope' => 'full',
    'repair_catalogs' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
        continue;
    }
    if ($arg === '--json') {
        $options['json'] = true;
        continue;
    }
    if ($arg === '--code-only') {
        $options['code_only'] = true;
        $options['scan_scope'] = 'code_only';
        continue;
    }
    if ($arg === '--data-only') {
        $options['data_only'] = true;
        $options['scan_scope'] = 'data_only';
        continue;
    }
    if (preg_match('/^--company=(\d+)$/', $arg, $m)) {
        $options['company'] = (int)$m[1];
        continue;
    }
    if ($arg === '--repair-catalogs') {
        $options['repair_catalogs'] = true;
        continue;
    }
    itm_script_write_stderr( "Unknown argument: {$arg}\n");
    exit(2);
}

if ($options['help']) {
    itm_script_write_stdout( "Usage: php scripts/detect_fk_dropdown_ui_risk.php [options]\n\n");
    itm_script_write_stdout( "Options:\n");
    itm_script_write_stdout( "  --company=N   Limit data scan to one tenant company_id\n");
    itm_script_write_stdout( "  --json        Machine-readable output\n");
    itm_script_write_stdout( "  --code-only   Scan module PHP patterns only (no database)\n");
    itm_script_write_stdout( "  --data-only   Scan database cross-tenant FK rows only\n");
    itm_script_write_stdout( "  --repair-catalogs  Delete legacy catalog rows with cross-tenant FK ids\n");
    itm_script_write_stdout( "  --help        Show this help\n\n");
    itm_script_write_stdout( "Browser UI: open scripts/detect_fk_dropdown_ui_risk_ui.php\n");
    exit(0);
}

if (!empty($options['repair_catalogs'])) {
    if (!defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }
    require_once $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        itm_script_write_stderr( "Database connection failed.\n");
        exit(2);
    }
    if (!function_exists('itm_cleanup_catalogs_cross_tenant_fk_rows')) {
        itm_script_write_stderr( "Cleanup helper is not available.\n");
        exit(2);
    }
    $deleted = itm_cleanup_catalogs_cross_tenant_fk_rows($conn);
    itm_script_write_stdout( "Removed {$deleted} catalog row(s) with cross-tenant FK references.\n");
    exit(0);
}

$runOptions = [
    'scan_scope' => (string)$options['scan_scope'],
    'company' => (int)$options['company'],
    'code_only' => (bool)$options['code_only'],
    'data_only' => (bool)$options['data_only'],
];

$report = itm_detect_fk_dropdown_ui_risk_run($root, null, $runOptions);
$dataIssues = $report['data_issues'] ?? [];
$codeIssues = $report['code_issues'] ?? [];

if (!empty($report['db_error'])) {
    itm_script_write_stderr( (string)$report['db_error'] . "\n");
    exit(2);
}

if ($options['json']) {
    itm_script_write_stdout( json_encode($report, JSON_PRETTY_PRINT) . "\n");
} else {
    itm_script_write_stdout( "FK dropdown UI risk report\n");
    itm_script_write_stdout( str_repeat('=', 28) . "\n\n");

    if (!$options['code_only']) {
        itm_script_write_stdout( "Database cross-tenant FK rows: " . count($dataIssues) . "\n");
        itm_script_write_stdout( "  duplicate_dropdown_risk: " . (int)($report['summary']['duplicate_dropdown_data'] ?? 0) . "\n\n");

        foreach ($dataIssues as $issue) {
            $riskLabel = itm_detect_fk_risk_label((string)($issue['risk'] ?? ''));
            itm_script_write_stdout( '[' . strtoupper($riskLabel) . '] ' . itm_detect_fk_data_issue_summary($issue) . "\n");
            itm_script_write_stdout( '  table: ' . (string)($issue['child_table'] ?? '') . ' row #' . (int)($issue['child_id'] ?? 0) . "\n");
            itm_script_write_stdout( '  edit: ' . (string)($issue['module'] ?? '') . 'edit.php?id=' . (int)($issue['child_id'] ?? 0) . "\n");
        }

        if ($dataIssues !== []) {
            itm_script_write_stdout( "\n");
        }
    }

    if (!$options['data_only']) {
        itm_script_write_stdout( "Module code without tenant FK resolve: " . count($codeIssues) . "\n\n");
        foreach ($codeIssues as $issue) {
            itm_script_write_stdout( '[' . strtoupper(itm_detect_fk_risk_label((string)($issue['risk'] ?? ''))) . '] '
                . itm_detect_fk_code_issue_summary($issue) . "\n");
            itm_script_write_stdout( '  file: ' . (string)($issue['file'] ?? '') . "\n");
        }
    }

    if ($dataIssues === [] && $codeIssues === []) {
        itm_script_write_stdout( colorText("\n[OK] No FK dropdown UI risks detected.\n", 'pass'));
    } else {
        itm_script_write_stdout( colorText("\n[FAIL] Review rows above. duplicate_dropdown_risk = two select options for the same logical FK value.\n", 'fail'));
    }
}

$exitCode = ($dataIssues === [] && $codeIssues === []) ? 0 : 1;
exit($exitCode);

itm_script_output_end();
