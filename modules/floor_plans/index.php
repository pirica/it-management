<?php
function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}
/**
 * Floor Plans Module - Index
 *
 * Gallery-first index for image/PDF floor plans with nested folders and tags.
 * list_all.php keeps the standard metadata table for export/import compliance.
 * Configures $crud_table and $crud_title before execution to scope the logic.
 */

$crud_table = 'floor_plans';
$crud_title = 'Floor Plans';
$crud_action = $crud_action ?? 'index';
?>
<?php
require '../../config/config.php';
require_once '../../includes/itm_crud_fk_label_search.php';
require __DIR__ . '/gallery_helpers.php';

if (isset($_GET['ajax_action']) && (string)$_GET['ajax_action'] === 'create_share_session') {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();
    require_once __DIR__ . '/floor_plans_share_helpers.php';
    $planId = (int)($_POST['id'] ?? 0);
    $ownerUsername = (string)($_SESSION['username'] ?? '');
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $result = floor_plans_share_create_session($conn, $planId, (int)$company_id, $employeeId, $ownerUsername);
    if (!$result['ok'] || empty($result['session'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Unable to create share session.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $session = $result['session'];
    echo json_encode([
        'ok' => true,
        'share_code' => (string)$session['share_code'],
        'join_url' => floor_plans_share_build_join_url((string)$session['access_token']),
        'expires_at' => (string)$session['expires_at'],
        'ttl_seconds' => itm_qr_share_session_ttl_seconds(),
        'has_images' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Check for valid table configuration to prevent injection via $crud_table clones.
if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

/**
 * Escapes database identifiers (table/column names).
 */
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
function cr_fk_label_by_id($conn, $fk, $company_id, $rawId) {
    $id = (int)$rawId;
    if ($id <= 0) { return ''; }

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
    return itm_fk_append_selected_option(
        $conn,
        $fk,
        (int)$company_id,
        (array)$options,
        $selectedValue,
        static function ($conn, $fk, $companyId, $resolvedId) {
            return cr_fk_label_by_id($conn, $fk, (int)$companyId, (int)$resolvedId);
        }
    );
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
    foreach (['name', 'title', 'username', 'account_name', 'account_code', 'code', 'description', 'email', 'mode_name'] as $candidate) {
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
    // Why: Keep audit meta available for view/hidden forms/POST; list hides via itm_crud_is_list_hidden_audit_field.
    return array_values(array_filter($columns, function ($c) {
        return ($c['Field'] ?? '') !== 'id';
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
        'folder_id' => 'Folder',
        'it_location_id' => fp_it_location_link_label(),
        'display_name' => 'File',
        'stored_filename' => 'Stored File',
        'mime_type' => 'MIME Type',
        'file_ext' => 'Type',
        'file_size' => 'Size',
        'created_by' => 'Uploaded By',
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

/**
 * Renders cell values with appropriate formatting (badges for booleans, etc.)
 */
function cr_floor_plan_hidden_fields(): array {
    return ['company_id', 'stored_filename', 'active', 'deleted_by', 'deleted_at', 'created_by', 'created_at', 'updated_by', 'updated_at'];
}

function cr_is_hidden_floor_plan_field($field) {
    if (($GLOBALS['crud_table'] ?? '') !== 'floor_plans') {
        return false;
    }
    return in_array((string)$field, cr_floor_plan_hidden_fields(), true);
}

function cr_render_cell_value($table, $field, $value) {
    if (function_exists('itm_crud_render_audit_cell_value')) {
        $auditHtml = itm_crud_render_audit_cell_value($GLOBALS['conn'] ?? null, (int)($GLOBALS['company_id'] ?? 0), $field, $value);
        if ($auditHtml !== null) {
            return $auditHtml;
        }
    }
if ($table === 'floor_plans') {
        if ($field === 'file_size') {
            return sanitize(fp_format_file_size((int)$value));
        }
        if ($field === 'mime_type') {
            return sanitize((string)$value);
        }
        if ($field === 'display_name' && (string)$value !== '') {
            return sanitize((string)$value);
        }
        if ($field === 'file_ext' && (string)$value !== '') {
            return sanitize(strtoupper((string)$value));
        }
        if ($field === 'it_location_id') {
            $locationLabel = fp_it_location_label_by_id($GLOBALS['conn'], (int)($GLOBALS['company_id'] ?? 0), $value);
            if ($locationLabel !== '') {
                return sanitize($locationLabel);
            }
        }
    }

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
        $resolvedLabel = cr_fk_label_by_id($GLOBALS['conn'], $GLOBALS['fkMap'][$field], (int)($GLOBALS['company_id'] ?? 0), $value);
        if ($resolvedLabel !== '') {
            return sanitize($resolvedLabel);
        }
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

// INITIALIZATION
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
// Detect company scoping from schema even when company_id is hidden from UI field lists.
$hasCompany = false;
foreach ($columns as $c) {
    if (($c['Field'] ?? '') === 'company_id') {
        $hasCompany = true;
        break;
    }
}
// floor_plans is always tenant-scoped; DESCRIBE fails when tables are not migrated yet.
if ($crud_table === 'floor_plans') {
    $hasCompany = true;
}
$fpSchemaReady = fp_floor_plan_schema_ready($conn);
$fpGalleryAccessError = fp_gallery_access_error($conn);
$fieldColumns = cr_manageable_columns($columns);
// Exclude employee-specific sensitive fields.
$fieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_employee_field($col['Field']) && !cr_is_hidden_floor_plan_field($col['Field']);
}));


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'employee_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'budget_categories', 'floor_plans', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'suppliers', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'employees', 'departments'];
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
if ($crud_table === 'floor_plans' && $crud_action === 'list_all') {
    $fpListFields = ['display_name', 'folder_id', 'file_ext', 'file_size', 'it_location_id', 'mime_type', 'active'];
    $uiColumns = array_values(array_filter($uiColumns, static function ($col) use ($fpListFields) {
        return in_array((string)($col['Field'] ?? ''), $fpListFields, true);
    }));
}

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
$fpDeleteReturn = (string)($fp_delete_return ?? 'index');
$fpDeleteRedirectUrl = ($fpDeleteReturn === 'list_all') ? ($modulePath . '/list_all.php') : $listUrl;
$csrfToken = cr_get_csrf_token();

// Gallery POST actions (folders, uploads, rename, move, tags).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fp_action']) && ($crud_action === 'index' || $crud_action === 'create')) {
    cr_require_valid_csrf_token();
    if ($fpGalleryAccessError !== '') {
        $_SESSION['crud_error'] = $fpGalleryAccessError;
        header('Location: ' . $listUrl);
        exit;
    }
    $fpAction = (string)$_POST['fp_action'];
    $redirectFolder = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : (isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0);
    $redirectSuffix = $redirectFolder > 0 ? '?folder_id=' . $redirectFolder : '';

    if ($fpAction === 'folder_create') {
        $name = trim((string)($_POST['folder_name'] ?? ''));
        $parentId = (int)($_POST['parent_folder_id'] ?? 0);
        $parentId = $parentId > 0 ? $parentId : null;
        if ($name === '') {
            $_SESSION['crud_error'] = 'Folder name is required.';
        } elseif (fp_folder_name_exists($conn, (int)$company_id, $parentId, $name)) {
            $_SESSION['crud_error'] = 'A folder with that name already exists at this level.';
        } elseif ($parentId === null) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plan_folders (company_id, parent_folder_id, name, active) VALUES (?, NULL, ?, 1)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $company_id, $name);
                if (!mysqli_stmt_execute($stmt)) {
                    $_SESSION['crud_error'] = 'Could not create folder.';
                } else {
                    $newFolderId = (int)mysqli_insert_id($conn);
                    if ($newFolderId > 0) {
                        $folderNewValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $newFolderId, (int)$company_id);
                        fp_audit_log_record($conn, 'floor_plan_folders', $newFolderId, (int)$company_id, 'INSERT', null, $folderNewValues);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plan_folders (company_id, parent_folder_id, name, active) VALUES (?, ?, ?, 1)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iis', $company_id, $parentId, $name);
                if (!mysqli_stmt_execute($stmt)) {
                    $_SESSION['crud_error'] = 'Could not create folder.';
                } else {
                    $newFolderId = (int)mysqli_insert_id($conn);
                    if ($newFolderId > 0) {
                        $folderNewValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $newFolderId, (int)$company_id);
                        fp_audit_log_record($conn, 'floor_plan_folders', $newFolderId, (int)$company_id, 'INSERT', null, $folderNewValues);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: ' . $listUrl . $redirectSuffix);
        exit;
    }

    if ($fpAction === 'folder_rename') {
        $folderId = (int)($_POST['folder_id'] ?? 0);
        $name = trim((string)($_POST['folder_name'] ?? ''));
        if ($folderId <= 0 || !fp_folder_belongs_to_company($conn, $folderId, (int)$company_id)) {
            $_SESSION['crud_error'] = 'Folder not found.';
        } elseif ($name === '') {
            $_SESSION['crud_error'] = 'Folder name is required.';
        } else {
            $parentRes = mysqli_query($conn, 'SELECT parent_folder_id FROM floor_plan_folders WHERE id=' . (int)$folderId . ' AND company_id=' . (int)$company_id . ' LIMIT 1');
            $parentRow = ($parentRes) ? mysqli_fetch_assoc($parentRes) : null;
            $parentId = isset($parentRow['parent_folder_id']) && $parentRow['parent_folder_id'] !== null ? (int)$parentRow['parent_folder_id'] : null;
            if (fp_folder_name_exists($conn, (int)$company_id, $parentId, $name, $folderId)) {
                $_SESSION['crud_error'] = 'A folder with that name already exists at this level.';
            } else {
                $folderOldValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $folderId, (int)$company_id);
                $stmt = mysqli_prepare($conn, 'UPDATE floor_plan_folders SET name=? WHERE id=? AND company_id=? LIMIT 1');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sii', $name, $folderId, $company_id);
                    if (!mysqli_stmt_execute($stmt)) {
                        $_SESSION['crud_error'] = 'Could not rename folder.';
                    } elseif ($folderOldValues !== null) {
                        $folderNewValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $folderId, (int)$company_id);
                        fp_audit_log_record($conn, 'floor_plan_folders', $folderId, (int)$company_id, 'UPDATE', $folderOldValues, $folderNewValues);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        header('Location: ' . $listUrl . $redirectSuffix);
        exit;
    }

    if ($fpAction === 'folder_move') {
        $folderId = (int)($_POST['move_folder_id'] ?? 0);
        $parentRaw = $_POST['parent_folder_id'] ?? '';
        $newParentId = ($parentRaw === '' || $parentRaw === 'root') ? null : (int)$parentRaw;
        if ($folderId <= 0) {
            $_SESSION['crud_error'] = 'Folder not found.';
        } else {
            $allFolders = fp_fetch_folders($conn, (int)$company_id);
            $moveError = fp_move_folder_to_parent($conn, (int)$company_id, $folderId, $newParentId, $allFolders);
            if ($moveError === '__NOOP__') {
                $_SESSION['crud_success'] = 'Folder is already in the selected location.';
            } elseif ($moveError !== '') {
                $_SESSION['crud_error'] = $moveError;
            } else {
                $_SESSION['crud_success'] = 'Folder moved.';
            }
        }
        header('Location: ' . $listUrl . '?folder_id=' . (int)$folderId);
        exit;
    }

    if ($fpAction === 'folder_delete') {
        $folderId = (int)($_POST['folder_id'] ?? 0);
        if ($folderId <= 0 || !fp_folder_belongs_to_company($conn, $folderId, (int)$company_id)) {
            $_SESSION['crud_error'] = 'Folder not found.';
        } elseif (fp_folder_has_children($conn, $folderId, (int)$company_id) || fp_folder_has_files($conn, $folderId, (int)$company_id)) {
            $_SESSION['crud_error'] = 'Remove subfolders and files before deleting this folder.';
        } else {
            $folderOldValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $folderId, (int)$company_id);
            $stmt = mysqli_prepare($conn, 'DELETE FROM floor_plan_folders WHERE id=? AND company_id=? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $folderId, $company_id);
                if (mysqli_stmt_execute($stmt) && $folderOldValues !== null) {
                    fp_audit_log_record($conn, 'floor_plan_folders', $folderId, (int)$company_id, 'DELETE', $folderOldValues, null);
                }
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: ' . $listUrl);
        exit;
    }

    if ($fpAction === 'upload_files') {
        $targetFolderId = (int)($_POST['folder_id'] ?? 0);
        if ($targetFolderId > 0 && !fp_folder_belongs_to_company($conn, $targetFolderId, (int)$company_id)) {
            $_SESSION['crud_error'] = 'Invalid target folder.';
            header('Location: ' . $listUrl);
            exit;
        }
        $resolvedLocationId = fp_resolve_post_it_location_id($conn, (int)$company_id, $_POST['it_location_id'] ?? 0);
        if ($resolvedLocationId === -1) {
            $_SESSION['crud_error'] = 'Invalid linked IT location.';
            header('Location: ' . $listUrl);
            exit;
        }
        $folderParam = $targetFolderId > 0 ? $targetFolderId : null;
        $locationParam = $resolvedLocationId;
        $uploadErrors = [];
        $uploadedCount = 0;
        $names = $_FILES['gallery_files']['name'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
        }
        $fileCount = count($names);
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $_FILES['gallery_files']['name'][$i] ?? '',
                'type' => $_FILES['gallery_files']['type'][$i] ?? '',
                'tmp_name' => $_FILES['gallery_files']['tmp_name'][$i] ?? '',
                'error' => $_FILES['gallery_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['gallery_files']['size'][$i] ?? 0,
            ];
            if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileError = '';
            if (!fp_validate_upload_file($file, $fileError)) {
                $uploadErrors[] = $fileError;
                continue;
            }
            $originalName = (string)$file['name'];
            $displayName = trim((string)pathinfo($originalName, PATHINFO_FILENAME));
            if ($displayName === '') {
                $displayName = 'Floor plan';
            }
            $ext = fp_normalize_extension($originalName);
            if ($ext === 'jpg') {
                $ext = 'jpg';
            }
            $mime = fp_detect_upload_mime_type((string)$file['tmp_name']);
            $placeholder = 'pending';
            $size = (int)$file['size'];
            $employeeId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
            if ($folderParam === null && $employeeId > 0) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plans (company_id, folder_id, display_name, stored_filename, mime_type, file_ext, file_size, created_by, active) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 1)');
                mysqli_stmt_bind_param($stmt, 'issssii', $company_id, $displayName, $placeholder, $mime, $ext, $size, $employeeId);
            } elseif ($folderParam === null) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plans (company_id, folder_id, display_name, stored_filename, mime_type, file_ext, file_size, active) VALUES (?, NULL, ?, ?, ?, ?, ?, 1)');
                mysqli_stmt_bind_param($stmt, 'issssi', $company_id, $displayName, $placeholder, $mime, $ext, $size);
            } elseif ($employeeId > 0) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plans (company_id, folder_id, display_name, stored_filename, mime_type, file_ext, file_size, created_by, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
                mysqli_stmt_bind_param($stmt, 'iissssii', $company_id, $folderParam, $displayName, $placeholder, $mime, $ext, $size, $employeeId);
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO floor_plans (company_id, folder_id, display_name, stored_filename, mime_type, file_ext, file_size, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                mysqli_stmt_bind_param($stmt, 'iissssi', $company_id, $folderParam, $displayName, $placeholder, $mime, $ext, $size);
            }
            if (!$stmt || !mysqli_stmt_execute($stmt)) {
                $uploadErrors[] = 'Could not save ' . $displayName . '.';
                mysqli_stmt_close($stmt);
                continue;
            }
            $planId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            $storedFilename = 'floor_plan_' . $planId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $dest = fp_absolute_path((int)$company_id, $storedFilename);
            if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
                $rollbackEmp = (int)($_SESSION['employee_id'] ?? 0);
                mysqli_query($conn, 'UPDATE floor_plans SET deleted_at=NOW(), deleted_by=' . (int)$rollbackEmp . ' WHERE id=' . (int)$planId . ' AND company_id=' . (int)$company_id . ' AND deleted_at IS NULL LIMIT 1');
                $uploadErrors[] = 'Could not store ' . $displayName . ' on disk.';
                continue;
            }
            $storedFilenameOldValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
            $upd = mysqli_prepare($conn, 'UPDATE floor_plans SET stored_filename=? WHERE id=? AND company_id=? LIMIT 1');
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'sii', $storedFilename, $planId, $company_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
            if ($storedFilenameOldValues !== null) {
                $storedFilenameNewValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
                fp_audit_log_floor_plan($conn, $planId, (int)$company_id, 'UPDATE', $storedFilenameOldValues, $storedFilenameNewValues);
            }
            fp_apply_plan_it_location($conn, $planId, (int)$company_id, $locationParam);
            $tagRaw = trim((string)($_POST['upload_tags'] ?? ''));
            if ($tagRaw !== '') {
                fp_save_tags_for_plan($conn, $planId, (int)$company_id, fp_parse_tag_input($tagRaw));
            }
            $newValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
            fp_audit_log_floor_plan($conn, $planId, (int)$company_id, 'INSERT', null, $newValues);
            $uploadedCount++;
        }
        if ($uploadedCount === 0 && empty($uploadErrors)) {
            $uploadErrors[] = 'No files were uploaded.';
        }
        if (!empty($uploadErrors)) {
            $_SESSION['crud_error'] = implode(' ', $uploadErrors);
        }
        header('Location: ' . $listUrl . ($targetFolderId > 0 ? '?folder_id=' . $targetFolderId : ''));
        exit;
    }

    if ($fpAction === 'rename_file') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        if ($planId <= 0 || $displayName === '') {
            $_SESSION['crud_error'] = 'File name is required.';
        } else {
            $oldValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
            $stmt = mysqli_prepare($conn, 'UPDATE floor_plans SET display_name=? WHERE id=? AND company_id=? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sii', $displayName, $planId, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $newValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
                fp_audit_log_floor_plan($conn, $planId, (int)$company_id, 'UPDATE', $oldValues, $newValues);
            }
        }
        header('Location: ' . $listUrl . $redirectSuffix);
        exit;
    }

    if ($fpAction === 'move_file') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $targetFolderId = (int)($_POST['folder_id'] ?? 0);
        if ($planId <= 0) {
            $_SESSION['crud_error'] = 'File not found.';
        } elseif ($targetFolderId > 0 && !fp_folder_belongs_to_company($conn, $targetFolderId, (int)$company_id)) {
            $_SESSION['crud_error'] = 'Invalid folder.';
        } else {
            $moveOldValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
            if ($moveOldValues === null) {
                $_SESSION['crud_error'] = 'File not found.';
            } else {
                $currentFolderId = (int)($moveOldValues['folder_id'] ?? 0);
                $alreadyInTarget = ($targetFolderId > 0 && $currentFolderId === $targetFolderId)
                    || ($targetFolderId <= 0 && $currentFolderId <= 0);
                $moved = false;
                if (!$alreadyInTarget) {
                    if ($targetFolderId > 0) {
                        $stmt = mysqli_prepare($conn, 'UPDATE floor_plans SET folder_id=? WHERE id=? AND company_id=? LIMIT 1');
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 'iii', $targetFolderId, $planId, $company_id);
                            mysqli_stmt_execute($stmt);
                            $moved = mysqli_stmt_affected_rows($stmt) > 0;
                            mysqli_stmt_close($stmt);
                        }
                    } else {
                        $stmt = mysqli_prepare($conn, 'UPDATE floor_plans SET folder_id=NULL WHERE id=? AND company_id=? LIMIT 1');
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 'ii', $planId, $company_id);
                            mysqli_stmt_execute($stmt);
                            $moved = mysqli_stmt_affected_rows($stmt) > 0;
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
                if (!$alreadyInTarget && !$moved) {
                    $_SESSION['crud_error'] = 'Could not move file.';
                } elseif (!$alreadyInTarget) {
                    $moveNewValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
                    fp_audit_log_floor_plan($conn, $planId, (int)$company_id, 'UPDATE', $moveOldValues, $moveNewValues);
                }
            }
        }
        header('Location: ' . $listUrl . ($targetFolderId > 0 ? '?folder_id=' . $targetFolderId : ''));
        exit;
    }

    if ($fpAction === 'delete_file') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            fp_delete_plans_by_ids($conn, [$planId], (int)$company_id);
        }
        header('Location: ' . $listUrl . $redirectSuffix);
        exit;
    }
}

// Handle Excel/CSV database import requests from table-tools.js.
$requestContentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
$isJsonImportRequest = false;
$rawBody = '';
$jsonBody = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true)) {
    $rawBody = (string)file_get_contents('php://input');
    $jsonBody = json_decode($rawBody, true);
    $hasImportRows = is_array($jsonBody) && isset($jsonBody['import_excel_rows']);

    // Why: table-tools.js may send JSON payloads with non-JSON content-type headers.
    $bodyMentionsImportRows = strpos($rawBody, '"import_excel_rows"') !== false;
    $isJsonImportRequest = strpos($requestContentType, 'application/json') !== false || $hasImportRows || $bodyMentionsImportRows;
}
if ($isJsonImportRequest) {
        header('Content-Type: application/json');

        if (!is_array($jsonBody)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON import payload.']);
            exit;
        }

        if (!isset($jsonBody['import_excel_rows'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing import rows payload.']);
            exit;
        }

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        if ($fpGalleryAccessError !== '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $fpGalleryAccessError]);
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
            }
        }

        echo json_encode(['ok' => true, 'inserted' => $insertedRows]);
        exit;
    }

// HANDLE BULK DELETIONS (from POST)
if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }

    // Why: Server-side RBAC before CSRF/delete SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'delete', $crud_table);

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
    $dbErrorCode = 0;
    $dbErrorMessage = '';

    if ($bulkAction === 'clear_table') {
        if ($hasCompany && $company_id > 0) {
            $allRes = mysqli_query($conn, 'SELECT id FROM floor_plans WHERE company_id=' . (int)$company_id);
            $allIds = [];
            while ($allRes && ($r = mysqli_fetch_assoc($allRes))) {
                $allIds[] = (int)$r['id'];
            }
            fp_delete_plans_by_ids($conn, $allIds, (int)$company_id);
        }
        header('Location: ' . $modulePath . '/list_all.php');
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) { $ids = []; }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) { $idList[$id] = $id; }
        }

        if (!empty($idList) && $hasCompany && $company_id > 0) {
            fp_delete_plans_by_ids($conn, array_values($idList), (int)$company_id);
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $modulePath . '/list_all.php');
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0 && $hasCompany && $company_id > 0) {
        fp_delete_plans_by_ids($conn, [$id], (int)$company_id);
    }
    header('Location: ' . $fpDeleteRedirectUrl);
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

