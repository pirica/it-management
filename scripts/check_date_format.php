<?php
/**
 * Project-wide date format audit: UK dd/mmm/yyyy helpers + hospitality d/M/Y + static gates.
 *
 * Browser: scripts/check_date_format.php (Administrator session).
 * CLI: php scripts/check_date_format.php
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="check_date_format.php?run=1">check_date_format.php?run=1</a> (Administrator). CLI: <code>php scripts/check_date_format.php</code> — exit <code>1</code> on failure.
<p>Contracts: UK <code>dd/mmm/yyyy</code> via <code>itm_format_date_display()</code> (PHP <code>d/M/Y</code>, e.g. <code>18/Jun/2026</code>); hospitality stay dates use the same helper or <code>itm_format_hotel_date_display()</code>; audit stamps <code>d/M/Y - H:i:s</code> (e.g. <code>01/Jan/2026 - 00:00:01</code>). Import parsing still accepts numeric <code>dd/mm/yyyy</code> via <code>itm_parse_date_input()</code>.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_date_format_audit.php';

$nl = itm_check_script_begin_browser_admin('Date format audit');

echo '=== Helper contracts (includes/itm_date_format.php) ===' . $nl;
$fail = itm_date_format_audit_run_helper_contracts();

echo $nl . '=== Hospitality static (modules/hotel* + booking/) ===' . $nl;
$fail += itm_date_format_audit_run_hospitality_static();

echo $nl . '=== Project scaffold date cells (modules/*/index.php) ===' . $nl;
$fail += itm_date_format_audit_run_project_static();

echo $nl;
if ($fail > 0) {
    echo colorText('[FAIL]', 'fail') . ' ' . $fail . ' issue(s)' . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS]', 'pass') . ' Date format contracts OK' . $nl;
itm_script_output_end();
exit(0);
