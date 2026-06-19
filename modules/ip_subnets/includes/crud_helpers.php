<?php
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Retrieves column metadata for the current table.
 */
function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

/**
 * Maps foreign key constraints for the current table using information_schema.
 */
function cr_fk_map($conn, $table) {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $map = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[$row['COLUMN_NAME']] = $row;
    }
    return $map;
}

/**
 * Fetches available options for a foreign key dropdown, scoped by company.
 */
function cr_fk_options($conn, $fk, $company_id, $columnName = '') {
    if (function_exists('itm_ipam_fk_options_override')) {
        $override = itm_ipam_fk_options_override($conn, $fk, (int)$company_id, (string)$columnName);
        if (is_array($override)) {
            return $override;
        }
    }

    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $where = '';
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }

    $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Resolves a human-readable label for a saved foreign-key value.
 * Uses tenant-scoped lookup first and falls back to global-by-id for legacy/shared rows.
 */
function cr_fk_label_by_id($conn, $fk, $company_id, $rawId, $columnName = '') {
    $id = (int)$rawId;
    if ($id <= 0) { return ''; }

    if (function_exists('itm_ipam_fk_label_by_id')) {
        $ipamLabel = itm_ipam_fk_label_by_id($conn, $fk, (int)$company_id, $id, (string)$columnName);
        if ($ipamLabel !== '') {
            return $ipamLabel;
        }
    }

    $fkTable = (string)$fk['REFERENCED_TABLE_NAME'];
    $fkCol = (string)$fk['REFERENCED_COLUMN_NAME'];
    $meta = cr_fk_metadata($conn, $fkTable);
    $labelCol = $meta['label_col'];
    $available = $meta['available'];

    $tableSql = cr_escape_identifier($fkTable);
    $idSql = cr_escape_identifier($fkCol);
    $labelSql = cr_escape_identifier($labelCol);

    if ($company_id > 0 && in_array('company_id', $available, true)) {
        $tenantSql = 'SELECT ' . $labelSql . ' AS label FROM ' . $tableSql
            . ' WHERE ' . $idSql . '=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
        $tenantRes = mysqli_query($conn, $tenantSql);
        $tenantRow = ($tenantRes) ? mysqli_fetch_assoc($tenantRes) : null;
        if (is_array($tenantRow) && isset($tenantRow['label'])) {
            return (string)$tenantRow['label'];
        }
    }

    $fallbackSql = 'SELECT ' . $labelSql . ' AS label FROM ' . $tableSql
        . ' WHERE ' . $idSql . '=' . $id . ' LIMIT 1';
    $fallbackRes = mysqli_query($conn, $fallbackSql);
    $fallbackRow = ($fallbackRes) ? mysqli_fetch_assoc($fallbackRes) : null;
    if (is_array($fallbackRow) && isset($fallbackRow['label'])) {
        return (string)$fallbackRow['label'];
    }

    return '';
}

/**
 * Ensures edit forms preserve persisted FK selections even if option lists are tenant-filtered.
 */

/**
 * Resolves a user display label for *_by fields when schema does not declare a foreign key.
 */
function cr_user_label_by_id($conn, $company_id, $rawId) {
    if ($rawId === null || $rawId === '') {
        return '';
    }

    $id = (int)$rawId;
    if ($id <= 0) {
        return '';
    }

    $whereCompany = ($company_id > 0)
        ? ' WHERE id=' . $id . ' AND company_id=' . (int)$company_id
        : ' WHERE id=' . $id;
    $sql = 'SELECT username, first_name, last_name FROM `employees`' . $whereCompany . ' LIMIT 1';
    $res = mysqli_query($conn, $sql);

    if ((!$res || mysqli_num_rows($res) === 0) && $company_id > 0) {
        $res = mysqli_query($conn, 'SELECT username, first_name, last_name FROM `employees` WHERE id=' . $id . ' LIMIT 1');
    }

    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string)($row['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
    }

    return '';
}


/**
 * Loads selectable users for *_by edit/create fields.
 */
function cr_user_options($conn, $company_id) {
    $where = ($company_id > 0)
        ? ' WHERE company_id=' . (int)$company_id
        : '';
    $sql = 'SELECT id, username, first_name, last_name FROM `employees`' . $where . ' ORDER BY first_name ASC, last_name ASC, username ASC';
    $res = mysqli_query($conn, $sql);
    $options = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        $username = trim((string)($row['username'] ?? ''));
        $label = $fullName !== '' ? $fullName : ($username !== '' ? $username : ('User #' . (int)$row['id']));
        $options[] = ['id' => (int)$row['id'], 'label' => $label];
    }
    return $options;
}

function cr_append_selected_user_option($conn, $company_id, $options, $selectedValue) {
    $selectedId = (int)$selectedValue;
    if ($selectedId <= 0) {
        return $options;
    }

    foreach ($options as $option) {
        if ((int)$option['id'] === $selectedId) {
            return $options;
        }
    }

    $label = cr_user_label_by_id($conn, $company_id, $selectedId);
    if ($label !== '') {
        $options[] = ['id' => $selectedId, 'label' => $label];
    }

    return $options;
}

function cr_append_selected_fk_option($conn, $fk, $company_id, $options, $selectedValue) {
    $selectedId = (int)$selectedValue;
    if ($selectedId <= 0) {
        return $options;
    }

    foreach ((array)$options as $opt) {
        if ((int)($opt['id'] ?? 0) === $selectedId) {
            return $options;
        }
    }

    $resolvedLabel = cr_fk_label_by_id($conn, $fk, (int)$company_id, $selectedId);
    if ($resolvedLabel !== '') {
        $options[] = ['id' => $selectedId, 'label' => $resolvedLabel];
    }

    return $options;
}

