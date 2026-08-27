<?php
/**
 * Portal date format regression (booking/* + admin settings).
 *
 * CLI: php scripts/verify_hotel_booking_portal_date_formats.php
 * Browser: scripts/verify_hotel_booking_portal_date_formats.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_hotel_booking_portal_date_formats.php?run=1">verify_hotel_booking_portal_date_formats.php?run=1</a> (Administrator). CLI: <code>php scripts/verify_hotel_booking_portal_date_formats.php</code> — exit <code>1</code> on failure.
<p>Validates all four <code>portal_date_format</code> values in PHP helpers, JS mirror of <code>hotel-booking-date-format.js</code>, schema enum, admin settings UI, and booking portal wiring.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_hotel_booking.php';
require_once ROOT_PATH . 'booking/bootstrap.php';
require_once ROOT_PATH . 'booking/includes/portal_chrome.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_verify_db_migrations_report.php';

itm_script_output_begin('Hotel booking portal date formats');

$fail = 0;
function hb_date_fmt_fail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function hb_date_fmt_pass(string $msg): void
{
    echo "[PASS] {$msg}\n";
}

/**
 * Mirror of booking/js/hotel-booking-date-format.js formatDateYmd() for regression.
 */
function hb_date_fmt_js_mirror(string $ymd, string $dateFormat): string
{
    $parts = explode('-', $ymd);
    if (count($parts) !== 3) {
        return '';
    }
    $y = (int) $parts[0];
    $m = (int) $parts[1];
    $d = (int) $parts[2];
    if ($y <= 0 || $m < 1 || $m > 12 || $d < 1) {
        return '';
    }
    $monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $pad2 = static function (int $n): string {
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    };
    if ($dateFormat === 'us_mmddyyyy') {
        return $pad2($m) . '/' . $pad2($d) . '/' . $y;
    }
    if ($dateFormat === 'iso_yyyymmdd') {
        return $y . '-' . $pad2($m) . '-' . $pad2($d);
    }
    if ($dateFormat === 'european_ddmmmyyyy') {
        return $pad2($d) . '/' . $monthsShort[$m - 1] . '/' . $y;
    }
    return $pad2($d) . '/' . $pad2($m) . '/' . $y;
}

$sampleYmd = '2026-08-17';
$expected = [
    'european_ddmmyyyy' => '17/08/2026',
    'european_ddmmmyyyy' => '17/Aug/2026',
    'us_mmddyyyy' => '08/17/2026',
    'iso_yyyymmdd' => '2026-08-17',
];

foreach ($expected as $fmt => $want) {
    $phpGot = itm_hotel_booking_portal_format_date_display($sampleYmd, ['portal_date_format' => $fmt]);
    if ($phpGot !== $want) {
        hb_date_fmt_fail("PHP format {$fmt} expected {$want} got {$phpGot}");
    } else {
        hb_date_fmt_pass("PHP format {$fmt} => {$want}");
    }

    $jsGot = hb_date_fmt_js_mirror($sampleYmd, $fmt);
    if ($jsGot !== $want) {
        hb_date_fmt_fail("JS mirror {$fmt} expected {$want} got {$jsGot}");
    } else {
        hb_date_fmt_pass("JS mirror {$fmt} => {$want}");
    }

    hb_portal_bind_money_settings(['portal_date_format' => $fmt]);
    $portalGot = hb_portal_format_date_display($sampleYmd);
    if ($portalGot !== $want) {
        hb_date_fmt_fail("hb_portal_format_date_display {$fmt} expected {$want} got {$portalGot}");
    } else {
        hb_date_fmt_pass("hb_portal_format_date_display {$fmt}");
    }
}
unset($GLOBALS['hb_portal_money_settings']);

$colType = itm_verify_db_migrations_column_type($conn, 'hotel_booking_settings', 'portal_date_format');
if (strpos($colType, 'european_ddmmmyyyy') !== false) {
    hb_date_fmt_pass('schema portal_date_format enum includes european_ddmmmyyyy');
} else {
    hb_date_fmt_fail('schema portal_date_format enum missing european_ddmmmyyyy — apply db/ or migration');
}

