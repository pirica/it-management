<?php
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'create', 'equipment');

require_once __DIR__ . '/equipment_assignment_sync.php';
require_once ROOT_PATH . 'includes/itm_fk_option_labels.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$originalData = null;
$csrfToken = itm_get_csrf_token();

function equipment_build_select_options(array $rows, callable $labelBuilder): array
{
    $items = [];
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $items[] = ['id' => $id, 'label' => (string)$labelBuilder($row)];
    }

    return $items;
}

function equipment_fetch_department_select_options(mysqli $conn, int $companyId): array
{
    return equipment_build_select_options(
        itm_department_select_rows_for_company($conn, $companyId),
        'itm_department_option_label'
    );
}

function equipment_fetch_supplier_select_options(mysqli $conn, int $companyId): array
{
    return equipment_build_select_options(
        itm_supplier_select_rows_for_company($conn, $companyId),
        'itm_supplier_option_label'
    );
}

function equipment_fetch_location_select_options(mysqli $conn, int $companyId): array
{
    return equipment_build_select_options(
        itm_location_select_rows_for_company($conn, $companyId, true),
        'itm_location_option_label'
    );
}

function equipment_fetch_rack_select_options(mysqli $conn, int $companyId): array
{
    return equipment_build_select_options(
        itm_rack_select_rows_for_company($conn, $companyId),
        'itm_rack_option_label'
    );
}

function fetch_options($conn, $table, $label = 'name', $where = '') {
    $items = [];
    if (!equipment_table_exists($conn, $table)) {
        return $items;
    }
    $hasCompanyColumn = equipment_table_has_column($conn, $table, 'company_id');
    $companyScope = ($hasCompanyColumn && isset($GLOBALS['company_id']) && (int)$GLOBALS['company_id'] > 0)
        ? 'company_id = ' . (int)$GLOBALS['company_id']
        : '';

    $where = trim((string)$where);
    if ($companyScope !== '') {
        if ($where === '') {
            $where = 'WHERE ' . $companyScope;
        } elseif (stripos($where, 'where') === 0) {
            $where .= ' AND ' . $companyScope;
        } else {
            $where = 'WHERE ' . $where . ' AND ' . $companyScope;
        }
    }

    $res = mysqli_query($conn, "SELECT id, $label AS label FROM $table $where ORDER BY $label");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    return $items;
}

function equipment_append_persisted_department_option(mysqli $conn, array &$departments, int $departmentId, int $companyId): void
{
    if ($departmentId <= 0) {
        return;
    }

    foreach ($departments as $row) {
        if ((int)($row['id'] ?? 0) === $departmentId) {
            return;
        }
    }

    $label = '';
    $stmt = mysqli_prepare($conn, 'SELECT name, code FROM departments WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $departmentId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (is_array($row)) {
            $label = itm_department_option_label($row);
        }
    }

    if ($label !== '') {
        $departments[] = ['id' => $departmentId, 'label' => $label];
    }
}

function equipment_append_persisted_supplier_option(mysqli $conn, array &$suppliers, int $supplierId, int $companyId): void
{
    if ($supplierId <= 0) {
        return;
    }

    foreach ($suppliers as $row) {
        if ((int)($row['id'] ?? 0) === $supplierId) {
            return;
        }
    }

    $label = '';
    $stmt = mysqli_prepare($conn, 'SELECT name, supplier_code FROM suppliers WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $supplierId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (is_array($row)) {
            $label = itm_supplier_option_label($row);
        }
    }

    if ($label === '') {
        $stmtFallback = mysqli_prepare($conn, 'SELECT name, supplier_code FROM suppliers WHERE id = ? LIMIT 1');
        if ($stmtFallback) {
            mysqli_stmt_bind_param($stmtFallback, 'i', $supplierId);
            mysqli_stmt_execute($stmtFallback);
            $resFallback = mysqli_stmt_get_result($stmtFallback);
            $rowFallback = $resFallback ? mysqli_fetch_assoc($resFallback) : null;
            mysqli_stmt_close($stmtFallback);
            if (is_array($rowFallback)) {
                $label = itm_supplier_option_label($rowFallback);
            }
        }
    }

    if ($label !== '') {
        $suppliers[] = ['id' => $supplierId, 'label' => $label];
    }
}

function equipment_fetch_employee_options(mysqli $conn, int $companyId): array
{
    $items = [];
    if ($companyId <= 0) {
        return $items;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, first_name, last_name, display_name, username,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''),
                    NULLIF(TRIM(COALESCE(display_name, '')), ''),
                    CONCAT('Employee #', id)
                ) AS fallback_label
         FROM employees
         WHERE company_id = ?
         ORDER BY fallback_label"
    );
    if (!$stmt) {
        return $items;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $label = itm_employee_manager_option_label($row);
        if ($label === '') {
            $label = trim((string)($row['fallback_label'] ?? ''));
        }
        $items[] = ['id' => (int)($row['id'] ?? 0), 'label' => $label];
    }
    mysqli_stmt_close($stmt);

    return $items;
}

function equipment_resolve_employee_label(mysqli $conn, int $employeeId, int $companyId): string
{
    if ($employeeId <= 0) {
        return '';
    }

    $label = '';
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, first_name, last_name, display_name, username,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''),
                    NULLIF(TRIM(COALESCE(display_name, '')), ''),
                    CONCAT('Employee #', id)
                ) AS fallback_label
         FROM employees
         WHERE id = ? AND company_id = ?
         LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (is_array($row)) {
            $label = itm_employee_manager_option_label($row);
            if ($label === '') {
                $label = trim((string)($row['fallback_label'] ?? ''));
            }
        }
    }

    if ($label === '') {
        $stmtFallback = mysqli_prepare(
            $conn,
            "SELECT id, first_name, last_name, display_name, username,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''),
                        NULLIF(TRIM(COALESCE(display_name, '')), ''),
                        CONCAT('Employee #', id)
                    ) AS fallback_label
             FROM employees
             WHERE id = ?
             LIMIT 1"
        );
        if ($stmtFallback) {
            mysqli_stmt_bind_param($stmtFallback, 'i', $employeeId);
            mysqli_stmt_execute($stmtFallback);
            $resFallback = mysqli_stmt_get_result($stmtFallback);
            $rowFallback = $resFallback ? mysqli_fetch_assoc($resFallback) : null;
            mysqli_stmt_close($stmtFallback);
            if (is_array($rowFallback)) {
                $label = itm_employee_manager_option_label($rowFallback);
                if ($label === '') {
                    $label = trim((string)($rowFallback['fallback_label'] ?? ''));
                }
            }
        }
    }

    return $label;
}

function equipment_append_persisted_employee_option(mysqli $conn, array &$employees, int $employeeId, int $companyId): void
{
    if ($employeeId <= 0) {
        return;
    }

    foreach ($employees as $row) {
        if ((int)($row['id'] ?? 0) === $employeeId) {
            return;
        }
    }

    $label = equipment_resolve_employee_label($conn, $employeeId, $companyId);
    if ($label !== '') {
        $employees[] = ['id' => $employeeId, 'label' => $label];
    }
}

function equipment_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query(
        $conn,
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableEsc}' LIMIT 1"
    );

    $cache[$table] = $res && mysqli_num_rows($res) > 0;
    return $cache[$table];
}

