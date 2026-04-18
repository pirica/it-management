<?php
$crud_table = 'patches_updates';
$crud_title = 'Patches Updates';
$crud_action = 'index';
?>
<?php
require '../../config/config.php';

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = $crud_title ?? ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

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
function cr_user_label_select($idCol, $available) {
    $parts = [];
    if (in_array('first_name', $available, true)) {
        $parts[] = "NULLIF(`first_name`, '')";
    }
    if (in_array('last_name', $available, true)) {
        $parts[] = "NULLIF(`last_name`, '')";
    }

    $idExpr = 'CAST(' . cr_escape_identifier($idCol) . ' AS CHAR)';

    if (!empty($parts)) {
        $fullExpr = 'TRIM(CONCAT_WS(\' \', ' . implode(', ', $parts) . '))';
        if (in_array('username', $available, true)) {
            return 'COALESCE(NULLIF(' . $fullExpr . ", ''), NULLIF(`username`, ''), " . $idExpr . ')';
        }
        return 'COALESCE(NULLIF(' . $fullExpr . ", ''), " . $idExpr . ')';
    }

    if (in_array('username', $available, true)) {
        return 'COALESCE(NULLIF(`username`, \'\'), ' . $idExpr . ')';
    }

    return $idExpr;
}


function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $where = '';
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }

    $selectExtras = '';
    if ($table === 'patches_updates_status') {
        $selectExtras = ', color';
    }

    $labelSelect = cr_escape_identifier($labelCol);
    if ($table === 'users') {
        $labelSelect = cr_user_label_select($col, $available);
    }

    $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . $labelSelect . " AS label" . $selectExtras . " FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    return $rows;
}

function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }
    if ($table === 'users' && in_array('last_name', $available, true)) {
        return [
            'label_col' => 'last_name',
            'available' => $available,
        ];
    }

    foreach (['name', 'title', 'username', 'code', 'mode_name'] as $candidate) {
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


function cr_fk_option_by_id($conn, $fk, $rawId, $company_id) {
    $id = (int)$rawId;
    if ($id <= 0) {
        return null;
    }

    $table = (string)$fk['REFERENCED_TABLE_NAME'];
    $col = (string)$fk['REFERENCED_COLUMN_NAME'];
    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];
    $selectExtras = '';
    if ($table === 'patches_updates_status') {
        $selectExtras = ', color';
    }

    $labelSelect = cr_escape_identifier($labelCol);
    if ($table === 'users') {
        $labelSelect = cr_user_label_select($col, $available);
    }

    $baseSql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . $labelSelect . ' AS label' . $selectExtras
        . ' FROM ' . cr_escape_identifier($table)
        . ' WHERE ' . cr_escape_identifier($col) . '=' . $id;

    $scopedSql = $baseSql;
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $scopedSql .= ' AND company_id=' . (int)$company_id;
    }
    $scopedSql .= ' LIMIT 1';

    $res = mysqli_query($conn, $scopedSql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }

    if (in_array('company_id', $available, true) && $company_id > 0) {
        $fallbackSql = $baseSql . ' LIMIT 1';
        $fallbackRes = mysqli_query($conn, $fallbackSql);
        if ($fallbackRes && ($fallbackRow = mysqli_fetch_assoc($fallbackRes))) {
            return $fallbackRow;
        }
    }

    return null;
}

function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') {
        return '';
    }

    $map = [
        'department_id' => 'Department Name',
        'office_key_card_department_id' => 'Office Key Card Department',
        'opera_username' => 'OPERA Username',
        'onq_ri' => 'OnQ R&I',
        'hu_the_lobby' => 'HU & The Lobby',
        'created_by' => 'Created by',
    ];

    if (isset($map[$label])) {
        return $map[$label];
    }

    if ($label === 'id') {
        return 'ID';
    }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

function cr_is_hidden_employee_field($field) {
    if (($GLOBALS['crud_table'] ?? '') !== 'employees') {
        return false;
    }

    $hidden = ['company_id', 'user_id', 'location_id', 'phone', 'location', 'employee_code'];
    return in_array($field, $hidden, true);
}

