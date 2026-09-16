<?php
/**
 * Hospitality-only date format gate (subset of check_date_format.php).
 *
 * Browser: scripts/check_hospitality_date_format.php (Administrator session).
 * CLI: php scripts/check_hospitality_date_format.php
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="check_hospitality_date_format.php?run=1">check_hospitality_date_format.php?run=1</a> (Administrator). CLI: <code>php scripts/check_hospitality_date_format.php</code> — exit <code>1</code> on failure.
<p>Hospitality subset of <code>check_date_format.php</code>: helper contracts + <code>modules/hotel*</code> and <code>booking/</code> stay-date static scan (<code>d/M/Y</code>).</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_date_format_audit.php';

$nl = itm_check_script_begin_browser_admin('Hospitality date format audit');

echo '=== Helper contracts (includes/itm_date_format.php) ===' . $nl;
$fail = itm_date_format_audit_run_helper_contracts();

echo $nl . '=== Hospitality static (modules/hotel* + booking/) ===' . $nl;
$fail += itm_date_format_audit_run_hospitality_static();

echo $nl;
if ($fail > 0) {
    echo colorText('[FAIL]', 'fail') . ' ' . $fail . ' issue(s)' . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS]', 'pass') . ' Hospitality date format contracts OK' . $nl;
itm_script_output_end();
exit(0);
