<?php
/**
 * CLI/browser regression: hotel booking schema helpers and tenant seeds.
 */
define('ITM_CLI_SCRIPT', true);
require dirname(__DIR__) . '/config/config.php';

$fail = 0;
function hb_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function hb_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$tables = [
    'hotel_bookings_future', 'hotel_bookings_present', 'hotel_bookings_history',
    'booking_rooms_types', 'hotel_booking_housekeeping_statuses', 'hotel_booking_amenities', 'hotel_booking_hotels',
    'hotel_booking_rooms', 'hotel_bookings', 'hotel_booking_settings',
];
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    if ($res && mysqli_num_rows($res) > 0) {
        hb_pass("table {$t}");
    } else {
        hb_fail("missing table {$t}");
    }
}

$seg = itm_hotel_booking_resolve_segment('2026-12-01', '2026-12-05', '2026-07-29');
if ($seg === 'future') {
    hb_pass('segment future');
} else {
    hb_fail('segment expected future got ' . $seg);
}

$pending = itm_hotel_booking_status_id_by_name($conn, 1, 'hotel_bookings_future', 'PENDING');
if ($pending) {
    hb_pass('PENDING status company 1');
} else {
    hb_fail('PENDING status missing');
}

if (itm_hotel_booking_customer_last_name_matches('John Smith', 'smith') && !itm_hotel_booking_customer_last_name_matches('John Smith', 'Jones')) {
    hb_pass('guest last name match helper');
} else {
    hb_fail('guest last name match helper');
}

$quote = itm_hotel_booking_portal_quote_nightly(100, ['rooms' => 1, 'adults' => 2, 'children' => 1, 'babies' => 0], 0);
if (abs($quote - 122.0) < 0.01) {
    hb_pass('portal quote nightly child supplement');
} else {
    hb_fail('portal quote expected 122 got ' . $quote);
}

$label = itm_hotel_booking_portal_occupancy_label(['rooms' => 2, 'adults' => 2, 'children' => 1, 'babies' => 1]);
if (strpos($label, '2 rooms') !== false && strpos($label, '1 baby') !== false) {
    hb_pass('portal occupancy label');
} else {
    hb_fail('portal occupancy label unexpected: ' . $label);
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'hotel_booking_special_rates'");
if ($res && mysqli_num_rows($res) > 0) {
    hb_pass('table hotel_booking_special_rates');
} else {
    hb_fail('missing table hotel_booking_special_rates');
}

$occParsed = itm_hotel_booking_portal_parse_occupancy([
    'rooms' => 1,
    'adults' => 2,
    'aaa_rate' => '1',
    'promo_code' => 'ab-12!xy',
    'member_account' => 'toolongcode',
]);
if (!empty($occParsed['aaa_rate']) && $occParsed['promo_code'] === 'AB12XY' && $occParsed['member_account'] === 'TOOLONGC') {
    hb_pass('portal special rate parse sanitize');
} else {
    hb_fail('portal special rate parse sanitize');
}

$resolvedAaa = itm_hotel_booking_portal_resolved_rate_slug(['aaa_rate' => 1]);
$resolvedPromo = itm_hotel_booking_portal_resolved_rate_slug(['promo_code' => 'SAVE10']);
if ($resolvedAaa === 'aaa' && $resolvedPromo === 'promo') {
    hb_pass('portal resolved rate slug');
} else {
    hb_fail('portal resolved rate slug got aaa=' . $resolvedAaa . ' promo=' . $resolvedPromo);
}

$exclusive = itm_hotel_booking_portal_enforce_exclusive_rate_checkboxes([
    'use_points' => 1,
    'aaa_rate' => 1,
    'senior_rate' => 1,
]);
if (!empty($exclusive['use_points']) && empty($exclusive['aaa_rate']) && empty($exclusive['senior_rate'])) {
    hb_pass('portal exclusive rate checkboxes');
} else {
    hb_fail('portal exclusive rate checkboxes');
}

if (itm_hotel_booking_normalize_special_rate_percent_input('15,5') === 15.5) {
    hb_pass('special rate percent normalize');
} else {
    hb_fail('special rate percent normalize');
}

if (abs(itm_hotel_booking_portal_breakfast_supplement_per_night(['adults' => 2, 'children' => 1]) - 80.0) < 0.01) {
    hb_pass('portal breakfast supplement per night');
} else {
    hb_fail('portal breakfast supplement per night');
}

$upgradeDraft = [
    'base_price_per_night' => 100,
    'rate_plan' => 'room_only',
    'traveling_with_pet' => 0,
    'upgrade_accepted' => 1,
    'upgrade_price_per_night' => 121.0,
];
$upgradeTotal = itm_hotel_booking_portal_compute_checkout_total(100, '2026-08-01', '2026-08-03', ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0], 0, $upgradeDraft);
$expectedUpgradeTotal = itm_hotel_booking_compute_stay_payment(100, '2026-08-01', '2026-08-03', ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0], 0) + 121.0 * 2;
if (abs($upgradeTotal - $expectedUpgradeTotal) < 0.02) {
    hb_pass('portal checkout total with room upgrade supplement');
} else {
    hb_fail('portal checkout upgrade total expected ' . $expectedUpgradeTotal . ' got ' . $upgradeTotal);
}