function cr_status_color_by_id($statusId) {
    $statusId = (int)$statusId;
    if ($statusId <= 0) {
        return '';
    }

    $sql = 'SELECT color FROM `patches_updates_status` WHERE id=' . $statusId;
    if ((int)($GLOBALS['company_id'] ?? 0) > 0) {
        $sql .= ' AND company_id=' . (int)$GLOBALS['company_id'];
    }
    $sql .= ' LIMIT 1';

    $res = mysqli_query($GLOBALS['conn'], $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;

    if (!$row && (int)($GLOBALS['company_id'] ?? 0) > 0) {
        $fallbackSql = 'SELECT color FROM `patches_updates_status` WHERE id=' . $statusId . ' LIMIT 1';
        $fallbackRes = mysqli_query($GLOBALS['conn'], $fallbackSql);
        $row = $fallbackRes ? mysqli_fetch_assoc($fallbackRes) : null;
    }

    $color = (string)($row['color'] ?? '');
    if (!preg_match('/^#[A-Fa-f0-9]{6}$/', $color)) {
        return '';
    }

    return strtoupper($color);
}

function cr_render_cell_value($table, $field, $value) {
    if (isset($GLOBALS['fkMap'][$field]) && (string)$value !== '') {
        $fkOption = cr_fk_option_by_id($GLOBALS['conn'], $GLOBALS['fkMap'][$field], $value, (int)($GLOBALS['company_id'] ?? 0));

        if ($field === 'status_id') {
            $color = cr_status_color_by_id((int)$value);
            if ($fkOption && isset($fkOption['color']) && preg_match('/^#[A-Fa-f0-9]{6}$/', (string)$fkOption['color'])) {
                $color = strtoupper((string)$fkOption['color']);
            }
            $square = $color !== ''
                ? '<span title="' . sanitize($color) . '" style="display:inline-block;width:10px;height:10px;border:1px solid #999;border-radius:2px;vertical-align:middle;margin-right:6px;background:' . sanitize($color) . ';"></span>'
                : '';
            $statusLabel = (string)($fkOption['label'] ?? '');
            return $square . sanitize($statusLabel !== '' ? $statusLabel : (string)$value);
        }

        if ($fkOption && isset($fkOption['label']) && (string)$fkOption['label'] !== '') {
            return sanitize((string)$fkOption['label']);
        }
    }

    if ($table === 'patches_updates' && $field === 'patches_updates_photos') {
        $photos = cr_parse_photo_filenames((string)$value);
        if (empty($photos)) {
            return '—';
        }

        $html = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
        foreach ($photos as $photo) {
            $photoUrl = cr_photo_public_path($photo);
            $html .= '<a href="' . sanitize($photoUrl) . '" target="_blank">'
                . '<img src="' . sanitize($photoUrl) . '" alt="Photo" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #d0d7de;">'
                . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    if (($GLOBALS['crud_table'] ?? '') === 'employees') {
        $employeeBoolFields = ['active', 'network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms', 'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system', 'digital_rev', 'office_key_card'];
        if (in_array($field, $employeeBoolFields, true)) {
            return ((int)$value === 1) ? '✅' : '❌';
        }
    }

    $text = (string)($value ?? '');
    if ($table === 'employees' && $field === 'email' && $text !== '') {
        $safeEmail = sanitize($text);
        $mailto = 'mailto:' . $text;
        $outlook = 'ms-outlook://compose?to=' . $text;
        return '<a href="' . sanitize($mailto) . '" data-outlook-link="1" data-outlook-href="' . sanitize($outlook) . '">' . $safeEmail . '</a>';
    }

    return sanitize($text);
}

function cr_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function cr_validate_csrf_token($token) {
    $requestToken = (string)$token;
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    return $requestToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $requestToken);
}

function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!cr_validate_csrf_token($token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

function cr_string_contains($haystack, $needle) {
    return $needle !== '' && strpos((string)$haystack, (string)$needle) !== false;
}

function cr_string_starts_with($haystack, $needle) {
    return $needle === '' || strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
}

function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = cr_string_contains($type, 'unsigned');
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

function cr_is_tinyint_column($column) {
    $type = strtolower((string)($column['Type'] ?? ''));
    return preg_match('/^tinyint\(\d+\)/', $type) === 1;
}


function cr_parse_photo_filenames($rawValue) {
    if (!is_string($rawValue) || trim($rawValue) === '') {
        return [];
    }

    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $decoded), static function ($value) {
        return $value !== '';
    }));
}

