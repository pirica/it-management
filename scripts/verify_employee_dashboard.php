<?php
/**
 * Employee dashboard (dashboard.php) regression checks.
 *
 * CLI: php scripts/verify_employee_dashboard.php
 * Browser: scripts/verify_employee_dashboard.php
 */


declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_employee_dashboard.php">verify_employee_dashboard.php</a>. CLI: <code>php scripts/verify_employee_dashboard.php</code>. Run when changing employee dashboard cards, company switcher, Admin tenant user remap, or stats loader.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
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

$smartWidgetWired = strpos($dashboardSource, 'itm_dashboard_load_smart_widgets') !== false
    || strpos($dashboardSource, 'itm_dashboard_widgets_cards.php') !== false
    || strpos($dashboardSource, 'itm_dashboard_widgets.php') !== false;
if (!$smartWidgetWired) {
    ed_verify_fail('dashboard.php must wire smart dashboard widgets (loader or cards partial)');
} else {
    ed_verify_pass('dashboard.php wires smart dashboard widgets');
}

if (strpos($dashboardSource, 'js/vendor/chart.js') === false) {
    ed_verify_fail('dashboard.php must load Chart.js for smart widget sparklines');
} else {
    ed_verify_pass('dashboard.php loads Chart.js for smart widgets');
}

if (strpos($dashboardSource, 'itm_employee_count_by_employment_status_name') !== false) {
    ed_verify_fail('dashboard.php must not contain company Active/On Leave employment status counts');
} else {
    ed_verify_pass('dashboard.php has no company employment status counts');
}

if (strpos($dashboardSource, 'Switch Company') === false
    || strpos($dashboardSource, 'Change Company') === false
    || strpos($dashboardSource, 'itm_switch_active_company_session') === false
    || strpos($dashboardSource, 'itm_list_employee_accessible_companies') === false
    || strpos($dashboardSource, 'itm_try_post_csrf') === false
    || strpos($dashboardSource, 'itm_company_session_login_employee_id') === false) {
    ed_verify_fail('dashboard.php must contain company switcher markup and switch from login employee identity');
} else {
    ed_verify_pass('dashboard.php has company switcher keyed from login employee identity');
}

$sessionSource = (string)@file_get_contents(ROOT_PATH . 'includes/itm_company_session.php');
$configSource = (string)@file_get_contents(ROOT_PATH . 'config/config.php');
if (strpos($sessionSource, 'function itm_is_admin') === false) {
    ed_verify_fail('itm_is_admin() must be defined in includes/itm_company_session.php so Admin company switch remaps the user on the next GET');
} else {
    ed_verify_pass('itm_is_admin() is defined in itm_company_session.php for tenant Admin remap');
}
$requirePos = strpos($configSource, 'includes/itm_company_session.php');
$ensurePos = strpos($configSource, 'itm_ensure_company_context_employee_session($conn');
if ($requirePos === false || $ensurePos === false || $requirePos > $ensurePos) {
    ed_verify_fail('config.php must require itm_company_session.php before itm_ensure_company_context_employee_session()');
} else {
    ed_verify_pass('config.php remaps tenant Admin identity after itm_is_admin() exists');
}

$cardsSource = is_file($cardsPath) ? (string)file_get_contents($cardsPath) : '';
$requiredLabels = ['My Assets', 'My Tickets', 'Vault Entries', 'My Patches (All/For Me)'];
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

if (strpos($helperSource, 'patches_summary') === false || strpos($helperSource, 'itm_patches_updates_my_work_summary_counts') === false) {
    ed_verify_fail('itm_employee_dashboard.php must load patches_summary via itm_patches_updates_my_work_summary_counts()');
} else {
    ed_verify_pass('Employee dashboard helper loads patches All/For Me summary');
}

if (strpos($cardsSource, 'patches_updates') === false || strpos($cardsSource, 'My Patches (All/For Me)') === false) {
    ed_verify_fail('Employee dashboard My work section must include My Patches (All/For Me) card');
} else {
    ed_verify_pass('Employee dashboard My work includes patches All/For Me card');
}

if (strpos($dashboardSource, 'itm-emp-dash-hero') === false) {
    ed_verify_fail('dashboard.php must render the employee dashboard hero section');
} else {
    ed_verify_pass('dashboard.php renders hero section');
}

