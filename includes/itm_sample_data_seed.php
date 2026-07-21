<?php
/**
 * Shared Add sample data seeding — templates from db/02_data_sample.sql for any tenant company_id.
 */

declare(strict_types=1);

if (!function_exists('itm_sample_data_prerequisite_map')) {
    /**
     * Explicit parent seed order before child tables (lookup chains).
     *
     * @return array<string, array<int, string>>
     */
    function itm_sample_data_prerequisite_map(): array
    {
        return [
            'expenses' => ['departments', 'budget_categories', 'cost_centers', 'gl_accounts'],
            'employee_positions' => ['departments'],
            'employee_onboarding_requests' => ['departments', 'employee_positions'],
            'approvers' => ['departments', 'employee_positions', 'approver_type'],
            'employee_assignment_history' => ['departments'],
            'inventory_items' => ['inventory_categories', 'suppliers'],
            'tickets' => ['ticket_categories', 'ticket_statuses', 'ticket_priorities', 'equipment'],
        ];
    }
}

if (!function_exists('itm_seed_lookup_parent_auto_seed_skip_tables')) {
    /**
     * Parents resolved at insert time (FK remap / existing tenant rows) — never bulk-seed from sample SQL.
     *
     * @return array<string, true>
     */
    function itm_seed_lookup_parent_auto_seed_skip_tables(): array
    {
        return [
            'companies' => true,
            'employees' => true,
            'audit_logs' => true,
            'employee_companies' => true,
            'ui_configuration' => true,
        ];
    }
}

if (!function_exists('itm_seed_lookup_parents_for_table')) {
    /**
     * Recursively seed lookup parents from sample SQL so FK columns resolve for the tenant.
     */
    function itm_seed_lookup_parents_for_table(mysqli $conn, string $table, int $companyId, array &$visited = []): void
    {
        static $parentSeedStack = [];

        if (!function_exists('itm_seed_table_from_database_sql') || !itm_is_safe_identifier($table) || $companyId <= 0) {
            return;
        }

        if (isset($visited[$table])) {
            return;
        }
        $visited[$table] = true;

        $parents = itm_sample_data_prerequisite_map()[$table] ?? [];
        $skipParents = itm_seed_lookup_parent_auto_seed_skip_tables();

        foreach (array_values(array_unique($parents)) as $parentTable) {
            if (!itm_is_safe_identifier($parentTable) || isset($skipParents[$parentTable])) {
                continue;
            }
            if (isset($parentSeedStack[$parentTable])) {
                continue;
            }
            if (function_exists('itm_seed_tenant_row_count')
                && itm_table_has_column($conn, $parentTable, 'company_id')
                && itm_seed_tenant_row_count($conn, $parentTable, $companyId) > 0) {
                continue;
            }

            itm_seed_lookup_parents_for_table($conn, $parentTable, $companyId, $visited);
            $seedErr = '';
            $parentSeedStack[$parentTable] = true;
            itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $seedErr);
            unset($parentSeedStack[$parentTable]);
        }
    }
}

