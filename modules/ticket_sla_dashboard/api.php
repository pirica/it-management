<?php
/**
 * SLA Command Center JSON API — summary counts and filtered ticket lists.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once dirname(__DIR__, 2) . '/config/config.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

itm_api_enforce_rate_limit_or_exit($conn);

$action = strtolower(trim((string)($_GET['action'] ?? 'summary')));

if ($action === 'summary') {
    $summary = itm_ticket_sla_count_summary($conn, $companyId);
    echo json_encode([
        'ok' => true,
        'summary' => $summary,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'list') {
    $filter = strtolower(trim((string)($_GET['filter'] ?? 'at_risk')));
    $allowed = ['at_risk', 'breached', 'met', 'all'];
    if (!in_array($filter, $allowed, true)) {
        $filter = 'at_risk';
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? itm_resolve_records_per_page($ui_config ?? null));
    $list = itm_ticket_sla_list_by_filter($conn, $companyId, $filter, $page, $perPage);
    $rows = [];
    foreach ($list['rows'] as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'ticket_external_code' => (string)($row['ticket_external_code'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'status_name' => (string)($row['status_name'] ?? ''),
            'priority_name' => (string)($row['priority_name'] ?? ''),
            'sla_state' => (string)($row['sla_state'] ?? ''),
            'sla_response_due_at' => (string)($row['sla_response_due_at'] ?? ''),
            'sla_resolve_due_at' => (string)($row['sla_resolve_due_at'] ?? ''),
            'assigned_to_username' => (string)($row['assigned_to_username'] ?? ''),
            'view_url' => BASE_URL . 'modules/tickets/view.php?id=' . (int)$row['id'],
        ];
    }
    echo json_encode([
        'ok' => true,
        'filter' => $filter,
        'page' => (int)$list['page'],
        'per_page' => (int)$list['per_page'],
        'total' => (int)$list['total'],
        'total_pages' => (int)$list['total_pages'],
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action. Use summary or list.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
