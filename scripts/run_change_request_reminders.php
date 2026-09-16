<?php
/**
 * Send pre-change reminder emails for scheduled change requests.
 *
 * CLI: php scripts/run_change_request_reminders.php [--company=1]
 * Browser: scripts/run_change_request_reminders.php?run=1 (Admin session)
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/run_change_request_reminders.php</code> or <code>php scripts/run_change_request_reminders.php --company=1</code>. Sends reminder emails to requesters and CAB members for submitted/approved changes whose <code>scheduled_start</code> matches <code>reminder_days_before</code> from <code>change_request_settings</code>. Schedule via cron (daily).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Change Request Reminders</title></head><body>';
}

require_once ROOT_PATH . 'includes/itm_change_requests.php';

$nl = itm_script_output_nl();
$companyFilter = 0;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
}

$companyIds = [];
if ($companyFilter > 0) {
    $companyIds[] = $companyFilter;
} else {
    $res = mysqli_query($conn, 'SELECT id FROM companies ORDER BY id');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $companyIds[] = (int)($row['id'] ?? 0);
    }
}

$totalSent = 0;
$totalMatches = 0;
foreach ($companyIds as $companyId) {
    if ($companyId <= 0) {
        continue;
    }
    $batch = itm_change_request_process_reminders($conn, $companyId);
    $totalSent += (int)($batch['sent'] ?? 0);
    $totalMatches += (int)($batch['matches'] ?? 0);
    echo itm_script_format_status_line('[INFO] company ' . $companyId . ': matches=' . (int)($batch['matches'] ?? 0) . ' sent=' . (int)($batch['sent'] ?? 0)) . $nl;
}

echo itm_script_format_status_line('[PASS] Reminder run complete — matches=' . $totalMatches . ' sent=' . $totalSent) . $nl;

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

itm_script_output_end();
exit(0);
