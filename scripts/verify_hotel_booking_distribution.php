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
    'hotel_booking_distribution_rate_plan_mappings',
    'hotel_booking_distribution_ari_restrictions',
    'hotel_booking_distribution_webhook_queue',
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

if (!function_exists('itm_hotel_booking_distribution_modify_booking')) {
    hbd_fail('modify helper missing');
} else {
    hbd_pass('modify helper loaded');
}

if (!function_exists('itm_hotel_booking_distribution_push_ari_to_webhook')) {
    hbd_fail('webhook push helper missing');
} else {
    hbd_pass('webhook push helper loaded');
}

$phase3Helpers = [
    'itm_hotel_booking_distribution_encrypt_secret',
    'itm_hotel_booking_distribution_verify_inbound_signature',
    'itm_hotel_booking_distribution_enqueue_webhook',
    'itm_hotel_booking_distribution_enrich_ari_snapshot',
    'itm_hotel_booking_distribution_ari_snapshot_checksum',
    'itm_hotel_booking_distribution_should_skip_delta_push',
    'itm_hotel_booking_distribution_build_reservation_ack',
    'itm_hotel_booking_distribution_build_reservation_nack',
    'itm_hotel_booking_distribution_mark_reservation_ack',
    'itm_hotel_booking_distribution_booking_com_api_request',
];
foreach ($phase3Helpers as $fn) {
    if (!function_exists($fn)) {
        hbd_fail("phase3 helper missing: {$fn}");
    } else {
        hbd_pass("phase3 helper {$fn}");
    }
}

$secretPlain = 'test_partner_secret_' . substr(sha1((string) microtime(true)), 0, 8);
$secretEnc = itm_hotel_booking_distribution_encrypt_secret($secretPlain);
if ($secretEnc !== '' && itm_hotel_booking_distribution_decrypt_secret($secretEnc) === $secretPlain) {
    hbd_pass('partner secret encrypt round-trip');
} else {
    hbd_fail('partner secret encrypt round-trip');
}

$signChannel = ['webhook_signing_secret_encrypted' => $secretEnc];
$rawBody = '{"test":"payload"}';
$_SERVER['HTTP_X_ITM_SIGNATURE'] = hash_hmac('sha256', $rawBody, $secretPlain);
if (itm_hotel_booking_distribution_verify_inbound_signature($signChannel, $rawBody)) {
    hbd_pass('inbound HMAC signature valid');
} else {
    hbd_fail('inbound HMAC signature valid');
}
unset($_SERVER['HTTP_X_ITM_SIGNATURE']);
$_SERVER['HTTP_X_ITM_SIGNATURE'] = 'invalid';
if (!itm_hotel_booking_distribution_verify_inbound_signature($signChannel, $rawBody)) {
    hbd_pass('inbound HMAC signature rejects invalid');
} else {
    hbd_fail('inbound HMAC signature rejects invalid');
}
unset($_SERVER['HTTP_X_ITM_SIGNATURE']);

$snapshotSample = [
    'success' => true,
    'hotel_id' => 1,
    'start_date' => '2026-12-01',
    'end_date' => '2026-12-03',
    'inventory' => [],
];
$checksum1 = itm_hotel_booking_distribution_ari_snapshot_checksum($snapshotSample);
$checksum2 = itm_hotel_booking_distribution_ari_snapshot_checksum($snapshotSample);
if ($checksum1 === $checksum2 && strlen($checksum1) === 64) {
    hbd_pass('ARI snapshot checksum stable');
} else {
    hbd_fail('ARI snapshot checksum stable');
}
$channelChecksum = ['last_ari_push_checksum' => $checksum1];
if (itm_hotel_booking_distribution_should_skip_delta_push($channelChecksum, $snapshotSample, false)) {
    hbd_pass('delta push skip when checksum matches');
} else {
    hbd_fail('delta push skip when checksum matches');
}
if (!itm_hotel_booking_distribution_should_skip_delta_push($channelChecksum, $snapshotSample, true)) {
    hbd_pass('force push bypasses delta skip');
} else {
    hbd_fail('force push bypasses delta skip');
}

$ackChannel = ['standard' => 'booking_com', 'partner_property_id' => '12345'];
$ack = itm_hotel_booking_distribution_build_reservation_ack($ackChannel, ['external_reservation_id' => 'EXT-1', 'reservation_id' => 99], 'book');
if (($ack['reservation']['acknowledgement'] ?? '') === 'ACK') {
    hbd_pass('booking_com ACK payload');
} else {
    hbd_fail('booking_com ACK payload');
}
$nack = itm_hotel_booking_distribution_build_reservation_nack($ackChannel, 'no_availability', 'Sold out');
if (($nack['acknowledgement'] ?? '') === 'NACK') {
    hbd_pass('booking_com NACK payload');
} else {
    hbd_fail('booking_com NACK payload');
}

$otaXml = '<?xml version="1.0"?><OTA_HotelAvailRQ xmlns="http://www.opentravel.org/OTA/2003/05"><AvailRequestSegments><AvailRequestSegment><StayDateRange Start="2026-12-01" End="2026-12-03"/><HotelSearchCriteria><Criterion><HotelRef HotelCode="HTL1"/></Criterion></HotelSearchCriteria></AvailRequestSegment></AvailRequestSegments></OTA_HotelAvailRQ>';
$parsed = itm_hotel_booking_distribution_opentravel_parse_request($otaXml);
if (($parsed['action'] ?? '') === 'availability' && ($parsed['payload']['check_in'] ?? '') === '2026-12-01') {
    hbd_pass('opentravel avail RQ parse');
} else {
    hbd_fail('opentravel avail RQ parse');
}

$availPayload = ['success' => true, 'currency_code' => 'EUR', 'external_hotel_code' => 'HTL1', 'room_types' => [['external_code' => 'STD', 'name' => 'Standard', 'available_rooms' => 2, 'total_amount' => 200]]];
$otaRs = itm_hotel_booking_distribution_opentravel_encode_response($availPayload, 'availability');
if (strpos($otaRs, 'OTA_HotelAvailRS') !== false && strpos($otaRs, 'RoomTypeCode="STD"') !== false) {
    hbd_pass('opentravel avail RS encode');
} else {
    hbd_fail('opentravel avail RS encode');
}

if (itm_hotel_booking_distribution_suggest_external_code('room_type', 'Standard Room', 3) === 'STD') {
    hbd_pass('suggest external code STD for Standard');
} else {
    hbd_fail('suggest external code STD for Standard');
}
if (itm_hotel_booking_distribution_suggest_external_code('hotel', 'TechCorp Retreat', 1) === 'HTL1') {
    hbd_pass('suggest external code HTL1 for hotel id 1');
} else {
    hbd_fail('suggest external code HTL1 for hotel id 1');
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
        $syncHotels = itm_hotel_booking_distribution_sync_hotel_mappings($conn, 1, $channelId, 1, false);
        $syncRooms = itm_hotel_booking_distribution_sync_room_type_mappings($conn, 1, $channelId, 1, false);
        if (($syncHotels['created'] ?? -1) >= 0 && ($syncRooms['created'] ?? -1) >= 0) {
            hbd_pass('OTA mapping sync helpers');
        } else {
            hbd_fail('OTA mapping sync helpers');
        }
        $mappedStd = itm_hotel_booking_distribution_mapping_external_code($conn, 1, $channelId, 'room_type', 3);
        if ($mappedStd !== '') {
            hbd_pass('room type mapping external_code populated after sync');
        } else {
            hbd_fail('room type mapping external_code populated after sync');
        }

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
