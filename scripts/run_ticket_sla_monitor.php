<?php
/**
 * Cron: stamp ticket SLA breaches, log activity, notify assignees.
 *
 * CLI: php scripts/run_ticket_sla_monitor.php [--company=1]
 * Browser: scripts/run_ticket_sla_monitor.php?run=1 (admin)
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/run_ticket_sla_monitor.php</code> or <code>php scripts/run_ticket_sla_monitor.php --company=1</code>. Schedule every 15 minutes via cron. Admin browser: <code>?run=1</code>.
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
    if (!isset($_GET['run']) || (string)$_GET['run'] !== '1') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Ticket SLA Monitor</title></head><body>';
        echo itm_script_browser_nav_echo();
        echo '<h1>Run Ticket SLA Monitor</h1>';
        echo itm_script_browser_how_to_use();
        echo '<p><a href="?run=1">Run now</a></p></body></html>';
        itm_script_output_end();
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Ticket SLA Monitor</title></head><body>';
}

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

$scopeLabel = $companyFilter > 0 ? 'company ' . $companyFilter : 'all companies';
$stats = itm_ticket_sla_process_scheduled_breaches($conn, $companyFilter > 0 ? $companyFilter : null);

echo itm_script_format_status_line('[OK] Ticket SLA monitor (' . $scopeLabel . ')') . $nl;
echo itm_script_format_status_line('  response breaches stamped: ' . (int)$stats['response_stamped']) . $nl;
echo itm_script_format_status_line('  resolve breaches stamped: ' . (int)$stats['resolve_stamped']) . $nl;

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

itm_script_output_end();