function cr_photo_public_path(string $filename): string {
    return TICKET_UPLOAD_URL . rawurlencode($filename);
}

function cr_detect_upload_mime_type(string $tmpName): string {
    if ($tmpName === '' || !is_file($tmpName)) {
        return '';
    }

    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }

    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
        return strtolower((string)$imageInfo['mime']);
    }

    return '';
}

function cr_resolve_upload_record_id(mysqli $conn, string $table, bool $isEdit, int $editId): int {
    if ($isEdit && $editId > 0) {
        return $editId;
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $statusResult = mysqli_query($conn, "SHOW TABLE STATUS LIKE '{$tableEsc}'");
    if ($statusResult) {
        $statusRow = mysqli_fetch_assoc($statusResult);
        if (is_array($statusRow) && isset($statusRow['Auto_increment'])) {
            return (int)$statusRow['Auto_increment'];
        }
    }

    return 0;
}

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


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'user_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'inventory_categories', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'patches_updates', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'users', 'departments'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    if (($col['Field'] ?? '') !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string)$rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json');

        $_POST['csrf_token'] = (string)($jsonBody['csrf_token'] ?? '');
        if (!cr_validate_csrf_token($_POST['csrf_token'])) {
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
        foreach ($uiColumns as $col) {
            $fieldByLabel[strtolower((string)cr_humanize_field($col['Field']))] = $col;
        }

        $importColumns = [];
        foreach ($columnKeys as $labelKey) {
            $importColumns[] = $fieldByLabel[$labelKey] ?? null;
        }

        $insertedRows = 0;
        $failedRows = 0;
        $firstInsertError = '';
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
                if ($rawValue === '' || $rawValue === '—') {
                    continue;
                }

                if ($fieldName === 'company_id') {
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
                    $normalizedNumeric = null;
                    $numericError = '';
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
            if (array_key_exists('created_by', $rowData) && $rowData['created_by'] === 'NULL') {
                $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
                if ($sessionUserId > 0) {
                    $rowData['created_by'] = (string)$sessionUserId;
                }
            }

            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = (string)$col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $rowData[$name] ?? 'NULL';
            }

            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                $insertedRows++;
                continue;
            }

            $failedRows++;
            if ($firstInsertError === '') {
                $friendlyError = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                $firstInsertError = 'Row ' . ($rowIndex + 1) . ': ' . $friendlyError;
            }
        }

        if ($insertedRows <= 0) {
            http_response_code(400);
            $importError = $firstInsertError !== ''
                ? $firstInsertError
                : 'Import failed: no valid rows were saved.';
            echo json_encode(['ok' => false, 'error' => $importError]);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'inserted' => $insertedRows,
            'failed' => $failedRows,
            'warning' => $firstInsertError,
        ]);
        exit;
    }
}

