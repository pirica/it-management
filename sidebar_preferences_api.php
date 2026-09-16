<?php
/**
 * JSON API for live sidebar section collapse toggles (double-click headers).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/config/config.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!itm_try_post_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
if ($action !== 'toggle_section_collapse') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sectionId = trim((string)($_POST['section_id'] ?? ''));
$result = itm_toggle_employee_sidebar_section_collapsed($conn, $companyId, $employeeId, $sectionId);
if (empty($result['ok'])) {
    $error = (string)($result['error'] ?? 'toggle_failed');
    if ($error === 'feature_disabled') {
        http_response_code(403);
    } elseif ($error === 'invalid_section') {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'section_id' => (string)($result['section_id'] ?? $sectionId),
    'is_collapsed' => (int)($result['is_collapsed'] ?? 0),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
