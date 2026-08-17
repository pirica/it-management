<?php
/**
 * Regression checks for SLA Command Center helpers, schema, and monitor.
 *
 * Usage: php scripts/verify_ticket_sla_dashboard.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_ticket_sla_dashboard.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_ticket_sla.php</code>, <code>modules/ticket_sla_dashboard/</code>, or <code>scripts/run_ticket_sla_monitor.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Ticket SLA Dashboard Verification');
$nl = itm_script_output_nl();
$failures = 0;

function tsd_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function tsd_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    tsd_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

foreach ([
    'itm_ticket_sla_render_badge',
    'itm_ticket_sla_count_summary',
    'itm_ticket_sla_list_by_filter',
    'itm_ticket_sla_process_scheduled_breaches',
    'itm_ticket_sla_resolve_state',
] as $fn) {
    if (!function_exists($fn)) {
        tsd_verify_fail("Missing helper {$fn}()");
    } else {
        tsd_verify_pass("Helper {$fn}() loaded");
    }
}

$colRes = mysqli_query($conn, "SHOW COLUMNS FROM tickets LIKE 'sla_response_breached_at'");
if (!$colRes || mysqli_num_rows($colRes) === 0) {
    tsd_verify_fail('Column tickets.sla_response_breached_at missing — apply db/migrations/ticket_sla_breach.sql');
} else {
    tsd_verify_pass('Column tickets.sla_response_breached_at exists');
}

$colRes2 = mysqli_query($conn, "SHOW COLUMNS FROM tickets LIKE 'sla_resolve_breached_at'");
if (!$colRes2 || mysqli_num_rows($colRes2) === 0) {
    tsd_verify_fail('Column tickets.sla_resolve_breached_at missing');
} else {
    tsd_verify_pass('Column tickets.sla_resolve_breached_at exists');
}

$companyId = 1;
$summary = itm_ticket_sla_count_summary($conn, $companyId);
foreach (['at_risk', 'breached', 'met', 'total'] as $key) {
    if (!array_key_exists($key, $summary)) {
        tsd_verify_fail("Summary missing key {$key}");
    }
}
if ($failures === 0) {
    tsd_verify_pass('itm_ticket_sla_count_summary() returns expected keys');
}

$list = itm_ticket_sla_list_by_filter($conn, $companyId, 'all', 1, 5);
if (!isset($list['rows'], $list['total'], $list['total_pages'])) {
    tsd_verify_fail('itm_ticket_sla_list_by_filter() missing list keys');
} else {
    tsd_verify_pass('itm_ticket_sla_list_by_filter() returns list envelope');
}

$badgeBreached = itm_ticket_sla_render_badge([
    'sla_response_due_at' => '2020-01-01 00:00:00',
    'first_response_at' => null,
    'sla_resolve_due_at' => null,
    'resolved_at' => null,
]);
if (strpos($badgeBreached, 'Breached') === false) {
    tsd_verify_fail('Breached badge label missing for overdue response');
} else {
    tsd_verify_pass('itm_ticket_sla_render_badge() marks overdue response as Breached');
}

$badgeMet = itm_ticket_sla_render_badge([
    'sla_response_due_at' => '2099-01-01 00:00:00',
    'first_response_at' => '2026-01-01 00:00:00',
    'sla_resolve_due_at' => '2099-06-01 00:00:00',
    'resolved_at' => '2026-02-01 00:00:00',
]);
if (itm_ticket_sla_resolve_state([
    'sla_response_due_at' => '2099-01-01 00:00:00',
    'first_response_at' => '2026-01-01 00:00:00',
    'sla_resolve_due_at' => '2099-06-01 00:00:00',
    'resolved_at' => '2026-02-01 00:00:00',
]) !== 'met') {
    tsd_verify_fail('itm_ticket_sla_resolve_state() expected met');
} else {
    tsd_verify_pass('itm_ticket_sla_resolve_state() detects met SLA');
}

$monitor = itm_ticket_sla_process_scheduled_breaches($conn, $companyId);
if (!isset($monitor['response_stamped'], $monitor['resolve_stamped'])) {
    tsd_verify_fail('itm_ticket_sla_process_scheduled_breaches() missing stats keys');
} else {
    tsd_verify_pass('itm_ticket_sla_process_scheduled_breaches() returns stats');
}

$apiPath = dirname(__DIR__) . '/modules/ticket_sla_dashboard/api.php';
if (!is_file($apiPath)) {
    tsd_verify_fail('Missing modules/ticket_sla_dashboard/api.php');
} else {
    tsd_verify_pass('api.php present');
}

$indexPath = dirname(__DIR__) . '/modules/ticket_sla_dashboard/index.php';
if (!is_file($indexPath)) {
    tsd_verify_fail('Missing modules/ticket_sla_dashboard/index.php');
} else {
    tsd_verify_pass('index.php present');
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
