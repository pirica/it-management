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
        'hu_the_lobby' => 'Hu The Lobby',
        'navision' => 'Navision',
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
    if (!esa_seed_system_access($conn)) {
        return false;
    }

    return esa_sync_from_employees_legacy($conn);
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

    $abilityFields = esa_ability_fields();
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

    $fields = esa_ability_fields();
    $fieldSql = implode(', ', array_map('esa_escape_identifier', array_keys($fields)));
    $sql = 'SELECT ' . $fieldSql . ' FROM `employee_system_access` WHERE `company_id`=' . $companyId
        . ' AND `employee_id`=' . $employeeId . ' LIMIT 1';
    $res = mysqli_query($conn, $sql);
    $row = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return $ids;
    }

    $accessByCode = esa_system_access_id_map($conn);
    foreach (array_keys($fields) as $field) {
        if ((int)($row[$field] ?? 0) === 1 && isset($accessByCode[$field])) {
            $mappedId = (int)$accessByCode[$field];
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
        $res = mysqli_query($conn, 'SELECT `id`, `code` FROM `system_access` WHERE `company_id`=' . $companyId . ' AND `id` IN (' . $idSql . ')');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $validAccessById[(int)$row['id']] = (string)$row['code'];
        }
    }

    $legacyFields = esa_ability_fields();
    $normalized = [];
    foreach (array_keys($legacyFields) as $field) {
        $normalized[$field] = 0;
    }
    foreach ($validAccessById as $code) {
        if (isset($normalized[$code])) {
            $normalized[$code] = 1;
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
    $fields = esa_ability_fields();

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
    $fields = esa_ability_fields();
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
