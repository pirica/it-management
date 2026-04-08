<?php
/**
 * IDF API Bootstrap
 * 
 * Shared initialization for all IDF-related API endpoints.
 * Handles:
 * - Session/Company authentication verification.
 * - JSON content-type header enforcement.
 * - POST method enforcement.
 * - Helper functions for standard JSON responses and input parsing.
 */

require_once __DIR__ . '/../../../config/config.php';

// All API responses are JSON
header('Content-Type: application/json; charset=utf-8');

// Ensure we only accept POST for state-changing operations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Ensure the user is actually logged in
if (!isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);

/**
 * Reads the raw JSON body of the request.
 */
function idf_read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

/**
 * Validates the CSRF token against the session.
 * Accepts token via JSON body, POST field, or X-CSRF-TOKEN header.
 */
function idf_require_csrf(array $data): void {
    $token = (string)($data['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!itm_validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

/**
 * Terminates the request with a successful JSON payload.
 */
function idf_ok(array $payload = []): void {
    echo json_encode(array_merge(['ok' => true], $payload));
    exit;
}

/**
 * Terminates the request with an error JSON payload.
 */
function idf_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

/**
 * Helper to escape strings for legacy mysqli queries.
 */
function idf_escape(mysqli $conn, ?string $s): string {
    return mysqli_real_escape_string($conn, (string)($s ?? ''));
}