function equipment_table_has_column(mysqli $conn, string $table, string $column): bool
{
    if (!equipment_table_exists($conn, $table)) {
        return false;
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res && mysqli_num_rows($res) > 0;
}

function equipment_table_varchar_length(mysqli $conn, string $table, string $column): int
{
    static $cache = [];

    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!equipment_table_exists($conn, $table)) {
        $cache[$cacheKey] = 0;
        return 0;
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $type = strtolower((string)($row['Type'] ?? ''));
    if (preg_match('/^varchar\((\d+)\)$/', $type, $matches) === 1) {
        $cache[$cacheKey] = (int)$matches[1];
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = 0;
    return 0;
}

function equipment_name_exists(mysqli $conn, int $companyId, string $name, int $excludeId = 0): bool
{
    if ($companyId <= 0 || trim($name) === '') {
        return false;
    }

    $sql = 'SELECT id FROM equipment WHERE company_id = ? AND name = ?';
    $types = 'is';
    $params = [$companyId, $name];

    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function equipment_optional_unique_field_exists(mysqli $conn, int $companyId, string $fieldName, string $value, int $excludeId = 0): bool
{
    $fieldName = trim($fieldName);
    $value = trim($value);
    if ($companyId <= 0 || $fieldName === '' || $value === '' || !equipment_table_has_column($conn, 'equipment', $fieldName)) {
        return false;
    }

    $allowedFields = ['serial_number', 'hostname', 'ip_address'];
    if (!in_array($fieldName, $allowedFields, true)) {
        return false;
    }

    $sql = "SELECT id FROM equipment WHERE company_id = ? AND {$fieldName} = ?";
    $types = 'is';
    $params = [$companyId, $value];

    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function equipment_delete_idf_data(mysqli $conn, int $companyId, int $equipmentId): void
{
    if ($equipmentId <= 0 || $companyId <= 0) {
        return;
    }

    $positionIds = [];
    $equipmentIdString = (string)$equipmentId;
    $stmtPositions = mysqli_prepare(
        $conn,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ? AND equipment_id = ?"
    );
    if ($stmtPositions) {
        mysqli_stmt_bind_param($stmtPositions, 'is', $companyId, $equipmentIdString);
        mysqli_stmt_execute($stmtPositions);
        $resPositions = mysqli_stmt_get_result($stmtPositions);
        while ($resPositions && ($row = mysqli_fetch_assoc($resPositions))) {
            $positionId = (int)($row['id'] ?? 0);
            if ($positionId > 0) {
                $positionIds[$positionId] = $positionId;
            }
        }
        mysqli_stmt_close($stmtPositions);
    }

    if ($positionIds) {
        $positionIdList = implode(',', array_values($positionIds));
        mysqli_query(
            $conn,
            "DELETE FROM idf_links
             WHERE company_id = " . (int)$companyId . "
               AND (
                    port_id_a IN (
                        SELECT id FROM idf_ports
                        WHERE company_id = " . (int)$companyId . " AND position_id IN ({$positionIdList})
                    )
                    OR
                    port_id_b IN (
                        SELECT id FROM idf_ports
                        WHERE company_id = " . (int)$companyId . " AND position_id IN ({$positionIdList})
                    )
               )"
        );
        mysqli_query(
            $conn,
            "DELETE FROM idf_ports
             WHERE company_id = " . (int)$companyId . "
               AND position_id IN ({$positionIdList})"
        );
    }

    $equipmentIdValue = "'" . mysqli_real_escape_string($conn, (string)$equipmentId) . "'";
    mysqli_query(
        $conn,
        "DELETE FROM idf_positions
         WHERE company_id = " . (int)$companyId . "
           AND equipment_id = {$equipmentIdValue}"
    );
}

function equipment_resolve_idf_device_type_id(mysqli $conn, int $companyId, int $equipmentTypeId): int
{
    $equipmentTypeName = '';
    $stmtEquipmentType = mysqli_prepare($conn, 'SELECT name FROM equipment_types WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmtEquipmentType) {
        mysqli_stmt_bind_param($stmtEquipmentType, 'ii', $equipmentTypeId, $companyId);
        mysqli_stmt_execute($stmtEquipmentType);
        $resEquipmentType = mysqli_stmt_get_result($stmtEquipmentType);
        $equipmentTypeRow = $resEquipmentType ? mysqli_fetch_assoc($resEquipmentType) : null;
        mysqli_stmt_close($stmtEquipmentType);
        $equipmentTypeName = strtolower(trim((string)($equipmentTypeRow['name'] ?? '')));
    }

    $idfTypeName = 'other';
    if (strpos($equipmentTypeName, 'switch') !== false) {
        $idfTypeName = 'switch';
    } elseif (strpos($equipmentTypeName, 'patch') !== false) {
        $idfTypeName = 'patch_panel';
    } elseif (strpos($equipmentTypeName, 'ups') !== false) {
        $idfTypeName = 'ups';
    } elseif (strpos($equipmentTypeName, 'server') !== false) {
        $idfTypeName = 'server';
    }

    $stmtIdfType = mysqli_prepare(
        $conn,
        'SELECT id FROM idf_device_type WHERE company_id = ? AND LOWER(idfdevicetype_name) = ? ORDER BY id ASC LIMIT 1'
    );
    if ($stmtIdfType) {
        mysqli_stmt_bind_param($stmtIdfType, 'is', $companyId, $idfTypeName);
        mysqli_stmt_execute($stmtIdfType);
        $resIdfType = mysqli_stmt_get_result($stmtIdfType);
        $idfTypeRow = $resIdfType ? mysqli_fetch_assoc($resIdfType) : null;
        mysqli_stmt_close($stmtIdfType);
        if ($idfTypeRow) {
            return (int)($idfTypeRow['id'] ?? 0);
        }
    }
    return 0;
}

function equipment_find_available_idf_slot(mysqli $conn, int $companyId, int $idfId): array
{
    $slot = ['position_id' => 0, 'position_no' => 0, 'requires_insert' => false];
    if ($companyId <= 0 || $idfId <= 0) {
        return $slot;
    }

    $stmtEmpty = mysqli_prepare(
        $conn,
        "SELECT id, position_no
         FROM idf_positions
         WHERE company_id = ?
           AND idf_id = ?
           AND (
               equipment_id IS NULL
               OR TRIM(equipment_id) = ''
               OR equipment_id = '0'
               OR equipment_id NOT REGEXP '^[0-9]+$'
           )
         ORDER BY position_no ASC
         LIMIT 1"
    );
    if ($stmtEmpty) {
        mysqli_stmt_bind_param($stmtEmpty, 'ii', $companyId, $idfId);
        mysqli_stmt_execute($stmtEmpty);
        $resEmpty = mysqli_stmt_get_result($stmtEmpty);
        $emptyRow = $resEmpty ? mysqli_fetch_assoc($resEmpty) : null;
        mysqli_stmt_close($stmtEmpty);
        if ($emptyRow) {
            $slot['position_id'] = (int)($emptyRow['id'] ?? 0);
            $slot['position_no'] = (int)($emptyRow['position_no'] ?? 0);
            return $slot;
        }
    }

    $occupiedPositions = [];
    $maxPosInDb = 0;
    $stmtUsed = mysqli_prepare(
        $conn,
        "SELECT position_no
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ?"
    );
    if ($stmtUsed) {
        mysqli_stmt_bind_param($stmtUsed, 'ii', $companyId, $idfId);
        mysqli_stmt_execute($stmtUsed);
        $resUsed = mysqli_stmt_get_result($stmtUsed);
        while ($resUsed && ($usedRow = mysqli_fetch_assoc($resUsed))) {
            $usedPositionNo = (int)($usedRow['position_no'] ?? 0);
            if ($usedPositionNo <= 0) {
                continue;
            }
            $occupiedPositions[$usedPositionNo] = true;
            if ($usedPositionNo > $maxPosInDb) {
                $maxPosInDb = $usedPositionNo;
            }
        }
        mysqli_stmt_close($stmtUsed);
    }

    // Why: Rack view always shows at least 10 slots, so missing rows in that range should behave as free positions.
    $scanLimit = max(10, $maxPosInDb);
    for ($positionNo = 1; $positionNo <= $scanLimit; $positionNo++) {
        if (!isset($occupiedPositions[$positionNo])) {
            $slot['position_no'] = $positionNo;
            $slot['requires_insert'] = true;
            return $slot;
        }
    }

    $slot['position_no'] = $maxPosInDb > 0 ? ($maxPosInDb + 1) : 1;
    $slot['requires_insert'] = true;
    return $slot;
}

function equipment_sync_idf_position_and_ports(mysqli $conn, int $companyId, array $equipmentData): string
{
    $equipmentId = (int)($equipmentData['id'] ?? 0);
    $idfId = (int)($equipmentData['idf_id'] ?? 0);
    $equipmentName = trim((string)($equipmentData['name'] ?? ''));
    $equipmentTypeId = (int)($equipmentData['equipment_type_id'] ?? 0);
    $switchRj45Id = (int)($equipmentData['switch_rj45_id'] ?? 0);
    $layoutId = (int)($equipmentData['switch_port_numbering_layout_id'] ?? 0);
    $notes = trim((string)($equipmentData['notes'] ?? ''));
    $switchEnvironmentId = (int)($equipmentData['switch_environment_id'] ?? 0);
    $switchFiberPortsNumberRaw = trim((string)($equipmentData['switch_fiber_ports_number'] ?? ''));

    if ($equipmentId <= 0) {
        return '';
    }

    $stmtCurrent = mysqli_prepare(
        $conn,
        "SELECT id, idf_id, position_no
         FROM idf_positions
         WHERE company_id = ? AND equipment_id = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    $currentPosition = null;
    if ($stmtCurrent) {
        $equipmentIdStr = (string)$equipmentId;
        mysqli_stmt_bind_param($stmtCurrent, 'is', $companyId, $equipmentIdStr);
        mysqli_stmt_execute($stmtCurrent);
        $resCurrent = mysqli_stmt_get_result($stmtCurrent);
        $currentPosition = $resCurrent ? mysqli_fetch_assoc($resCurrent) : null;
        mysqli_stmt_close($stmtCurrent);
    }

    if ($idfId <= 0) {
        if ($currentPosition) {
            $positionId = (int)($currentPosition['id'] ?? 0);
            if ($positionId > 0) {
                mysqli_query($conn, "DELETE FROM idf_ports WHERE position_id = " . $positionId . " AND company_id = " . $companyId);
                mysqli_query($conn, "DELETE FROM idf_positions WHERE id = " . $positionId . " AND company_id = " . $companyId . " LIMIT 1");

            }
        }
        mysqli_query($conn, "UPDATE switch_ports SET idf_id = NULL WHERE company_id = " . $companyId . " AND equipment_id = " . $equipmentId);
        return '';
    }

    $targetPositionId = 0;
    $targetPositionNo = 0;
    if ($currentPosition && (int)($currentPosition['idf_id'] ?? 0) === $idfId) {
        $targetPositionId = (int)($currentPosition['id'] ?? 0);
        $targetPositionNo = (int)($currentPosition['position_no'] ?? 0);
    } else {
        $availableSlot = equipment_find_available_idf_slot($conn, $companyId, $idfId);
        $targetPositionId = (int)($availableSlot['position_id'] ?? 0);
        $targetPositionNo = (int)($availableSlot['position_no'] ?? 0);
        $requiresInsert = !empty($availableSlot['requires_insert']);

        if ($targetPositionNo <= 0) {
            return 'Unable to assign an IDF position. Please verify IDF settings.';
        }

        if ($targetPositionId <= 0 && $requiresInsert) {
            $bootstrapDeviceTypeId = equipment_resolve_idf_device_type_id($conn, $companyId, $equipmentTypeId);
            if ($bootstrapDeviceTypeId <= 0) {
                return 'A database error occurred. Please try again.';
            }
            $stmtInsertBootstrapPosition = mysqli_prepare(
                $conn,
                "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, rj45_count, sfp_count, switch_port_numbering_layout_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 0, NULLIF(?, 0), ?)"
            );
            if ($stmtInsertBootstrapPosition) {
                $equipmentIdStr = (string)$equipmentId;
                $notesForInsert = $notes !== '' ? $notes : null;
                mysqli_stmt_bind_param(
                    $stmtInsertBootstrapPosition,
                    'iiiissis',
                    $companyId,
                    $idfId,
                    $targetPositionNo,
                    $bootstrapDeviceTypeId,
                    $equipmentName,
                    $equipmentIdStr,
                    $layoutId,
                    $notesForInsert
                );
                if (!mysqli_stmt_execute($stmtInsertBootstrapPosition)) {
                    $bootstrapError = mysqli_stmt_error($stmtInsertBootstrapPosition);
                    mysqli_stmt_close($stmtInsertBootstrapPosition);
                    return $bootstrapError !== '' ? $bootstrapError : 'A database error occurred. Please try again.';
                }
                $targetPositionId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($stmtInsertBootstrapPosition);
            }
        }

        if ($targetPositionId <= 0) {
            return 'Unable to assign an IDF position. Please verify IDF settings.';
        }
    }

    $idfDeviceTypeId = equipment_resolve_idf_device_type_id($conn, $companyId, $equipmentTypeId);
    if ($idfDeviceTypeId <= 0) {
        return 'Unable to resolve IDF device type for the selected equipment.';
    }

    $equipmentIdStr = (string)$equipmentId;
    $escapedName = "'" . mysqli_real_escape_string($conn, $equipmentName) . "'";
    $escapedEquipmentId = "'" . mysqli_real_escape_string($conn, $equipmentIdStr) . "'";
    $escapedNotes = $notes === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $notes) . "'";
    $layoutSql = $layoutId > 0 ? (string)$layoutId : 'NULL';
    $equipmentIdEscaped = mysqli_real_escape_string($conn, (string)$equipmentId);
    $stmtDuplicateDeviceName = mysqli_prepare(
        $conn,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ?
           AND device_name = ?
           AND id <> ?
           AND (equipment_id IS NULL OR equipment_id <> '{$equipmentIdEscaped}')
         LIMIT 1"
    );
    if ($stmtDuplicateDeviceName) {
        mysqli_stmt_bind_param($stmtDuplicateDeviceName, 'isi', $companyId, $equipmentName, $targetPositionId);
        mysqli_stmt_execute($stmtDuplicateDeviceName);
        $resDuplicateDeviceName = mysqli_stmt_get_result($stmtDuplicateDeviceName);
        $duplicateDeviceNameRow = $resDuplicateDeviceName ? mysqli_fetch_assoc($resDuplicateDeviceName) : null;
        mysqli_stmt_close($stmtDuplicateDeviceName);
        if ($duplicateDeviceNameRow) {
            return 'Device name already exists. Please choose a unique device name.';
        }
    }

    mysqli_query(
        $conn,
        "UPDATE idf_positions
         SET idf_id = {$idfId},
             position_no = {$targetPositionNo},
             device_type = {$idfDeviceTypeId},
             device_name = {$escapedName},
             equipment_id = {$escapedEquipmentId},
             switch_port_numbering_layout_id = {$layoutSql},
             notes = {$escapedNotes}
         WHERE id = {$targetPositionId}
         LIMIT 1"
    );

    if ($currentPosition && (int)($currentPosition['idf_id'] ?? 0) !== $idfId) {
        $oldPositionId = (int)($currentPosition['id'] ?? 0);
        if ($oldPositionId > 0 && $oldPositionId !== $targetPositionId) {
            mysqli_query($conn, "DELETE FROM idf_ports WHERE position_id = {$oldPositionId} AND company_id = {$companyId}");
            mysqli_query(
                $conn,
                "UPDATE idf_positions
                 SET equipment_id = NULL, device_name = CONCAT('Empty Position ', position_no), rj45_count = 0, sfp_count = 0, notes = NULL
                 WHERE id = {$oldPositionId}
                 LIMIT 1"
            );
        }
    }

    $portCount = 0;
    if ($switchRj45Id > 0) {
        $stmtRj45 = mysqli_prepare($conn, "SELECT name FROM equipment_rj45 WHERE id = ? AND company_id = ? LIMIT 1");
        if ($stmtRj45) {
            mysqli_stmt_bind_param($stmtRj45, 'ii', $switchRj45Id, $companyId);
            mysqli_stmt_execute($stmtRj45);
            $resRj45 = mysqli_stmt_get_result($stmtRj45);
            $rj45Row = $resRj45 ? mysqli_fetch_assoc($resRj45) : null;
            mysqli_stmt_close($stmtRj45);
            if ($rj45Row && preg_match('/(\d+)/', (string)($rj45Row['name'] ?? ''), $matches)) {
                $portCount = (int)$matches[1];
            }
        }
    }
    // Why: idf_positions.sfp_count feeds rack-summary badges alongside materialized fiber idf_ports; keep it aligned with Network Details Fiber Ports Number on every save path.
    $sfpSummaryCount = max(0, (int)$switchFiberPortsNumberRaw);

    mysqli_query($conn, "UPDATE idf_positions SET rj45_count = {$portCount}, sfp_count = {$sfpSummaryCount} WHERE id = {$targetPositionId} LIMIT 1");

    if ($portCount > 0) {
        $unknownStatusId = 0;
        $stmtStatus = mysqli_prepare($conn, "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1");
        if ($stmtStatus) {
            mysqli_stmt_bind_param($stmtStatus, 'i', $companyId);
            mysqli_stmt_execute($stmtStatus);
            $resStatus = mysqli_stmt_get_result($stmtStatus);
            $statusRow = $resStatus ? mysqli_fetch_assoc($resStatus) : null;
            mysqli_stmt_close($stmtStatus);
            $unknownStatusId = (int)($statusRow['id'] ?? 0);
        }
        $rj45PortTypeId = 0;
        $stmtPortType = mysqli_prepare($conn, "SELECT id FROM switch_port_types WHERE company_id = ? AND LOWER(type) = 'rj45' LIMIT 1");
        if ($stmtPortType) {
            mysqli_stmt_bind_param($stmtPortType, 'i', $companyId);
            mysqli_stmt_execute($stmtPortType);
            $resPortType = mysqli_stmt_get_result($stmtPortType);
            $portTypeRow = $resPortType ? mysqli_fetch_assoc($resPortType) : null;
            mysqli_stmt_close($stmtPortType);
            $rj45PortTypeId = (int)($portTypeRow['id'] ?? 0);
        }
        if ($unknownStatusId > 0 && $rj45PortTypeId > 0) {
            $managementSql = $switchEnvironmentId > 0 ? (string)$switchEnvironmentId : 'NULL';
            for ($n = 1; $n <= $portCount; $n++) {
                mysqli_query(
                    $conn,
                    "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status_id, switch_port_numbering_layout_id, management_id)
                     VALUES ({$companyId}, {$targetPositionId}, {$n}, {$rj45PortTypeId}, {$unknownStatusId}, NULLIF({$layoutSql}, 0), {$managementSql})
                     ON DUPLICATE KEY UPDATE
                        status_id = VALUES(status_id),
                        switch_port_numbering_layout_id = COALESCE(VALUES(switch_port_numbering_layout_id), switch_port_numbering_layout_id),
                        management_id = COALESCE(VALUES(management_id), management_id)"
                );
            }
        }
    }

    $equipmentHostnameForSync = trim((string)($equipmentData['hostname'] ?? ''));

    $stmtMirrorSwitchPorts = mysqli_prepare(
        $conn,
        "INSERT INTO idf_ports (
            company_id, position_id, port_no, port_type, label, status_id, vlan_id, notes, connected_to,
            switch_port_numbering_layout_id, management_id
         )
         SELECT
            ?, ?, sp.port_number,
            CASE
                WHEN sp.port_type REGEXP '^[0-9]+$' THEN CAST(sp.port_type AS UNSIGNED)
                ELSE COALESCE(spt.id, 0)
            END AS resolved_port_type,
            COALESCE(NULLIF(NULLIF(TRIM(sp.to_patch_port), ''), '0'), ''),
            COALESCE(NULLIF(sp.status_id, 0), 1),
            sp.vlan_id,
            COALESCE(sp.comments, ''),
            COALESCE(NULLIF(TRIM(sp.hostname), ''), NULLIF(?, '')),
            NULLIF(?, 0),
            NULLIF(?, 0)
         FROM switch_ports sp
         LEFT JOIN switch_port_types spt
           ON spt.company_id = sp.company_id
          AND LOWER(TRIM(spt.type)) = LOWER(TRIM(sp.port_type))
         WHERE sp.company_id = ?
           AND sp.equipment_id = ?
           AND sp.port_number > 0
         HAVING resolved_port_type > 0
         ON DUPLICATE KEY UPDATE
            label = COALESCE(NULLIF(NULLIF(VALUES(label), ''), '0'), label),
            status_id = VALUES(status_id),
            vlan_id = VALUES(vlan_id),
            notes = VALUES(notes),
            connected_to = COALESCE(NULLIF(VALUES(connected_to), ''), connected_to),
            switch_port_numbering_layout_id = COALESCE(VALUES(switch_port_numbering_layout_id), idf_ports.switch_port_numbering_layout_id),
            management_id = COALESCE(VALUES(management_id), idf_ports.management_id)"
    );
    if ($stmtMirrorSwitchPorts) {
        mysqli_stmt_bind_param(
            $stmtMirrorSwitchPorts,
            'iissiii',
            $companyId,
            $targetPositionId,
            $equipmentHostnameForSync,
            $layoutId,
            $switchEnvironmentId,
            $companyId,
            $equipmentId
        );
        mysqli_stmt_execute($stmtMirrorSwitchPorts);
        mysqli_stmt_close($stmtMirrorSwitchPorts);
    }

    mysqli_query($conn, "UPDATE switch_ports SET idf_id = " . (int)$idfId . " WHERE company_id = " . (int)$companyId . " AND equipment_id = " . (int)$equipmentId);

    equipment_finalize_linked_port_capacity(
        $conn,
        $companyId,
        $equipmentId,
        $targetPositionId,
        $idfId,
        $portCount,
        $sfpSummaryCount,
        $layoutId,
        $switchEnvironmentId,
        trim((string)($equipmentData['hostname'] ?? ''))
    );

    return '';
}

function equipment_prune_idf_position_port_capacity(
    mysqli $conn,
    int $companyId,
    int $positionId,
    int $rj45Count,
    int $sfpCount,
    int $rj45PortTypeId,
    int $equipmentId = 0
): void {
    if ($companyId <= 0 || $positionId <= 0) {
        return;
    }

    if ($rj45PortTypeId > 0) {
        if ($rj45Count > 0) {
            $stmtDeleteExtraRj45 = mysqli_prepare(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = ?
                   AND position_id = ?
                   AND port_type = ?
                   AND port_no > ?"
            );
            if ($stmtDeleteExtraRj45) {
                mysqli_stmt_bind_param($stmtDeleteExtraRj45, 'iiii', $companyId, $positionId, $rj45PortTypeId, $rj45Count);
                mysqli_stmt_execute($stmtDeleteExtraRj45);
                mysqli_stmt_close($stmtDeleteExtraRj45);
            }
        } else {
            $stmtDeleteAllRj45 = mysqli_prepare(
                $conn,
                "DELETE FROM idf_ports
                 WHERE company_id = ?
                   AND position_id = ?
                   AND port_type = ?"
            );
            if ($stmtDeleteAllRj45) {
                mysqli_stmt_bind_param($stmtDeleteAllRj45, 'iii', $companyId, $positionId, $rj45PortTypeId);
                mysqli_stmt_execute($stmtDeleteAllRj45);
                mysqli_stmt_close($stmtDeleteAllRj45);
            }
        }
    }

    if ($sfpCount > 0) {
        $stmtDeleteExtraSfp = mysqli_prepare(
            $conn,
            "DELETE FROM idf_ports
             WHERE company_id = ?
               AND position_id = ?
               AND port_type IN (
                    SELECT id
                    FROM switch_port_types
                    WHERE company_id = ?
                      AND LOWER(type) LIKE '%sfp%'
               )
               AND port_no > ?"
        );
        if ($stmtDeleteExtraSfp) {
            mysqli_stmt_bind_param(
                $stmtDeleteExtraSfp,
                'iiii',
                $companyId,
                $positionId,
                $companyId,
                $sfpCount
            );
            mysqli_stmt_execute($stmtDeleteExtraSfp);
            mysqli_stmt_close($stmtDeleteExtraSfp);
        }
    } else {
        $stmtDeleteAllSfp = mysqli_prepare(
            $conn,
            "DELETE FROM idf_ports
             WHERE company_id = ?
               AND position_id = ?
               AND port_type IN (
                    SELECT id
                    FROM switch_port_types
                    WHERE company_id = ?
                      AND LOWER(type) LIKE '%sfp%'
               )"
        );
        if ($stmtDeleteAllSfp) {
            mysqli_stmt_bind_param($stmtDeleteAllSfp, 'iii', $companyId, $positionId, $companyId);
            mysqli_stmt_execute($stmtDeleteAllSfp);
            mysqli_stmt_close($stmtDeleteAllSfp);
        }
    }
}

function equipment_finalize_linked_port_capacity(
    mysqli $conn,
    int $companyId,
    int $equipmentId,
    int $positionId,
    int $idfId,
    int $rj45Count,
    int $sfpCount,
    int $layoutId,
    int $switchEnvironmentId,
    string $equipmentHostname
): void {
    if ($companyId <= 0 || $equipmentId <= 0 || $positionId <= 0) {
        return;
    }

    require_once ROOT_PATH . 'modules/idfs/idf_ports_sync.php';

    $unknownStatusId = 0;
    $stmtStatus = mysqli_prepare($conn, "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1");
    if ($stmtStatus) {
        mysqli_stmt_bind_param($stmtStatus, 'i', $companyId);
        mysqli_stmt_execute($stmtStatus);
        $resStatus = mysqli_stmt_get_result($stmtStatus);
        $statusRow = $resStatus ? mysqli_fetch_assoc($resStatus) : null;
        mysqli_stmt_close($stmtStatus);
        $unknownStatusId = (int)($statusRow['id'] ?? 0);
    }
    if ($unknownStatusId <= 0) {
        return;
    }

    $rj45PortTypeId = 0;
    $rj45PortTypeName = 'RJ45';
    $stmtRj45Type = mysqli_prepare($conn, "SELECT id, type FROM switch_port_types WHERE company_id = ? AND LOWER(type) = 'rj45' LIMIT 1");
    if ($stmtRj45Type) {
        mysqli_stmt_bind_param($stmtRj45Type, 'i', $companyId);
        mysqli_stmt_execute($stmtRj45Type);
        $resRj45Type = mysqli_stmt_get_result($stmtRj45Type);
        $rj45TypeRow = $resRj45Type ? mysqli_fetch_assoc($resRj45Type) : null;
        mysqli_stmt_close($stmtRj45Type);
        $rj45PortTypeId = (int)($rj45TypeRow['id'] ?? 0);
        $rj45PortTypeName = trim((string)($rj45TypeRow['type'] ?? 'RJ45'));
        if ($rj45PortTypeName === '') {
            $rj45PortTypeName = 'RJ45';
        }
    }

    $fiberPortTypeId = 0;
    $fiberPortTypeName = 'SFP';
    if ($sfpCount > 0) {
        $stmtFiberType = mysqli_prepare(
            $conn,
            "SELECT id, type
             FROM switch_port_types
             WHERE company_id = ?
               AND (LOWER(type) = 'sfp' OR LOWER(type) = 'sfp+' OR LOWER(type) = 'sfp plus')
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtFiberType) {
            mysqli_stmt_bind_param($stmtFiberType, 'i', $companyId);
            mysqli_stmt_execute($stmtFiberType);
            $resFiberType = mysqli_stmt_get_result($stmtFiberType);
            $fiberTypeRow = $resFiberType ? mysqli_fetch_assoc($resFiberType) : null;
            mysqli_stmt_close($stmtFiberType);
            $fiberPortTypeId = (int)($fiberTypeRow['id'] ?? 0);
            $fiberPortTypeName = trim((string)($fiberTypeRow['type'] ?? ''));
            if ($fiberPortTypeName === '') {
                $fiberPortTypeName = 'SFP';
            }
        }
    }

    $layoutSql = $layoutId > 0 ? (string)$layoutId : 'NULL';
    $managementSql = $switchEnvironmentId > 0 ? (string)$switchEnvironmentId : 'NULL';

    if ($rj45Count > 0 && $rj45PortTypeId > 0) {
        for ($portNo = 1; $portNo <= $rj45Count; $portNo++) {
            mysqli_query(
                $conn,
                "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status_id, switch_port_numbering_layout_id, management_id)
                 VALUES ({$companyId}, {$positionId}, {$portNo}, {$rj45PortTypeId}, {$unknownStatusId}, NULLIF({$layoutSql}, 0), {$managementSql})
                 ON DUPLICATE KEY UPDATE
                    status_id = VALUES(status_id),
                    switch_port_numbering_layout_id = COALESCE(VALUES(switch_port_numbering_layout_id), switch_port_numbering_layout_id),
                    management_id = COALESCE(VALUES(management_id), management_id)"
            );
        }
    }

    if ($sfpCount > 0 && $fiberPortTypeId > 0) {
        for ($fiberIdx = 1; $fiberIdx <= $sfpCount; $fiberIdx++) {
            $fiberPortNo = idf_resolve_synthetic_fiber_port_no(0, $fiberIdx);
            mysqli_query(
                $conn,
                "INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status_id, switch_port_numbering_layout_id, management_id)
                 VALUES ({$companyId}, {$positionId}, {$fiberPortNo}, {$fiberPortTypeId}, {$unknownStatusId}, NULLIF({$layoutSql}, 0), {$managementSql})
                 ON DUPLICATE KEY UPDATE
                    status_id = VALUES(status_id),
                    switch_port_numbering_layout_id = COALESCE(VALUES(switch_port_numbering_layout_id), switch_port_numbering_layout_id),
                    management_id = COALESCE(VALUES(management_id), management_id)"
            );
        }
    }

    equipment_prune_idf_position_port_capacity($conn, $companyId, $positionId, $rj45Count, $sfpCount, $rj45PortTypeId, $equipmentId);

    $defaultColorId = 0;
    if (equipment_table_has_column($conn, 'switch_ports', 'equipment_id')) {
        $stmtGrayColor = mysqli_prepare(
            $conn,
            "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
        );
        if ($stmtGrayColor) {
            mysqli_stmt_bind_param($stmtGrayColor, 'i', $companyId);
            mysqli_stmt_execute($stmtGrayColor);
            $resGrayColor = mysqli_stmt_get_result($stmtGrayColor);
            $grayColorRow = $resGrayColor ? mysqli_fetch_assoc($resGrayColor) : null;
            mysqli_stmt_close($stmtGrayColor);
            $defaultColorId = (int)($grayColorRow['id'] ?? 0);
        }
        if ($defaultColorId <= 0) {
            $stmtAnyColor = mysqli_prepare(
                $conn,
                "SELECT id FROM cable_colors WHERE company_id = ? ORDER BY id ASC LIMIT 1"
            );
            if ($stmtAnyColor) {
                mysqli_stmt_bind_param($stmtAnyColor, 'i', $companyId);
                mysqli_stmt_execute($stmtAnyColor);
                $resAnyColor = mysqli_stmt_get_result($stmtAnyColor);
                $anyColorRow = $resAnyColor ? mysqli_fetch_assoc($resAnyColor) : null;
                mysqli_stmt_close($stmtAnyColor);
                $defaultColorId = (int)($anyColorRow['id'] ?? 0);
            }
        }

        $stmtEnsureSwitchRj45 = mysqli_prepare(
            $conn,
            "INSERT INTO switch_ports
                (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, idf_id, management_id, comments)
             SELECT ?, ?, NULLIF(?, ''), ?, ?, '', ?, ?, NULLIF(?, 0), NULLIF(?, 0), ''
             WHERE NOT EXISTS (
                SELECT 1
                FROM switch_ports
                WHERE company_id = ?
                  AND equipment_id = ?
                  AND port_number = ?
             )"
        );
        if ($stmtEnsureSwitchRj45 && $defaultColorId > 0) {
            for ($portNo = 1; $portNo <= $rj45Count; $portNo++) {
                mysqli_stmt_bind_param(
                    $stmtEnsureSwitchRj45,
                    'iissisiiiiii',
                    $companyId,
                    $equipmentId,
                    $equipmentHostname,
                    $rj45PortTypeName,
                    $portNo,
                    $unknownStatusId,
                    $defaultColorId,
                    $idfId,
                    $switchEnvironmentId,
                    $companyId,
                    $equipmentId,
                    $portNo
                );
                mysqli_stmt_execute($stmtEnsureSwitchRj45);
            }
            mysqli_stmt_close($stmtEnsureSwitchRj45);
        }

        if ($sfpCount > 0 && $fiberPortTypeId > 0) {
            $stmtEnsureSwitchSfp = mysqli_prepare(
            $conn,
            "INSERT INTO switch_ports
                (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, idf_id, management_id, comments)
             SELECT ?, ?, NULLIF(?, ''), ?, ?, '', ?, ?, NULLIF(?, 0), NULLIF(?, 0), ''
             WHERE NOT EXISTS (
                SELECT 1
                FROM switch_ports sp_check
                LEFT JOIN switch_port_types spt_check
                  ON spt_check.company_id = sp_check.company_id
                 AND (
                      spt_check.type = sp_check.port_type
                      OR spt_check.id = CAST(sp_check.port_type AS UNSIGNED)
                 )
                WHERE sp_check.company_id = ?
                  AND sp_check.equipment_id = ?
                  AND sp_check.port_number = ?
                  AND LOWER(TRIM(COALESCE(spt_check.type, sp_check.port_type, ''))) LIKE 'sfp%'
             )"
            );
            if ($stmtEnsureSwitchSfp && $defaultColorId > 0) {
                for ($fiberIdx = 1; $fiberIdx <= $sfpCount; $fiberIdx++) {
                    $fiberPortNo = idf_resolve_synthetic_fiber_port_no(0, $fiberIdx);
                    mysqli_stmt_bind_param(
                        $stmtEnsureSwitchSfp,
                        'iissisiiiiii',
                        $companyId,
                        $equipmentId,
                        $equipmentHostname,
                        $fiberPortTypeName,
                        $fiberPortNo,
                        $unknownStatusId,
                        $defaultColorId,
                        $idfId,
                        $switchEnvironmentId,
                        $companyId,
                        $equipmentId,
                        $fiberPortNo
                    );
                    mysqli_stmt_execute($stmtEnsureSwitchSfp);
                }
                mysqli_stmt_close($stmtEnsureSwitchSfp);
            }
        }
    }

    if ($equipmentHostname !== '') {
        idf_apply_equipment_hostname_to_position_ports($conn, $companyId, $positionId, $equipmentId, $equipmentHostname);
    }
}

function equipment_regenerate_synced_switch_and_idf_ports(mysqli $conn, int $companyId, int $equipmentId): string
{
    if ($companyId <= 0 || $equipmentId <= 0) {
        return '';
    }

    $hasSwitchFiberPortsNumberColumn = equipment_table_has_column($conn, 'equipment', 'switch_fiber_ports_number');
    $hasSwitchFiberPortLabelColumn = equipment_table_has_column($conn, 'equipment', 'switch_fiber_port_label');
    $hasSwitchLayoutColumn = equipment_table_has_column($conn, 'equipment', 'switch_port_numbering_layout_id');
    $hasSwitchEnvironmentColumn = equipment_table_has_column($conn, 'equipment', 'switch_environment_id');
    $hasEquipmentRj45SpeedColumn = equipment_table_has_column($conn, 'equipment', 'rj45_speed_id');
    $hasSwitchFiberIdColumn = equipment_table_has_column($conn, 'equipment', 'switch_fiber_id');
    $hasEquipmentFiberTable = equipment_table_exists($conn, 'equipment_fiber');

    $switchFiberPortsNumberSelect = $hasSwitchFiberPortsNumberColumn ? "COALESCE(e.switch_fiber_ports_number, '')" : "''";
    $switchFiberPortLabelSelect = $hasSwitchFiberPortLabelColumn ? "COALESCE(e.switch_fiber_port_label, '')" : "''";
    $equipmentLayoutSelect = $hasSwitchLayoutColumn ? "COALESCE(e.switch_port_numbering_layout_id, 0)" : "0";
    $switchEnvironmentSelect = $hasSwitchEnvironmentColumn ? "COALESCE(e.switch_environment_id, 0)" : "0";
    $equipmentRj45SpeedSelect = $hasEquipmentRj45SpeedColumn ? "COALESCE(e.rj45_speed_id, 0)" : "0";
    $switchFiberNameSelect = ($hasSwitchFiberIdColumn && $hasEquipmentFiberTable) ? "COALESCE(ef.name, '')" : "''";
    $switchFiberJoinSql = ($hasSwitchFiberIdColumn && $hasEquipmentFiberTable)
        ? "LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id"
        : "";

    $stmtMeta = mysqli_prepare(
        $conn,
        "SELECT
            p.id AS position_id,
            p.idf_id,
            p.rj45_count,
            p.sfp_count,
            COALESCE(p.switch_port_numbering_layout_id, 0) AS position_layout_id,
            COALESCE(e.hostname, '') AS equipment_hostname,
            {$switchFiberPortsNumberSelect} AS switch_fiber_ports_number,
            {$switchFiberPortLabelSelect} AS switch_fiber_port_label,
            {$switchFiberNameSelect} AS switch_fiber_name,
            {$equipmentLayoutSelect} AS equipment_layout_id,
            {$switchEnvironmentSelect} AS switch_environment_id,
            {$equipmentRj45SpeedSelect} AS rj45_speed_id
         FROM equipment e
         LEFT JOIN idf_positions p
           ON p.company_id = e.company_id
          AND p.equipment_id REGEXP '^[0-9]+$'
          AND CAST(p.equipment_id AS UNSIGNED) = e.id
         {$switchFiberJoinSql}
         WHERE e.company_id = ? AND e.id = ?
         LIMIT 1"
    );
    if (!$stmtMeta) {
        return 'A database error occurred. Please try again.';
    }

    mysqli_stmt_bind_param($stmtMeta, 'ii', $companyId, $equipmentId);
    mysqli_stmt_execute($stmtMeta);
    $metaRes = mysqli_stmt_get_result($stmtMeta);
    $meta = $metaRes ? mysqli_fetch_assoc($metaRes) : null;
    mysqli_stmt_close($stmtMeta);
    if (!$meta) {
        return '';
    }

    $positionId = (int)($meta['position_id'] ?? 0);
    if ($positionId <= 0) {
        return '';
    }

    $idfId = (int)($meta['idf_id'] ?? 0);
    $portCount = max(0, (int)($meta['rj45_count'] ?? $meta['port_count'] ?? 0));
    $fiberCount = max(0, (int)($meta['switch_fiber_ports_number'] ?? 0));
    $layoutId = (int)($meta['equipment_layout_id'] ?? 0);
    if ($layoutId <= 0) {
        $layoutId = (int)($meta['position_layout_id'] ?? 0);
    }
    $managementId = (int)($meta['switch_environment_id'] ?? 0);
    $rj45SpeedId = (int)($meta['rj45_speed_id'] ?? 0);
    $hostname = trim((string)($meta['equipment_hostname'] ?? ''));

    $unknownStatusId = 0;
    $stmtStatus = mysqli_prepare($conn, "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1");
    if ($stmtStatus) {
        mysqli_stmt_bind_param($stmtStatus, 'i', $companyId);
        mysqli_stmt_execute($stmtStatus);
        $resStatus = mysqli_stmt_get_result($stmtStatus);
        $statusRow = $resStatus ? mysqli_fetch_assoc($resStatus) : null;
        mysqli_stmt_close($stmtStatus);
        $unknownStatusId = (int)($statusRow['id'] ?? 0);
    }
    if ($unknownStatusId <= 0) {
        return 'Unable to resolve default status for port synchronization.';
    }

    $rj45PortTypeId = 0;
    $stmtRj45Type = mysqli_prepare($conn, "SELECT id FROM switch_port_types WHERE company_id = ? AND LOWER(type) = 'rj45' LIMIT 1");
    if ($stmtRj45Type) {
        mysqli_stmt_bind_param($stmtRj45Type, 'i', $companyId);
        mysqli_stmt_execute($stmtRj45Type);
        $resRj45Type = mysqli_stmt_get_result($stmtRj45Type);
        $rj45TypeRow = $resRj45Type ? mysqli_fetch_assoc($resRj45Type) : null;
        mysqli_stmt_close($stmtRj45Type);
        $rj45PortTypeId = (int)($rj45TypeRow['id'] ?? 0);
    }
    if ($portCount > 0 && $rj45PortTypeId <= 0) {
        return 'Unable to resolve RJ45 port type for synchronization.';
    }

    $fiberPortsNumberId = 0;
    $switchFiberPortsNumber = trim((string)($meta['switch_fiber_ports_number'] ?? ''));
    if ($switchFiberPortsNumber !== '') {
        $stmtFiberCount = mysqli_prepare(
            $conn,
            "SELECT id
             FROM equipment_fiber_count
             WHERE company_id = ? AND name = ?
             LIMIT 1"
        );
        if ($stmtFiberCount) {
            mysqli_stmt_bind_param($stmtFiberCount, 'is', $companyId, $switchFiberPortsNumber);
            mysqli_stmt_execute($stmtFiberCount);
            $resFiberCount = mysqli_stmt_get_result($stmtFiberCount);
            $fiberCountRow = $resFiberCount ? mysqli_fetch_assoc($resFiberCount) : null;
            mysqli_stmt_close($stmtFiberCount);
            $fiberPortsNumberId = (int)($fiberCountRow['id'] ?? 0);
        }
    }

    $fiberPortTypeId = 0;
    if ($fiberCount > 0) {
        $fiberHint = strtolower(trim((string)($meta['switch_fiber_port_label'] ?? '') . ' ' . (string)($meta['switch_fiber_name'] ?? '')));
        $fiberTypeName = (strpos($fiberHint, 'sfp+') !== false || strpos($fiberHint, 'sfp plus') !== false) ? 'sfp+' : 'sfp';
        $fiberTypeAlt = $fiberTypeName === 'sfp+' ? 'sfp plus' : 'sfp';
        $stmtFiberType = mysqli_prepare(
            $conn,
            "SELECT id
             FROM switch_port_types
             WHERE company_id = ?
               AND (LOWER(type) = ? OR LOWER(type) = ?)
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtFiberType) {
            mysqli_stmt_bind_param($stmtFiberType, 'iss', $companyId, $fiberTypeName, $fiberTypeAlt);
            mysqli_stmt_execute($stmtFiberType);
            $resFiberType = mysqli_stmt_get_result($stmtFiberType);
            $fiberTypeRow = $resFiberType ? mysqli_fetch_assoc($resFiberType) : null;
            mysqli_stmt_close($stmtFiberType);
            $fiberPortTypeId = (int)($fiberTypeRow['id'] ?? 0);
        }
        if ($fiberPortTypeId <= 0) {
            return 'Unable to resolve fiber port type for synchronization.';
        }
    }

    $portsToInsert = [];
    if ($portCount > 0) {
        for ($portNo = 1; $portNo <= $portCount; $portNo++) {
            $portsToInsert[] = ['port_no' => $portNo, 'port_type' => $rj45PortTypeId];
        }
    }
    if ($fiberCount > 0 && $fiberPortTypeId > 0) {
        for ($portNo = 1; $portNo <= $fiberCount; $portNo++) {
            $portsToInsert[] = ['port_no' => $portNo, 'port_type' => $fiberPortTypeId];
        }
    }

    mysqli_query(
        $conn,
        "DELETE FROM idf_links
         WHERE company_id = " . (int)$companyId . "
           AND (
                port_id_a IN (
                    SELECT id FROM idf_ports
                    WHERE company_id = " . (int)$companyId . " AND position_id = " . (int)$positionId . "
                )
                OR
                port_id_b IN (
                    SELECT id FROM idf_ports
                    WHERE company_id = " . (int)$companyId . " AND position_id = " . (int)$positionId . "
                )
           )"
    );

    mysqli_query($conn, "DELETE FROM idf_ports WHERE company_id = " . (int)$companyId . " AND position_id = " . (int)$positionId);

    if (!empty($portsToInsert)) {
        $stmtInsertIdfPort = mysqli_prepare(
            $conn,
            "INSERT INTO idf_ports
                (company_id, position_id, port_no, port_type, status_id, rj45_speed_id, fiber_ports_number, switch_port_numbering_layout_id, management_id)
             VALUES
                (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0))"
        );
        if ($stmtInsertIdfPort) {
            foreach ($portsToInsert as $portMeta) {
                $portNo = (int)$portMeta['port_no'];
                $portTypeId = (int)$portMeta['port_type'];
                $portRj45SpeedId = $portTypeId === $rj45PortTypeId ? $rj45SpeedId : 0;
                mysqli_stmt_bind_param(
                    $stmtInsertIdfPort,
                    'iiiiiiiii',
                    $companyId,
                    $positionId,
                    $portNo,
                    $portTypeId,
                    $unknownStatusId,
                    $portRj45SpeedId,
                    $fiberPortsNumberId,
                    $layoutId,
                    $managementId
                );
                mysqli_stmt_execute($stmtInsertIdfPort);
            }
            mysqli_stmt_close($stmtInsertIdfPort);
        }
    }

    mysqli_query($conn, "DELETE FROM switch_ports WHERE company_id = " . (int)$companyId . " AND equipment_id = " . (int)$equipmentId);

    if (!empty($portsToInsert)) {
        $portTypeNameById = [];
        $stmtPortTypes = mysqli_prepare($conn, "SELECT id, type FROM switch_port_types WHERE company_id = ?");
        if ($stmtPortTypes) {
            mysqli_stmt_bind_param($stmtPortTypes, 'i', $companyId);
            mysqli_stmt_execute($stmtPortTypes);
            $resPortTypes = mysqli_stmt_get_result($stmtPortTypes);
            while ($resPortTypes && ($portTypeRow = mysqli_fetch_assoc($resPortTypes))) {
                $portTypeId = (int)($portTypeRow['id'] ?? 0);
                $portTypeName = trim((string)($portTypeRow['type'] ?? ''));
                if ($portTypeId > 0 && $portTypeName !== '') {
                    $portTypeNameById[$portTypeId] = $portTypeName;
                }
            }
            mysqli_stmt_close($stmtPortTypes);
        }

        $defaultColorId = 0;
        $stmtGrayColor = mysqli_prepare(
            $conn,
            "SELECT id
             FROM cable_colors
             WHERE company_id = ?
               AND LOWER(color_name) = 'gray'
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtGrayColor) {
            mysqli_stmt_bind_param($stmtGrayColor, 'i', $companyId);
            mysqli_stmt_execute($stmtGrayColor);
            $resGrayColor = mysqli_stmt_get_result($stmtGrayColor);
            $grayColorRow = $resGrayColor ? mysqli_fetch_assoc($resGrayColor) : null;
            mysqli_stmt_close($stmtGrayColor);
            $defaultColorId = (int)($grayColorRow['id'] ?? 0);
        }
        if ($defaultColorId <= 0) {
            $stmtAnyColor = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM cable_colors
                 WHERE company_id = ?
                 ORDER BY id ASC
                 LIMIT 1"
            );
            if ($stmtAnyColor) {
                mysqli_stmt_bind_param($stmtAnyColor, 'i', $companyId);
                mysqli_stmt_execute($stmtAnyColor);
                $resAnyColor = mysqli_stmt_get_result($stmtAnyColor);
                $anyColorRow = $resAnyColor ? mysqli_fetch_assoc($resAnyColor) : null;
                mysqli_stmt_close($stmtAnyColor);
                $defaultColorId = (int)($anyColorRow['id'] ?? 0);
            }
        }
        if ($defaultColorId <= 0) {
            return 'Unable to resolve default cable color for synchronization.';
        }

        $stmtInsertSwitchPort = mysqli_prepare(
            $conn,
            "INSERT INTO switch_ports
                (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, rj45_speed_id, idf_id, management_id, comments)
             VALUES
                (?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?)"
        );
        if ($stmtInsertSwitchPort) {
            $defaultPatchPort = '';
            $defaultComments = '';
            foreach ($portsToInsert as $portMeta) {
                $portNo = (int)$portMeta['port_no'];
                $portTypeId = (int)$portMeta['port_type'];
                $portTypeName = trim((string)($portTypeNameById[$portTypeId] ?? ''));
                $portRj45SpeedId = $portTypeId === $rj45PortTypeId ? $rj45SpeedId : 0;
                if ($portTypeName === '') {
                    continue;
                }
                mysqli_stmt_bind_param(
                    $stmtInsertSwitchPort,
                    'iissisiiiiis',
                    $companyId,
                    $equipmentId,
                    $hostname,
                    $portTypeName,
                    $portNo,
                    $defaultPatchPort,
                    $unknownStatusId,
                    $defaultColorId,
                    $portRj45SpeedId,
                    $idfId,
                    $managementId,
                    $defaultComments
                );
                mysqli_stmt_execute($stmtInsertSwitchPort);
            }
            mysqli_stmt_close($stmtInsertSwitchPort);
        }
    }

    mysqli_query(
        $conn,
        'UPDATE idf_positions
         SET rj45_count = ' . (int)$portCount . ',
             sfp_count = ' . (int)$fiberCount . '
         WHERE company_id = ' . (int)$companyId . '
           AND id = ' . (int)$positionId . '
         LIMIT 1'
    );

    return '';
}

function equipment_validate_idf_assignment(mysqli $conn, int $companyId, int $equipmentId, int $idfId): string
{
    if ($companyId <= 0 || $idfId <= 0) {
        return '';
    }

    $stmtCurrent = mysqli_prepare(
        $conn,
        "SELECT id, idf_id
         FROM idf_positions
         WHERE company_id = ? AND equipment_id = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    $currentRow = null;
    if ($stmtCurrent) {
        $equipmentIdStr = (string)$equipmentId;
        mysqli_stmt_bind_param($stmtCurrent, 'is', $companyId, $equipmentIdStr);
        mysqli_stmt_execute($stmtCurrent);
        $resCurrent = mysqli_stmt_get_result($stmtCurrent);
        $currentRow = $resCurrent ? mysqli_fetch_assoc($resCurrent) : null;
        mysqli_stmt_close($stmtCurrent);
    }

    if ($currentRow && (int)($currentRow['idf_id'] ?? 0) === $idfId) {
        return '';
    }

    $availableSlot = equipment_find_available_idf_slot($conn, $companyId, $idfId);
    $availablePositionId = (int)($availableSlot['position_id'] ?? 0);
    $availablePositionNo = (int)($availableSlot['position_no'] ?? 0);

    if ($availablePositionId > 0 || $availablePositionNo > 0) {
        return '';
    }

    return 'Unable to assign an IDF position. Please verify IDF settings.';
}

function equipment_detect_upload_mime(array $file): string
{
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        return '';
    }

    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)@finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if ($mime !== '') {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = (string)@mime_content_type($tmpName);
        if ($mime !== '') {
            return strtolower($mime);
        }
    }

    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
        return strtolower((string)$imageInfo['mime']);
    }

    $extension = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $extensionMimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    return $extensionMimeMap[$extension] ?? '';
}

