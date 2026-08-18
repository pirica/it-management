<?php
/**
 * Scheduled workflow automation runner (date-based triggers).
 *
 * CLI: php scripts/run_automation_rules.php
 * Browser: scripts/run_automation_rules.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Schedule daily (cron) to evaluate date-based automation triggers such as <code>equipment.warranty_expiring</code>.
<code>php scripts/run_automation_rules.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Automation Rules Scheduler');

$nl = itm_script_output_nl();
$count = itm_automation_rules_run_scheduled($conn);
echo colorText('[PASS] Scheduled dispatch contexts processed: ' . (int)$count, 'pass') . $nl;

itm_script_output_end(0);
