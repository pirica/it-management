<?php
/**
 *
 * Central implementation of the module CRUD flow.
 *
 * Features:
 * - Dynamic Schema Detection: Uses `DESCRIBE` and `information_schema` to build forms
 *   and tables without hardcoding columns.
 * - CSRF & Prepared Statements: Hardened against common web vulnerabilities.
 * - Foreign Key Integration: Automatically maps ID columns to their parent tables,
 *   heuristically selecting display labels (e.g., 'name', 'title').
 * - Inline Reference Addition: Allows users to create parent records (like a new
 *   category) directly from a child record's dropdown via JS.
 * - Bulk Operations: Supports multi-row deletion and table clearing.
 * - Global Search & Pagination: Scopes queries by `company_id` for multi-tenancy.
 */

$crud_table = 'idf_ports';
$crud_title = 'Idf Ports';
$crud_action = $crud_action ?? 'index';
?>
<?php
require '../../config/config.php';
require_once '../../includes/itm_crud_fk_label_search.php';

// Special logic for the system access module to ensure auxiliary tables exist.
if (($crud_table ?? '') === 'system_access') {
    require '../../includes/employee_system_access.php';
    esa_ensure_table($conn);
}

// Security: Prevent injection of arbitrary table names through wrapper variables.
if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

/**
 * Escapes a MySQL identifier (table/column name).
 */
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Fetches column definitions for the target table.
 */
function cr_table_columns($conn, $table) {
    $cols = [];
    if (!itm_is_safe_identifier($table)) return $cols;
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

/**
 * Detects foreign key relationships for the table to enable dropdown selection.
 */
function cr_fk_map($conn, $table) {
    $map = [];
    if (!itm_is_safe_identifier($table)) return $map;
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map[$row['COLUMN_NAME']] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $map;
}

/**
 * Retrieves the list of valid options for a foreign key dropdown.
 */
function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];
    $rows = [];

    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($col)) {
        return $rows;
    }

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    // Multi-tenant check: filter options by company if the parent table is scoped.
    $hasCompany = (in_array('company_id', $available, true) && $company_id > 0);
    $where = $hasCompany ? ' WHERE company_id=?' : '';

    if ($table === 'idf_positions') {
        $where = $hasCompany ? ' WHERE p.company_id=?' : '';
        $sql = "SELECT p.id AS id,
                       CONCAT_WS(' | ',
                           NULLIF(i.name, ''),
                           IF(p.position_no IS NULL, NULL, CONCAT('Pos ', p.position_no)),
                           NULLIF(p.device_name, '')
                       ) AS label
                FROM `idf_positions` p
                LEFT JOIN `idfs` i ON i.id = p.idf_id"
            . $where
            . ' ORDER BY p.position_no ASC';
    } else {
        $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    }

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($hasCompany) {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $rows;
}

/**
 * Heuristically finds the best column to use as a display label for a reference table.
 */
function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }

    $tableLabelCandidates = [
        'equipment' => ['hostname', 'name'],
        'switch_status' => ['status', 'name'],
        'cable_colors' => ['color_name', 'name'],
        'vlans' => ['vlan_name', 'name', 'vlan_number'],
        'idf_device_type' => ['idfdevicetype_name', 'name'],
        'idf_positions' => ['device_name', 'position_no'],
        'idf_ports' => ['label', 'port_no'],
    ];

    $candidates = $tableLabelCandidates[$table] ?? ['name', 'title', 'label', 'type', 'hostname', 'device_name', 'vlan_name', 'status', 'port_number', 'port_no', 'position_no', 'color_name', 'idfdevicetype_name', 'username', 'code', 'mode_name', 'hex_color', 'cable_type'];
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }
    if (!in_array($labelCol, $available, true)) {
        foreach ($available as $candidateField) {
            if (!in_array($candidateField, ['id', 'company_id', 'active', 'created_at', 'updated_at'], true)) {
                $labelCol = $candidateField;
                break;
            }
        }
    }
    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

/**
 * Resolves a foreign-key label with tenant scope first, then safe legacy fallback.
 */
