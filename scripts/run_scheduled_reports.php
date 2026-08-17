<?php
/**
 * Cron: deliver due scheduled executive reports.
 * CLI: php scripts/run_scheduled_reports.php [--company=ID]
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_scheduled_reports.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Scheduled Reports Runner');

$companyId = null;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--company=') === 0) {
        $companyId = (int) substr($arg, 10);
    }
}

$summary = itm_scheduled_reports_process_due($conn, $companyId);
echo colorText('[INFO] processed=' . (int) $summary['processed'] . ' sent=' . (int) $summary['sent'] . ' failed=' . (int) $summary['failed'], 'info') . itm_script_output_nl();
foreach ($summary['errors'] as $err) {
    echo colorText('[FAIL] ' . $err, 'fail') . itm_script_output_nl();
}

itm_script_output_end(((int) $summary['failed']) === 0 ? 0 : 1);