if (!function_exists('itm_seed_find_server_equipment_id')) {
    function itm_seed_find_server_equipment_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $deletedPredicate = itm_table_has_column($conn, 'equipment', 'deleted_at')
            ? ' AND e.deleted_at IS NULL'
            : '';

        $sql = "SELECT e.id FROM equipment e
            INNER JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND et.company_id = ? AND et.name = 'Server'
              AND e.active = 1" . $deletedPredicate . '
            ORDER BY e.id ASC LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_resolve_equipment_type_id_by_name')) {
    function itm_seed_resolve_equipment_type_id_by_name(mysqli $conn, int $companyId, string $typeName): int
    {
        if ($companyId <= 0 || $typeName === '') {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_types WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $typeName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_equipment_type_id_by_name')) {
    function itm_seed_ensure_equipment_type_id_by_name(mysqli $conn, int $companyId, string $typeName): int
    {
        $existingId = itm_seed_resolve_equipment_type_id_by_name($conn, $companyId, $typeName);
        if ($existingId > 0) {
            return $existingId;
        }

        $code = $typeName === 'Server' ? 'SRV' : strtoupper(substr($typeName, 0, 6));
        $insertSql = 'INSERT INTO equipment_types (company_id, name, code, active) VALUES ('
            . (int)$companyId . ", '"
            . mysqli_real_escape_string($conn, $typeName) . "', '"
            . mysqli_real_escape_string($conn, $code) . "', 1)";
        if (!itm_run_query($conn, $insertSql)) {
            return 0;
        }

        return itm_seed_resolve_equipment_type_id_by_name($conn, $companyId, $typeName);
    }
}

if (!function_exists('itm_seed_find_equipment_copy_candidate_id')) {
    function itm_seed_find_equipment_copy_candidate_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $deletedPredicate = itm_table_has_column($conn, 'equipment', 'deleted_at')
            ? ' AND deleted_at IS NULL'
            : '';

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM equipment WHERE company_id = ? AND name = ?" . $deletedPredicate . ' ORDER BY id ASC LIMIT 1'
        );
        if ($stmt) {
            $sampleName = 'Primary File Server';
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $sampleName);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $res = mysqli_query(
            $conn,
            'SELECT id FROM equipment WHERE company_id = ' . (int)$companyId . $deletedPredicate . ' ORDER BY id ASC LIMIT 1'
        );
        $row = ($res) ? mysqli_fetch_assoc($res) : null;

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_equipment_status_active_id')) {
    function itm_seed_ensure_equipment_status_active_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM equipment_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $insertSql = "INSERT INTO equipment_statuses (company_id, name, created_at) VALUES ("
            . (int)$companyId . ", 'Active', '2026-01-01 00:00:01')";
        if (!itm_run_query($conn, $insertSql)) {
            return 0;
        }

        $resolvedId = (int)mysqli_insert_id($conn);
        if ($resolvedId > 0) {
            return $resolvedId;
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM equipment_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1"
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_restore_equipment_by_name_if_deleted')) {
    function itm_seed_restore_equipment_by_name_if_deleted(
        mysqli $conn,
        int $companyId,
        string $equipmentName,
        int $equipmentTypeId,
        int $statusId
    ): int {
        if ($companyId <= 0 || $equipmentName === '' || $equipmentTypeId <= 0 || $statusId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, deleted_at, active FROM equipment WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $equipmentName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            return 0;
        }

        $equipmentId = (int)$row['id'];
        $isDeleted = trim((string)($row['deleted_at'] ?? '')) !== '' || (int)($row['active'] ?? 1) === 0;
        if (!$isDeleted) {
            return 0;
        }

        $restoreStmt = mysqli_prepare(
            $conn,
            'UPDATE equipment
             SET equipment_type_id = ?, status_id = ?, active = 1, deleted_at = NULL, deleted_by = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND company_id = ?'
        );
        if (!$restoreStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($restoreStmt, 'iiii', $equipmentTypeId, $statusId, $equipmentId, $companyId);
        if (!mysqli_stmt_execute($restoreStmt)) {
            mysqli_stmt_close($restoreStmt);
            return 0;
        }
        mysqli_stmt_close($restoreStmt);

        return $equipmentId;
    }
}

if (!function_exists('itm_seed_insert_minimal_primary_file_server')) {
    function itm_seed_insert_minimal_primary_file_server(mysqli $conn, int $companyId, int $serverTypeId): int
    {
        if ($companyId <= 0 || $serverTypeId <= 0) {
            return 0;
        }

        $statusId = itm_seed_ensure_equipment_status_active_id($conn, $companyId);
        if ($statusId <= 0) {
            return 0;
        }

        $restoredId = itm_seed_restore_equipment_by_name_if_deleted(
            $conn,
            $companyId,
            'Primary File Server',
            $serverTypeId,
            $statusId
        );
        if ($restoredId > 0) {
            return $restoredId;
        }

        $insertSql = 'INSERT INTO equipment (company_id, equipment_type_id, name, serial_number, model, hostname, ip_address, status_id, purchase_date, purchase_cost, printer_color_capable, printer_scan, active, created_at) VALUES ('
            . (int)$companyId . ', ' . (int)$serverTypeId . ", 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', "
            . (int)$statusId . ", '2026-06-05', 8500.00, 0, 0, 1, '2026-01-01 00:00:01')";
        if (!itm_run_query($conn, $insertSql)) {
            return 0;
        }

        return itm_seed_find_server_equipment_id($conn, $companyId);
    }
}

if (!function_exists('itm_seed_ensure_server_equipment')) {
    function itm_seed_ensure_server_equipment(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $serverEquipmentId = itm_seed_find_server_equipment_id($conn, $companyId);
        if ($serverEquipmentId > 0) {
            return $serverEquipmentId;
        }

        $serverTypeId = itm_seed_ensure_equipment_type_id_by_name($conn, $companyId, 'Server');
        if ($serverTypeId <= 0) {
            return 0;
        }

        $candidateId = itm_seed_find_equipment_copy_candidate_id($conn, $companyId);
        if ($candidateId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE equipment SET equipment_type_id = ? WHERE id = ? AND company_id = ?'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iii', $serverTypeId, $candidateId, $companyId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            return itm_seed_find_server_equipment_id($conn, $companyId);
        }

        return itm_seed_insert_minimal_primary_file_server($conn, $companyId, $serverTypeId);
    }
}

if (!function_exists('itm_seed_find_switch_equipment_id')) {
    function itm_seed_find_switch_equipment_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $deletedPredicate = itm_table_has_column($conn, 'equipment', 'deleted_at')
            ? ' AND e.deleted_at IS NULL'
            : '';

        $sql = "SELECT e.id FROM equipment e
            INNER JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND et.company_id = ? AND et.name = 'Switch'
              AND e.active = 1" . $deletedPredicate . '
            ORDER BY e.id ASC LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_equipment_rj45_id_by_name')) {
    function itm_seed_ensure_equipment_rj45_id_by_name(mysqli $conn, int $companyId, string $rj45Name): int
    {
        if ($companyId <= 0 || $rj45Name === '') {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_rj45 WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $rj45Name);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $insertSql = "INSERT INTO equipment_rj45 (company_id, name, created_at) VALUES ("
            . (int)$companyId . ", '" . mysqli_real_escape_string($conn, $rj45Name) . "', '2026-01-01 00:00:01')";
        if (!itm_run_query($conn, $insertSql)) {
            return 0;
        }

        $resolvedId = (int)mysqli_insert_id($conn);
        if ($resolvedId > 0) {
            return $resolvedId;
        }

        $lookupStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_rj45 WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if (!$lookupStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($lookupStmt, 'is', $companyId, $rj45Name);
        mysqli_stmt_execute($lookupStmt);
        $lookupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lookupStmt));
        mysqli_stmt_close($lookupStmt);

        return is_array($lookupRow) ? (int)($lookupRow['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_switch_port_type_rj45')) {
    function itm_seed_ensure_switch_port_type_rj45(mysqli $conn, int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        $typeName = 'RJ45';
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM switch_port_types WHERE company_id = ? AND type = ? LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $typeName);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return true;
            }
        }

        $insertSql = "INSERT INTO switch_port_types (company_id, type) VALUES ("
            . (int)$companyId . ", 'RJ45')";

        return (bool) itm_run_query($conn, $insertSql);
    }
}

if (!function_exists('itm_seed_ensure_unknown_switch_status_id')) {
    function itm_seed_ensure_unknown_switch_status_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $statusStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1"
        );
        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
            mysqli_stmt_execute($statusStmt);
            $statusRow = mysqli_fetch_assoc(mysqli_stmt_get_result($statusStmt));
            mysqli_stmt_close($statusStmt);
            $existingId = (int)($statusRow['id'] ?? 0);
            if ($existingId > 0) {
                return $existingId;
            }
        }

        $grayColorId = 0;
        $colorStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
        );
        if ($colorStmt) {
            mysqli_stmt_bind_param($colorStmt, 'i', $companyId);
            mysqli_stmt_execute($colorStmt);
            $colorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($colorStmt));
            mysqli_stmt_close($colorStmt);
            $grayColorId = (int)($colorRow['id'] ?? 0);
        }
        if ($grayColorId <= 0) {
            $insertColorSql = "INSERT INTO cable_colors (company_id, color_name, hex_color, created_at) VALUES ("
                . (int)$companyId . ", 'Gray', '#808080', '2026-01-01 00:00:01')";
            if (itm_run_query($conn, $insertColorSql)) {
                $grayColorId = (int)mysqli_insert_id($conn);
            }
            if ($grayColorId <= 0) {
                $colorStmt = mysqli_prepare(
                    $conn,
                    "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
                );
                if ($colorStmt) {
                    mysqli_stmt_bind_param($colorStmt, 'i', $companyId);
                    mysqli_stmt_execute($colorStmt);
                    $colorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($colorStmt));
                    mysqli_stmt_close($colorStmt);
                    $grayColorId = (int)($colorRow['id'] ?? 0);
                }
            }
        }
        if ($grayColorId <= 0) {
            return 0;
        }

        $insertStatusSql = 'INSERT INTO switch_status (company_id, status, color_id, created_at) VALUES ('
            . (int)$companyId . ", 'Unknown', " . (int)$grayColorId . ", '2026-01-01 00:00:01')";
        if (!itm_run_query($conn, $insertStatusSql)) {
            return 0;
        }

        $unknownStatusId = (int)mysqli_insert_id($conn);
        if ($unknownStatusId > 0) {
            return $unknownStatusId;
        }

        $statusStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1"
        );
        if (!$statusStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
        mysqli_stmt_execute($statusStmt);
        $statusRow = mysqli_fetch_assoc(mysqli_stmt_get_result($statusStmt));
        mysqli_stmt_close($statusStmt);

        return is_array($statusRow) ? (int)($statusRow['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_parse_rj45_port_count_from_name')) {
    function itm_seed_parse_rj45_port_count_from_name(string $rj45Name): int
    {
        if (preg_match('/(\d+)/', $rj45Name, $matches)) {
            $count = (int)($matches[1] ?? 0);
            if ($count > 0) {
                return $count;
            }
        }

        return 24;
    }
}

if (!function_exists('itm_seed_count_switch_rj45_ports')) {
    function itm_seed_count_switch_rj45_ports(mysqli $conn, int $companyId, int $equipmentId = 0): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        if ($equipmentId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND LOWER(port_type) = 'rj45'"
            );
            if (!$stmt) {
                return 0;
            }
            mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            return (int)($row['c'] ?? 0);
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ? AND LOWER(port_type) = 'rj45'"
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_seed_ensure_switch_rj45_ports')) {
  /**
   * Ensure RJ45 switch_ports rows exist for sample switch equipment (default 24).
   *
   * @return int Rows inserted this call
   */
    function itm_seed_ensure_switch_rj45_ports(mysqli $conn, int $companyId, int $equipmentId, int $portCount, string $hostname): int
    {
        if ($companyId <= 0 || $equipmentId <= 0 || $portCount <= 0) {
            return 0;
        }

        if (!itm_table_has_column($conn, 'switch_ports', 'equipment_id')) {
            return 0;
        }

        $beforeCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $equipmentId);

        if (!itm_seed_ensure_switch_port_type_rj45($conn, $companyId)) {
            return 0;
        }

        $unknownStatusId = itm_seed_ensure_unknown_switch_status_id($conn, $companyId);
        if ($unknownStatusId <= 0) {
            return 0;
        }

        $grayColorId = 0;
        $colorStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
        );
        if ($colorStmt) {
            mysqli_stmt_bind_param($colorStmt, 'i', $companyId);
            mysqli_stmt_execute($colorStmt);
            $colorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($colorStmt));
            mysqli_stmt_close($colorStmt);
            $grayColorId = (int)($colorRow['id'] ?? 0);
        }
        if ($grayColorId <= 0) {
            $anyColorStmt = mysqli_prepare(
                $conn,
                'SELECT id FROM cable_colors WHERE company_id = ? ORDER BY id ASC LIMIT 1'
            );
            if ($anyColorStmt) {
                mysqli_stmt_bind_param($anyColorStmt, 'i', $companyId);
                mysqli_stmt_execute($anyColorStmt);
                $anyColorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($anyColorStmt));
                mysqli_stmt_close($anyColorStmt);
                $grayColorId = (int)($anyColorRow['id'] ?? 0);
            }
        }
        if ($grayColorId <= 0) {
            return 0;
        }

        $portType = 'RJ45';
        $insertStmt = mysqli_prepare(
            $conn,
            "INSERT INTO switch_ports (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, comments, active)
             SELECT ?, ?, NULLIF(?, ''), ?, ?, '', ?, ?, '', 1
             WHERE NOT EXISTS (
                SELECT 1 FROM switch_ports
                WHERE company_id = ? AND equipment_id = ? AND port_number = ?
             )"
        );
        if (!$insertStmt) {
            return 0;
        }

        for ($portNo = 1; $portNo <= $portCount; $portNo++) {
            mysqli_stmt_bind_param(
                $insertStmt,
                'iissiiiiii',
                $companyId,
                $equipmentId,
                $hostname,
                $portType,
                $portNo,
                $unknownStatusId,
                $grayColorId,
                $companyId,
                $equipmentId,
                $portNo
            );
            mysqli_stmt_execute($insertStmt);
        }
        mysqli_stmt_close($insertStmt);

        $afterCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $equipmentId);

        return max(0, $afterCount - $beforeCount);
    }
}

