<?php
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);

function idf_read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function idf_require_csrf(array $data): void {
    $token = (string)($data['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!itm_validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function idf_ok(array $payload = []): void {
    echo json_encode(array_merge(['ok' => true], $payload));
    exit;
}

function idf_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function idf_escape(mysqli $conn, ?string $s): string {
    return mysqli_real_escape_string($conn, (string)($s ?? ''));
}
