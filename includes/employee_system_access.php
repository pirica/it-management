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

function esa_current_company_id() {
    return isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
}

function esa_ensure_table($conn) {
    $legacySql = "CREATE TABLE IF NOT EXISTS `employee_system_access` (
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

    if (!mysqli_query($conn, $legacySql)) {
        return false;
    }

    $catalogSql = "CREATE TABLE IF NOT EXISTS `system_access` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `code` VARCHAR(100) NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY `uq_system_access_company_code` (`company_id`, `code`),
        UNIQUE KEY `uq_system_access_company_name` (`company_id`, `name`),
        KEY `idx_system_access_company` (`company_id`),
        CONSTRAINT `fk_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conn, $catalogSql)) {
        return false;
    }

    $relationSql = "CREATE TABLE IF NOT EXISTS `employee_system_access_relations` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `system_access_id` INT NOT NULL,
        `granted` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_esa_rel_company_employee_system` (`company_id`, `employee_id`, `system_access_id`),
        KEY `idx_esa_rel_company_employee` (`company_id`, `employee_id`),
        KEY `idx_esa_rel_system_access` (`system_access_id`),
        CONSTRAINT `fk_esa_rel_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_esa_rel_system_access` FOREIGN KEY (`system_access_id`) REFERENCES `system_access` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conn, $relationSql)) {
        return false;
    }

    if (!esa_seed_system_access($conn)) {
        return false;
    }

    if (!esa_sync_from_employees_legacy($conn)) {
        return false;
    }

    return esa_sync_relations_from_legacy($conn);
}

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

function esa_sync_relations_from_legacy($conn) {
    $abilityFields = esa_ability_fields();
    $accessMap = esa_system_access_id_map($conn);
    if (!$accessMap) {
        return false;
    }

    $selectParts = [];
    foreach (array_keys($abilityFields) as $field) {
        if (!isset($accessMap[$field])) {
            continue;
        }
        $fieldEsc = esa_escape_identifier($field);
        $selectParts[] = 'SELECT esa.`company_id`, esa.`employee_id`, ' . (int)$accessMap[$field] . ' AS `system_access_id` FROM `employee_system_access` esa WHERE esa.' . $fieldEsc . '=1';
    }

    if (!$selectParts) {
        return true;
    }

    $sql = 'INSERT INTO `employee_system_access_relations` (`company_id`, `employee_id`, `system_access_id`) '
        . implode(' UNION ALL ', $selectParts)
        . ' ON DUPLICATE KEY UPDATE `granted`=1';

    return mysqli_query($conn, $sql) !== false;
}

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

function esa_get_employee_access_ids($conn, $companyId, $employeeId) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $ids = [];

    $sql = 'SELECT `system_access_id` FROM `employee_system_access_relations` WHERE `company_id`=' . $companyId
        . ' AND `employee_id`=' . $employeeId . ' AND `granted`=1';
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $id = (int)($row['system_access_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function esa_save_employee_access_ids($conn, $companyId, $employeeId, $systemAccessIds) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $systemAccessIds = is_array($systemAccessIds) ? $systemAccessIds : [];

    $cleanIds = [];
    foreach ($systemAccessIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $cleanIds[$id] = true;
        }
    }
    $cleanIds = array_keys($cleanIds);

    $validAccessById = [];
    if (!empty($cleanIds)) {
        $idSql = implode(',', array_map('intval', $cleanIds));
        $res = mysqli_query($conn, 'SELECT `id`, `code` FROM `system_access` WHERE `company_id`=' . $companyId . ' AND `id` IN (' . $idSql . ')');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $validAccessById[(int)$row['id']] = (string)$row['code'];
        }
    }

    if (!mysqli_begin_transaction($conn)) {
        return false;
    }

    $ok = true;
    if (!mysqli_query($conn, 'DELETE FROM `employee_system_access_relations` WHERE `company_id`=' . $companyId . ' AND `employee_id`=' . $employeeId)) {
        $ok = false;
    }

    if ($ok) {
        foreach ($validAccessById as $accessId => $code) {
            $insertSql = 'INSERT INTO `employee_system_access_relations` (`company_id`, `employee_id`, `system_access_id`, `granted`) VALUES ('
                . $companyId . ', ' . $employeeId . ', ' . (int)$accessId . ', 1)';
            if (!mysqli_query($conn, $insertSql)) {
                $ok = false;
                break;
            }
        }
    }

    if ($ok) {
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

        if (!mysqli_query($conn, $legacySql)) {
            $ok = false;
        }
    }

    if ($ok) {
        return mysqli_commit($conn);
    }

    mysqli_rollback($conn);
    return false;
}

function esa_get_employee_access($conn, $companyId, $employeeId) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $fields = esa_ability_fields();

    $defaults = [];
    foreach (array_keys($fields) as $field) {
        $defaults[$field] = 0;
    }

    $sql = 'SELECT sa.`code` '
        . 'FROM `employee_system_access_relations` esar '
        . 'INNER JOIN `system_access` sa ON sa.`id` = esar.`system_access_id` '
        . 'WHERE esar.`company_id`=' . $companyId . ' AND esar.`employee_id`=' . $employeeId . ' AND esar.`granted`=1';
    $res = mysqli_query($conn, $sql);
    $foundRelations = false;

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $code = (string)($row['code'] ?? '');
        if (isset($defaults[$code])) {
            $defaults[$code] = 1;
            $foundRelations = true;
        }
    }

    if ($foundRelations) {
        return $defaults;
    }

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

function esa_save_employee_access($conn, $companyId, $employeeId, $payload) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $fields = esa_ability_fields();
    $normalized = [];

    foreach (array_keys($fields) as $field) {
        $normalized[$field] = !empty($payload[$field]) ? 1 : 0;
    }

    $accessMap = esa_system_access_id_map($conn);
    if (!$accessMap) {
        return false;
    }

    if (!mysqli_begin_transaction($conn)) {
        return false;
    }

    $ok = true;
    $deleteSql = 'DELETE FROM `employee_system_access_relations` WHERE `company_id`=' . $companyId . ' AND `employee_id`=' . $employeeId;
    if (!mysqli_query($conn, $deleteSql)) {
        $ok = false;
    }

    if ($ok) {
        foreach ($normalized as $field => $value) {
            if ((int)$value !== 1 || !isset($accessMap[$field])) {
                continue;
            }
            $insertSql = 'INSERT INTO `employee_system_access_relations` (`company_id`, `employee_id`, `system_access_id`, `granted`) VALUES ('
                . $companyId . ', ' . $employeeId . ', ' . (int)$accessMap[$field] . ', 1)';
            if (!mysqli_query($conn, $insertSql)) {
                $ok = false;
                break;
            }
        }
    }

    if ($ok) {
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

        if (!mysqli_query($conn, $legacySql)) {
            $ok = false;
        }
    }

    if ($ok) {
        return mysqli_commit($conn);
    }

    mysqli_rollback($conn);
    return false;
}
