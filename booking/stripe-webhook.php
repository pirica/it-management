<?php
/**
 * Stripe webhook endpoint for hotel booking Checkout (no employee session).
 */
define('ITM_STRIPE_WEBHOOK', true);
require __DIR__ . '/bootstrap.php';
require_once ROOT_PATH . 'includes/itm_stripe_checkout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Empty payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$companyId = (int) ($_GET['company_id'] ?? 0);
if ($companyId < 1) {
    $tmp = json_decode($payload, true);
    if (is_array($tmp)) {
        $object = $tmp['data']['object'] ?? null;
        if (is_array($object) && is_array($object['metadata'] ?? null)) {
            $companyId = (int) ($object['metadata']['company_id'] ?? 0);
        }
    }
}

if ($companyId < 1) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'company_id required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$webhookSecret = itm_stripe_checkout_webhook_secret($conn, $companyId);
if ($webhookSecret === '' || !itm_stripe_verify_webhook_signature($payload, $sigHeader, $webhookSecret)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid signature'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$result = itm_stripe_handle_webhook_event($conn, $event);
header('Content-Type: application/json; charset=utf-8');
if (empty($result['ok'])) {
    http_response_code(500);
    echo json_encode(['error' => (string) ($result['error'] ?? 'Handler failed')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['received' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
