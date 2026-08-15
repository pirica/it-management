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
    && itm_hotel_booking_auth2_matches('0042', '0042')
    && itm_hotel_booking_auth2_matches($auth2Sample, $auth2Sample)) {
    hb_pass('guest auth2 generate/normalize/match');
} else {
    hb_fail('guest auth2 generate/normalize/match got ' . $auth2Sample);
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
if (hb_portal_money_format(69.5, 'EUR') === '€69.50' && hb_portal_money_format(77.0, 'EUR') === '€77') {
    hb_pass('portal money format keeps NR cents (69.50) without rounding to 70');
} else {
    hb_fail('portal money format expected €69.50 / €77 got ' . hb_portal_money_format(69.5, 'EUR') . ' / ' . hb_portal_money_format(77.0, 'EUR'));
}

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
    && strpos($publicJsSrc, 'Info</a>') !== false
    && strpos($publicJsSrc, 'Email</a>') !== false) {
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
if (is_file($planningJs) && strpos((string) file_get_contents($planningJs), 'planning_move') !== false && strpos((string) file_get_contents($planningJs), 'hb-plan-draggable') !== false && strpos((string) file_get_contents($planningJs), 'openHkMaintModal') !== false) {
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

$portalCheckoutPhp = dirname(__DIR__) . '/booking/includes/portal_checkout.php';
$portalCheckoutBody = is_file($portalCheckoutPhp) ? (string) file_get_contents($portalCheckoutPhp) : '';
if (
    $portalCheckoutBody !== ''
    && strpos($portalCheckoutBody, 'data-hb-pdf-manage-link="1"') !== false
    && strpos($portalCheckoutBody, 'data-hb-manage-url') !== false
) {
    hb_pass('booking confirmation Manage my booking PDF link markup');
} else {
    hb_fail('booking confirmation Manage my booking PDF link markup missing');
}

if (
    $portalCheckoutBody !== ''
    && strpos($portalCheckoutBody, '<dt>Full name</dt>') !== false
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
        hb_pass('booking stay-bar occupancy interactive only on rooms.php');
    }
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
    $slice = $fnPos !== false ? substr($lockSrc, $fnPos, 1800) : '';
    $bad = itm_hotel_booking_portal_insert_booking_locked($conn, 0, 0, 0, '', '', 0, '', 0, '', '', 0, 0, 0);
    if (stripos($slice, 'FOR UPDATE') !== false && empty($bad['ok'])) {
        hb_pass('portal insert booking locked uses FOR UPDATE');
    } else {
        hb_fail('portal insert booking locked contract');
    }
} else {
    hb_fail('portal insert booking locked helper missing');
}

$roomSingleSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/rooms/room-single.php');
$bootstrapSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/bootstrap.php');
if (strpos($roomSingleSrc, 'itm_hotel_booking_portal_insert_stay_bookings_locked') !== false
    && strpos($roomSingleSrc, 'itm_hotel_booking_portal_resolve_step4_charge') !== false
    && strpos($roomSingleSrc, 'itm_hotel_booking_portal_send_booking_confirmation_emails') !== false
    && strpos($roomSingleSrc, 'Lock quoted stay to draft occupancy') !== false
    && strpos($roomSingleSrc, "parse_occupancy(\$_POST)") === false
    && strpos($roomSingleSrc, "Checkout session expired. Please start your reservation again.") !== false
    && strpos($roomSingleSrc, "!\$draft || empty(\$draft['room_id'])") !== false
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
    && strpos($roomsPhpSrc, 'hb_select_room_book_href') !== false
    && strpos($selectRateSrc, 'hb_portal_render_room_lines_summary') !== false
    && strpos($selectRateSrc, 'count($roomLines) < $roomsNeeded') !== false
    && strpos($portalBookingSrc, 'itm_hotel_booking_portal_split_occupancy_for_room_line') !== false) {
    hb_pass('portal multi-room pick queue + stay insert wiring');
} else {
    hb_fail('portal multi-room pick queue + stay insert wiring missing');
}

if (strpos($portalBookingSrc, 'function itm_hotel_booking_portal_send_booking_confirmation_emails') !== false
    && strpos($portalBookingSrc, 'hotel_reservations_email') !== false
    && strpos($portalBookingSrc, 'function itm_hotel_booking_portal_load_confirmation_group_rows') !== false) {
    hb_pass('portal step4 confirmation emails to guest and reservations desk');
} else {
    hb_fail('portal step4 confirmation email helper missing');
}

$portalCheckoutPaymentSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/includes/portal_checkout.php');
if (strpos($portalCheckoutPaymentSrc, 'hb-payment-room-group-list') !== false
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
if (strpos($portalCheckoutSrcMulti, 'itm_hotel_booking_portal_draft_room_lines_for_display') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-reservation-summary-room-list') !== false
    && strpos($portalCheckoutSrcMulti, 'hb-reservation-room-line-price') !== false
    && strpos($portalCheckoutSrcMulti, 'itm_hotel_booking_portal_room_line_stay_charges') !== false
    && strpos($customizeSrc, 'itm_hotel_booking_portal_draft_room_lines_for_display') !== false) {
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
        && strpos($portalCheckoutSrcMulti, 'groupRoomDisplayAmounts') !== false) {
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

itm_script_output_end();
exit($fail > 0 ? 1 : 0);
