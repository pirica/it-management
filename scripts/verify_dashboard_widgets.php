<?php
/**
 * Smart dashboard widgets regression checks.
 *
 * CLI: php scripts/verify_dashboard_widgets.php
 * Browser: scripts/verify_dashboard_widgets.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_dashboard_widgets.php?run=1">verify_dashboard_widgets.php?run=1</a>. CLI: <code>php scripts/verify_dashboard_widgets.php</code>. Run when changing smart dashboard widgets on <code>dashboard.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_dashboard_widgets.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Smart Dashboard Widgets Verification');

$nl = itm_script_output_nl();
$failures = 0;

function dw_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function dw_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    dw_verify_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$dashboardPath = ROOT_PATH . 'dashboard.php';
$widgetsPath = ROOT_PATH . 'includes/itm_dashboard_widgets.php';
$queriesPath = ROOT_PATH . 'includes/itm_dashboard_queries.php';
$ticketsQueryPath = ROOT_PATH . 'includes/itm_tickets_list_query.php';

if (!is_file($widgetsPath)) {
    dw_verify_fail('Missing includes/itm_dashboard_widgets.php');
} else {
    dw_verify_pass('includes/itm_dashboard_widgets.php exists');
}

if (!is_file($queriesPath)) {
    dw_verify_fail('Missing includes/itm_dashboard_queries.php');
} else {
    dw_verify_pass('includes/itm_dashboard_queries.php exists');
}

$registry = itm_dashboard_widget_registry();
$expectedSlugs = ['my_open_tickets', 'expiring_30d', 'visitors_today', 'backup_tape_gaps'];
foreach ($expectedSlugs as $slug) {
    if (!isset($registry[$slug])) {
        dw_verify_fail('Registry missing widget slug: ' . $slug);
    } else {
        dw_verify_pass('Registry contains slug: ' . $slug);
    }
}

$dashboardSource = is_file($dashboardPath) ? (string)file_get_contents($dashboardPath) : '';
if (strpos($dashboardSource, 'itm_dashboard_load_smart_widgets') === false) {
    dw_verify_fail('dashboard.php must call itm_dashboard_load_smart_widgets()');
} else {
    dw_verify_pass('dashboard.php loads smart widgets');
}

if (strpos($dashboardSource, 'itm_dashboard_widgets_cards.php') === false) {
    dw_verify_fail('dashboard.php must include itm_dashboard_widgets_cards.php');
} else {
    dw_verify_pass('dashboard.php includes smart widget cards partial');
}

if (strpos($dashboardSource, 'js/vendor/chart.js') === false) {
    dw_verify_fail('dashboard.php must load Chart.js for smart widgets');
} else {
    dw_verify_pass('dashboard.php loads Chart.js');
}

$ticketsSource = is_file($ticketsQueryPath) ? (string)file_get_contents($ticketsQueryPath) : '';
if (strpos($ticketsSource, 'open_only') === false || strpos($ticketsSource, 'ts.is_closed = 0') === false) {
    dw_verify_fail('itm_tickets_list_query.php must support open_only filter');
} else {
    dw_verify_pass('Tickets list query supports open_only');
}

$stylesSource = (string)@file_get_contents(ROOT_PATH . 'css/styles.css');
if (strpos($stylesSource, 'itm-smart-dash-grid') === false) {
    dw_verify_fail('styles.css must define itm-smart-dash-grid');
} else {
    dw_verify_pass('Smart dashboard CSS grid present');
}

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$adminId = 0;
$adminStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM employees WHERE company_id = ? AND username = \'Admin\' AND deleted_at IS NULL LIMIT 1'
);
if ($adminStmt) {
    mysqli_stmt_bind_param($adminStmt, 'i', $companyId);
    mysqli_stmt_execute($adminStmt);
    $adminRes = mysqli_stmt_get_result($adminStmt);
    $adminRow = $adminRes ? mysqli_fetch_assoc($adminRes) : null;
    mysqli_stmt_close($adminStmt);
    if (is_array($adminRow)) {
        $adminId = (int)($adminRow['id'] ?? 0);
    }
}

if ($adminId <= 0) {
    dw_verify_fail('Could not resolve seed Admin employee for company ' . $companyId);
} else {
    dw_verify_pass('Resolved seed Admin employee id ' . $adminId);
    $resolved = itm_dashboard_resolve_widgets_for_employee($conn, $companyId, $adminId);
    if (!is_array($resolved) || count($resolved) < 1) {
        dw_verify_fail('Admin should resolve at least one smart widget');
    } else {
        dw_verify_pass('Admin resolves ' . count($resolved) . ' smart widget(s)');
    }

    $openCount = itm_dashboard_query_my_open_tickets_count($conn, $companyId, $adminId);
    if ($openCount < 0) {
        dw_verify_fail('Open ticket count must be non-negative');
    } else {
        dw_verify_pass('Open ticket metric is non-negative (' . $openCount . ')');
    }

    $directSql = 'SELECT COUNT(*) AS cnt FROM tickets t
        INNER JOIN ticket_statuses ts ON ts.id = t.status_id
        WHERE t.company_id = ? AND t.assigned_to_employee_id = ?
          AND t.deleted_at IS NULL AND t.is_archived = 0 AND ts.is_closed = 0';
    $directStmt = mysqli_prepare($conn, $directSql);
    $directCount = -1;
    if ($directStmt) {
        mysqli_stmt_bind_param($directStmt, 'ii', $companyId, $adminId);
        mysqli_stmt_execute($directStmt);
        $directRes = mysqli_stmt_get_result($directStmt);
        $directRow = $directRes ? mysqli_fetch_assoc($directRes) : null;
        mysqli_stmt_close($directStmt);
        if (is_array($directRow)) {
            $directCount = (int)($directRow['cnt'] ?? -1);
        }
    }
    if ($directCount !== $openCount) {
        dw_verify_fail('Open ticket helper mismatch direct SQL (' . $openCount . ' vs ' . $directCount . ')');
    } else {
        dw_verify_pass('Open ticket helper matches direct SQL');
    }

    if (!itm_dashboard_widget_can_show($conn, $companyId, $adminId, 'nonexistent_widget_slug')) {
        dw_verify_pass('Unknown widget slug denied');
    } else {
        dw_verify_fail('Unknown widget slug must not be allowed');
    }
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All smart dashboard widget checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
