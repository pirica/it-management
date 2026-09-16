<?php
/**
 * CLI audit: hotel_bookings portal rate plan quick-add contract (__add_new__ + modal + script path).
 *
 * Browser: scripts/check_hotel_bookings_rate_plan_form.php (Administrator session).
 * CLI: php scripts/check_hotel_bookings_rate_plan_form.php
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text report. CLI: <code>php scripts/check_hotel_bookings_rate_plan_form.php</code> — run after hotel_bookings create/edit or rate-plan select JS changes; exit <code>1</code> on failure.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once dirname(__DIR__) . '/modules/hotel_bookings/includes/hb_booking_form.php';

$nl = itm_check_script_begin_browser_admin('Hotel bookings rate plan form');

$failures = hb_booking_rate_plan_form_audit_failures(dirname(__DIR__));

if (empty($failures)) {
    echo 'PASS: hotel_bookings portal rate plan quick-add contract OK.' . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'FAIL: ' . count($failures) . ' issue(s):' . $nl;
foreach ($failures as $msg) {
    echo ' - ' . $msg . $nl;
}
itm_script_output_end();
exit(1);
