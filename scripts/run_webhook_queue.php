<?php
/**
 * Cron: process integration webhook delivery queue.
 * CLI: php scripts/run_webhook_queue.php [--limit=50]
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Integration Webhook Queue');

$limit = 50;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int) substr($arg, 8);
    }
}

$summary = itm_webhook_queue_process_pending($conn, $limit);
echo colorText('[INFO] processed=' . (int) $summary['processed'] . ' delivered=' . (int) $summary['delivered'] . ' failed=' . (int) $summary['failed'], 'info') . itm_script_output_nl();
itm_script_output_end(0);