// HANDLE FETCH FOR EDIT/VIEW
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) { $where .= ' AND company_id=' . (int)$company_id; }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) { $errors[] = 'Record not found.'; }
}

// HANDLE FORM SUBMISSION (CREATE/EDIT)

// Handle sample data seeding for empty companies in list view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    cr_require_valid_csrf_token();
    $fpSampleRedirect = ($crud_action === 'list_all') ? ($modulePath . '/list_all.php') : $listUrl;

    if ($fpGalleryAccessError !== '') {
        $_SESSION['crud_error'] = $fpGalleryAccessError;
        header('Location: ' . $fpSampleRedirect);
        exit;
    }

    $folderCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM floor_plan_folders WHERE company_id=' . (int)$company_id);
    $tagCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM floor_plan_tags WHERE company_id=' . (int)$company_id);
    $fileCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM floor_plans WHERE company_id=' . (int)$company_id);
    $folderRows = ($folderCountRes && ($r = mysqli_fetch_assoc($folderCountRes))) ? (int)($r['total_rows'] ?? 0) : 0;
    $tagRows = ($tagCountRes && ($r = mysqli_fetch_assoc($tagCountRes))) ? (int)($r['total_rows'] ?? 0) : 0;
    $fileRows = ($fileCountRes && ($r = mysqli_fetch_assoc($fileCountRes))) ? (int)($r['total_rows'] ?? 0) : 0;

    if ($folderRows > 0 || $tagRows > 0 || $fileRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when folders, tags, and files are all empty.';
        header('Location: ' . $fpSampleRedirect);
        exit;
    }

    fp_seed_sample_folders_and_tags($conn, (int)$company_id);
    header('Location: ' . $fpSampleRedirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $crud_action === 'edit' && $crud_table === 'floor_plans') {
    cr_require_valid_csrf_token();
    $planId = (int)($_POST['id'] ?? $editId);
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $targetFolderId = (int)($_POST['folder_id'] ?? 0);
    $resolvedLocationId = fp_resolve_post_it_location_id($conn, (int)$company_id, $_POST['it_location_id'] ?? 0);
    if ($planId <= 0 || $displayName === '') {
        $errors[] = 'File name is required.';
    } elseif ($targetFolderId > 0 && !fp_folder_belongs_to_company($conn, $targetFolderId, (int)$company_id)) {
        $errors[] = 'Invalid folder.';
    } elseif ($resolvedLocationId === -1) {
        $errors[] = 'Invalid linked IT location.';
    } else {
        $editOldValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
        if ($targetFolderId > 0) {
            $stmt = mysqli_prepare($conn, 'UPDATE floor_plans SET display_name=?, folder_id=? WHERE id=? AND company_id=? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'siii', $displayName, $targetFolderId, $planId, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE floor_plans SET display_name=?, folder_id=NULL WHERE id=? AND company_id=? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sii', $displayName, $planId, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        fp_apply_plan_it_location($conn, $planId, (int)$company_id, $resolvedLocationId);
        fp_save_tags_for_plan($conn, $planId, (int)$company_id, fp_parse_tag_input((string)($_POST['tag_names'] ?? '')));
        if ($editOldValues !== null) {
            $editNewValues = fp_audit_fetch_floor_plan($conn, $planId, (int)$company_id);
            fp_audit_log_floor_plan($conn, $planId, (int)$company_id, 'UPDATE', $editOldValues, $editNewValues);
        }
        header('Location: view.php?id=' . $planId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true) && $crud_table !== 'floor_plans') {
    // Why: Server-side RBAC before CSRF persistence (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, $crud_action, $crud_table);

    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$col['Type']);
        
        // Booleans (checkboxes)
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            $sqlValues[$name] = (string) (int) $data[$name];
            continue;
        }

        // Automatic company scoping
        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            $sqlValues[$name] = (string) (int) $company_id;
            continue;
        }

        if (preg_match('/(_by|_by_user_id)$/', (string)$name)) {
            $userValue = trim((string)($_POST[$name] ?? ''));
            $data[$name] = ($userValue === '') ? 'NULL' : (string)(int)$userValue;
            continue;
        }

        // Foreign keys with inline addition capability
        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                // Inline insertion of a missing reference record.
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) { $findSql .= ' AND company_id=' . (int)$company_id; }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string) (int) $row['id'];
                    $sqlValues[$name] = (string) (int) $row['id'];
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
                        $resolvedId = (string) (int) mysqli_insert_id($conn);
                        $data[$name] = $resolvedId;
                        $sqlValues[$name] = $resolvedId;
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = '';
                        $sqlValues[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        // Generic field handling
        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = '';
            $sqlValues[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
                $sqlValues[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = (string) $value;
            $sqlValues[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            if (function_exists('itm_crud_stamp_create_audit')) {
                itm_crud_stamp_create_audit($data, $sqlValues);
            }
            $fields = []; $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $sqlValues[$name] ?? 'NULL';
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            if (function_exists('itm_crud_stamp_update_audit')) {
                itm_crud_stamp_update_audit($data, $sqlValues, $data);
            }
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . ($sqlValues[$name] ?? 'NULL');
            }
            $where = ' WHERE id=' . $editId;
            if ($hasCompany && $company_id > 0) { $where .= ' AND company_id=' . (int)$company_id; }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
        }

        $dbErrorCode = 0; $dbErrorMessage = '';
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            header('Location: ' . $listUrl);
            exit;
        }
        $errors[] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}

$galleryFolderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$galleryUnfiled = isset($_GET['unfiled']) && (string)$_GET['unfiled'] === '1';
$gallerySearch = ($crud_action === 'index') ? trim((string)($_GET['search'] ?? '')) : '';
$galleryFolders = [];
$galleryTree = [];
$galleryItems = [];
$gallerySampleEmpty = false;
if ($crud_action === 'index' && $fpGalleryAccessError === '') {
    $galleryFolders = fp_fetch_folders($conn, (int)$company_id);
    $galleryTree = fp_build_folder_tree($galleryFolders);
    $galleryItems = fp_fetch_gallery_items(
        $conn,
        (int)$company_id,
        $gallerySearch,
        $galleryFolderId > 0 ? $galleryFolderId : null,
        $galleryUnfiled
    );
    $fc = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM floor_plan_folders WHERE company_id=' . (int)$company_id);
    $tc = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM floor_plan_tags WHERE company_id=' . (int)$company_id);
    $pc = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM floor_plans WHERE company_id=' . (int)$company_id);
    $folderCount = ($fc && ($r = mysqli_fetch_assoc($fc))) ? (int)$r['c'] : 0;
    $tagCount = ($tc && ($r = mysqli_fetch_assoc($tc))) ? (int)$r['c'] : 0;
    $fileCount = ($pc && ($r = mysqli_fetch_assoc($pc))) ? (int)$r['c'] : 0;
    $gallerySampleEmpty = ($folderCount + $tagCount + $fileCount) === 0;
}

