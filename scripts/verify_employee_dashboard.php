<?php
/**
 * Employee dashboard (dashboard.php) regression checks.
 *
 * CLI: php scripts/verify_employee_dashboard.php
 * Browser: scripts/verify_employee_dashboard.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employee Dashboard Verification');

$nl = itm_script_output_nl();
$failures = 0;

function ed_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function ed_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$dashboardPath = ROOT_PATH . 'dashboard.php';
if (!is_file($dashboardPath)) {
    ed_verify_fail('Missing dashboard.php');
    itm_script_output_end();
    exit(1);
}

$dashboardSource = (string)file_get_contents($dashboardPath);
$helperPath = ROOT_PATH . 'includes/itm_employee_dashboard.php';
$cardsPath = ROOT_PATH . 'includes/itm_employee_dashboard_cards.php';

if (!is_file($helperPath)) {
    ed_verify_fail('Missing includes/itm_employee_dashboard.php');
} else {
    ed_verify_pass('includes/itm_employee_dashboard.php exists');
}

if (!is_file($cardsPath)) {
    ed_verify_fail('Missing includes/itm_employee_dashboard_cards.php');
} else {
    ed_verify_pass('includes/itm_employee_dashboard_cards.php exists');
}

if (strpos($dashboardSource, 'itm_employee_dashboard_load_context') === false) {
    ed_verify_fail('dashboard.php must call itm_employee_dashboard_load_context()');
} else {
    ed_verify_pass('dashboard.php loads employee dashboard context');
}

if (strpos($dashboardSource, 'itm_employee_count_by_employment_status_name') !== false) {
    ed_verify_fail('dashboard.php must not contain company Active/On Leave employment status counts');
} else {
    ed_verify_pass('dashboard.php has no company employment status counts');
}

if (strpos($dashboardSource, 'Switch Company') !== false) {
    ed_verify_fail('dashboard.php must not contain company switcher markup');
} else {
    ed_verify_pass('dashboard.php has no company switcher');
}

$cardsSource = is_file($cardsPath) ? (string)file_get_contents($cardsPath) : '';
$requiredLabels = ['My Assets', 'My Tickets', 'Vault Entries'];
foreach ($requiredLabels as $label) {
    if (strpos($cardsSource, $label) === false) {
        ed_verify_fail('Employee dashboard cards must include label: ' . $label);
    } else {
        ed_verify_pass('Employee dashboard cards include: ' . $label);
    }
}

if (strpos($helperSource = (string)@file_get_contents($helperPath), 'itm_user_config_fetch_stats_batch') === false) {
    ed_verify_fail('itm_employee_dashboard.php must call itm_user_config_fetch_stats_batch()');
} else {
    ed_verify_pass('Employee dashboard helper uses consolidated stats batch');
}

if (strpos($dashboardSource, 'itm-emp-dash-hero') === false) {
    ed_verify_fail('dashboard.php must render the employee dashboard hero section');
} else {
    ed_verify_pass('dashboard.php renders hero section');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All employee dashboard checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