if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method not allowed.';
        exit;
    }

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
    $dbErrorCode = 0;
    $dbErrorMessage = '';

    if ($bulkAction === 'clear_table') {
        $where = '';
        if ($hasCompany && $company_id > 0) {
            $where = ' WHERE company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $idList[$id] = $id;
            }
        }

        if (!empty($idList)) {
            $where = ' WHERE id IN (' . implode(',', array_values($idList)) . ')';
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
            if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
            }
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $listUrl);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $where = ' WHERE id=' . $id;
        if ($hasCompany && $company_id > 0) {
            $where .= ' AND company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
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
foreach ($fieldColumns as $col) {
    $data[$col['Field']] = '';
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patchPhotoFilenamesToDeleteAfterSave = [];

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) {
        $where .= ' AND company_id=' . (int)$company_id;
    }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) {
        $errors[] = 'Record not found.';
    }
}


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

    $isPatchEdit = ($crud_action === 'edit' && $editId > 0);
    $patchPhotoFilenames = cr_parse_photo_filenames((string)($data['patches_updates_photos'] ?? ''));

    if ($isPatchEdit && isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1') {
        $patchPhotoFilenamesToDeleteAfterSave = $patchPhotoFilenames;
        $patchPhotoFilenames = [];
    }

    if (isset($_FILES['photo']) && is_array($_FILES['photo']['error'])) {
        $uploadRecordId = cr_resolve_upload_record_id($conn, $crud_table, $isPatchEdit, $editId);
        foreach ($_FILES['photo']['error'] as $index => $fileError) {
            if ($fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = 'Photo upload failed.';
                break;
            }

            $tmpName = (string)($_FILES['photo']['tmp_name'][$index] ?? '');
            $name = (string)($_FILES['photo']['name'][$index] ?? '');
            if (!in_array(cr_detect_upload_mime_type($tmpName), ALLOWED_TYPES, true)) {
                $errors[] = 'Only image files are allowed for photo uploads.';
                break;
            }

            $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = 'jpg';
            }
            $photoFilename = 'patch_update_' . $uploadRecordId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($tmpName, TICKET_UPLOAD_PATH . $photoFilename)) {
                $patchPhotoFilenames[] = $photoFilename;
            }
        }
    }

    $patchPhotoSqlValue = empty($patchPhotoFilenames)
        ? 'NULL'
        : "'" . mysqli_real_escape_string($conn, json_encode(array_values($patchPhotoFilenames), JSON_UNESCAPED_SLASHES)) . "'";

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = cr_is_tinyint_column($col);
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        if ($name === 'patches_updates_photos') {
            $data[$name] = $patchPhotoSqlValue;
            continue;
        }

        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int)$company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string)(int)$row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $data[$name] = (string)(int)mysqli_insert_id($conn);
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        $value = $_POST[$name] ?? null;
        if ($name === 'created_by' && ($GLOBALS['crud_table'] ?? '') === 'patches_updates' && $crud_action === 'create' && ($value === '' || $value === null)) {
            $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
            $data[$name] = $sessionUserId > 0 ? (string)$sessionUserId : 'NULL';
            continue;
        }

        if ($value === '' || $value === null) {
            $data[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $data[$name];
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . $data[$name];
            }
            $where = ' WHERE id=' . $editId;
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
        }

        $dbErrorCode = 0;
        $dbErrorMessage = '';
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            foreach ($patchPhotoFilenamesToDeleteAfterSave as $photoToDelete) {
                @unlink(TICKET_UPLOAD_PATH . $photoToDelete);
            }

            header('Location: ' . $listUrl);
            exit;
        }
        $errors[] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}

$where = '';
if ($hasCompany && $company_id > 0) {
    $where = ' WHERE company_id=' . (int)$company_id;
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (cr_string_contains($searchRaw, '%') || cr_string_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($displayFieldColumns as $col) {
        $fieldName = (string)($col['Field'] ?? '');
        if ($fieldName === '') {
            continue;
        }
        $searchConditions[] = 'CAST(' . cr_escape_identifier($fieldName) . " AS CHAR) LIKE '{$searchEsc}'";
    }

    if (!empty($searchConditions)) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $searchConditions) . ')';
    }
}

$sortableColumns = array_map(static function ($col) {
    return $col['Field'];
}, $uiColumns);