// FETCH LIST DATA
$where = '';
if ($hasCompany && $company_id > 0) { $where = ' WHERE company_id=' . (int)$company_id; }
if (function_exists('itm_crud_append_not_deleted_predicate')) {
    $where = itm_crud_append_not_deleted_predicate($where);
}

// SEARCH LOGIC
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

// SORTING LOGIC
$sortableColumns = array_map(static function ($col) { return $col['Field']; }, $fieldColumns);
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) { $sort = 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

// PAGINATION LOGIC
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) { $totalRows = (int)($countRow['total_rows'] ?? 0); }
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$rows = null;
if ($crud_action !== 'index') {
    $rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
}
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = itm_resolve_new_button_position($ui_config);
$showBulkActions = ($crud_action === 'list_all' && $totalRows >= $perPage);
$listTableColspan = count($uiColumns) + 1 + ($showBulkActions ? 1 : 0);
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
    $crud_title = 'Floor Plans';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <?php if ($crud_action === 'index'): ?>
    <script src="../../js/floor-plans-gallery.js" defer></script>
    <?php endif; ?>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if ($crud_action === 'index'): ?>
                <?php require __DIR__ . '/gallery_index_view.php'; ?>
            <?php elseif ($crud_action === 'list_all'): ?>
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
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) { itm_crud_render_delete_hidden_audit_inputs(); } ?>
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- SEARCH BAR -->
                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" class="itm-floor-plan-search">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div class="form-group">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="list_all.php" class="btn" title="Clear">🔙</a>
                        </div>
                    </form>
                </div>

                <!-- DATA TABLE -->
                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <?php if ($showBulkActions): ?>
                                <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                            <?php endif; ?>
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
                                <?php if ($showBulkActions): ?>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                <?php endif; ?>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php if ($f === 'comments' && trim((string)($row[$f] ?? '')) !== ''): ?>
                                            <span title="<?php echo sanitize((string)$row[$f]); ?>">💬</span>
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
                            <tr><td colspan="<?php echo (int)$listTableColspan; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" action="list_all.php" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                <?php
                    if (function_exists('itm_crud_render_form_hidden_audit_inputs')) {
                        itm_crud_render_form_hidden_audit_inputs($data, (string)$crud_action);
                    }
                    ?>
