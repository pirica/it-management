<?php
/**
 * Stripe Checkout regression (hotel booking portal).
 *
 * CLI: php scripts/verify_stripe_checkout.php
 * Browser: scripts/verify_stripe_checkout.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_stripe_checkout.php?run=1">verify_stripe_checkout.php?run=1</a> (Administrator). CLI: <code>php scripts/verify_stripe_checkout.php</code> — exit <code>1</code> on failure.
<p>Table/column checks, encrypt round-trip, webhook signature probe, mock <code>checkout.session.completed</code> payload validation. No live Stripe API key required.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_stripe_checkout.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Stripe Checkout verification');

$fail = 0;
function stripe_verify_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function stripe_verify_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$tables = ['hotel_booking_payment_events'];
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    if ($res && mysqli_num_rows($res) > 0) {
        stripe_verify_pass("table {$t}");
    } else {
        stripe_verify_fail("missing table {$t} — apply db/migrations/hotel_booking_stripe.sql");
    }
}

$bookingCols = ['payment_status', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'amount_paid'];
foreach ($bookingCols as $col) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `hotel_bookings` LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
    if ($res && mysqli_num_rows($res) > 0) {
        stripe_verify_pass("hotel_bookings.{$col}");
    } else {
        stripe_verify_fail("missing column hotel_bookings.{$col}");
    }
}

$settingsCols = ['stripe_enabled', 'stripe_mode', 'stripe_publishable_key', 'stripe_secret_key_encrypted', 'stripe_webhook_signing_secret_encrypted', 'deposit_percent'];
foreach ($settingsCols as $col) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `hotel_booking_settings` LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
    if ($res && mysqli_num_rows($res) > 0) {
        stripe_verify_pass("hotel_booking_settings.{$col}");
    } else {
        stripe_verify_fail("missing column hotel_booking_settings.{$col}");
    }
}

$plain = 'sk_test_verify_' . bin2hex(random_bytes(4));
$enc = itm_stripe_checkout_encrypt_secret($plain);
$dec = itm_stripe_checkout_decrypt_secret($enc);
if ($enc !== '' && $dec === $plain) {
    stripe_verify_pass('encrypt/decrypt round-trip');
} else {
    stripe_verify_fail('encrypt/decrypt round-trip');
}

$payload = '{"id":"evt_test"}';
$secret = 'whsec_test_secret_for_verify';
$timestamp = time();
$signed = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
$header = 't=' . $timestamp . ',v1=' . $signed;
if (itm_stripe_verify_webhook_signature($payload, $header, $secret)) {
    stripe_verify_pass('webhook signature valid probe');
} else {
    stripe_verify_fail('webhook signature valid probe');
}
if (!itm_stripe_verify_webhook_signature($payload, $header, 'wrong_secret')) {
    stripe_verify_pass('webhook signature rejects wrong secret');
} else {
    stripe_verify_fail('webhook signature rejects wrong secret');
}

$mockEvent = [
    'type' => 'checkout.session.completed',
    'data' => [
        'object' => [
            'id' => 'cs_test_mock',
            'payment_intent' => 'pi_test_mock',
            'amount_total' => 12500,
            'metadata' => [
                'company_id' => '1',
                'booking_id' => '99',
            ],
        ],
    ],
];
$validation = itm_stripe_validate_session_payload($mockEvent);
if (!empty($validation['ok']) && (int) ($validation['company_id'] ?? 0) === 1 && (int) ($validation['booking_id'] ?? 0) === 99) {
    stripe_verify_pass('mock session payload validation');
} else {
    stripe_verify_fail('mock session payload validation');
}

$badEvent = ['type' => 'payment_intent.succeeded', 'data' => ['object' => []]];
$badValidation = itm_stripe_validate_session_payload($badEvent);
if (empty($badValidation['ok'])) {
    stripe_verify_pass('non-checkout event rejected by validator');
} else {
    stripe_verify_fail('non-checkout event rejected by validator');
}

$webhookFile = dirname(__DIR__) . '/booking/stripe-webhook.php';
if (is_file($webhookFile) && strpos(file_get_contents($webhookFile), 'ITM_STRIPE_WEBHOOK') !== false) {
    stripe_verify_pass('booking/stripe-webhook.php defines ITM_STRIPE_WEBHOOK');
} else {
    stripe_verify_fail('booking/stripe-webhook.php missing ITM_STRIPE_WEBHOOK');
}

$paymentStripeFile = dirname(__DIR__) . '/booking/payment-stripe.php';
if (is_file($paymentStripeFile)) {
    stripe_verify_pass('booking/payment-stripe.php exists');
} else {
    stripe_verify_fail('booking/payment-stripe.php missing');
}

$deposit = itm_stripe_checkout_compute_charge_amount(200.00, 50);
if ($deposit === 100.0) {
    stripe_verify_pass('deposit percent math');
} else {
    stripe_verify_fail('deposit percent math expected 100 got ' . $deposit);
}

itm_script_output_end($fail === 0 ? 0 : 1, $fail === 0 ? 'All Stripe Checkout checks passed.' : "{$fail} check(s) failed.");
