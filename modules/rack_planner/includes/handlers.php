<?php
// Handle Excel/CSV database import requests from table-tools.js.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true)) {
    $itm_content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim((string) $_SERVER['CONTENT_TYPE'])) : '';
    if (strpos($itm_content_type, 'application/json') === 0) {
        $itm_raw_body = file_get_contents('php://input');
        $itm_payload = json_decode($itm_raw_body, true);

        if (is_array($itm_payload) && isset($itm_payload['import_excel_rows'])) {
            itm_handle_json_table_import($conn, $crud_table, (int)($company_id ?? 0));
            exit;
        }
    }
}

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$errors = [];
$success_msg = '';

if (isset($_SESSION['crud_error'])) {
    $errors[] = $_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
if (isset($_SESSION['crud_success'])) {
    $success_msg = $_SESSION['crud_success'];
    unset($_SESSION['crud_success']);
}

$catalogOptions = rack_planner_fetch_catalog_options($conn, $company_id);
$equipmentPickerOptions = rack_planner_fetch_equipment_picker_options($conn, $company_id);
$combinedCodeMeta = rack_planner_combined_code_meta_map($catalogOptions, $equipmentPickerOptions);

// Handle Delete
if ($crud_action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $logged_employee_id = (int)($_SESSION['employee_id'] ?? 0);

    // Handle Clear Table Bulk Action
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'clear_table') {
        $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET active = 0, deleted_by = ?, deleted_at = CURRENT_TIMESTAMP WHERE company_id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'ii', $logged_employee_id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Table cleared.';
        } else {
            $_SESSION['crud_error'] = 'Error clearing table.';
        }
        mysqli_stmt_close($stmt);
        header('Location: index.php');
        exit;
    }

    // Handle Bulk Delete
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET active = 0, deleted_by = ?, deleted_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders) AND company_id = ? AND deleted_at IS NULL");
            $types = 'i' . str_repeat('i', count($ids)) . 'i';
            $params = [$logged_employee_id];
            foreach ($ids as $val) {
                $params[] = (int)$val;
            }
            $params[] = $company_id;
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_success'] = 'Selected plans deleted.';
            } else {
                $_SESSION['crud_error'] = 'Error deleting plans.';
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: index.php');
        exit;
    }

    // Single Delete
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET active = 0, deleted_by = ?, deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'iii', $logged_employee_id, $id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Rack plan deleted.';
        } else {
            $_SESSION['crud_error'] = 'Error deleting rack plan.';
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: index.php');
    exit;
}

