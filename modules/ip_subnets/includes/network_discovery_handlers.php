<?php
/**
 * POST handlers for network discovery profiles (shared by ip_subnets tabs and network_discovery module).
 */

declare(strict_types=1);

if (!isset($ndFlash)) {
    $ndFlash = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nd_action'])) {
    itm_require_post_csrf();
    $postAction = trim((string)($_POST['nd_action'] ?? ''));

    if ($postAction === 'save_profile' && $isAdmin) {
        $subnetIds = $_POST['subnet_ids'] ?? [];
        if (!is_array($subnetIds)) {
            $subnetIds = [];
        }
        $result = itm_network_discovery_save_profile($conn, $companyId, [
            'id' => (int)($_POST['profile_id'] ?? 0),
            'name' => $_POST['name'] ?? '',
            'schedule_cron' => $_POST['schedule_cron'] ?? '',
            'snmp_enabled' => $_POST['snmp_enabled'] ?? '',
            'enabled' => $_POST['enabled'] ?? '',
            'auto_create_policy' => $_POST['auto_create_policy'] ?? 'review',
            'subnet_ids' => $subnetIds,
        ], $employeeId);
        $ndFlash = !empty($result['ok']) ? 'Profile saved.' : (string)($result['error'] ?? 'Could not save profile.');
    } elseif ($postAction === 'delete_profile' && $isAdmin) {
        $result = itm_network_discovery_delete_profile($conn, $companyId, (int)($_POST['profile_id'] ?? 0), $employeeId);
        $ndFlash = !empty($result['ok']) ? 'Profile deleted.' : (string)($result['error'] ?? 'Could not delete profile.');
    } elseif ($postAction === 'run_profile' && $isAdmin) {
        $profileId = (int)($_POST['profile_id'] ?? 0);
        $batch = itm_network_discovery_profile_run_batch($conn, $profileId, $employeeId);
        $ndFlash = !empty($batch['ok'])
            ? (string)($batch['detail'] ?? 'Scan batch finished.')
            : (string)($batch['error'] ?? 'Scan failed.');
    }
}
