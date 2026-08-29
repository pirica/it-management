<?php
/**
 * Software / Office / OS / hardware EOL tracking regression.
 *
 * Browser: open scripts/verify_software_eol.php?run=1 (Administrator session).
 * CLI: php scripts/verify_software_eol.php
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_software_eol.php</code> — schema columns, catalog helper, inherited EOL window count, calendar collector, email row shape. Run after changing <code>includes/itm_software_eol.php</code> or EOL DDL/seeds.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_software_eol.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Software EOL Verification');
$nl = itm_script_output_nl();
$failures = 0;

function seol_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function seol_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    seol_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

$required = [
    'equipment' => ['eol_date', 'extended_date', 'esu_date'],
    'software' => ['name', 'build', 'eol_date', 'extended_date', 'esu_date'],
    'equipment_software' => ['equipment_id', 'software_id'],
    'workstation_office' => ['build', 'eol_date', 'extended_date', 'esu_date'],
    'workstation_os_versions' => ['build', 'eol_date', 'extended_date', 'esu_date'],
];
foreach ($required as $table => $cols) {
    foreach ($cols as $col) {
        if (!itm_software_eol_table_has_column($conn, $table, $col)) {
            seol_fail($table . ' missing column ' . $col);
        }
    }
}
if ($failures === 0) {
    seol_pass('EOL columns present on equipment, software, office, OS versions, and junction');
}

if (itm_software_eol_tables_ready($conn)) {
    seol_pass('itm_software_eol_tables_ready');
} else {
    seol_fail('itm_software_eol_tables_ready is false');
}

$catalog = itm_email_alert_rule_catalog();
if (isset($catalog['eol_date']['supports_days_before']) && $catalog['eol_date']['supports_days_before'] === true) {
    seol_pass('email catalog includes eol_date');
} else {
    seol_fail('email catalog missing eol_date');
}

$companyId = 1;
$events = [];
$start = date('Y-m-d', strtotime('-1 year'));
$end = date('Y-m-d', strtotime('+10 years'));
itm_software_eol_append_calendar_events($conn, $companyId, $start, $end, $events);
$eventCount = 0;
foreach ($events as $dayEvents) {
    $eventCount += is_array($dayEvents) ? count($dayEvents) : 0;
}
if ($eventCount > 0) {
    seol_pass('calendar collector returned ' . $eventCount . ' event(s) for company 1 in a wide window');
} else {
    seol_fail('calendar collector returned no events for company 1 (import seeds?)');
}

$dash = itm_software_eol_count_equipment_in_window($conn, $companyId, 30);
if ($dash >= 0) {
    seol_pass('inherited EOL 30-day count=' . $dash);
} else {
    seol_fail('inherited EOL 30-day count failed');
}

$today = date('Y-m-d');
$cutoff = date('Y-m-d', strtotime('+30 days'));
$emailRows = itm_software_eol_email_rows($conn, $companyId, $today, $cutoff);
seol_pass('email helper returned ' . count($emailRows) . ' row(s) for company 1');

$catalogRows = itm_software_eol_expiring_catalog_rows($conn, $companyId, 'eol_date');
if ($catalogRows !== []) {
    seol_pass('catalog EOL expiring rows=' . count($catalogRows));
} else {
    seol_fail('catalog EOL expiring rows empty');
}

$hw = itm_software_eol_expiring_hardware_rows($conn, $companyId, 'eol_date');
seol_pass('hardware EOL expiring rows=' . count($hw));

$ids = itm_software_eol_normalize_id_list(['1', '1', '0', '-2', '3']);
if ($ids === [1, 3]) {
    seol_pass('normalize id list unique positive ints');
} else {
    seol_fail('normalize id list unexpected ' . json_encode($ids));
}

if ($failures > 0) {
    echo itm_script_format_status_line('[FAIL] Software EOL verification failed (' . $failures . ')') . $nl;
    itm_script_output_end();
    exit(1);
}

echo itm_script_format_status_line('[PASS] Software EOL verification') . $nl;
itm_script_output_end();
exit(0);