function cr_fk_label_lookup($conn, $table, $idCol, $labelCol, $value, $companyId, $hasCompanyScope) {
    static $cache = [];

    $rawValue = trim((string)$value);
    if ($rawValue === '') {
        return null;
    }
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($idCol) || !itm_is_safe_identifier($labelCol)) {
        return null;
    }

    $cacheKey = $table . '|' . $idCol . '|' . $labelCol . '|' . $rawValue . '|' . (int)$companyId . '|' . (int)$hasCompanyScope;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $label = null;
    $lookup = function ($withCompany) use ($conn, $table, $idCol, $labelCol, $rawValue, $companyId, &$label) {
        $sql = 'SELECT ' . cr_escape_identifier($labelCol) . ' AS label FROM ' . cr_escape_identifier($table)
            . ' WHERE ' . cr_escape_identifier($idCol) . ' = ?';
        if ($withCompany && $companyId > 0) {
            $sql .= ' AND company_id = ?';
        }
        $sql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }
        if ($withCompany && $companyId > 0) {
            mysqli_stmt_bind_param($stmt, 'si', $rawValue, $companyId);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $rawValue);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res)) && isset($row['label']) && trim((string)$row['label']) !== '') {
            $label = (string)$row['label'];
        }
        mysqli_stmt_close($stmt);
    };

    $lookup($hasCompanyScope);
    if ($label === null && $hasCompanyScope && $companyId > 0) {
        $lookup(false);
    }

    $cache[$cacheKey] = $label;
    return $label;
}

/**
 * Creates a richer label for related IDF positions.
 */
function cr_idf_position_display_label($conn, $positionId, $companyId) {
    $positionId = (int)$positionId;
    if ($positionId <= 0) {
        return '';
    }

    static $cache = [];
    $cacheKey = $positionId . '|' . (int)$companyId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $sql = "SELECT p.position_no, p.device_name, i.name AS idf_name
            FROM `idf_positions` p
            LEFT JOIN `idfs` i ON i.id = p.idf_id
            WHERE p.id = ?";

    $query = function ($withCompany) use ($conn, $sql, $positionId, $companyId) {
        $localSql = $sql;
        if ($withCompany && $companyId > 0) {
            $localSql .= ' AND p.company_id = ?';
        }
        $localSql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conn, $localSql);
        if (!$stmt) {
            return null;
        }
        if ($withCompany && $companyId > 0) {
            mysqli_stmt_bind_param($stmt, 'ii', $positionId, $companyId);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $positionId);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    };

    $row = $query(true);
    if (!$row && $companyId > 0) {
        $row = $query(false);
    }

    if (!$row) {
        $label = 'Position #' . $positionId;
        $cache[$cacheKey] = $label;
        return $label;
    }

    $idfName = trim((string)($row['idf_name'] ?? ''));
    $positionNo = (int)($row['position_no'] ?? 0);
    $deviceName = trim((string)($row['device_name'] ?? ''));

    $parts = [];
    if ($idfName !== '') {
        $parts[] = $idfName;
    }
    if ($positionNo > 0) {
        $parts[] = 'Pos ' . $positionNo;
    }
    if ($deviceName !== '') {
        $parts[] = $deviceName;
    }

    $label = $parts ? implode(' | ', $parts) : ('Position #' . $positionId);
    $cache[$cacheKey] = $label;
    return $label;
}

/**
 * Removes internal/automatic columns from the manageable field set.
 */
function cr_manageable_columns($columns) {
    // Why: Keep audit meta available for view/hidden forms/POST; list hides via itm_crud_is_list_hidden_audit_field.
    return array_values(array_filter($columns, function ($c) {
        return ($c['Field'] ?? '') !== 'id';
    }));
}

/**
 * Converts DB column names to user-friendly titles.
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
    ];

    if (isset($map[$label])) { return $map[$label]; }
    if ($label === 'id') { return 'ID'; }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

/**
 * Privacy filter for employee-related modules.
 */
function cr_is_hidden_employee_field($field) {
    if (($GLOBALS['crud_table'] ?? '') !== 'employees') { return false; }
    $hidden = ['company_id', 'employee_id', 'location_id', 'phone', 'location', 'employee_code'];
    return in_array($field, $hidden, true);
}

/**
 * Formats database values for UI display (badges, icons, clickable links).
 */