$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
    $totalRows = (int)($countRow['total_rows'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error"><?php echo sanitize(implode(' ', $errors)); ?></div>
            <?php endif; ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                </form>
            </div>


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
                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                            <?php foreach ($uiColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?>
                                            <?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php if ($f === 'comments' && trim((string)($row[$f] ?? '')) !== ''): ?>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($fieldColumns) + 2; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" enctype="multipart/form-data" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($fieldColumns as $col): $name = $col['Field'];
                        $isTinyInt = cr_is_tinyint_column($col);
                        $isDate = cr_string_starts_with($col['Type'], 'date');
                        $isDateTime = cr_string_starts_with($col['Type'], 'datetime');
                        $isText = cr_string_contains($col['Type'], 'text');
                        $val = $data[$name] ?? '';
                        $displayVal = ($val === 'NULL') ? '' : (string)$val;
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                <input type="hidden" name="company_id" value="<?php echo (int)$company_id; ?>">
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
                                    if ($displayVal !== '' && !in_array($displayVal, ['__new__', '__add_new__'], true)) {
                                        $hasSelectedOption = false;
                                        foreach ($opts as $opt) {
                                            if ((string)($opt['id'] ?? '') === (string)$displayVal) {
                                                $hasSelectedOption = true;
                                                break;
                                            }
                                        }
                                        if (!$hasSelectedOption) {
                                            $selectedOption = cr_fk_option_by_id($conn, $fkMap[$name], $displayVal, (int)$company_id);
                                            if ($selectedOption) {
                                                $opts[] = $selectedOption;
                                            }
                                        }
                                    }
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
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?> <?php echo ($name === 'status_id' && isset($opt['color'])) ? ('data-status-color="' . sanitize((string)$opt['color']) . '"') : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                                <?php if ($name === 'status_id'): ?>
                                    <?php $statusColor = cr_status_color_by_id((int)$displayVal); ?>
                                    <span data-status-color-preview style="display:inline-block;width:12px;height:12px;border:1px solid #999;border-radius:2px;vertical-align:middle;margin-left:8px;background:<?php echo sanitize($statusColor !== '' ? $statusColor : '#FFFFFF'); ?>;"></span>
                                <?php endif; ?>
                            <?php elseif ($name === 'patches_updates_photos'): ?>
                                <?php $existingPatchPhotos = cr_parse_photo_filenames((string)($data['patches_updates_photos'] ?? '')); ?>
                                <input type="file" name="photo[]" accept="image/*" multiple>
                                <div class="form-hint" style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <span><?php echo count($existingPatchPhotos); ?> photos current.</span>
                                    <?php if (!empty($existingPatchPhotos)): ?>
                                        <label class="itm-checkbox-control" style="margin:0;">
                                            <input type="checkbox" name="delete_photo" value="1">
                                            <span>Remove current photos</span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($existingPatchPhotos)): ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                                        <?php foreach ($existingPatchPhotos as $patchPhoto): ?>
                                            <a href="<?php echo sanitize(cr_photo_public_path($patchPhoto)); ?>" target="_blank">
                                                <img src="<?php echo sanitize(cr_photo_public_path($patchPhoto)); ?>" alt="Photo" style="width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid #d0d7de;">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;"><a href="index.php" class="btn">🔙</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    const selectAllRows = document.getElementById('select-all-rows') || document.getElementById('select-all-departments');
    const bulkDeleteForm = document.querySelector('form[id="bulk-delete-form"], form[id="department-bulk-form"]');
    const toggleButton = bulkDeleteForm ? bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
    const rowCheckboxes = bulkDeleteForm ? document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]') : [];
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) {
            selectAllHeaderCell.style.display = visible ? '' : 'none';
        }
        deleteCells.forEach(function (cell) {
            cell.style.display = visible ? '' : 'none';
        });
    }

    if (selectAllRows) {
        selectAllRows.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllRows.checked;
            });
        });
    }

    if (bulkDeleteForm && toggleButton) {
        setSelectionVisibility(false);

        bulkDeleteForm.addEventListener('submit', function (event) {
            if (event.submitter !== toggleButton) {
                return;
            }

            if (!selectionMode) {
                event.preventDefault();
                selectionMode = true;
                setSelectionVisibility(true);
                toggleButton.textContent = 'Delete Selected';
                return;
            }

            const anySelected = Array.from(rowCheckboxes).some(function (checkbox) { return checkbox.checked; });
            if (!anySelected) {
                event.preventDefault();
                alert('Please select at least one record to delete.');
                return;
            }

            if (!confirm('Delete selected records?')) {
                event.preventDefault();
            }
        });
    }
})();
</script>
<script src="../../js/theme.js"></script>
<script>
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="../../js/select-add-option.js"></script>

<script>
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) {
        window.location.href = outlookHref;
    }
});

document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) {
        indicator.textContent = event.target.checked ? '✅' : '❌';
    }
});
</script>


<script>
document.querySelectorAll('select[name="status_id"]').forEach((selectEl) => {
    const preview = selectEl.parentElement.querySelector('[data-status-color-preview]');
    if (!preview) {
        return;
    }

    const syncColor = () => {
        const selected = selectEl.options[selectEl.selectedIndex];
        const color = selected ? (selected.getAttribute('data-status-color') || '#FFFFFF') : '#FFFFFF';
        preview.style.background = /^#[A-Fa-f0-9]{6}$/.test(color) ? color : '#FFFFFF';
    };

    selectEl.addEventListener('change', syncColor);
    syncColor();
});
</script>
</body>
</html>
