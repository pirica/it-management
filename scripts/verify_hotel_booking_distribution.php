<?php
/**
 * Hotel booking distribution API regression checks.
 *
 * CLI: php scripts/verify_hotel_booking_distribution.php
 * Browser: scripts/verify_hotel_booking_distribution.php?run=1 (Administrator).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_hotel_booking_distribution.php?run=1">verify_hotel_booking_distribution.php?run=1</a> (Administrator). CLI: <code>php scripts/verify_hotel_booking_distribution.php</code> — exit <code>1</code> on failure.
<p>Regression for <code>hotel_booking_distribution_*</code> tables, API key helpers, channel lookup, availability builder, and <code>modules/hotel_booking_api/api.php</code> probe contract.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Hotel booking distribution verification');

$fail = 0;
function hbd_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function hbd_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$tables = [
    'hotel_booking_distribution_channels',
    'hotel_booking_distribution_mappings',
    'hotel_booking_distribution_reservations',
    'hotel_booking_distribution_ari_events',
];
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    if ($res && mysqli_num_rows($res) > 0) {
        hbd_pass("table {$t}");
    } else {
        hbd_fail("missing table {$t} — apply db/migrations/hotel_booking_distribution.sql");
    }
}

if (!function_exists('itm_hotel_booking_distribution_generate_api_key')) {
    hbd_fail('itm_hotel_booking_distribution_generate_api_key missing');
} else {
    hbd_pass('distribution helper loaded');
}

$plainKey = itm_hotel_booking_distribution_generate_api_key();
$hash = itm_hotel_booking_distribution_hash_api_key($plainKey);
if (itm_hotel_booking_distribution_verify_api_key($plainKey, $hash)) {
    hbd_pass('api key hash round-trip');
} else {
    hbd_fail('api key hash round-trip');
}

$standards = itm_hotel_booking_distribution_standards();
if (isset($standards['itm_native'], $standards['opentravel'])) {
    hbd_pass('distribution standards map');
} else {
    hbd_fail('distribution standards map incomplete');
}

$apiFile = dirname(__DIR__) . '/modules/hotel_booking_api/api.php';
if (is_file($apiFile) && strpos(file_get_contents($apiFile), 'ITM_HOTEL_BOOKING_DISTRIBUTION_API') !== false) {
    hbd_pass('modules/hotel_booking_api/api.php defines distribution bypass');
} else {
    hbd_fail('modules/hotel_booking_api/api.php missing or bypass constant absent');
}

$companyId = 1;
$channelCode = 'verify_' . substr(sha1((string) microtime(true)), 0, 8);
$prefix = itm_hotel_booking_distribution_api_key_prefix($plainKey);
$ins = mysqli_prepare(
    $conn,
    'INSERT INTO hotel_booking_distribution_channels (company_id, channel_code, name, standard, api_key_prefix, api_key_hash, hourly_rate_limit, active, created_at)
     VALUES (?, ?, ?, \'itm_native\', ?, ?, 1000, 1, NOW())'
);
$channelId = 0;
if ($ins) {
    $name = 'Verify channel';
    mysqli_stmt_bind_param($ins, 'issss', $companyId, $channelCode, $name, $prefix, $hash);
    if (mysqli_stmt_execute($ins)) {
        $channelId = (int) mysqli_insert_id($conn);
        hbd_pass('disposable channel insert');
    } else {
        hbd_fail('disposable channel insert');
    }
    mysqli_stmt_close($ins);
} else {
    hbd_fail('disposable channel prepare');
}

if ($channelId > 0) {
    $channel = itm_hotel_booking_distribution_lookup_channel_by_api_key($conn, $plainKey);
    if ($channel && (int) ($channel['id'] ?? 0) === $channelId) {
        hbd_pass('channel lookup by api key');
    } else {
        hbd_fail('channel lookup by api key');
    }

    $hotelRes = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = 1 AND deleted_at IS NULL AND active = 1 LIMIT 1');
    $hotelRow = $hotelRes ? mysqli_fetch_assoc($hotelRes) : null;
    $hotelId = (int) ($hotelRow['id'] ?? 0);
    if ($hotelId > 0) {
        $avail = itm_hotel_booking_distribution_build_availability(
            $conn,
            $channel,
            $hotelId,
            '2026-12-01',
            '2026-12-03',
            ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0]
        );
        if (!empty($avail['success']) && isset($avail['room_types']) && is_array($avail['room_types'])) {
            hbd_pass('availability builder');
        } else {
            hbd_fail('availability builder: ' . json_encode($avail));
        }
    } else {
        hbd_fail('no seed hotel for company 1');
    }

    mysqli_query($conn, 'DELETE FROM hotel_booking_distribution_channels WHERE id = ' . (int) $channelId);
    hbd_pass('disposable channel cleanup');
}

itm_script_output_end($fail === 0 ? 0 : 1);
