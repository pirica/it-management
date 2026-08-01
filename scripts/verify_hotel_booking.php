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
]);
if ($contactsSample['name'] === 'TechCorp Retreat'
    && $contactsSample['phone'] === '+351 210 000 001'
    && strpos(hb_portal_hotel_directions_url($contactsSample['location']), 'maps.google.com') !== false
    && hb_portal_hotel_phone_tel_href($contactsSample['phone']) === 'tel:+351210000001') {
    hb_pass('portal change booking hotel contacts helpers');
} else {
    hb_fail('portal change booking hotel contacts helpers');
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
if (is_file($bookingConfirmationPdfJs) && strpos((string) file_get_contents($bookingConfirmationPdfJs), 'hbSaveBookingConfirmationPdf') !== false) {
    hb_pass('booking confirmation pdf download script');
} else {
    hb_fail('booking confirmation pdf download script missing');
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
if (!is_file($bookingGalleryJs)) {
    hb_fail('booking/js/hotel-booking-gallery.js missing');
} else {
    $galleryJs = (string) file_get_contents($bookingGalleryJs);
    $galleryCss = is_file($bookingGalleryCss) ? (string) file_get_contents($bookingGalleryCss) : '';
    if (strpos($galleryJs, 'HB_bindGallery') === false || strpos($galleryJs, ' / ') === false) {
        hb_fail('booking gallery JS must expose HB_bindGallery and spaced counter format');
    } elseif (strpos($galleryCss, '.hb-gallery-counter') === false || strpos($galleryCss, '.hb-gallery-prev') === false) {
        hb_fail('booking gallery CSS must style arrows and counter');
    } else {
        hb_pass('booking portal photo gallery arrows and counter');
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

exit($fail > 0 ? 1 : 0);
