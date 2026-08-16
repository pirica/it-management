<?php
/**
 * Global command-palette search API (phase 1: SQL LIKE per module).
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';
require_once '../../includes/itm_api_rate_limit.php';
require_once '../../includes/itm_command_palette_search.php';

itm_api_enforce_rate_limit_or_exit($conn);

if (!isset($_SESSION['employee_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$companyId = (int)$_SESSION['company_id'];
$employeeId = (int)$_SESSION['employee_id'];

$query = '';
$perModuleLimit = 5;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = trim((string)($_GET['q'] ?? $_GET['query'] ?? ''));
    $perModuleLimit = (int)($_GET['limit'] ?? 5);
} else {
    $rawBody = file_get_contents('php://input');
    $data = is_string($rawBody) ? json_decode($rawBody, true) : null;
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $csrfToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!itm_validate_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $query = trim((string)($data['query'] ?? $data['q'] ?? ''));
    $perModuleLimit = (int)($data['limit'] ?? 5);
}

if (strlen($query) > 200) {
    http_response_code(400);
    echo json_encode(['error' => 'Query too long.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = itm_command_palette_search($conn, $companyId, $employeeId, $query, $perModuleLimit);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
