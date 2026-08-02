<?php
/**
 * OpenTravel OTA message coverage regression (parse + encode round-trips).
 *
 * CLI: php scripts/verify_hotel_booking_distribution_opentravel_coverage.php
 * Browser: scripts/verify_hotel_booking_distribution_opentravel_coverage.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_hotel_booking_distribution_opentravel_coverage.php</code>. Browser: <a href="verify_hotel_booking_distribution_opentravel_coverage.php?run=1">verify_hotel_booking_distribution_opentravel_coverage.php?run=1</a> (Administrator).
<p>Covers <code>OTA_HotelAvailRQ</code>, <code>OTA_HotelResNotifRQ</code>, <code>OTA_HotelAvailNotifRQ</code>, <code>OTA_PingRQ</code> parse and matching RS encode paths.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('OpenTravel OTA message coverage');

$fail = 0;
function ota_cov_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function ota_cov_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$availXml = '<?xml version="1.0"?><OTA_HotelAvailRQ xmlns="http://www.opentravel.org/OTA/2003/05"><AvailRequestSegments><AvailRequestSegment><StayDateRange Start="2026-12-01" End="2026-12-03"/><HotelSearchCriteria><Criterion><HotelRef HotelCode="HTL1"/></Criterion></HotelSearchCriteria></AvailRequestSegment></AvailRequestSegments></OTA_HotelAvailRQ>';
$parsed = itm_hotel_booking_distribution_opentravel_parse_request($availXml);
if (($parsed['action'] ?? '') === 'availability') {
    ota_cov_pass('OTA_HotelAvailRQ parse');
} else {
    ota_cov_fail('OTA_HotelAvailRQ parse');
}

$resXml = '<?xml version="1.0"?><OTA_HotelResNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" ResStatus="Book"><HotelReservations><HotelReservation><UniqueID ID="OTA-55"/><RoomStays><RoomStay><TimeSpan Start="2026-12-05" End="2026-12-07"/><RoomTypes><RoomType RoomTypeCode="STD"/></RoomTypes></RoomStay></RoomStays><ResGuests><ResGuest><Profiles><ProfileInfo><Profile><Customer><PersonName><GivenName>Ann</GivenName><Surname>Tester</Surname></PersonName><Email>ann@example.com</Email></Customer></Profile></ProfileInfo></Profiles></ResGuest></ResGuests><BasicPropertyInfo HotelCode="HTL1"/></HotelReservation></HotelReservations></OTA_HotelResNotifRQ>';
$parsedRes = itm_hotel_booking_distribution_opentravel_parse_request($resXml);
if (($parsedRes['action'] ?? '') === 'notify' && ($parsedRes['payload']['external_reservation_id'] ?? '') === 'OTA-55') {
    ota_cov_pass('OTA_HotelResNotifRQ parse');
} else {
    ota_cov_fail('OTA_HotelResNotifRQ parse');
}

$ariXml = '<?xml version="1.0"?><OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"><AvailStatusMessages HotelCode="HTL1"><AvailStatusMessage BookingLimit="4"><StatusApplicationControl Start="2026-12-08" End="2026-12-08" InvTypeCode="STD"/><LengthsOfStay><LengthOfStay Time="110" TimeUnit="Day"/></LengthsOfStay><RestrictionStatus Status="Close"/></AvailStatusMessage></AvailStatusMessages></OTA_HotelAvailNotifRQ>';
$parsedAri = itm_hotel_booking_distribution_opentravel_parse_request($ariXml);
if (($parsedAri['action'] ?? '') === 'ari_push'
    && !empty($parsedAri['payload']['stop_sell'])
    && ($parsedAri['payload']['rates'][0]['price_per_night'] ?? 0) == 110) {
    ota_cov_pass('OTA_HotelAvailNotifRQ parse (ari_push + stop-sell)');
} else {
    ota_cov_fail('OTA_HotelAvailNotifRQ parse');
}

$pingXml = '<?xml version="1.0"?><OTA_PingRQ xmlns="http://www.opentravel.org/OTA/2003/05"><EchoData>ping</EchoData></OTA_PingRQ>';
$parsedPing = itm_hotel_booking_distribution_opentravel_parse_request($pingXml);
if (($parsedPing['action'] ?? '') === 'probe') {
    ota_cov_pass('OTA_PingRQ parse');
} else {
    ota_cov_fail('OTA_PingRQ parse');
}

$availRs = itm_hotel_booking_distribution_opentravel_encode_response([
    'success' => true,
    'currency_code' => 'EUR',
    'external_hotel_code' => 'HTL1',
    'room_types' => [['external_code' => 'STD', 'name' => 'Standard', 'available_rooms' => 2, 'total_amount' => 200]],
], 'availability');
if (strpos($availRs, 'OTA_HotelAvailRS') !== false) {
    ota_cov_pass('OTA_HotelAvailRS encode');
} else {
    ota_cov_fail('OTA_HotelAvailRS encode');
}

$ariRs = itm_hotel_booking_distribution_opentravel_encode_response([
    'success' => true,
    'external_hotel_code' => 'HTL1',
    'inventory' => [[
        'external_code' => 'STD',
        'days' => [['date' => '2026-12-01', 'available_rooms' => 2, 'price_per_night' => 99, 'stop_sell' => true, 'closed_to_arrival' => 1]],
    ]],
], 'ari_snapshot');
if (strpos($ariRs, 'RestrictionStatus') !== false && strpos($ariRs, 'OTA_HotelAvailNotifRS') !== false) {
    ota_cov_pass('OTA_HotelAvailNotifRS encode with restrictions');
} else {
    ota_cov_fail('OTA_HotelAvailNotifRS encode with restrictions');
}

$resRs = itm_hotel_booking_distribution_opentravel_encode_response([
    'success' => true,
    'external_reservation_id' => 'OTA-1',
    'reservation_id' => 7,
    'status' => 'confirmed',
], 'book');
if (strpos($resRs, 'OTA_HotelResNotifRS') !== false) {
    ota_cov_pass('OTA_HotelResNotifRS encode');
} else {
    ota_cov_fail('OTA_HotelResNotifRS encode');
}

$ariPushRs = itm_hotel_booking_distribution_opentravel_encode_response(['success' => true], 'ari_push');
if (strpos($ariPushRs, 'OTA_HotelAvailNotifRS') !== false) {
    ota_cov_pass('OTA_HotelAvailNotifRS encode (ari_push ack)');
} else {
    ota_cov_fail('OTA_HotelAvailNotifRS encode (ari_push ack)');
}

itm_script_output_end($fail === 0 ? 0 : 1);
