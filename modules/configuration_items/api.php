<?php
/**
 * Configuration Items JSON API — impact subgraph (BFS).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_cmdb.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

itm_api_enforce_rate_limit_or_exit($conn);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));

if ($action === 'impact' && $method === 'GET') {
    $ciId = (int)($_GET['id'] ?? 0);
    if ($ciId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid configuration item id.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $graph = itm_cmdb_build_impact_graph($conn, $companyId, $ciId);
    echo json_encode(['ok' => true, 'graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'add_relationship' && $method === 'POST') {
    itm_require_post_csrf();
    $ciId = (int)($_POST['ci_id'] ?? 0);
    $relatedCiId = (int)($_POST['related_ci_id'] ?? 0);
    $direction = strtolower(trim((string)($_POST['direction'] ?? 'upstream')));
    $relationshipType = strtolower(trim((string)($_POST['relationship_type'] ?? 'depends_on')));
    $types = itm_cmdb_relationship_types();
    if (!isset($types[$relationshipType])) {
        $relationshipType = 'depends_on';
    }
    if ($ciId <= 0 || $relatedCiId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Select two configuration items.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($direction === 'downstream') {
        $parentCiId = $ciId;
        $childCiId = $relatedCiId;
    } else {
        $parentCiId = $relatedCiId;
        $childCiId = $ciId;
    }
    $result = itm_cmdb_add_relationship($conn, $companyId, $parentCiId, $childCiId, $relationshipType, $employeeId);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => (string)($result['error'] ?? 'Failed.')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => (int)($result['id'] ?? 0)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete_relationship' && $method === 'POST') {
    itm_require_post_csrf();
    $relationshipId = (int)($_POST['relationship_id'] ?? 0);
    if ($relationshipId <= 0 || !itm_cmdb_delete_relationship($conn, $companyId, $relationshipId, $employeeId)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not remove relationship.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action. Use impact, add_relationship, or delete_relationship.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
