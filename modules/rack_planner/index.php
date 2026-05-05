<?php
/**
 * Rack Planner Module
 *
 * Standard CRUD for Rack Planner with custom visualization.
 */

require '../../config/config.php';

$crud_table = 'rack_planner';
$crud_title = 'Rack Planner';
$crud_action = $crud_action ?? 'index';

function rack_planner_component_catalog(): array
{
    return [
        'empty' => ['label' => ' - Empty - ', 'size' => 1],
        'pp24' => ['label' => '24-Port Patchpanel Cat.6a', 'size' => 1],
        'pp48' => ['label' => '48-Port Patchpanel Cat.6a', 'size' => 1],
        'ppfo24' => ['label' => '24-Port Patchpanel Fiber Optic', 'size' => 1],
        'ppfo48' => ['label' => '48-Port Patchpanel Fiber Optic', 'size' => 1],
        'sw24' => ['label' => '24-Port Switch', 'size' => 1],
        'sw48' => ['label' => '48-Port Switch', 'size' => 1],
        'bs' => ['label' => '1-RU Blade Server', 'size' => 1],
        'bs_2' => ['label' => '2-RU Blade Server', 'size' => 2],
        'ds' => ['label' => '1-RU Data Storage', 'size' => 1],
        'rt' => ['label' => '1-RU Router', 'size' => 1],
        'tr_2' => ['label' => '2-RU Rack Tray', 'size' => 2],
        'ph' => ['label' => '1-RU Placeholder', 'size' => 1],
        'ph_2' => ['label' => '2-RU Placeholder', 'size' => 2],
    ];
}

function rack_planner_component_groups(): array
{
    return [
        'Empty' => ['empty'],
        'Patchpanel' => ['pp24', 'pp48', 'ppfo24', 'ppfo48'],
        'Switch' => ['sw24', 'sw48'],
        'Server' => ['bs', 'bs_2'],
        'Other devices' => ['ds', 'rt', 'tr_2', 'ph', 'ph_2'],
    ];
}

function rack_planner_is_two_ru_name(string $name): bool
{
    return (bool)preg_match('/\b2\s*-\s*ru\b|\b2\s*ru\b/i', $name);
}

function rack_planner_fetch_catalog_options(mysqli $conn, int $companyId): array
{
    $options = [];
    $seen = [];
    if ($companyId <= 0) {
        return $options;
    }

    $sql = "SELECT c.id, c.model, c.price, et.name AS equipment_type_name
            FROM catalogs c
            LEFT JOIN equipment_types et ON et.id = c.equipment_type_id
            WHERE c.company_id = ? AND c.active = 1
            ORDER BY c.model ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $options;
    }

    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $model = trim((string)($row['model'] ?? ''));
        if ($model === '') {
            continue;
        }

        $equipmentType = trim((string)($row['equipment_type_name'] ?? ''));
        if ($equipmentType === '') {
            $equipmentType = 'Other';
        }

        $priceText = 'N/A';
        $priceValue = null;
        if (isset($row['price']) && $row['price'] !== null && $row['price'] !== '') {
            $priceValue = (float)$row['price'];
            $priceText = number_format($priceValue, 2, '.', ',');
        }

        $size = rack_planner_is_two_ru_name($model) ? 2 : 1;
        $code = 'catalog:' . (int)$row['id'];
        $selectText = $model . ' - ' . $equipmentType . ' - ' . $priceText;
        $dedupeKey = strtolower($model . '|' . $equipmentType . '|' . $priceText);
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;

        $options[] = [
            'code' => $code,
            'label' => $model,
            'select_text' => $selectText,
            'size' => $size,
            'model' => $model,
            'equipment_type' => $equipmentType,
            'price' => $priceText,
            'price_value' => $priceValue,
        ];
    }
    mysqli_stmt_close($stmt);

    return $options;
}

