<?php
/**
 * Floor Designer Module - Index
 *
 * Interactive building floor plan management with network point placement.
 */
$crud_table = 'floor_designer';
$crud_title = 'Floor Designer';
$crud_action = $crud_action ?? 'index';

require_once '../../config/config.php';

// Validate table configuration

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}


$pk = 'id';
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($company_id <= 0) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// IMPORT EXCEL (JSON endpoint)
if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($crud_action, ["index", "list_all"], true) && strpos((string)($_SERVER["CONTENT_TYPE"] ?? ""), "application/json") !== false) {
    itm_handle_json_table_import($conn, $crud_table, $company_id);
}

function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}

/**
 * Escapes a database identifier
 */
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Fetches column metadata
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
 * Maps foreign key columns
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
 * Fetches available options for a foreign key dropdown
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
 * Detects metadata for a referenced table
 */
function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }
    foreach (['name', 'display_name', 'title', 'username', 'code', 'mode_name'] as $candidate) {
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
 * Filters out system-managed columns
 */
function cr_manageable_columns($columns) {
    // Why: Keep audit meta available for view/hidden forms/POST; list hides via itm_crud_is_list_hidden_audit_field.
    return array_values(array_filter($columns, function ($c) {
        return ($c['Field'] ?? '') !== 'id';
    }));
}

/**
 * Converts a database field name into a human-readable label
 */
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') return '';

    $map = [
        'it_location_id' => 'Location',
        'sq_meters' => 'Square Meters (m²)',
        'shape_type' => 'Floor Shape Type',
        'floor_plan_id' => 'Background Image',
        'active' => 'Active',
    ];

    if (isset($map[$label])) return $map[$label];
    if ($label === 'id') return 'ID';

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

/**
 * Renders a specific table cell value
 */
function cr_render_cell_value($table, $field, $value) {
    if (function_exists('itm_crud_render_audit_cell_value')) {
        $auditHtml = itm_crud_render_audit_cell_value($GLOBALS['conn'] ?? null, (int)($GLOBALS['company_id'] ?? 0), $field, $value);
        if ($auditHtml !== null) {
            return $auditHtml;
        }
    }
if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    if (isset($GLOBALS['fkMap'][$field])) {
        $fk = $GLOBALS['fkMap'][$field];
        $fkTable = $fk['REFERENCED_TABLE_NAME'];
        $fkCol = $fk['REFERENCED_COLUMN_NAME'];
        $meta = cr_fk_metadata($GLOBALS['conn'], $fkTable);
        $labelCol = $meta['label_col'];

        $sql = "SELECT " . cr_escape_identifier($labelCol) . " FROM " . cr_escape_identifier($fkTable) . " WHERE " . cr_escape_identifier($fkCol) . " = ? LIMIT 1";
        $stmt = mysqli_prepare($GLOBALS['conn'], $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $value);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                return sanitize((string)$row[$labelCol]);
            }
            mysqli_stmt_close($stmt);
        }
    }

    return sanitize((string)($value ?? ''));
}

function cr_get_csrf_token() {
    return itm_get_csrf_token();
}

function cr_require_valid_csrf_token() {
    itm_require_post_csrf();
}

/**
 * Validates and normalizes numeric input
 */
function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = str_contains($type, 'unsigned');
    $raw = trim((string)$rawValue);

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type, $match)) {
        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $error = cr_humanize_field($fieldName) . ' must be a valid integer.';
            return false;
        }
        $normalizedValue = (string)$intVal;
        return true;
    }

    if (preg_match('/^(decimal|float|double)\b/', $type)) {
        if (!is_numeric($raw)) {
            $error = cr_humanize_field($fieldName) . ' must be a valid number.';
            return false;
        }
        $normalizedValue = (string)$raw;
        return true;
    }

    return true;
}

// Module initialization
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}

$hideCompanyIdTables = ['floor_designer', 'floor_designer_points'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables, $crud_table) {
    $fieldName = (string)($col['Field'] ?? '');
    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field($fieldName)) {
        return false;
    }
    if ($fieldName !== 'company_id') {
        return true;
    }
    return !in_array((string)$crud_table, $hideCompanyIdTables, true);
}));

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

