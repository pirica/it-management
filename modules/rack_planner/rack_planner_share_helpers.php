<?php
/**
 * Temporary QR / code share sessions for Rack Planner layouts.
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/includes/functions.php';

function rack_planner_share_module_slug()
{
    return 'rack_planner';
}

/** @deprecated Use rack_planner_share_module_slug() */
function rack_planner_share_table_name()
{
    return rack_planner_share_module_slug();
}

function rack_planner_share_join_script_path()
{
    return 'modules/rack_planner/join.php';
}

function rack_planner_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(rack_planner_share_join_script_path(), $accessToken);
}

/**
 * @return array<string,mixed>
 */
function rack_planner_share_build_payload_from_plan(array $plan, $ownerUsername, array $catalogOptions, array $equipmentOptions)
{
    $rackUnits = max(1, (int)($plan['rack_units'] ?? 42));
    $layoutRaw = (string)($plan['layout_json'] ?? '');
    $combinedMeta = rack_planner_combined_code_meta_map($catalogOptions, $equipmentOptions);
    $layout = rack_planner_normalize_layout_json($layoutRaw, $rackUnits, $combinedMeta);
    $assignments = rack_planner_assignments_by_unit($layout);
    $devices = [];
    foreach ($layout['devices'] as $device) {
        $devices[] = [
            'code' => (string)($device['code'] ?? ''),
            'label' => (string)($device['label'] ?? ''),
            'start_u' => (int)($device['start_u'] ?? 0),
            'size' => (int)($device['size'] ?? 1),
            'price' => (isset($device['price']) && is_numeric($device['price'])) ? number_format((float)$device['price'], 2, '.', '') : '',
        ];
    }

    $unitRows = [];
    for ($u = $rackUnits; $u >= 1; $u--) {
        $assignment = $assignments[$u] ?? null;
        if ($assignment === null) {
            continue;
        }
        if ((int)($assignment['start_u'] ?? 0) !== $u) {
            continue;
        }
        $unitRows[] = [
            'unit' => $u,
            'label' => (string)($assignment['label'] ?? ''),
            'code' => (string)($assignment['code'] ?? ''),
            'size' => (int)($assignment['size'] ?? 1),
            'price' => isset($assignment['price']) && is_numeric($assignment['price'])
                ? number_format((float)$assignment['price'], 2, '.', '')
                : '',
        ];
    }

    $name = (string)($plan['name'] ?? 'Rack Plan');

    return [
        'type' => 'rack_planner',
        'heading' => $name,
        'owner_username' => (string)$ownerUsername,
        'name' => $name,
        'rack_units' => $rackUnits,
        'status_name' => (string)($plan['status_name'] ?? ''),
        'notes' => (string)($plan['notes'] ?? ''),
        'total_amount' => number_format(rack_planner_layout_total($layout), 2, '.', ''),
        'devices' => $devices,
        'unit_rows' => $unitRows,
    ];
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function rack_planner_share_create_session($conn, $planId, $companyId, $employeeId, $ownerUsername)
{
    $planId = (int)$planId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($planId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $stmt = $conn->prepare(
        'SELECT rp.*, rs.name AS status_name
         FROM rack_planner rp
         LEFT JOIN rack_statuses rs ON rs.id = rp.status_id AND rs.company_id = rp.company_id
         WHERE rp.id = ? AND rp.company_id = ? AND rp.employee_id = ? AND rp.active = 1 AND rp.deleted_at IS NULL
         LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load rack plan.'];
    }
    $stmt->bind_param('iii', $planId, $companyId, $employeeId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$plan) {
        return ['ok' => false, 'error' => 'Rack plan not found or you are not the owner.'];
    }

    $catalogOptions = rack_planner_fetch_catalog_options($conn, $companyId);
    $equipmentOptions = rack_planner_fetch_equipment_picker_options($conn, $companyId);
    $payload = rack_planner_share_build_payload_from_plan($plan, $ownerUsername, $catalogOptions, $equipmentOptions);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, rack_planner_share_module_slug(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $planId,
        'payload_json' => $payloadJson,
    ]);
}