$resCol = mysqli_query($conn, "SHOW COLUMNS FROM booking_rooms_types LIKE 'upgrade_price_per_night'");
if ($resCol && mysqli_num_rows($resCol) > 0) {
    hb_pass('booking_rooms_types upgrade columns');
    $resStd = mysqli_query($conn, "SELECT t.upgrade_price_per_night FROM booking_rooms_types t WHERE t.company_id = 1 AND t.code = 'STD' AND t.deleted_at IS NULL LIMIT 1");
    if ($resStd && ($rowStd = mysqli_fetch_assoc($resStd)) && abs((float) ($rowStd['upgrade_price_per_night'] ?? 0) - 121.0) < 0.01) {
        hb_pass('seed STD upgrade price 121');
    } else {
        hb_fail('seed STD upgrade_price_per_night expected 121');
    }
} else {
    hb_fail('missing booking_rooms_types.upgrade_price_per_night (apply db/migrations/booking_rooms_types_upgrade.sql or re-import db/)');
}

$taxSample = itm_hotel_booking_portal_tourist_tax_amount(['adults' => 2, 'children' => 0], 1, 2.0);
if (abs($taxSample - 4.0) < 0.01) {
    hb_pass('portal tourist tax amount');
} else {
    hb_fail('portal tourist tax amount');
}

if (itm_hotel_booking_portal_tourist_tax_per_person_from_settings([]) === 2.0) {
    hb_pass('portal tourist tax default 2 when settings missing');
} else {
    hb_fail('portal tourist tax default');
}

$breakdownSample = itm_hotel_booking_portal_checkout_breakdown(781.0, '2026-07-29', '2026-07-30', ['adults' => 2, 'children' => 0], 0, ['rate_plan' => 'breakfast'], 2.0);
if (abs((float) ($breakdownSample['room_charges'] ?? 0) - 841.0) < 0.01 && abs((float) ($breakdownSample['tourist_tax'] ?? 0) - 4.0) < 0.01 && abs((float) ($breakdownSample['total'] ?? 0) - 845.0) < 0.01) {
    hb_pass('portal checkout breakdown with tourist tax');
} else {
    hb_fail('portal checkout breakdown with tourist tax');
}

if (itm_hotel_booking_portal_validate_guest_email('guest@example.com') && !itm_hotel_booking_portal_validate_guest_email('not-an-email')) {
    hb_pass('portal guest email validation');
} else {
    hb_fail('portal guest email validation');
}

if (itm_hotel_booking_portal_validate_guest_phone('+351912345678') && !itm_hotel_booking_portal_validate_guest_phone('91234')) {
    hb_pass('portal guest phone validation');
} else {
    hb_fail('portal guest phone validation');
}