// AJAX Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    cr_require_valid_csrf_token();
    header('Content-Type: application/json');
    $ajax_action = $_POST['ajax_action'];

    if ($ajax_action === 'save_point') {
        if (!isset($_POST['rotation']) || !is_numeric($_POST['rotation'])) {
            echo json_encode(['ok' => false, 'error' => 'Invalid rotation value.']);
            exit;
        }
        $point_id = (int)($_POST['point_id'] ?? 0);
        $floor_designer_id = (int)($_POST['floor_designer_id'] ?? 0);
        $point_type_id = (int)($_POST['point_type_id'] ?? 0) ?: null;
        $x = (float)($_POST['x'] ?? 0);
        $y = (float)($_POST['y'] ?? 0);
        $comment_x = (float)($_POST['comment_x'] ?? 0);
        $comment_y = (float)($_POST['comment_y'] ?? 30);
        $label = trim((string)($_POST['label'] ?? ''));
        $rotation = (float)($_POST['rotation'] ?? 0);
        $wlan_address = trim((string)($_POST['wlan_address'] ?? ''));
        $ip_address = trim((string)($_POST['ip_address'] ?? ''));
        $mac_address = trim((string)($_POST['mac_address'] ?? ''));
        $patch_port = trim((string)($_POST['patch_port'] ?? ''));
        $switch_id = (int)($_POST['switch_id'] ?? 0) ?: null;
        $switch_port_id = (int)($_POST['switch_port_id'] ?? 0) ?: null;
        $cable_color_id = (int)($_POST['cable_color_id'] ?? 0) ?: null;

        if ($point_id > 0) {
            $sql = "UPDATE floor_designer_points
                    SET point_type_id=?, label=?, rotation=?,
                        wlan_address=?, ip_address=?, mac_address=?, patch_port=?,
                        switch_id=?, switch_port_id=?, cable_color_id=?
                    WHERE id=? AND company_id=?";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'isdssssiiiii',
                $point_type_id, $label, $rotation,
                $wlan_address, $ip_address, $mac_address, $patch_port,
                $switch_id, $switch_port_id, $cable_color_id,
                $point_id, $company_id
            );
        } else {
            $sql = "INSERT INTO floor_designer_points
                    (company_id, floor_designer_id, point_type_id, x, y, comment_x, comment_y,
                     label, rotation, wlan_address, ip_address, mac_address, patch_port,
                     switch_id, switch_port_id, cable_color_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'iiiddddsdssssiii',
                $company_id, $floor_designer_id, $point_type_id,
                $x, $y, $comment_x, $comment_y,
                $label, $rotation,
                $wlan_address, $ip_address, $mac_address, $patch_port,
                $switch_id, $switch_port_id, $cable_color_id
            );




        }

        if (mysqli_stmt_execute($stmt)) {
            $new_id = $point_id ?: mysqli_insert_id($conn);
            echo json_encode(['ok' => true, 'id' => $new_id]);
        } else {
            echo json_encode(['ok' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($ajax_action === 'update_point_pos') {
        $point_id = (int)($_POST['point_id'] ?? 0);
        $x = (float)($_POST['x'] ?? 0);
        $y = (float)($_POST['y'] ?? 0);
        $sql = "UPDATE floor_designer_points SET x=?, y=? WHERE id=? AND company_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ddii', $x, $y, $point_id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($ajax_action === 'update_comment_pos') {
        $point_id = (int)($_POST['point_id'] ?? 0);
        $comment_x = (float)($_POST['comment_x'] ?? 0);
        $comment_y = (float)($_POST['comment_y'] ?? 0);
        $sql = "UPDATE floor_designer_points SET comment_x=?, comment_y=? WHERE id=? AND company_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ddii', $comment_x, $comment_y, $point_id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($ajax_action === 'delete_point') {
        $point_id = (int)($_POST['point_id'] ?? 0);
        $empId = (int)($_SESSION['employee_id'] ?? 0);
        // Why: Soft-delete designer points so audit meta remains; list filters deleted_at IS NULL.
        $sql = "UPDATE floor_designer_points SET deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=? AND deleted_at IS NULL";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $empId, $point_id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($ajax_action === 'get_switch_ports') {
        $switch_id = (int)($_POST['switch_id'] ?? 0);
        $sql = "SELECT id, port_number, port_type FROM switch_ports WHERE equipment_id=? AND company_id=? ORDER BY (CASE WHEN port_type = 'RJ45' THEN 1 ELSE 2 END) ASC, port_number ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $switch_id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ports = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $ports[] = $row;
        }
        echo json_encode(['ok' => true, 'ports' => $ports]);
        exit;
    }
        if ($ajax_action === 'save_as_floor_plan') {
        require_once '../floor_plans/gallery_helpers.php';
        $file_data = $_POST['data'] ?? '';
        $name = trim((string)($_POST['name'] ?? 'Floor Designer Export'));
        $ext = (isset($_POST['ext']) && $_POST['ext'] === 'pdf') ? 'pdf' : 'png';

        $mime = 'image/png';
        $prefix = 'data:image/png;base64,';
        if ($ext === 'pdf') {
            $mime = 'application/pdf';
            $prefix = 'data:application/pdf;base64,';
        }

        $parts = explode(",", $file_data);
        if (count($parts) === 2) {
            $file_data = str_replace(' ', '+', $parts[1]);
            $decoded = base64_decode($file_data);

            if ($decoded) {
                $size = strlen($decoded);
                $employeeId = (int)($_SESSION['employee_id'] ?? 0);

                // Insert record first to get ID
                $placeholder = 'pending';
                $sql = "INSERT INTO floor_plans (company_id, display_name, stored_filename, mime_type, file_ext, file_size, created_by, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'issssii', $company_id, $name, $placeholder, $mime, $ext, $size, $employeeId);

                if (mysqli_stmt_execute($stmt)) {
                    $planId = mysqli_insert_id($conn);
                    $storedFilename = 'floor_plan_' . $planId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

                    $destDir = fp_company_upload_dir($company_id);
                    if (file_put_contents($destDir . $storedFilename, $decoded)) {
                        mysqli_query($conn, "UPDATE floor_plans SET stored_filename='" . mysqli_real_escape_string($conn, $storedFilename) . "' WHERE id=$planId");

                        // Audit Log
                        if (function_exists('itm_log_audit')) {
                            $newValues = itm_fetch_audit_record($conn, 'floor_plans', $planId, $company_id);
                            itm_log_audit($conn, 'floor_plans', $planId, 'INSERT', null, $newValues);
                        }

                        echo json_encode(['ok' => true, 'id' => $planId]);
                    } else {
                        $rollbackEmp = (int)($_SESSION['employee_id'] ?? 0);
                        mysqli_query($conn, "UPDATE floor_plans SET deleted_at=NOW(), deleted_by=" . (int)$rollbackEmp . " WHERE id=" . (int)$planId . " AND company_id=" . (int)$company_id . " AND deleted_at IS NULL");
                        echo json_encode(['ok' => false, 'error' => 'Failed to write file to disk.']);
                    }
                } else {
                    echo json_encode(['ok' => false, 'error' => 'Database insert failed: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['ok' => false, 'error' => 'Invalid file data.']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'No file data provided or invalid format.']);
        }
        exit;
    }
}

// Handle Deletion
if ($crud_action === 'delete') {
    itm_require_crud_role_module_permission($conn, 'delete', $crud_table);
    cr_require_valid_csrf_token();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $empId = (int)($_SESSION['employee_id'] ?? 0);
        $sql = "UPDATE floor_designer SET deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=? AND deleted_at IS NULL";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $empId, $id, $company_id);
        mysqli_stmt_execute($stmt);
    }
    header('Location: ' . $listUrl);
    exit;
}

// Standard CRUD Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();
    $errors = [];
    $data = [];

    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $it_location_id = (int)($_POST['it_location_id'] ?? 0) ?: null;
    $sq_meters = ($_POST['sq_meters'] !== '') ? (float)$_POST['sq_meters'] : null;
    $shape_type = $_POST['shape_type'] ?? 'Square';
    $floor_plan_id = (int)($_POST['floor_plan_id'] ?? 0) ?: null;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $sql = "INSERT INTO floor_designer (company_id, name, it_location_id, sq_meters, shape_type, floor_plan_id, active) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isidsii', $company_id, $name, $it_location_id, $sq_meters, $shape_type, $floor_plan_id, $active);
        } else {
            $sql = "UPDATE floor_designer SET name=?, it_location_id=?, sq_meters=?, shape_type=?, floor_plan_id=?, active=? WHERE id=? AND company_id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sidsiiii', $name, $it_location_id, $sq_meters, $shape_type, $floor_plan_id, $active, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $redirect_id = ($crud_action === 'create') ? mysqli_insert_id($conn) : $id;

            // Manual audit logging since we bypass itm_run_query
            if (function_exists('itm_log_audit')) {
                $auditAction = ($crud_action === 'create') ? 'INSERT' : 'UPDATE';
                $oldValues = ($crud_action === 'edit') ? itm_fetch_audit_record($conn, 'floor_designer', $id, $company_id) : null;
                $newValues = itm_fetch_audit_record($conn, 'floor_designer', $redirect_id, $company_id);
                itm_log_audit($conn, 'floor_designer', $redirect_id, $auditAction, $oldValues, $newValues);
            }

            header('Location: edit.php?id=' . $redirect_id);
            exit;
        } else {
            $errors[] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        }
    }
}

// Fetch Data for List
$where = " WHERE company_id=$company_id";
if (function_exists('itm_crud_append_not_deleted_predicate')) {
    $where = itm_crud_append_not_deleted_predicate($where);
}
$sort = $_GET['sort'] ?? 'id';
$dir = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';
$rows = mysqli_query($conn, "SELECT * FROM floor_designer $where ORDER BY " . cr_escape_identifier($sort) . " $dir");

// Fetch Data for Edit/View
$editId = (int)($_GET['id'] ?? 0);
$data = [];
$points = [];
if ($editId > 0) {
    $q = mysqli_query($conn, "SELECT * FROM floor_designer WHERE id=$editId AND company_id=$company_id LIMIT 1");
    $data = mysqli_fetch_assoc($q);
    if (!$data) {
        $errors[] = 'Record not found.';
    } else {
        $pq = mysqli_query($conn, "SELECT p.*, st.type as point_type_name, cc.color_name, cc.hex_color, e.name as switch_name, sp.port_number as switch_port_number
            FROM floor_designer_points p
            LEFT JOIN switch_port_types st ON st.id = p.point_type_id
            LEFT JOIN cable_colors cc ON cc.id = p.cable_color_id
            LEFT JOIN equipment e ON e.id = p.switch_id
            LEFT JOIN switch_ports sp ON sp.id = p.switch_port_id
            WHERE p.floor_designer_id=$editId AND p.company_id=$company_id AND p.deleted_at IS NULL");
        while ($p = mysqli_fetch_assoc($pq)) {
            $points[] = $p;
        }
    }
}

$moduleListHeading = '🧩 ' . $crud_title;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Floor Designer';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        /* Why: Keep designer artboard size local — do not pollute :root CSS variables. */
        .designer-wrapper { --designer-width: 1000px; --designer-height: 800px; position: relative; width: 100%; height: 750px; border: 2px solid #ccc; background: #f0f2f5; overflow: auto; border-radius: 8px; box-shadow: inset 0 0 10px rgba(0,0,0,0.1); }
        .designer-container { position: relative; transform-origin: 0 0; min-width: var(--designer-width); min-height: var(--designer-height); transition: transform 0.1s ease-out; }
        .floor-shape { position: absolute; top: 0; left: 0; width: var(--designer-width); height: var(--designer-height); border: 5px solid #24292f; background-color: #fff; background-size: contain; background-repeat: no-repeat; background-position: center; pointer-events: none; z-index: 1; box-sizing: border-box; }
        .point { position: absolute; width: 24px; height: 24px; border-radius: 50%; border: 3px solid #fff; cursor: move; z-index: 100; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.4); transform: translate(-50%, -50%); }
        .point.rj45 { background: #0969da; }
        .point.sfp { background: #1a7f37; }
        .point.door { background: transparent; border: none; box-shadow: none; width: 60px; height: 60px; border-radius: 0; transform-origin: 0 0; --rotation: 0deg; transform: rotate(var(--rotation)); }
        .point.access-point { background: #fff; border: 2px solid #0969da; width: 40px; height: 40px; border-radius: 50%; color: #0969da; --rotation: 0deg; transform: translate(-50%, -50%) rotate(var(--rotation)); }
        .point:hover { transform: translate(-50%, -50%) scale(1.2); z-index: 110; }
        .point.door:hover { transform: rotate(var(--rotation)) scale(1.1); z-index: 110; }
        .point.access-point:hover { transform: translate(-50%, -50%) rotate(var(--rotation)) scale(1.1); z-index: 110; }
        .comment-box { position: absolute; background: rgba(255,255,255,0.95); border: 1px solid #d0d7de; border-radius: 6px; padding: 6px; font-size: 11px; white-space: nowrap; cursor: move; z-index: 90; box-shadow: 0 3px 6px rgba(0,0,0,0.15); pointer-events: auto; }
        .comment-box table { border-collapse: collapse; margin: 0; }
        .comment-box td { padding: 1px 4px; border: none; font-size: 10px; color: #24292f; line-height: 1.3; }
        .comment-box .label-row { font-weight: 600; border-bottom: 1px solid #d0d7de; padding-bottom: 3px; margin-bottom: 3px; color: #0969da; }
        .controls { margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #d0d7de; }
        .layer-controls { margin-left: auto; display: flex; gap: 10px; align-items: center; border-left: 1px solid #d0d7de; padding-left: 15px; }
        .grid-layer { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; z-index: 10; background-image: linear-gradient(#d0d7de 1px, transparent 1px), linear-gradient(90deg, #d0d7de 1px, transparent 1px); background-size: 25px 25px; display: none; opacity: 0.8; }
        .show-grid .grid-layer { display: block; }
        .point.is-filtered { opacity: 0.1 !important; pointer-events: none !important; }
        .comment-box.is-filtered { display: none !important; }

        /* Shape Rendering Overrides */
        .shape-square { border-radius: 0; }
        .shape-rectangular { width: 1500px; }
        .shape-irregular-ew { border-radius: 150px / 80px; }
        .shape-irregular-e { border-top-right-radius: 300px 500px; border-bottom-right-radius: 300px 500px; }
        .shape-irregular-w { border-top-left-radius: 300px 500px; border-bottom-left-radius: 300px 500px; }
        .shape-irregular-ns { border-radius: 600px / 150px; }
        .shape-irregular-n { border-top-left-radius: 600px 250px; border-top-right-radius: 600px 250px; }
        .shape-irregular-s { border-bottom-left-radius: 600px 250px; border-bottom-right-radius: 600px 250px; }

        #point-modal .form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; }
        #point-modal input, #point-modal select { width: 100%; }

        @media print {
            body { background: #fff; }
            .container { padding: 0; margin: 0; width: 100%; max-width: none; }
            .container > :not(.main-content), .main-content > :not(.content), .content > :not(.designer-wrapper), .controls { display: none !important; }
            .designer-wrapper { border: none; width: 100%; height: auto; overflow: visible; background: #fff; }
            .designer-container { transform: none !important; }
            .floor-shape { border: 2px solid #000; }
        }
        @media (max-width: 768px) {
            .designer-wrapper { height: min(750px, 65vh); }
            .controls { flex-direction: column; align-items: stretch; }
            .layer-controls { margin-left: 0; border-left: none; padding-left: 0; flex-wrap: wrap; }
            .comment-box { white-space: normal; max-width: min(240px, 80vw); }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors ?? []); ?>

            <?php if ($crud_action === 'index' || $crud_action === 'list_all'): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1><?php echo sanitize($moduleListHeading); ?></h1>
                    <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                </div>
                <div class="card">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <?php foreach ($uiColumns as $col): ?>
                                <th><?php echo sanitize(cr_humanize_field($col['Field'])); ?></th>
                            <?php endforeach; ?>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <?php foreach ($uiColumns as $col): ?>
                                    <td><?php echo cr_render_cell_value($crud_table, $col['Field'], $row[$col['Field']]); ?></td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) { itm_crud_render_delete_hidden_audit_inputs(); } ?>
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($uiColumns) + 1; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($crud_action === 'create' || $crud_action === 'edit'): ?>
                <h1><?php echo $crud_action === 'create' ? 'New' : 'Edit'; ?> Floor Plan</h1>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <?php
                    if (function_exists('itm_crud_render_form_hidden_audit_inputs')) {
                        itm_crud_render_form_hidden_audit_inputs($data, (string)$crud_action);
                    }
                    ?>
<input type="hidden" name="id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                    <?php foreach ($uiColumns as $col):
                        $name = $col['Field'];
                        $displayVal = cr_form_display_value($data[$name] ?? '');
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'active'): ?>
                                <?php $isActive = ($crud_action === 'create') ? true : ((int)$displayVal === 1); ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="active" value="1" <?php echo $isActive ? 'checked' : ''; ?>>
                                    <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo $isActive ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif ($name === 'shape_type'): ?>
                                <select name="shape_type">
                                    <?php
                                    $shapes = ['Square', 'Rectangular', 'Irregular east-west walls', 'Irregular east wall', 'Irregular west wall', 'Irregular north-south walls', 'Irregular north wall', 'Irregular south wall'];
                                    foreach ($shapes as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo ($displayVal === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($name === 'floor_plan_id'): ?>
                                <select name="floor_plan_id">
                                    <option value="">-- No background image --</option>
                                    <?php
                                    $images = mysqli_query($conn, "SELECT id, display_name FROM floor_plans WHERE company_id=$company_id AND active=1 ORDER BY display_name ASC");
                                    while ($img = mysqli_fetch_assoc($images)): ?>
                                        <option value="<?php echo $img['id']; ?>" <?php echo ((string)$displayVal === (string)$img['id']) ? 'selected' : ''; ?>><?php echo sanitize($img['display_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            <?php elseif ($name === 'it_location_id'): ?>
                                <select name="it_location_id">
                                    <option value="">-- Select Location --</option>
                                    <?php
                                    $locs = mysqli_query($conn, "SELECT id, name FROM it_locations WHERE company_id=$company_id AND active=1 ORDER BY name ASC");
                                    while ($l = mysqli_fetch_assoc($locs)): ?>
                                        <option value="<?php echo $l['id']; ?>" <?php echo ((string)$displayVal === (string)$l['id']) ? 'selected' : ''; ?>><?php echo sanitize($l['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            <?php elseif ($name === 'sq_meters'): ?>
                                <input type="number" name="sq_meters" step="0.01" value="<?php echo sanitize($displayVal); ?>">
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $crud_action === 'create' ? 'Create & Design' : 'Save & Design'; ?></button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

                <?php if ($crud_action === 'edit' && !empty($data)): ?>
                <hr style="margin:30px 0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h2>Interactive Designer</h2>
                    <div style="display:flex;gap:5px;">
                         <button class="btn btn-sm" onclick="saveToGallery('image')">🖼️ Save Image to Gallery</button>
                         <button class="btn btn-sm" onclick="saveToGallery('pdf')">📄 Save PDF to Gallery</button>
                         <button class="btn btn-sm" onclick="exportPNG()">🖼️ Export PNG</button>
                         <button class="btn btn-sm" onclick="exportPDF()">📄 Export PDF</button>
                    </div>
                </div>

                <div class="controls">
                    <div class="btn-group">
                        <button class="btn btn-sm" onclick="zoom(0.1)" title="Zoom In">➕</button>
                        <button class="btn btn-sm" onclick="zoom(-0.1)" title="Zoom Out">➖</button>
                        <button class="btn btn-sm" onclick="resetZoom()">🔄 Reset</button>
                    </div>
                    <button class="btn btn-sm" id="grid-toggle-btn" onclick="toggleGrid()">🏁 Show Grid</button>

                    <div class="form-group" style="margin:0; min-width:200px;">
                        <input type="text" id="point-filter" onkeyup="filterPoints(this.value)" placeholder="Search IP, MAC, Label, Switch..." style="padding:4px 8px; font-size:12px;">
                    </div>

                    <div class="layer-controls">
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('RJ45', this.checked)"> RJ45</label>
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('SFP', this.checked)"> Fiber</label>
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('Door', this.checked)"> Doors</label>
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('Access Point', this.checked)"> APs</label>
                        <label style="font-size:12px; border-left: 1px solid #d0d7de; padding-left: 10px;"><input type="checkbox" checked onchange="toggleComments(this.checked)"> Comments</label>
                    </div>

                    <button class="btn btn-sm btn-primary" onclick="addNewPoint()">📍 Add Network Point</button>
                    <button class="btn btn-sm btn-primary" onclick="addNewDoor()">🚪 Add Door</button>
                    <button class="btn btn-sm btn-primary" onclick="addNewAP()">🛜 Add Access Point</button>
                </div>

                <div class="designer-wrapper" id="designer-wrapper">
                    <div id="designer-container" class="designer-container">
                        <div class="grid-layer"></div>
                        <div id="floor-shape" class="floor-shape"></div>
                    </div>
                </div>

                <!-- Point Modal -->
                <div id="point-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:25px; border:1px solid #d0d7de; z-index:2000; width:95%; max-width:550px; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 8px 24px rgba(140,149,159,0.2);">
                    <h2 id="modal-title" style="margin-top:0; color:#0969da; font-size:18px;">Edit Network Point</h2>
                    <input type="hidden" id="modal-point-id">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Label</label>
                            <input type="text" id="modal-label" placeholder="e.g. Desk 42">
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select id="modal-type" onchange="toggleModalFields()">
                                <option value="">-- Select Type --</option>
                                <?php
                                $types = mysqli_query($conn, "SELECT id, type FROM switch_port_types WHERE company_id=$company_id ORDER BY type ASC");
                                while ($t = mysqli_fetch_assoc($types)): ?>
                                    <option value="<?php echo $t['id']; ?>" data-type-name="<?php echo sanitize($t['type']); ?>"><?php echo sanitize($t['type']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Switch</label>
                            <select id="modal-switch" onchange="loadPorts(this.value)">
                                <option value="">-- Select Switch --</option>
                                <?php
                                $switches = mysqli_query($conn, "SELECT id, name FROM equipment WHERE company_id=$company_id AND active=1 AND equipment_type_id IN (SELECT id FROM equipment_types WHERE name LIKE '%Switch%') ORDER BY name ASC");
                                while ($s = mysqli_fetch_assoc($switches)): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Switch Port</label>
                            <select id="modal-port"><option value="">-- Select Port --</option></select>
                        </div>
                        <div class="form-group">
                            <label>IP Address</label>
                            <input type="text" id="modal-ip">
                        </div>
                        <div class="form-group">
                            <label>MAC Address</label>
                            <input type="text" id="modal-mac">
                        </div>
                        <div class="form-group">
                            <label>WLAN Address</label>
                            <input type="text" id="modal-wlan">
                        </div>
                        <div class="form-group">
                            <label>Patch Port</label>
                            <input type="text" id="modal-patch">
                        </div>
                        <div class="form-group" id="group-rotation" style="display:none;">
                            <label>Rotation (degrees)</label>
                            <select id="modal-rotation"><option value="0">0°</option><option value="90">90°</option><option value="180">180°</option><option value="270">270°</option><option value="360">360°</option><option value="other">Other…</option></select>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Cable Color</label>
                            <select id="modal-color">
                                <option value="">-- Select Color --</option>
                                <?php
                                $colors = mysqli_query($conn, "SELECT id, color_name, hex_color FROM cable_colors WHERE company_id=$company_id ORDER BY color_name ASC");
                                while ($c = mysqli_fetch_assoc($colors)): ?>
                                    <option value="<?php echo $c['id']; ?>" data-hex="<?php echo $c['hex_color']; ?>"><?php echo sanitize($c['color_name']); ?> (<?php echo $c['hex_color']; ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:25px; border-top:1px solid #d0d7de; padding-top:15px; display:flex; justify-content: flex-end; gap:10px;">
                        <button class="btn btn-primary" onclick="savePointData()" title="Save">💾</button>
                        <button class="btn btn-danger" id="btn-delete-point" onclick="deletePointData()" title="Delete">🗑️</button>
                        <button class="btn" onclick="closeModal()" title="Cancel">🔙</button>
                    </div>
                </div>

                <script>
                    const points = <?php echo json_encode($points); ?>;
                    const floorData = <?php echo json_encode($data); ?>;
                    const container = document.getElementById('designer-container');
                    const wrapper = document.getElementById('designer-wrapper');
                    const shapeEl = document.getElementById('floor-shape');
                    let currentZoom = 1;
                    let snapToGrid = false;
                    const gridSize = 25;

                    function initDesigner() {
                        renderFloor();
                        points.forEach(p => renderPoint(p));
                        const rotSelect = document.getElementById("modal-rotation");
                        if (rotSelect) {
                            rotSelect.addEventListener("change", function() {
                                if (this.value === "other") {
                                    const val = window.prompt("Insert custom rotation in degrees:");
                                    if (val !== null && val.trim() !== "" && !isNaN(val)) {
                                        setRotationValue(parseFloat(val));
                                    } else {
                                        this.value = "0";
                                    }
                                }
                            });
                        }
                    }

                    function renderFloor() {
                        if (floorData.floor_plan_id) {
                            <?php
                            $bgUrl = '';
                            if (!empty($data['floor_plan_id'])) {
                                $fpq = mysqli_query($conn, "SELECT stored_filename, company_id FROM floor_plans WHERE id=" . (int)$data['floor_plan_id'] . " AND company_id=" . (int)$company_id);
                                if ($fpr = mysqli_fetch_assoc($fpq)) {
                                    $bgUrl = "../../floor_plans/" . (int)$fpr['company_id'] . "/" . $fpr['stored_filename'];
                                }
                            }
                            ?>
                            shapeEl.style.backgroundImage = "url('<?php echo $bgUrl; ?>')";
                            shapeEl.style.backgroundColor = "transparent";
                        } else {
                            shapeEl.style.backgroundColor = "#fff";
                        }

                        const type = floorData.shape_type;
                        shapeEl.className = 'floor-shape';
                        if (type === 'Square') shapeEl.classList.add('shape-square');
                        else if (type === 'Rectangular') shapeEl.classList.add('shape-rectangular');
                        else if (type === 'Irregular east-west walls') shapeEl.classList.add('shape-irregular-ew');
                        else if (type === 'Irregular east wall') shapeEl.classList.add('shape-irregular-e');
                        else if (type === 'Irregular west wall') shapeEl.classList.add('shape-irregular-w');
                        else if (type === 'Irregular north-south walls') shapeEl.classList.add('shape-irregular-ns');
                        else if (type === 'Irregular north wall') shapeEl.classList.add('shape-irregular-n');
                        else if (type === 'Irregular south wall') shapeEl.classList.add('shape-irregular-s');
                    }

                    function renderPoint(p) {
                        const isSfp = String(p.point_type_name || '').includes('SFP') || String(p.point_type_name || '').includes('Fiber');
                        const isDoor = String(p.point_type_name || '').includes('Door');
                        const isAP = String(p.point_type_name || '').includes('Access Point');
                        const el = document.createElement('div');
                        el.id = 'point-' + p.id;

                        if (isDoor) {
                            el.className = `point point-type-Door door`;
                            el.style.left = p.x + 'px';
                            el.style.top = p.y + 'px';
                            el.style.setProperty('--rotation', (p.rotation || 0) + 'deg');
                            el.innerHTML = `
                                <svg viewBox="0 0 100 100" width="60" height="60" style="pointer-events: none;">
                                    <path d="M 10,90 L 10,10 L 90,10 A 80,80 0 0 1 10,90" fill="none" stroke="#24292f" stroke-width="3" />
                                </svg>`;
                        } else if (isAP) {
                            el.className = `point point-type-Access-Point access-point`;
                            el.style.left = p.x + 'px';
                            el.style.top = p.y + 'px';
                            el.style.setProperty('--rotation', (p.rotation || 0) + 'deg');
                            if (p.hex_color) el.style.backgroundColor = p.hex_color;
                            el.innerHTML = `
                                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="pointer-events: none;">
                                    <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                                    <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                                    <line x1="12" y1="20" x2="12.01" y2="20"></line>
                                </svg>`;
                        } else {
                            el.className = `point point-type-${isSfp ? 'SFP' : 'RJ45'} ${isSfp ? 'sfp' : 'rj45'}`;
                            el.style.left = p.x + 'px';
                            el.style.top = p.y + 'px';
                            if (p.hex_color) el.style.backgroundColor = p.hex_color;
                            el.textContent = p.label ? p.label.substring(0, 2).toUpperCase() : p.id;
                        }

                        el.dataset.id = p.id;
                        el.dataset.search = `${p.label} ${p.ip_address} ${p.mac_address} ${p.switch_name} ${p.patch_port}`.toLowerCase();

                        makeDraggable(el, (newX, newY) => {
                            p.x = newX; p.y = newY;
                            updatePointPosition(p.id, newX, newY);
                            updateCommentAbsolutePos(p);
                        });
                        el.onclick = (e) => { e.stopPropagation(); openPointModal(p); };
                        container.appendChild(el);

                        const cb = document.createElement('div');
                        cb.id = 'comment-' + p.id;
                        cb.className = `comment-box comment-type-${isDoor ? "Door" : (isAP ? "Access-Point" : (isSfp ? "SFP" : "RJ45"))}`;
                        updateCommentAbsolutePos(p, cb);

                        let tableHtml = `<table>`;
                        if (p.label) tableHtml += `<tr class="label-row"><td colspan="2">${p.label}</td></tr>`;
                        if (p.wlan_address) tableHtml += `<tr><td>Wlan:</td><td>${p.wlan_address}</td></tr>`;
                        if (p.ip_address) tableHtml += `<tr><td>IP:</td><td>${p.ip_address}</td></tr>`;
                        if (p.mac_address) tableHtml += `<tr><td>MAC:</td><td>${p.mac_address}</td></tr>`;
                        if (p.patch_port) tableHtml += `<tr><td>Patch:</td><td>${p.patch_port}</td></tr>`;
                        if (p.switch_name) tableHtml += `<tr><td>SW:</td><td>${p.switch_name}</td></tr>`;
                        if (p.switch_port_number) tableHtml += `<tr><td>Port:</td><td>${p.switch_port_number}</td></tr>`;
                        if (p.color_name) tableHtml += `<tr><td>Color:</td><td>${p.color_name}</td></tr>`;
                        tableHtml += `</table>`;
                        cb.innerHTML = tableHtml;

                        makeDraggable(cb, (newAbsX, newAbsY) => {
                            const relX = newAbsX - p.x;
                            const relY = newAbsY - p.y;
                            p.comment_x = relX; p.comment_y = relY;
                            updateCommentRelPosition(p.id, relX, relY);
                        });
                        container.appendChild(cb);
                    }

                    function updateCommentAbsolutePos(p, cb = null) {
                        if (!cb) cb = document.getElementById('comment-' + p.id);
                        if (!cb) return;
                        cb.style.left = (parseFloat(p.x) + parseFloat(p.comment_x)) + 'px';
                        cb.style.top = (parseFloat(p.y) + parseFloat(p.comment_y)) + 'px';
                    }

                    function makeDraggable(el, onEnd) {
                        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
                        let moved = false;
                        el.onmousedown = (e) => {
                            e = e || window.event;
                            if (e.button !== 0) return;
                            e.preventDefault();
                            pos3 = e.clientX;
                            pos4 = e.clientY;
                            moved = false;
                            document.onmouseup = () => {
                                document.onmouseup = null;
                                document.onmousemove = null;
                                if (moved) onEnd(parseInt(el.style.left), parseInt(el.style.top));
                            };
                            document.onmousemove = (e) => {
                                e = e || window.event;
                                e.preventDefault();
                                moved = true;
                                pos1 = pos3 - e.clientX;
                                pos2 = pos4 - e.clientY;
                                pos3 = e.clientX;
                                pos4 = e.clientY;
                                let newX = el.offsetLeft - (pos1 / currentZoom);
                                let newY = el.offsetTop - (pos2 / currentZoom);
                                if (snapToGrid) {
                                    newX = Math.round(newX / gridSize) * gridSize;
                                    newY = Math.round(newY / gridSize) * gridSize;
                                }
                                el.style.left = newX + "px";
                                el.style.top = newY + "px";
                            };
                        };
                    }



                    function toggleGrid() {
                        snapToGrid = !snapToGrid;
                        const btn = document.getElementById('grid-toggle-btn');
                        if (snapToGrid) {
                            wrapper.classList.add('show-grid');
                            btn.textContent = '🏁 Hide Grid';
                            btn.classList.add('btn-primary');
                        } else {
                            wrapper.classList.remove('show-grid');
                            btn.textContent = '🏁 Show Grid';
                            btn.classList.remove('btn-primary');
                        }
                    }


                    function addNewPoint() {
                        openPointModal({
                            id: 0,
                            floor_designer_id: floorData.id,
                            point_type_name: 'RJ45',
                            x: 50, y: 50,
                            comment_x: 20, comment_y: 20,
                            label: '',
                            rotation: 0
                        });
                    }

                    function addNewAP() {
                        const apTypeOpt = Array.from(document.querySelectorAll('#modal-type option')).find(opt => opt.textContent.includes('Access Point'));
                        openPointModal({
                            id: 0,
                            floor_designer_id: floorData.id,
                            point_type_id: apTypeOpt ? apTypeOpt.value : 16,
                            x: 150, y: 150,
                            comment_x: 20, comment_y: 20,
                            label: 'AP',
                            rotation: 0
                        });
                    }

                    function addNewDoor() {
                        const doorTypeOpt = Array.from(document.querySelectorAll('#modal-type option')).find(opt => opt.textContent.includes('Door'));
                        openPointModal({
                            id: 0,
                            floor_designer_id: floorData.id,
                            point_type_id: doorTypeOpt ? doorTypeOpt.value : 3,
                            x: 100, y: 100,
                            comment_x: 20, comment_y: 20,
                            label: 'Door',
                            rotation: 0
                        });
                    }

                                        function setRotationValue(val) {
                        const rotSelect = document.getElementById("modal-rotation");
                        if (!rotSelect) return;
                        let exists = false;
                        for (let i = 0; i < rotSelect.options.length; i++) {
                            if (rotSelect.options[i].value == val) {
                                exists = true;
                                break;
                            }
                        }
                        if (!exists) {
                            const opt = document.createElement("option");
                            opt.value = val;
                            opt.textContent = val + "°";
                            rotSelect.insertBefore(opt, rotSelect.lastElementChild);
                        }
                        rotSelect.value = val;
                    }

                    function openPointModal(p) {
                        document.getElementById('modal-point-id').value = p.id;
                        document.getElementById('modal-label').value = p.label || '';
                        const typeSelect = document.getElementById("modal-type");
                        typeSelect.value = p.point_type_id || "";
                        if (typeSelect.value === "" && p.point_type_name) {
                            const options = Array.from(typeSelect.options);
                            const match = options.find(opt => opt.getAttribute("data-type-name") === p.point_type_name);
                            if (match) typeSelect.value = match.value;
                        }
                        document.getElementById('modal-switch').value = p.switch_id || '';
                        document.getElementById('modal-ip').value = p.ip_address || '';
                        document.getElementById('modal-mac').value = p.mac_address || '';
                        document.getElementById('modal-wlan').value = p.wlan_address || '';
                        document.getElementById('modal-patch').value = p.patch_port || '';
                        setRotationValue(p.rotation || 0);
                        document.getElementById('modal-color').value = p.cable_color_id || '';
                        document.getElementById('btn-delete-point').style.display = p.id > 0 ? 'inline-block' : 'none';
                        document.getElementById('modal-title').textContent = p.id > 0 ? 'Edit Network Point' : 'Add New Network Point';

                        if (p.switch_id) {
                            loadPorts(p.switch_id, p.switch_port_id);
                        } else {
                            document.getElementById('modal-port').innerHTML = '<option value="">-- Select Port --</option>';
                        }
                        toggleModalFields();
                        document.getElementById('point-modal').style.display = 'block';
                    }

                    function toggleModalFields() {
                        const typeSelect = document.getElementById('modal-type');
                        const typeName = typeSelect.options[typeSelect.selectedIndex]?.dataset.typeName || '';
                        const isDoor = typeName.includes('Door');
                        const isAP = typeName.includes('Access Point');

                        document.getElementById('group-rotation').style.display = (isDoor || isAP) ? 'block' : 'none';

                        const netFields = ['modal-switch', 'modal-port', 'modal-ip', 'modal-mac', 'modal-wlan', 'modal-patch', 'modal-color'];
                        netFields.forEach(id => {
                            const el = document.getElementById(id);
                            if (el && el.closest('.form-group')) {
                                el.closest('.form-group').style.display = isDoor ? 'none' : 'block';
                            }
                        });
                    }

                    function loadPorts(switchId, selectedPortId = null) {
                        const portSelect = document.getElementById('modal-port');
                        if (!switchId) {
                            portSelect.innerHTML = '<option value="">-- Select Port --</option>';
                            return;
                        }
                        portSelect.innerHTML = '<option value="">Loading...</option>';
                        const formData = new FormData();
                        formData.append('ajax_action', 'get_switch_ports');
                        formData.append('switch_id', switchId);
                        formData.append('csrf_token', ITM_CSRF_TOKEN);

                        fetch('index.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                portSelect.innerHTML = '<option value="">-- Select Port --</option>';
                                data.ports.forEach(pt => {
                                    const opt = document.createElement('option');
                                    opt.value = pt.id;
                                    opt.textContent = `Port ${pt.port_number} (${pt.port_type})`;
                                    if (selectedPortId && selectedPortId == pt.id) opt.selected = true;
                                    portSelect.appendChild(opt);
                                });
                            }
                        });
                    }

                    function closeModal() {
                        document.getElementById('point-modal').style.display = 'none';
                    }

                    function savePointData() {
                        const pointId = document.getElementById('modal-point-id').value;
                        const formData = new FormData();
                        formData.append('ajax_action', 'save_point');
                        formData.append('csrf_token', ITM_CSRF_TOKEN);
                        formData.append('point_id', pointId);
                        formData.append('floor_designer_id', floorData.id);
                        formData.append('point_type_id', document.getElementById('modal-type').value);
                        formData.append('label', document.getElementById('modal-label').value);
                        formData.append('switch_id', document.getElementById('modal-switch').value);
                        formData.append('switch_port_id', document.getElementById('modal-port').value);
                        formData.append('ip_address', document.getElementById('modal-ip').value);
                        formData.append('mac_address', document.getElementById('modal-mac').value);
                        formData.append('wlan_address', document.getElementById('modal-wlan').value);
                        formData.append('patch_port', document.getElementById('modal-patch').value);
                        formData.append('rotation', document.getElementById('modal-rotation').value);
                        formData.append('cable_color_id', document.getElementById('modal-color').value);

                        if (pointId == 0) {
                            formData.append('x', 100);
                            formData.append('y', 100);
                            formData.append('comment_x', 20);
                            formData.append('comment_y', 20);
                        }

                        fetch('index.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                location.reload();
                            } else {
                                alert('Error saving point: ' + data.error);
                            }
                        });
                    }

                    function deletePointData() {
                        if (!confirm('Delete this network point permanently?')) return;
                        const formData = new FormData();
                        formData.append('ajax_action', 'delete_point');
                        formData.append('point_id', document.getElementById('modal-point-id').value);
                        formData.append('csrf_token', ITM_CSRF_TOKEN);
                        fetch('index.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => { if (data.ok) location.reload(); });
                    }

                    function updatePointPosition(id, x, y) {
                        const formData = new FormData();
                        formData.append('ajax_action', 'update_point_pos');
                        formData.append('point_id', id);
                        formData.append('x', x);
                        formData.append('y', y);
                        formData.append('csrf_token', ITM_CSRF_TOKEN);
                        fetch('index.php', { method: 'POST', body: formData });
                    }

                    function updateCommentRelPosition(id, rx, ry) {
                        const formData = new FormData();
                        formData.append('ajax_action', 'update_comment_pos');
                        formData.append('point_id', id);
                        formData.append('comment_x', rx);
                        formData.append('comment_y', ry);
                        formData.append('csrf_token', ITM_CSRF_TOKEN);
                        fetch('index.php', { method: 'POST', body: formData });
                    }


                    initDesigner();
                </script>
                <?php endif; ?>

            <?php elseif ($crud_action === 'view'): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h1>Floor: <?php echo sanitize($data['name']); ?></h1>
                    <div style="display:flex;gap:5px;">
                        <button class="btn btn-sm" onclick="saveToGallery('image')">🖼️ Save Image to Gallery</button>
                         <button class="btn btn-sm" onclick="saveToGallery('pdf')">📄 Save PDF to Gallery</button>
                         <button class="btn btn-sm" onclick="exportPNG()">🖼️ Export PNG</button>
                         <button class="btn btn-sm" onclick="exportPDF()">📄 Export PDF</button>
                         <a class="btn btn-sm btn-primary" href="edit.php?id=<?php echo (int)$data['id']; ?>" title="Edit">✏️</a>
                         <a href="index.php" class="btn btn-sm" title="Back">🔙</a>
                    </div>
                </div>

                <div class="controls">
                    <div class="btn-group">
                        <button class="btn btn-sm" onclick="zoom(0.1)" title="Zoom In">➕</button>
                        <button class="btn btn-sm" onclick="zoom(-0.1)" title="Zoom Out">➖</button>
                        <button class="btn btn-sm" onclick="resetZoom()">🔄 Reset</button>
                    </div>

                    <div class="form-group" style="margin:0; min-width:200px;">
                        <input type="text" id="point-filter" onkeyup="filterPoints(this.value)" placeholder="Search IP, MAC, Label, Switch..." style="padding:4px 8px; font-size:12px;">
                    </div>

                    <div class="layer-controls">
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('RJ45', this.checked)"> RJ45</label>
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('SFP', this.checked)"> Fiber</label>
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('Door', this.checked)"> Doors</label>
                        <label style="font-size:12px;"><input type="checkbox" checked onchange="toggleLayer('Access Point', this.checked)"> APs</label>
                        <label style="font-size:12px; border-left: 1px solid #d0d7de; padding-left: 10px;"><input type="checkbox" checked onchange="toggleComments(this.checked)"> Comments</label>
                    </div>
                </div>

                <div class="designer-wrapper" id="designer-wrapper">
                    <div id="designer-container" class="designer-container">
                        <div id="floor-shape" class="floor-shape"></div>
                    </div>
                </div>

                <script>
                    const points = <?php echo json_encode($points); ?>;
                    const floorData = <?php echo json_encode($data); ?>;
                    const container = document.getElementById('designer-container');
                    const wrapper = document.getElementById('designer-wrapper');
                    const shapeEl = document.getElementById('floor-shape');
                    let currentZoom = 1;

                    function initDesigner() {
                        renderFloor();
                        points.forEach(p => renderPoint(p));
                        const rotSelect = document.getElementById("modal-rotation");
                        if (rotSelect) {
                            rotSelect.addEventListener("change", function() {
                                if (this.value === "other") {
                                    const val = window.prompt("Insert custom rotation in degrees:");
                                    if (val !== null && val.trim() !== "" && !isNaN(val)) {
                                        setRotationValue(parseFloat(val));
                                    } else {
                                        this.value = "0";
                                    }
                                }
                            });
                        }
                    }

                    function renderFloor() {
                        if (floorData.floor_plan_id) {
                            <?php
                            $bgUrl = '';
                            if (!empty($data['floor_plan_id'])) {
                                $fpq = mysqli_query($conn, "SELECT stored_filename, company_id FROM floor_plans WHERE id=" . (int)$data['floor_plan_id'] . " AND company_id=" . (int)$company_id);
                                if ($fpr = mysqli_fetch_assoc($fpq)) {
                                    $bgUrl = "../../floor_plans/" . (int)$fpr['company_id'] . "/" . $fpr['stored_filename'];
                                }
                            }
                            ?>
                            shapeEl.style.backgroundImage = "url('<?php echo $bgUrl; ?>')";
                            shapeEl.style.backgroundColor = "transparent";
                        }
                        const type = floorData.shape_type;
                        shapeEl.className = 'floor-shape';
                        if (type === 'Square') shapeEl.classList.add('shape-square');
                        else if (type === 'Rectangular') shapeEl.classList.add('shape-rectangular');
                        else if (type === 'Irregular east-west walls') shapeEl.classList.add('shape-irregular-ew');
                        else if (type === 'Irregular east wall') shapeEl.classList.add('shape-irregular-e');
                        else if (type === 'Irregular west wall') shapeEl.classList.add('shape-irregular-w');
                        else if (type === 'Irregular north-south walls') shapeEl.classList.add('shape-irregular-ns');
                        else if (type === 'Irregular north wall') shapeEl.classList.add('shape-irregular-n');
                        else if (type === 'Irregular south wall') shapeEl.classList.add('shape-irregular-s');
                    }

                    function renderPoint(p) {
                        const isSfp = String(p.point_type_name || '').includes('SFP') || String(p.point_type_name || '').includes('Fiber');
                        const isDoor = String(p.point_type_name || '').includes('Door');
                        const isAP = String(p.point_type_name || '').includes('Access Point');
                        const el = document.createElement('div');
                        el.id = 'point-' + p.id;

                        if (isDoor) {
                            el.className = `point point-type-Door door`;
                            el.style.left = p.x + 'px';
                            el.style.top = p.y + 'px';
                            el.style.setProperty('--rotation', (p.rotation || 0) + 'deg');
                            el.innerHTML = `
                                <svg viewBox="0 0 100 100" width="60" height="60" style="pointer-events: none;">
                                    <path d="M 10,90 L 10,10 L 90,10 A 80,80 0 0 1 10,90" fill="none" stroke="#24292f" stroke-width="3" />
                                    <rect x="0" y="85" width="20" height="10" fill="#333" />
                                    <rect x="80" y="85" width="20" height="10" fill="#333" />
                                </svg>`;
                        } else if (isAP) {
                            el.className = `point point-type-Access-Point access-point`;
                            el.style.left = p.x + 'px';
                            el.style.top = p.y + 'px';
                            el.style.setProperty('--rotation', (p.rotation || 0) + 'deg');
                            if (p.hex_color) el.style.backgroundColor = p.hex_color;
                            el.innerHTML = `
                                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="pointer-events: none;">
                                    <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                                    <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                                    <line x1="12" y1="20" x2="12.01" y2="20"></line>
                                </svg>`;
                        } else {
                            el.className = `point point-type-${isSfp ? 'SFP' : 'RJ45'} ${isSfp ? 'sfp' : 'rj45'}`;
                            el.style.left = p.x + 'px';
                            el.style.top = p.y + 'px';
                            if (p.hex_color) el.style.backgroundColor = p.hex_color;
                            el.textContent = p.label ? p.label.substring(0, 2).toUpperCase() : p.id;
                        }

                        el.dataset.id = p.id;
                        el.dataset.search = `${p.label} ${p.ip_address} ${p.mac_address} ${p.switch_name} ${p.patch_port}`.toLowerCase();
                        container.appendChild(el);

                        const cb = document.createElement('div');
                        cb.id = 'comment-' + p.id;
                        cb.className = `comment-box comment-type-${isDoor ? 'Door' : (isAP ? 'Access-Point' : (isSfp ? 'SFP' : 'RJ45'))}`;
                        cb.style.left = (parseFloat(p.x) + parseFloat(p.comment_x)) + 'px';
                        cb.style.top = (parseFloat(p.y) + parseFloat(p.comment_y)) + 'px';

                        let tableHtml = `<table>`;
                        if (p.label) tableHtml += `<tr class="label-row"><td colspan="2">${p.label}</td></tr>`;
                        if (p.wlan_address) tableHtml += `<tr><td>Wlan:</td><td>${p.wlan_address}</td></tr>`;
                        if (p.ip_address) tableHtml += `<tr><td>IP:</td><td>${p.ip_address}</td></tr>`;
                        if (p.mac_address) tableHtml += `<tr><td>MAC:</td><td>${p.mac_address}</td></tr>`;
                        if (p.patch_port) tableHtml += `<tr><td>Patch:</td><td>${p.patch_port}</td></tr>`;
                        if (p.switch_name) tableHtml += `<tr><td>SW:</td><td>${p.switch_name}</td></tr>`;
                        if (p.switch_port_number) tableHtml += `<tr><td>Port:</td><td>${p.switch_port_number}</td></tr>`;
                        if (p.color_name) tableHtml += `<tr><td>Color:</td><td>${p.color_name}</td></tr>`;
                        tableHtml += `</table>`;
                        cb.innerHTML = tableHtml;
                        container.appendChild(cb);
                    }

                    initDesigner();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../../js/select-add-option.js"></script>
<script src="../../js/table-tools.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) { indicator.textContent = event.target.checked ? '✅' : '❌'; }
});
</script>
<script>
                    function zoom(delta) {
                        currentZoom = Math.max(0.1, Math.min(3, currentZoom + delta));
                        container.style.transform = 'scale(' + currentZoom + ')';
                    }

                    function resetZoom() {
                        currentZoom = 1;
                        container.style.transform = 'scale(1)';
                    }

                    function toggleLayer(typeName, show) {
                        const cls = typeName.replace(/ /g, '-');
                        const points = document.querySelectorAll('.point-type-' + cls);
                        const comments = document.querySelectorAll('.comment-type-' + cls);
                        const display = show ? '' : 'none';
                        points.forEach(el => el.style.display = display);
                        comments.forEach(el => el.style.display = display);
                    }

                    function toggleComments(show) {
                        const comments = document.querySelectorAll(".comment-box");
                        const display = show ? "" : "none";
                        comments.forEach(el => el.style.display = display);
                    }

                    function filterPoints(val) {
                        const query = val.toLowerCase();
                        const points = document.querySelectorAll('.point');
                        points.forEach(el => {
                            const match = el.dataset.search.includes(query);
                            const comment = document.getElementById('comment-' + el.dataset.id);
                            if (match) {
                                el.classList.remove('is-filtered');
                                if (comment) comment.classList.remove('is-filtered');
                            } else {
                                el.classList.add('is-filtered');
                                if (comment) comment.classList.add('is-filtered');
                            }
                        });
                    }

                    function exportPNG() {
                        const originalZoom = currentZoom;
                        resetZoom();
                        const el = document.getElementById('designer-container');
                        html2canvas(el, { backgroundColor: '#f0f2f5', scale: 2 }).then(canvas => {
                            const link = document.createElement('a');
                            link.download = 'floor_plan_' + floorData.name + '_' + new Date().getTime() + '.png';
                            link.href = canvas.toDataURL('image/png');
                            link.click();
                            currentZoom = originalZoom;
                            container.style.transform = 'scale(' + currentZoom + ')';
                        });
                    }

                    function saveToGallery(format = 'image') {
                        const originalZoom = currentZoom;
                        resetZoom();
                        const el = document.getElementById('designer-container');

                        setTimeout(() => {
                            html2canvas(el, { backgroundColor: '#f0f2f5', scale: 2 }).then(canvas => {
                                let fileData = '';
                                let extension = 'png';

                                if (format === 'pdf') {
                                    const { jsPDF } = window.jspdf;
                                    const pdf = new jsPDF('l', 'px', [canvas.width, canvas.height]);
                                    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, canvas.width, canvas.height);
                                    fileData = pdf.output('datauristring');
                                    extension = 'pdf';
                                } else {
                                    fileData = canvas.toDataURL('image/png');
                                    extension = 'png';
                                }

                                const formData = new FormData();
                                formData.append('ajax_action', 'save_as_floor_plan');
                                formData.append('data', fileData);
                                formData.append('ext', extension);
                                formData.append('name', floorData.name + ' Designer Export ' + new Date().toLocaleString());
                                formData.append('csrf_token', ITM_CSRF_TOKEN);

                                fetch('index.php', { method: 'POST', body: formData })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.ok) {
                                        alert('Successfully saved to Floor Plans gallery!');
                                    } else {
                                        alert('Error saving to gallery: ' + data.error);
                                    }
                                    currentZoom = originalZoom;
                                    container.style.transform = 'scale(' + currentZoom + ')';
                                })
                                .catch(err => {
                                    alert('Network error while saving to gallery.');
                                    currentZoom = originalZoom;
                                    container.style.transform = 'scale(' + currentZoom + ')';
                                });
                            });
                        }, 100);
                    }

                    function exportPDF() {
                        const originalZoom = currentZoom;
                        resetZoom();
                        const el = document.getElementById('designer-container');
                        html2canvas(el, { backgroundColor: '#f0f2f5', scale: 2 }).then(canvas => {
                            const imgData = canvas.toDataURL('image/png');
                            const { jsPDF } = window.jspdf;
                            const pdf = new jsPDF('l', 'px', [canvas.width, canvas.height]);
                            pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
                            pdf.save('floor_plan_' + floorData.name + '_' + new Date().getTime() + '.pdf');
                            currentZoom = originalZoom;
                            container.style.transform = 'scale(' + currentZoom + ')';
                        });
                    }
</script>
</body>
</html>
