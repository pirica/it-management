<?php
/**
 * Saved report views JSON API.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . 'includes/itm_saved_reports.php';

$companyId = (int) ($_SESSION['company_id'] ?? 0);
$employeeId = (int) ($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

itm_api_enforce_rate_limit_or_exit($conn);

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($method === 'POST') {
    if (!itm_try_post_csrf()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($action === 'save') {
    $payload = [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'id' => (int) ($_POST['id'] ?? 0),
        'module_slug' => (string) ($_POST['module_slug'] ?? ''),
        'name' => (string) ($_POST['name'] ?? ''),
        'shared_scope' => (string) ($_POST['shared_scope'] ?? 'private'),
        'filters' => json_decode((string) ($_POST['filters_json'] ?? '{}'), true),
        'columns' => json_decode((string) ($_POST['columns_json'] ?? '[]'), true),
    ];
    if (!is_array($payload['filters'])) {
        $payload['filters'] = [];
    }
    if (!is_array($payload['columns'])) {
        $payload['columns'] = [];
    }
    $result = itm_saved_reports_save($conn, $payload);
    if (!$result['ok']) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $viewId = (int) ($_POST['id'] ?? 0);
    $result = itm_saved_reports_soft_delete($conn, $viewId, $employeeId, $companyId);
    if (!$result['ok']) {
        http_response_code(403);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'list') {
    $moduleSlug = trim((string) ($_GET['module_slug'] ?? ''));
    $rows = itm_saved_reports_list_visible($conn, $companyId, $employeeId, $moduleSlug !== '' ? $moduleSlug : null);
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'module_slug' => (string) $row['module_slug'],
            'shared_scope' => (string) $row['shared_scope'],
            'owner' => (int) $row['employee_id'] === $employeeId,
            'list_url' => itm_saved_reports_build_list_url((string) $row['module_slug'], $row['filters'] ?? []),
            'filters' => $row['filters'] ?? [],
            'columns' => $row['columns'] ?? [],
        ];
    }
    echo json_encode(['ok' => true, 'views' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'run') {
    $viewId = (int) ($_GET['id'] ?? 0);
    $limit = (int) ($_GET['limit'] ?? 100);
    $offset = (int) ($_GET['offset'] ?? 0);
    $row = itm_saved_reports_fetch_by_id($conn, $viewId, $companyId);
    if (!$row || !itm_saved_reports_can_view($conn, $row, $employeeId, $companyId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Saved view not found or access denied.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $query = itm_saved_reports_run_query($conn, $companyId, $row, ['limit' => $limit, 'offset' => $offset]);
    if (!$query['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $query['error']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'id' => $viewId,
        'name' => (string) ($row['name'] ?? ''),
        'module_slug' => (string) ($row['module_slug'] ?? ''),
        'total' => $query['total'],
        'columns' => $query['columns'],
        'labels' => $query['labels'],
        'rows' => $query['rows'],
        'limit' => $limit,
        'offset' => $offset,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
