<?php
/**
 * Cron: monthly equipment depreciation snapshots.
 * CLI: php scripts/run_asset_depreciation.php [--company=ID]
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_asset_depreciation.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Asset Depreciation Runner');

$companyId = null;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--company=') === 0) {
        $companyId = (int) substr($arg, 10);
    }
}

$summary = itm_asset_depreciation_run_monthly_snapshots($conn, $companyId);
echo colorText('[INFO] processed=' . (int) $summary['processed'] . ' logged=' . (int) $summary['logged'], 'info') . itm_script_output_nl();
itm_script_output_end(0);