function cr_render_cell_value($table, $field, $value) {
    if (function_exists('itm_crud_render_audit_cell_value')) {
        $auditHtml = itm_crud_render_audit_cell_value($GLOBALS['conn'] ?? null, (int)($GLOBALS['company_id'] ?? 0), $field, $value);
        if ($auditHtml !== null) {
            return $auditHtml;
        }
    }
$companyId = (int)($GLOBALS['company_id'] ?? 0);
    $rawValue = trim((string)($value ?? ''));

    if (($GLOBALS['crud_table'] ?? '') === 'idf_ports' && $field === 'position_id' && ctype_digit($rawValue)) {
        return sanitize(cr_idf_position_display_label($GLOBALS['conn'], (int)$rawValue, $companyId));
    }

    if (isset($GLOBALS['fkMap'][$field]) && $rawValue !== '') {
        $fk = $GLOBALS['fkMap'][$field];
        $fkTable = (string)$fk['REFERENCED_TABLE_NAME'];
        $fkCol = (string)$fk['REFERENCED_COLUMN_NAME'];
        $fkMeta = cr_fk_metadata($GLOBALS['conn'], $fkTable);
        $labelCol = (string)$fkMeta['label_col'];
        $hasCompanyScope = in_array('company_id', (array)$fkMeta['available'], true);
        $label = cr_fk_label_lookup($GLOBALS['conn'], $fkTable, $fkCol, $labelCol, $rawValue, $companyId, $hasCompanyScope);
        if ($label !== null) {
            return sanitize($label);
        }
    }

    // Status badges for the 'active' flag.
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    // Special boolean mapping for Employee Access module.
    if (($GLOBALS['crud_table'] ?? '') === 'employees') {
        $employeeBoolFields = ['network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms', 'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system', 'digital_rev', 'office_key_card'];
        if (in_array($field, $employeeBoolFields, true)) {
            return ((int)$value === 1) ? '✅' : '❌';
        }
    }

    $text = (string)($value ?? '');
    // Interactive email links with Outlook deep-link support.
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
        exit('Forbidden: invalid CSRF token.');
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

/**
 * Validates inputs against strict MySQL numeric ranges (e.g. tinyint vs bigint).
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

// DATA LOADING & INITIALIZATION
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$fieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_employee_field($col['Field']);
}));
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'employee_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'inventory_categories', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'suppliers', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'employees', 'departments'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    $fieldName = (string)($col['Field'] ?? '');
    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field($fieldName)) {
        return false;
    }
    if ($fieldName !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));

// Why: Search and list share visible columns; alias matches role/ui_configuration modules.
$displayFieldColumns = $uiColumns;

// Why: View shows create/update/delete audit stamps while list hides them.
$viewColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    $fieldName = (string)($col['Field'] ?? '');
    if ($fieldName !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();

// Handle Excel/CSV database import requests from table-tools.js.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string)$rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json');

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        if (!$hasCompany || $company_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires an active company.']);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.']);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $columnKeys = [];
        foreach ($headerRow as $headerValue) {
            $columnKeys[] = strtolower(preg_replace('/\s+/', ' ', $headerValue));
        }

        $fieldByLabel = [];
        foreach ($fieldColumns as $col) {
            $fieldName = (string)$col['Field'];
            $fieldByLabel[strtolower((string)cr_humanize_field($fieldName))] = $col;
            $fieldByLabel[strtolower(str_replace('_', ' ', $fieldName))] = $col;
        }
        $fieldByLabel['id'] = null;

        $importColumns = [];
        foreach ($columnKeys as $labelKey) {
            $importColumns[] = $fieldByLabel[$labelKey] ?? null;
        }

        $insertedRows = 0;
        $importErrors = [];
        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array)$importRows[$rowIndex];
            if (empty(array_filter($sourceRow, function ($v) { return trim((string)$v) !== ''; }))) {
                continue;
            }

            $rowData = [];
            foreach ($fieldColumns as $col) {
                $rowData[$col['Field']] = 'NULL';
            }

            foreach ($importColumns as $idx => $columnMeta) {
                if (!is_array($columnMeta)) {
                    continue;
                }

                $fieldName = (string)$columnMeta['Field'];
                $rawValue = trim((string)($sourceRow[$idx] ?? ''));
                if ($rawValue === '' || $rawValue === '—' || strcasecmp($rawValue, 'null') === 0) {
                    continue;
                }

                if ($fieldName === 'company_id' || $fieldName === 'id') {
                    continue;
                }

                $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$columnMeta['Type']);
                if ($isTinyInt) {
                    $normalizedBool = strtolower($rawValue);
                    if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                        $rowData[$fieldName] = '1';
                    } elseif (in_array($normalizedBool, ['0', 'inactive', 'no', 'false', 'off', '❌'], true)) {
                        $rowData[$fieldName] = '0';
                    }
                    continue;
                }

                if (isset($fkMap[$fieldName])) {
                    $fk = $fkMap[$fieldName];
                    $options = cr_fk_options($conn, $fk, (int)$company_id);
                    $resolvedId = 0;
                    foreach ($options as $option) {
                        if (strcasecmp((string)$option['label'], $rawValue) === 0) {
                            $resolvedId = (int)$option['id'];
                            break;
                        }
                    }
                    if ($resolvedId <= 0 && ctype_digit($rawValue)) {
                        $resolvedId = (int)$rawValue;
                    }
                    $rowData[$fieldName] = $resolvedId > 0 ? (string)$resolvedId : 'NULL';
                    continue;
                }

                if (preg_match('/int|decimal|float|double/', (string)$columnMeta['Type'])) {
                    $normalizedNumeric = null; $numericError = '';
                    if (cr_validate_numeric_value($rawValue, $columnMeta, $fieldName, $normalizedNumeric, $numericError)) {
                        $rowData[$fieldName] = $normalizedNumeric;
                    }
                    continue;
                }

                $rowData[$fieldName] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
            }

            if ($hasCompany) {
                $rowData['company_id'] = (string)(int)$company_id;
            }

            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = (string)$col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $rowData[$name] ?? 'NULL';
            }

            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $dbErrorCode = 0; $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                $insertedRows++;
            } elseif (count($importErrors) < 5) {
                $importErrors[] = 'row ' . ($rowIndex + 1) . ': ' . (string)$dbErrorMessage;
            }
        }

        $response = ['ok' => true, 'inserted' => $insertedRows];
        if (!empty($importErrors)) {
            $response['failed'] = count($importErrors);
            $response['errors'] = $importErrors;
            if ($insertedRows === 0) {
                $response['message'] = $importErrors[0];
            }
        }
        echo json_encode($response);
        exit;
    }
}


// HANDLE DELETIONS (via POST)
if ($crud_action === 'delete') {
    itm_require_crud_role_module_permission($conn, 'delete', $crud_table);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

    // Clear whole table (scoped by company)
    if ($bulkAction === 'clear_table') {
        $hasCompanyFilter = ($hasCompany && $company_id > 0);
        $where = $hasCompanyFilter ? ' WHERE company_id=?' : '';
        $deleteSql = function_exists('itm_crud_build_soft_delete_sql')
        ? itm_crud_build_soft_delete_sql($crud_table, $where, (int)($_SESSION['employee_id'] ?? 0))
        : ('DELETE FROM ' . cr_escape_identifier($crud_table) . $where);

        $stmt = mysqli_prepare($conn, $deleteSql);
        if ($stmt) {
            if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmt, 'i', $company_id); }
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    // Bulk delete selected IDs
    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) { $ids = []; }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) { $idList[] = $id; }
        }

        if (!empty($idList)) {
            $placeholders = implode(',', array_fill(0, count($idList), '?'));
            $hasCompanyFilter = ($hasCompany && $company_id > 0);
            $where = ' WHERE id IN (' . $placeholders . ')';
            if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
            $deleteSql = function_exists('itm_crud_build_soft_delete_sql')
        ? itm_crud_build_soft_delete_sql($crud_table, $where, (int)($_SESSION['employee_id'] ?? 0))
        : ('DELETE FROM ' . cr_escape_identifier($crud_table) . $where);

            $stmt = mysqli_prepare($conn, $deleteSql);
            if ($stmt) {
                $types = str_repeat('i', count($idList));
                if ($hasCompanyFilter) {
                    $types .= 'i';
                    $idList[] = (int)$company_id;
                }
                mysqli_stmt_bind_param($stmt, $types, ...$idList);
                if (!mysqli_stmt_execute($stmt)) {
                    $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $listUrl);
        exit;
    }

    // Single row delete
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $hasCompanyFilter = ($hasCompany && $company_id > 0);
        $where = ' WHERE id=?';
        if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
        $deleteSql = function_exists('itm_crud_build_soft_delete_sql')
        ? itm_crud_build_soft_delete_sql($crud_table, $where, (int)($_SESSION['employee_id'] ?? 0)) . ''
        : ('DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');

        $stmt = mysqli_prepare($conn, $deleteSql);
        if ($stmt) {
            if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id); }
            else { mysqli_stmt_bind_param($stmt, 'i', $id); }
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }
    }
    header('Location: ' . $listUrl);
    exit;
}

$errors = [];
if (!empty($_SESSION['crud_error'])) {
    $errors[] = (string)$_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
$data = [];
foreach ($fieldColumns as $col) { $data[$col['Field']] = ''; }

// HANDLE FETCH FOR EDIT/VIEW
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $hasCompanyFilter = ($hasCompany && $company_id > 0);
    $where = ' WHERE id=?';
    if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
    $sql = 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmt, 'ii', $editId, $company_id); }
        else { mysqli_stmt_bind_param($stmt, 'i', $editId); }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : [];
        mysqli_stmt_close($stmt);
    }

    if (!$data) { $errors[] = 'Record not found.'; }
}

// HANDLE FORM SUBMISSION (CREATE/EDIT)

// Handle sample data seeding for empty companies in list view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: ' . $listUrl);
        exit;
    }

    $where = ' WHERE company_id=' . (int)$company_id;
    $countSql = 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where;
    $countResult = mysqli_query($conn, $countSql);
    $existingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $existingRows = (int)($countRow['total_rows'] ?? 0);
    }

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: ' . $listUrl);
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, $crud_table, (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: ' . $listUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');

        // Logical Booleans
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        // Auto-assign company ownership
        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        // Handle Foreign Keys with inline "add new parent" support
        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = null;
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                // The JS bridge requested an inline insert of a missing reference.
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];

                $hasCompanyFilter = (in_array('company_id', $available, true) && $company_id > 0);
                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "=?";
                if ($hasCompanyFilter) { $findSql .= ' AND company_id=?'; }
                $findSql .= ' LIMIT 1';

                $stmtFind = mysqli_prepare($conn, $findSql);
                $existingId = null;
                if ($stmtFind) {
                    if ($hasCompanyFilter) { mysqli_stmt_bind_param($stmtFind, 'si', $newValueRaw, $company_id); }
                    else { mysqli_stmt_bind_param($stmtFind, 's', $newValueRaw); }
                    mysqli_stmt_execute($stmtFind);
                    $resEx = mysqli_stmt_get_result($stmtFind);
                    if ($resEx && mysqli_num_rows($resEx) > 0) {
                        $row = mysqli_fetch_assoc($resEx);
                        $existingId = (int)$row['id'];
                    }
                    mysqli_stmt_close($stmtFind);
                }

                if ($existingId !== null) {
                    $data[$name] = $existingId;
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $placeholders = ['?'];
                    $params = [$newValueRaw];
                    $types = 's';
                    if ($hasCompanyFilter) {
                        $insertFields[] = '`company_id`';
                        $placeholders[] = '?';
                        $params[] = (int)$company_id;
                        $types .= 'i';
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $placeholders) . ')';

                    $stmtIns = mysqli_prepare($conn, $insertSql);
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, $types, ...$params);
                        if (mysqli_stmt_execute($stmtIns)) {
                            $data[$name] = (int)mysqli_insert_id($conn);
                        } else {
                            $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtIns), mysqli_stmt_error($stmtIns));
                            $data[$name] = null;
                        }
                        mysqli_stmt_close($stmtIns);
                    }
                }
                continue;
            }
        }

        // Generic value processing and numeric validation
        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = null;
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null; $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = null;
            } else {
                $data[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = (string)$value;
        }
    }

    // PERSISTENCE (Prepared Statements)
    if (empty($errors)) {
        if ($crud_action === 'create' && function_exists('itm_crud_stamp_create_audit')) {
            $sqlValuesStamp = null;
            itm_crud_stamp_create_audit($data, $sqlValuesStamp);
        } elseif ($crud_action === 'edit' && function_exists('itm_crud_stamp_update_audit')) {
            $sqlValuesStamp = null;
            itm_crud_stamp_update_audit($data, $sqlValuesStamp, $data);
        }
        $fields = []; $placeholders = []; $params = []; $types = '';

        foreach ($fieldColumns as $col) {
            $name = $col['Field'];
            $fields[] = cr_escape_identifier($name);
            $placeholders[] = '?';
            $params[] = $data[$name];

            $colType = strtolower($col['Type']);
            if (str_contains($colType, 'int') || str_contains($colType, 'decimal') || str_contains($colType, 'float') || str_contains($colType, 'double')) {
                $types .= ($data[$name] === null) ? 's' : (str_contains($colType, 'int') ? 'i' : 'd');
            } else {
                $types .= 's';
            }
        }

        if ($crud_action === 'create') {
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: ' . $listUrl);
                    exit;
                }
                $errors[] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            }
        } else {
            $sets = [];
            foreach ($fields as $f) { $sets[] = $f . '=?'; }
            $hasCompanyFilter = ($hasCompany && $company_id > 0);
            $where = ' WHERE id=?';
            if ($hasCompanyFilter) { $where .= ' AND company_id=?'; }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $types .= 'i';
                $params[] = $editId;
                if ($hasCompanyFilter) {
                    $types .= 'i';
                    $params[] = $company_id;
                }
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: ' . $listUrl);
                    exit;
                }
                $errors[] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// FETCH LIST DATA (Pagination, Search, and Sort)
$where = '';
if ($hasCompany && $company_id > 0) { $where = ' WHERE company_id=' . (int)$company_id; }
if (function_exists('itm_crud_append_not_deleted_predicate')) {
    $where = itm_crud_append_not_deleted_predicate($where);
}

// SEARCH
$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($fieldColumns as $col) {
        $fieldName = (string)($col['Field'] ?? '');
        if ($fieldName === '') { continue; }
        $searchConditions[] = 'CAST(' . cr_escape_identifier($fieldName) . " AS CHAR) LIKE '{$searchEsc}'";
    }
    $itmFkSearchFields = [];
    foreach ($fieldColumns as $col) {
        $itmFkFieldName = (string)($col['Field'] ?? '');
        if ($itmFkFieldName !== '') {
            $itmFkSearchFields[] = $itmFkFieldName;
        }
    }
    if (!empty($fkMap)) {
        $itmFkLabelSearch = itm_crud_fk_label_search_conditions($conn, $crud_table, '', $fkMap, $itmFkSearchFields, (int)$company_id, $searchEsc);
        if (!empty($itmFkLabelSearch)) {
            $searchConditions = array_merge($searchConditions, $itmFkLabelSearch);
        }
    }
    if (!empty($searchConditions)) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $searchConditions) . ')';
    }
}

// SORTING
$sortableColumns = array_map(static function ($col) { return $col['Field']; }, $fieldColumns);
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) { $sort = 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

// PAGINATION
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) { $totalRows = (int)($countRow['total_rows'] ?? 0); }
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$showBulkActions = ($totalRows >= $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = itm_resolve_new_button_position($ui_config);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'IDF Ports';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <!-- LIST VIEW -->
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>

                <?php if ($showBulkActions): ?>
                <!-- TABLE MAINTENANCE -->
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        
                                                                                <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) { itm_crud_render_delete_hidden_audit_inputs(); } ?>
<button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- SEARCH BAR -->
                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

                <!-- DATA TABLE -->
                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                            <?php foreach ($uiColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php if ($f === 'comments' && trim((string)($row[$f] ?? '')) !== ''): ?>
                                            <span title="<?php echo sanitize((string)$row[$f]); ?>" data-itm-export-value="<?php echo sanitize((string)$row[$f]); ?>">💬</span>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) { itm_crud_render_delete_hidden_audit_inputs(); } ?>
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($fieldColumns) + ($showBulkActions ? 2 : 1); ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                <?php
                    if (function_exists('itm_crud_render_form_hidden_audit_inputs')) {
                        itm_crud_render_form_hidden_audit_inputs($data, (string)$crud_action);
                    }
                    ?>
<button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- PAGINATION -->
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <!-- EDIT/CREATE VIEW -->
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($uiColumns as $col): $name = $col['Field'];
                        if ($name === 'active') {
                            $val = $data[$name] ?? '';
                            $displayVal = ($val === null || (string)$val === '') ? '1' : (string)$val;
                            echo '<input type="hidden" name="active" value="' . sanitize($displayVal) . '">';
                            continue;
                        }
                        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
                        $isDate = str_starts_with($col['Type'], 'date');
                        $isDateTime = str_starts_with($col['Type'], 'datetime');
                        $isText = str_contains($col['Type'], 'text');
                        $val = $data[$name] ?? '';
                        $displayVal = ($val === null) ? '' : (string)$val;
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                <input type="number" name="company_id" value="<?php echo (int)$company_id; ?>" readonly>
                            <?php elseif ($isTinyInt): ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(cr_humanize_field($name)); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif (isset($fkMap[$name])): ?>
                                <?php
                                    $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <!-- VIEW (DETAILS) -->
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($viewColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <a href="index.php" class="btn">🔙</a>
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../js/theme.js"></script>
<script> window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>; </script>
<script src="../../js/select-add-option.js"></script>
<script>
/**
 * Helper to handle Outlook mailto links and dynamic checkbox visual indicators.
 */
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) { window.location.href = outlookHref; }
});
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) { indicator.textContent = event.target.checked ? '✅' : '❌'; }
});
</script>
</body>
</html>
