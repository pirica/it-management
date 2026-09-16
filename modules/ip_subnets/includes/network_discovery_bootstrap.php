<?php
/**
 * Bootstrap variables for network discovery UI partials.
 */

declare(strict_types=1);

if (!function_exists('itm_network_discovery_list_profiles')) {
    require_once ROOT_PATH . 'includes/itm_network_discovery.php';
}
if (!function_exists('itm_background_jobs_profile_has_active_scan')) {
    require_once ROOT_PATH . 'includes/itm_background_jobs.php';
}

$ndFlash = $ndFlash ?? '';
$ndApiBase = $ndApiBase ?? '../network_discovery/api.php';
$ndSubnetListUrl = $ndSubnetListUrl ?? 'index.php';
$ndProfilesTabUrl = $ndProfilesTabUrl ?? 'index.php?tab=profiles';
$ndStagingTabUrl = $ndStagingTabUrl ?? 'index.php?tab=staging';
$ndExternalModuleUrl = $ndExternalModuleUrl ?? '../network_discovery/index.php';

if (isset($ipSubnetsTab) && in_array($ipSubnetsTab, ['profiles', 'staging'], true)) {
    $activeTab = $ipSubnetsTab;
} else {
    $activeTab = strtolower(trim((string)($_GET['tab'] ?? 'staging')));
    if (!in_array($activeTab, ['profiles', 'staging'], true)) {
        $activeTab = 'staging';
    }
}

$profiles = itm_network_discovery_list_profiles($conn, $companyId);
$stagingStatus = trim((string)($_GET['status'] ?? 'pending'));
$profileFilter = (int)($_GET['profile_id'] ?? 0);
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$stagingData = itm_network_discovery_list_staging($conn, $companyId, $stagingStatus, $profileFilter, $page, $perPage);
$stagingRows = $stagingData['rows'] ?? [];
$stagingTotal = (int)($stagingData['total'] ?? 0);
$stagingPages = max(1, (int)ceil($stagingTotal / $perPage));

$subnetOptions = [];
$subnetStmt = mysqli_prepare(
    $conn,
    'SELECT id, cidr, description FROM ip_subnets WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY cidr ASC'
);
if ($subnetStmt) {
    mysqli_stmt_bind_param($subnetStmt, 'i', $companyId);
    mysqli_stmt_execute($subnetStmt);
    $subnetRes = mysqli_stmt_get_result($subnetStmt);
    while ($subnetRes && ($subnetRow = mysqli_fetch_assoc($subnetRes))) {
        $subnetOptions[] = $subnetRow;
    }
    mysqli_stmt_close($subnetStmt);
}

$equipmentOptions = [];
$eqStmt = mysqli_prepare(
    $conn,
    'SELECT id, name, hostname, ip_address FROM equipment WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC LIMIT 500'
);
if ($eqStmt) {
    mysqli_stmt_bind_param($eqStmt, 'i', $companyId);
    mysqli_stmt_execute($eqStmt);
    $eqRes = mysqli_stmt_get_result($eqStmt);
    while ($eqRes && ($eqRow = mysqli_fetch_assoc($eqRes))) {
        $equipmentOptions[] = $eqRow;
    }
    mysqli_stmt_close($eqStmt);
}