/**
 * Heuristically determines which column to use as a display label for a table.
 */
function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }
    foreach (['name', 'title', 'vlan_name', 'cidr', 'username', 'account_name', 'account_code', 'code', 'description', 'email', 'mode_name'] as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }
    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

/**
 * Filters out system/auto-managed columns from CRUD views.
 */
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

/**
 * Converts database column names to human-readable labels.
 */
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') { return ''; }

    $map = [
        'department_id' => 'Department Name',
        'office_key_card_department_id' => 'Office Key Card Department',
        'opera_username' => 'OPERA Username',
        'onq_ri' => 'OnQ R&I',
        'hu_the_lobby' => 'HU & The Lobby',
        'vlan_id' => 'VLAN',
        'cidr' => 'CIDR',
        'network_ip' => 'Network IP',
        'prefix_length' => 'Prefix Length',
        'gateway_ip' => 'Gateway IP',
        'dns1_ip' => 'DNS 1',
        'dns2_ip' => 'DNS 2',
        'dhcp_enabled' => 'DHCP Enabled',
    ];

    if (isset($map[$label])) { return $map[$label]; }
    if ($label === 'id') { return 'ID'; }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

/**
 * Special handling for employee privacy - hides internal IDs from general lists.
 */
function cr_is_hidden_employee_field($field) {
    if (($GLOBALS['crud_table'] ?? '') !== 'employees') { return false; }
    $hidden = ['company_id', 'employee_id', 'location_id', 'phone', 'location', 'employee_code'];
    return in_array($field, $hidden, true);
}

function cr_is_hidden_ipam_field($field) {
    $table = (string)($GLOBALS['crud_table'] ?? '');
    if ($table === 'ip_subnets' && in_array($field, ['network_ip', 'prefix_length'], true)) {
        return true;
    }
    return false;
}

/**
 * Renders cell values with appropriate formatting (badges for booleans, etc.)
 */
function cr_render_cell_value($table, $field, $value) {
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    if (($GLOBALS['crud_table'] ?? '') === 'employees') {
        $employeeBoolFields = ['network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms', 'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system', 'digital_rev', 'office_key_card'];
        if (in_array($field, $employeeBoolFields, true)) {
            return ((int)$value === 1) ? '✅' : '❌';
        }
    }

    if (isset($GLOBALS['fkMap'][$field])) {
        $resolvedLabel = cr_fk_label_by_id($GLOBALS['conn'], $GLOBALS['fkMap'][$field], (int)($GLOBALS['company_id'] ?? 0), $value, (string)$field);
        if ($resolvedLabel !== '') {
            return sanitize($resolvedLabel);
        }
    }

    if ($field === 'dhcp_enabled' || $field === 'is_gateway' || $field === 'is_dns' || $field === 'dhcp_managed') {
        return ((int)$value === 1) ? '✅' : '❌';
    }

    if (preg_match('/(_by|_by_user_id)$/', (string)$field)) {
        $userLabel = cr_user_label_by_id($GLOBALS['conn'], (int)($GLOBALS['company_id'] ?? 0), $value);
        if ($userLabel !== '') {
            return sanitize($userLabel);
        }
    }

    $text = (string)($value ?? '');
    // Special handling for clickable email links.
    if ($table === 'employees' && $field === 'email' && $text !== '') {
        $safeEmail = sanitize($text);
        $mailto = 'mailto:' . $text;
        $outlook = 'ms-outlook://compose?to=' . $text;
        return '<a href="' . sanitize($mailto) . '" data-outlook-link="1" data-outlook-href="' . sanitize($outlook) . '">' . $safeEmail . '</a>';
    }

    if (function_exists('itm_format_cell_scalar_display')) {
        $text = itm_format_cell_scalar_display($field, $text);
    }

    return sanitize($text);
}


function cr_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

/**
 * Validates numeric inputs against MySQL column type constraints (signed/unsigned, integer/decimal).
 */
function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = str_contains($type, 'unsigned');
    $raw = trim((string)$rawValue);

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type, $match)) {
        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid integer');
            return false;
        }

        $ranges = [
            'tinyint' => [-128, 127, 0, 255],
            'smallint' => [-32768, 32767, 0, 65535],
            'mediumint' => [-8388608, 8388607, 0, 16777215],
            'int' => [-2147483648, 2147483647, 0, 4294967295],
        ];
        $typeName = $match[1];

        if (isset($ranges[$typeName])) {
            [$signedMin, $signedMax, $unsignedMin, $unsignedMax] = $ranges[$typeName];
            $min = $isUnsigned ? $unsignedMin : $signedMin;
            $max = $isUnsigned ? $unsignedMax : $signedMax;
            if ($intVal < $min || $intVal > $max) {
                $error = cr_numeric_validation_error($fieldName, 'is out of range');
                return false;
            }
        } elseif ($typeName === 'bigint' && $isUnsigned && $intVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$intVal;
        return true;
    }

    if (preg_match('/^(decimal|float|double)\b/', $type)) {
        if (!is_numeric($raw)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid number');
            return false;
        }

        $floatVal = (float)$raw;
        if (!is_finite($floatVal)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a finite number');
            return false;
        }

        if ($isUnsigned && $floatVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$raw;
        return true;
    }

    $error = cr_numeric_validation_error($fieldName, 'has an unsupported numeric type');
    return false;
}