if (!function_exists('itm_seed_insert_minimal_sample_switch')) {
    function itm_seed_insert_minimal_sample_switch(mysqli $conn, int $companyId, int $switchTypeId, int $rj45Id, int $portCount): int
    {
        if ($companyId <= 0 || $switchTypeId <= 0 || $rj45Id <= 0 || $portCount <= 0) {
            return 0;
        }

        $statusId = itm_seed_ensure_equipment_status_active_id($conn, $companyId);
        if ($statusId <= 0) {
            return 0;
        }

        $hostname = 'sw-core-01';
        $equipmentName = 'Core Switch';
        $restoredId = itm_seed_restore_equipment_by_name_if_deleted(
            $conn,
            $companyId,
            $equipmentName,
            $switchTypeId,
            $statusId
        );
        if ($restoredId > 0) {
            $updateStmt = mysqli_prepare(
                $conn,
                'UPDATE equipment SET switch_rj45_id = ?, hostname = ? WHERE id = ? AND company_id = ?'
            );
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'isii', $rj45Id, $hostname, $restoredId, $companyId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }
            itm_seed_ensure_switch_rj45_ports($conn, $companyId, $restoredId, $portCount, $hostname);

            return $restoredId;
        }

        $layoutId = 0;
        $layoutStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM switch_port_numbering_layout WHERE company_id = ? ORDER BY id ASC LIMIT 1'
        );
        if ($layoutStmt) {
            mysqli_stmt_bind_param($layoutStmt, 'i', $companyId);
            mysqli_stmt_execute($layoutStmt);
            $layoutRow = mysqli_fetch_assoc(mysqli_stmt_get_result($layoutStmt));
            mysqli_stmt_close($layoutStmt);
            $layoutId = (int)($layoutRow['id'] ?? 0);
        }
        if ($layoutId <= 0) {
            $globalLayoutRes = mysqli_query($conn, 'SELECT id FROM switch_port_numbering_layout ORDER BY id ASC LIMIT 1');
            if ($globalLayoutRes && ($globalLayoutRow = mysqli_fetch_assoc($globalLayoutRes))) {
                $layoutId = (int)($globalLayoutRow['id'] ?? 0);
            }
        }
        if ($layoutId <= 0) {
            return 0;
        }
        $insertStmt = mysqli_prepare(
            $conn,
            'INSERT INTO equipment (company_id, equipment_type_id, name, serial_number, model, hostname, ip_address, status_id, switch_rj45_id, switch_port_numbering_layout_id, printer_color_capable, printer_scan, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)'
        );
        if (!$insertStmt) {
            return 0;
        }

        $serialNumber = 'SN-SW-001';
        $model = 'UniFi Switch 24 PoE';
        $ipAddress = '192.168.10.10';
        $createdAt = '2026-01-01 00:00:01';
        mysqli_stmt_bind_param(
            $insertStmt,
            'iisssssiiis',
            $companyId,
            $switchTypeId,
            $equipmentName,
            $serialNumber,
            $model,
            $hostname,
            $ipAddress,
            $statusId,
            $rj45Id,
            $layoutId,
            $createdAt
        );
        if (!mysqli_stmt_execute($insertStmt)) {
            mysqli_stmt_close($insertStmt);
            return 0;
        }
        mysqli_stmt_close($insertStmt);

        $equipmentId = (int)mysqli_insert_id($conn);
        if ($equipmentId <= 0) {
            $equipmentId = itm_seed_find_switch_equipment_id($conn, $companyId);
        }
        if ($equipmentId <= 0) {
            return 0;
        }

        itm_seed_ensure_switch_rj45_ports($conn, $companyId, $equipmentId, $portCount, $hostname);

        return $equipmentId;
    }
}

