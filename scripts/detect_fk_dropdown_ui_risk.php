<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/detect_fk_dropdown_ui_risk_ui.php';
    return;
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
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
    fwrite(STDERR, "Unknown argument: {$arg}\n");
    exit(2);
}

if ($options['help']) {
    fwrite(STDOUT, "Usage: php scripts/detect_fk_dropdown_ui_risk.php [options]\n\n");
    fwrite(STDOUT, "Options:\n");
    fwrite(STDOUT, "  --company=N   Limit data scan to one tenant company_id\n");
    fwrite(STDOUT, "  --json        Machine-readable output\n");
    fwrite(STDOUT, "  --code-only   Scan module PHP patterns only (no database)\n");
    fwrite(STDOUT, "  --data-only   Scan database cross-tenant FK rows only\n");
    fwrite(STDOUT, "  --repair-catalogs  Delete legacy catalog rows with cross-tenant FK ids\n");
    fwrite(STDOUT, "  --help        Show this help\n\n");
    fwrite(STDOUT, "Browser UI: open scripts/detect_fk_dropdown_ui_risk_ui.php\n");
    exit(0);
}

if (!empty($options['repair_catalogs'])) {
    if (!defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }
    require_once $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        fwrite(STDERR, "Database connection failed.\n");
        exit(2);
    }
    if (!function_exists('itm_cleanup_catalogs_cross_tenant_fk_rows')) {
        fwrite(STDERR, "Cleanup helper is not available.\n");
        exit(2);
    }
    $deleted = itm_cleanup_catalogs_cross_tenant_fk_rows($conn);
    fwrite(STDOUT, "Removed {$deleted} catalog row(s) with cross-tenant FK references.\n");
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
    fwrite(STDERR, (string)$report['db_error'] . "\n");
    exit(2);
}

if ($options['json']) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT) . "\n");
} else {
    fwrite(STDOUT, "FK dropdown UI risk report\n");
    fwrite(STDOUT, str_repeat('=', 28) . "\n\n");

    if (!$options['code_only']) {
        fwrite(STDOUT, "Database cross-tenant FK rows: " . count($dataIssues) . "\n");
        fwrite(STDOUT, "  duplicate_dropdown_risk: " . (int)($report['summary']['duplicate_dropdown_data'] ?? 0) . "\n\n");

        foreach ($dataIssues as $issue) {
            $riskLabel = itm_detect_fk_risk_label((string)($issue['risk'] ?? ''));
            fwrite(STDOUT, '[' . strtoupper($riskLabel) . '] ' . itm_detect_fk_data_issue_summary($issue) . "\n");
            fwrite(STDOUT, '  table: ' . (string)($issue['child_table'] ?? '') . ' row #' . (int)($issue['child_id'] ?? 0) . "\n");
            fwrite(STDOUT, '  edit: ' . (string)($issue['module'] ?? '') . 'edit.php?id=' . (int)($issue['child_id'] ?? 0) . "\n");
        }

        if ($dataIssues !== []) {
            fwrite(STDOUT, "\n");
        }
    }

    if (!$options['data_only']) {
        fwrite(STDOUT, "Module code without tenant FK resolve: " . count($codeIssues) . "\n\n");
        foreach ($codeIssues as $issue) {
            fwrite(STDOUT, '[' . strtoupper(itm_detect_fk_risk_label((string)($issue['risk'] ?? ''))) . '] '
                . itm_detect_fk_code_issue_summary($issue) . "\n");
            fwrite(STDOUT, '  file: ' . (string)($issue['file'] ?? '') . "\n");
        }
    }

    if ($dataIssues === [] && $codeIssues === []) {
        fwrite(STDOUT, colorText("\n[OK] No FK dropdown UI risks detected.\n", 'pass'));
    } else {
        fwrite(STDOUT, colorText("\n[FAIL] Review rows above. duplicate_dropdown_risk = two select options for the same logical FK value.\n", 'fail'));
    }
}

$exitCode = ($dataIssues === [] && $codeIssues === []) ? 0 : 1;
exit($exitCode);

itm_script_output_end();
