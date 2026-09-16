<?php
/**
 * Problem Management JSON API — known-error suggestions for ticket text.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

itm_api_enforce_rate_limit_or_exit($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = strtolower(trim((string)($_GET['action'] ?? '')));
if ($action !== 'suggest') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action. Use suggest.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$title = trim((string)($_GET['title'] ?? ''));
$description = trim((string)($_GET['description'] ?? ''));
$limit = max(1, min(10, (int)($_GET['limit'] ?? 5)));

$suggestions = itm_known_error_suggest_for_ticket($conn, $companyId, $title, $description, $limit);
$rows = [];
foreach ($suggestions as $row) {
    $rows[] = [
        'known_error_id' => (int)($row['known_error_id'] ?? 0),
        'problem_id' => (int)($row['problem_id'] ?? 0),
        'ke_title' => (string)($row['ke_title'] ?? ''),
        'workaround' => (string)($row['workaround'] ?? ''),
        'symptom_keywords' => (string)($row['symptom_keywords'] ?? ''),
        'problem_title' => (string)($row['problem_title'] ?? ''),
        'problem_status' => (string)($row['problem_status'] ?? ''),
        'knowledge_base_id' => (int)($row['knowledge_base_id'] ?? 0),
        'match_score' => (int)($row['match_score'] ?? 0),
        'view_url' => BASE_URL . 'modules/problems/view.php?id=' . (int)($row['problem_id'] ?? 0),
    ];
}

echo json_encode([
    'ok' => true,
    'count' => count($rows),
    'suggestions' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
