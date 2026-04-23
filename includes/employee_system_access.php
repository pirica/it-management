<?php
/**
 * Employee System Access Management Functions
 * 
 * Manages the permissions and system access levels for employees.
 * Handles synchronization between employee rows and the legacy
 * employee_system_access permission matrix.
 */

/**
 * Returns a map of standard access field codes to their human-readable labels
 */
function esa_ability_fields() {
    return [
        'network_access' => 'Network Access',
        'micros_emc' => 'Micros Emc',
        'opera_username' => 'Opera Username',
        'micros_card' => 'Micros Card',
        'pms_id' => 'Pms Id',
        'synergy_mms' => 'Synergy Mms',
        'email_account' => 'Email Account',
        'landline_phone' => 'Landline Phone',
        'hu_the_lobby' => 'Hu The Lobby',
        'mobile_phone' => 'Mobile Phone',
        'navision' => 'Navision',
        'mobile_email' => 'Mobile Email',
        'onq_ri' => 'Onq Ri',
        'birchstreet' => 'Birchstreet',
        'delphi' => 'Delphi',
        'omina' => 'Omina',
        'vingcard_system' => 'Vingcard System',
        'digital_rev' => 'Digital Rev',
        'office_key_card' => 'Office Key Card',
    ];
}

/**
 * Resolves the writable legacy access fields for a company.
 *
 * Why: some tenants maintain additional system_access codes mapped to
 * employee_system_access columns; this keeps edit/save/view aligned so
 * all configured access flags persist correctly.
 */
function esa_resolve_ability_fields($conn, $companyId = null) {
    $companyId = $companyId === null ? esa_current_company_id() : (int)$companyId;
    $resolved = esa_ability_fields();

    if ($companyId <= 0) {
        return $resolved;
    }

    $catalogStmt = mysqli_prepare($conn, 'SELECT `code`, `name` FROM `system_access` WHERE `company_id` = ?');
    if ($catalogStmt) {
        mysqli_stmt_bind_param($catalogStmt, 'i', $companyId);
        mysqli_stmt_execute($catalogStmt);
        $catalogRes = mysqli_stmt_get_result($catalogStmt);
        while ($catalogRes && ($catalogRow = mysqli_fetch_assoc($catalogRes))) {
            $catalogCode = (string)($catalogRow['code'] ?? '');
            if ($catalogCode === '' || !itm_is_safe_identifier($catalogCode)) {
                continue;
            }
            if (!itm_table_has_column($conn, 'employee_system_access', $catalogCode)) {
                continue;
            }
            $resolved[$catalogCode] = (string)($catalogRow['name'] ?? $catalogCode);
        }
        mysqli_stmt_close($catalogStmt);
    }

    return $resolved;
}

/**
 * Normalizes text for stable legacy/code label comparisons.
 */