if (itm_hotel_booking_portal_normalize_guest_phone('351 912 345 678') === '+351912345678') {
    hb_pass('portal guest phone normalize');
} else {
    hb_fail('portal guest phone normalize');
}

$occMeta = itm_hotel_booking_portal_occupancy_meta_line(['rooms' => 2, 'adults' => 2, 'children' => 1, 'babies' => 1]);
$notesBuilt = itm_hotel_booking_portal_build_booking_notes([
    'rate_plan' => 'breakfast',
    'service_animal' => 1,
    'upgrade_accepted' => 1,
    'upgrade_target_name' => 'King Grand Deluxe Room with Pool View',
    'additional_comments' => 'Late arrival',
], ['rooms' => 2, 'adults' => 2, 'children' => 1, 'babies' => 1]);
$parsedOcc = itm_hotel_booking_portal_parse_occupancy_meta_from_notes($notesBuilt);
if (strpos($notesBuilt, $occMeta) === 0
    && strpos($notesBuilt, 'Rate: Breakfast included') !== false
    && strpos($notesBuilt, 'Rate plan: breakfast') !== false
    && strpos($notesBuilt, 'Room: King Grand Deluxe Room with Pool View') !== false
    && strpos($notesBuilt, "Guest comments:\nLate arrival") !== false
    && is_array($parsedOcc)
    && (int) ($parsedOcc['rooms'] ?? 0) === 2
    && (int) ($parsedOcc['children'] ?? 0) === 1) {
    hb_pass('portal booking notes with occupancy meta');
} else {
    hb_fail('portal booking notes with occupancy meta');
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'hotel_booking_portal_rate_plans'");
if ($res && mysqli_num_rows($res) > 0) {
    hb_pass('table hotel_booking_portal_rate_plans');
} else {
    hb_fail('missing table hotel_booking_portal_rate_plans');
}

$ratePlanParsed = itm_hotel_booking_portal_parse_rate_plan_from_notes("Rate: Breakfast included\nRate plan: breakfast");
if ($ratePlanParsed === 'breakfast') {
    hb_pass('parse rate plan from notes breakfast');
} else {
    hb_fail('parse rate plan from notes breakfast');
}

$policyUrl = itm_hotel_booking_portal_resolve_cancellation_policy_url($conn, 1, 1, 'room_only');
if ($policyUrl !== '' && strpos($policyUrl, '1_cancellation_policy.html') !== false) {
    hb_pass('resolve cancellation policy url room_only');
} else {
    hb_fail('resolve cancellation policy url room_only got ' . $policyUrl);
}

$futureBooking = [
    'check_in' => date('Y-m-d', strtotime('+14 days')),
    'check_out' => date('Y-m-d', strtotime('+16 days')),
    'future_status_id' => 1,
    'present_status_id' => null,
    'history_status_id' => null,
];
if (itm_hotel_booking_portal_guest_can_cancel_booking($conn, 1, $futureBooking)) {
    hb_pass('portal guest can cancel future booking');
} else {
    hb_fail('portal guest can cancel future booking');
}

$pastBooking = [
    'check_in' => '2020-01-01',
    'check_out' => '2020-01-03',
    'future_status_id' => null,
    'present_status_id' => null,
    'history_status_id' => 1,
];
if (!itm_hotel_booking_portal_guest_can_cancel_booking($conn, 1, $pastBooking)) {
    hb_pass('portal guest cannot cancel past booking');
} else {
    hb_fail('portal guest cannot cancel past booking');
}

$confirmPdfJs = dirname(__DIR__) . '/booking/js/hotel-booking-confirmation-pdf.js';
if (is_file($confirmPdfJs) && strpos((string) file_get_contents($confirmPdfJs), 'hbSaveBookingConfirmationPdf') !== false) {
    hb_pass('booking confirmation pdf download script');
} else {
    hb_fail('booking confirmation pdf download script missing');
}

exit($fail > 0 ? 1 : 0);