<button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample folders &amp; tags</button>
                        </form>
                        <p style="text-align:center;margin-top:8px;font-size:0.9em;">Imports metadata only in table view; upload files in the gallery.</p>
                    </div>
                <?php endif; ?>

                <p style="margin-top:12px;"><a href="index.php" class="btn btn-sm">← Back to gallery</a></p>

                <!-- PAGINATION CONTROLS -->
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($crud_action === 'create'): ?>
                <?php require __DIR__ . '/create_upload_view.php'; ?>
            <?php elseif ($crud_action === 'edit'): ?>
                <?php echo itm_render_alert_errors($errors); ?>
                <?php if (!empty($data)): ?>
                    <?php require __DIR__ . '/edit_form_view.php'; ?>
                <?php endif; ?>

            <?php elseif ($crud_action === 'view'): ?>
                <?php if (!empty($data)): ?>
                    <?php require __DIR__ . '/view_detail.php'; ?>
                <?php else: ?>
                    <div class="alert alert-danger">Record not found.</div>
                    <a href="index.php" class="btn">Gallery</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JS FOR BULK ACTIONS AND UI INDICATORS -->
<script src="../../js/theme.js"></script>
<?php if ($crud_action === 'list_all'): ?>
<script src="../../js/bulk-delete-selection.js"></script>
<?php endif; ?>
<script> window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>; </script>
<script src="../../js/select-add-option.js"></script>
<script>
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
<?php require_once ROOT_PATH . 'includes/itm_qr_share_modal.php'; ?>
</body>
</html>