function esa_normalize_access_token($value) {
    $value = strtolower(trim((string)$value));
    if ($value === '') {
        return '';
    }
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

/**
 * Resolves which employee_system_access column a system_access row should map to.
 *
 * Why: some environments have legacy system_access codes that differ from the
 * actual matrix column names; this fallback keeps load/save consistent.
 */
function esa_resolve_field_for_catalog_row($accessRow, $abilityFields) {
    $code = (string)($accessRow['code'] ?? '');
    if ($code !== '' && isset($abilityFields[$code])) {
        return $code;
    }

    $name = (string)($accessRow['name'] ?? '');
    $needleTokens = array_filter([
        esa_normalize_access_token($code),
        esa_normalize_access_token($name),
    ]);
    if (empty($needleTokens)) {
        return '';
    }

    foreach ($abilityFields as $fieldCode => $fieldLabel) {
        $fieldTokens = [
            esa_normalize_access_token($fieldCode),
            esa_normalize_access_token($fieldLabel),
        ];
        foreach ($needleTokens as $needle) {
            if ($needle === '') {
                continue;
            }
            if (in_array($needle, $fieldTokens, true)) {
                return (string)$fieldCode;
            }
        }
    }

    return '';
}

/**
 * Safely escapes a database identifier (table or column name)
 */
function esa_escape_identifier($name) {
    return '`' . str_replace('`', '``', (string)$name) . '`';
}

/**
 * Helper to get the current company ID from the session
 */
function esa_current_company_id() {
    return isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
}

/**
 * Keeps access catalogs/data aligned without creating schema at runtime.
 */
function esa_ensure_table($conn) {
    if (!esa_ensure_legacy_matrix_columns($conn)) {
        return false;
    }

    if (!esa_seed_system_access($conn)) {
        return false;
    }

    return esa_sync_from_employees_legacy($conn);
}

/**
 * Ensures legacy employee_system_access columns exist for all standard abilities.
 *
 * Why: older databases can miss newer permission flags, which makes checkboxes
 * appear unsaved even when selected in UI.
 */
function esa_ensure_legacy_matrix_columns($conn) {
    $requiredFields = array_keys(esa_ability_fields());
    foreach ($requiredFields as $field) {
        if (!itm_is_safe_identifier($field)) {
            continue;
        }
        if (itm_table_has_column($conn, 'employee_system_access', $field)) {
            continue;
        }

        $sql = 'ALTER TABLE `employee_system_access` ADD COLUMN ' . esa_escape_identifier($field) . " TINYINT(1) NOT NULL DEFAULT 0";
        if (mysqli_query($conn, $sql) === false) {
            return false;
        }
    }

    return true;
}

/**
 * Populates the system_access catalog with default entries for the current company
 */
function esa_seed_system_access($conn) {
    $companyId = esa_current_company_id();
    if ($companyId <= 0) {
        return true;
    }

    $values = [];
    foreach (esa_ability_fields() as $code => $label) {
        $codeEsc = mysqli_real_escape_string($conn, $code);
        $labelEsc = mysqli_real_escape_string($conn, $label);
        $values[] = '(' . $companyId . ", '{$codeEsc}', '{$labelEsc}', 1)";
    }

    if (!$values) {
        return true;
    }

    $sql = 'INSERT INTO `system_access` (`company_id`, `code`, `name`, `active`) VALUES ' . implode(', ', $values)
        . ' ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `active`=1';

    return mysqli_query($conn, $sql) !== false;
}

/**
 * Migrates permission data from the 'employees' table to 'employee_system_access'
 */
function esa_sync_from_employees_legacy($conn) {
    // Determine which columns actually exist in the employees table
    $employeeColumns = [];
    $columnsRes = mysqli_query($conn, 'SHOW COLUMNS FROM employees');
    while ($columnsRes && ($column = mysqli_fetch_assoc($columnsRes))) {
        $employeeColumns[(string)$column['Field']] = true;
    }

    $abilityFields = esa_resolve_ability_fields($conn, esa_current_company_id());
    $selectParts = [];
    foreach (array_keys($abilityFields) as $field) {
        if (isset($employeeColumns[$field])) {
            $fieldEsc = esa_escape_identifier($field);
            $selectParts[] = 'COALESCE(e.' . $fieldEsc . ', 0) AS ' . $fieldEsc;
        } else {
            $selectParts[] = '0 AS ' . esa_escape_identifier($field);
        }
    }

    // Perform bulk insertion of missing records
    $sql = 'INSERT INTO `employee_system_access` (`company_id`, `employee_id`, ' . implode(', ', array_map('esa_escape_identifier', array_keys($abilityFields))) . ') '
        . 'SELECT e.`company_id`, e.`id`, ' . implode(', ', $selectParts) . ' '
        . 'FROM `employees` e '
        . 'LEFT JOIN `employee_system_access` esa ON esa.`company_id` = e.`company_id` AND esa.`employee_id` = e.`id` '
        . 'WHERE esa.`id` IS NULL';

    return mysqli_query($conn, $sql) !== false;
}

/**
 * Returns a mapping of system codes to their database IDs for the current company
 */
function esa_system_access_id_map($conn) {
    $companyId = esa_current_company_id();
    $map = [];
    if ($companyId <= 0) {
        return $map;
    }
    $res = mysqli_query($conn, 'SELECT id, code FROM `system_access` WHERE `company_id`=' . $companyId);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[(string)$row['code']] = (int)$row['id'];
    }
    return $map;
}

/**
 * Retrieves the catalog of available systems for a specific company
 */
function esa_get_system_access_catalog($conn, $companyId, $includeInactive = false) {
    $companyId = (int)$companyId;
    $rows = [];
    if ($companyId <= 0) {
        return $rows;
    }

    $sql = 'SELECT `id`, `code`, `name`, `active` FROM `system_access` WHERE `company_id`=' . $companyId;
    if (!$includeInactive) {
        $sql .= ' AND `active`=1';
    }
    $sql .= ' ORDER BY `name` ASC';

    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'code' => (string)($row['code'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'active' => (int)($row['active'] ?? 0),
        ];
    }

    return $rows;
}

/**
 * Gets a list of system IDs that a specific employee has access to.
 * Reads from legacy employee_system_access columns and maps them to system_access IDs.
 */