if (!function_exists('itm_seed_ensure_switch_equipment')) {
    function itm_seed_ensure_switch_equipment(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $rj45Name = '24 ports';
        $rj45Id = itm_seed_ensure_equipment_rj45_id_by_name($conn, $companyId, $rj45Name);
        $portCount = itm_seed_parse_rj45_port_count_from_name($rj45Name);
        if ($rj45Id <= 0) {
            return 0;
        }

        $switchEquipmentId = itm_seed_find_switch_equipment_id($conn, $companyId);
        if ($switchEquipmentId > 0) {
            $updateStmt = mysqli_prepare(
                $conn,
                'UPDATE equipment SET switch_rj45_id = ? WHERE id = ? AND company_id = ?'
            );
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'iii', $rj45Id, $switchEquipmentId, $companyId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }

            $hostname = 'sw-core-01';
            $hostStmt = mysqli_prepare(
                $conn,
                'SELECT hostname FROM equipment WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($hostStmt) {
                mysqli_stmt_bind_param($hostStmt, 'ii', $switchEquipmentId, $companyId);
                mysqli_stmt_execute($hostStmt);
                $hostRow = mysqli_fetch_assoc(mysqli_stmt_get_result($hostStmt));
                mysqli_stmt_close($hostStmt);
                $resolvedHost = trim((string)($hostRow['hostname'] ?? ''));
                if ($resolvedHost !== '') {
                    $hostname = $resolvedHost;
                }
            }

            itm_seed_ensure_switch_rj45_ports($conn, $companyId, $switchEquipmentId, $portCount, $hostname);

            return $switchEquipmentId;
        }

        $switchTypeId = itm_seed_ensure_equipment_type_id_by_name($conn, $companyId, 'Switch');
        if ($switchTypeId <= 0) {
            return 0;
        }

        return itm_seed_insert_minimal_sample_switch($conn, $companyId, $switchTypeId, $rj45Id, $portCount);
    }
}

