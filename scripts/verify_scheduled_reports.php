<?php
/**
 * Scheduled executive reports regression checks.
 * CLI: php scripts/verify_scheduled_reports.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM'
<code>php scripts/verify_scheduled_reports.php</code> — validates <code>scheduled_reports</code> schema, helpers, and cron matcher.
ITM;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_scheduled_reports.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Scheduled Reports');
$nl = itm_script_output_nl();
$failures = 0;

function sr_fail($msg)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $msg, 'fail') . $nl;
}
function sr_pass($msg)
{
    global $nl;
    echo colorText('[PASS] ' . $msg, 'pass') . $nl;
}

$colRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'scheduled_reports'");
$row = $colRes ? mysqli_fetch_assoc($colRes) : null;
if ((int)($row['c'] ?? 0) < 10) {
    sr_fail('scheduled_reports table missing or incomplete');
} else {
    sr_pass('scheduled_reports columns present');
}

if (!itm_scheduled_reports_cron_is_due('0 8 * * 1', new DateTimeImmutable('2026-08-17 08:00:00'))) {
    sr_fail('Cron matcher should match Monday 08:00');
} else {
    sr_pass('Cron due matcher positive case');
}
if (itm_scheduled_reports_cron_is_due('0 8 * * 1', new DateTimeImmutable('2026-08-17 09:00:00'))) {
    sr_fail('Cron matcher should reject wrong hour');
} else {
    sr_pass('Cron due matcher negative case');
}

$dataset = itm_scheduled_reports_load_dataset($conn, 1, 'equipment_summary');
if (!isset($dataset['labels']) || !is_array($dataset['labels'])) {
    sr_fail('Dataset loader returned invalid shape');
} else {
    sr_pass('Dataset loader callable');
}

itm_script_output_end($failures === 0 ? 0 : 1);
