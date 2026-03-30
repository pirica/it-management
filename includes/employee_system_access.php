<?php

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

function esa_escape_identifier($name) {
    return '`' . str_replace('`', '``', (string)$name) . '`';
}

function esa_ensure_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `employee_system_access` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `network_access` TINYINT(1) NOT NULL DEFAULT 0,
        `micros_emc` TINYINT(1) NOT NULL DEFAULT 0,
        `opera_username` TINYINT(1) NOT NULL DEFAULT 0,
        `micros_card` TINYINT(1) NOT NULL DEFAULT 0,
        `pms_id` TINYINT(1) NOT NULL DEFAULT 0,
        `synergy_mms` TINYINT(1) NOT NULL DEFAULT 0,
        `hu_the_lobby` TINYINT(1) NOT NULL DEFAULT 0,
        `navision` TINYINT(1) NOT NULL DEFAULT 0,
        `onq_ri` TINYINT(1) NOT NULL DEFAULT 0,
        `birchstreet` TINYINT(1) NOT NULL DEFAULT 0,
        `delphi` TINYINT(1) NOT NULL DEFAULT 0,
        `omina` TINYINT(1) NOT NULL DEFAULT 0,
        `vingcard_system` TINYINT(1) NOT NULL DEFAULT 0,
        `digital_rev` TINYINT(1) NOT NULL DEFAULT 0,
        `office_key_card` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_employee_system_access_company_employee` (`company_id`, `employee_id`),
        KEY `idx_employee_system_access_company` (`company_id`),
        CONSTRAINT `fk_employee_system_access_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conn, $sql)) {
        return false;
    }

    return esa_sync_from_employees_legacy($conn);
}

function esa_sync_from_employees_legacy($conn) {
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

    $sql = 'INSERT INTO `employee_system_access` (`company_id`, `employee_id`, ' . implode(', ', array_map('esa_escape_identifier', array_keys($abilityFields))) . ') '
        . 'SELECT e.`company_id`, e.`id`, ' . implode(', ', $selectParts) . ' '
        . 'FROM `employees` e '
        . 'LEFT JOIN `employee_system_access` esa ON esa.`company_id` = e.`company_id` AND esa.`employee_id` = e.`id` '
        . 'WHERE esa.`id` IS NULL';

    return mysqli_query($conn, $sql) !== false;
}

function esa_get_employee_access($conn, $companyId, $employeeId) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $fields = esa_ability_fields();

    $defaults = [];
    foreach (array_keys($fields) as $field) {
        $defaults[$field] = 0;
    }

    $sql = 'SELECT * FROM `employee_system_access` WHERE `company_id`=' . $companyId . ' AND `employee_id`=' . $employeeId . ' LIMIT 1';
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) === 1) {
        $row = mysqli_fetch_assoc($res);
        foreach (array_keys($fields) as $field) {
            $defaults[$field] = ((int)($row[$field] ?? 0) === 1) ? 1 : 0;
        }
    }

    return $defaults;
}

function esa_save_employee_access($conn, $companyId, $employeeId, $payload) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $fields = esa_ability_fields();
    $normalized = [];

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
    $sql = 'INSERT INTO `employee_system_access` (' . $insertCols . ') VALUES (' . $insertVals . ') '
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    return mysqli_query($conn, $sql) !== false;
}