function esa_get_employee_access_ids($conn, $companyId, $employeeId) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $ids = [];

    $fields = esa_resolve_ability_fields($conn, $companyId);
    $fieldSql = implode(', ', array_map('esa_escape_identifier', array_keys($fields)));
    $sql = 'SELECT ' . $fieldSql . ' FROM `employee_system_access` WHERE `company_id`=' . $companyId
        . ' AND `employee_id`=' . $employeeId . ' LIMIT 1';
    $res = mysqli_query($conn, $sql);
    $row = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return $ids;
    }

    $catalog = esa_get_system_access_catalog($conn, $companyId, true);
    foreach ($catalog as $accessRow) {
        $mappedField = esa_resolve_field_for_catalog_row($accessRow, $fields);
        if ($mappedField === '') {
            continue;
        }
        if ((int)($row[$mappedField] ?? 0) === 1) {
            $mappedId = (int)($accessRow['id'] ?? 0);
            if ($mappedId > 0) {
                $ids[] = $mappedId;
            }
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Saves employee access permissions using system IDs.
 * Persists to the legacy employee_system_access matrix only.
 */
function esa_save_employee_access_ids($conn, $companyId, $employeeId, $systemAccessIds) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $systemAccessIds = is_array($systemAccessIds) ? $systemAccessIds : [];

    // Filter and validate IDs
    $cleanIds = [];
    foreach ($systemAccessIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $cleanIds[$id] = true;
        }
    }
    $cleanIds = array_keys($cleanIds);

    // Verify provided IDs belong to the company
    $validAccessById = [];
    if (!empty($cleanIds)) {
        $idSql = implode(',', array_map('intval', $cleanIds));
        $res = mysqli_query($conn, 'SELECT `id`, `code`, `name` FROM `system_access` WHERE `company_id`=' . $companyId . ' AND `id` IN (' . $idSql . ')');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $validAccessById[(int)$row['id']] = $row;
        }
    }

    $legacyFields = esa_resolve_ability_fields($conn, $companyId);
    $normalized = [];
    foreach (array_keys($legacyFields) as $field) {
        $normalized[$field] = 0;
    }
    foreach ($validAccessById as $catalogRow) {
        $resolvedField = esa_resolve_field_for_catalog_row($catalogRow, $legacyFields);
        if ($resolvedField !== '' && isset($normalized[$resolvedField])) {
            $normalized[$resolvedField] = 1;
        }
    }

    $columns = ['company_id', 'employee_id'];
    $values = [$companyId, $employeeId];
    $updates = [];
    foreach ($normalized as $field => $value) {
        $columns[] = $field;
        $values[] = (int)$value;
        $fieldEsc = esa_escape_identifier($field);
        $updates[] = $fieldEsc . '=' . (int)$value;
    }

    $legacySql = 'INSERT INTO `employee_system_access` (' . implode(', ', array_map('esa_escape_identifier', $columns)) . ') VALUES (' . implode(', ', array_map('intval', $values)) . ') '
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    return mysqli_query($conn, $legacySql) !== false;
}

/**
 * Gets the system access map for an employee
 * 
 * Reads from the legacy flat table.
 */
function esa_get_employee_access($conn, $companyId, $employeeId) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $fields = esa_resolve_ability_fields($conn, $companyId);

    $defaults = [];
    foreach (array_keys($fields) as $field) {
        $defaults[$field] = 0;
    }

    // Read from legacy flat table
    $legacySql = 'SELECT * FROM `employee_system_access` WHERE `company_id`=' . $companyId . ' AND `employee_id`=' . $employeeId . ' LIMIT 1';
    $legacyRes = mysqli_query($conn, $legacySql);
    if ($legacyRes && mysqli_num_rows($legacyRes) === 1) {
        $row = mysqli_fetch_assoc($legacyRes);
        foreach (array_keys($fields) as $field) {
            $defaults[$field] = ((int)($row[$field] ?? 0) === 1) ? 1 : 0;
        }
    }

    return $defaults;
}

/**
 * Saves employee access using a payload of system codes
 * 
 * Saves to legacy storage.
 */
function esa_save_employee_access($conn, $companyId, $employeeId, $payload) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $fields = esa_resolve_ability_fields($conn, $companyId);
    $normalized = [];

    // Normalize payload to 1/0
    foreach (array_keys($fields) as $field) {
        $normalized[$field] = !empty($payload[$field]) ? 1 : 0;
    }

    $columns = ['company_id', 'employee_id'];
    $values = [$companyId, $employeeId];
    $updates = [];

    foreach ($normalized as $field => $value) {
        $columns[] = $field;
        $values[] = (int)$value;
        $fieldEsc = esa_escape_identifier($field);
        $updates[] = $fieldEsc . '=' . (int)$value;
    }

    $insertCols = implode(', ', array_map('esa_escape_identifier', $columns));
    $insertVals = implode(', ', array_map('intval', $values));
    $legacySql = 'INSERT INTO `employee_system_access` (' . $insertCols . ') VALUES (' . $insertVals . ') '
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    return mysqli_query($conn, $legacySql) !== false;
}