if (!function_exists('itm_seed_backup_tape_log_today_row_exists')) {
    function itm_seed_backup_tape_log_today_row_exists(mysqli $conn, int $companyId, int $serverId): bool
    {
        if ($companyId <= 0 || $serverId <= 0) {
            return false;
        }

        $today = date('Y-m-d');
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM backup_tape_log WHERE company_id = ? AND server_id = ? AND log_date = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iis', $companyId, $serverId, $today);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) && (int)($row['id'] ?? 0) > 0;
    }
}

if (!function_exists('itm_seed_backup_tape_log_value_for_today')) {
    function itm_seed_backup_tape_log_value_for_today(string $columnName, string $valueToken, int $serverId): string
    {
        if ($columnName === 'log_date') {
            return "'" . date('Y-m-d') . "'";
        }
        if ($columnName === 'tape_to_be_used') {
            return "'" . date('l') . "'";
        }
        if ($columnName === 'server_id' && $serverId > 0) {
            return (string)$serverId;
        }
        if ($columnName === 'time_tape_inserted' || $columnName === 'time_returned_to_safe') {
            return "'" . date('Y-m-d H:i:s') . "'";
        }

        return $valueToken;
    }
}

if (!function_exists('itm_seed_insert_backup_tape_log_today_row')) {
    /**
     * Insert one editable backup_tape_log row for today (direct INSERT — avoids template FK remap skips).
     */
    function itm_seed_insert_backup_tape_log_today_row(mysqli $conn, int $companyId, int $serverId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0 || $serverId <= 0) {
            $error = 'Server is required before adding backup tape log sample data.';
            return 0;
        }

        if (function_exists('itm_seed_backup_tape_log_today_row_exists')
            && itm_seed_backup_tape_log_today_row_exists($conn, $companyId, $serverId)) {
            return 0;
        }

        $today = date('Y-m-d');
        $dayName = date('l');
        $now = date('Y-m-d H:i:s');
        $printName = 'Sample backup log';

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO backup_tape_log (company_id, server_id, log_date, tape_to_be_used, time_tape_inserted, time_returned_to_safe, print_name, backup_status, problem_details, tape_used_for_restore, ism_review, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare backup_tape_log sample insert.';
            return 0;
        }

        $backupStatus = 'Full';
        $problemDetails = 'Sample backup log entry';
        $tapeUsed = 0;
        $ismReview = 0;
        mysqli_stmt_bind_param(
            $stmt,
            'iisssssssii',
            $companyId,
            $serverId,
            $today,
            $dayName,
            $now,
            $now,
            $printName,
            $backupStatus,
            $problemDetails,
            $tapeUsed,
            $ismReview
        );

        if (!mysqli_stmt_execute($stmt)) {
            $error = 'Could not insert backup tape log sample row: ' . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return 0;
        }

        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_row_assoc_from_insert_entry')) {
    /**
     * @param array{columns:array<int,string>,values:array<int,string>} $rowEntry
     * @return array<string, string>
     */
    function itm_seed_row_assoc_from_insert_entry(array $rowEntry): array
    {
        $assoc = [];
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName === '') {
                continue;
            }
            $token = trim((string)($rawValues[$index] ?? ''));
            if ($token === '' || strcasecmp($token, 'NULL') === 0) {
                continue;
            }
            $assoc[$columnName] = trim($token, "'\"");
        }

        return $assoc;
    }
}

