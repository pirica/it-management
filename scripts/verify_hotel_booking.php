<?php
/**
 * CLI/browser regression: hotel booking schema helpers and tenant seeds.
 *
 * Browser: scripts/verify_hotel_booking.php?run=1 (Administrator).
 * CLI: php scripts/verify_hotel_booking.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_hotel_booking.php?run=1">verify_hotel_booking.php?run=1</a> (Administrator). CLI: <code>php scripts/verify_hotel_booking.php</code> — exit <code>1</code> on failure.
<p>Regression for <code>hotel_booking_*</code> / <code>booking_rooms_types</code> tables, segment helpers, portal quote/occupancy, hospitality module render probes, and stay-date <code>d/M/Y</code> contracts.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_verify_db_migrations_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Hotel booking verification');

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
    'hotel_booking_rooms', 'hotel_bookings', 'hotel_booking_last_rooms', 'hotel_booking_settings',
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

$allow = itm_hotel_booking_planning_view_allowlists();
if (isset($allow['arrivals']['present'], $allow['future']['future'])
    && in_array('DUE-IN', $allow['arrivals']['present'], true)
    && in_array('PENDING', $allow['future']['future'], true)
) {
    hb_pass('planning view allowlists');
} else {
    hb_fail('planning view allowlists missing arrivals/future maps');
}

$visFuture = [
    'check_in' => '2099-01-10',
    'check_out' => '2099-01-12',
    'future_status_id' => $pending,
    'present_status_id' => 0,
    'history_status_id' => 0,
];
if (itm_hotel_booking_planning_booking_visible($conn, 1, $visFuture, '2099-01-01', 'future', [])
    && !itm_hotel_booking_planning_booking_visible($conn, 1, $visFuture, '2099-01-01', 'arrivals', [])
) {
    hb_pass('planning Future/Arrivals date filters');
} else {
    hb_fail('planning Future/Arrivals date filters');
}

$hideTok = itm_hotel_booking_planning_sanitize_hide_names(['cancelled', 'bogus', 'NO-SHOW']);
if ($hideTok === ['CANCELLED', 'NO-SHOW']) {
    hb_pass('planning hide token sanitize');
} else {
    hb_fail('planning hide token sanitize');
}

$emptyRoom = itm_hotel_booking_planning_unassigned_room_row();
if ((int) ($emptyRoom['id'] ?? -1) === 0) {
    hb_pass('planning unassigned empty row');
} else {
    hb_fail('planning unassigned empty row');
}

if (itm_hotel_booking_customer_last_name_matches('John Smith', 'smith') && !itm_hotel_booking_customer_last_name_matches('John Smith', 'Jones')) {
    hb_pass('guest last name match helper');
} else {
    hb_fail('guest last name match helper');
}

$auth2Sample = itm_hotel_booking_generate_auth2();
$auth2Strong = strlen($auth2Sample) === 12
    && preg_match('/[A-Z]/', $auth2Sample)
    && preg_match('/[a-z]/', $auth2Sample)
    && preg_match('/[0-9]/', $auth2Sample)
    && preg_match('/[!@#$%&*?]/', $auth2Sample);
if ($auth2Strong
    && itm_hotel_booking_normalize_auth2('1234') === '1234'
    && itm_hotel_booking_normalize_auth2('Ab#3699cD@eF') === 'Ab#3699cD@eF'
    && itm_hotel_booking_normalize_auth2($auth2Sample) === $auth2Sample
    && itm_hotel_booking_auth2_matches('0042', '42') === false
    && itm_hotel_booking_auth2_matches('0042', '0042') === false
    && itm_hotel_booking_auth2_matches($auth2Sample, $auth2Sample)) {
    hb_pass('guest auth2 generate/normalize/match');
} else {
    hb_fail('guest auth2 generate/normalize/match got ' . $auth2Sample);
}

$resGuestCode = mysqli_query($conn, "SHOW COLUMNS FROM hotel_bookings LIKE 'guest_confirmation_code'");
if ($resGuestCode && mysqli_fetch_assoc($resGuestCode)) {
    itm_hotel_booking_portal_backfill_legacy_auth2_groups($conn);
    itm_hotel_booking_portal_backfill_guest_confirmation_codes($conn);
    $sampleCode = itm_hotel_booking_generate_guest_confirmation_code($conn, 1);
    if (strlen($sampleCode) === 10 && itm_hotel_booking_normalize_guest_confirmation_code($sampleCode) === $sampleCode) {
        hb_pass('guest confirmation code generate/normalize');
    } else {
        hb_fail('guest confirmation code generate/normalize got ' . $sampleCode);
    }
    $legacyLeft = mysqli_query($conn, "SELECT COUNT(*) AS c FROM hotel_bookings WHERE deleted_at IS NULL AND auth2 REGEXP '^[0-9]{4}$'");
    $legacyCount = ($legacyLeft && ($lr = mysqli_fetch_assoc($legacyLeft))) ? (int) ($lr['c'] ?? 0) : -1;
    if ($legacyCount === 0) {
        hb_pass('legacy auth2 retired from hotel_bookings');
    } else {
        hb_fail('legacy auth2 rows remain: ' . $legacyCount);
    }
} else {
    hb_fail('hotel_bookings.guest_confirmation_code column missing — apply db/migrations/hotel_bookings_guest_confirmation_code.sql');
}

$resAuth2Type = mysqli_query($conn, "SHOW COLUMNS FROM hotel_bookings LIKE 'auth2'");
if ($resAuth2Type && ($auth2Col = mysqli_fetch_assoc($resAuth2Type)) && stripos((string) ($auth2Col['Type'] ?? ''), 'varchar(12)') !== false) {
    hb_pass('hotel_bookings.auth2 varchar(12) column');
} else {
    hb_fail('hotel_bookings.auth2 must be varchar(12) — apply db/migrations/hotel_bookings_auth2_strong.sql');
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

$resCodesTable = mysqli_query($conn, "SHOW TABLES LIKE 'hotel_booking_special_rate_codes'");
if ($resCodesTable && mysqli_num_rows($resCodesTable) > 0) {
    hb_pass('table hotel_booking_special_rate_codes');
} else {
    hb_fail('missing table hotel_booking_special_rate_codes — apply db/migrations/hotel_booking_special_rate_codes.sql');
}

$bogusFilter = itm_hotel_booking_portal_filter_occupancy_special_rate_codes($conn, 1, 1, ['promo_code' => 'BOGUS99'], date('Y-m-d'));
if (empty($bogusFilter['occupancy']['promo_code']) && !empty($bogusFilter['rejected'])) {
    hb_pass('portal special rate code rejects unregistered code');
} else {
    hb_fail('portal special rate code should reject unregistered code');
}

if (itm_hotel_booking_portal_special_rate_code_is_valid($conn, 1, 1, 'promo', 'SAVE10', date('Y-m-d'))) {
    hb_pass('portal special rate code SAVE10 valid when seeded');
} else {
    hb_fail('portal special rate code SAVE10 should be valid for company 1 hotel 1 after migration/seeds');
}

if (!itm_hotel_booking_portal_special_rate_code_is_valid($conn, 1, 1, 'promo', 'NOTACODE', date('Y-m-d'))) {
    hb_pass('portal special rate code unknown code invalid');
} else {
    hb_fail('portal special rate unknown code should be invalid');
}

if (abs(itm_hotel_booking_portal_breakfast_supplement_per_night(['adults' => 2, 'children' => 1]) - 80.0) < 0.01) {
    hb_pass('portal breakfast supplement per night');
} else {
    hb_fail('portal breakfast supplement per night');
}

$resPricingCol = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_hotels LIKE 'portal_breakfast_adult_price_per_night'");
if ($resPricingCol && mysqli_num_rows($resPricingCol) > 0) {
    hb_pass('hotel_booking_hotels portal pricing columns');
    $pricingRow = itm_hotel_booking_portal_hotel_pricing($conn, 1, 1);
    if (is_array($pricingRow) && isset($pricingRow['breakfast_adult_price_per_night'])) {
        hb_pass('portal hotel pricing loader');
    } else {
        hb_fail('portal hotel pricing loader');
    }
} else {
    hb_fail('missing hotel_booking_hotels portal pricing columns — apply db/migrations/hotel_booking_portal_hotel_pricing.sql');
}

$resContactEmailCol = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_hotels LIKE 'contact_email'");
$resReservationsEmailCol = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_hotels LIKE 'reservations_email'");
if ($resContactEmailCol && mysqli_num_rows($resContactEmailCol) > 0 && $resReservationsEmailCol && mysqli_num_rows($resReservationsEmailCol) > 0) {
    hb_pass('hotel_booking_hotels contact and reservations email columns');
    $seededHotelsWithEmails = 0;
    $hotelEmailRes = mysqli_query($conn, "SELECT id FROM hotel_booking_hotels WHERE deleted_at IS NULL AND contact_email IS NOT NULL AND contact_email <> '' AND reservations_email IS NOT NULL AND reservations_email <> '' AND company_id BETWEEN 1 AND 5");
    if ($hotelEmailRes) {
        $seededHotelsWithEmails = mysqli_num_rows($hotelEmailRes);
    }
    if ($seededHotelsWithEmails >= 5) {
        hb_pass('hotel_booking_hotels sample contact/reservations emails for five properties');
    } else {
        hb_fail('expected contact_email and reservations_email on five seeded hotels — found ' . (int) $seededHotelsWithEmails);
    }
} else {
    hb_fail('missing hotel_booking_hotels contact_email or reservations_email — apply db/migrations/hotel_booking_hotels_contact_emails.sql');
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

$bpStd = mysqli_query($conn, "SELECT bp.price_per_night, bp.hotel_id, bp.room_type_id FROM hotel_booking_room_type_base_prices bp INNER JOIN booking_rooms_types t ON t.id = bp.room_type_id AND t.company_id = bp.company_id WHERE bp.company_id = 1 AND bp.hotel_id = 1 AND t.code = 'STD' AND bp.deleted_at IS NULL AND t.deleted_at IS NULL LIMIT 1");
if ($bpStd && ($bpRow = mysqli_fetch_assoc($bpStd)) && abs((float) ($bpRow['price_per_night'] ?? 0) - 75.0) < 0.01) {
    $stdTypeId = (int) ($bpRow['room_type_id'] ?? 0);
    $stdHotelId = (int) ($bpRow['hotel_id'] ?? 1);
    $checkDay = date('Y-m-d', strtotime('+14 days'));
    $stdBar = itm_hotel_booking_portal_check_in_display_bar($conn, 1, $stdHotelId, $stdTypeId, $checkDay, 75.0);
    $calOcc = ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0];
    $calMonth = itm_hotel_booking_hotel_calendar_month($conn, 1, $stdHotelId, (int) date('Y', strtotime($checkDay)), (int) date('n', strtotime($checkDay)), $calOcc);
    $calPrice = isset($calMonth['days'][$checkDay]['price']) ? (float) $calMonth['days'][$checkDay]['price'] : null;
    $calExcl = isset($calMonth['days'][$checkDay]['bar_excl_tax']) ? (float) $calMonth['days'][$checkDay]['bar_excl_tax'] : null;
    $taxOne = itm_hotel_booking_portal_tourist_tax_amount($calOcc, 1, 2.0);
    $cheapest = itm_hotel_booking_portal_cheapest_rate_offer_for_hotel($conn, 1, $stdHotelId);
    $planDisc = max(0.0, (float) ($cheapest['discount_percent'] ?? 0));
    $roomAfterPlan = round($stdBar * (1 - ($planDisc / 100)), 2);
    $expectedIncl = round($roomAfterPlan + $taxOne, 2);
    if (abs($stdBar - 75.0) < 0.01
        && $calExcl !== null && abs($calExcl - $stdBar) < 0.01
        && $calPrice !== null && abs($calPrice - $expectedIncl) < 0.01
        && !empty($calMonth['prices_include_tax'])
        && ($cheapest['slug'] ?? '') === 'non_refundable'
        && abs($planDisc - 10.0) < 0.01
    ) {
        hb_pass('calendar shows cheapest NR rate tax-inclusive (STD 75 -10% + tourist tax)');
    } else {
        hb_fail('calendar cheapest NR tax-inclusive expected ' . $expectedIncl . ' (stdBar=' . $stdBar . ' cal=' . json_encode($calPrice) . ' excl=' . json_encode($calExcl) . ' disc=' . $planDisc . ' slug=' . json_encode($cheapest['slug'] ?? null) . ')');
    }
    $room1 = mysqli_query($conn, 'SELECT r.id, r.room_type_id, COALESCE(bp.price_per_night, 0) AS price_per_night FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.company_id = 1 AND r.hotel_id = 1 AND r.id = 1 AND r.deleted_at IS NULL LIMIT 1');
    if ($room1 && ($r1 = mysqli_fetch_assoc($room1)) && (int) ($r1['room_type_id'] ?? 0) === $stdTypeId && abs((float) ($r1['price_per_night'] ?? 0) - 75.0) < 0.01) {
        hb_pass('seed room id 1 is STD calendar BAR room');
    } else {
        hb_fail('seed room id 1 must be STD at 75.00 to match calendar from-price');
    }
} else {
    hb_fail('hotel 1 STD base price expected 75.00');
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

$nrOffer = itm_hotel_booking_portal_rate_plan_offer('non_refundable');
$flexOffer = itm_hotel_booking_portal_rate_plan_offer('flexible');
$surOffer = itm_hotel_booking_portal_rate_plan_offer('flexible', ['plan_surcharge_percent' => 2]);
$quotedSur = itm_hotel_booking_portal_quote_nightly(100.0, ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0], 0, null, 2.0);
if (($nrOffer['pay_badge'] ?? '') === 'Non-refundable'
    && abs((float) ($nrOffer['discount_percent'] ?? 0) - 10.0) < 0.01
    && ($flexOffer['price_label'] ?? '') === 'Flexible rate'
    && abs(itm_hotel_booking_portal_rate_plan_effective_discount(0, 'non_refundable') - 10.0) < 0.01
    && abs((float) ($surOffer['surcharge_percent'] ?? 0) - 2.0) < 0.01
    && abs(itm_hotel_booking_portal_rate_plan_effective_surcharge('flexible', ['plan_surcharge_percent' => 2]) - 2.0) < 0.01
    && abs($quotedSur - 102.0) < 0.01
) {
    hb_pass('portal rate plan offer labels, NR discount, and plan surcharge');
} else {
    hb_fail('portal rate plan offer labels, NR discount, and plan surcharge');
}

$colSur = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_portal_rate_plans LIKE 'plan_surcharge_percent'");
if ($colSur && mysqli_num_rows($colSur) === 1) {
    hb_pass('hotel_booking_portal_rate_plans.plan_surcharge_percent column');
} else {
    hb_fail('hotel_booking_portal_rate_plans.plan_surcharge_percent column missing');
}

$editRatePlanSrc = is_file(dirname(__DIR__) . '/modules/hotel_booking_portal_rate_plans/edit.php') ? (string) file_get_contents(dirname(__DIR__) . '/modules/hotel_booking_portal_rate_plans/edit.php') : '';
$selectRateSrcSur = is_file(dirname(__DIR__) . '/booking/rooms/select-rate.php') ? (string) file_get_contents(dirname(__DIR__) . '/booking/rooms/select-rate.php') : '';
if (strpos($editRatePlanSrc, 'plan_surcharge_percent') !== false
    && strpos($selectRateSrcSur, 'surcharge_percent') !== false
    && strpos((string) file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-select-room.js'), 'cheapestPlanSurchargePercent') !== false
) {
    hb_pass('admin + portal wire plan_surcharge_percent');
} else {
    hb_fail('admin + portal must wire plan_surcharge_percent');
}

if (itm_hotel_booking_portal_free_cancellation_days_from_settings([]) === 5
    && itm_hotel_booking_portal_free_cancellation_days_from_settings(['free_cancellation_days_before_check_in' => 7]) === 7
) {
    hb_pass('portal free cancellation days from settings');
} else {
    hb_fail('portal free cancellation days from settings');
}

if (abs(itm_hotel_booking_portal_price_incl_tourist_tax(75.0, 2.0, ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0]) - 77.0) < 0.01) {
    hb_pass('portal from-price includes tourist tax');
} else {
    hb_fail('portal from-price includes tourist tax');
}

require_once dirname(__DIR__) . '/booking/includes/portal_chrome.php';
hb_portal_bind_money_settings(['portal_money_symbol' => 'EUR', 'portal_money_symbol_suffix' => 0, 'portal_money_symbol_prefix' => 1]);
if (hb_portal_money_format(69.5, 'EUR') === '€69.50' && hb_portal_money_format(77.0, 'EUR') === '€77') {
    hb_pass('portal money format keeps NR cents (69.50) without rounding to 70');
} else {
    hb_fail('portal money format expected €69.50 / €77 got ' . hb_portal_money_format(69.5, 'EUR') . ' / ' . hb_portal_money_format(77.0, 'EUR'));
}
unset($GLOBALS['hb_portal_money_settings']);

$datesJsPath = dirname(__DIR__) . '/booking/js/hotel-booking-dates.js';
$datesJsSrc = is_file($datesJsPath) ? (string) file_get_contents($datesJsPath) : '';
if (strpos($datesJsSrc, 'toFixed(2)') !== false && strpos($datesJsSrc, 'Math.round(parseFloat(amount) || 0)') === false) {
    hb_pass('Select Dates calendar formatMoney keeps cents');
} else {
    hb_fail('Select Dates calendar formatMoney must not Math.round whole euros');
}
if (strpos($datesJsSrc, 'monthAdvanceDaysLeftThreshold') !== false
    && strpos($datesJsSrc, 'calendar_month_advance_days_left') !== false
) {
    hb_pass('Select Dates auto-advance reads calendar_month_advance_days_left from settings');
} else {
    hb_fail('Select Dates must use monthAdvanceDaysLeftThreshold / calendar_month_advance_days_left');
}

if (function_exists('itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings')
    && itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings([]) === 3
    && itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings(['calendar_month_advance_days_left' => 0]) === 0
    && itm_hotel_booking_portal_calendar_month_advance_days_left_from_settings(['calendar_month_advance_days_left' => 7]) === 7
) {
    hb_pass('portal calendar month advance days helper');
} else {
    hb_fail('portal calendar month advance days helper');
}

$colAdv = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'calendar_month_advance_days_left'");
if ($colAdv && mysqli_num_rows($colAdv) === 1) {
    hb_pass('hotel_booking_settings.calendar_month_advance_days_left column');
} else {
    hb_fail('hotel_booking_settings.calendar_month_advance_days_left column missing');
}

$colStrike = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'show_discount_strikethrough'");
if ($colStrike && mysqli_num_rows($colStrike) === 1) {
    hb_pass('hotel_booking_settings.show_discount_strikethrough column');
} else {
    hb_fail('hotel_booking_settings.show_discount_strikethrough column missing');
}

if (function_exists('itm_hotel_booking_portal_show_discount_strikethrough_from_settings')
    && itm_hotel_booking_portal_show_discount_strikethrough_from_settings([]) === true
    && itm_hotel_booking_portal_show_discount_strikethrough_from_settings(['show_discount_strikethrough' => 1]) === true
    && itm_hotel_booking_portal_show_discount_strikethrough_from_settings(['show_discount_strikethrough' => 0]) === false
) {
    hb_pass('portal show_discount_strikethrough helper');
} else {
    hb_fail('portal show_discount_strikethrough helper');
}

$roomsPhpSrc = is_file(dirname(__DIR__) . '/booking/rooms.php') ? (string) file_get_contents(dirname(__DIR__) . '/booking/rooms.php') : '';
$selectRoomJsSrcCheck = is_file(dirname(__DIR__) . '/booking/js/hotel-booking-select-room.js') ? (string) file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-select-room.js') : '';
if (strpos($roomsPhpSrc, 'cheapestPlanDiscountPercent') !== false
    && strpos($roomsPhpSrc, 'displayDiscountPercent') !== false
    && strpos($selectRoomJsSrcCheck, 'cheapestPlanDiscountPercent') !== false
) {
    hb_pass('Step 1 rooms apply cheapest plan discount (STD From = NR)');
} else {
    hb_fail('Step 1 rooms must apply cheapestPlanDiscountPercent for STD From');
}

$settingsIndexSrc = is_file(dirname(__DIR__) . '/modules/hotel_booking_settings/index.php') ? (string) file_get_contents(dirname(__DIR__) . '/modules/hotel_booking_settings/index.php') : '';
$selectRateSrc = is_file(dirname(__DIR__) . '/booking/rooms/select-rate.php') ? (string) file_get_contents(dirname(__DIR__) . '/booking/rooms/select-rate.php') : '';
if (strpos($settingsIndexSrc, 'show_discount_strikethrough') !== false
    && strpos($roomsPhpSrc, 'showDiscountStrikethrough') !== false
    && strpos($selectRoomJsSrcCheck, 'showDiscountStrikethrough') !== false
    && strpos($selectRateSrc, 'showDiscountStrikethrough') !== false
) {
    hb_pass('admin + portal Step 1/2 wire show_discount_strikethrough');
} else {
    hb_fail('admin + portal Step 1/2 must wire show_discount_strikethrough');
}

$seedCancelDaysOk = true;
$seedAdvanceOk = true;
$seedMerchOk = true;
$seedStrikeOk = true;
$seedRes = mysqli_query($conn, 'SELECT company_id, free_cancellation_days_before_check_in, calendar_month_advance_days_left, show_discount_strikethrough FROM hotel_booking_settings WHERE company_id BETWEEN 1 AND 5 AND deleted_at IS NULL');
$seedCompanies = [];
while ($seedRes && ($sr = mysqli_fetch_assoc($seedRes))) {
    $seedCompanies[(int) $sr['company_id']] = (int) ($sr['free_cancellation_days_before_check_in'] ?? 0);
    if ((int) ($sr['free_cancellation_days_before_check_in'] ?? 0) !== 5) {
        $seedCancelDaysOk = false;
    }
    if ((int) ($sr['calendar_month_advance_days_left'] ?? -1) !== 3) {
        $seedAdvanceOk = false;
    }
    if ((int) ($sr['show_discount_strikethrough'] ?? 0) !== 1) {
        $seedStrikeOk = false;
    }
}
if (count($seedCompanies) < 5) {
    $seedCancelDaysOk = false;
    $seedAdvanceOk = false;
    $seedStrikeOk = false;
}
$merchRes = mysqli_query($conn, "SELECT company_id, COUNT(*) AS c FROM hotel_booking_portal_rate_plans WHERE company_id BETWEEN 1 AND 5 AND deleted_at IS NULL AND rate_plan_slug = 'flexible' AND cancel_template LIKE '%{date}%' GROUP BY company_id");
$merchCompanies = 0;
while ($merchRes && ($mr = mysqli_fetch_assoc($merchRes))) {
    $merchCompanies++;
    if ((int) ($mr['c'] ?? 0) < 1) {
        $seedMerchOk = false;
    }
}
if ($merchCompanies < 5) {
    $seedMerchOk = false;
}
if ($seedCancelDaysOk && $seedAdvanceOk && $seedStrikeOk && $seedMerchOk) {
    hb_pass('free cancel days + calendar advance + strikethrough + rate plan merchandising seeded for companies 1-5');
} else {
    hb_fail('free cancel days + calendar advance + strikethrough + rate plan merchandising seeded for companies 1-5');
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
    'traveling_with_pet' => 1,
    'upgrade_accepted' => 1,
    'upgrade_target_name' => 'King Grand Deluxe Room with Pool View',
    'upgrade_bed_summary' => '1 King bed',
    'upgrade_pitch' => 'You deserve a little extra. Enjoy a room with added perks.',
    'upgrade_price_per_night' => 121,
    'additional_comments' => 'Late arrival',
], ['rooms' => 2, 'adults' => 2, 'children' => 1, 'babies' => 1]);
$parsedOcc = itm_hotel_booking_portal_parse_occupancy_meta_from_notes($notesBuilt);
$parsedMeta = itm_hotel_booking_portal_parse_booking_notes_meta($notesBuilt);
if (strpos($notesBuilt, $occMeta) === 0
    && strpos($notesBuilt, 'Rate: Breakfast included') !== false
    && strpos($notesBuilt, 'Rate plan: breakfast') !== false
    && strpos($notesBuilt, 'Room upgrade: yes') !== false
    && strpos($notesBuilt, 'Room upgrade title: King Grand Deluxe Room with Pool View 1 King bed') !== false
    && strpos($notesBuilt, 'Traveling with pet: yes') !== false
    && strpos($notesBuilt, 'Room: King Grand Deluxe Room with Pool View') !== false
    && strpos($notesBuilt, "Guest comments:\nLate arrival") !== false
    && is_array($parsedOcc)
    && (int) ($parsedOcc['rooms'] ?? 0) === 2
    && (int) ($parsedOcc['children'] ?? 0) === 1
    && !empty($parsedMeta['traveling_with_pet'])
    && !empty($parsedMeta['service_animal'])
    && !empty($parsedMeta['room_upgrade']['accepted'])
    && (float) ($parsedMeta['room_upgrade']['per_night'] ?? 0) === 121.0
    && trim((string) ($parsedMeta['guest_comments'] ?? '')) === 'Late arrival') {
    hb_pass('portal booking notes with occupancy meta');
} else {
    hb_fail('portal booking notes with occupancy meta');
}

$accessNotes = itm_hotel_booking_portal_build_booking_notes([
    'accessibility_need' => 'mobility',
    'accessibility_pep_acknowledged' => 1,
]);
$accessParsed = itm_hotel_booking_portal_parse_booking_notes_meta($accessNotes);
if (strpos($accessNotes, 'Accessibility need: Mobility impairments') !== false
    && strpos($accessNotes, 'Accessibility PEP acknowledged: yes') !== false
    && ($accessParsed['accessibility_need'] ?? '') === 'mobility'
    && !empty($accessParsed['accessibility_pep_acknowledged'])
    && itm_hotel_booking_portal_accessibility_pep_required('mobility')
    && !itm_hotel_booking_portal_accessibility_pep_required('none')
    && itm_hotel_booking_portal_room_is_accessible(['accessible_room' => 1])
    && !itm_hotel_booking_portal_room_is_accessible(['accessible_room' => 0, 'type_accessible_room' => 0])) {
    hb_pass('portal accessibility need notes and room flag');
} else {
    hb_fail('portal accessibility need notes and room flag');
}

$seedAccessibleConnectingOk = true;
for ($seedCompanyId = 1; $seedCompanyId <= 5; $seedCompanyId++) {
    $seedRoomsByNumber = [];
    $seedRoomRes = mysqli_query(
        $conn,
        'SELECT id, room_number, accessible_room, connected_to, connecting_room_id FROM hotel_booking_rooms WHERE company_id = '
        . (int) $seedCompanyId
        . " AND deleted_at IS NULL AND room_number IN ('101','201','202')"
    );
    while ($seedRoomRes && ($seedRoomRow = mysqli_fetch_assoc($seedRoomRes))) {
        $seedRoomsByNumber[(string) ($seedRoomRow['room_number'] ?? '')] = $seedRoomRow;
    }
    if (!isset($seedRoomsByNumber['101']) || (int) ($seedRoomsByNumber['101']['accessible_room'] ?? 0) !== 1) {
        $seedAccessibleConnectingOk = false;
        break;
    }
    if (!isset($seedRoomsByNumber['201'], $seedRoomsByNumber['202'])) {
        $seedAccessibleConnectingOk = false;
        break;
    }
    $seedId201 = (int) ($seedRoomsByNumber['201']['id'] ?? 0);
    $seedId202 = (int) ($seedRoomsByNumber['202']['id'] ?? 0);
    if ($seedId201 < 1 || $seedId202 < 1
        || (string) ($seedRoomsByNumber['201']['connected_to'] ?? '') !== '202'
        || (string) ($seedRoomsByNumber['202']['connected_to'] ?? '') !== '201'
        || (int) ($seedRoomsByNumber['201']['connecting_room_id'] ?? 0) !== $seedId202
        || (int) ($seedRoomsByNumber['202']['connecting_room_id'] ?? 0) !== $seedId201) {
        $seedAccessibleConnectingOk = false;
        break;
    }
}
if ($seedAccessibleConnectingOk) {
    hb_pass('seed hotel_booking_rooms accessible 101 + connecting 201/202 all companies');
} else {
    hb_fail('seed hotel_booking_rooms accessible 101 + connecting 201/202 all companies');
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
if ($policyUrl !== '' && strpos($policyUrl, 'cancellation-policy.php') !== false && strpos($policyUrl, 'slug=room_only') !== false) {
    hb_pass('resolve cancellation policy url room_only');
} else {
    hb_fail('resolve cancellation policy url room_only got ' . $policyUrl);
}

$blockedPhpPolicy = itm_hotel_booking_normalize_cancellation_policy_url('cancellation_policy/evil.php');
$allowedHtmlPolicy = itm_hotel_booking_normalize_cancellation_policy_url('cancellation_policy/policy.html');
$allowedTxtPolicy = itm_hotel_booking_normalize_cancellation_policy_url('cancellation_policy/notes.txt');
if ($blockedPhpPolicy === '' && $allowedHtmlPolicy === 'cancellation_policy/policy.html' && $allowedTxtPolicy === 'cancellation_policy/notes.txt') {
    hb_pass('cancellation policy url extension allowlist');
} else {
    hb_fail('cancellation policy url extension allowlist');
}
if (!is_file(dirname(__DIR__) . '/booking/cancellation_policy/.htaccess')) {
    hb_fail('booking/cancellation_policy/.htaccess missing');
} else {
    hb_pass('booking cancellation_policy htaccess present');
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

$presentBooking = [
    'check_in' => date('Y-m-d', strtotime('-1 day')),
    'check_out' => date('Y-m-d', strtotime('+14 days')),
    'future_status_id' => null,
    'present_status_id' => 1,
    'history_status_id' => null,
];
if (itm_hotel_booking_portal_guest_can_cancel_booking($conn, 1, $presentBooking)) {
    hb_pass('portal guest can cancel present-segment booking');
} else {
    hb_fail('portal guest can cancel present-segment booking');
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

if (!itm_hotel_booking_portal_guest_can_cancel_booking($conn, 1, $pastBooking)) {
    hb_pass('portal guest cannot cancel past booking');
} else {
    hb_fail('portal guest cannot cancel past booking');
}

require dirname(__DIR__) . '/booking/includes/portal_checkout.php';
$contactsSample = hb_portal_hotel_contacts_from_booking([
    'hotel_name' => 'TechCorp Retreat',
    'hotel_location' => 'Lisbon, Portugal',
    'hotel_phone' => '+351 210 000 001',
    'hotel_website_url' => 'https://example.com/techcorp-retreat',
    'hotel_contact_email' => 'info@techcorp-retreat.example',
    'hotel_reservations_email' => 'reservations@techcorp-retreat.example',
]);
if ($contactsSample['name'] === 'TechCorp Retreat'
    && $contactsSample['phone'] === '+351 210 000 001'
    && $contactsSample['contact_email'] === 'info@techcorp-retreat.example'
    && $contactsSample['reservations_email'] === 'reservations@techcorp-retreat.example'
    && hb_portal_hotel_mailto_href($contactsSample['reservations_email']) === 'mailto:reservations@techcorp-retreat.example'
    && hb_portal_hotel_mailto_href($contactsSample['contact_email']) === 'mailto:info@techcorp-retreat.example'
    && strpos(hb_portal_hotel_directions_url($contactsSample['location']), 'maps.google.com') !== false
    && hb_portal_hotel_phone_tel_href($contactsSample['phone']) === 'tel:+351210000001') {
    hb_pass('portal change booking hotel contacts helpers');
} else {
    hb_fail('portal change booking hotel contacts helpers');
}

$publicJsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-public.js');
if (strpos($publicJsSrc, 'contact_email') !== false
    && strpos($publicJsSrc, 'reservations_email') !== false
    && strpos($publicJsSrc, "hbUiCopy('home_info_link'") !== false
    && strpos($publicJsSrc, "hbUiCopy('home_email_link'") !== false) {
    hb_pass('public hotel details Info/Email contact links');
} else {
    hb_fail('public hotel details missing Info (contact_email) / Email (reservations_email) links');
}

$portalCheckoutSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/includes/portal_checkout.php');
if (strpos($portalCheckoutSrc, 'hb_portal_render_hotel_action_links') !== false
    && strpos($portalCheckoutSrc, 'hotel_contact_email') !== false
    && strpos($portalCheckoutSrc, 'hotel_reservations_email') !== false) {
    hb_pass('portal checkout hotel Info/Email wiring');
} else {
    hb_fail('portal checkout hotel Info/Email wiring missing');
}

$cancelledDisplay = hb_portal_booking_display_is_cancelled([
    'check_in' => date('Y-m-d', strtotime('+14 days')),
    'check_out' => date('Y-m-d', strtotime('+16 days')),
    'future_status_id' => 99,
], ['is_cancelled' => true]);
if ($cancelledDisplay && !hb_portal_booking_display_is_cancelled(['check_in' => '2026-01-01', 'check_out' => '2026-01-03'], ['is_cancelled' => false])) {
    hb_pass('portal booking display is cancelled helper');
} else {
    hb_fail('portal booking display is cancelled helper');
}

$changeBookingJs = dirname(__DIR__) . '/booking/js/hotel-booking-change-booking.js';
if (is_file($changeBookingJs) && strpos((string) file_get_contents($changeBookingJs), 'hb-change-booking-modal') !== false) {
    hb_pass('change booking modal script');
} else {
    hb_fail('change booking modal script missing');
}

$planningJs = dirname(__DIR__) . '/modules/hotel_bookings/js/hotel-bookings-planning.js';
$planningJsSrc = is_file($planningJs) ? (string) file_get_contents($planningJs) : '';
if ($planningJsSrc !== ''
    && strpos($planningJsSrc, 'planning_move') !== false
    && strpos($planningJsSrc, 'hb-plan-draggable') !== false
    && strpos($planningJsSrc, 'openHkMaintModal') !== false
    && strpos($planningJsSrc, 'getRoomViewBase') !== false
    && strpos($planningJsSrc, 'td.hb-plan-room-col') !== false) {
    hb_pass('planning grid drag-drop script');
} else {
    hb_fail('planning grid drag-drop script missing');
}

if (function_exists('itm_hotel_booking_planning_move_booking') && function_exists('itm_hotel_booking_planning_move_maintenance')) {
    hb_pass('planning move helpers');
} else {
    hb_fail('planning move helpers missing');
}

$resCol = mysqli_query($conn, "SHOW COLUMNS FROM hotel_bookings LIKE 'portal_rate_plan_id'");
if ($resCol && mysqli_num_rows($resCol) > 0) {
    hb_pass('hotel_bookings portal_rate_plan_id column');
} else {
    hb_fail('missing hotel_bookings.portal_rate_plan_id');
}

if (function_exists('itm_hotel_booking_portal_rate_plans_active_for_hotel') && function_exists('itm_hotel_booking_portal_resolve_cancellation_policy_url_for_booking')) {
    hb_pass('portal rate plan booking FK helpers');
} else {
    hb_fail('portal rate plan booking FK helpers missing');
}

if (function_exists('itm_hotel_booking_portal_rate_plan_hard_delete')) {
    hb_pass('portal rate plan hard delete helper');
} else {
    hb_fail('portal rate plan hard delete helper missing');
}

if (function_exists('itm_hotel_booking_portal_rate_plan_slot_in_use')
    && function_exists('itm_hotel_booking_portal_rate_plan_next_free_slot')
    && function_exists('itm_hotel_booking_portal_rate_plan_create')) {
    hb_pass('portal rate plan create and slot helpers');
} else {
    hb_fail('portal rate plan create and slot helpers missing');
}

$resHotel = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = 1 AND deleted_at IS NULL LIMIT 1');
$hotelProbe = $resHotel ? mysqli_fetch_assoc($resHotel) : null;
if ($hotelProbe) {
    $probeHotelId = (int) ($hotelProbe['id'] ?? 0);
    itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, 1, $probeHotelId, 1);
    $nextFreeSlot = itm_hotel_booking_portal_rate_plan_next_free_slot($conn, 1, $probeHotelId);
    if ($nextFreeSlot >= 5 && $nextFreeSlot <= 127) {
        hb_pass('portal rate plan next free slot after defaults');
    } elseif ($nextFreeSlot >= 1 && $nextFreeSlot <= 127) {
        hb_pass('portal rate plan next free slot');
    } else {
        hb_fail('portal rate plan next free slot invalid: ' . $nextFreeSlot);
    }
    if (itm_hotel_booking_portal_rate_plan_slot_in_use($conn, 1, $probeHotelId, 1)) {
        hb_pass('portal rate plan slot in use for slot 1');
    } else {
        hb_fail('portal rate plan slot 1 not in use after ensure');
    }
} else {
    hb_fail('no hotel row for portal rate plan slot probe');
}

if (function_exists('itm_hospitality_render_bookings_hub_link') && is_file(dirname(__DIR__) . '/modules/hotel_booking_portal_rate_plans/delete.php')) {
    hb_pass('hospitality bookings hub link + rate plan delete.php');
} else {
    hb_fail('hospitality bookings hub link or rate plan delete.php missing');
}

if (function_exists('itm_hospitality_render_list_create_and_hub')) {
    hb_pass('hospitality list create and hub stack helper');
} else {
    hb_fail('hospitality list create and hub stack helper missing');
}

require_once dirname(__DIR__) . '/modules/hotel_bookings/includes/hb_booking_form.php';
$ratePlanFormFailures = hb_booking_rate_plan_form_audit_failures(dirname(__DIR__));
if (empty($ratePlanFormFailures)) {
    hb_pass('hotel bookings portal rate plan form contract');
} else {
    hb_fail('hotel bookings portal rate plan form: ' . implode('; ', $ratePlanFormFailures));
}

$bookingConfirmationPdfJs = dirname(__DIR__) . '/booking/js/hotel-booking-confirmation-pdf.js';
$bookingConfirmationPdfJsBody = is_file($bookingConfirmationPdfJs) ? (string) file_get_contents($bookingConfirmationPdfJs) : '';
if (
    $bookingConfirmationPdfJsBody !== ''
    && strpos($bookingConfirmationPdfJsBody, 'hbSaveBookingConfirmationPdf') !== false
    && strpos($bookingConfirmationPdfJsBody, 'hbPdfAddManageBookingLink') !== false
    && strpos($bookingConfirmationPdfJsBody, 'pdf.link') !== false
) {
    hb_pass('booking confirmation pdf download script');
} else {
    hb_fail('booking confirmation pdf download script missing Manage my booking PDF link helper');
}

$portalCheckoutPdfSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/includes/portal_checkout.php');
$vendorHtml2canvas = dirname(__DIR__) . '/booking/js/vendor/html2canvas-1.4.1.min.js';
$vendorJspdf = dirname(__DIR__) . '/booking/js/vendor/jspdf-2.5.1.umd.min.js';
if (
    is_file($vendorHtml2canvas)
    && is_file($vendorJspdf)
    && strpos($portalCheckoutPdfSrc, '/js/vendor/html2canvas-1.4.1.min.js') !== false
    && strpos($portalCheckoutPdfSrc, '/js/vendor/jspdf-2.5.1.umd.min.js') !== false
    && stripos($portalCheckoutPdfSrc, 'cdn.jsdelivr.net') === false
    && stripos($portalCheckoutPdfSrc, 'html2canvas@') === false
) {
    hb_pass('booking confirmation pdf vendored html2canvas + jspdf (no CDN)');
} else {
    hb_fail('booking confirmation pdf must load vendored html2canvas/jspdf under booking/js/vendor/');
}

$portalCheckoutPhp = dirname(__DIR__) . '/booking/includes/portal_checkout.php';
$portalCheckoutBody = is_file($portalCheckoutPhp) ? (string) file_get_contents($portalCheckoutPhp) : '';
$portalBookingHintSrc = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (
    $portalCheckoutBody !== ''
    && strpos($portalCheckoutBody, 'itm_hotel_booking_portal_manage_booking_hint_html') !== false
    && strpos($portalCheckoutBody, 'data-hb-manage-url') !== false
    && strpos($portalBookingHintSrc, 'data-hb-pdf-manage-link="1"') !== false
) {
    hb_pass('booking confirmation Manage my booking PDF link markup');
} else {
    hb_fail('booking confirmation Manage my booking PDF link markup missing');
}

if (
    $portalCheckoutBody !== ''
    && strpos($portalCheckoutBody, "hb_portal_ui_copy_esc('portal_ui_step4_full_name_label'") !== false
    && strpos($portalCheckoutBody, 'htmlspecialchars($guestName') !== false
) {
    hb_pass('payment confirmation shows guest full name');
} else {
    hb_fail('payment confirmation missing guest full name row');
}

$_SESSION['employee_id'] = 1;
$_SESSION['login_employee_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['company_name'] = 'TechCorp Global';
$_SESSION['username'] = 'Admin';
$_SESSION['role_name'] = 'admin';

$hospitalityModuleSlugs = [
    'hotel_bookings',
    'hotel_booking_hotels',
    'booking_rooms_types',
    'hotel_booking_rooms',
    'hotel_booking_amenities',
    'hotel_booking_special_rates',
    'hotel_booking_room_type_calendar',
    'hotel_booking_portal_rate_plans',
    'hotel_booking_room_utilities',
    'hotel_booking_housekeeping_statuses',
    'hotel_booking_housekeeping_maintenance_status',
    'hotel_booking_housekeeping_maintenance',
    'hotel_bookings_future',
    'hotel_bookings_present',
    'hotel_bookings_history',
    'hotel_booking_settings',
    'hotel_booking_room_photos',
];

$repoRoot = dirname(__DIR__);
$phpBin = getenv('PHP_EXE');
if ($phpBin === false || trim((string) $phpBin) === '') {
    $phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
}

$bookingFormProbe = $repoRoot . '/scripts/lib/itm_hospitality_booking_form_probe.php';
if (is_file($bookingFormProbe)) {
    foreach (['create', 'edit'] as $probeMode) {
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($bookingFormProbe) . ' ' . escapeshellarg($probeMode);
        $probeOutput = [];
        $probeCode = 0;
        exec($cmd . ' 2>&1', $probeOutput, $probeCode);
        if ($probeCode !== 0) {
            $detail = trim(implode('; ', $probeOutput));
            hb_fail('hotel bookings ' . $probeMode . ' form probe: ' . ($detail !== '' ? $detail : 'exit ' . $probeCode));
        } else {
            hb_pass('hotel bookings ' . $probeMode . ' form HTML probe');
        }
    }
} else {
    hb_fail('missing scripts/lib/itm_hospitality_booking_form_probe.php');
}

$probeScript = $repoRoot . '/scripts/lib/itm_hospitality_index_probe.php';
foreach ($hospitalityModuleSlugs as $slug) {
    if (!is_file($repoRoot . '/modules/' . $slug . '/index.php')) {
        hb_fail("hospitality index missing modules/{$slug}/index.php");
        continue;
    }

    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($probeScript) . ' ' . escapeshellarg($slug);
    $probeOutput = [];
    $probeCode = 0;
    exec($cmd . ' 2>&1', $probeOutput, $probeCode);
    if ($probeCode !== 0) {
        $detail = trim(implode('; ', $probeOutput));
        hb_fail("hospitality index {$slug}: " . ($detail !== '' ? $detail : 'probe exit ' . $probeCode));
    } else {
        hb_pass("hospitality index {$slug} renders");
    }
}

$roomPhotosDelete = $repoRoot . '/modules/hotel_booking_room_photos/delete.php';
if (!is_file($roomPhotosDelete)) {
    hb_fail('hotel_booking_room_photos delete.php missing');
} else {
    $deleteSource = (string) file_get_contents($roomPhotosDelete);
    if (strpos($deleteSource, 'itm_crud_build_soft_delete_sql') !== false) {
        hb_fail('hotel_booking_room_photos delete.php must hard-delete, not soft-delete');
    } elseif (strpos($deleteSource, 'itm_hotel_booking_room_photos_hard_delete') === false) {
        hb_fail('hotel_booking_room_photos delete.php must use itm_hotel_booking_room_photos_hard_delete()');
    } else {
        hb_pass('hotel_booking_room_photos delete hard-delete contract');
    }
}

$hotelsIndex = $repoRoot . '/modules/hotel_booking_hotels/index.php';
$hotelBookingHelper = $repoRoot . '/includes/itm_hotel_booking.php';
if (!is_file($hotelsIndex)) {
    hb_fail('hotel_booking_hotels index.php missing');
} else {
    $hotelsIndexSource = (string) file_get_contents($hotelsIndex);
    $helperSource = is_file($hotelBookingHelper) ? (string) file_get_contents($hotelBookingHelper) : '';
    if (strpos($hotelsIndexSource, 'itm_hotel_booking_render_photo_thumbnail_link') === false) {
        hb_fail('hotel_booking_hotels index must render photo thumbnails');
    } elseif (strpos($hotelsIndexSource, 'name="record_id"') === false) {
        hb_fail('hotel_booking_hotels edit form must POST record_id for photo uploads');
    } elseif (strpos($helperSource, 'itm_hotel_booking_photo_random_stored_filename') === false) {
        hb_fail('hotel booking photo uploads must use randomized stored filenames');
    } elseif (strpos($helperSource, 'itm_hotel_booking_photos_upload_was_attempted') === false) {
        hb_fail('hotel booking photo upload helper must detect attempted uploads');
    } elseif (strpos($helperSource, 'itm_app_root_public_path_prefix') === false) {
        hb_fail('hotel booking photo public URLs must use app-root path prefix for admin');
    } elseif (strpos($helperSource, 'ITM_HOTEL_BOOKING_PUBLIC_PORTAL') === false || strpos($helperSource, 'APPURL') === false) {
        hb_fail('hotel booking photo public URLs must use APPURL under public portal');
    } elseif (strpos($helperSource, 'itm_hotel_booking_photo_is_servable') === false) {
        hb_fail('hotel booking photos must skip missing or invalid files on disk');
    } elseif (strpos($helperSource, 'booking/images/') === false || strpos($helperSource, 'hotel_photos') === false || strpos($helperSource, 'room_types_photos') === false) {
        hb_fail('hotel booking photos must use booking/images/{hotel_id}/hotel_photos and room_types_photos');
    } elseif (!is_file($repoRoot . '/booking/images/1/hotel_photos/hb_seed_01.jpg')) {
        hb_fail('booking/images/1/hotel_photos/hb_seed_01.jpg sample file missing');
    } elseif (!is_file($repoRoot . '/booking/images/image_2.jpg') || !is_file($repoRoot . '/booking/images/room-3.jpg') || !is_file($repoRoot . '/booking/images/room-5.jpg') || !is_file($repoRoot . '/booking/images/room-6.jpg')) {
        hb_fail('booking/images portal fallback JPGs missing (image_2.jpg, room-3.jpg, room-5.jpg, room-6.jpg)');
    } elseif (strpos((string) file_get_contents($repoRoot . '/booking/index.php'), 'hb-hotel-card-gallery') === false) {
        hb_fail('booking index must render hotel card gallery with arrows and counter');
    } elseif (strpos((string) file_get_contents($repoRoot . '/booking/rooms.php'), 'hb-room-card-gallery') === false) {
        hb_fail('booking rooms must render room card gallery with arrows and counter');
    } elseif (!is_file($repoRoot . '/booking/js/hotel-booking-gallery.js')) {
        hb_fail('booking/js/hotel-booking-gallery.js shared gallery helper missing');
    } elseif (strpos($helperSource, 'target="_blank"') === false && strpos($helperSource, "target='_blank'") === false) {
        hb_fail('itm_hotel_booking_render_photo_thumbnail_link must open full image in new tab');
    } else {
        hb_pass('hotel_booking_hotels list/edit photo thumbnail markup');
    }
}

$bookingGalleryJs = $repoRoot . '/booking/js/hotel-booking-gallery.js';
$bookingGalleryCss = $repoRoot . '/booking/css/hotel-booking-modern.css';
$portalChrome = $repoRoot . '/booking/includes/portal_chrome.php';
$roomsPhp = $repoRoot . '/booking/rooms.php';
$manageBookingsPhp = $repoRoot . '/booking/users/bookings.php';
if (!is_file($bookingGalleryJs)) {
    hb_fail('booking/js/hotel-booking-gallery.js missing');
} else {
    $galleryJs = (string) file_get_contents($bookingGalleryJs);
    $galleryCss = is_file($bookingGalleryCss) ? (string) file_get_contents($bookingGalleryCss) : '';
    $chromeSrc = is_file($portalChrome) ? (string) file_get_contents($portalChrome) : '';
    $roomsSrc = is_file($roomsPhp) ? (string) file_get_contents($roomsPhp) : '';
    $manageSrc = is_file($manageBookingsPhp) ? (string) file_get_contents($manageBookingsPhp) : '';
    if (strpos($galleryJs, 'HB_bindGallery') === false || strpos($galleryJs, ' / ') === false) {
        hb_fail('booking gallery JS must expose HB_bindGallery and spaced counter format');
    } elseif (strpos($galleryCss, '.hb-gallery-counter') === false || strpos($galleryCss, '.hb-gallery-prev') === false) {
        hb_fail('booking gallery CSS must style arrows and counter');
    } elseif (strpos((string) file_get_contents($repoRoot . '/booking/index.php'), 'hb-detail-modal-card') === false) {
        hb_fail('booking index hotel Details modal must use hb-detail-modal-card (no inner vertical scrollbar)');
    } elseif (strpos($galleryCss, '.hb-detail-modal-card') === false || strpos($galleryCss, 'overflow: visible') === false) {
        hb_fail('booking CSS must set hb-detail-modal-card overflow visible without max-height trap');
    } elseif (strpos($chromeSrc, 'occupancy_interactive') === false || strpos($chromeSrc, 'hb-stay-occupancy-readonly') === false) {
        hb_fail('stay bar must support occupancy_interactive and readonly occupancy markup');
    } elseif (strpos($roomsSrc, "'occupancy_interactive' => true") === false && strpos($roomsSrc, '"occupancy_interactive" => true') === false) {
        hb_fail('rooms.php must enable interactive stay-bar occupancy (modal page)');
    } elseif (strpos($manageSrc, 'hb_portal_render_stay_bar') === false) {
        hb_fail('manage bookings must render stay bar');
    } else {
        hb_pass('booking portal photo gallery arrows and counter');
    }
}

$applyOccupancyPhp = $repoRoot . '/booking/apply-occupancy.php';
$occupancyJs = $repoRoot . '/booking/js/hotel-booking-occupancy.js';
$selectRatePhp = $repoRoot . '/booking/rooms/select-rate.php';
$customizePhp = $repoRoot . '/booking/rooms/customize.php';
$roomSinglePhp = $repoRoot . '/booking/rooms/room-single.php';
$portalBookingSrcOcc = is_file($repoRoot . '/includes/itm_hotel_booking.php')
    ? (string) file_get_contents($repoRoot . '/includes/itm_hotel_booking.php')
    : '';
if (!is_file($applyOccupancyPhp)) {
    hb_fail('booking/apply-occupancy.php missing');
} elseif (!is_file($occupancyJs)) {
    hb_fail('booking/js/hotel-booking-occupancy.js missing');
} elseif (strpos($portalBookingSrcOcc, 'function itm_hotel_booking_portal_apply_checkout_occupancy_change') === false) {
    hb_fail('itm_hotel_booking_portal_apply_checkout_occupancy_change helper missing');
} elseif (strpos($chromeSrc, 'hb_portal_render_occupancy_modal') === false
    || strpos($chromeSrc, 'hb_portal_render_checkout_occupancy_assets') === false
    || strpos($chromeSrc, 'hb-occupancy-unavailable-modal') === false) {
    hb_fail('portal_chrome must expose shared occupancy modal + checkout assets');
} elseif (strpos($roomsSrc, 'hb_portal_render_occupancy_modal') === false) {
    hb_fail('rooms.php must use shared occupancy modal renderer');
} elseif (strpos((string) file_get_contents($applyOccupancyPhp), 'itm_hotel_booking_portal_apply_checkout_occupancy_change') === false) {
    hb_fail('apply-occupancy.php must call checkout occupancy apply helper');
} elseif (strpos((string) file_get_contents($occupancyJs), 'hb-occupancy-unavailable-modal') === false
    || strpos((string) file_get_contents($occupancyJs), 'cfg.applyUrl') === false) {
    hb_fail('hotel-booking-occupancy.js must POST apply URL and show unavailable modal');
} elseif (strpos((string) file_get_contents($occupancyJs), '$cfg') !== false) {
    hb_fail('hotel-booking-occupancy.js must use cfg (not PHP-style $cfg) in Apply handler');
} else {
    $selectRateSrcOcc = is_file($selectRatePhp) ? (string) file_get_contents($selectRatePhp) : '';
    $customizeSrcOcc = is_file($customizePhp) ? (string) file_get_contents($customizePhp) : '';
    $roomSingleSrcOcc = is_file($roomSinglePhp) ? (string) file_get_contents($roomSinglePhp) : '';
    if (strpos($selectRateSrcOcc, "'occupancy_interactive' => true") === false
        && strpos($selectRateSrcOcc, '"occupancy_interactive" => true') === false) {
        hb_fail('select-rate.php must enable interactive stay-bar occupancy');
    } elseif (strpos($customizeSrcOcc, "'occupancy_interactive' => true") === false
        && strpos($customizeSrcOcc, '"occupancy_interactive" => true') === false) {
        hb_fail('customize.php must enable interactive stay-bar occupancy');
    } elseif (strpos($roomSingleSrcOcc, "'occupancy_interactive' => true") === false
        && strpos($roomSingleSrcOcc, '"occupancy_interactive" => true') === false) {
        hb_fail('room-single.php must enable interactive stay-bar occupancy');
    } elseif (strpos($selectRateSrcOcc, 'hb_portal_render_checkout_occupancy_assets') === false
        || strpos($customizeSrcOcc, 'hb_portal_render_checkout_occupancy_assets') === false
        || strpos($roomSingleSrcOcc, 'hb_portal_render_checkout_occupancy_assets') === false) {
        hb_fail('checkout steps 2–4 must wire hb_portal_render_checkout_occupancy_assets');
    } else {
        hb_pass('booking stay-bar occupancy interactive through checkout step 4');
    }
}

if (!function_exists('itm_hotel_booking_portal_prepare_checkout_summary')) {
    hb_fail('itm_hotel_booking_portal_prepare_checkout_summary helper missing');
} else {
    $customizeSrcOcc = is_file($customizePhp) ? (string) file_get_contents($customizePhp) : '';
    $roomSingleSrcOcc = is_file($roomSinglePhp) ? (string) file_get_contents($roomSinglePhp) : '';
    if (strpos($customizeSrcOcc, 'itm_hotel_booking_portal_prepare_checkout_summary') === false
        || strpos($roomSingleSrcOcc, 'itm_hotel_booking_portal_prepare_checkout_summary') === false) {
        hb_fail('customize.php and room-single.php must prepare checkout summary before breakdown');
    } elseif (strpos($customizeSrcOcc, 'itm_hotel_booking_portal_checkout_breakdown') === false
        || strpos($roomSingleSrcOcc, 'itm_hotel_booking_portal_checkout_breakdown') === false) {
        hb_fail('customize.php and room-single.php must render checkout breakdown');
    } else {
    $customizePreparePos = strpos($customizeSrcOcc, 'itm_hotel_booking_portal_prepare_checkout_summary');
    $customizeBreakdownPos = strpos($customizeSrcOcc, 'itm_hotel_booking_portal_checkout_breakdown');
    $roomSinglePreparePos = strpos($roomSingleSrcOcc, 'itm_hotel_booking_portal_prepare_checkout_summary');
    $roomSingleBreakdownPos = strpos($roomSingleSrcOcc, 'itm_hotel_booking_portal_checkout_breakdown');
    if ($customizePreparePos === false || $customizeBreakdownPos === false
        || $customizePreparePos > $customizeBreakdownPos
        || $roomSinglePreparePos === false || $roomSingleBreakdownPos === false
        || $roomSinglePreparePos > $roomSingleBreakdownPos) {
        hb_fail('steps 3–4 must call prepare_checkout_summary before checkout_breakdown');
    } else {
        $taxTwoAdults = itm_hotel_booking_portal_checkout_breakdown(
            100.0,
            '2026-09-30',
            '2026-10-01',
            ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0],
            0,
            ['rate_plan' => 'room_only', 'company_id' => 1, 'hotel_id' => 1],
            2.0
        );
        $taxOneAdult = itm_hotel_booking_portal_checkout_breakdown(
            100.0,
            '2026-09-30',
            '2026-10-01',
            ['rooms' => 2, 'adults' => 1, 'children' => 0, 'babies' => 0],
            0,
            ['rate_plan' => 'room_only', 'company_id' => 1, 'hotel_id' => 1],
            2.0
        );
        if ((float) ($taxTwoAdults['tourist_tax'] ?? 0) <= (float) ($taxOneAdult['tourist_tax'] ?? 0)) {
            hb_fail('checkout breakdown tourist tax must decrease when adults decrease');
        } else {
            hb_pass('steps 3–4 prepare checkout summary before breakdown repricing');
        }
    }
    }
}

if (!function_exists('itm_hotel_booking_portal_build_rooms_restart_url')) {
    hb_fail('itm_hotel_booking_portal_build_rooms_restart_url helper missing');
} else {
    if (!defined('APPURL')) {
        define('APPURL', 'http://localhost/it-management/booking');
    }
    $restartUrl = itm_hotel_booking_portal_build_rooms_restart_url(
        1,
        '2026-09-24',
        2,
        ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0]
    );
    if (strpos($restartUrl, 'rooms.php') === false
        || strpos($restartUrl, 'rooms=2') === false
        || strpos($restartUrl, 'check_in=2026-09-24') === false) {
        hb_fail('build_rooms_restart_url must preserve stay dates and occupancy query');
    } else {
        hb_pass('portal checkout occupancy restart URL builder');
    }
}

if (!function_exists('itm_hotel_booking_portal_merge_occupancy_into_url')) {
    hb_fail('itm_hotel_booking_portal_merge_occupancy_into_url helper missing');
} else {
    $mergedOccUrl = itm_hotel_booking_portal_merge_occupancy_into_url(
        APPURL . '/rooms/select-rate.php?id=1&check_in=2026-09-30&nights=1&rooms=1&adults=1&children=0&babies=0',
        ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0]
    );
    if (strpos($mergedOccUrl, 'adults=2') === false || strpos($mergedOccUrl, 'adults=1') !== false) {
        hb_fail('merge_occupancy_into_url must replace adults query param on checkout redirect');
    } else {
        hb_pass('portal checkout occupancy merge into redirect URL');
    }
}

$applyOccSrc = is_file($applyOccupancyPhp) ? (string) file_get_contents($applyOccupancyPhp) : '';
if (strpos($applyOccSrc, 'itm_hotel_booking_portal_checkout_redirect_url_allowed') === false) {
    hb_fail('apply-occupancy.php must use checkout_redirect_url_allowed for redirect_url validation');
}

$occApplyExpired = itm_hotel_booking_portal_apply_checkout_occupancy_change($conn, 1, ['hotel_id' => 0], [], []);
if (empty($occApplyExpired['ok']) && !empty($occApplyExpired['restart'])) {
    if (empty($occApplyExpired['redirect_url'])) {
        hb_fail('portal apply checkout occupancy expired draft must include redirect_url when restart');
    } else {
        hb_pass('portal apply checkout occupancy rejects expired draft');
    }
} else {
    hb_fail('portal apply checkout occupancy must restart when draft is invalid');
}

$occApplyRoomBump = itm_hotel_booking_portal_apply_checkout_occupancy_change(
    $conn,
    1,
    [
        'hotel_id' => 1,
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'nights' => 2,
        'occupancy' => ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0],
        'room_id' => 1,
    ],
    ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0],
    []
);
if (empty($occApplyRoomBump['ok']) && !empty($occApplyRoomBump['restart'])
    && strpos((string) ($occApplyRoomBump['redirect_url'] ?? ''), 'rooms.php') !== false) {
    hb_pass('portal apply checkout occupancy room-count change restarts at step 1');
} else {
    hb_fail('portal apply checkout occupancy must restart step 1 when room count changes');
}

$selectRoomJs = $repoRoot . '/booking/js/hotel-booking-select-room.js';
if (!is_file($selectRoomJs)) {
    hb_fail('booking/js/hotel-booking-select-room.js missing');
} else {
    $selectRoomJsSrc = (string) file_get_contents($selectRoomJs);
    $roomsSrcForTax = is_file($roomsPhp) ? (string) file_get_contents($roomsPhp) : '';
    if (strpos($selectRoomJsSrc, 'touristTaxPerPersonPerNight') === false || strpos($selectRoomJsSrc, 'touristTaxPerNight') === false) {
        hb_fail('select-room JS must add tourist tax when re-rendering card prices');
    } elseif (strpos($roomsSrcForTax, 'touristTaxPerPersonPerNight') === false) {
        hb_fail('rooms.php must pass touristTaxPerPersonPerNight into HB_SELECT_ROOM');
    } elseif (strpos($roomsSrcForTax, 'cardQuoteOccupancy') === false
        || strpos($selectRoomJsSrc, 'cardQuoteOccupancy') === false) {
        hb_fail('rooms Step 1 must quote card prices per room slot (cardQuoteOccupancy)');
    } else {
        hb_pass('rooms list JS prices include tourist tax');
    }
}

$roomTypesIndex = $repoRoot . '/modules/booking_rooms_types/index.php';
if (!is_file($roomTypesIndex)) {
    hb_fail('booking_rooms_types index.php missing');
} else {
    $roomTypesIndexSource = (string) file_get_contents($roomTypesIndex);
    if (strpos($roomTypesIndexSource, 'itm_hotel_booking_render_photo_thumbnail_link') === false) {
        hb_fail('booking_rooms_types index must render photo thumbnails');
    } elseif (strpos($roomTypesIndexSource, 'name="record_id"') === false) {
        hb_fail('booking_rooms_types edit form must POST record_id for photo uploads');
    } elseif (strpos($roomTypesIndexSource, 'Current photos') === false) {
        hb_fail('booking_rooms_types edit form must preview current photos');
    } elseif (strpos($roomTypesIndexSource, 'brtFormPortalRuleColumns') === false || strpos($roomTypesIndexSource, 'Portal rules') === false) {
        hb_fail('booking_rooms_types create/edit must expose Portal rules form card');
    } elseif (strpos($roomTypesIndexSource, 'brtFormEditSections') === false || strpos($roomTypesIndexSource, 'name="pets_allowed"') === false) {
        hb_fail('booking_rooms_types create/edit must expose full view fields (including pets_allowed)');
    } elseif (strpos((string) file_get_contents($repoRoot . '/db/02_data.sql'), 'hb_rt_std_01.jpg') === false) {
        hb_fail('db/02_data.sql must seed booking_rooms_type_photos sample rows');
    } elseif (!is_file($repoRoot . '/booking/images/1/room_types_photos/hb_rt_std_01.jpg')) {
        hb_fail('booking/images/1/room_types_photos/hb_rt_std_01.jpg sample file missing');
    } else {
        hb_pass('booking_rooms_types list/edit photo thumbnail markup');
    }
}

$roomsDuplicate = $repoRoot . '/modules/hotel_booking_rooms/duplicate.php';
$roomsIndex = $repoRoot . '/modules/hotel_booking_rooms/index.php';
if (!is_file($roomsDuplicate)) {
    hb_fail('hotel_booking_rooms duplicate.php missing');
} elseif (!is_file($roomsIndex)) {
    hb_fail('hotel_booking_rooms index.php missing');
} else {
    $roomsIndexSource = (string) file_get_contents($roomsIndex);
    $helperSource = is_file($hotelBookingHelper) ? (string) file_get_contents($hotelBookingHelper) : '';
    if (strpos($roomsIndexSource, 'action="duplicate.php"') === false || strpos($roomsIndexSource, 'title="Duplicate"') === false) {
        hb_fail('hotel_booking_rooms index must expose Duplicate action');
    } elseif (strpos($helperSource, 'itm_hotel_booking_room_duplicate_record') === false) {
        hb_fail('itm_hotel_booking_room_duplicate_record helper missing');
    } elseif (strpos($roomsIndexSource, 'itm_crud_render_checkbox_boolean_cell_value') === false
        || strpos($roomsIndexSource, 'itm_crud_register_column_type_map') === false) {
        hb_fail('hotel_booking_rooms index must render tinyint checkbox columns with ✅/❌ via shared CRUD helper');
    } else {
        hb_pass('hotel_booking_rooms duplicate action wiring');
    }
}

$sourceRoomRes = mysqli_query($conn, 'SELECT id, hotel_id, room_number, name FROM hotel_booking_rooms WHERE company_id = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
$sourceRoom = $sourceRoomRes ? mysqli_fetch_assoc($sourceRoomRes) : null;
if (!$sourceRoom) {
    hb_fail('hotel_booking_rooms duplicate runtime: no seed room for company 1');
} else {
    $_SESSION['employee_id'] = 1;
    $expectedNumber = itm_hotel_booking_room_resolve_duplicate_room_number($conn, 1, (int) $sourceRoom['hotel_id'], (string) $sourceRoom['room_number']);
    $expectedName = itm_hotel_booking_room_resolve_duplicate_name((string) $sourceRoom['name']);
    $dup = itm_hotel_booking_room_duplicate_record($conn, 1, (int) $sourceRoom['id']);
    if (empty($dup['ok']) || (int) ($dup['new_id'] ?? 0) < 1) {
        hb_fail('hotel_booking_rooms duplicate runtime failed: ' . (string) ($dup['message'] ?? 'unknown'));
    } else {
        $newId = (int) $dup['new_id'];
        $newRes = mysqli_query($conn, 'SELECT room_number, name FROM hotel_booking_rooms WHERE company_id = 1 AND id = ' . $newId . ' LIMIT 1');
        $newRow = $newRes ? mysqli_fetch_assoc($newRes) : null;
        if (!$newRow || (string) $newRow['room_number'] !== $expectedNumber || (string) $newRow['name'] !== $expectedName) {
            hb_fail('hotel_booking_rooms duplicate runtime: room_number/name mismatch');
        } elseif ((string) $newRow['room_number'] === (string) $sourceRoom['room_number']) {
            hb_fail('hotel_booking_rooms duplicate runtime: room_number unchanged');
        } else {
            hb_pass('hotel_booking_rooms duplicate runtime');
            mysqli_query($conn, 'DELETE FROM hotel_booking_rooms WHERE company_id = 1 AND id = ' . $newId . ' LIMIT 1');
        }
    }
}

foreach (['hotel_booking_room_type_rate_overrides', 'hotel_booking_room_type_blocks'] as $calendarTable) {
    $resCalendar = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $calendarTable) . "'");
    if ($resCalendar && mysqli_num_rows($resCalendar) > 0) {
        hb_pass('table ' . $calendarTable);
    } else {
        hb_fail('missing table ' . $calendarTable);
    }
}

$typeRes = mysqli_query($conn, 'SELECT id FROM booking_rooms_types WHERE company_id = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
$typeRow = $typeRes ? mysqli_fetch_assoc($typeRes) : null;
$hotelRes = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
$hotelRow = $hotelRes ? mysqli_fetch_assoc($hotelRes) : null;
if (!$typeRow || !$hotelRow) {
    hb_fail('room type calendar runtime: missing seed hotel or room type');
} else {
    $calHotelId = (int) $hotelRow['id'];
    $calTypeId = (int) $typeRow['id'];
  $insRate = mysqli_prepare($conn, 'INSERT INTO hotel_booking_room_type_rate_overrides (company_id, hotel_id, room_type_id, start_date, end_date, price_per_night, active, created_at) VALUES (1, ?, ?, ?, ?, 199.50, 1, NOW())');
    if ($insRate) {
        $start = '2030-06-01';
        $end = '2030-06-03';
        mysqli_stmt_bind_param($insRate, 'iiss', $calHotelId, $calTypeId, $start, $end);
        mysqli_stmt_execute($insRate);
        $overrideId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($insRate);
        $resolved = itm_hotel_booking_resolve_room_type_nightly_bar($conn, 1, $calHotelId, $calTypeId, '2030-06-02', 100.0);
        if (abs($resolved - 199.5) < 0.01) {
            hb_pass('room type rate override resolves for night');
        } else {
            hb_fail('room type rate override expected 199.5 got ' . $resolved);
        }
        if ($overrideId > 0) {
            mysqli_query($conn, 'DELETE FROM hotel_booking_room_type_rate_overrides WHERE company_id = 1 AND id = ' . $overrideId . ' LIMIT 1');
        }
    } else {
        hb_fail('room type rate override insert prepare failed');
    }

    $insBlock = mysqli_prepare($conn, 'INSERT INTO hotel_booking_room_type_blocks (company_id, hotel_id, room_type_id, start_date, end_date, reason, active, created_at) VALUES (1, ?, ?, ?, ?, ?, 1, NOW())');
    if ($insBlock) {
        $bStart = '2030-07-10';
        $bEnd = '2030-07-12';
        $reason = 'QA stop-sell';
        mysqli_stmt_bind_param($insBlock, 'iisss', $calHotelId, $calTypeId, $bStart, $bEnd, $reason);
        mysqli_stmt_execute($insBlock);
        $blockId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($insBlock);
        if (itm_hotel_booking_room_type_blocked_for_stay($conn, 1, $calHotelId, $calTypeId, '2030-07-10', '2030-07-13')) {
            hb_pass('room type stop-sell blocks stay');
        } else {
            hb_fail('room type stop-sell block not detected');
        }
        if ($blockId > 0) {
            mysqli_query($conn, 'DELETE FROM hotel_booking_room_type_blocks WHERE company_id = 1 AND id = ' . $blockId . ' LIMIT 1');
        }
    } else {
        hb_fail('room type block insert prepare failed');
    }
}

if (itm_format_hotel_date_display('2026-08-31') === '31/Aug/2026') {
    hb_pass('hotel date display format');
} else {
    hb_fail('hotel date display expected 31/Aug/2026 got ' . itm_format_hotel_date_display('2026-08-31'));
}

if (itm_parse_date_input('31/Aug/2026') === '2026-08-31' && itm_parse_date_input('01/Oct/2026') === '2026-10-01' && itm_parse_date_input('1/Oct/2026') === '2026-10-01') {
    hb_pass('hotel date parse format');
} else {
    hb_fail('hotel date parse 31/Aug/2026');
}

if (itm_format_hotel_date_display('2026-10-01') === '01/Oct/2026') {
    hb_pass('hotel date display zero-padded day');
} else {
    hb_fail('hotel date display expected 01/Oct/2026 got ' . itm_format_hotel_date_display('2026-10-01'));
}

if (function_exists('itm_hotel_booking_portal_manage_rate_limit_check')
    && function_exists('itm_hotel_booking_portal_manage_rate_limit_record')) {
    $rlKey = itm_hotel_booking_portal_manage_rate_limit_session_key();
    $_SESSION[$rlKey] = [];
    if (function_exists('itm_hotel_booking_portal_manage_rate_limit_ip_events')) {
        itm_hotel_booking_portal_manage_rate_limit_ip_events(900, []);
    }
    $rlOk = itm_hotel_booking_portal_manage_rate_limit_check(3, 900);
    for ($i = 0; $i < 3; $i++) {
        itm_hotel_booking_portal_manage_rate_limit_record();
    }
    $rlBlocked = itm_hotel_booking_portal_manage_rate_limit_check(3, 900);
    $_SESSION[$rlKey] = [];
    if (function_exists('itm_hotel_booking_portal_manage_rate_limit_ip_events')) {
        itm_hotel_booking_portal_manage_rate_limit_ip_events(900, []);
    }
    if (!empty($rlOk['ok']) && empty($rlBlocked['ok'])) {
        hb_pass('portal manage rate limit blocks after max attempts');
    } else {
        hb_fail('portal manage rate limit expected block after 3 records');
    }
} else {
    hb_fail('portal manage rate limit helpers missing');
}

if (function_exists('itm_hotel_booking_portal_manage_otp_rate_limit_check')
    && function_exists('itm_hotel_booking_portal_manage_otp_rate_limit_record')) {
    $otpRlKey = itm_hotel_booking_portal_manage_otp_rate_limit_session_key();
    $_SESSION[$otpRlKey] = [];
    if (function_exists('itm_hotel_booking_portal_manage_otp_rate_limit_ip_events')) {
        itm_hotel_booking_portal_manage_otp_rate_limit_ip_events(600, []);
    }
    $otpOk = itm_hotel_booking_portal_manage_otp_rate_limit_check(3, 600);
    for ($i = 0; $i < 3; $i++) {
        itm_hotel_booking_portal_manage_otp_rate_limit_record();
    }
    $otpBlocked = itm_hotel_booking_portal_manage_otp_rate_limit_check(3, 600);
    $_SESSION[$otpRlKey] = [];
    if (function_exists('itm_hotel_booking_portal_manage_otp_rate_limit_ip_events')) {
        itm_hotel_booking_portal_manage_otp_rate_limit_ip_events(600, []);
    }
    if (!empty($otpOk['ok']) && empty($otpBlocked['ok'])) {
        hb_pass('portal manage OTP rate limit blocks after max attempts');
    } else {
        hb_fail('portal manage OTP rate limit expected block after 3 records');
    }
} else {
    hb_fail('portal manage OTP rate limit helpers missing');
}

$manageBookingsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/users/bookings.php');
if (strpos($manageBookingsSrc, 'confirmation_code') !== false
    && strpos($manageBookingsSrc, 'name="reservation_id"') === false) {
    hb_pass('portal manage lookup uses guest confirmation code');
} else {
    hb_fail('portal manage lookup must use confirmation_code (not sequential reservation id)');
}

$bookingHelperSrc = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (strpos($bookingHelperSrc, 'UPDATE customers SET name = ?, phone = ?') !== false
    && strpos($bookingHelperSrc, 'guest_confirmation_code = ?') !== false
    && strpos($bookingHelperSrc, 'itm_hotel_booking_auth2_is_legacy_digits($stored)') !== false
    && strpos($bookingHelperSrc, 'return false') !== false) {
    hb_pass('portal customer refresh + guest code + legacy auth2 sunset wiring');
} else {
    hb_fail('portal customer refresh / guest code / legacy auth2 wiring missing');
}

if (strpos($manageBookingsSrc, 'itm_hotel_booking_portal_manage_otp_rate_limit_check') !== false
    && strpos($manageBookingsSrc, 'itm_hotel_booking_portal_manage_lookup_failure_message') !== false
    && strpos($manageBookingsSrc, 'masked_email') === false
    && function_exists('itm_hotel_booking_portal_ui_copy_from_settings')
    && itm_hotel_booking_portal_ui_copy_from_settings(null, 'portal_ui_manage_lookup_failure') !== '') {
    hb_pass('portal manage lookup enumeration copy + OTP verify throttle wiring');
} else {
    hb_fail('portal manage lookup enumeration / OTP throttle wiring missing');
}

if (function_exists('itm_hotel_booking_portal_resolve_step4_charge')) {
    $resolveDraft = [
        'check_in' => '2030-06-01',
        'check_out' => '2030-06-03',
        'portal_rate_plan_id' => 0,
        'resolved_rate_slug' => '',
        'discount_percent' => 99.0,
        'base_price_per_night' => 1.0,
        'surcharge_percent' => 0,
        'room_type_id' => 1,
        'rate_plan' => 'non_refundable',
    ];
    $roomProbe = mysqli_query($conn, 'SELECT r.id, r.hotel_id, r.room_type_id, COALESCE(bp.price_per_night, 0) AS price_per_night FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.company_id = 1 AND r.deleted_at IS NULL AND r.active = 1 LIMIT 1');
    $roomRow = $roomProbe ? mysqli_fetch_assoc($roomProbe) : null;
    if ($roomRow) {
        $plans = itm_hotel_booking_portal_rate_plans_active_for_hotel($conn, 1, (int) $roomRow['hotel_id']);
        foreach ($plans as $plan) {
            if (strtolower((string) ($plan['rate_plan_slug'] ?? '')) === 'non_refundable') {
                $resolveDraft['portal_rate_plan_id'] = (int) ($plan['id'] ?? 0);
                break;
            }
        }
        $occ = ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0];
        $resolved = itm_hotel_booking_portal_resolve_step4_charge($conn, 1, $roomRow, $resolveDraft, $occ);
        if (!empty($resolved['ok']) && (float) ($resolved['discount_percent'] ?? 0) < 50.0 && (float) ($resolved['base_per_night'] ?? 0) > 1.0) {
            hb_pass('portal step4 charge re-resolves BAR/discount from DB');
        } else {
            hb_fail('portal step4 charge DB resolve rejected tampered draft');
        }
    } else {
        hb_fail('portal step4 charge DB resolve — no sample room');
    }
} else {
    hb_fail('portal step4 charge resolver missing');
}

if (function_exists('itm_hotel_booking_portal_manage_otp_verify')
    && function_exists('itm_hotel_booking_portal_manage_otp_is_verified')) {
    $_SESSION[itm_hotel_booking_portal_manage_otp_session_key()] = [
        'company_id' => 1,
        'reservation_id' => 42,
        'otp_hash' => hash('sha256', '123456'),
        'expires_at' => time() + 300,
        'verified' => false,
    ];
    $badOtp = itm_hotel_booking_portal_manage_otp_verify('000000');
    $goodOtp = itm_hotel_booking_portal_manage_otp_verify('123456');
    $verified = itm_hotel_booking_portal_manage_otp_is_verified(1, 42);
    itm_hotel_booking_portal_manage_otp_clear();
    if (empty($badOtp['ok']) && !empty($goodOtp['ok']) && $verified) {
        hb_pass('portal manage email OTP verify');
    } else {
        hb_fail('portal manage email OTP verify');
    }
} else {
    hb_fail('portal manage email OTP helpers missing');
}

if (function_exists('itm_hotel_booking_portal_insert_booking_locked')) {
    $lockSrc = file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
    $fnPos = strpos($lockSrc, 'function itm_hotel_booking_portal_insert_booking_locked');
    $slice = $fnPos !== false ? substr($lockSrc, $fnPos, 2200) : '';
    $stayPos = strpos($lockSrc, 'function itm_hotel_booking_portal_insert_stay_bookings_locked');
    $staySlice = $stayPos !== false ? substr($lockSrc, $stayPos, 6000) : '';
    $bad = itm_hotel_booking_portal_insert_booking_locked($conn, 0, 0, 0, '', '', 0, '', 0, '', '', 0, 0, 0);
    if (stripos($slice, 'FOR UPDATE') !== false
        && stripos($slice, "'nested'") !== false
        && stripos($staySlice, 'mysqli_begin_transaction') !== false
        && stripos($staySlice, "'nested' => true") !== false
        && stripos($staySlice, 'mysqli_commit') !== false
        && empty($bad['ok'])) {
        hb_pass('portal insert booking locked uses FOR UPDATE');
        hb_pass('portal multi-room stay insert uses outer transaction + nested locks');
    } else {
        hb_fail('portal insert booking locked / multi-room atomic contract');
    }
} else {
    hb_fail('portal insert booking locked helper missing');
}

$portalBookingSrcEarly = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (strpos($portalBookingSrcEarly, 'function itm_hotel_booking_portal_resolve_room_lines_pricing_from_db') !== false
    && strpos($portalBookingSrcEarly, 'debug-44bff2') === false
    && strpos((string) @file_get_contents(dirname(__DIR__) . '/booking/rooms.php'), 'debug-44bff2') === false
    && strpos((string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-customize.js'), '7624/ingest') === false) {
    hb_pass('portal step4 multi-room DB line resolve + debug instrumentation removed');
} else {
    hb_fail('portal multi-room DB resolve or debug strip missing');
}

if (function_exists('itm_hotel_booking_portal_resolve_step4_charge')
    && function_exists('itm_hotel_booking_portal_resolve_room_lines_pricing_from_db')) {
    $roomProbe = mysqli_query($conn, 'SELECT r.id, r.hotel_id, r.room_type_id, COALESCE(bp.price_per_night, 0) AS price_per_night FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.company_id = 1 AND r.deleted_at IS NULL AND r.active = 1 ORDER BY r.id ASC LIMIT 2');
    $roomRows = [];
    if ($roomProbe) {
        while ($probeRow = mysqli_fetch_assoc($roomProbe)) {
            $roomRows[] = $probeRow;
        }
    }
    if (count($roomRows) >= 2) {
        $hotelId = (int) ($roomRows[0]['hotel_id'] ?? 0);
        $plans = itm_hotel_booking_portal_rate_plans_active_for_hotel($conn, 1, $hotelId);
        $planId = 0;
        foreach ($plans as $plan) {
            if (strtolower((string) ($plan['rate_plan_slug'] ?? '')) === 'non_refundable') {
                $planId = (int) ($plan['id'] ?? 0);
                break;
            }
        }
        if ($planId > 0) {
            $multiDraft = [
                'check_in' => '2030-06-01',
                'check_out' => '2030-06-03',
                'hotel_id' => $hotelId,
                'portal_rate_plan_id' => $planId,
                'resolved_rate_slug' => 'standard',
                'room_lines' => [
                    [
                        'room_id' => (int) $roomRows[0]['id'],
                        'room_type_id' => (int) $roomRows[0]['room_type_id'],
                        'portal_rate_plan_id' => $planId,
                        'base_price_per_night' => 1.0,
                        'discount_percent' => 99.0,
                    ],
                    [
                        'room_id' => (int) $roomRows[1]['id'],
                        'room_type_id' => (int) $roomRows[1]['room_type_id'],
                        'portal_rate_plan_id' => $planId,
                        'base_price_per_night' => 1.0,
                        'discount_percent' => 99.0,
                    ],
                ],
            ];
            $occ = ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0];
            $multiResolved = itm_hotel_booking_portal_resolve_step4_charge($conn, 1, $roomRows[0], $multiDraft, $occ);
            $payLines = is_array($multiResolved['draft_for_pay']['room_lines'] ?? null) ? $multiResolved['draft_for_pay']['room_lines'] : [];
            $line0Base = (float) ($payLines[0]['base_price_per_night'] ?? 0);
            $line0Disc = (float) ($payLines[0]['discount_percent'] ?? 99);
            if (!empty($multiResolved['ok']) && $line0Base > 1.0 && $line0Disc < 50.0) {
                hb_pass('portal step4 multi-room charge re-resolves each room_lines BAR/discount from DB');
            } else {
                hb_fail('portal step4 multi-room DB resolve rejected tampered room_lines');
            }
        } else {
            hb_fail('portal step4 multi-room DB resolve — no NR plan');
        }
    } else {
        hb_fail('portal step4 multi-room DB resolve — need two sample rooms');
    }
} else {
    hb_fail('portal step4 multi-room DB resolve helpers missing');
}

$roomSingleSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/room-single.php');
$bootstrapSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/bootstrap.php');
if (strpos($roomSingleSrc, 'itm_hotel_booking_portal_insert_stay_bookings_locked') !== false
    && strpos($roomSingleSrc, 'itm_hotel_booking_portal_resolve_step4_charge') !== false
    && strpos($roomSingleSrc, 'itm_hotel_booking_portal_send_booking_confirmation_emails') !== false
    && strpos($roomSingleSrc, 'Lock quoted stay to draft occupancy') !== false
    && strpos($roomSingleSrc, "parse_occupancy(\$_POST)") === false
    && strpos($roomSingleSrc, "portal_ui_step4_session_expired") !== false
    && strpos($roomSingleSrc, "portal_ui_step4_book_reservation_button") !== false
    && strpos($bootstrapSrc, 'function hb_require_company_public_portal') !== false
    && strpos($bootstrapSrc, 'function hb_company_public_portal_enabled') !== false) {
    hb_pass('portal step4 draft required + occupancy lock + tenant portal gate wiring');
} else {
    hb_fail('portal step4 draft required / occupancy lock / tenant portal gate wiring missing');
}

$roomsPhpSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms.php');
$selectRateSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/select-rate.php');
$portalBookingSrc = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (function_exists('itm_hotel_booking_portal_room_line_pick')
    && function_exists('itm_hotel_booking_portal_room_lines_from_draft')
    && function_exists('itm_hotel_booking_portal_insert_stay_bookings_locked')
    && strpos($roomsPhpSrc, 'hb-room-lines-banner') !== false
    && strpos($roomsPhpSrc, 'pick_room_id') !== false
    && strpos($roomsPhpSrc, 'hb_portal_render_reservation_summary') !== false
    && strpos($roomsPhpSrc, 'hb-checkout-aside-stack') !== false
    && strpos($selectRateSrc, 'hb_portal_render_room_lines_summary') !== false
    && strpos($selectRateSrc, 'itm_hotel_booking_portal_draft_rated_room_lines') !== false
    && strpos($selectRateSrc, 'itm_hotel_booking_portal_room_line_apply_rate_plan') !== false
    && strpos($portalBookingSrc, 'itm_hotel_booking_portal_split_occupancy_for_room_line') !== false) {
    hb_pass('portal multi-room pick queue + stay insert wiring');
} else {
    hb_fail('portal multi-room pick queue + stay insert wiring missing');
}

if (strpos($portalBookingSrc, 'function itm_hotel_booking_portal_send_booking_confirmation_emails') !== false
    && strpos($portalBookingSrc, 'hotel_reservations_email') !== false
    && strpos($portalBookingSrc, 'function itm_hotel_booking_portal_load_confirmation_group_rows') !== false
    && strpos($portalBookingSrc, 'function itm_hotel_booking_portal_manage_booking_hint_html') !== false
    && strpos($portalBookingSrc, 'itm_hotel_booking_portal_manage_booking_hint_html') !== false
    && strpos($portalBookingSrc, 'itm_hotel_booking_portal_confirmation_email_flags_from_settings') !== false
    && strpos($portalBookingSrc, '$emailFlags[\'guest\']') !== false
    && strpos($portalBookingSrc, '$emailFlags[\'reservations\']') !== false
    && strpos($portalBookingSrc, 'To view or cancel your reservation later') !== false
    && strpos($portalBookingSrc, 'To view or change your reservation later') === false) {
    hb_pass('portal step4 confirmation emails to guest and reservations desk');
} else {
    hb_fail('portal step4 confirmation email helper missing');
}

$settingsIndexSrc = (string) @file_get_contents(dirname(__DIR__) . '/modules/hotel_booking_settings/index.php');
if (function_exists('itm_hotel_booking_portal_confirmation_email_flags_from_settings')
    && !itm_hotel_booking_portal_confirmation_email_flags_from_settings(['portal_confirmation_email_guest' => 1, 'portal_confirmation_email_reservations' => 0])['reservations']
    && itm_hotel_booking_portal_confirmation_email_flags_from_settings(['portal_confirmation_email_guest' => 0, 'portal_confirmation_email_reservations' => 0])['guest'] === false
    && strpos($settingsIndexSrc, 'portal_confirmation_email_guest') !== false
    && strpos($settingsIndexSrc, 'portal_confirmation_email_reservations') !== false
    && strpos($settingsIndexSrc, 'itm-hb-confirm-email-both') !== false) {
    hb_pass('portal step4 confirmation email admin toggles');
} else {
    hb_fail('portal step4 confirmation email admin toggles missing');
}

$portalCheckoutPaymentSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/includes/portal_checkout.php');
if (strpos($portalCheckoutPaymentSrc, 'itm_hotel_booking_portal_manage_booking_hint_html') !== false
    && strpos($portalCheckoutPaymentSrc, 'To view or change your reservation later') === false
    && substr_count($portalCheckoutPaymentSrc, 'itm_hotel_booking_portal_manage_booking_hint_html') === 1) {
    hb_pass('portal payment confirmation manage hint copy');
} else {
    hb_fail('portal payment confirmation manage hint copy missing or duplicated');
}

if (strpos($portalCheckoutPaymentSrc, 'hb-payment-room-group-list') !== false
    && strpos($portalCheckoutPaymentSrc, 'hb-payment-room-group-line') !== false
    && strpos($portalCheckoutPaymentSrc, '!$showMultiRoomGroup && $ratePlanLabel') !== false
    && strpos($portalCheckoutPaymentSrc, 'itm_hotel_booking_portal_load_confirmation_group_rows') !== false
    && strpos($portalCheckoutPaymentSrc, 'Additional rooms') === false) {
    hb_pass('portal payment confirmation single id multi-room list');
} else {
    hb_fail('portal payment confirmation multi-room group list missing');
}

$customizeSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/customize.php');
if (strpos($customizeSrc, '$hideUpsellOptions') !== false
    && strpos($customizeSrc, '!$hideUpsellOptions && $roomTypeId > 0') !== false
    && strpos($customizeSrc, 'accept_room_upgrade') !== false
    && strpos($customizeSrc, '&& !$hideUpsellOptions') !== false) {
    hb_pass('customize hides room upgrade upsell when rooms>1');
} else {
    hb_fail('customize multi-room upsell hide missing');
}

$portalCheckoutSrcMulti = (string) @file_get_contents(dirname(__DIR__) . '/booking/includes/portal_checkout.php');
$customizeJsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-customize.js');
if (strpos($customizeSrc, "'baseRoomTitle' => \$baseReservationRoomTitle") !== false
    && strpos($customizeSrc, "'upgradeRoomTitle' => \$upgradeReservationRoomTitle") !== false
    && strpos($customizeSrc, "'display_room_title' => \$displayReservationRoomTitle") !== false
    && strpos($portalCheckoutSrcMulti, 'display_room_title') !== false
    && strpos($customizeJsSrc, 'refreshRoomTitle') !== false
    && strpos($customizeJsSrc, 'cfg.upgradeRoomTitle') !== false) {
    hb_pass('customize upsell updates reservation summary room title');
} else {
    hb_fail('customize upsell reservation summary room title wiring missing');
}

if (strpos($portalCheckoutSrcMulti, 'itm_hotel_booking_portal_draft_room_lines_for_display') !== false
    && strpos($portalCheckoutSrcMulti, 'hb_portal_room_line_rate_plan_label') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-reservation-room-rate') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-reservation-room-line-price') !== false
    && strpos($portalCheckoutSrcMulti, 'itm_hotel_booking_portal_room_line_stay_charges') !== false
    && strpos($customizeSrc, 'itm_hotel_booking_portal_draft_all_rooms_rated') !== false) {
    hb_pass('portal reservation summary lists multi-room draft lines');
} else {
    hb_fail('portal reservation summary multi-room list missing');
}

$bookingHelperSrc = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (strpos($bookingHelperSrc, 'function itm_hotel_booking_portal_room_line_stay_charges') !== false) {
    hb_pass('portal multi-room per-line stay charge helper');
} else {
    hb_fail('portal multi-room per-line stay charge helper missing');
}

if (function_exists('itm_hotel_booking_portal_multi_room_payment_shares')) {
    $proportionalShares = itm_hotel_booking_portal_multi_room_payment_shares(211.0, [67.5, 85.5]);
    $shareSum = round((float) ($proportionalShares[0] ?? 0) + (float) ($proportionalShares[1] ?? 0), 2);
    if (count($proportionalShares) === 2
        && $shareSum === 211.0
        && (float) ($proportionalShares[0] ?? 0) !== (float) ($proportionalShares[1] ?? 0)
        && strpos($bookingHelperSrc, 'itm_hotel_booking_portal_confirmation_group_room_display_amounts') !== false
        && strpos($portalCheckoutSrcMulti, 'groupRoomDisplayAmounts') !== false
    && strpos($bookingHelperSrc, "'room_id' => (int) (\$row['room_id']") !== false) {
        hb_pass('portal multi-room payment shares weighted by room rate');
    } else {
        hb_fail('portal multi-room payment shares weighted by room rate');
    }
} else {
    hb_fail('portal multi-room payment shares helper missing');
}

if (function_exists('itm_hotel_booking_portal_notes_has_traveling_pet')
    && function_exists('itm_hotel_booking_portal_confirmation_pet_fee')
    && itm_hotel_booking_portal_notes_has_traveling_pet("Rate: Best available\nTraveling with pet: yes")
    && !itm_hotel_booking_portal_notes_has_traveling_pet("Traveling with pet: no")
    && strpos($portalCheckoutSrcMulti, 'Traveling with a pet') !== false
    && strpos($portalCheckoutSrcMulti, 'itm_hotel_booking_portal_confirmation_pet_fee') !== false
    && strpos($bookingHelperSrc, 'Traveling with a pet') !== false) {
    hb_pass('portal confirmation pet fee line from booking notes');
} else {
    hb_fail('portal confirmation pet fee line missing');
}

if (strpos($portalCheckoutSrcMulti, 'hb_portal_render_confirmation_special_requests') !== false
    && strpos($portalCheckoutSrcMulti, 'hb_portal_render_confirmation_room_upgrade') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-confirmation-special-requests') !== false) {
    hb_pass('portal payment confirmation customize sections');
} else {
    hb_fail('portal payment confirmation customize sections missing');
}

$manageSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/users/bookings.php');
if (strpos($portalCheckoutSrcMulti, 'function hb_portal_booking_rate_plan_label') !== false
    && strpos($portalCheckoutSrcMulti, "portal_ui_confirm_rate_label") !== false
    && strpos($portalCheckoutSrcMulti, 'hb-reservation-rate-line') !== false
    && strpos($manageSrc, 'hb_portal_render_payment_confirmation') !== false) {
    hb_pass('portal manage booking shows selected rate plan');
} else {
    hb_fail('portal manage booking selected rate plan display missing');
}

$otpIssueSrc = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (strpos($manageSrc, 'verify_manage_otp') !== false && strpos($manageSrc, 'itm_hotel_booking_portal_manage_otp_issue') !== false) {
    hb_pass('portal manage email OTP flow wiring');
} else {
    hb_fail('portal manage email OTP flow wiring missing');
}
if (strpos($otpIssueSrc, "'footer_link_text' => 'Manage my booking'") !== false
    && strpos($otpIssueSrc, "'show_gear_icon' => false") !== false
    && strpos($otpIssueSrc, "(\$settingsRow['urlmybooking']") !== false
    && strpos($otpIssueSrc, "(\$verifiedBookingRow['hotel_name']") !== false
    && strpos($otpIssueSrc, 'itm_hotel_booking_portal_reservations_email_send_options') !== false
    && strpos($otpIssueSrc, 'hotel_reservations_email') !== false) {
    hb_pass('portal manage OTP email uses hotel name + urlmybooking footer');
} else {
    hb_fail('portal manage OTP email hotel branding missing');
}

if (function_exists('itm_hotel_booking_portal_reservations_email_send_options')) {
    $fromOpts = itm_hotel_booking_portal_reservations_email_send_options('TechCorp Retreat', 'reservations@techcorp-retreat.example');
    if (($fromOpts['from_email'] ?? '') === 'reservations@techcorp-retreat.example'
        && ($fromOpts['from_name'] ?? '') === 'TechCorp Retreat'
        && ($fromOpts['log_from_email'] ?? '') === 'reservations@techcorp-retreat.example'
        && itm_hotel_booking_portal_reservations_email_send_options('Hotel', 'not-an-email') === []) {
        hb_pass('portal OTP From uses hotel reservations_email');
    } else {
        hb_fail('portal OTP From reservations_email options');
    }
} else {
    hb_fail('portal reservations email send options helper missing');
}

$emailHelperSrc = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_email.php');
if (strpos($emailHelperSrc, "array_key_exists('from_email', \$options)") !== false
    && strpos($emailHelperSrc, "\$smtpSendConfig['from_email']") !== false) {
    hb_pass('itm_send_email supports from_email override');
} else {
    hb_fail('itm_send_email from_email override missing');
}

$roomsSrcPick = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms.php');
if (function_exists('itm_hotel_booking_portal_select_rate_display_occupancy')
    && strpos($selectRateSrc, 'itm_hotel_booking_portal_select_rate_display_occupancy') !== false
    && strpos($selectRateSrc, '$rateDisplayOccupancy') !== false
    && strpos($roomsSrcPick, 'ratedRoomLines') !== false
    && strpos($selectRateSrc, '$summaryLineNightlyAmounts') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-room-lines-summary-intro') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-room-lines-summary-nightly') !== false) {
    hb_pass('portal select-rate quotes current room only for multi-room');
} else {
    hb_fail('portal select-rate current-room quote wiring missing');
}

if (function_exists('itm_hotel_booking_portal_select_rate_display_occupancy')
    && function_exists('itm_hotel_booking_portal_compute_checkout_total')
    && function_exists('itm_hotel_booking_portal_quote_nightly')) {
    $mrCompanyId = 1;
    $mrHotelId = 1;
    $mrCheckIn = date('Y-m-d', strtotime('+14 days'));
    $mrCheckOut = date('Y-m-d', strtotime($mrCheckIn . ' +1 day'));
    $mrOccupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0]);
    $mrSettings = itm_hotel_booking_settings_row($conn, $mrCompanyId) ?: [];
    $mrTaxRate = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($mrSettings);
    $mrPortalPricing = itm_hotel_booking_portal_hotel_pricing($conn, $mrCompanyId, $mrHotelId);
    $mrDisc = itm_hotel_booking_special_rate_discount($conn, $mrCompanyId, $mrHotelId, 'public');
    $mrCheapest = itm_hotel_booking_portal_cheapest_rate_offer_for_hotel($conn, $mrCompanyId, $mrHotelId);
    $mrDisplayDisc = min(50.0, $mrDisc + (float) ($mrCheapest['discount_percent'] ?? 0));
    $mrLines = [];
    $mrDlxLine = null;
    $mrStmt = mysqli_prepare($conn, 'SELECT r.id, t.id AS type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night, t.code
        FROM hotel_booking_rooms r
        INNER JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
        LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
        WHERE r.company_id = ? AND r.hotel_id = ? AND t.code IN (\'STD\', \'DLX\') AND r.deleted_at IS NULL AND r.active = 1
        GROUP BY t.code
        ORDER BY t.code ASC');
    if ($mrStmt) {
        mysqli_stmt_bind_param($mrStmt, 'ii', $mrCompanyId, $mrHotelId);
        mysqli_stmt_execute($mrStmt);
        $mrRes = mysqli_stmt_get_result($mrStmt);
        while ($mrRes && ($mrRow = mysqli_fetch_assoc($mrRes))) {
            $mrBar = itm_hotel_booking_portal_check_in_display_bar($conn, $mrCompanyId, $mrHotelId, (int) $mrRow['type_id'], $mrCheckIn, (float) $mrRow['price_per_night']);
            $mrLine = itm_hotel_booking_portal_room_line_normalize([
                'room_id' => (int) $mrRow['id'],
                'room_type_id' => (int) $mrRow['type_id'],
                'type_code' => (string) ($mrRow['code'] ?? ''),
                'base_price_per_night' => $mrBar,
            ]);
            $mrLines[] = $mrLine;
            if (($mrRow['code'] ?? '') === 'DLX') {
                $mrDlxLine = $mrLine;
            }
        }
        mysqli_stmt_close($mrStmt);
    }
    if (count($mrLines) >= 2 && is_array($mrDlxLine)) {
        $mrDlxIdx = 1;
        $mrLineOcc = itm_hotel_booking_portal_split_occupancy_for_room_line($mrOccupancy, $mrDlxIdx, 2);
        $mrLineTax = itm_hotel_booking_portal_tourist_tax_amount($mrLineOcc, 1, $mrTaxRate);
        $mrStep1Card = round(itm_hotel_booking_portal_quote_nightly((float) $mrDlxLine['base_price_per_night'], $mrLineOcc, $mrDisplayDisc, $mrPortalPricing, 0) + $mrLineTax, 2);
        $mrDisplayOcc = itm_hotel_booking_portal_select_rate_display_occupancy($mrOccupancy, $mrLines, (int) $mrDlxLine['room_id'], 2);
        $mrNrDisc = itm_hotel_booking_portal_rate_plan_effective_discount($mrDisc, 'non_refundable', $mrCheapest);
        $mrSlice = [
            'company_id' => $mrCompanyId,
            'hotel_id' => $mrHotelId,
            'room_type_id' => (int) $mrDlxLine['room_type_id'],
            'rate_plan' => 'non_refundable',
            'traveling_with_pet' => 0,
            'service_animal' => 0,
            'base_price_per_night' => (float) $mrDlxLine['base_price_per_night'],
            'surcharge_percent' => 0.0,
        ];
        $mrStep2Nightly = itm_hotel_booking_portal_compute_checkout_total(
            (float) $mrDlxLine['base_price_per_night'],
            $mrCheckIn,
            $mrCheckOut,
            $mrDisplayOcc,
            $mrNrDisc,
            $mrSlice,
            $mrTaxRate,
            $conn,
            $mrCompanyId
        );
        $mrCombinedWrong = itm_hotel_booking_portal_compute_checkout_total(
            (float) $mrDlxLine['base_price_per_night'],
            $mrCheckIn,
            $mrCheckOut,
            $mrOccupancy,
            $mrNrDisc,
            itm_hotel_booking_portal_select_rate_pricing_draft($mrSlice, $mrLines, 2),
            $mrTaxRate,
            $conn,
            $mrCompanyId
        );
        if (abs($mrStep2Nightly - $mrStep1Card) < 0.02 && $mrCombinedWrong > $mrStep2Nightly + 0.5) {
            hb_pass('portal select-rate multi-room nightly matches step1 card for current room');
        } else {
            hb_fail('portal select-rate current-room nightly mismatch (card=' . $mrStep1Card . ' step2=' . $mrStep2Nightly . ' combined=' . $mrCombinedWrong . ')');
        }
    } else {
        hb_fail('portal select-rate current-room probe could not load DLX line');
    }
} else {
    hb_fail('portal select-rate current-room nightly probe helpers missing');
}

$typeRulesRow = [
    'max_adults' => 2,
    'max_children' => 2,
    'max_babies' => 1,
    'max_total_guests' => 4,
    'min_adults' => 1,
    'included_adults_per_room' => 2,
    'portal_bookable' => 1,
    'min_stay_nights' => 1,
    'closed_to_arrival_days' => '5,6',
];
$lineTotalsRules = array_fill(0, 11, 100.0);
$lineTotalsRules[0] = 50.0;
if (!itm_hotel_booking_room_type_fits_occupancy($typeRulesRow, ['rooms' => 1, 'adults' => 2, 'children' => 2, 'babies' => 1])
    && itm_hotel_booking_portal_effective_max_total_guests(['max_total_guests' => 4, 'extra_bed_allowed' => 1, 'max_extra_beds' => 1]) === 5
    && !itm_hotel_booking_portal_child_age_counters_valid(['child_max_age' => 1], 1, 0)
    && itm_hotel_booking_portal_child_age_counters_valid(['child_max_age' => 12], 1, 1)
    && function_exists('itm_hotel_booking_portal_connecting_unit_fits_occupancy')
    && function_exists('itm_hotel_booking_portal_checkout_required_room_line_count')
    && itm_hotel_booking_portal_checkout_required_room_line_count(['connecting_room_id' => 99], ['rooms' => 1]) === 2
    && itm_hotel_booking_portal_weekday_closed_list('5,6') === [5, 6]
    && abs(itm_hotel_booking_portal_complimentary_room_credit(['portal_complimentary_min_rooms_paid' => 10, 'portal_complimentary_rooms_free' => 1], 11, $lineTotalsRules) - 50.0) < 0.01
    && !empty(itm_hotel_booking_portal_room_type_card_available($typeRulesRow, ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0], '2026-08-10', '2026-08-11', true)['available'])) {
    hb_pass('portal room type rule helpers (occupancy, weekday, complimentary, bookable)');
} else {
    hb_fail('portal room type rule helpers regression');
}

$customizeSrcRules = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/customize.php');
$roomsSrcRules = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms.php');
if (strpos($customizeSrcRules, 'portal_ui_step3_no_special_requests') !== false
    && strpos($roomsSrcRules, 'cardQuoteOccupancy') !== false
    && strpos($roomsSrcRules, 'itm_hotel_booking_room_type_fits_occupancy($typeRow, $cardQuoteOccupancy') !== false
    && strpos($roomsSrcRules, 'itm_hotel_booking_portal_connecting_unit_inventory_available') !== false
    && strpos((string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/select-rate.php'), 'itm_hotel_booking_portal_connecting_unit_append_unrated_pick') !== false
    && strpos($roomsSrcRules, 'portal_bookable') !== false) {
    hb_pass('portal customize pets gate + rooms cardQuoteOccupancy wiring');
} else {
    hb_fail('portal customize pets gate or rooms cardQuoteOccupancy wiring missing');
}

$colComplimentaryMin = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_complimentary_min_rooms_paid'");
$colComplimentaryFree = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_complimentary_rooms_free'");
$colConfirmEmailGuest = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_confirmation_email_guest'");
$colConfirmEmailReservations = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_confirmation_email_reservations'");
$colShowRoomNumber = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_show_room_number_on_confirmation'");
$colHideUpgradeMulti = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_hide_upgrade_upsell_when_multi_room'");
$colMoneySymbol = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_money_symbol'");
$colMoneySuffix = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_money_symbol_suffix'");
$colMoneyPrefix = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_money_symbol_prefix'");
$colPortalBookable = mysqli_query($conn, "SHOW COLUMNS FROM booking_rooms_types LIKE 'portal_bookable'");
if ($colComplimentaryMin && mysqli_num_rows($colComplimentaryMin) === 1
    && $colComplimentaryFree && mysqli_num_rows($colComplimentaryFree) === 1
    && $colConfirmEmailGuest && mysqli_num_rows($colConfirmEmailGuest) === 1
    && $colConfirmEmailReservations && mysqli_num_rows($colConfirmEmailReservations) === 1
    && $colShowRoomNumber && mysqli_num_rows($colShowRoomNumber) === 1
    && $colHideUpgradeMulti && mysqli_num_rows($colHideUpgradeMulti) === 1
    && $colMoneySymbol && mysqli_num_rows($colMoneySymbol) === 1
    && $colMoneySuffix && mysqli_num_rows($colMoneySuffix) === 1
    && $colMoneyPrefix && mysqli_num_rows($colMoneyPrefix) === 1
    && $colPortalBookable && mysqli_num_rows($colPortalBookable) === 1) {
    hb_pass('booking_rooms_types portal rule columns + complimentary settings columns');
} else {
    hb_fail('booking_rooms_types portal rule columns or complimentary settings columns missing');
}

if (function_exists('itm_hotel_booking_portal_money_format_options_from_settings')
    && itm_hotel_booking_portal_money_format_options_from_settings(['portal_money_symbol' => 'GBP'])['symbol'] === '£'
    && itm_hotel_booking_portal_money_format_options_from_settings(['portal_money_symbol' => 'USD', 'portal_money_symbol_prefix' => 1])['suffix'] === false
    && itm_hotel_booking_portal_format_money_with_options(69.5, ['symbol' => '$', 'suffix' => false], 'short') === '$69.50'
    && itm_hotel_booking_portal_format_money_with_options(77.0, ['symbol' => '£', 'suffix' => true], 'decimal') === '77.00£'
    && !itm_hotel_booking_portal_show_room_number_from_settings([])
    && itm_hotel_booking_portal_show_room_number_from_settings(['portal_show_room_number_on_confirmation' => 1])
    && itm_hotel_booking_portal_hide_upgrade_upsell_when_multi_room_from_settings([])
    && !itm_hotel_booking_portal_hide_upgrade_upsell_when_multi_room_from_settings(['portal_hide_upgrade_upsell_when_multi_room' => 0])) {
    hb_pass('portal display money + room number + upgrade upsell helpers');
} else {
    hb_fail('portal display money + room number + upgrade upsell helpers');
}

$customizeSrcDisplay = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/customize.php');
$moneyJsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-money.js');
if (strpos($settingsIndexSrc, 'portal_show_room_number_on_confirmation') !== false
    && strpos($settingsIndexSrc, 'portal_hide_upgrade_upsell_when_multi_room') !== false
    && strpos($settingsIndexSrc, 'portal_money_symbol') !== false
    && strpos($customizeSrcDisplay, 'itm_hotel_booking_portal_hide_upgrade_upsell_when_multi_room_from_settings') !== false
    && strpos($moneyJsSrc, 'hbPortalFormatMoney') !== false
    && is_file(dirname(__DIR__) . '/booking/js/hotel-booking-money.js')) {
    hb_pass('portal display admin toggles + customize upsell + money JS');
} else {
    hb_fail('portal display admin toggles + customize upsell + money JS missing');
}

hb_portal_bind_money_settings(['portal_money_symbol' => 'USD', 'portal_money_symbol_prefix' => 1]);
if (hb_portal_money_format(12.5, 'USD') === '$12.50') {
    hb_pass('portal money format respects USD prefix settings');
} else {
    hb_fail('portal money format USD prefix expected $12.50 got ' . hb_portal_money_format(12.5, 'USD'));
}
unset($GLOBALS['hb_portal_money_settings']);

$colInternalRate = mysqli_query($conn, "SHOW COLUMNS FROM hotel_bookings LIKE 'internal_rate_code'");
$colShowInternalRates = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_show_internal_rates'");
$colPortalDateFormat = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_date_format'");
$colPortalTimeFormat = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_time_format'");
$colDatetimeEuropean2 = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_datetime_european2_enabled'");
$colDatetimeDefault = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_datetime_format_default'");
if ($colInternalRate && mysqli_num_rows($colInternalRate) === 1
    && $colShowInternalRates && mysqli_num_rows($colShowInternalRates) === 1
    && $colPortalDateFormat && mysqli_num_rows($colPortalDateFormat) === 1
    && $colPortalTimeFormat && mysqli_num_rows($colPortalTimeFormat) === 1
    && $colDatetimeEuropean2 && mysqli_num_rows($colDatetimeEuropean2) === 1
    && $colDatetimeDefault && mysqli_num_rows($colDatetimeDefault) === 1) {
    hb_pass('internal rate + portal date/time format schema columns');
} else {
    hb_fail('internal rate + portal date/time format schema columns missing');
}

$colMaxDiscount = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_max_discount_percent'");
$colTaxLabel = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_tourist_tax_label'");
$colPetKg = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_settings LIKE 'portal_default_pet_max_weight_kg'");
$colBreakfastAgeMin = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_hotels LIKE 'portal_breakfast_child_age_min'");
if ($colMaxDiscount && mysqli_num_rows($colMaxDiscount) === 1
    && $colTaxLabel && mysqli_num_rows($colTaxLabel) === 1
    && $colPetKg && mysqli_num_rows($colPetKg) === 1
    && $colBreakfastAgeMin && mysqli_num_rows($colBreakfastAgeMin) === 1) {
    hb_pass('portal money/tax label + breakfast child age schema columns');
} else {
    hb_fail('portal money/tax label + breakfast child age schema columns missing');
}

if (function_exists('itm_hotel_booking_portal_max_discount_percent_from_settings')
    && itm_hotel_booking_portal_max_discount_percent_from_settings(['portal_max_discount_percent' => 40]) === 40.0
    && itm_hotel_booking_portal_tourist_tax_label_from_settings(['portal_tourist_tax_label' => 'City tax']) === 'City tax'
    && itm_hotel_booking_portal_plan_label_from_slug('breakfast', [], '') === 'Breakfast included'
    && strpos($settingsIndexSrc, 'portal_max_discount_percent') !== false
    && strpos($settingsIndexSrc, 'portal_tourist_tax_label') !== false
    && strpos((string) @file_get_contents(dirname(__DIR__) . '/modules/hotel_booking_portal_rate_plans/index.php'), 'breakfast_child_age_min') !== false) {
    hb_pass('portal money/tax admin fields + label helpers');
} else {
    hb_fail('portal money/tax admin fields + label helpers');
}

hb_portal_bind_money_settings(['portal_max_discount_percent' => 40]);
if (itm_hotel_booking_portal_clamp_combined_discount_percent(30, 20) === 40.0) {
    hb_pass('portal offer percent cap from settings');
} else {
    hb_fail('portal offer percent cap from settings');
}
unset($GLOBALS['hb_portal_money_settings'], $itm_hb_portal_offer_percent_cap);

if (itm_hotel_booking_normalize_internal_rate_code('COMPIMENTARY') === 'comp'
    && itm_hotel_booking_normalize_internal_rate_code('HOUSE_USE') === 'use'
    && itm_hotel_booking_internal_rate_waive_scope('comp') === 'all'
    && itm_hotel_booking_internal_rate_waive_scope('use') === 'room_only') {
    hb_pass('internal rate alias normalization + waive scope');
} else {
    hb_fail('internal rate alias normalization + waive scope');
}

$useBreakdown = itm_hotel_booking_apply_internal_rate_to_breakdown([
    'room_charges' => 200.0,
    'tourist_tax' => 12.5,
    'total' => 212.5,
], 'use');
$compBreakdown = itm_hotel_booking_apply_internal_rate_to_breakdown([
    'room_charges' => 200.0,
    'tourist_tax' => 12.5,
    'total' => 212.5,
], 'comp');
if ((float) ($useBreakdown['room_charges'] ?? -1) === 0.0
    && (float) ($useBreakdown['total'] ?? -1) === 12.5
    && (float) ($compBreakdown['total'] ?? -1) === 0.0) {
    hb_pass('internal rate breakdown USE room-only + COMP all waived');
} else {
    hb_fail('internal rate breakdown USE/COMP totals unexpected');
}

$defaultDatetimeMap = itm_hotel_booking_portal_datetime_format_enabled_map([]);
if (!empty($defaultDatetimeMap['european2'])
    && itm_hotel_booking_portal_datetime_format_default_from_settings([]) === 'european2'
    && itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'european_ddmmyyyy']) === '17/08/2026'
    && itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'european_ddmmmyyyy']) === '17/Aug/2026'
    && itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'us_mmddyyyy']) === '08/17/2026'
    && itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'iso_yyyymmdd']) === '2026-08-17') {
    hb_pass('portal date/time format helpers + default datetime2');
} else {
    hb_fail('portal date/time format helpers + default datetime2');
}

$hbFormSrc = (string) @file_get_contents(dirname(__DIR__) . '/modules/hotel_bookings/includes/hb_booking_form.php');
$roomsSrcInternal = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms.php');
$selectRoomJsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-select-room.js');
$dateFormatJsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-date-format.js');
if (strpos($hbFormSrc, 'internal_rate_code') !== false
    && strpos($hbFormSrc, 'hb-booking-internal-rate') !== false
    && strpos($settingsIndexSrc, 'portal_show_internal_rates') !== false
    && strpos($settingsIndexSrc, 'portal_date_format') !== false
    && strpos($roomsSrcInternal, 'hb-rate-internal') !== false
    && strpos($selectRoomJsSrc, 'internal_rate_code') !== false
    && strpos($dateFormatJsSrc, 'itmHotelDateFormatYmd') !== false) {
    hb_pass('admin booking form + portal internal rates + date format JS wiring');
} else {
    hb_fail('admin booking form + portal internal rates + date format JS wiring missing');
}

$bootstrapSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/bootstrap.php');
if (strpos($bootstrapSrc, 'for ($i = 1; $i <= 5') === false
    && strpos($bootstrapSrc, 'public_portal_enabled = 1') !== false) {
    hb_pass('hb_public_company_id scans enabled portal settings (no companies 1-5 cap)');
} else {
    hb_fail('hb_public_company_id still uses hardcoded company loop or missing SQL scan');
}

$displayColsOk = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_maps_base_url')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_calendar_month_horizon')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_occupancy_max_rooms')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_default_included_adults_per_room');
if ($displayColsOk) {
    hb_pass('portal display config schema columns');
} else {
    hb_fail('portal display config schema columns missing — apply hotel_booking_portal_display_config.sql');
}

$mapsTest = itm_hotel_booking_portal_maps_url('Lisbon', ['portal_maps_base_url' => 'https://example.test/?q=']);
if (strpos($mapsTest, 'https://example.test/?q=') === 0 && strpos($mapsTest, 'Lisbon') !== false) {
    hb_pass('portal maps URL builder');
} else {
    hb_fail('portal maps URL builder');
}

$fallbackImg = itm_hotel_booking_portal_room_fallback_image_url('DLX', [
    'portal_room_type_code_fallback_json' => '{"DLX":"/images/room-5.jpg"}',
    'portal_default_room_image_url' => '/images/room-9.jpg',
], 'http://localhost/it-management/booking');
if (strpos($fallbackImg, 'room-5.jpg') !== false) {
    hb_pass('portal room fallback image helper (code JSON)');
} else {
    hb_fail('portal room fallback image helper (code JSON)');
}

$occParsed = itm_hotel_booking_portal_parse_occupancy(['rooms' => 99, 'adults' => 99, 'children' => 99], ['rooms' => 2, 'adults' => 4, 'children' => 1, 'babies' => 0]);
if ((int) ($occParsed['rooms'] ?? 0) === 2 && (int) ($occParsed['adults'] ?? 0) === 4 && (int) ($occParsed['children'] ?? 0) === 1) {
    hb_pass('portal parse_occupancy respects injected limits');
} else {
    hb_fail('portal parse_occupancy respects injected limits');
}

$guestPolicyUrl = itm_hotel_booking_portal_cancellation_policy_guest_url(1, 1, 'room_only');
if (strpos($guestPolicyUrl, 'cancellation-policy.php') !== false
    && strpos($guestPolicyUrl, 'company_id=1') !== false
    && strpos($guestPolicyUrl, 'slug=room_only') !== false) {
    hb_pass('cancellation policy guest endpoint URL helper');
} else {
    hb_fail('cancellation policy guest endpoint URL helper');
}

if (strpos($settingsIndexSrc, 'portal_maps_base_url') !== false
    && strpos($settingsIndexSrc, 'portal_occupancy_max_adults') !== false
    && strpos($settingsIndexSrc, 'portal_cancellation_policy_not_found_url') !== false
    && strpos($selectRoomJsSrc, 'occupancyLimitsFromCfg') !== false
    && strpos((string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-dates.js'), 'calendar_month_horizon') !== false
    && is_file(dirname(__DIR__) . '/booking/cancellation-policy.php')) {
    hb_pass('portal display config admin + portal JS/endpoint wiring');
} else {
    hb_fail('portal display config admin + portal JS/endpoint wiring');
}

if (is_file(dirname(__DIR__) . '/booking/cancellation_policy/404.html')
    && strpos((string) @file_get_contents(dirname(__DIR__) . '/booking/cancellation_policy/404.html'), 'Cancellation policy not available') !== false) {
    hb_pass('cancellation policy 404.html seed file');
} else {
    hb_fail('cancellation policy 404.html seed file missing');
}

$notFoundHtml = itm_hotel_booking_portal_cancellation_policy_not_found_html($conn, 1);
if (strpos($notFoundHtml, 'Cancellation policy not available') !== false
    || strpos($notFoundHtml, 'Cancellation policy not found') !== false) {
    hb_pass('cancellation policy not-found HTML helper');
} else {
    hb_fail('cancellation policy not-found HTML helper');
}

$guestCopyColsOk = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_manage_booking_label')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_accessible_room_banner_text')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_disabled_message')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_step_progress_template');
if ($guestCopyColsOk) {
    hb_pass('portal guest copy schema columns');
} else {
    hb_fail('portal guest copy schema columns missing — apply hotel_booking_portal_guest_copy.sql');
}

$manageLabel = itm_hotel_booking_portal_manage_booking_label_from_settings([]);
if ($manageLabel === 'Manage my booking') {
    hb_pass('portal manage booking label default');
} else {
    hb_fail('portal manage booking label default');
}

$progressLabel = itm_hotel_booking_portal_step_progress_label_from_settings([], 2, 4);
if ($progressLabel === 'Step 2 of 4') {
    hb_pass('portal step progress template default');
} else {
    hb_fail('portal step progress template default');
}

$customProgress = itm_hotel_booking_portal_step_progress_label_from_settings(['portal_step_progress_template' => 'Phase {step}/{total}'], 3, 4);
if ($customProgress === 'Phase 3/4') {
    hb_pass('portal step progress template substitution');
} else {
    hb_fail('portal step progress template substitution');
}

$stepHeading = itm_hotel_booking_portal_checkout_step_heading_from_settings([
    'portal_step_label_rate' => 'Pick a rate',
    'portal_step_progress_template' => 'Step {step} of {total}',
], 2);
if (($stepHeading['progress'] ?? '') === 'Step 2 of 4' && ($stepHeading['title'] ?? '') === 'Pick a rate') {
    hb_pass('portal checkout step heading helper');
} else {
    hb_fail('portal checkout step heading helper');
}

$roomsPhpSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms.php');
$selectRateSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/select-rate.php');
$customizeSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/customize.php');
$roomSingleSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/room-single.php');
if (strpos($roomsPhpSrc, 'itm_hotel_booking_portal_checkout_step_heading_from_settings') !== false
    && strpos($selectRateSrc, 'itm_hotel_booking_portal_checkout_step_heading_from_settings') !== false
    && strpos($customizeSrc, 'itm_hotel_booking_portal_checkout_step_heading_from_settings') !== false
    && strpos($roomSingleSrc, 'itm_hotel_booking_portal_checkout_step_heading_from_settings') !== false
    && strpos($roomsPhpSrc, 'itm_hotel_booking_portal_accessible_room_banner_text_from_settings') !== false) {
    hb_pass('portal guest copy checkout page wiring');
} else {
    hb_fail('portal guest copy checkout page wiring');
}

if (strpos($settingsIndexSrc, 'portal_manage_booking_label') !== false
    && strpos($settingsIndexSrc, 'portal_step_progress_template') !== false
    && strpos($settingsIndexSrc, 'portal_step_label_room') !== false
    && strpos($portalBookingSrc, 'itm_hotel_booking_portal_manage_booking_label_from_settings') !== false
    && strpos($portalBookingSrc, 'manage_booking_label_from_settings') !== false) {
    hb_pass('portal guest copy admin + helper wiring');
} else {
    hb_fail('portal guest copy admin + helper wiring');
}

if (strpos($portalBookingSrc, 'function itm_hotel_booking_portal_manage_booking_hint_html') !== false
    && strpos($portalBookingSrc, 'itm_hotel_booking_portal_manage_booking_label_from_settings($settings)') !== false) {
    hb_pass('manage booking hint uses label helper');
} else {
    hb_fail('manage booking hint uses label helper');
}

if (strpos($bootstrapSrc, 'itm_hotel_booking_portal_disabled_message_from_settings') !== false) {
    hb_pass('portal disabled message helper wired in bootstrap');
} else {
    hb_fail('portal disabled message helper wired in bootstrap');
}

$uiCopyRegistry = function_exists('itm_hotel_booking_portal_ui_copy_registry')
    ? itm_hotel_booking_portal_ui_copy_registry()
    : [];
if (count($uiCopyRegistry) >= 150
    && function_exists('itm_hotel_booking_portal_ui_copy_from_settings')
    && function_exists('itm_hotel_booking_portal_ui_copy_map_for_js')) {
    hb_pass('portal ui copy registry + getter helpers present');
} else {
    hb_fail('portal ui copy registry or getter helpers missing');
}

$uiCopyDefaultHome = itm_hotel_booking_portal_ui_copy_from_settings([], 'portal_ui_home_from_label');
if ($uiCopyDefaultHome === 'From') {
    hb_pass('portal ui copy getter returns registry default');
} else {
    hb_fail('portal ui copy getter default mismatch for portal_ui_home_from_label');
}

$uiCopyPlaceholder = itm_hotel_booking_portal_ui_copy_from_settings(
    ['portal_ui_step1_multi_room_banner_lead' => 'Room {current} of {total}'],
    'portal_ui_step1_multi_room_banner_lead',
    ['current' => 2, 'total' => 5]
);
if ($uiCopyPlaceholder === 'Room 2 of 5') {
    hb_pass('portal ui copy placeholder substitution');
} else {
    hb_fail('portal ui copy placeholder substitution failed');
}

$settingsModuleSrc = (string) @file_get_contents(dirname(__DIR__) . '/modules/hotel_booking_settings/index.php');
if (strpos($settingsModuleSrc, 'itm_hotel_booking_portal_ui_copy_registry') !== false
    && strpos($settingsModuleSrc, 'itm_hotel_booking_portal_ui_copy_validate_post_values') !== false
    && strpos($settingsModuleSrc, 'itm_hotel_booking_portal_ui_copy_save_values') !== false) {
    hb_pass('hotel booking settings admin portal ui copy form wiring');
} else {
    hb_fail('hotel booking settings admin portal ui copy form wiring missing');
}

$hbSettingsJs = (string) @file_get_contents(dirname(__DIR__) . '/includes/itm_hotel_booking.php');
if (strpos($hbSettingsJs, "'ui_copy'") !== false
    && strpos($hbSettingsJs, 'itm_hotel_booking_portal_ui_copy_map_for_js') !== false) {
    hb_pass('portal public settings exports ui_copy for JS');
} else {
    hb_fail('portal public settings ui_copy JS export missing');
}

$uiCopyColsOk = function_exists('itm_verify_db_migrations_column_exists')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_home', 'portal_ui_home_from_label')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_step1', 'portal_ui_step1_filter_king_bed')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_checkout', 'portal_ui_step4_book_reservation_button')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_confirm', 'portal_ui_confirm_rate_label')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_confirm', 'portal_ui_manage_lookup_failure')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_home', 'portal_ui_shared_modal_close')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_home', 'portal_ui_home_dates_loading')
    && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_portal_ui_copy_confirm', 'portal_ui_auth_sign_in_title');
if ($uiCopyColsOk) {
    hb_pass('portal ui copy satellite schema tables');
} else {
    hb_fail('portal ui copy satellite schema tables missing — apply hotel_booking_portal_ui_copy_gap.sql or fresh db/ import');
}

$portalCheckoutSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/includes/portal_checkout.php');
if (strpos($portalCheckoutSrc, "portal_ui_confirm_reservation_notes_heading") !== false) {
    hb_pass('portal checkout reservation notes heading uses portal_ui copy');
} else {
    hb_fail('portal checkout reservation notes heading not wired to portal_ui');
}

$authLoginSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/auth/login.php');
if (strpos($authLoginSrc, "portal_ui_auth_sign_in_title") !== false) {
    hb_pass('legacy auth login uses portal_ui copy');
} else {
    hb_fail('legacy auth login missing portal_ui copy');
}

$selectRoomJs = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-select-room.js');
if (strpos($selectRoomJs, 'HB_SELECT_ROOM.ui_copy') !== false) {
    hb_pass('select-room JS hbUiCopy reads HB_SELECT_ROOM.ui_copy');
} else {
    hb_fail('select-room JS hbUiCopy missing HB_SELECT_ROOM.ui_copy source');
}

$datesJs = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-dates.js');
if (strpos($datesJs, "hbUiCopy('home_dates_loading'") !== false) {
    hb_pass('dates modal JS uses portal_ui home_dates copy keys');
} else {
    hb_fail('dates modal JS missing portal_ui home_dates copy keys');
}

itm_script_output_end();
exit($fail > 0 ? 1 : 0);