function rack_planner_catalog_code_meta_map(array $catalogOptions): array
{
    $map = [];
    foreach ($catalogOptions as $catalogOption) {
        if (!is_array($catalogOption)) {
            continue;
        }

        $code = trim((string)($catalogOption['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $size = ((int)($catalogOption['size'] ?? 1) === 2) ? 2 : 1;
        $label = trim((string)($catalogOption['label'] ?? ''));
        if ($label === '') {
            $label = $code;
        }

        $map[$code] = [
            'label' => trim((string)($catalogOption['select_text'] ?? $label)),
            'size' => $size,
            'price' => isset($catalogOption['price_value']) && is_numeric($catalogOption['price_value']) ? (float)$catalogOption['price_value'] : null,
        ];
    }

    return $map;
}

function rack_planner_normalize_layout_json(string $layoutJson, int $rackUnits, array $catalogCodeMeta = []): array
{
    $units = max(1, min(100, $rackUnits));
    $catalog = rack_planner_component_catalog();

    $decoded = json_decode($layoutJson, true);
    $rawDevices = [];
    if (is_array($decoded) && isset($decoded['devices']) && is_array($decoded['devices'])) {
        $rawDevices = $decoded['devices'];
    }

    $devices = [];
    $occupied = [];

    foreach ($rawDevices as $rawDevice) {
        if (!is_array($rawDevice)) {
            continue;
        }

        $code = trim((string)($rawDevice['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $meta = null;
        if (isset($catalog[$code])) {
            $meta = $catalog[$code];
        } elseif (isset($catalogCodeMeta[$code])) {
            $meta = $catalogCodeMeta[$code];
        } elseif (strpos($code, 'catalog:') === 0) {
            $meta = [
                'label' => trim((string)($rawDevice['label'] ?? $code)),
                'size' => (int)($rawDevice['size'] ?? 1),
            ];
        } else {
            continue;
        }

        $size = (int)($meta['size'] ?? 1);
        if ($size !== 2) {
            $size = 1;
        }

        $startU = (int)($rawDevice['start_u'] ?? ($rawDevice['unit'] ?? 0));
        if ($startU < 1 || ($startU + $size - 1) > $units) {
            continue;
        }

        $canPlace = true;
        for ($u = $startU; $u < $startU + $size; $u++) {
            if (isset($occupied[$u])) {
                $canPlace = false;
                break;
            }
        }
        if (!$canPlace) {
            continue;
        }

        for ($u = $startU; $u < $startU + $size; $u++) {
            $occupied[$u] = true;
        }

        $label = trim((string)($rawDevice['label'] ?? ''));
        if ($label === '') {
            $label = trim((string)($meta['label'] ?? ''));
        }
        if (strpos($code, 'catalog:') === 0 && trim((string)($meta['label'] ?? '')) !== '') {
            // Keep catalog labels standardized as "Model - Equipment Type - Price".
            $label = trim((string)$meta['label']);
        }
        if ($label === '') {
            $label = $code;
        }

        $price = null;
        if (isset($meta['price']) && is_numeric($meta['price'])) {
            $price = (float)$meta['price'];
        } elseif (isset($rawDevice['price']) && is_numeric($rawDevice['price'])) {
            $price = (float)$rawDevice['price'];
        }

        $devices[] = [
            'code' => $code,
            'label' => $label,
            'start_u' => $startU,
            'size' => $size,
            'price' => $price,
        ];
    }

    usort($devices, static function (array $a, array $b): int {
        return $b['start_u'] <=> $a['start_u'];
    });

    return [
        'version' => 1,
        'units' => $units,
        'devices' => $devices,
    ];
}

function rack_planner_layout_total(array $layout): float
{
    $total = 0.0;
    if (!isset($layout['devices']) || !is_array($layout['devices'])) {
        return $total;
    }

    foreach ($layout['devices'] as $device) {
        if (!is_array($device)) {
            continue;
        }
        if (!isset($device['price']) || !is_numeric($device['price'])) {
            continue;
        }
        $total += (float)$device['price'];
    }

    return $total;
}

function rack_planner_encode_layout(array $layout): string
{
    $json = json_encode($layout, JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        return '{"version":1,"units":42,"devices":[]}';
    }
    return $json;
}

function rack_planner_assignments_by_unit(array $layout): array
{
    $assignments = [];
    foreach ($layout['devices'] as $device) {
        $startU = (int)$device['start_u'];
        $size = (int)$device['size'];
        for ($u = $startU; $u < $startU + $size; $u++) {
            $assignments[$u] = [
                'code' => (string)$device['code'],
                'label' => (string)$device['label'],
                'start_u' => $startU,
                'size' => $size,
            ];
        }
    }
    return $assignments;
}

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
$catalogCodeMeta = rack_planner_catalog_code_meta_map($catalogOptions);

// Handle Delete
if ($crud_action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    // Handle Clear Table Bulk Action
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'clear_table') {
        $stmt = mysqli_prepare($conn, "DELETE FROM rack_planner WHERE company_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
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
            $stmt = mysqli_prepare($conn, "DELETE FROM rack_planner WHERE id IN ($placeholders) AND company_id = ?");
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_map('intval', $ids);
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
        $stmt = mysqli_prepare($conn, "DELETE FROM rack_planner WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
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
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $rackUnits = max(1, min(100, (int)($_POST['rack_units'] ?? 42)));
    $layoutRaw = (string)($_POST['layout_json'] ?? '');
    $normalizedLayout = rack_planner_normalize_layout_json($layoutRaw, $rackUnits, $catalogCodeMeta);
    $layoutJson = rack_planner_encode_layout($normalizedLayout);
    $totalAmount = rack_planner_layout_total($normalizedLayout);

    if ($id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Create mode requires Save before auto-save.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET layout_json = ? WHERE id = ? AND company_id = ?");
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to prepare auto-save.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'sii', $layoutJson, $id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode([
            'success' => false,
            'message' => 'Auto-save failed.',
            'layout_json' => $layoutJson,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Auto-saved.',
        'layout_json' => $layoutJson,
        'total_amount' => number_format($totalAmount, 2, '.', ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'])) {
    itm_require_post_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $rack_units = max(1, min(100, (int)($_POST['rack_units'] ?? 42)));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $layout_raw = (string)($_POST['layout_json'] ?? '');
    $layout_json = rack_planner_encode_layout(rack_planner_normalize_layout_json($layout_raw, $rack_units, $catalogCodeMeta));

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $stmt = mysqli_prepare($conn, "INSERT INTO rack_planner (company_id, name, rack_units, layout_json, notes, active) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isissi', $company_id, $name, $rack_units, $layout_json, $notes, $active);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET name = ?, rack_units = ?, layout_json = ?, notes = ?, active = ? WHERE id = ? AND company_id = ?");
            mysqli_stmt_bind_param($stmt, 'sissiii', $name, $rack_units, $layout_json, $notes, $active, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Rack plan saved.';
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
    $stmt = mysqli_prepare($conn, "INSERT INTO rack_planner (company_id, name, rack_units, layout_json, notes, active) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isissi', $company_id, $name, $units, $json, $notes, $active);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

// Data Fetching
$data = ['id' => 0, 'name' => '', 'rack_units' => 42, 'layout_json' => '{"version":1,"units":42,"devices":[]}', 'notes' => '', 'active' => 1];
if (in_array($crud_action, ['edit', 'view'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM rack_planner WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $data = $row;
        } else {
            $_SESSION['crud_error'] = 'Rack plan not found.';
            header('Location: index.php');
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

$componentCatalog = rack_planner_component_catalog();
$componentGroups = rack_planner_component_groups();
$normalizedLayout = rack_planner_normalize_layout_json((string)($data['layout_json'] ?? ''), (int)($data['rack_units'] ?? 42), $catalogCodeMeta);
$data['layout_json'] = rack_planner_encode_layout($normalizedLayout);
$rackAssignmentsByUnit = rack_planner_assignments_by_unit($normalizedLayout);
$layoutTotalAmount = rack_planner_layout_total($normalizedLayout);

$search = trim((string)($_GET['search'] ?? ''));
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$ui_config = itm_get_ui_configuration($conn, $company_id);
$perPage = itm_resolve_records_per_page($ui_config);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        /* Rack Visualizer Styles from image.png */
        .rack-visualizer-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: var(--bg-secondary);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-top: 20px;
        }
        .rack-visualizer-top {
            width: 600px;
            height: 35px;
            background: #f2f2f2;
            border: 1px solid #d9d9d9;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            position: relative;
        }
        .rack-visualizer-top::after {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            bottom: 0;
            border: 1px solid #e0e0e0;
            border-bottom: none;
            border-radius: 2px 2px 0 0;
        }
        .rack-visualizer-frame {
            width: 600px;
            background: #fff;
            border: 1px solid #d9d9d9;
            padding: 20px 50px;
            position: relative;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.02);
        }
        .rack-visualizer-rail {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 24px;
            background: #e6e6e6;
            border: 1px solid #d0d0d0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 2px;
            gap: 0;
        }
        .rack-visualizer-rail-left { left: 55px; }
        .rack-visualizer-rail-right { right: 55px; }
        .rack-visualizer-rail-unit {
            width: 100%;
            height: 40px;
            background-image: url('../../assets/unit_empty.svg');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 100% 100%;
        }
        .rack-visualizer-content {
            border: 1px solid #f0f0f0;
            min-height: 100px;
        }
        .rack-visualizer-u {
            height: 40px;
            border-bottom: 1px dashed #d9d9d9;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: #ccc;
            font-size: 10px;
            background-image: url('../../assets/unit_empty.svg');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 100% 100%;
        }
        .rack-visualizer-u.has-device {
            color: #1f2937;
        }
        .rack-visualizer-u.has-device-anchor {
            z-index: 2;
            cursor: move;
        }
        .rack-visualizer-u-label {
            display: none;
            max-width: 88%;
            font-size: 11px;
            line-height: 1.2;
            color: #1f2937;
            background: rgba(255,255,255,0.85);
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 3px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            pointer-events: none;
        }
        .rack-visualizer-u.has-device .rack-visualizer-u-label {
            display: inline-block;
        }
        .rack-visualizer-u.has-device-anchor[data-device-size="2"] .rack-visualizer-u-label {
            position: absolute;
            left: 6%;
            width: 88%;
            max-width: none;
            height: calc(80px - 2px);
            top: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: normal;
            text-align: center;
            border-radius: 10px;
            z-index: 3;
        }
        .rack-visualizer-u::before {
            content: attr(data-u);
            position: absolute;
            left: -40px;
            color: #999;
        }
        .rack-visualizer-base {
            width: 600px;
            height: 70px;
            background: #f2f2f2;
            border: 1px solid #d9d9d9;
            border-top: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 0 0 4px 4px;
        }
        .rack-visualizer-vents {
            display: flex;
            gap: 6px;
        }
        .rack-visualizer-vent {
            width: 6px;
            height: 35px;
            background: #d9d9d9;
            border-radius: 3px;
        }
        .rack-visualizer-feet {
            width: 520px;
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
        .rack-visualizer-foot {
            width: 90px;
            height: 12px;
            background: #e6e6e6;
            border: 1px solid #d9d9d9;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }

        .rack-planner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .rack-unit-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 15px;
        }
        .rack-unit-modal-overlay.is-open { display: flex; }
        .rack-unit-modal {
            width: min(520px, 100%);
            background: #fff;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
        }
        .rack-unit-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #e5e5e5;
            background: #f5f5f5;
            font-weight: 600;
        }
        .rack-unit-modal-close {
            border: 0;
            background: transparent;
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }
        .rack-unit-modal-body { padding: 14px 16px 18px; }
        .rack-unit-modal-row { display: flex; align-items: center; gap: 10px; }
        .rack-unit-modal-row label { font-weight: 600; min-width: 52px; }
        .rack-unit-modal-actions { margin-top: 12px; display: flex; justify-content: flex-end; }
        .rack-visualizer-total {
            margin-top: 12px;
            font-weight: 700;
            color: #111827;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 12px;
        }
        .rack-visualizer-u.rack-drop-target {
            outline: 2px solid #2563eb;
            outline-offset: -2px;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): echo sanitize($error) . '<br>'; endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo sanitize($success_msg); ?></div>
            <?php endif; ?>

            <?php if ($crud_action === 'index' || $crud_action === 'list_all'): ?>
                <div class="rack-planner-header">
                    <h1>Rack Planner</h1>
                    <a href="create.php" class="btn btn-primary">➕</a>
                </div>

                <?php
                $whereClause = " WHERE company_id = ?";
                $params = [$company_id];
                $types = "i";

                if ($search !== '') {
                    $whereClause .= " AND (name LIKE ? OR notes LIKE ?)";
                    $searchParam = "%$search%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $types .= "ss";
                }

                // itm_is_safe_identifier check for sort column
                $allowedSort = ['id', 'name', 'rack_units', 'active'];
                if (!in_array($sort, $allowedSort) || !itm_is_safe_identifier($sort)) {
                    $sort = 'id';
                }

                $sqlCount = "SELECT COUNT(*) as total FROM rack_planner $whereClause";
                $stmtCount = mysqli_prepare($conn, $sqlCount);
                $totalRows = 0;
                if ($stmtCount) {
                    mysqli_stmt_bind_param($stmtCount, $types, ...$params);
                    mysqli_stmt_execute($stmtCount);
                    $resCount = mysqli_stmt_get_result($stmtCount);
                    if ($countRow = mysqli_fetch_assoc($resCount)) {
                        $totalRows = (int)$countRow['total'];
                    }
                    mysqli_stmt_close($stmtCount);
                }

                $totalPages = max(1, (int)ceil($totalRows / $perPage));
                if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
                ?>

                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <?php if ($totalRows >= $perPage): ?>
                            <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($search); ?>" placeholder="Type to search records...">
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
                                <th style="width:36px;"><input type="checkbox" id="select-all-rows"></th>
                                <th><a href="?sort=name&dir=<?php echo ($sort === 'name' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">Name <?php echo ($sort === 'name') ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?sort=rack_units&dir=<?php echo ($sort === 'rack_units' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">Units <?php echo ($sort === 'rack_units') ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th>Notes</th>
                                <th><a href="?sort=active&dir=<?php echo ($sort === 'active' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">Active <?php echo ($sort === 'active') ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM rack_planner $whereClause ORDER BY $sort $dir LIMIT ?, ?";
                            $stmt = mysqli_prepare($conn, $sql);
                            if ($stmt) {
                                $currentParams = $params;
                                $currentParams[] = $offset;
                                $currentParams[] = $perPage;
                                $currentTypes = $types . "ii";
                                mysqli_stmt_bind_param($stmt, $currentTypes, ...$currentParams);
                                mysqli_stmt_execute($stmt);
                                $res = mysqli_stmt_get_result($stmt);
                                if ($res && mysqli_num_rows($res) > 0):
                                    while ($row = mysqli_fetch_assoc($res)):
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                    <td><?php echo sanitize($row['name']); ?></td>
                                    <td><?php echo (int)$row['rack_units']; ?> U</td>
                                    <td><?php echo sanitize($row['notes']); ?></td>
                                    <td>
                                        <?php if ($row['active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap">
                                            <a class="btn btn-sm" href="view.php?id=<?php echo $row['id']; ?>">🔎</a>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo $row['id']; ?>">✏️</a>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this plan?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                                    endwhile;
                                else:
                            ?>
                                <tr><td colspan="6" style="text-align:center;">No rack plans found.</td></tr>
                            <?php
                                endif;
                                mysqli_stmt_close($stmt);
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:5px;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php elseif ($crud_action === 'create' || $crud_action === 'edit'): ?>
                <h1><?php echo $crud_action === 'create' ? 'New' : 'Edit'; ?> Rack Plan</h1>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">
                    <input type="hidden" name="layout_json" id="layoutJsonInput" value="<?php echo sanitize($data['layout_json']); ?>">

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?php echo sanitize($data['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Rack Units</label>
                        <input type="number" name="rack_units" value="<?php echo (int)$data['rack_units']; ?>" min="1" max="100">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes"><?php echo sanitize($data['notes']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="active" value="1" <?php echo $data['active'] ? 'checked' : ''; ?>>
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save</button>
                        <a href="index.php" class="btn">Cancel</a>
                    </div>
                </form>

                <div class="rack-visualizer-container">
                    <div class="rack-visualizer-top"></div>
                    <div class="rack-visualizer-frame">
                        <div class="rack-visualizer-rail rack-visualizer-rail-left">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-rail rack-visualizer-rail-right">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-content">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <?php $assignment = $rackAssignmentsByUnit[$u] ?? null; ?>
                                <div
                                    class="rack-visualizer-u<?php echo $assignment ? ' has-device' : ''; ?>"
                                    data-u="<?php echo $u; ?>"
                                    data-device-code="<?php echo $assignment ? sanitize($assignment['code']) : ''; ?>"
                                    data-device-label="<?php echo $assignment ? sanitize($assignment['label']) : ''; ?>"
                                    data-device-size="<?php echo $assignment ? (int)$assignment['size'] : ''; ?>"
                                    data-device-start-u="<?php echo $assignment ? (int)$assignment['start_u'] : ''; ?>"
                                >
                                    <span class="rack-visualizer-u-label"><?php echo $assignment ? sanitize($assignment['label']) : ''; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="rack-visualizer-base">
                        <div class="rack-visualizer-vents">
                            <?php for($i=0; $i<30; $i++): ?>
                                <div class="rack-visualizer-vent"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="rack-visualizer-feet">
                        <div class="rack-visualizer-foot"></div>
                        <div class="rack-visualizer-foot"></div>
                    </div>
                </div>
                <div class="rack-visualizer-total">TOTAL: <span id="rackTotalAmount"><?php echo number_format($layoutTotalAmount, 2, '.', ','); ?></span></div>
                <div class="rack-unit-modal-overlay" id="rackUnitModal" aria-hidden="true">
                    <div class="rack-unit-modal" role="dialog" aria-modal="true" aria-labelledby="rackUnitModalTitle">
                        <div class="rack-unit-modal-header">
                            <p id="rackUnitModalTitle">Component</p>
                            <button type="button" class="rack-unit-modal-close" id="rackUnitModalClose" aria-label="Close">&times;</button>
                        </div>
                        <div class="rack-unit-modal-body">
                            <div class="rack-unit-modal-row">
                                <label for="unitTypeSelect">Type</label>
                                <select name="unitTypeSelect" id="unitTypeSelect" class="block w-full rounded-md bg-full-white border-0 py-1.5 ring-1 ring-gray-300 ring-inset focus:ring-1 focus:ring-brand-blue text-sm">
                                    <option value="">- Choose -</option>
                                    <?php foreach ($componentGroups as $groupLabel => $groupCodes): ?>
                                        <optgroup label="<?php echo sanitize($groupLabel); ?>">
                                            <?php foreach ($groupCodes as $groupCode): ?>
                                                <?php if (!isset($componentCatalog[$groupCode])) { continue; } ?>
                                                <?php $meta = $componentCatalog[$groupCode]; ?>
                                                <option
                                                    value="<?php echo sanitize($groupCode); ?>"
                                                    data-size="<?php echo (int)$meta['size']; ?>"
                                                    data-label="<?php echo sanitize($meta['label']); ?>"
                                                    data-price=""
                                                >
                                                    <?php echo sanitize($meta['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            </div>
                            <div class="rack-unit-modal-actions">
                                <button type="button" class="btn btn-sm" id="insertFromCatalogsBtn">Insert from Catalogs</button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($crud_action === 'view'): ?>
                <div class="rack-planner-header">
                    <h1>View Rack Plan: <?php echo sanitize($data['name']); ?></h1>
                    <div>
                        <a href="edit.php?id=<?php echo $data['id']; ?>" class="btn btn-primary">✏️ Edit</a>
                        <a href="index.php" class="btn">🔙 Back</a>
                    </div>
                </div>

                <div class="card">
                    <p><strong>Units:</strong> <?php echo (int)$data['rack_units']; ?> U</p>
                    <p><strong>Notes:</strong> <?php echo sanitize($data['notes']); ?></p>
                </div>

                <div class="rack-visualizer-container">
                    <div class="rack-visualizer-top"></div>
                    <div class="rack-visualizer-frame">
                        <div class="rack-visualizer-rail rack-visualizer-rail-left">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-rail rack-visualizer-rail-right">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-content">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <?php $assignment = $rackAssignmentsByUnit[$u] ?? null; ?>
                                <div
                                    class="rack-visualizer-u<?php echo $assignment ? ' has-device' : ''; ?>"
                                    data-u="<?php echo $u; ?>"
                                    data-device-code="<?php echo $assignment ? sanitize($assignment['code']) : ''; ?>"
                                    data-device-label="<?php echo $assignment ? sanitize($assignment['label']) : ''; ?>"
                                    data-device-size="<?php echo $assignment ? (int)$assignment['size'] : ''; ?>"
                                    data-device-start-u="<?php echo $assignment ? (int)$assignment['start_u'] : ''; ?>"
                                >
                                    <span class="rack-visualizer-u-label"><?php echo $assignment ? sanitize($assignment['label']) : ''; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="rack-visualizer-base">
                        <div class="rack-visualizer-vents">
                            <?php for($i=0; $i<30; $i++): ?>
                                <div class="rack-visualizer-vent"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="rack-visualizer-feet">
                        <div class="rack-visualizer-foot"></div>
                        <div class="rack-visualizer-foot"></div>
                    </div>
                </div>
                <div class="rack-visualizer-total">TOTAL: <span><?php echo number_format($layoutTotalAmount, 2, '.', ','); ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/table-tools.js"></script>
<script>
const rackComponentCatalog = <?php echo json_encode($componentCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const rackCatalogOptions = <?php echo json_encode($catalogOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

(function () {
    const selectAllRows = document.getElementById('select-all-rows');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const toggleButton = document.getElementById('bulk-delete-toggle');
    const rowCheckboxes = document.querySelectorAll('input[name="ids[]"][form="bulk-delete-form"]');
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); });
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

    const rackUnitModal = document.getElementById('rackUnitModal');
    const rackUnitModalClose = document.getElementById('rackUnitModalClose');
    const rackUnitModalTitle = document.getElementById('rackUnitModalTitle');
    const rackUnitCells = Array.from(document.querySelectorAll('.rack-visualizer-content .rack-visualizer-u'));
    const unitTypeSelect = document.getElementById('unitTypeSelect');
    const insertFromCatalogsBtn = document.getElementById('insertFromCatalogsBtn');
    const layoutJsonInput = document.getElementById('layoutJsonInput');
    const rackPlanForm = document.querySelector('form.form-grid');
    const rackUnitsInput = rackPlanForm ? rackPlanForm.querySelector('input[name="rack_units"]') : null;
    const rackTotalAmount = document.getElementById('rackTotalAmount');

    function getRackUnitsLimit() {
        const inputUnits = rackUnitsInput ? parseInt(rackUnitsInput.value, 10) : NaN;
        if (Number.isInteger(inputUnits) && inputUnits > 0) {
            return inputUnits;
        }
        return rackUnitCells.length;
    }

    function closeRackModal() {
        if (!rackUnitModal) {
            return;
        }
        rackUnitModal.classList.remove('is-open');
        rackUnitModal.setAttribute('aria-hidden', 'true');
    }

    function getComponentMeta(code) {
        if (!code || typeof rackComponentCatalog !== 'object' || rackComponentCatalog === null) {
            return null;
        }
        if (!Object.prototype.hasOwnProperty.call(rackComponentCatalog, code)) {
            return null;
        }
        return rackComponentCatalog[code];
    }

    function inferCatalogSizeFromName(name) {
        return /\b2\s*-\s*ru\b|\b2\s*ru\b/i.test(String(name || '')) ? 2 : 1;
    }

    function getCatalogMeta(code) {
        if (!code || !Array.isArray(rackCatalogOptions)) {
            return null;
        }

        for (let i = 0; i < rackCatalogOptions.length; i++) {
            const option = rackCatalogOptions[i];
            if (!option || typeof option !== 'object') {
                continue;
            }
            if (String(option.code || '') !== code) {
                continue;
            }

            const size = Number(option.size) === 2 ? 2 : inferCatalogSizeFromName(option.label || option.model || '');
            const priceValue = (option.price_value !== null && option.price_value !== undefined && option.price_value !== '') ? Number(option.price_value) : null;
            const displayLabel = String(option.select_text || option.label || option.model || code);
            return {
                label: displayLabel,
                size: size,
                price: Number.isFinite(priceValue) ? priceValue : null
            };
        }

        return null;
    }

    function getAnyComponentMeta(code, rawDevice) {
        const staticMeta = getComponentMeta(code);
        if (staticMeta) {
            return {
                label: String(staticMeta.label || code),
                size: Number(staticMeta.size) === 2 ? 2 : 1
            };
        }

        if (String(code || '').indexOf('catalog:') === 0) {
            const catalogMeta = getCatalogMeta(code);
            if (catalogMeta) {
                return catalogMeta;
            }

            const fallbackLabel = String((rawDevice && rawDevice.label) || code).trim();
            const fallbackSize = Number((rawDevice && rawDevice.size) || inferCatalogSizeFromName(fallbackLabel)) === 2 ? 2 : 1;
            const fallbackPrice = (rawDevice && rawDevice.price !== undefined && rawDevice.price !== null && rawDevice.price !== '' && !Number.isNaN(Number(rawDevice.price))) ? Number(rawDevice.price) : null;
            return {
                label: fallbackLabel === '' ? code : fallbackLabel,
                size: fallbackSize,
                price: fallbackPrice
            };
        }

        return null;
    }

    function normalizeLayout(layout) {
        const units = getRackUnitsLimit();
        const occupied = {};
        const devices = [];
        const rawDevices = layout && Array.isArray(layout.devices) ? layout.devices : [];

        rawDevices.forEach(function (rawDevice) {
            if (!rawDevice || typeof rawDevice !== 'object') {
                return;
            }

            const code = String(rawDevice.code || '').trim();
            const meta = getAnyComponentMeta(code, rawDevice);
            if (!meta) {
                return;
            }

            const size = Number(meta.size) === 2 ? 2 : 1;
            const startU = parseInt(rawDevice.start_u, 10);
            if (!Number.isInteger(startU) || startU < 1 || (startU + size - 1) > units) {
                return;
            }

            for (let u = startU; u < (startU + size); u++) {
                if (occupied[u]) {
                    return;
                }
            }

            for (let u = startU; u < (startU + size); u++) {
                occupied[u] = true;
            }

            let label = String(rawDevice.label || '').trim();
            if (label === '') {
                label = String(meta.label || code);
            }

            devices.push({
                code: code,
                label: label,
                start_u: startU,
                size: size,
                price: (meta.price !== null && meta.price !== undefined && !Number.isNaN(Number(meta.price))) ? Number(meta.price) : ((rawDevice.price !== undefined && rawDevice.price !== null && rawDevice.price !== '' && !Number.isNaN(Number(rawDevice.price)))) ? Number(rawDevice.price) : null
            });
        });

        devices.sort(function (a, b) {
            return b.start_u - a.start_u;
        });

        return {
            version: 1,
            units: units,
            devices: devices
        };
    }

    function computeLayoutTotal(layout) {
        if (!layout || !Array.isArray(layout.devices)) {
            return 0;
        }
        let total = 0;
        layout.devices.forEach(function (device) {
            if (!device || device.price === undefined || device.price === null || device.price === '') {
                return;
            }
            const n = Number(device.price);
            if (Number.isFinite(n)) {
                total += n;
            }
        });
        return total;
    }

    function updateTotalAmount(layout) {
        if (!rackTotalAmount) {
            return;
        }
        rackTotalAmount.textContent = computeLayoutTotal(layout).toFixed(2);
    }

    function parseLayoutFromInput() {
        const fallback = { version: 1, units: getRackUnitsLimit(), devices: [] };
        if (!layoutJsonInput || String(layoutJsonInput.value || '').trim() === '') {
            return normalizeLayout(fallback);
        }

        try {
            const parsed = JSON.parse(layoutJsonInput.value);
            if (!parsed || typeof parsed !== 'object') {
                return normalizeLayout(fallback);
            }
            return normalizeLayout(parsed);
        } catch (error) {
            return normalizeLayout(fallback);
        }
    }

    function buildAssignments(layout) {
        const assignmentByUnit = {};
        if (!layout || !Array.isArray(layout.devices)) {
            return assignmentByUnit;
        }

        layout.devices.forEach(function (device) {
            const startU = Number(device.start_u);
            const size = Number(device.size) === 2 ? 2 : 1;
            for (let u = startU; u < (startU + size); u++) {
                assignmentByUnit[u] = {
                    code: String(device.code || ''),
                    label: String(device.label || ''),
                    start_u: startU,
                    size: size,
                    price: (device.price !== undefined && device.price !== null && device.price !== '' && !Number.isNaN(Number(device.price))) ? Number(device.price) : null
                };
            }
        });

        return assignmentByUnit;
    }

    function saveLayoutToInput(layout) {
        if (!layoutJsonInput) {
            return;
        }
        layoutJsonInput.value = JSON.stringify(layout);
    }

    function hasSelectOptionValue(selectEl, value) {
        if (!selectEl) {
            return false;
        }
        for (let i = 0; i < selectEl.options.length; i++) {
            if (String(selectEl.options[i].value || '') === String(value || '')) {
                return true;
            }
        }
        return false;
    }

    function ensureOptionExists(value, label, size, price) {
        if (!unitTypeSelect || !value || hasSelectOptionValue(unitTypeSelect, value)) {
            return;
        }

        const option = document.createElement('option');
        option.value = value;
        option.textContent = String(label || value);
        option.setAttribute('data-label', String(label || value));
        option.setAttribute('data-size', String(Number(size) === 2 ? 2 : 1));
        option.setAttribute('data-price', (price !== undefined && price !== null && !Number.isNaN(Number(price))) ? Number(price).toFixed(2) : '');
        option.setAttribute('data-source', 'layout');
        unitTypeSelect.appendChild(option);
    }

    function appendCatalogOptionsToSelect() {
        if (!unitTypeSelect || !Array.isArray(rackCatalogOptions) || rackCatalogOptions.length === 0) {
            return;
        }

        let catalogsGroup = unitTypeSelect.querySelector('optgroup[label="Catalogs"]');
        if (!catalogsGroup) {
            catalogsGroup = document.createElement('optgroup');
            catalogsGroup.label = 'Catalogs';
            unitTypeSelect.appendChild(catalogsGroup);
        }

        rackCatalogOptions.forEach(function (catalogOption) {
            if (!catalogOption || typeof catalogOption !== 'object') {
                return;
            }

            const optionValue = String(catalogOption.code || '');
            if (optionValue === '' || hasSelectOptionValue(unitTypeSelect, optionValue)) {
                return;
            }

            const optionLabel = String(catalogOption.label || catalogOption.model || optionValue);
            const optionSize = Number(catalogOption.size) === 2 ? 2 : inferCatalogSizeFromName(optionLabel);
            const optionText = String(catalogOption.select_text || optionLabel);
            const optionPrice = (catalogOption.price_value !== null && catalogOption.price_value !== undefined && catalogOption.price_value !== '') ? Number(catalogOption.price_value) : NaN;

            const optionEl = document.createElement('option');
            optionEl.value = optionValue;
            optionEl.textContent = optionText;
            optionEl.setAttribute('data-label', optionText);
            optionEl.setAttribute('data-size', String(optionSize));
            optionEl.setAttribute('data-price', Number.isFinite(optionPrice) ? optionPrice.toFixed(2) : '');
            optionEl.setAttribute('data-source', 'catalog');
            catalogsGroup.appendChild(optionEl);
        });
    }

    function getSelectedOptionMeta() {
        if (!unitTypeSelect) {
            return null;
        }

        const selectedOption = unitTypeSelect.options[unitTypeSelect.selectedIndex];
        if (!selectedOption) {
            return null;
        }

        const selectedCode = String(selectedOption.value || '');
        if (selectedCode === '') {
            return null;
        }

        const optionSize = parseInt(String(selectedOption.getAttribute('data-size') || ''), 10);
        const size = optionSize === 2 ? 2 : 1;
        let label = String(selectedOption.getAttribute('data-label') || '').trim();
        if (label === '') {
            label = String(selectedOption.textContent || selectedCode).trim();
        }
        const optionPriceRaw = String(selectedOption.getAttribute('data-price') || '').trim();
        const optionPriceNum = optionPriceRaw === '' ? null : Number(optionPriceRaw);

        return {
            code: selectedCode,
            label: label === '' ? selectedCode : label,
            size: size,
            price: Number.isFinite(optionPriceNum) ? optionPriceNum : null
        };
    }

    function renderLayout(layout) {
        const assignmentByUnit = buildAssignments(layout);

        rackUnitCells.forEach(function (cell) {
            const unit = parseInt(cell.getAttribute('data-u'), 10);
            const assignment = assignmentByUnit[unit];
            const labelEl = cell.querySelector('.rack-visualizer-u-label');
            cell.classList.remove('rack-drop-target');
            cell.setAttribute('draggable', 'false');

            if (assignment) {
                cell.classList.add('has-device');
                cell.classList.remove('has-device-anchor');
                cell.setAttribute('data-device-code', assignment.code);
                cell.setAttribute('data-device-label', assignment.label);
                cell.setAttribute('data-device-size', String(assignment.size));
                cell.setAttribute('data-device-start-u', String(assignment.start_u));
                const anchorUnit = Number(assignment.start_u) + Number(assignment.size) - 1;
                if (unit === anchorUnit) {
                    cell.classList.add('has-device-anchor');
                    cell.setAttribute('draggable', 'true');
                    if (labelEl) {
                        labelEl.textContent = assignment.label;
                    }
                } else if (labelEl) {
                    labelEl.textContent = '';
                }
            } else {
                cell.classList.remove('has-device');
                cell.classList.remove('has-device-anchor');
                cell.setAttribute('data-device-code', '');
                cell.setAttribute('data-device-label', '');
                cell.setAttribute('data-device-size', '');
                cell.setAttribute('data-device-start-u', '');
                if (labelEl) {
                    labelEl.textContent = '';
                }
            }
        });

        saveLayoutToInput(layout);
        updateTotalAmount(layout);
    }

    let autoSaveInFlight = false;
    let autoSavePending = false;
    let lastAutoSavePayload = '';

    function autoSaveLayoutToDatabase(layout) {
        if (!rackPlanForm || !layoutJsonInput) {
            return;
        }

        const idInput = rackPlanForm.querySelector('input[name="id"]');
        const csrfInput = rackPlanForm.querySelector('input[name="csrf_token"]');
        const recordId = idInput ? parseInt(String(idInput.value || ''), 10) : 0;
        if (!Number.isInteger(recordId) || recordId <= 0) {
            return;
        }

        const payload = JSON.stringify(layout);
        if (payload === lastAutoSavePayload && !autoSavePending && !autoSaveInFlight) {
            return;
        }
        lastAutoSavePayload = payload;

        const submitOnce = function () {
            autoSaveInFlight = true;
            const formData = new FormData();
            formData.set('ajax_update_layout', '1');
            formData.set('id', String(recordId));
            formData.set('rack_units', String(getRackUnitsLimit()));
            formData.set('layout_json', layoutJsonInput.value);
            if (csrfInput) {
                formData.set('csrf_token', String(csrfInput.value || ''));
            }

            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (!result || typeof result !== 'object') {
                    return;
                }
                if (result.layout_json && layoutJsonInput) {
                    layoutJsonInput.value = String(result.layout_json);
                }
                if (result.total_amount !== undefined && result.total_amount !== null && rackTotalAmount) {
                    const totalNum = Number(result.total_amount);
                    if (Number.isFinite(totalNum)) {
                        rackTotalAmount.textContent = totalNum.toFixed(2);
                    }
                }
            }).catch(function () {
                // Keep the UI responsive even if auto-save fails; manual Save still works.
            }).finally(function () {
                autoSaveInFlight = false;
                if (autoSavePending) {
                    autoSavePending = false;
                    submitOnce();
                }
            });
        };

        if (autoSaveInFlight) {
            autoSavePending = true;
            return;
        }

        submitOnce();
    }

    if (rackUnitModal && rackUnitModalClose && unitTypeSelect && layoutJsonInput && rackUnitCells.length > 0) {
        let layoutState = parseLayoutFromInput();
        let activeUnit = null;
        let dragState = null;
        let ignoreNextClick = false;

        function clearDragTargets() {
            rackUnitCells.forEach(function (cell) {
                cell.classList.remove('rack-drop-target');
            });
        }

        function rangesOverlap(startA, sizeA, startB, sizeB) {
            const endA = startA + sizeA - 1;
            const endB = startB + sizeB - 1;
            return startA <= endB && startB <= endA;
        }

        function moveDeviceToUnit(sourceStartU, targetStartU) {
            const sourceIndex = layoutState.devices.findIndex(function (device) {
                return Number(device.start_u) === Number(sourceStartU);
            });
            if (sourceIndex === -1) {
                return;
            }

            const sourceDevice = layoutState.devices[sourceIndex];
            const sourceSize = Number(sourceDevice.size) === 2 ? 2 : 1;
            if (!Number.isInteger(targetStartU) || targetStartU < 1) {
                return;
            }
            if (Number(sourceDevice.start_u) === targetStartU) {
                return;
            }

            const rackLimit = getRackUnitsLimit();
            if ((targetStartU + sourceSize - 1) > rackLimit) {
                alert('Not enough space for this ' + String(sourceSize) + '-RU component.');
                return;
            }

            for (let i = 0; i < layoutState.devices.length; i++) {
                if (i === sourceIndex) {
                    continue;
                }
                const other = layoutState.devices[i];
                const otherStart = Number(other.start_u);
                const otherSize = Number(other.size) === 2 ? 2 : 1;
                if (rangesOverlap(targetStartU, sourceSize, otherStart, otherSize)) {
                    alert('Selected space overlaps another component.');
                    return;
                }
            }

            sourceDevice.start_u = targetStartU;
            layoutState = normalizeLayout(layoutState);
            renderLayout(layoutState);
            autoSaveLayoutToDatabase(layoutState);
        }

        function removeDeviceCoveringUnit(unit) {
            layoutState.devices = layoutState.devices.filter(function (device) {
                const startU = Number(device.start_u);
                const size = Number(device.size) === 2 ? 2 : 1;
                return !(unit >= startU && unit < (startU + size));
            });
        }

        function isRangeAvailable(startU, size) {
            const assignments = buildAssignments(layoutState);
            for (let u = startU; u < (startU + size); u++) {
                if (assignments[u]) {
                    return false;
                }
            }
            return true;
        }

        function openRackModalForUnit(unit) {
            const assignments = buildAssignments(layoutState);
            const assignment = assignments[unit] || null;
            activeUnit = assignment ? assignment.start_u : unit;
            if (rackUnitModalTitle) {
                rackUnitModalTitle.textContent = 'Component ' + String(activeUnit);
            }
            if (assignment && String(assignment.code || '').indexOf('catalog:') === 0) {
                appendCatalogOptionsToSelect();
            }
            if (assignment) {
                ensureOptionExists(String(assignment.code || ''), String(assignment.label || assignment.code || ''), Number(assignment.size || 1), assignment.price);
            }
            unitTypeSelect.value = assignment ? assignment.code : '';
            rackUnitModal.classList.add('is-open');
            rackUnitModal.setAttribute('aria-hidden', 'false');
        }

        renderLayout(layoutState);

        if (insertFromCatalogsBtn) {
            insertFromCatalogsBtn.addEventListener('click', function () {
                appendCatalogOptionsToSelect();
                unitTypeSelect.focus();
            });
        }

        rackUnitCells.forEach(function (cell) {
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', function () {
                if (ignoreNextClick) {
                    ignoreNextClick = false;
                    return;
                }
                const clickedUnit = parseInt(cell.getAttribute('data-u'), 10);
                if (!Number.isInteger(clickedUnit) || clickedUnit < 1) {
                    return;
                }
                openRackModalForUnit(clickedUnit);
            });

            cell.addEventListener('dragstart', function (event) {
                if (!cell.classList.contains('has-device-anchor')) {
                    event.preventDefault();
                    return;
                }

                const sourceStartU = parseInt(String(cell.getAttribute('data-device-start-u') || ''), 10);
                const sourceSize = parseInt(String(cell.getAttribute('data-device-size') || ''), 10);
                if (!Number.isInteger(sourceStartU) || sourceStartU < 1) {
                    event.preventDefault();
                    return;
                }

                dragState = {
                    sourceStartU: sourceStartU,
                    sourceSize: sourceSize === 2 ? 2 : 1
                };

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(sourceStartU));
                }
            });

            cell.addEventListener('dragover', function (event) {
                if (!dragState) {
                    return;
                }
                event.preventDefault();
                cell.classList.add('rack-drop-target');
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            cell.addEventListener('dragleave', function () {
                cell.classList.remove('rack-drop-target');
            });

            cell.addEventListener('drop', function (event) {
                if (!dragState) {
                    return;
                }
                event.preventDefault();
                const targetUnit = parseInt(String(cell.getAttribute('data-u') || ''), 10);
                clearDragTargets();
                moveDeviceToUnit(dragState.sourceStartU, targetUnit);
                dragState = null;
                ignoreNextClick = true;
                setTimeout(function () { ignoreNextClick = false; }, 50);
            });

            cell.addEventListener('dragend', function () {
                dragState = null;
                clearDragTargets();
            });
        });

        unitTypeSelect.addEventListener('change', function () {
            if (!Number.isInteger(activeUnit) || activeUnit < 1) {
                closeRackModal();
                return;
            }

            if (String(unitTypeSelect.value || '') === '__add_new__') {
                unitTypeSelect.value = '';
                window.open('../catalogs/create.php', '_blank');
                return;
            }

            const selectedMeta = getSelectedOptionMeta();
            removeDeviceCoveringUnit(activeUnit);

            if (selectedMeta) {
                const size = Number(selectedMeta.size) === 2 ? 2 : 1;
                const rackLimit = getRackUnitsLimit();
                if ((activeUnit + size - 1) > rackLimit) {
                    alert('Not enough space for this ' + String(size) + '-RU component.');
                    unitTypeSelect.value = '';
                    layoutState = normalizeLayout(layoutState);
                    renderLayout(layoutState);
                    closeRackModal();
                    return;
                }

                if (!isRangeAvailable(activeUnit, size)) {
                    alert('Selected space overlaps another component.');
                    unitTypeSelect.value = '';
                    layoutState = normalizeLayout(layoutState);
                    renderLayout(layoutState);
                    closeRackModal();
                    return;
                }

                layoutState.devices.push({
                    code: String(selectedMeta.code),
                    label: String(selectedMeta.label),
                    start_u: activeUnit,
                    size: size,
                    price: selectedMeta.price
                });
            }

            layoutState = normalizeLayout(layoutState);
            renderLayout(layoutState);
            autoSaveLayoutToDatabase(layoutState);
            closeRackModal();
        });

        rackUnitModalClose.addEventListener('click', function () {
            closeRackModal();
        });

        rackUnitModal.addEventListener('click', function (event) {
            if (event.target === rackUnitModal) {
                closeRackModal();
            }
        });

        if (rackPlanForm) {
            rackPlanForm.addEventListener('submit', function () {
                layoutState = normalizeLayout(layoutState);
                saveLayoutToInput(layoutState);
            });
        }
    }
})();
</script>
</body>
</html>