if (!function_exists('itm_seed_table_row_exists_for_tenant')) {
    /**
     * Skip template rows when the tenant already has a matching business-key row.
     */
    function itm_seed_table_row_exists_for_tenant(mysqli $conn, string $tableName, int $companyId, array $rowAssoc): bool
    {
        if ($companyId <= 0 || !itm_is_safe_identifier($tableName) || $rowAssoc === []) {
            return false;
        }

        $detectFkLib = ROOT_PATH . 'includes/detect_fk_dropdown_ui_risk_lib.php';
        if (is_file($detectFkLib)) {
            require_once $detectFkLib;
        }

        if (!function_exists('itm_fk_table_column_names') || !function_exists('itm_detect_fk_business_key_columns')) {
            return false;
        }

        $tableColumns = itm_fk_table_column_names($conn, $tableName);
        if (!in_array('company_id', $tableColumns, true)) {
            return false;
        }

        $businessKeys = itm_detect_fk_business_key_columns($tableName, $tableColumns);
        if ($businessKeys === []) {
            return false;
        }

        $whereParts = ['company_id = ' . (int)$companyId, 'deleted_at IS NULL'];
        if (!itm_table_has_column($conn, $tableName, 'deleted_at')) {
            array_pop($whereParts);
        }

        foreach ($businessKeys as $keyColumn) {
            if (!itm_is_safe_identifier($keyColumn)) {
                continue;
            }
            if (!array_key_exists($keyColumn, $rowAssoc)) {
                return false;
            }
            $keyValue = (string)$rowAssoc[$keyColumn];
            if ($keyValue === '') {
                $whereParts[] = '(`' . $keyColumn . "` = '' OR `" . $keyColumn . '` IS NULL)';
            } else {
                $whereParts[] = '`' . $keyColumn . "` = '" . mysqli_real_escape_string($conn, $keyValue) . "'";
            }
        }

        $sql = 'SELECT id FROM `' . str_replace('`', '``', $tableName) . '` WHERE ' . implode(' AND ', $whereParts) . ' LIMIT 1';
        $res = mysqli_query($conn, $sql);

        return ($res && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('itm_seed_insert_row_is_unique_violation')) {
    function itm_seed_insert_row_is_unique_violation(int $dbErrorCode, string $dbErrorMessage): bool
    {
        if ($dbErrorCode === 1062) {
            return true;
        }

        return stripos($dbErrorMessage, 'Duplicate entry') !== false;
    }
}

if (!function_exists('itm_seed_table_column_metas')) {
    /**
     * @return array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}>
     */
    function itm_seed_table_column_metas(mysqli $conn, string $tableName): array
    {
        $metas = [];
        if (!itm_is_safe_identifier($tableName)) {
            return $metas;
        }

        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $res = mysqli_query($conn, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $metas[] = [
                'name' => (string)($row['Field'] ?? ''),
                'type' => (string)($row['Type'] ?? ''),
                'null' => (string)($row['Null'] ?? ''),
                'default' => $row['Default'] ?? null,
                'extra' => (string)($row['Extra'] ?? ''),
                'key' => (string)($row['Key'] ?? ''),
            ];
        }

        return $metas;
    }
}

if (!function_exists('itm_seed_column_skipped_for_fallback')) {
    function itm_seed_column_skipped_for_fallback(string $name, array $meta): bool
    {
        if ($name === 'id' || strpos($meta['extra'], 'auto_increment') !== false) {
            return true;
        }
        if (in_array($name, ['deleted_by', 'deleted_at', 'created_by', 'updated_by'], true)) {
            return true;
        }
        if (in_array($name, ['created_at', 'updated_at'], true)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_seed_fill_scalar_fallback_value')) {
    function itm_seed_fill_scalar_fallback_value(string $name, string $type, string $tableName): ?string
    {
        $suffix = substr(md5($tableName . '-' . $name . '-' . microtime(true)), 0, 8);
        $lower = strtolower($name);

        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
            return '1';
        }
        if ($name === 'amount' || strpos($type, 'decimal') !== false || strpos($type, 'double') !== false || strpos($type, 'float') !== false) {
            return '1.00';
        }
        if (strpos($lower, 'email') !== false) {
            return 'sample-' . $suffix . '@example.com';
        }
        if ($name === 'hex_color') {
            return '#808080';
        }
        if (strpos($lower, 'date') !== false && strpos($type, 'datetime') === false && strpos($type, 'timestamp') === false) {
            return date('Y-m-d');
        }
        if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            return date('Y-m-d H:i:s');
        }
        if (in_array($lower, ['name', 'title', 'label', 'code', 'status', 'stage', 'level', 'type', 'mode_name', 'mode_code', 'color_name', 'cable_type'], true)) {
            return 'Sample ' . $suffix;
        }

        if (preg_match('/^(varchar|char|text|mediumtext|longtext)/', $type)) {
            return 'Sample ' . $suffix;
        }

        return null;
    }
}

if (!function_exists('itm_seed_insert_random_fallback_row')) {
    /**
     * Insert exactly one synthetic row when no template rows apply for an empty tenant table.
     */
    function itm_seed_insert_random_fallback_row(mysqli $conn, string $tableName, int $companyId, &$error = ''): int
    {
        $error = '';
        if (!itm_is_safe_identifier($tableName) || $companyId <= 0) {
            $error = 'Invalid table or company for sample fallback.';
            return 0;
        }

        if ($tableName !== 'backup_tape_log') {
            itm_seed_lookup_parents_for_table($conn, $tableName, $companyId);
        } elseif (function_exists('itm_seed_ensure_server_equipment')) {
            itm_seed_ensure_server_equipment($conn, $companyId);
        }

        $columnMetas = itm_seed_table_column_metas($conn, $tableName);
        if ($columnMetas === []) {
            $error = 'Could not read columns for ' . $tableName . '.';
            return 0;
        }

        $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $tableName) : [];
        $targetColumns = [];
        $targetValues = [];
        $bindTypes = '';
        $bindParams = [];

        foreach ($columnMetas as $meta) {
            $name = (string)$meta['name'];
            if ($name === '' || itm_seed_column_skipped_for_fallback($name, $meta)) {
                continue;
            }

            $type = (string)$meta['type'];
            $nullable = (($meta['null'] ?? '') === 'YES');
            $value = null;
            $bindType = 's';

            if ($name === 'company_id') {
                $value = $companyId;
                $bindType = 'i';
            } elseif ($tableName === 'backup_tape_log' && $name === 'server_id' && function_exists('itm_seed_find_server_equipment_id')) {
                $fkId = itm_seed_find_server_equipment_id($conn, $companyId);
                if ($fkId > 0) {
                    $value = $fkId;
                    $bindType = 'i';
                } elseif (!$nullable) {
                    $error = 'Could not resolve Server equipment for backup_tape_log sample.';
                    return 0;
                }
            } elseif (isset($fkMap[$name])) {
                $refTable = (string)($fkMap[$name]['REFERENCED_TABLE_NAME'] ?? '');
                $fkId = 0;
                if ($refTable !== '' && function_exists('itm_first_tenant_row_id')) {
                    $fkId = itm_first_tenant_row_id($conn, $refTable, $companyId);
                }
                if ($fkId <= 0 && $refTable !== '' && $tableName !== 'backup_tape_log') {
                    itm_seed_lookup_parents_for_table($conn, $refTable, $companyId);
                    if (function_exists('itm_first_tenant_row_id')) {
                        $fkId = itm_first_tenant_row_id($conn, $refTable, $companyId);
                    }
                }
                if ($fkId > 0) {
                    $value = $fkId;
                    $bindType = 'i';
                } elseif (!$nullable) {
                    $error = 'Could not resolve required FK ' . $name . ' for fallback row.';
                    return 0;
                }
            } else {
                $scalar = itm_seed_fill_scalar_fallback_value($name, $type, $tableName);
                if ($scalar !== null) {
                    $value = $scalar;
                    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
                        $bindType = 'i';
                        $value = (int)$scalar;
                    }
                } elseif (!$nullable && ($meta['default'] ?? null) === null) {
                    $error = 'No fallback value for required column ' . $name . '.';
                    return 0;
                }
            }

            if ($value === null && $nullable) {
                continue;
            }
            if ($value === null) {
                continue;
            }

            $targetColumns[] = '`' . str_replace('`', '``', $name) . '`';
            $targetValues[] = '?';
            $bindTypes .= $bindType;
            $bindParams[] = $value;
        }

        if ($targetColumns === []) {
            $error = 'No insertable columns for fallback row.';
            return 0;
        }

        $sql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $error = mysqli_error($conn);
            return 0;
        }

        $refs = [];
        $refs[] = &$bindTypes;
        foreach ($bindParams as $key => $param) {
            $refs[] = &$bindParams[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));

        if (!mysqli_stmt_execute($stmt)) {
            $errno = (int)mysqli_stmt_errno($stmt);
            $message = (string)mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if (itm_seed_insert_row_is_unique_violation($errno, $message)) {
                return 0;
            }
            $error = itm_format_db_constraint_error($errno, $message);
            return 0;
        }

        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_tenant_row_count')) {
    function itm_seed_tenant_row_count(mysqli $conn, string $tableName, int $companyId): int
    {
        if (!itm_is_safe_identifier($tableName) || $companyId <= 0) {
            return 0;
        }
        if (!itm_table_has_column($conn, $tableName, 'company_id')) {
            $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $tableName) . '`');
            $row = $res ? mysqli_fetch_assoc($res) : null;

            return (int)($row['c'] ?? 0);
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_seed_filter_template_rows')) {
    /**
     * @param array<int, array{columns:array<int,string>,values:array<int,string>}> $tableRows
     * @return array<int, array{columns:array<int,string>,values:array<int,string>}>
     */
    function itm_seed_filter_template_rows(array $tableRows, int $templateCompanyId): array
    {
        $filtered = [];
        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $companyIndex = null;
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === 'company_id') {
                    $companyIndex = $index;
                    break;
                }
            }

            if ($companyIndex === null) {
                $filtered[] = $rowEntry;
                continue;
            }

            $rawCompanyToken = trim((string)($rawValues[$companyIndex] ?? ''));
            if ($rawCompanyToken === '' || strtoupper($rawCompanyToken) === 'NULL') {
                continue;
            }
            $rawCompanyToken = trim($rawCompanyToken, "'\"");
            if ((int)$rawCompanyToken === $templateCompanyId) {
                $filtered[] = $rowEntry;
            }
        }

        return $filtered;
    }
}