if (strpos($cardsSource, 'itm-emp-dash-panel') === false) {
    ed_verify_fail('Employee dashboard cards must use panel layout (itm-emp-dash-panel)');
} else {
    ed_verify_pass('Employee dashboard cards use panel layout');
}

if (strpos($cardsSource, 'itm-emp-dash-card-icon') === false) {
    ed_verify_fail('Employee dashboard cards must render icon tiles (itm-emp-dash-card-icon)');
} else {
    ed_verify_pass('Employee dashboard cards render icon tiles');
}

if (strpos($dashboardSource, 'css/styles.css?v=') === false) {
    ed_verify_fail('dashboard.php must load styles.css with cache-busting version query');
} else {
    ed_verify_pass('dashboard.php loads versioned stylesheet');
}

if (strpos($cardsSource, 'itm-emp-dash-sections') === false) {
    ed_verify_fail('Employee dashboard cards must use responsive section wrapper (itm-emp-dash-sections)');
} else {
    ed_verify_pass('Employee dashboard cards use section wrapper');
}

if (strpos($dashboardSource, 'itm-employee-dashboard-page') === false) {
    ed_verify_fail('dashboard.php must set body class itm-employee-dashboard-page for viewport layout');
} else {
    ed_verify_pass('dashboard.php sets viewport layout body class');
}

$stylesSource = (string)@file_get_contents(ROOT_PATH . 'css/styles.css');
if (strpos($cardsSource, 'itm-emp-dash-section--activity') === false) {
    ed_verify_fail('Activity section must use bottom row layout (itm-emp-dash-section--activity)');
} else {
    ed_verify_pass('Activity section uses bottom row layout');
}

if (strpos($cardsSource, 'modules/myactivity/index.php') === false) {
    ed_verify_fail('Employee dashboard My Activity card must link to modules/myactivity/');
} else {
    ed_verify_pass('Employee dashboard My Activity links to myactivity module');
}

if (strpos($cardsSource, 'modules/audit_logs/index.php') !== false) {
    ed_verify_fail('Employee dashboard cards must not link Audit Logs');
} else {
    ed_verify_pass('Employee dashboard has no Audit Logs card');
}

if (strpos($cardsSource, 'itm-emp-dash-section--private') === false) {
    ed_verify_fail('Private section must use full-width dashboard row (itm-emp-dash-section--private)');
} else {
    ed_verify_pass('Private section uses full-width dashboard row');
}

if (strpos($cardsSource, 'Sidebar Prefs') !== false) {
    ed_verify_fail('Employee dashboard must not render Sidebar Prefs card');
} else {
    ed_verify_pass('Employee dashboard has no Sidebar Prefs card');
}

if (strpos($cardsSource, "'Private'") === false && strpos($cardsSource, '"Private"') === false) {
    ed_verify_fail('Employee dashboard cards must include Private module section');
} else {
    ed_verify_pass('Employee dashboard includes Private module section');
}

if (strpos($stylesSource, 'itm-emp-dash-section--private .itm-emp-dash-grid') === false) {
    ed_verify_fail('Private section CSS must use responsive auto-fit card grid');
} else {
    ed_verify_pass('Private section uses responsive card grid');
}

if (strpos($stylesSource, 'grid-auto-rows: auto') === false
    || strpos($stylesSource, 'body.itm-employee-dashboard-page .main-content') === false
    || strpos($stylesSource, 'overflow: visible') === false) {
    ed_verify_fail('Employee dashboard CSS must use scrollable auto-height sections');
} else {
    ed_verify_pass('Employee dashboard uses scrollable auto-height layout');
}

if (strpos($stylesSource, 'itm-emp-dash-section--activity .itm-emp-dash-grid') === false
    || strpos($stylesSource, 'repeat(3') === false) {
    ed_verify_fail('Activity section CSS must use a three-column single-row card grid');
} else {
    ed_verify_pass('Activity section uses three-column card row');
}

if (strpos($stylesSource, 'itm-emp-dash-company-switch') === false) {
    ed_verify_fail('Employee dashboard CSS must style the company switcher card');
} else {
    ed_verify_pass('Employee dashboard CSS includes company switcher');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All employee dashboard checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