function equipment_parse_photo_filenames($rawValue): array
{
    if ($rawValue === null) {
        return [];
    }

    $value = trim((string)$rawValue);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $items = $decoded;
    } elseif (str_contains($value, ',')) {
        $items = explode(',', $value);
    } else {
        $items = [$value];
    }

    $filenames = [];
    foreach ($items as $item) {
        $filename = basename((string)$item);
        if ($filename !== '') {
            $filenames[$filename] = $filename;
        }
    }

    return array_values($filenames);
}

function equipment_encode_photo_filenames(array $filenames): string
{
    $clean = [];
    foreach ($filenames as $filename) {
        $base = basename((string)$filename);
        if ($base !== '') {
            $clean[$base] = $base;
        }
    }
    $clean = array_values($clean);

    if (count($clean) === 0) {
        return '';
    }
    if (count($clean) === 1) {
        return $clean[0];
    }

    return json_encode($clean, JSON_UNESCAPED_SLASHES);
}

$types = fetch_options($conn, 'equipment_types');
$manufacturers = fetch_options($conn, 'manufacturers');
$departments = equipment_fetch_department_select_options($conn, (int)$company_id);
$suppliers = equipment_fetch_supplier_select_options($conn, (int)$company_id);
$employees = equipment_fetch_employee_options($conn, (int)$company_id);
$supplierStatuses = fetch_options($conn, 'supplier_statuses');
$locations = equipment_fetch_location_select_options($conn, (int)$company_id);
$locationTypes = fetch_options($conn, 'location_types', 'name', "WHERE company_id = $company_id");
$racks = equipment_fetch_rack_select_options($conn, (int)$company_id);
$idfs = fetch_options($conn, 'idfs', 'name', "WHERE company_id = $company_id");
$rackStatuses = fetch_options($conn, 'rack_statuses');
$statuses = fetch_options($conn, 'equipment_statuses');
$defaultStatusId = '';
foreach ($statuses as $statusItem) {
    if (strcasecmp((string)$statusItem['label'], 'Active') === 0) {
        $defaultStatusId = (string)$statusItem['id'];
        break;
    }
}
if ($defaultStatusId === '' && !empty($statuses)) {
    $defaultStatusId = (string)$statuses[0]['id'];
}
if ($defaultStatusId === '') {
    $defaultStatusId = '1';
}
$warrantyTypes = fetch_options($conn, 'warranty_types');
$printerTypes = fetch_options($conn, 'printer_device_types');
$workstationDeviceTypes = fetch_options($conn, 'workstation_device_types');
$workstationOsTypes = fetch_options($conn, 'workstation_os_types');
$workstationOsVersions = fetch_options($conn, 'workstation_os_versions');
$workstationRamOptions = fetch_options($conn, 'workstation_ram');
$workstationOfficeOptions = fetch_options($conn, 'workstation_office');
$rj45CableOptions = fetch_options($conn, 'rj45_speed', 'cable_type');
$switchRj45Options = fetch_options($conn, 'equipment_rj45');
$switchFiberOptions = fetch_options($conn, 'equipment_fiber');
$switchFiberPatchOptions = fetch_options($conn, 'equipment_fiber_patch');
$switchFiberRackOptions = fetch_options($conn, 'equipment_fiber_rack');
$switchPoeOptions = itm_equipment_poe_options_rows($conn, (int)$company_id);
$switchEnvironmentOptions = fetch_options($conn, 'equipment_environment');
$switchPortNumberingLayoutOptions = fetch_options($conn, 'switch_port_numbering_layout');
$hasWorkstationOfficeIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_office_id');
$hasEquipmentRj45SpeedColumn = equipment_table_has_column($conn, 'equipment', 'rj45_speed_id');
$hasWorkstationOsVersionIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_os_version_id');
$hasWorkstationRamIdColumn = equipment_table_has_column($conn, 'equipment', 'workstation_ram_id');
$hasWorkstationStorageColumn = equipment_table_has_column($conn, 'equipment', 'workstation_storage');
$hasWorkstationOsInstalledOnColumn = equipment_table_has_column($conn, 'equipment', 'workstation_os_installed_on');
$hasSwitchFiberPortLabelColumn = equipment_table_has_column($conn, 'equipment', 'switch_fiber_port_label');

