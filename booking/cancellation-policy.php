<?php
/**
 * Guest-facing cancellation policy HTML (DB or seed file via shared loader).
 */
require __DIR__ . '/bootstrap.php';

$companyId = (int) ($_GET['company_id'] ?? 0);
$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['slug'] ?? 'room_only')));
if ($slug === '') {
    $slug = 'room_only';
}

if ($companyId < 1 || $hotelId < 1) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Invalid cancellation policy request.</p>';
    exit;
}

hb_require_company_public_portal($conn, $companyId, ['json' => false, 'redirect' => APPURL . '/']);

$planRow = itm_hotel_booking_portal_rate_plan_row_by_slug($conn, $companyId, $hotelId, $slug);
if (!$planRow) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Cancellation policy not found.</p>';
    exit;
}

$html = itm_hotel_booking_load_cancellation_policy_html($planRow);
if ($html === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Cancellation policy content is not available.</p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
