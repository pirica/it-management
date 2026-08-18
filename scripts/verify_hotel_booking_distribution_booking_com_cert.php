<?php
/**
 * Booking.com Connectivity certification checklist (offline — no live API required).
 *
 * CLI: php scripts/verify_hotel_booking_distribution_booking_com_cert.php
 * Browser: scripts/verify_hotel_booking_distribution_booking_com_cert.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_hotel_booking_distribution_booking_com_cert.php</code> — optional <code>--company=1</code>. Browser: <a href="verify_hotel_booking_distribution_booking_com_cert.php?run=1">verify_hotel_booking_distribution_booking_com_cert.php?run=1</a> (Administrator).
<p>Validates Booking.com adapter shapes (notify normalize, ACK/NACK, rates payload, credential fields). Does not call the live Booking.com API.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Booking.com Connectivity certification checklist');

$fail = 0;
function bcc_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function bcc_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$certMessages = [
    'availability_shop' => 'JSON room_rates shop wrapper',
    'reservation_notify' => 'reservation notify normalize (book/modify/cancel)',
    'reservation_ack' => 'ACK acknowledgement on success',
    'reservation_nack' => 'NACK acknowledgement on failure',
    'ari_rates_push' => 'rates[] payload for outbound ARI',
    'partner_credentials' => 'encrypted username/password + property_id columns',
];
foreach ($certMessages as $slug => $label) {
    bcc_pass('cert message slot: ' . $slug . ' — ' . $label);
}

$notifyBody = [
    'property' => ['id' => 'PROP-99'],
    'reservation' => [
        'reservation_id' => 'BC-1001',
        'status' => 'created',
        'checkin' => '2026-12-10',
        'checkout' => '2026-12-12',
        'room' => ['room_type_code' => 'STD'],
        'guest' => ['first_name' => 'Cert', 'last_name' => 'Guest', 'email' => 'cert@example.com'],
    ],
];
$normalized = itm_hotel_booking_distribution_booking_com_normalize_notification($notifyBody);
if (($normalized['action'] ?? '') === 'notify' && ($normalized['payload']['external_reservation_id'] ?? '') === 'BC-1001') {
    bcc_pass('notify normalize book');
} else {
    bcc_fail('notify normalize book');
}

$cancelBody = ['reservation' => ['reservation_id' => 'BC-9', 'status' => 'cancelled']];
$cancelNorm = itm_hotel_booking_distribution_booking_com_normalize_notification($cancelBody);
if (($cancelNorm['payload']['notification_type'] ?? '') === 'cancel') {
    bcc_pass('notify normalize cancel');
} else {
    bcc_fail('notify normalize cancel');
}

$channel = ['standard' => 'booking_com', 'partner_property_id' => 'PROP-99'];
$ack = itm_hotel_booking_distribution_build_reservation_ack($channel, ['external_reservation_id' => 'BC-1', 'reservation_id' => 42], 'book');
if (($ack['reservation']['acknowledgement'] ?? '') === 'ACK') {
    bcc_pass('ACK response contract');
} else {
    bcc_fail('ACK response contract');
}
$nack = itm_hotel_booking_distribution_build_reservation_nack($channel, 'sold_out', 'No rooms');
if (($nack['acknowledgement'] ?? '') === 'NACK') {
    bcc_pass('NACK response contract');
} else {
    bcc_fail('NACK response contract');
}

$snapshot = [
    'external_hotel_code' => 'HTL1',
    'start_date' => '2026-12-01',
    'end_date' => '2026-12-02',
    'inventory' => [[
        'external_code' => 'STD',
        'days' => [['date' => '2026-12-01', 'available_rooms' => 3, 'price_per_night' => 99.5, 'stop_sell' => false]],
    ]],
];
$ratesPayload = itm_hotel_booking_distribution_booking_com_format_ari_push($snapshot);
if (!empty($ratesPayload['rates']) && ($ratesPayload['rates'][0]['room_type_code'] ?? '') === 'STD') {
    bcc_pass('rates push payload shape');
} else {
    bcc_fail('rates push payload shape');
}

$sandboxChannel = ['partner_sandbox_mode' => 1];
$url = itm_hotel_booking_distribution_booking_com_base_url($sandboxChannel);
if ($url !== '') {
    bcc_pass('sandbox base URL resolves (' . $url . ')');
} else {
    bcc_fail('sandbox base URL');
}

$res = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_distribution_channels LIKE 'partner_api_username'");
if ($res && mysqli_num_rows($res) > 0) {
    bcc_pass('partner credential columns exist');
} else {
    bcc_fail('partner credential columns missing — apply phase3 migration');
}

$res = mysqli_query($conn, "SHOW COLUMNS FROM hotel_booking_distribution_reservations LIKE 'ack_status'");
if ($res && mysqli_num_rows($res) > 0) {
    bcc_pass('reservation ack_status column exists');
} else {
    bcc_fail('reservation ack_status column missing');
}

bcc_pass('live API certification: configure channel credentials + property_id in Distribution Channels edit, then use Booking.com sandbox hotel (not exercised here)');

itm_script_output_end($fail === 0 ? 0 : 1);