$settingsIndexSrc = (string) @file_get_contents(dirname(__DIR__) . '/modules/hotel_booking_settings/index.php');
$dateJsSrc = (string) @file_get_contents(dirname(__DIR__) . '/booking/js/hotel-booking-date-format.js');
if (strpos($settingsIndexSrc, 'value="european_ddmmmyyyy"') !== false
    && strpos($settingsIndexSrc, 'DD/MMM/YYYY') !== false
    && strpos($dateJsSrc, 'european_ddmmmyyyy') !== false
    && strpos($dateJsSrc, 'MONTHS_SHORT') !== false) {
    hb_date_fmt_pass('admin settings + hotel-booking-date-format.js wiring');
} else {
    hb_date_fmt_fail('admin settings or hotel-booking-date-format.js missing DD/MMM/YYYY wiring');
}

$bookingFiles = [
    'index.php',
    'rooms.php',
    'rooms/select-rate.php',
    'rooms/customize.php',
    'rooms/room-single.php',
    'rooms/payment.php',
    'rooms/confirmation-pdf.php',
    'users/bookings.php',
    'includes/portal_chrome.php',
    'includes/portal_checkout.php',
];
$bookingRoot = dirname(__DIR__) . '/booking/';
foreach ($bookingFiles as $rel) {
    $path = $bookingRoot . $rel;
    if (!is_file($path)) {
        hb_date_fmt_fail("missing booking file {$rel}");
        continue;
    }
    $src = (string) @file_get_contents($path);
    $usesPhpFormat = strpos($src, 'hb_portal_format_date_display') !== false
        || strpos($src, 'hb_portal_format_stay_range_label') !== false
        || strpos($src, 'hb_portal_render_date_input') !== false
        || strpos($src, 'portal_chrome.php') !== false;
    $usesJsFormat = strpos($src, 'hotel-booking-date-format.js') !== false
        || strpos($src, 'hb_portal_render_date_format_scripts') !== false
        || strpos($src, 'hbPortalFormatDateYmd') !== false
        || strpos($src, 'itmHotelDateFormatYmd') !== false;
    if ($usesPhpFormat || $usesJsFormat) {
        hb_date_fmt_pass("booking/{$rel} date format wiring");
    } else {
        hb_date_fmt_fail("booking/{$rel} has no portal date format wiring");
    }
}

$selectRateSrc = (string) @file_get_contents($bookingRoot . 'rooms/select-rate.php');
if (strpos($selectRateSrc, "date('F jS, Y'") !== false) {
    hb_date_fmt_fail('select-rate.php still uses hardcoded English cancel deadline date()');
} else {
    hb_date_fmt_pass('select-rate.php cancel deadline uses portal formatter');
}

$roomSingleSrc = (string) @file_get_contents($bookingRoot . 'rooms/room-single.php');
if (strpos($roomSingleSrc, 'hb_portal_render_date_input') === false) {
    hb_date_fmt_fail('room-single.php must use hb_portal_render_date_input for editable dates');
} else {
    hb_date_fmt_pass('room-single.php editable dates use hb_portal_render_date_input');
}

$hotelDateInputJs = (string) @file_get_contents(dirname(__DIR__) . '/js/hotel-date-input.js');
if (strpos($hotelDateInputJs, 'hbPortalFormatDateYmd') === false) {
    hb_date_fmt_fail('js/hotel-date-input.js must delegate display to hbPortalFormatDateYmd');
} else {
    hb_date_fmt_pass('js/hotel-date-input.js delegates to portal date formatter');
}

$cancelDeadline = itm_hotel_booking_portal_format_free_cancel_deadline_display('2026-08-27', 5, ['portal_date_format' => 'european_ddmmmyyyy']);
if ($cancelDeadline !== '22/Aug/2026') {
    hb_date_fmt_fail("free cancel deadline expected 22/Aug/2026 got {$cancelDeadline}");
} else {
    hb_date_fmt_pass('free cancel deadline respects european_ddmmmyyyy');
}

if ($fail > 0) {
    itm_script_output_end(1, "{$fail} failure(s)");
    exit(1);
}

itm_script_output_end(0, 'All portal date format checks passed');
exit(0);