if (!function_exists('itm_seed_table_from_database_sql')) {
    /**
     * Inserts sample rows for a module table from db/02_data_sample.sql for any tenant company_id.
     */
    function itm_seed_table_from_database_sql($conn, $tableName, $companyId, &$error = '')
    {
        $error = '';
        $tableName = (string)$tableName;
        $companyId = (int)$companyId;

        if (!itm_is_safe_identifier($tableName)) {
            $error = 'Invalid table selected for sample data.';
            return 0;
        }

        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $backupTapeServerId = 0;
        if ($tableName === 'switch_ports') {
            if (!function_exists('itm_seed_ensure_switch_equipment')) {
                $error = 'Switch ports sample seeding is unavailable.';
                return 0;
            }

            $switchEquipmentId = itm_seed_ensure_switch_equipment($conn, $companyId);
            if ($switchEquipmentId <= 0) {
                $error = 'Could not create or resolve Switch equipment for sample ports.';
                return 0;
            }

            $rj45PortCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $switchEquipmentId);
            if ($rj45PortCount < 24) {
                $error = 'Expected 24 RJ45 switch_ports rows after sample seed (got ' . $rj45PortCount . ').';
                return 0;
            }

            return $rj45PortCount;
        }

        if ($tableName === 'backup_tape_log') {
            if (!function_exists('itm_seed_ensure_server_equipment')) {
                $error = 'Backup tape log sample seeding is unavailable.';
                return 0;
            }

            $backupTapeServerId = itm_seed_ensure_server_equipment($conn, $companyId);
            if ($backupTapeServerId <= 0) {
                $error = 'Could not create or resolve Server equipment for backup tape log.';
                return 0;
            }

            if (!function_exists('itm_seed_insert_backup_tape_log_today_row')) {
                $error = 'Backup tape log sample seeding is unavailable.';
                return 0;
            }

            return itm_seed_insert_backup_tape_log_today_row($conn, $companyId, $backupTapeServerId, $error);
        }

        itm_seed_lookup_parents_for_table($conn, $tableName, $companyId);

        $tenantRowsBefore = itm_seed_tenant_row_count($conn, $tableName, $companyId);

        $sqlBody = itm_database_sql_read_sample();
        if ($sqlBody === '') {
            $error = 'Sample source file db/02_data_sample.sql was not found or is empty.';
            return 0;
        }

        $parsedInserts = itm_parse_database_sql_inserts($sqlBody, $tableName);
        $tableRows = $parsedInserts[$tableName] ?? [];

        if (empty($tableRows)) {
            if ($tableName === 'employee_sidebar_preferences'
                && function_exists('itm_seed_default_employee_sidebar_preferences_for_company')) {
                return itm_seed_default_employee_sidebar_preferences_for_company($conn, $companyId, 1, $error);
            }

            return itm_seed_insert_random_fallback_row($conn, $tableName, $companyId, $error);
        }

        $templateCompanyId = defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
            ? (int)ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
            : 1;
        $tableRows = itm_seed_filter_template_rows($tableRows, $templateCompanyId);
        if ($tableRows === []) {
            return itm_seed_insert_random_fallback_row($conn, $tableName, $companyId, $error);
        }

        $tableFkMap = itm_table_outbound_fk_map($conn, $tableName);
        $insertCount = 0;

        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $rowAssoc = itm_seed_row_assoc_from_insert_entry($rowEntry);

            if (itm_seed_table_row_exists_for_tenant($conn, $tableName, $companyId, $rowAssoc)) {
                continue;
            }

            if ($tableName === 'backup_tape_log' && $backupTapeServerId <= 0) {
                continue;
            }

            $targetColumns = [];
            $targetValues = [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                    continue;
                }

                if ($columnName === 'id') {
                    continue;
                }

                if ($columnName === 'company_id') {
                    $targetColumns[] = '`company_id`';
                    $targetValues[] = (string)$companyId;
                    continue;
                }

                if ($tableName === 'backup_tape_log') {
                    if ($columnName === 'server_id') {
                        $targetColumns[] = '`server_id`';
                        $targetValues[] = (string)$backupTapeServerId;
                        continue;
                    }
                    if (in_array($columnName, ['log_date', 'tape_to_be_used', 'time_tape_inserted', 'time_returned_to_safe'], true)
                        && function_exists('itm_seed_backup_tape_log_value_for_today')) {
                        $valueToken = itm_seed_backup_tape_log_value_for_today(
                            $columnName,
                            (string)$rawValues[$index],
                            $backupTapeServerId
                        );
                        $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                        $targetValues[] = $valueToken;
                        continue;
                    }
                }

                $valueToken = (string)$rawValues[$index];
                if (isset($tableFkMap[$columnName]) && function_exists('itm_seed_resolve_fk_from_database_sql')) {
                    $rawFkToken = trim($valueToken);
                    if ($rawFkToken !== '' && strtoupper($rawFkToken) !== 'NULL') {
                        $rawFkToken = trim($rawFkToken, "'\"");
                        $storedFkId = (int)$rawFkToken;
                        if ($storedFkId > 0) {
                            $resolvedFkId = itm_seed_resolve_fk_from_database_sql(
                                $conn,
                                $tableFkMap[$columnName],
                                $companyId,
                                $storedFkId
                            );
                            if ($resolvedFkId > 0) {
                                $valueToken = (string)(int)$resolvedFkId;
                            } elseif (function_exists('itm_table_column_is_nullable')
                                && itm_table_column_is_nullable($conn, $tableName, $columnName)) {
                                $valueToken = 'NULL';
                            } else {
                                continue 2;
                            }
                        }
                    }
                }

                $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                $targetValues[] = $valueToken;
            }

            if (empty($targetColumns)) {
                continue;
            }

            $insertSql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (!itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                if (itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    continue;
                }
                $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                return $insertCount;
            }
            $insertCount++;
        }

        if ($insertCount === 0) {
            if ($tenantRowsBefore > 0 || itm_seed_tenant_row_count($conn, $tableName, $companyId) > 0) {
                return 0;
            }
            $fallback = itm_seed_insert_random_fallback_row($conn, $tableName, $companyId, $error);
            if ($fallback > 0) {
                return $fallback;
            }
            if ($error === '') {
                $error = 'No sample rows could be inserted from db/02_data_sample.sql for this module.';
            }
        }

        return $insertCount;
    }
}