$switchTypeId = 0;
$serverTypeId = 0;
$printerTypeId = 0;
foreach ($types as $typeItem) {
    if (strcasecmp((string)$typeItem['label'], 'Switch') === 0) {
        $switchTypeId = (int)$typeItem['id'];
    }
    if (strcasecmp((string)$typeItem['label'], 'Server') === 0) {
        $serverTypeId = (int)$typeItem['id'];
    }
    if (strcasecmp((string)$typeItem['label'], 'Printer') === 0) {
        $printerTypeId = (int)$typeItem['id'];
    }
}

$data = [
    'equipment_type_id' => '', 'manufacturer_id' => '', 'location_id' => '', 'rack_id' => '', 'idf_id' => '', 'name' => '',
    'serial_number' => '', 'model' => '', 'hostname' => '', 'ip_address' => '', 'patch_port' => '', 'mac_address' => '', 'department_id' => '', 'supplier_id' => '', 'assigned_to_employee_id' => '', 'equipment_id' => '', 'assigned_date' => '',
    'status_id' => $defaultStatusId, 'purchase_date' => '', 'purchase_cost' => '', 'warranty_expiry' => '', 'certificate_expiry' => '', 'warranty_type_id' => '',
    'printer_device_type_id' => '', 'printer_color_capable' => 0, 'printer_scan' => 0,
    'workstation_device_type_id' => '', 'workstation_os_type_id' => '',
    'workstation_office_id' => '', 'rj45_speed_id' => '', 'workstation_os_version_id' => '', 'workstation_ram_id' => '',
    'workstation_processor' => '', 'workstation_storage' => '', 'workstation_os_installed_on' => '',
    'switch_rj45_id' => '', 'switch_port_numbering_layout_id' => '1', 'switch_fiber_id' => '', 'switch_fiber_patch_id' => '', 'switch_fiber_rack_id' => '', 'switch_fiber_ports_number' => '', 'switch_fiber_port_label' => '', 'switch_poe_id' => '', 'switch_environment_id' => '',
    'notes' => '', 'photo_filename' => '', 'active' => 1
];

