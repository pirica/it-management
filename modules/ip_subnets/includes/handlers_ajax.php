<?php
// Ping IP / port check from list view (AJAX).
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($crud_action, ['index', 'list_all'], true)
    && isset($_POST['ping_ip_check'])
) {
    header('Content-Type: application/json; charset=utf-8');
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Active company is required.']);
        exit;
    }

    if (!function_exists('itm_ipam_run_ping_port_check')) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ping tools are not available.']);
        exit;
    }

    $pingPayload = itm_ipam_run_ping_port_check(
        (string)($_POST['ping_ip'] ?? ''),
        (string)($_POST['ping_port'] ?? '')
    );
    if (empty($pingPayload['ok'])) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => (string)($pingPayload['error'] ?? 'Ping check failed.'),
        ]);
        exit;
    }

    echo json_encode($pingPayload);
    exit;
}

// Network discovery scan (TCP, no exec).
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($crud_action, ['index', 'list_all'], true)
    && isset($_POST['network_discovery_scan'])
) {
    header('Content-Type: application/json; charset=utf-8');
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Active company is required.']);
        exit;
    }

    $rangeStart = (string)($_POST['range_start'] ?? '');
    $rangeEnd = (string)($_POST['range_end'] ?? '');
    $batchOffset = max(0, (int)($_POST['batch_offset'] ?? 0));
    $batchSize = max(1, min(25, (int)($_POST['batch_size'] ?? 5)));
    $useBatch = isset($_POST['batch_offset']) || isset($_POST['batch_size']);

    if ($useBatch && function_exists('itm_ipam_network_discovery_scan_batch')) {
        $scanPayload = itm_ipam_network_discovery_scan_batch(
            $conn,
            (int)$company_id,
            $rangeStart,
            $rangeEnd,
            $batchOffset,
            $batchSize
        );
    } elseif (!function_exists('itm_ipam_network_discovery_scan')) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Network discovery is not available.']);
        exit;
    } else {
        $scanPayload = itm_ipam_network_discovery_scan(
            $conn,
            (int)$company_id,
            $rangeStart,
            $rangeEnd
        );
    }
    if (empty($scanPayload['ok'])) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => (string)($scanPayload['error'] ?? 'Network discovery scan failed.'),
        ]);
        exit;
    }

    echo json_encode($scanPayload);
    exit;
}

// Import discovered hosts into ip_addresses inventory.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($crud_action, ['index', 'list_all'], true)
    && isset($_POST['network_discovery_import'])
) {
    header('Content-Type: application/json; charset=utf-8');
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Active company is required.']);
        exit;
    }

    if (
        !function_exists('itm_ipam_network_discovery_import_hosts')
        && !function_exists('itm_ipam_network_discovery_import_hosts_batch')
    ) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Network discovery import is not available.']);
        exit;
    }

    $hostIpsRaw = trim((string)($_POST['host_ips'] ?? ''));
    $hostIps = [];
    if ($hostIpsRaw !== '') {
        $decoded = json_decode($hostIpsRaw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $hostIp) {
                $hostIps[] = (string)$hostIp;
            }
        }
    }
    if ($hostIps === []) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No discovered hosts were selected for import.']);
        exit;
    }

    $batchOffset = max(0, (int)($_POST['batch_offset'] ?? 0));
    $batchSize = max(1, min(25, (int)($_POST['batch_size'] ?? 5)));
    $useBatch = isset($_POST['batch_offset']) || isset($_POST['batch_size']);

    if ($useBatch && function_exists('itm_ipam_network_discovery_import_hosts_batch')) {
        $importPayload = itm_ipam_network_discovery_import_hosts_batch(
            $conn,
            (int)$company_id,
            $hostIps,
            $batchOffset,
            $batchSize
        );
    } elseif (!function_exists('itm_ipam_network_discovery_import_hosts')) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Network discovery import is not available.']);
        exit;
    } else {
        $importPayload = itm_ipam_network_discovery_import_hosts($conn, (int)$company_id, $hostIps);
    }
    if (empty($importPayload['ok'])) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => (string)($importPayload['error'] ?? 'Import failed.'),
        ]);
        exit;
    }

    echo json_encode($importPayload);
    exit;
}

