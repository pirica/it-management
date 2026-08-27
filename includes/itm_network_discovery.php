<?php
/**
 * Network Discovery v2 — scheduled profiles, staging queue, promote/link/dismiss.
 */

declare(strict_types=1);

if (!function_exists('itm_network_discovery_batch_size')) {
    function itm_network_discovery_batch_size(): int
    {
        return 10;
    }
}

if (!function_exists('itm_network_discovery_max_ips_per_profile')) {
    function itm_network_discovery_max_ips_per_profile(): int
    {
        return 2048;
    }
}

if (!function_exists('itm_network_discovery_table_exists')) {
    function itm_network_discovery_table_exists(mysqli $conn, string $table): bool
    {
        if (!function_exists('itm_ipam_table_exists')) {
            require_once ROOT_PATH . 'includes/ipam_helpers.php';
        }
        return itm_ipam_table_exists($conn, $table);
    }
}

if (!function_exists('itm_network_discovery_auto_create_policies')) {
    /** @return array<int, string> */
    function itm_network_discovery_auto_create_policies(): array
    {
        return ['none', 'review', 'equipment'];
    }
}

if (!function_exists('itm_network_discovery_decode_subnet_ids')) {
    /** @return array<int, int> */
    function itm_network_discovery_decode_subnet_ids(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $ids = [];
        foreach ($decoded as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('itm_network_discovery_snmp_sysname')) {
    /**
     * Phase 2 SNMP — uses PHP snmp extension when present; otherwise returns null.
     */
    function itm_network_discovery_snmp_sysname(string $ip, bool $enabled): ?string
    {
        if (!$enabled || !function_exists('snmpget')) {
            return null;
        }
        $community = trim((string)getenv('ITM_NETWORK_DISCOVERY_SNMP_COMMUNITY'));
        if ($community === '') {
            $community = 'public';
        }
        $timeout = 500000;
        $retries = 1;
        $oid = '1.3.6.1.2.1.1.5.0';
        try {
            @snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
            $value = @snmpget($ip, $community, $oid, $timeout, $retries);
            if ($value === false || $value === null) {
                return null;
            }
            $value = trim((string)$value);
            return $value !== '' ? $value : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('itm_network_discovery_build_profile_ip_list')) {
    /**
     * @param array<int, int> $subnetIds
     * @return array{ok: bool, ips: array<int, string>, error: string, capped: bool}
     */
    function itm_network_discovery_build_profile_ip_list(mysqli $conn, int $companyId, array $subnetIds): array
    {
        if ($companyId <= 0) {
            return ['ok' => false, 'ips' => [], 'error' => 'Company is required.', 'capped' => false];
        }
        if ($subnetIds === []) {
            return ['ok' => false, 'ips' => [], 'error' => 'Select at least one subnet.', 'capped' => false];
        }
        if (!function_exists('itm_ipam_host_addresses_for_subnet')) {
            require_once ROOT_PATH . 'includes/ipam_helpers.php';
        }

        $ips = [];
        $max = itm_network_discovery_max_ips_per_profile();
        $placeholders = implode(',', array_fill(0, count($subnetIds), '?'));
        $types = 'i' . str_repeat('i', count($subnetIds));
        $sql = 'SELECT id, network_ip, prefix_length FROM ip_subnets WHERE company_id = ? AND deleted_at IS NULL AND active = 1 AND id IN (' . $placeholders . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'ips' => [], 'error' => 'Could not load subnets.', 'capped' => false];
        }
        $params = array_merge([$companyId], $subnetIds);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $subnetIps = itm_ipam_host_addresses_for_subnet(
                (string)($row['network_ip'] ?? ''),
                (int)($row['prefix_length'] ?? 0),
                512
            );
            foreach ($subnetIps as $ip) {
                $ips[] = $ip;
                if (count($ips) >= $max) {
                    mysqli_stmt_close($stmt);
                    return ['ok' => true, 'ips' => $ips, 'error' => '', 'capped' => true];
                }
            }
        }
        mysqli_stmt_close($stmt);

        return ['ok' => true, 'ips' => array_values(array_unique($ips)), 'error' => '', 'capped' => false];
    }
}

if (!function_exists('itm_network_discovery_enrich_host')) {
    /**
     * @param array<string, mixed> $host
     * @return array<string, mixed>
     */
    function itm_network_discovery_enrich_host(array $host, bool $snmpEnabled): array
    {
        $ip = (string)($host['ip'] ?? '');
        $portUsed = (int)($host['port_used'] ?? 0);
        $openPorts = $portUsed > 0 ? [$portUsed] : [80, 443, 22, 135, 3389];
        if (!function_exists('itm_ipam_http_fingerprint')) {
            require_once ROOT_PATH . 'includes/ipam_helpers.php';
        }
        $http = itm_ipam_http_fingerprint($ip, $openPorts);
        $host['http_server'] = (string)($http['server'] ?? '');
        $host['http_title'] = (string)($http['title'] ?? '');
        $host['http_port'] = $http['port'] ?? null;
        $host['snmp_sysname'] = itm_network_discovery_snmp_sysname($ip, $snmpEnabled);
        return $host;
    }
}

if (!function_exists('itm_network_discovery_hostname_guess_from_host')) {
    function itm_network_discovery_hostname_guess_from_host(array $host): string
    {
        $snmp = trim((string)($host['snmp_sysname'] ?? ''));
        if ($snmp !== '') {
            return $snmp;
        }
        $domainPrimary = trim((string)($host['domain_primary'] ?? ''));
        if ($domainPrimary !== '' && function_exists('itm_ipam_domain_to_hostname_hint')) {
            $hint = itm_ipam_domain_to_hostname_hint($domainPrimary);
            if ($hint !== '') {
                return $hint;
            }
        }
        $equipmentLabel = trim((string)($host['equipment_label'] ?? ''));
        if ($equipmentLabel !== '') {
            return $equipmentLabel;
        }
        $httpTitle = trim((string)($host['http_title'] ?? ''));
        if ($httpTitle !== '') {
            return $httpTitle;
        }
        return (string)($host['ip'] ?? '');
    }
}

if (!function_exists('itm_network_discovery_upsert_staging_host')) {
    /**
     * @param array<string, mixed> $host
     */
    function itm_network_discovery_upsert_staging_host(
        mysqli $conn,
        int $companyId,
        int $profileId,
        array $host,
        int $employeeId = 0
    ): void {
        $ip = trim((string)($host['ip'] ?? ''));
        if ($companyId <= 0 || $profileId <= 0 || $ip === '') {
            return;
        }

        $probePayload = [
            'port_used' => $host['port_used'] ?? null,
            'response_ms' => $host['response_ms'] ?? null,
            'subnet_id' => (int)($host['subnet_id'] ?? 0),
            'subnet_cidr' => (string)($host['subnet_cidr'] ?? ''),
            'equipment_id' => (int)($host['equipment_id'] ?? 0),
            'equipment_label' => (string)($host['equipment_label'] ?? ''),
            'domains' => $host['domains'] ?? [],
            'domain_primary' => (string)($host['domain_primary'] ?? ''),
            'http_server' => (string)($host['http_server'] ?? ''),
            'http_title' => (string)($host['http_title'] ?? ''),
            'http_port' => $host['http_port'] ?? null,
            'snmp_sysname' => $host['snmp_sysname'] ?? null,
            'in_inventory' => !empty($host['in_inventory']),
            'inventory_id' => (int)($host['inventory_id'] ?? 0),
        ];
        $probeJson = json_encode($probePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hostnameGuess = itm_network_discovery_hostname_guess_from_host($host);

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, status FROM network_discovery_staging
             WHERE company_id = ? AND profile_id = ? AND ip_address = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'iis', $companyId, $profileId, $ip);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $existing = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if ($existing) {
            $status = (string)($existing['status'] ?? 'pending');
            if ($status !== 'pending') {
                return;
            }
            $stagingId = (int)($existing['id'] ?? 0);
            $upd = mysqli_prepare(
                $conn,
                'UPDATE network_discovery_staging
                 SET hostname_guess = ?, probe_json = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ?'
            );
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'ssiii', $hostnameGuess, $probeJson, $employeeId, $stagingId, $companyId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
            return;
        }

        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO network_discovery_staging
             (company_id, profile_id, ip_address, hostname_guess, probe_json, status, created_by, active, created_at)
             VALUES (?, ?, ?, ?, ?, \'pending\', ?, 1, NOW())'
        );
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'iisssi', $companyId, $profileId, $ip, $hostnameGuess, $probeJson, $employeeId);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

if (!function_exists('itm_network_discovery_fetch_profile')) {
    function itm_network_discovery_fetch_profile(mysqli $conn, int $profileId, int $companyId): ?array
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM network_discovery_profiles
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $profileId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_network_discovery_profile_run_batch')) {
    /**
     * @return array{ok: bool, error?: string, complete?: bool, found?: int, scanned?: int, detail?: string}
     */
    function itm_network_discovery_profile_run_batch(mysqli $conn, int $profileId, int $employeeId = 0): array
    {
        if (!itm_network_discovery_table_exists($conn, 'network_discovery_profiles')) {
            return ['ok' => false, 'error' => 'Network discovery tables are not installed.'];
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM network_discovery_profiles WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Profile lookup failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $profileId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $profile = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$profile || (int)($profile['enabled'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'Profile not found or disabled.'];
        }

        $companyId = (int)($profile['company_id'] ?? 0);
        $snmpEnabled = (int)($profile['snmp_enabled'] ?? 0) === 1;
        $autoPolicy = (string)($profile['auto_create_policy'] ?? 'review');
        $batchSize = itm_network_discovery_batch_size();
        $inProgress = (int)($profile['scan_in_progress'] ?? 0) === 1;
        $offset = (int)($profile['scan_offset'] ?? 0);
        $ips = [];

        if ($inProgress) {
            $decoded = json_decode((string)($profile['scan_ips_json'] ?? '[]'), true);
            if (is_array($decoded)) {
                foreach ($decoded as $ip) {
                    $ip = trim((string)$ip);
                    if ($ip !== '') {
                        $ips[] = $ip;
                    }
                }
            }
        } else {
            $subnetIds = itm_network_discovery_decode_subnet_ids((string)($profile['subnet_ids_json'] ?? '[]'));
            $built = itm_network_discovery_build_profile_ip_list($conn, $companyId, $subnetIds);
            if (empty($built['ok'])) {
                return ['ok' => false, 'error' => (string)($built['error'] ?? 'Could not build IP list.')];
            }
            $ips = $built['ips'] ?? [];
            $ipsJson = json_encode($ips, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $start = mysqli_prepare(
                $conn,
                'UPDATE network_discovery_profiles
                 SET scan_in_progress = 1, scan_offset = 0, scan_ips_json = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ?'
            );
            if ($start) {
                mysqli_stmt_bind_param($start, 'sii', $ipsJson, $profileId, $companyId);
                mysqli_stmt_execute($start);
                mysqli_stmt_close($start);
            }
            $offset = 0;
            $inProgress = true;
        }

        if ($ips === []) {
            mysqli_query($conn, 'UPDATE network_discovery_profiles SET scan_in_progress = 0, scan_offset = 0, scan_ips_json = NULL, last_run_at = NOW() WHERE id = ' . (int)$profileId);
            return ['ok' => true, 'complete' => true, 'found' => 0, 'scanned' => 0, 'detail' => 'No addresses to scan.'];
        }

        if (!function_exists('itm_ipam_network_discovery_scan_ips_batch')) {
            require_once ROOT_PATH . 'includes/ipam_helpers.php';
        }

        $batch = itm_ipam_network_discovery_scan_ips_batch($conn, $companyId, $ips, $offset, $batchSize);
        if (empty($batch['ok'])) {
            return ['ok' => false, 'error' => (string)($batch['error'] ?? 'Scan batch failed.')];
        }

        $found = 0;
        foreach ($batch['hosts'] ?? [] as $host) {
            $enriched = itm_network_discovery_enrich_host($host, $snmpEnabled);
            itm_network_discovery_upsert_staging_host($conn, $companyId, $profileId, $enriched, $employeeId);
            $found++;

            if ($autoPolicy === 'equipment') {
                $stagingStmt = mysqli_prepare(
                    $conn,
                    'SELECT id FROM network_discovery_staging
                     WHERE company_id = ? AND profile_id = ? AND ip_address = ? AND status = \'pending\' AND deleted_at IS NULL LIMIT 1'
                );
                if ($stagingStmt) {
                    $hostIp = (string)($enriched['ip'] ?? '');
                    mysqli_stmt_bind_param($stagingStmt, 'iis', $companyId, $profileId, $hostIp);
                    mysqli_stmt_execute($stagingStmt);
                    $stagingRes = mysqli_stmt_get_result($stagingStmt);
                    $stagingRow = $stagingRes ? mysqli_fetch_assoc($stagingRes) : null;
                    mysqli_stmt_close($stagingStmt);
                    if ($stagingRow) {
                        itm_network_discovery_promote_staging($conn, $companyId, (int)$stagingRow['id'], $employeeId, true);
                    }
                }
            }
        }

        $nextOffset = (int)($batch['next_offset'] ?? $offset);
        $complete = !empty($batch['complete']);
        if ($complete) {
            $done = mysqli_prepare(
                $conn,
                'UPDATE network_discovery_profiles
                 SET scan_in_progress = 0, scan_offset = 0, scan_ips_json = NULL, last_run_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND company_id = ?'
            );
            if ($done) {
                mysqli_stmt_bind_param($done, 'ii', $profileId, $companyId);
                mysqli_stmt_execute($done);
                mysqli_stmt_close($done);
            }
        } else {
            $prog = mysqli_prepare(
                $conn,
                'UPDATE network_discovery_profiles SET scan_offset = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
            );
            if ($prog) {
                mysqli_stmt_bind_param($prog, 'iii', $nextOffset, $profileId, $companyId);
                mysqli_stmt_execute($prog);
                mysqli_stmt_close($prog);
            }
        }

        return [
            'ok' => true,
            'complete' => $complete,
            'found' => $found,
            'scanned' => (int)($batch['scanned'] ?? 0),
            'detail' => $complete
                ? 'Profile scan complete.'
                : ('Scanned ' . $nextOffset . ' of ' . count($ips) . ' addresses…'),
        ];
    }
}

if (!function_exists('itm_network_discovery_resolve_equipment_defaults')) {
    /** @return array{type_id: int, status_id: int} */
    function itm_network_discovery_resolve_equipment_defaults(mysqli $conn, int $companyId): array
    {
        $typeId = 0;
        $statusId = 0;
        $typeStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_types WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1'
        );
        if ($typeStmt) {
            mysqli_stmt_bind_param($typeStmt, 'i', $companyId);
            mysqli_stmt_execute($typeStmt);
            $typeRes = mysqli_stmt_get_result($typeStmt);
            if ($typeRes && ($typeRow = mysqli_fetch_assoc($typeRes))) {
                $typeId = (int)($typeRow['id'] ?? 0);
            }
            mysqli_stmt_close($typeStmt);
        }
        $statusStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_statuses WHERE company_id = ? AND deleted_at IS NULL AND active = 1 AND LOWER(name) = \'active\' LIMIT 1'
        );
        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
            mysqli_stmt_execute($statusStmt);
            $statusRes = mysqli_stmt_get_result($statusStmt);
            if ($statusRes && ($statusRow = mysqli_fetch_assoc($statusRes))) {
                $statusId = (int)($statusRow['id'] ?? 0);
            }
            mysqli_stmt_close($statusStmt);
        }
        if ($statusId <= 0) {
            $fallback = mysqli_prepare(
                $conn,
                'SELECT id FROM equipment_statuses WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1'
            );
            if ($fallback) {
                mysqli_stmt_bind_param($fallback, 'i', $companyId);
                mysqli_stmt_execute($fallback);
                $fbRes = mysqli_stmt_get_result($fallback);
                if ($fbRes && ($fbRow = mysqli_fetch_assoc($fbRes))) {
                    $statusId = (int)($fbRow['id'] ?? 0);
                }
                mysqli_stmt_close($fallback);
            }
        }
        return ['type_id' => $typeId, 'status_id' => $statusId];
    }
}

if (!function_exists('itm_network_discovery_create_ipam_row')) {
    function itm_network_discovery_create_ipam_row(
        mysqli $conn,
        int $companyId,
        int $subnetId,
        string $ip,
        int $equipmentId,
        string $hostnameGuess
    ): bool {
        if ($companyId <= 0 || $subnetId <= 0 || trim($ip) === '') {
            return false;
        }
        if (!function_exists('itm_ipam_network_discovery_import_hosts_batch')) {
            require_once ROOT_PATH . 'includes/ipam_helpers.php';
        }
        $import = itm_ipam_network_discovery_import_hosts_batch($conn, $companyId, [$ip], 0, 1);
        return !empty($import['ok']);
    }
}

if (!function_exists('itm_network_discovery_promote_staging')) {
    /**
     * @return array{ok: bool, error?: string, equipment_id?: int}
     */
    function itm_network_discovery_promote_staging(
        mysqli $conn,
        int $companyId,
        int $stagingId,
        int $employeeId,
        bool $createIpam = true
    ): array {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM network_discovery_staging WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Staging lookup failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $stagingId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Staging row not found.'];
        }
        if ((string)($row['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'error' => 'Staging row is not pending.'];
        }

        $ip = trim((string)($row['ip_address'] ?? ''));
        $probe = json_decode((string)($row['probe_json'] ?? '{}'), true);
        if (!is_array($probe)) {
            $probe = [];
        }
        $existingEquipmentId = (int)($probe['equipment_id'] ?? 0);
        if ($existingEquipmentId > 0) {
            return itm_network_discovery_link_staging($conn, $companyId, $stagingId, $existingEquipmentId, $employeeId, $createIpam);
        }

        $defaults = itm_network_discovery_resolve_equipment_defaults($conn, $companyId);
        if ($defaults['type_id'] <= 0 || $defaults['status_id'] <= 0) {
            return ['ok' => false, 'error' => 'Equipment type or status is not configured for this company.'];
        }

        $hostnameGuess = trim((string)($row['hostname_guess'] ?? ''));
        if ($hostnameGuess === '') {
            $hostnameGuess = $ip;
        }
        $name = $hostnameGuess;
        if (strlen($name) > 255) {
            $name = substr($name, 0, 255);
        }
        $hostname = $hostnameGuess;
        if (strlen($hostname) > 100) {
            $hostname = substr($hostname, 0, 100);
        }

        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO equipment (company_id, equipment_type_id, name, hostname, ip_address, status_id, active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())'
        );
        if (!$ins) {
            return ['ok' => false, 'error' => 'Could not create equipment.'];
        }
        mysqli_stmt_bind_param(
            $ins,
            'iisssii',
            $companyId,
            $defaults['type_id'],
            $name,
            $hostname,
            $ip,
            $defaults['status_id'],
            $employeeId
        );
        if (!mysqli_stmt_execute($ins)) {
            mysqli_stmt_close($ins);
            return ['ok' => false, 'error' => 'Equipment insert failed.'];
        }
        $equipmentId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($ins);

        if ($equipmentId <= 0) {
            return ['ok' => false, 'error' => 'Equipment was not created.'];
        }

        if (!function_exists('itm_cmdb_sync_equipment')) {
            require_once ROOT_PATH . 'includes/itm_cmdb.php';
        }
        itm_cmdb_sync_equipment($conn, $companyId, $equipmentId, $employeeId);

        if ($createIpam) {
            $subnetId = (int)($probe['subnet_id'] ?? 0);
            if ($subnetId > 0) {
                itm_network_discovery_create_ipam_row($conn, $companyId, $subnetId, $ip, $equipmentId, $hostnameGuess);
            }
        }

        $upd = mysqli_prepare(
            $conn,
            'UPDATE network_discovery_staging
             SET status = \'promoted\', promoted_equipment_id = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'iiii', $equipmentId, $employeeId, $stagingId, $companyId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }

        return ['ok' => true, 'equipment_id' => $equipmentId];
    }
}

if (!function_exists('itm_network_discovery_link_staging')) {
    /**
     * @return array{ok: bool, error?: string, equipment_id?: int}
     */
    function itm_network_discovery_link_staging(
        mysqli $conn,
        int $companyId,
        int $stagingId,
        int $equipmentId,
        int $employeeId,
        bool $createIpam = true
    ): array {
        if ($equipmentId <= 0) {
            return ['ok' => false, 'error' => 'Equipment is required.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM network_discovery_staging WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Staging lookup failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $stagingId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row || (string)($row['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'error' => 'Staging row is not pending.'];
        }

        $ip = trim((string)($row['ip_address'] ?? ''));
        $eqCheck = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$eqCheck) {
            return ['ok' => false, 'error' => 'Equipment validation failed.'];
        }
        mysqli_stmt_bind_param($eqCheck, 'ii', $equipmentId, $companyId);
        mysqli_stmt_execute($eqCheck);
        $eqRes = mysqli_stmt_get_result($eqCheck);
        $eqRow = $eqRes ? mysqli_fetch_assoc($eqRes) : null;
        mysqli_stmt_close($eqCheck);
        if (!$eqRow) {
            return ['ok' => false, 'error' => 'Equipment not found for this company.'];
        }

        $updEq = mysqli_prepare(
            $conn,
            'UPDATE equipment SET ip_address = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND (ip_address IS NULL OR TRIM(ip_address) = \'\')'
        );
        if ($updEq) {
            mysqli_stmt_bind_param($updEq, 'siii', $ip, $employeeId, $equipmentId, $companyId);
            mysqli_stmt_execute($updEq);
            mysqli_stmt_close($updEq);
        }

        if (!function_exists('itm_cmdb_sync_equipment')) {
            require_once ROOT_PATH . 'includes/itm_cmdb.php';
        }
        itm_cmdb_sync_equipment($conn, $companyId, $equipmentId, $employeeId);

        $probe = json_decode((string)($row['probe_json'] ?? '{}'), true);
        if (!is_array($probe)) {
            $probe = [];
        }
        if ($createIpam) {
            $subnetId = (int)($probe['subnet_id'] ?? 0);
            if ($subnetId > 0) {
                itm_network_discovery_create_ipam_row(
                    $conn,
                    $companyId,
                    $subnetId,
                    $ip,
                    $equipmentId,
                    (string)($row['hostname_guess'] ?? '')
                );
            }
        }

        $upd = mysqli_prepare(
            $conn,
            'UPDATE network_discovery_staging
             SET status = \'promoted\', promoted_equipment_id = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'iiii', $equipmentId, $employeeId, $stagingId, $companyId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }

        return ['ok' => true, 'equipment_id' => $equipmentId];
    }
}

if (!function_exists('itm_network_discovery_dismiss_staging')) {
    /** @return array{ok: bool, error?: string} */
    function itm_network_discovery_dismiss_staging(mysqli $conn, int $companyId, int $stagingId, int $employeeId): array
    {
        $upd = mysqli_prepare(
            $conn,
            'UPDATE network_discovery_staging
             SET status = \'dismissed\', updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND status = \'pending\' AND deleted_at IS NULL'
        );
        if (!$upd) {
            return ['ok' => false, 'error' => 'Dismiss failed.'];
        }
        mysqli_stmt_bind_param($upd, 'iii', $employeeId, $stagingId, $companyId);
        mysqli_stmt_execute($upd);
        $affected = mysqli_stmt_affected_rows($upd);
        mysqli_stmt_close($upd);
        if ($affected <= 0) {
            return ['ok' => false, 'error' => 'Staging row not found or not pending.'];
        }
        return ['ok' => true];
    }
}

if (!function_exists('itm_network_discovery_run_scheduled')) {
    /**
     * @return array{profiles: int, batches: int, found: int, errors: array<int, string>}
     */
    function itm_network_discovery_run_scheduled(mysqli $conn, int $companyFilter = 0): array
    {
        if (!itm_network_discovery_table_exists($conn, 'network_discovery_profiles')) {
            return ['profiles' => 0, 'batches' => 0, 'found' => 0, 'errors' => ['Tables not installed.']];
        }
        if (!function_exists('itm_scheduled_reports_cron_is_due')) {
            require_once ROOT_PATH . 'includes/itm_scheduled_reports.php';
        }

        $sql = 'SELECT id, company_id, schedule_cron, scan_in_progress FROM network_discovery_profiles
                WHERE deleted_at IS NULL AND active = 1 AND enabled = 1';
        if ($companyFilter > 0) {
            $sql .= ' AND company_id = ' . (int)$companyFilter;
        }
        $sql .= ' ORDER BY company_id ASC, id ASC';

        $profiles = 0;
        $batches = 0;
        $found = 0;
        $errors = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $profileId = (int)($row['id'] ?? 0);
            $inProgress = (int)($row['scan_in_progress'] ?? 0) === 1;
            $cron = (string)($row['schedule_cron'] ?? '');
            if (!$inProgress && !itm_scheduled_reports_cron_is_due($cron)) {
                continue;
            }
            $profiles++;
            $batch = itm_network_discovery_profile_run_batch($conn, $profileId, 0);
            $batches++;
            if (empty($batch['ok'])) {
                $errors[] = 'Profile #' . $profileId . ': ' . (string)($batch['error'] ?? 'failed');
                continue;
            }
            $found += (int)($batch['found'] ?? 0);
        }

        return ['profiles' => $profiles, 'batches' => $batches, 'found' => $found, 'errors' => $errors];
    }
}

if (!function_exists('itm_network_discovery_list_profiles')) {
    /** @return array<int, array<string, mixed>> */
    function itm_network_discovery_list_profiles(mysqli $conn, int $companyId): array
    {
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM network_discovery_profiles
             WHERE company_id = ? AND deleted_at IS NULL
             ORDER BY name ASC'
        );
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_network_discovery_list_staging')) {
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    function itm_network_discovery_list_staging(
        mysqli $conn,
        int $companyId,
        string $status = 'pending',
        int $profileFilter = 0,
        int $page = 1,
        int $perPage = 25
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $status = trim($status);
        $allowed = ['pending', 'promoted', 'dismissed', 'all'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $whereBase = 'company_id = ? AND deleted_at IS NULL';
        $whereStaging = 's.company_id = ? AND s.deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];
        if ($status !== 'all') {
            $whereBase .= ' AND status = ?';
            $whereStaging .= ' AND s.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($profileFilter > 0) {
            $whereBase .= ' AND profile_id = ?';
            $whereStaging .= ' AND s.profile_id = ?';
            $types .= 'i';
            $params[] = $profileFilter;
        }

        $countSql = 'SELECT COUNT(*) AS cnt FROM network_discovery_staging WHERE ' . $whereBase;
        $countStmt = mysqli_prepare($conn, $countSql);
        $total = 0;
        if ($countStmt) {
            mysqli_stmt_bind_param($countStmt, $types, ...$params);
            mysqli_stmt_execute($countStmt);
            $countRes = mysqli_stmt_get_result($countStmt);
            if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
                $total = (int)($countRow['cnt'] ?? 0);
            }
            mysqli_stmt_close($countStmt);
        }

        $listSql = 'SELECT s.*, p.name AS profile_name
                    FROM network_discovery_staging s
                    INNER JOIN network_discovery_profiles p ON p.id = s.profile_id AND p.company_id = s.company_id
                    WHERE ' . $whereStaging . '
                    ORDER BY s.created_at DESC
                    LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $listStmt = mysqli_prepare($conn, $listSql);
        $rows = [];
        if ($listStmt) {
            mysqli_stmt_bind_param($listStmt, $types, ...$params);
            mysqli_stmt_execute($listStmt);
            $listRes = mysqli_stmt_get_result($listStmt);
            while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($listStmt);
        }

        return ['rows' => $rows, 'total' => $total];
    }
}

if (!function_exists('itm_network_discovery_save_profile')) {
    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string, id?: int}
     */
    function itm_network_discovery_save_profile(mysqli $conn, int $companyId, array $input, int $employeeId): array
    {
        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $scheduleCron = trim((string)($input['schedule_cron'] ?? ''));
        $snmpEnabled = !empty($input['snmp_enabled']) ? 1 : 0;
        $enabled = !empty($input['enabled']) ? 1 : 0;
        $policy = strtolower(trim((string)($input['auto_create_policy'] ?? 'review')));
        if (!in_array($policy, itm_network_discovery_auto_create_policies(), true)) {
            $policy = 'review';
        }
        $subnetIds = $input['subnet_ids'] ?? [];
        if (!is_array($subnetIds)) {
            $subnetIds = [];
        }
        $subnetIds = array_values(array_unique(array_filter(array_map('intval', $subnetIds))));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Profile name is required.'];
        }
        if ($scheduleCron === '' || count(preg_split('/\s+/', $scheduleCron)) !== 5) {
            return ['ok' => false, 'error' => 'Schedule cron must use five fields (minute hour dom month dow).'];
        }
        if ($subnetIds === []) {
            return ['ok' => false, 'error' => 'Select at least one subnet.'];
        }

        $subnetJson = json_encode($subnetIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($id > 0) {
            $upd = mysqli_prepare(
                $conn,
                'UPDATE network_discovery_profiles
                 SET name = ?, subnet_ids_json = ?, schedule_cron = ?, snmp_enabled = ?, auto_create_policy = ?, enabled = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
            );
            if (!$upd) {
                return ['ok' => false, 'error' => 'Could not update profile.'];
            }
            mysqli_stmt_bind_param(
                $upd,
                'sssisiii',
                $name,
                $subnetJson,
                $scheduleCron,
                $snmpEnabled,
                $policy,
                $enabled,
                $employeeId,
                $id,
                $companyId
            );
            if (!mysqli_stmt_execute($upd)) {
                mysqli_stmt_close($upd);
                return ['ok' => false, 'error' => 'Profile update failed (duplicate name?).'];
            }
            mysqli_stmt_close($upd);
            return ['ok' => true, 'id' => $id];
        }

        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO network_discovery_profiles
             (company_id, name, subnet_ids_json, schedule_cron, snmp_enabled, auto_create_policy, enabled, created_by, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        if (!$ins) {
            return ['ok' => false, 'error' => 'Could not create profile.'];
        }
        mysqli_stmt_bind_param(
            $ins,
            'isssisii',
            $companyId,
            $name,
            $subnetJson,
            $scheduleCron,
            $snmpEnabled,
            $policy,
            $enabled,
            $employeeId
        );
        if (!mysqli_stmt_execute($ins)) {
            mysqli_stmt_close($ins);
            return ['ok' => false, 'error' => 'Profile insert failed (duplicate name?).'];
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($ins);
        return ['ok' => true, 'id' => $newId];
    }
}

if (!function_exists('itm_network_discovery_delete_profile')) {
    /** @return array{ok: bool, error?: string} */
    function itm_network_discovery_delete_profile(mysqli $conn, int $companyId, int $profileId, int $employeeId): array
    {
        $upd = mysqli_prepare(
            $conn,
            'UPDATE network_discovery_profiles
             SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
        );
        if (!$upd) {
            return ['ok' => false, 'error' => 'Delete failed.'];
        }
        mysqli_stmt_bind_param($upd, 'iiii', $employeeId, $employeeId, $profileId, $companyId);
        mysqli_stmt_execute($upd);
        $affected = mysqli_stmt_affected_rows($upd);
        mysqli_stmt_close($upd);
        if ($affected <= 0) {
            return ['ok' => false, 'error' => 'Profile not found.'];
        }
        return ['ok' => true];
    }
}
