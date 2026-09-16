<?php
/**
 * Network Discovery JSON API — promote, link, dismiss staging rows.
 */
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

header('Content-Type: application/json; charset=utf-8');

itm_api_enforce_rate_limit_or_exit($conn);

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

function nd_api_json_error(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    nd_api_json_error(405, 'POST required.');
}

itm_require_post_csrf();

$stagingId = (int)($_POST['staging_id'] ?? 0);
if ($stagingId <= 0) {
    nd_api_json_error(400, 'staging_id is required.');
}

if ($action === 'promote') {
    $createIpam = !empty($_POST['create_ipam']);
    $result = itm_network_discovery_promote_staging($conn, $companyId, $stagingId, $employeeId, $createIpam);
    if (empty($result['ok'])) {
        nd_api_json_error(400, (string)($result['error'] ?? 'Promote failed.'));
    }
    echo json_encode([
        'success' => true,
        'equipment_id' => (int)($result['equipment_id'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'link') {
    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $createIpam = !empty($_POST['create_ipam']);
    $result = itm_network_discovery_link_staging($conn, $companyId, $stagingId, $equipmentId, $employeeId, $createIpam);
    if (empty($result['ok'])) {
        nd_api_json_error(400, (string)($result['error'] ?? 'Link failed.'));
    }
    echo json_encode([
        'success' => true,
        'equipment_id' => (int)($result['equipment_id'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'dismiss') {
    $result = itm_network_discovery_dismiss_staging($conn, $companyId, $stagingId, $employeeId);
    if (empty($result['ok'])) {
        nd_api_json_error(400, (string)($result['error'] ?? 'Dismiss failed.'));
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

nd_api_json_error(400, 'Unknown action.');