if ($isEdit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) === 1) {
            $data = array_merge($data, mysqli_fetch_assoc($res));
            if (empty($data['switch_port_numbering_layout_id'])) {
                $data['switch_port_numbering_layout_id'] = '1';
            }
            $originalData = $data;
        } else {
            $error = 'Equipment record not found.';
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    foreach ($data as $k => $v) {
        if (in_array($k, ['printer_color_capable', 'printer_scan'], true)) {
            $data[$k] = isset($_POST[$k]) ? 1 : 0;
        } elseif ($k === 'photo_filename') {
            $data[$k] = $isEdit ? (string)($originalData['photo_filename'] ?? $v) : '';
        } elseif ($k === 'active') {
            $postedActive = $_POST['active'] ?? $data['active'];
            $data[$k] = (int)$postedActive === 1 ? 1 : 0;
        } elseif ($k === 'assigned_date') {
            $data[$k] = trim($_POST['assigned_date'] ?? '');
        } elseif ($k === 'equipment_id') {
            $data[$k] = trim($_POST['equipment_id'] ?? '');
        } else {
            $data[$k] = trim($_POST[$k] ?? '');
        }
    }

    foreach (['equipment_type_id','manufacturer_id','location_id','rack_id','idf_id','department_id','supplier_id','assigned_to_employee_id','status_id','warranty_type_id','printer_device_type_id','workstation_device_type_id','workstation_os_type_id','workstation_office_id','rj45_speed_id','workstation_os_version_id','workstation_ram_id','switch_rj45_id','switch_port_numbering_layout_id','switch_fiber_id','switch_fiber_patch_id','switch_fiber_rack_id','switch_poe_id','switch_environment_id'] as $fkField) {
        if (($data[$fkField] ?? '') === '__add_new__') {
            $data[$fkField] = '';
        }
    }
    $data['switch_fiber_ports_number'] = isset($_POST['switch_fiber_ports_number']) ? substr(trim((string)$_POST['switch_fiber_ports_number']), 0, 50) : '';
    if ($data['switch_fiber_ports_number'] === '__add_new__') {
        $data['switch_fiber_ports_number'] = '';
    }
    $data['switch_fiber_port_label'] = isset($_POST['switch_fiber_port_label']) ? substr(trim((string)$_POST['switch_fiber_port_label']), 0, 100) : '';

    if ((int)$data['status_id'] <= 0) {
        $data['status_id'] = $defaultStatusId;
    }

    $isSwitchEquipment = $switchTypeId > 0 && (int)$data['equipment_type_id'] === $switchTypeId;
    $isServerEquipment = $serverTypeId > 0 && (int)$data['equipment_type_id'] === $serverTypeId;
    $requestedIdfId = (int)($data['idf_id'] ?? 0);

    if ($data['name'] === '' || (int)$data['equipment_type_id'] <= 0) {
        $error = 'Please fill required fields: Name, Type.';
    } elseif (equipment_name_exists($conn, (int)$company_id, $data['name'], $isEdit ? (int)$id : 0)) {
        $error = 'Equipment name already exists for this company.';
    } elseif (equipment_optional_unique_field_exists($conn, (int)$company_id, 'serial_number', (string)$data['serial_number'], $isEdit ? (int)$id : 0)) {
        $error = 'Serial Number already exists for this company.';
    } elseif (equipment_optional_unique_field_exists($conn, (int)$company_id, 'hostname', (string)$data['hostname'], $isEdit ? (int)$id : 0)) {
        $error = 'Hostname already exists for this company.';
    } elseif (equipment_optional_unique_field_exists($conn, (int)$company_id, 'ip_address', (string)$data['ip_address'], $isEdit ? (int)$id : 0)) {
        $error = 'IP Address already exists for this company.';
    } elseif ($isSwitchEquipment && (int)$data['switch_rj45_id'] <= 0) {
        $error = 'Please fill required field: RJ45 Ports for switch equipment.';
    } elseif ($requestedIdfId > 0) {
        $assignmentCheckEquipmentId = $isEdit ? (int)$id : 0;
        $idfAssignmentError = equipment_validate_idf_assignment($conn, (int)$company_id, $assignmentCheckEquipmentId, $requestedIdfId);
        if ($idfAssignmentError !== '') {
            $error = $idfAssignmentError;
        }
    }

    if (!$error && $isEdit && (int)($data['equipment_id'] ?? 0) > 0 && (int)$data['equipment_id'] !== (int)$id) {
        $error = 'Equipment ID mismatch. Please reload the form and try again.';
    }

    if (!$error && (int)($data['assigned_to_employee_id'] ?? 0) > 0) {
        $requestedEmployeeId = (int)$data['assigned_to_employee_id'];
        $employeeCheckStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
        );
        $employeeCheckOk = false;
        if ($employeeCheckStmt) {
            mysqli_stmt_bind_param($employeeCheckStmt, 'ii', $requestedEmployeeId, $company_id);
            mysqli_stmt_execute($employeeCheckStmt);
            $employeeCheckRes = mysqli_stmt_get_result($employeeCheckStmt);
            $employeeCheckOk = $employeeCheckRes && mysqli_num_rows($employeeCheckRes) === 1;
            mysqli_stmt_close($employeeCheckStmt);
        }
        if (!$employeeCheckOk) {
            $error = 'Selected employee is invalid for this company.';
        }
    }

    if (!$error && $data['mac_address'] !== '') {
        $macColumnLength = equipment_table_varchar_length($conn, 'equipment', 'mac_address');
        if ($macColumnLength > 0 && strlen($data['mac_address']) > $macColumnLength) {
            $error = 'MAC Address is too long. Maximum allowed is ' . $macColumnLength . ' characters.';
        }
    }

    $photoFilenames = equipment_parse_photo_filenames($data['photo_filename']);
    $photoFilenamesToDeleteAfterSave = [];
    $deleteCurrentPhoto = isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1';
    $deletePhotoIndexesRaw = trim((string)($_POST['delete_photo_indexes'] ?? ''));
    $deletePhotoIndexes = [];
    if ($deletePhotoIndexesRaw !== '') {
        $deletePhotoIndexes = array_values(array_unique(array_filter(array_map(static function ($indexValue) {
            if (!is_numeric($indexValue)) {
                return null;
            }
            $index = (int)$indexValue;
            return $index >= 0 ? $index : null;
        }, explode(',', $deletePhotoIndexesRaw)), static function ($value) {
            return $value !== null;
        })));
    }
    if (!$error && $isEdit && $deleteCurrentPhoto && !empty($photoFilenames)) {
        $photoFilenamesToDeleteAfterSave = array_values(array_unique(array_merge(
            $photoFilenamesToDeleteAfterSave,
            $photoFilenames
        )));
        $photoFilenames = [];
    } elseif (!$error && $isEdit && !empty($deletePhotoIndexes) && !empty($photoFilenames)) {
        foreach ($deletePhotoIndexes as $deletePhotoIndex) {
            if (!array_key_exists($deletePhotoIndex, $photoFilenames)) {
                continue;
            }
            $photoFilenamesToDeleteAfterSave[] = (string)$photoFilenames[$deletePhotoIndex];
            unset($photoFilenames[$deletePhotoIndex]);
        }
        $photoFilenames = array_values($photoFilenames);
        $photoFilenamesToDeleteAfterSave = array_values(array_unique($photoFilenamesToDeleteAfterSave));
    }

    if (
        !$error
        && isset($_FILES['photo'])
        && is_array($_FILES['photo']['error'] ?? null)
    ) {
        $uploadedPhotoFilenames = [];
        $fileCount = count($_FILES['photo']['error']);

        for ($index = 0; $index < $fileCount; $index++) {
            $fileError = (int)($_FILES['photo']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($fileError !== UPLOAD_ERR_OK) {
                $error = 'One of the photo uploads failed.';
                break;
            }

            $fileSize = (int)($_FILES['photo']['size'][$index] ?? 0);
            if ($fileSize > MAX_FILE_SIZE) {
                $error = 'One of the photos exceeds max allowed size.';
                break;
            }

            $currentFile = [
                'tmp_name' => $_FILES['photo']['tmp_name'][$index] ?? '',
                'name' => $_FILES['photo']['name'][$index] ?? '',
            ];
            $mime = equipment_detect_upload_mime($currentFile);
            if (!in_array($mime, ALLOWED_TYPES, true)) {
                $error = 'One of the uploaded files has an unsupported image type.';
                break;
            }

            itm_ensure_upload_directory(UPLOAD_PATH, 'upload');

            $ext = pathinfo((string)$currentFile['name'], PATHINFO_EXTENSION);
            $photoFilename = 'equipment_' . time() . '_' . mt_rand(1000, 9999) . '_' . $index . '.' . strtolower((string)$ext);
            if (!move_uploaded_file((string)$currentFile['tmp_name'], UPLOAD_PATH . $photoFilename)) {
                $error = 'Unable to save one of the uploaded photos.';
                break;
            }
            $uploadedPhotoFilenames[] = $photoFilename;
        }

        if ($error !== '' && !empty($uploadedPhotoFilenames)) {
            foreach ($uploadedPhotoFilenames as $uploadedPhotoFilename) {
                $uploadedPath = UPLOAD_PATH . $uploadedPhotoFilename;
                if (is_file($uploadedPath)) {
                    @unlink($uploadedPath);
                }
            }
        } elseif (!$error && !empty($uploadedPhotoFilenames)) {
            $photoFilenames = array_values(array_unique(array_merge($photoFilenames, $uploadedPhotoFilenames)));
        }
    }

    if (!$error) {
        $equipment_type_id = (int)$data['equipment_type_id'];
        $manufacturer_id = (int)$data['manufacturer_id'] ?: 'NULL';
        $location_id = (int)$data['location_id'] ?: 'NULL';
        $rack_id = (int)$data['rack_id'] ?: 'NULL';
        $idf_id = (int)$data['idf_id'] ?: 'NULL';
        $department_id = (int)$data['department_id'] ?: 'NULL';
        $supplier_id = (int)$data['supplier_id'] ?: 'NULL';
        $name = "'" . escape_sql($data['name'], $conn) . "'";
        $serial_number = $data['serial_number'] === '' ? 'NULL' : "'" . escape_sql($data['serial_number'], $conn) . "'";
        $model = $data['model'] === '' ? 'NULL' : "'" . escape_sql($data['model'], $conn) . "'";
        $hostname = $data['hostname'] === '' ? 'NULL' : "'" . escape_sql($data['hostname'], $conn) . "'";
        $ip_address = $data['ip_address'] === '' ? 'NULL' : "'" . escape_sql($data['ip_address'], $conn) . "'";
        $patch_port = $data['patch_port'] === '' ? 'NULL' : "'" . escape_sql($data['patch_port'], $conn) . "'";
        $mac_address = $data['mac_address'] === '' ? 'NULL' : "'" . escape_sql($data['mac_address'], $conn) . "'";
        $status_id = (int)$data['status_id'] ?: 'NULL';
        $purchase_date = $data['purchase_date'] === '' ? 'NULL' : "'" . escape_sql($data['purchase_date'], $conn) . "'";
        $purchase_cost = $data['purchase_cost'] === '' ? 'NULL' : (float)$data['purchase_cost'];
        $warranty_expiry = $data['warranty_expiry'] === '' ? 'NULL' : "'" . escape_sql($data['warranty_expiry'], $conn) . "'";
        $certificate_expiry = ($isServerEquipment && $data['certificate_expiry'] !== '')
            ? "'" . escape_sql($data['certificate_expiry'], $conn) . "'"
            : 'NULL';
        $warranty_type_id = (int)$data['warranty_type_id'] ?: 'NULL';
        $printer_device_type_id = (int)$data['printer_device_type_id'] ?: 'NULL';
        $printer_color_capable = (int)$data['printer_color_capable'];
        $printer_scan = (int)$data['printer_scan'];
        $workstation_device_type_id = (int)$data['workstation_device_type_id'] ?: 'NULL';
        $workstation_os_type_id = (int)$data['workstation_os_type_id'] ?: 'NULL';
        $workstation_office_id = (int)$data['workstation_office_id'] ?: 'NULL';
        $rj45_speed_id = (int)$data['rj45_speed_id'] ?: 'NULL';
        $workstation_os_version_id = (int)$data['workstation_os_version_id'] ?: 'NULL';
        $workstation_ram_id = (int)$data['workstation_ram_id'] ?: 'NULL';
        $workstation_processor = $data['workstation_processor'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_processor'], $conn) . "'";
        $workstation_storage = $data['workstation_storage'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_storage'], $conn) . "'";
        $workstation_os_installed_on = $data['workstation_os_installed_on'] === '' ? 'NULL' : "'" . escape_sql($data['workstation_os_installed_on'], $conn) . "'";
        $switch_rj45_id = (int)$data['switch_rj45_id'] ?: 'NULL';
        $switch_port_numbering_layout_id = (int)$data['switch_port_numbering_layout_id'] ?: '1';
        $switch_fiber_id = (int)$data['switch_fiber_id'] ?: 'NULL';
        $switch_fiber_patch_id = (int)$data['switch_fiber_patch_id'] ?: 'NULL';
        $switch_fiber_rack_id = (int)$data['switch_fiber_rack_id'] ?: 'NULL';
        $switch_fiber_ports_number = $data['switch_fiber_ports_number'] === ''
            ? 'NULL'
            : "'" . escape_sql($data['switch_fiber_ports_number'], $conn) . "'";
        $switch_fiber_port_label = $data['switch_fiber_port_label'] === ''
            ? 'NULL'
            : "'" . escape_sql($data['switch_fiber_port_label'], $conn) . "'";
        $switch_fiber_ports_number_fk_sql = 'NULL';
        if ($data['switch_fiber_ports_number'] !== '' && equipment_table_exists($conn, 'equipment_fiber_count')) {
            $stmtFiberCountFk = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM equipment_fiber_count
                 WHERE company_id = ? AND name = ?
                 ORDER BY id ASC
                 LIMIT 1"
            );
            if ($stmtFiberCountFk) {
                $switchFiberCountName = (string)$data['switch_fiber_ports_number'];
                mysqli_stmt_bind_param($stmtFiberCountFk, 'is', $company_id, $switchFiberCountName);
                mysqli_stmt_execute($stmtFiberCountFk);
                $resFiberCountFk = mysqli_stmt_get_result($stmtFiberCountFk);
                $fiberCountFkRow = $resFiberCountFk ? mysqli_fetch_assoc($resFiberCountFk) : null;
                mysqli_stmt_close($stmtFiberCountFk);
                $resolvedFiberCountId = (int)($fiberCountFkRow['id'] ?? 0);
                if ($resolvedFiberCountId > 0) {
                    $switch_fiber_ports_number_fk_sql = (string)$resolvedFiberCountId;
                }
            }
        }
        $switch_poe_id = (int)$data['switch_poe_id'] ?: 'NULL';
        $switch_environment_id = (int)$data['switch_environment_id'] ?: 'NULL';
        $notes = $data['notes'] === '' ? 'NULL' : "'" . escape_sql($data['notes'], $conn) . "'";
        $encodedPhotoFilenames = equipment_encode_photo_filenames($photoFilenames);
        $photo = $encodedPhotoFilenames === '' ? 'NULL' : "'" . escape_sql($encodedPhotoFilenames, $conn) . "'";
        $active = (int)$data['active'];

        $workstationOfficeUpdateSql = $hasWorkstationOfficeIdColumn ? "workstation_office_id=$workstation_office_id,\n                    " : '';
        $workstationOfficeInsertColumns = $hasWorkstationOfficeIdColumn ? ', workstation_office_id' : '';
        $workstationOfficeInsertValues = $hasWorkstationOfficeIdColumn ? ", $workstation_office_id" : '';
        $rj45SpeedUpdateSql = $hasEquipmentRj45SpeedColumn ? "rj45_speed_id=$rj45_speed_id,\n                    " : '';
        $rj45SpeedInsertColumns = $hasEquipmentRj45SpeedColumn ? ', rj45_speed_id' : '';
        $rj45SpeedInsertValues = $hasEquipmentRj45SpeedColumn ? ", $rj45_speed_id" : '';
        $workstationOsVersionUpdateSql = $hasWorkstationOsVersionIdColumn ? "workstation_os_version_id=$workstation_os_version_id,\n                    " : '';
        $workstationOsVersionInsertColumns = $hasWorkstationOsVersionIdColumn ? ', workstation_os_version_id' : '';
        $workstationOsVersionInsertValues = $hasWorkstationOsVersionIdColumn ? ", $workstation_os_version_id" : '';
        $workstationRamUpdateSql = $hasWorkstationRamIdColumn ? "workstation_ram_id=$workstation_ram_id,\n                    " : '';
        $workstationRamInsertColumns = $hasWorkstationRamIdColumn ? ', workstation_ram_id' : '';
        $workstationRamInsertValues = $hasWorkstationRamIdColumn ? ", $workstation_ram_id" : '';
        $workstationStorageUpdateSql = $hasWorkstationStorageColumn ? "workstation_storage=$workstation_storage,\n                    " : '';
        $workstationStorageInsertColumns = $hasWorkstationStorageColumn ? ', workstation_storage' : '';
        $workstationStorageInsertValues = $hasWorkstationStorageColumn ? ", $workstation_storage" : '';
        $workstationOsInstalledOnUpdateSql = $hasWorkstationOsInstalledOnColumn ? "workstation_os_installed_on=$workstation_os_installed_on,\n                    " : '';
        $workstationOsInstalledOnInsertColumns = $hasWorkstationOsInstalledOnColumn ? ', workstation_os_installed_on' : '';
        $workstationOsInstalledOnInsertValues = $hasWorkstationOsInstalledOnColumn ? ", $workstation_os_installed_on" : '';
        $switchFiberPortLabelUpdateSql = $hasSwitchFiberPortLabelColumn ? "switch_fiber_port_label=$switch_fiber_port_label, " : '';
        $switchFiberPortLabelInsertColumns = $hasSwitchFiberPortLabelColumn ? ', switch_fiber_port_label' : '';
        $switchFiberPortLabelInsertValues = $hasSwitchFiberPortLabelColumn ? ", $switch_fiber_port_label" : '';

        if ($isEdit) {
            $sql = "UPDATE equipment SET equipment_type_id=$equipment_type_id, manufacturer_id=$manufacturer_id, location_id=$location_id, rack_id=$rack_id, idf_id=$idf_id, department_id=$department_id, supplier_id=$supplier_id,
                    name=$name, serial_number=$serial_number, model=$model, hostname=$hostname, ip_address=$ip_address, patch_port=$patch_port, mac_address=$mac_address,
                    status_id=$status_id, purchase_date=$purchase_date, purchase_cost=$purchase_cost, warranty_expiry=$warranty_expiry, certificate_expiry=$certificate_expiry,
                    warranty_type_id=$warranty_type_id, printer_device_type_id=$printer_device_type_id,
                    printer_color_capable=$printer_color_capable, printer_scan=$printer_scan,
                    workstation_device_type_id=$workstation_device_type_id, workstation_os_type_id=$workstation_os_type_id,
                    $workstationOfficeUpdateSql$rj45SpeedUpdateSql$workstationOsVersionUpdateSql$workstationRamUpdateSql
                    workstation_processor=$workstation_processor, $workstationStorageUpdateSql$workstationOsInstalledOnUpdateSql
                    switch_rj45_id=$switch_rj45_id, switch_port_numbering_layout_id=$switch_port_numbering_layout_id, switch_fiber_id=$switch_fiber_id, switch_fiber_patch_id=$switch_fiber_patch_id, switch_fiber_rack_id=$switch_fiber_rack_id, switch_fiber_ports_number=$switch_fiber_ports_number, $switchFiberPortLabelUpdateSql
                    switch_poe_id=$switch_poe_id, switch_environment_id=$switch_environment_id,
                    notes=$notes,
                    photo_filename=$photo, active=$active
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO equipment (company_id, equipment_type_id, manufacturer_id, location_id, rack_id, idf_id, department_id, supplier_id, name, serial_number, model, hostname,
                    ip_address, patch_port, mac_address, status_id, purchase_date, purchase_cost, warranty_expiry, certificate_expiry, warranty_type_id,
                    printer_device_type_id, printer_color_capable, printer_scan, workstation_device_type_id,
                    workstation_os_type_id$workstationOfficeInsertColumns$rj45SpeedInsertColumns$workstationOsVersionInsertColumns$workstationRamInsertColumns, workstation_processor$workstationStorageInsertColumns$workstationOsInstalledOnInsertColumns, switch_rj45_id, switch_port_numbering_layout_id, switch_fiber_id, switch_fiber_patch_id, switch_fiber_rack_id, switch_fiber_ports_number$switchFiberPortLabelInsertColumns, switch_poe_id, switch_environment_id, notes, photo_filename, active)
                    VALUES ($company_id, $equipment_type_id, $manufacturer_id, $location_id, $rack_id, $idf_id, $department_id, $supplier_id, $name, $serial_number, $model, $hostname,
                    $ip_address, $patch_port, $mac_address, $status_id, $purchase_date, $purchase_cost, $warranty_expiry, $certificate_expiry, $warranty_type_id,
                    $printer_device_type_id, $printer_color_capable, $printer_scan, $workstation_device_type_id,
                    $workstation_os_type_id$workstationOfficeInsertValues$rj45SpeedInsertValues$workstationOsVersionInsertValues$workstationRamInsertValues, $workstation_processor$workstationStorageInsertValues$workstationOsInstalledOnInsertValues, $switch_rj45_id, $switch_port_numbering_layout_id, $switch_fiber_id, $switch_fiber_patch_id, $switch_fiber_rack_id, $switch_fiber_ports_number$switchFiberPortLabelInsertValues, $switch_poe_id, $switch_environment_id, $notes, $photo, $active)";
        }

        mysqli_begin_transaction($conn);
        if (mysqli_query($conn, $sql)) {
            if (!$isEdit) {
                $id = (int)mysqli_insert_id($conn);
            }
            $idfSyncError = equipment_sync_idf_position_and_ports($conn, (int)$company_id, [
                'id' => $id,
                'idf_id' => (int)$data['idf_id'],
                'name' => (string)$data['name'],
                'hostname' => (string)$data['hostname'],
                'equipment_type_id' => (int)$data['equipment_type_id'],
                'switch_rj45_id' => (int)$data['switch_rj45_id'],
                'switch_port_numbering_layout_id' => (int)$data['switch_port_numbering_layout_id'],
                'switch_environment_id' => (int)$data['switch_environment_id'],
                'notes' => (string)$data['notes'],
                'switch_fiber_ports_number' => (string)$data['switch_fiber_ports_number'],
            ]);
            if ($idfSyncError !== '') {
                $error = $idfSyncError;
            }
            if ($error === '' && $equipment_type_id === $switchTypeId) {
                if (equipment_table_has_column($conn, 'switch_ports', 'management_id')) {
                    $switchPortsSyncSql = "UPDATE switch_ports
                                           SET hostname = $hostname,
                                               idf_id = $idf_id,
                                               rack_id = $rack_id,
                                               location_id = $location_id,
                                               fiber_port_id = $switch_fiber_id,
                                               fiber_patch_id = $switch_fiber_patch_id,
                                               fiber_rack_id = $switch_fiber_rack_id,
                                               management_id = $switch_environment_id
                                            WHERE company_id = $company_id
                                              AND equipment_id = $id";
                    if (equipment_table_has_column($conn, 'switch_ports', 'rj45_speed_id')) {
                        $switchPortsSyncSql = "UPDATE switch_ports
                                               SET hostname = $hostname,
                                                   idf_id = $idf_id,
                                                   rack_id = $rack_id,
                                                   location_id = $location_id,
                                                   fiber_port_id = $switch_fiber_id,
                                                   fiber_patch_id = $switch_fiber_patch_id,
                                                   fiber_rack_id = $switch_fiber_rack_id,
                                                   rj45_speed_id = $rj45_speed_id,
                                                   management_id = $switch_environment_id
                                                WHERE company_id = $company_id
                                                  AND equipment_id = $id";
                    }
                    if (!mysqli_query($conn, $switchPortsSyncSql)) {
                        $error = itm_format_db_constraint_error((int)mysqli_errno($conn), (string)mysqli_error($conn));
                    }
                }
                if ($error === '' && equipment_table_has_column($conn, 'idf_ports', 'management_id')) {
                    $idfPortsSyncSql = "UPDATE idf_ports ip
                                        JOIN idf_positions p ON p.id = ip.position_id
                                        SET ip.speed_id = $switch_fiber_id,
                                            ip.rj45_speed_id = $rj45_speed_id,
                                            ip.fiber_ports_number = $switch_fiber_ports_number_fk_sql,
                                            ip.switch_port_numbering_layout_id = $switch_port_numbering_layout_id,
                                            ip.management_id = $switch_environment_id,
                                            ip.poe_id = $switch_poe_id
                                        WHERE p.company_id = $company_id
                                          AND p.equipment_id = '$id'";
                    if (!mysqli_query($conn, $idfPortsSyncSql)) {
                        $error = itm_format_db_constraint_error((int)mysqli_errno($conn), (string)mysqli_error($conn));
                    }
                }
            }
            if ($error === '') {
                foreach ($photoFilenamesToDeleteAfterSave as $deletedFilename) {
                    $existingPhotoPath = UPLOAD_PATH . $deletedFilename;
                    if (is_file($existingPhotoPath)) {
                        @unlink($existingPhotoPath);
                    }
                }
            }
            if ($error === '' && $isEdit && $originalData) {
                $changedAwayFromSwitch = (int)$originalData['equipment_type_id'] === $switchTypeId && $equipment_type_id !== $switchTypeId;

                if ($changedAwayFromSwitch) {
                    $hasEquipmentId = equipment_table_has_column($conn, 'switch_ports', 'equipment_id');
                    if ($hasEquipmentId) {
                        mysqli_query(
                            $conn,
                            "DELETE FROM switch_ports WHERE company_id = $company_id AND equipment_id = $id"
                        );
                    } else {
                        mysqli_query(
                            $conn,
                            "DELETE FROM switch_ports WHERE company_id = $company_id"
                        );
                    }
                    equipment_delete_idf_data($conn, (int)$company_id, $id);
                }
            }

            if ($error === '') {
                $newAssigneeId = (int)($data['assigned_to_employee_id'] ?? 0);
                $newAssigneeId = $newAssigneeId > 0 ? $newAssigneeId : null;
                $oldAssigneeId = null;
                if ($isEdit && is_array($originalData)) {
                    $oldAssigneeId = (int)($originalData['assigned_to_employee_id'] ?? 0);
                    $oldAssigneeId = $oldAssigneeId > 0 ? $oldAssigneeId : null;
                }
                $assignedByUserId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : null;
                $assignmentAssignedDate = equipment_fetch_assignment_assigned_date($conn, (int)$company_id, $id);
                $assignmentAssetDescription = equipment_build_assignment_asset_description(
                    (string)$data['name'],
                    (string)$data['model']
                );
                $assignmentSyncError = equipment_sync_assigned_employee(
                    $conn,
                    (int)$company_id,
                    $id,
                    $newAssigneeId,
                    $oldAssigneeId,
                    $assignedByUserId,
                    $assignmentAssignedDate,
                    $assignmentAssetDescription
                );
                if ($assignmentSyncError !== null) {
                    $error = $assignmentSyncError;
                }
            }

            if ($error === '') {
                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
            }

            if ($error === '' && $isEdit) {
                if ($equipment_type_id === $switchTypeId) {
                    header('Location: index.php?switch_id=' . $id . '&saved=1&spm=1#switch-port-manager');
                    exit;
                }
                header('Location: edit.php?id=' . $id . '&saved=1');
                exit;
            }
            if ($error === '') {
                header('Location: index.php');
                exit;
            }
        } else {
            mysqli_rollback($conn);
            $dbErrorCode = (int)mysqli_errno($conn);
            $dbErrorMessage = (string)mysqli_error($conn);
            $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    }
}

function render_options($items, $selected = '') {
    foreach ($items as $i) {
        $sel = ((string)$selected === (string)$i['id']) ? 'selected' : '';
        echo '<option value="' . (int)$i['id'] . '" ' . $sel . '>' . sanitize($i['label']) . '</option>';
    }
}

$locationExtraFieldsConfig = [
    [
        'name' => 'type_id',
        'label' => 'Location Type',
        'type' => 'select',
        'options' => array_map(static function ($type) {
            return [
                'value' => (string)((int)($type['id'] ?? 0)),
                'label' => (string)($type['label'] ?? ''),
            ];
        }, $locationTypes),
    ],
];
$locationExtraFieldsJson = htmlspecialchars(
    json_encode($locationExtraFieldsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$rackExtraFieldsConfig = [
    [
        'name' => 'location_id',
        'label' => 'Location',
        'type' => 'select',
        'options' => array_map(static function ($location) {
            return [
                'value' => (string)((int)($location['id'] ?? 0)),
                'label' => (string)($location['label'] ?? ''),
            ];
        }, $locations),
    ],
    [
        'name' => 'status_id',
        'label' => 'Rack Status',
        'type' => 'select',
        'options' => array_map(static function ($status) {
            return [
                'value' => (string)((int)($status['id'] ?? 0)),
                'label' => (string)($status['label'] ?? ''),
            ];
        }, $rackStatuses),
    ],
];
$rackExtraFieldsJson = htmlspecialchars(
    json_encode($rackExtraFieldsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
// Why: Quick-add only requires name (company_id/active auto-set); code is optional for list display.
$departmentExtraFieldsConfig = [
    [
        'name' => 'code',
        'label' => 'Code',
        'type' => 'text',
        'required' => false,
    ],
];
$departmentExtraFieldsJson = htmlspecialchars(
    json_encode($departmentExtraFieldsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
// Why: suppliers.status_id is NOT NULL; quick-add must collect it (name + company_id/active auto-set).
$supplierExtraFieldsConfig = [
    [
        'name' => 'status_id',
        'label' => 'Supplier Status',
        'type' => 'select',
        'required' => true,
        'options' => array_map(static function ($status) {
            return [
                'value' => (string)((int)($status['id'] ?? 0)),
                'label' => (string)($status['label'] ?? ''),
            ];
        }, $supplierStatuses),
    ],
    [
        'name' => 'supplier_code',
        'label' => 'Supplier Code',
        'type' => 'text',
        'required' => false,
    ],
];
$supplierExtraFieldsJson = htmlspecialchars(
    json_encode($supplierExtraFieldsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$currentPhotoFilenames = equipment_parse_photo_filenames($data['photo_filename'] ?? '');
$currentPhotoUrls = [];
foreach ($currentPhotoFilenames as $currentPhotoFilename) {
    $currentPhotoUrls[] = UPLOAD_URL . rawurlencode((string)$currentPhotoFilename);
}

itm_equipment_poe_append_persisted_row($conn, $switchPoeOptions, (int)($data['switch_poe_id'] ?? 0), (int)$company_id);
equipment_append_persisted_department_option($conn, $departments, (int)($data['department_id'] ?? 0), (int)$company_id);
equipment_append_persisted_supplier_option($conn, $suppliers, (int)($data['supplier_id'] ?? 0), (int)$company_id);
equipment_append_persisted_employee_option($conn, $employees, (int)($data['assigned_to_employee_id'] ?? 0), (int)$company_id);
$assignmentAssignedDateHidden = equipment_resolve_assignment_assigned_date($data['updated_at'] ?? $data['created_at'] ?? '');
$data['assigned_date'] = $assignmentAssignedDateHidden;
$assignmentEquipmentIdHidden = $isEdit ? (int)$id : 0;
$data['equipment_id'] = (string)$assignmentEquipmentIdHidden;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'New'; ?> Equipment</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
    .photo-preview-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.65);
        z-index: 1200;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .photo-preview-content {
        background: var(--surface, #ffffff);
        border: 1px solid var(--border, #ddd);
        border-radius: 10px;
        max-width: min(90vw, 900px);
        max-height: 90vh;
        overflow: auto;
        padding: 12px;
        text-align: center;
    }
    .photo-preview-content img {
        max-width: 100%;
        max-height: calc(90vh - 120px);
        border-radius: 8px;
    }
    .photo-preview-actions {
        margin-bottom: 10px;
        text-align: right;
    }
    .photo-preview-trigger {
        margin-left: 8px;
    }
    .photo-preview-gallery {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .photo-preview-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .photo-preview-gallery img {
        width: 100%;
        height: auto;
        border: 1px solid var(--border, #ddd);
        border-radius: 8px;
    }
    .switch-details-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(220px, 1fr));
        gap: 14px 20px;
    }
    .form-row-3 {
        grid-template-columns: repeat(3, minmax(220px, 1fr));
    }
    .switch-details-grid .form-group {
        margin: 0;
    }
    .role-flags-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 14px;
        margin-top: 8px;
    }
    .role-flag-option {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 38px;
        padding: 8px 12px;
        border: 1px solid var(--border, #ddd);
        border-radius: 8px;
        background: var(--bg-secondary, #f6f8fa);
        color: var(--text-primary, #24292f);
        white-space: nowrap;
    }
    .role-flag-option input[type="checkbox"] {
        margin: 0;
    }
    .status-field-wrap {
        margin-top: 12px;
    }
    @media (max-width: 1100px) {
        .switch-details-grid {
            grid-template-columns: repeat(2, minmax(220px, 1fr));
        }
    }
    @media (max-width: 900px) {
        .form-row-3,
        .switch-details-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="<?php echo $isEdit ? 'Edit equipment' : 'New equipment'; ?>"><?php echo $isEdit ? '✏️' : '➕'; ?></h1>
            <?php if ($success): ?>
                <div class="alert alert-success">Equipment updated successfully.</div>
            <?php endif; ?>
            <?php echo itm_render_alert_errors($error ?? ''); ?>
            <div class="card">
                <form id="equipmentForm" method="POST" enctype="multipart/form-data"
                      data-original-idf-id="<?php echo sanitize((string)($originalData['idf_id'] ?? '')); ?>"
                      data-original-switch-rj45-id="<?php echo sanitize((string)($originalData['switch_rj45_id'] ?? '')); ?>"
                      data-original-switch-layout-id="<?php echo sanitize((string)($originalData['switch_port_numbering_layout_id'] ?? '')); ?>"
                      data-original-switch-fiber-ports-number="<?php echo sanitize((string)($originalData['switch_fiber_ports_number'] ?? '')); ?>"
                      data-original-switch-poe-id="<?php echo sanitize((string)($originalData['switch_poe_id'] ?? '')); ?>"
                      data-original-switch-environment-id="<?php echo sanitize((string)($originalData['switch_environment_id'] ?? '')); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <div class="form-row form-row-3">
                <div class="form-group"><label>Name *</label><input required name="name" value="<?php echo sanitize($data['name']); ?>"></div>
                <div class="form-group"><label>Type *</label><select name="equipment_type_id" required data-addable-select="1" data-add-table="equipment_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="equipment type"><option value="">-- Select --</option><?php render_options($types, $data['equipment_type_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>MAC Address</label><input name="mac_address" value="<?php echo sanitize($data['mac_address']); ?>"></div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group"><label>Hostname</label><input name="hostname" value="<?php echo sanitize($data['hostname']); ?>"></div>
                <div class="form-group"><label>IP Address</label><input name="ip_address" value="<?php echo sanitize($data['ip_address']); ?>"></div>
                <div class="form-group"><label>Manufacturer</label><select name="manufacturer_id" data-addable-select="1" data-add-table="manufacturers" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="manufacturer"><option value="">-- None --</option><?php render_options($manufacturers, $data['manufacturer_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div id="printer-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group"><label>Printer Type</label><select name="printer_device_type_id" data-addable-select="1" data-add-table="printer_device_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="printer type"><option value="">-- None --</option><?php render_options($printerTypes, $data['printer_device_type_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="role-flags-grid">
                        <label class="role-flag-option"><input type="checkbox" name="printer_color_capable" <?php echo (int)$data['printer_color_capable'] === 1 ? 'checked' : ''; ?>> Printer Color Capable</label>
                        <label class="role-flag-option"><input type="checkbox" name="printer_scan" <?php echo (int)$data['printer_scan'] === 1 ? 'checked' : ''; ?>> Printer & Scan</label>
                    </div>
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group"><label>Serial Number</label><input name="serial_number" value="<?php echo sanitize($data['serial_number']); ?>"></div>
                <div class="form-group"><label>Model</label><input name="model" value="<?php echo sanitize($data['model']); ?>"></div>
                <div class="form-group"><label>Supplier</label><select name="supplier_id" data-addable-select="1" data-add-table="suppliers" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="supplier" data-add-extra-fields="<?php echo $supplierExtraFieldsJson; ?>"><option value="">-- None --</option><?php render_options($suppliers, $data['supplier_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Warranty Type</label><select name="warranty_type_id" data-addable-select="1" data-add-table="warranty_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="warranty type"><option value="">-- Select --</option><?php render_options($warrantyTypes, $data['warranty_type_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Warranty Expiry</label><input type="date" name="warranty_expiry" value="<?php echo sanitize($data['warranty_expiry']); ?>"></div>
                <div class="form-group"></div>
            </div>
            <div id="server-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group"><label>Certificate Expiry</label><input type="date" name="certificate_expiry" value="<?php echo sanitize($data['certificate_expiry']); ?>"></div>
                    <div class="form-group"></div>
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?php echo sanitize($data['purchase_date']); ?>"></div>
                <div class="form-group"><label>Purchase Cost</label><input type="number" step="0.01" name="purchase_cost" value="<?php echo sanitize($data['purchase_cost']); ?>"></div>
                <div class="form-group"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Photo Upload</label>
                    <div id="equipmentPhotoUploadTarget" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload equipment photos">
                        <p class="itm-dropzone-hint">Drag and drop images here, or click to browse. You can select multiple photos.</p>
                        <input type="file" name="photo[]" id="equipmentPhotoInput" accept="image/*" multiple>
                    </div>
                    <div class="form-hint">You can upload one or many photos at once.<?php if ($isEdit): ?> Files upload automatically after selection when editing.<?php endif; ?></div>
                    <?php if (!empty($currentPhotoFilenames)): ?>
                        <input type="hidden" name="delete_photo" id="deletePhotoInput" value="0">
                        <input type="hidden" name="delete_photo_indexes" id="deletePhotoIndexesInput" value="">
                    <?php endif; ?>
                    <div class="form-hint" id="currentPhotoHint">
                        <span id="currentPhotoHintText"><?php echo !empty($currentPhotoFilenames) ? 'Current photos: ' . count($currentPhotoFilenames) : 'Selected photos: 0'; ?></span>
                        <button type="button" class="btn btn-sm photo-preview-trigger" id="openPhotoPreview">🔎</button>
                        <?php if (!empty($currentPhotoFilenames)): ?>
                            <button type="button" class="btn btn-sm" id="deletePhotoButton" style="margin-left:8px;">Delete All</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>RAM</label><select name="workstation_ram_id" data-addable-select="1" data-add-table="workstation_ram" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="ram"><option value="">-- None --</option><?php render_options($workstationRamOptions, $data['workstation_ram_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Storage (GB/TB)</label><input name="workstation_storage" value="<?php echo sanitize($data['workstation_storage']); ?>" placeholder="e.g. 512 GB / 1 TB"></div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group"><label>Workstation Processor</label><input name="workstation_processor" value="<?php echo sanitize($data['workstation_processor']); ?>"></div>
                <div class="form-group"><label>Workstation Device Type</label><select name="workstation_device_type_id" data-addable-select="1" data-add-table="workstation_device_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation device type"><option value="">-- None --</option><?php render_options($workstationDeviceTypes, $data['workstation_device_type_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Workstation OS Type</label><select name="workstation_os_type_id" data-addable-select="1" data-add-table="workstation_os_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation os type"><option value="">-- None --</option><?php render_options($workstationOsTypes, $data['workstation_os_type_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group"><label>Workstation Office</label><select name="workstation_office_id" data-addable-select="1" data-add-table="workstation_office" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation office"><option value="">-- None --</option><?php render_options($workstationOfficeOptions, $data['workstation_office_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"><label>Workstation OS Installed On</label><input type="date" name="workstation_os_installed_on" value="<?php echo sanitize($data['workstation_os_installed_on']); ?>"></div>
                <div class="form-group"><label>Workstation OS Version</label><select name="workstation_os_version_id" data-addable-select="1" data-add-table="workstation_os_versions" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="workstation os version"><option value="">-- None --</option><?php render_options($workstationOsVersions, $data['workstation_os_version_id']); ?><option value="__add_new__">➕</option></select></div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group"><label>Assign To Employee</label><select name="assigned_to_employee_id"><option value="">-- None --</option><?php render_options($employees, $data['assigned_to_employee_id']); ?></select><input type="hidden" name="equipment_id" value="<?php echo (int)$assignmentEquipmentIdHidden; ?>"><input type="hidden" name="assigned_date" value="<?php echo sanitize($assignmentAssignedDateHidden); ?>"></div>
                <div class="form-group"><label>Department</label><select name="department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="department" data-add-extra-fields="<?php echo $departmentExtraFieldsJson; ?>"><option value="">-- None --</option><?php render_options($departments, $data['department_id']); ?><option value="__add_new__">➕</option></select></div>
                <div class="form-group"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" rows="5"><?php echo sanitize($data['notes']); ?></textarea></div>
            <div id="switch-fields" style="display:block;">
                <h3 style="margin-top:20px;">Network Details</h3>
                <div class="switch-details-grid">
                    <div class="form-group"><label>Location</label><select name="location_id" data-addable-select="1" data-add-table="it_locations" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="location" data-add-extra-fields="<?php echo $locationExtraFieldsJson; ?>"><option value="">-- None --</option><?php render_options($locations, $data['location_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>Rack</label><select name="rack_id" data-addable-select="1" data-add-table="racks" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rack" data-add-extra-fields="<?php echo $rackExtraFieldsJson; ?>"><option value="">-- None --</option><?php render_options($racks, $data['rack_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>IDF</label><select class="input" id="idf-rack-select" name="idf_id" data-addable-select="1" data-add-table="idfs" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="idf"><option value="">-- Select IDF --</option><?php render_options($idfs, $data['idf_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>PoE Type</label><select name="switch_poe_id" data-addable-select="1" data-add-table="equipment_poe" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="poe type"><option value="">-- None --</option><?php render_options($switchPoeOptions, $data['switch_poe_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>RJ45 Cable</label><select name="rj45_speed_id" data-addable-select="1" data-add-table="rj45_speed" data-add-id-col="id" data-add-label-col="cable_type" data-add-company-scoped="1" data-add-friendly="rj45 cable"><option value="">-- None --</option><?php render_options($rj45CableOptions, $data['rj45_speed_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>RJ45 Ports *</label><select name="switch_rj45_id" data-addable-select="1" data-add-table="equipment_rj45" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="rj45 port option"><option value="">-- Select --</option><?php render_options($switchRj45Options, $data['switch_rj45_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>Port Numbering Layout</label><select name="switch_port_numbering_layout_id" data-addable-select="1" data-add-table="switch_port_numbering_layout" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="port numbering layout"><option value="">-- Select --</option><?php render_options($switchPortNumberingLayoutOptions, $data['switch_port_numbering_layout_id']); ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>Patch Port</label><input name="patch_port" value="<?php echo sanitize($data['patch_port']); ?>"></div>
                    <div class="form-group"><label>Fiber Ports Number</label><select id="switch-fiber-ports-number-select" name="switch_fiber_ports_number"><option value="">-- None --</option><?php $switchFiberPortsNumberOptions = ['2','4','8','12','16','24','32','48']; foreach ($switchFiberPortsNumberOptions as $switchFiberPortsNumberOption): ?><option value="<?php echo sanitize($switchFiberPortsNumberOption); ?>" <?php echo (sanitize((string)$data['switch_fiber_ports_number']) === (string)$switchFiberPortsNumberOption ? 'selected' : ''); ?>><?php echo sanitize($switchFiberPortsNumberOption); ?></option><?php endforeach; ?><?php if ((string)$data['switch_fiber_ports_number'] !== '' && !in_array((string)$data['switch_fiber_ports_number'], $switchFiberPortsNumberOptions, true)): ?><option value="<?php echo sanitize($data['switch_fiber_ports_number']); ?>" selected><?php echo sanitize($data['switch_fiber_ports_number']); ?></option><?php endif; ?><option value="__add_new__">➕</option></select></div>
                    <div class="form-group"><label>Management</label><select name="switch_environment_id" data-addable-select="1" data-add-table="equipment_environment" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="management type"><option value="">-- None --</option><?php render_options($switchEnvironmentOptions, $data['switch_environment_id']); ?><option value="__add_new__">➕</option></select></div>
                </div>
            </div>
            <div class="form-group status-field-wrap">
                <label>Status</label>
                <select name="status_id" data-addable-select="1" data-add-table="equipment_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="status">
                    <option value="">-- Select --</option>
                    <?php render_options($statuses, $data['status_id']); ?>
                    <option value="__add_new__">➕</option>
                </select>
            </div>
            <input type="hidden" name="active" value="<?php echo (int)$data['active']; ?>">
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾</button>
                <a href="index.php" class="btn">🔙</a>
            </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="photo-preview-modal" id="photoPreviewModal" aria-hidden="true">
    <div class="photo-preview-content" role="dialog" aria-modal="true" aria-label="Equipment photos" onclick="event.stopPropagation()">
        <div class="photo-preview-actions">
            <button type="button" class="btn btn-sm" id="closePhotoPreview">Close</button>
        </div>
        <div class="photo-preview-gallery" id="existingPhotoPreviewGallery">
            <?php foreach ($currentPhotoUrls as $photoIndex => $photoUrl): ?>
                <div class="photo-preview-item">
                    <a href="<?php echo sanitize($photoUrl); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo sanitize($photoUrl); ?>" alt="Current equipment photo <?php echo (int)$photoIndex + 1; ?>">
                    </a>
                    <button type="button" class="btn btn-sm delete-photo-item" data-photo-index="<?php echo (int)$photoIndex; ?>" aria-label="Delete photo <?php echo (int)$photoIndex + 1; ?>">♻️ Delete</button>
                </div>
            <?php endforeach; ?>
        </div>
        <h4 style="margin:14px 0 8px;">Selected (not saved yet)</h4>
        <div class="photo-preview-gallery" id="pendingPhotoPreviewGallery"></div>
        <p id="photoPreviewEmptyHint" style="margin-top:12px;color:var(--text-muted,#666);display:none;">No photos to preview yet.</p>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script src="../../js/itm-upload-helper.js"></script>
<script>
(function () {
    var typeSelect = document.querySelector('select[name="equipment_type_id"]');
    var switchFields = document.getElementById('switch-fields');
    var serverFields = document.getElementById('server-fields');
    var printerFields = document.getElementById('printer-fields');
    var switchFiberPortsNumberSelect = document.getElementById('switch-fiber-ports-number-select');
    var switchTypeId = '<?php echo (int)$switchTypeId; ?>';
    var serverTypeId = '<?php echo (int)$serverTypeId; ?>';
    var printerTypeId = '<?php echo (int)$printerTypeId; ?>';

    function setupFiberPortsNumberQuickAdd() {
        if (!switchFiberPortsNumberSelect) {
            return;
        }

        var previousValue = switchFiberPortsNumberSelect.value || '';
        switchFiberPortsNumberSelect.addEventListener('focus', function () {
            if (switchFiberPortsNumberSelect.value !== '__add_new__') {
                previousValue = switchFiberPortsNumberSelect.value || '';
            }
        });

        switchFiberPortsNumberSelect.addEventListener('change', function () {
            if (switchFiberPortsNumberSelect.value !== '__add_new__') {
                previousValue = switchFiberPortsNumberSelect.value || '';
                return;
            }

            var typedValue = window.prompt('Enter Fiber Ports Number');
            if (typedValue === null) {
                switchFiberPortsNumberSelect.value = previousValue;
                return;
            }

            typedValue = String(typedValue).trim();
            if (typedValue === '') {
                switchFiberPortsNumberSelect.value = previousValue;
                return;
            }

            var existingOption = Array.prototype.find.call(switchFiberPortsNumberSelect.options, function (option) {
                return String(option.value) === typedValue;
            });
            if (!existingOption) {
                var addOption = Array.prototype.find.call(switchFiberPortsNumberSelect.options, function (option) {
                    return String(option.value) === '__add_new__';
                });
                var customOption = new Option(typedValue, typedValue, true, true);
                if (addOption) {
                    switchFiberPortsNumberSelect.insertBefore(customOption, addOption);
                } else {
                    switchFiberPortsNumberSelect.appendChild(customOption);
                }
            }

            switchFiberPortsNumberSelect.value = typedValue;
            previousValue = typedValue;
        });
    }

    function toggleSwitchFields() {
        if (!typeSelect || !switchFields) {
            return;
        }
        switchFields.style.display = 'block';
    }

    function toggleServerFields() {
        if (!typeSelect || !serverFields) {
            return;
        }
        var show = serverTypeId !== '0' && typeSelect.value === serverTypeId;
        serverFields.style.display = show ? 'block' : 'none';
        if (!show) {
            var certificateInput = serverFields.querySelector('input[name="certificate_expiry"]');
            if (certificateInput) {
                certificateInput.value = '';
            }
        }
    }

    function togglePrinterFields() {
        if (!typeSelect || !printerFields) {
            return;
        }

        var show = printerTypeId !== '0' && typeSelect.value === printerTypeId;
        printerFields.style.display = show ? 'block' : 'none';

        if (!show) {
            var printerTypeSelect = printerFields.querySelector('select[name="printer_device_type_id"]');
            if (printerTypeSelect) {
                printerTypeSelect.value = '';
            }
            var colorCapableInput = printerFields.querySelector('input[name="printer_color_capable"]');
            if (colorCapableInput) {
                colorCapableInput.checked = false;
            }
            var printerScanInput = printerFields.querySelector('input[name="printer_scan"]');
            if (printerScanInput) {
                printerScanInput.checked = false;
            }
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleSwitchFields);
        typeSelect.addEventListener('change', toggleServerFields);
        typeSelect.addEventListener('change', togglePrinterFields);
        toggleSwitchFields();
        toggleServerFields();
        togglePrinterFields();
    }
    setupFiberPortsNumberQuickAdd();

    var openPhotoPreview = document.getElementById('openPhotoPreview');
    var photoPreviewModal = document.getElementById('photoPreviewModal');
    var closePhotoPreview = document.getElementById('closePhotoPreview');
    var deletePhotoButton = document.getElementById('deletePhotoButton');
    var deletePhotoInput = document.getElementById('deletePhotoInput');
    var deletePhotoIndexesInput = document.getElementById('deletePhotoIndexesInput');
    var currentPhotoHintText = document.getElementById('currentPhotoHintText');
    var photoInput = document.getElementById("equipmentPhotoInput");
    if (typeof itmUploadHelper !== "undefined") {
        itmUploadHelper.setupById("equipmentPhotoUploadTarget", "equipmentPhotoInput");
    }
    var equipmentForm = document.getElementById('equipmentForm');
    var deletePhotoItemButtons = document.querySelectorAll('.delete-photo-item');
    var existingPhotoPreviewGallery = document.getElementById('existingPhotoPreviewGallery');
    var pendingPhotoPreviewGallery = document.getElementById('pendingPhotoPreviewGallery');
    var photoPreviewEmptyHint = document.getElementById('photoPreviewEmptyHint');
    var pendingDeletedPhotoIndexes = new Set();
    var totalCurrentPhotos = deletePhotoItemButtons.length;
    var isEditMode = <?php echo $isEdit ? 'true' : 'false'; ?>;
    var isAutoSubmitting = false;
    var selectedPhotoPreviewUrls = [];

    function resetPendingPhotoDeletionState() {
        pendingDeletedPhotoIndexes.clear();
        if (deletePhotoInput) {
            deletePhotoInput.value = '0';
        }
        if (deletePhotoIndexesInput) {
            deletePhotoIndexesInput.value = '';
        }
        if (deletePhotoButton) {
            deletePhotoButton.disabled = false;
        }
    }

    function syncDeletePhotoIndexes() {
        if (!deletePhotoIndexesInput) {
            return;
        }
        deletePhotoIndexesInput.value = Array.from(pendingDeletedPhotoIndexes).sort(function (a, b) { return a - b; }).join(',');
    }

    function updateCurrentPhotoHint() {
        if (!currentPhotoHintText) {
            return;
        }
        var selectedPhotoCount = pendingPhotoPreviewGallery ? pendingPhotoPreviewGallery.children.length : 0;
        if (deletePhotoInput && deletePhotoInput.value === '1') {
            currentPhotoHintText.textContent = 'Current photos will be deleted after you save.';
            return;
        }
        if (pendingDeletedPhotoIndexes.size > 0) {
            var remainingPhotos = Math.max(totalCurrentPhotos - pendingDeletedPhotoIndexes.size, 0);
            currentPhotoHintText.textContent = pendingDeletedPhotoIndexes.size + ' photo(s) will be deleted after you save. Remaining: ' + remainingPhotos + '.';
            return;
        }
        if (totalCurrentPhotos > 0) {
            currentPhotoHintText.textContent = 'Current photos: ' + totalCurrentPhotos + '. Selected (not saved): ' + selectedPhotoCount + '.';
            return;
        }
        currentPhotoHintText.textContent = 'Selected photos: ' + selectedPhotoCount + ' (not saved yet).';
    }

    function updatePhotoPreviewActionState() {
        var visibleExistingPhotos = 0;
        if (existingPhotoPreviewGallery) {
            Array.prototype.forEach.call(existingPhotoPreviewGallery.children, function (item) {
                if (item.style.display !== 'none') {
                    visibleExistingPhotos += 1;
                }
            });
        }
        var selectedPhotoCount = pendingPhotoPreviewGallery ? pendingPhotoPreviewGallery.children.length : 0;
        var hasAnyPhotos = visibleExistingPhotos > 0 || selectedPhotoCount > 0;

        if (openPhotoPreview) {
            openPhotoPreview.disabled = !hasAnyPhotos;
        }
        if (photoPreviewEmptyHint) {
            photoPreviewEmptyHint.style.display = hasAnyPhotos ? 'none' : 'block';
        }
    }

    function clearPendingPhotoPreview() {
        selectedPhotoPreviewUrls.forEach(function (url) {
            URL.revokeObjectURL(url);
        });
        selectedPhotoPreviewUrls = [];
        if (pendingPhotoPreviewGallery) {
            pendingPhotoPreviewGallery.innerHTML = '';
        }
    }

    function renderPendingPhotoPreview() {
        clearPendingPhotoPreview();
        if (!pendingPhotoPreviewGallery || !photoInput || !photoInput.files) {
            updatePhotoPreviewActionState();
            updateCurrentPhotoHint();
            return;
        }

        Array.prototype.forEach.call(photoInput.files, function (file, index) {
            if (!file || typeof file.type !== 'string' || file.type.indexOf('image/') !== 0) {
                return;
            }
            var previewUrl = URL.createObjectURL(file);
            selectedPhotoPreviewUrls.push(previewUrl);

            var item = document.createElement('div');
            item.className = 'photo-preview-item';
            var image = document.createElement('img');
            image.src = previewUrl;
            image.alt = 'Selected equipment photo ' + (index + 1);
            item.appendChild(image);

            var label = document.createElement('small');
            label.textContent = file.name;
            item.appendChild(label);
            pendingPhotoPreviewGallery.appendChild(item);
        });

        updatePhotoPreviewActionState();
        updateCurrentPhotoHint();
    }

    function hidePhotoModal() {
        if (!photoPreviewModal) {
            return;
        }
        photoPreviewModal.style.display = 'none';
        photoPreviewModal.setAttribute('aria-hidden', 'true');
    }

    resetPendingPhotoDeletionState();
    updateCurrentPhotoHint();
    updatePhotoPreviewActionState();
    window.addEventListener('pageshow', function () {
        resetPendingPhotoDeletionState();
        clearPendingPhotoPreview();
        updateCurrentPhotoHint();
        updatePhotoPreviewActionState();
    });

    if (openPhotoPreview && photoPreviewModal) {
        openPhotoPreview.addEventListener('click', function (event) {
            event.preventDefault();
            photoPreviewModal.style.display = 'flex';
            photoPreviewModal.setAttribute('aria-hidden', 'false');
        });

        photoPreviewModal.addEventListener('click', hidePhotoModal);

        if (closePhotoPreview) {
            closePhotoPreview.addEventListener('click', hidePhotoModal);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hidePhotoModal();
            }
        });
    }

    if (deletePhotoButton && deletePhotoInput) {
        deletePhotoButton.addEventListener('click', function () {
            deletePhotoInput.value = '1';
            pendingDeletedPhotoIndexes.clear();
            syncDeletePhotoIndexes();
            hidePhotoModal();
            updateCurrentPhotoHint();
            if (photoInput) {
                photoInput.value = '';
            }
            deletePhotoButton.disabled = true;
        });
    }

    if (deletePhotoItemButtons.length > 0 && deletePhotoInput) {
        deletePhotoItemButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (deletePhotoInput.value === '1') {
                    return;
                }
                var photoIndex = parseInt(button.getAttribute('data-photo-index') || '', 10);
                if (!Number.isInteger(photoIndex) || photoIndex < 0) {
                    return;
                }
                pendingDeletedPhotoIndexes.add(photoIndex);
                syncDeletePhotoIndexes();
                var photoItem = button.closest('.photo-preview-item');
                if (photoItem) {
                    photoItem.style.display = 'none';
                }
                updateCurrentPhotoHint();
                updatePhotoPreviewActionState();
            });
        });
    }

    if (photoInput) {
        photoInput.addEventListener('change', renderPendingPhotoPreview);
    }

    if (photoInput && equipmentForm && isEditMode) {
        photoInput.addEventListener('change', function () {
            if (isAutoSubmitting || !photoInput.files || photoInput.files.length === 0) {
                return;
            }
            isAutoSubmitting = true;
            equipmentForm.requestSubmit();
        });
    }

    if (equipmentForm && isEditMode) {
        equipmentForm.addEventListener('submit', function (event) {
            if (isAutoSubmitting) {
                return;
            }

            var idfSelect = equipmentForm.querySelector('select[name="idf_id"]');
            if (!idfSelect) {
                return;
            }

            var originalIdfId = String(equipmentForm.getAttribute('data-original-idf-id') || '').trim();
            var nextIdfId = String(idfSelect.value || '').trim();
            if (originalIdfId !== '' && nextIdfId === '') {
                var message = 'Changing IDF to NULL will remove this equipment from the current IDF and delete related IDF position, ports, links, and synchronization data. This action cannot be undone.\n\nDo you want to continue saving?';
                if (!confirm(message)) {
                    event.preventDefault();
                    return;
                }
            }

            var normalizeValue = function (value) {
                return String(value === null || value === undefined ? '' : value).trim();
            };
            var switchRj45Select = equipmentForm.querySelector('select[name="switch_rj45_id"]');
            var switchLayoutSelect = equipmentForm.querySelector('select[name="switch_port_numbering_layout_id"]');
            var switchFiberPortsSelect = equipmentForm.querySelector('select[name="switch_fiber_ports_number"]');
            var switchPoeSelect = equipmentForm.querySelector('select[name="switch_poe_id"]');
            var switchEnvironmentSelect = equipmentForm.querySelector('select[name="switch_environment_id"]');

            var switchLayoutOrEnvChanged =
                normalizeValue(equipmentForm.getAttribute('data-original-switch-layout-id')) !== normalizeValue(switchLayoutSelect ? switchLayoutSelect.value : '') ||
                normalizeValue(equipmentForm.getAttribute('data-original-switch-poe-id')) !== normalizeValue(switchPoeSelect ? switchPoeSelect.value : '') ||
                normalizeValue(equipmentForm.getAttribute('data-original-switch-environment-id')) !== normalizeValue(switchEnvironmentSelect ? switchEnvironmentSelect.value : '');

            if (switchLayoutOrEnvChanged) {
                var switchMessage = 'Changing switch layout, PoE, or environment will update metadata on existing switch/IDF ports without removing cable links.\n\nDo you want to continue saving?';
                if (!confirm(switchMessage)) {
                    event.preventDefault();
                }
            }
        });
    }
})();
</script>
</body>
</html>