if (!function_exists('itm_seed_all_tables_from_database_sql')) {
    /**
     * Seeds all table samples from db/02_data_sample.sql while keeping inserts idempotent.
     */
    function itm_seed_all_tables_from_database_sql($conn, $companyId, &$error = '', &$seedReport = [])
    {
        $error = '';
        $seedReport = [
            'inserted_tables' => [],
            'skipped_tables' => [],
            'failed_tables' => [],
        ];
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $sqlBody = itm_database_sql_read_sample();
        if ($sqlBody === '') {
            $error = 'Sample source file db/02_data_sample.sql was not found or is empty.';
            return 0;
        }

        $insertCount = 0;
        foreach (itm_parse_database_sql_inserts($sqlBody) as $tableName => $insertRows) {
            unset($insertRows);
            if (!itm_is_safe_identifier($tableName)) {
                continue;
            }

            $tableExistsRes = mysqli_query(
                $conn,
                "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tableName) . "'"
            );
            if (!$tableExistsRes || mysqli_num_rows($tableExistsRes) === 0) {
                $seedReport['skipped_tables'][] = $tableName . ' (table does not exist)';
                continue;
            }

            $hasCompanyId = itm_table_has_column($conn, $tableName, 'company_id');
            $rowCount = 0;
            if ($hasCompanyId) {
                $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?');
                if (!$countStmt) {
                    continue;
                }
                mysqli_stmt_bind_param($countStmt, 'i', $companyId);
                mysqli_stmt_execute($countStmt);
                $countResult = mysqli_stmt_get_result($countStmt);
                $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
                $rowCount = isset($countRow['total_count']) ? (int)$countRow['total_count'] : 0;
                mysqli_stmt_close($countStmt);
            } else {
                $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '`');
                $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
                $rowCount = isset($countRow['total_count']) ? (int)$countRow['total_count'] : 0;
            }

            if ($rowCount > 0) {
                $seedReport['skipped_tables'][] = $tableName . ' (already has data)';
                continue;
            }

            $tableError = '';
            $tableInsertCount = itm_seed_table_from_database_sql($conn, $tableName, $companyId, $tableError);
            if ($tableInsertCount > 0) {
                $insertCount += $tableInsertCount;
                $seedReport['inserted_tables'][] = $tableName . ' (' . $tableInsertCount . ' rows)';
            } elseif ($tableError !== '') {
                $seedReport['failed_tables'][] = $tableName . ' (' . $tableError . ')';
            } else {
                $seedReport['skipped_tables'][] = $tableName . ' (no valid sample rows)';
            }
        }

        if ($insertCount === 0) {
            $notImportedTables = array_merge($seedReport['skipped_tables'], $seedReport['failed_tables']);
            $error = 'No sample rows were inserted. Not imported tables: ' . implode(', ', $notImportedTables) . '.';
        } elseif (!empty($seedReport['failed_tables'])) {
            $error = 'Some sample data could not be imported: ' . implode(', ', $seedReport['failed_tables']) . '.';
        }

        return $insertCount;
    }
}