// Handle Save (Create/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true) && isset($_POST['ajax_update_layout'])) {
    require_once ROOT_PATH . 'includes/itm_api_json_response.php';
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $rackUnits = max(1, min(100, (int)($_POST['rack_units'] ?? 42)));
    $layoutRaw = (string)($_POST['layout_json'] ?? '');
    $normalizedLayout = rack_planner_normalize_layout_json($layoutRaw, $rackUnits, $combinedCodeMeta);
    $layoutJson = rack_planner_encode_layout($normalizedLayout);
    $totalAmount = rack_planner_layout_total($normalizedLayout);

    if ($id <= 0) {
        itm_api_json_response([
            'success' => false,
            'message' => 'Create mode requires Save before auto-save.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], 400);
    }

    $logged_employee_id = (int)($_SESSION['employee_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET layout_json = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
    if (!$stmt) {
        itm_api_json_response([
            'success' => false,
            'message' => 'Unable to prepare auto-save.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], 500);
    }

    mysqli_stmt_bind_param($stmt, 'siii', $layoutJson, $logged_employee_id, $id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    $updatedRows = $ok ? mysqli_stmt_affected_rows($stmt) : 0;
    mysqli_stmt_close($stmt);

    if (!$ok) {
        itm_api_json_response([
            'success' => false,
            'message' => 'Auto-save failed.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], 500);
    }

    if ($updatedRows <= 0) {
        itm_api_json_response([
            'success' => false,
            'message' => 'Rack plan not found or not permitted.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], 404);
    }

    $sourcePriceSyncOk = true;
    if ($updatedRows > 0) {
        $sourcePriceSyncOk = rack_planner_sync_source_prices_from_layout($conn, $company_id, $normalizedLayout);
    }
    itm_api_json_response([
        'success' => true,
        'message' => $sourcePriceSyncOk ? 'Auto-saved.' : 'Auto-saved with source price sync warning.',
        'layout_json' => $layoutJson,
        'total_amount' => number_format($totalAmount, 2, '.', ''),
        'source_price_sync' => $sourcePriceSyncOk,
    ], 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'])) {
    itm_require_post_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $rack_units = max(1, min(100, (int)($_POST['rack_units'] ?? 42)));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $status_id = (int)($_POST['status_id'] ?? 0);
    $active = 1; // Always hidden 1
    $layout_raw = (string)($_POST['layout_json'] ?? '');
    $normalizedLayout = rack_planner_normalize_layout_json($layout_raw, $rack_units, $combinedCodeMeta);
    $layout_json = rack_planner_encode_layout($normalizedLayout);

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($status_id <= 0) {
        $errors[] = 'Status is required.';
    }

    if (empty($errors)) {
        $logged_employee_id = (int)($_SESSION['employee_id'] ?? 0);
        if ($crud_action === 'create') {
            $stmt = mysqli_prepare($conn, "INSERT INTO rack_planner (company_id, employee_id, name, rack_units, layout_json, notes, status_id, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iiisssiii', $company_id, $logged_employee_id, $name, $rack_units, $layout_json, $notes, $status_id, $active, $logged_employee_id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET name = ?, rack_units = ?, layout_json = ?, notes = ?, status_id = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
            mysqli_stmt_bind_param($stmt, 'sissiiiii', $name, $rack_units, $layout_json, $notes, $status_id, $active, $logged_employee_id, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $affectedRows = mysqli_stmt_affected_rows($stmt);
            $sourcePriceSyncOk = true;
            if ($crud_action === 'create' || $affectedRows > 0) {
                $sourcePriceSyncOk = rack_planner_sync_source_prices_from_layout($conn, $company_id, $normalizedLayout);
            }
            $_SESSION['crud_success'] = $sourcePriceSyncOk ? 'Rack plan saved.' : 'Rack plan saved with source price sync warning.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Error saving rack plan: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Add Sample Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($crud_action === 'index' || $crud_action === 'list_all') && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    $name = 'Core Rack A';
    $units = 42;
    $json = '{"version":1,"units":42,"devices":[]}';
    $notes = 'Sample empty rack plan.';
    $active = 1;
    $logged_employee_id = (int)($_SESSION['employee_id'] ?? 0);

    // Find default active status for sample data
    $sampleStatusId = 0;
    $statusQuery = "SELECT id FROM rack_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1";
    $stmtStatus = mysqli_prepare($conn, $statusQuery);
    if ($stmtStatus) {
        mysqli_stmt_bind_param($stmtStatus, 'i', $company_id);
        mysqli_stmt_execute($stmtStatus);
        $resStatus = mysqli_stmt_get_result($stmtStatus);
        if ($rowStatus = mysqli_fetch_assoc($resStatus)) {
            $sampleStatusId = (int)$rowStatus['id'];
        }
        mysqli_stmt_close($stmtStatus);
    }
    if ($sampleStatusId === 0) {
        $statusQuery = "SELECT id FROM rack_statuses WHERE company_id = ? ORDER BY id ASC LIMIT 1";
        $stmtStatus = mysqli_prepare($conn, $statusQuery);
        if ($stmtStatus) {
            mysqli_stmt_bind_param($stmtStatus, 'i', $company_id);
            mysqli_stmt_execute($stmtStatus);
            $resStatus = mysqli_stmt_get_result($stmtStatus);
            if ($rowStatus = mysqli_fetch_assoc($resStatus)) {
                $sampleStatusId = (int)$rowStatus['id'];
            }
            mysqli_stmt_close($stmtStatus);
        }
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO rack_planner (company_id, employee_id, name, rack_units, layout_json, notes, status_id, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iiisssiii', $company_id, $logged_employee_id, $name, $units, $json, $notes, $sampleStatusId, $active, $logged_employee_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}
