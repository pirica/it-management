<?php
/**
 * Temporary QR / code share sessions for Floor Plans.
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/gallery_helpers.php';

function floor_plans_share_module_slug()
{
    return 'floor_plans';
}

/** @deprecated Use floor_plans_share_module_slug() */
function floor_plans_share_table_name()
{
    return floor_plans_share_module_slug();
}

function floor_plans_share_join_script_path()
{
    return 'modules/floor_plans/join.php';
}

function floor_plans_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(floor_plans_share_join_script_path(), $accessToken);
}

function floor_plans_share_asset_url($accessToken)
{
    $accessToken = trim((string)$accessToken);
    if ($accessToken === '') {
        return '';
    }

    return rtrim((string)BASE_URL, '/') . '/modules/floor_plans/share_asset.php?t=' . rawurlencode($accessToken);
}

/**
 * @return array<string,mixed>
 */
function floor_plans_share_build_payload_from_plan(array $plan, $ownerUsername)
{
    $displayName = (string)($plan['display_name'] ?? 'Floor Plan');
    $storedFilename = (string)($plan['stored_filename'] ?? '');
    $mime = (string)($plan['mime_type'] ?? '');
    $ext = (string)($plan['file_ext'] ?? '');
    $previewKind = fp_resolve_preview_kind($mime, $ext);

    return [
        'type' => 'floor_plan',
        'heading' => $displayName,
        'owner_username' => (string)$ownerUsername,
        'display_name' => $displayName,
        'stored_filename' => $storedFilename,
        'mime_type' => $mime,
        'file_ext' => $ext,
        'file_size' => (int)($plan['file_size'] ?? 0),
        'preview_kind' => $previewKind,
    ];
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function floor_plans_share_create_session($conn, $floorPlanId, $companyId, $employeeId, $ownerUsername)
{
    $floorPlanId = (int)$floorPlanId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($floorPlanId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $stmt = $conn->prepare(
        'SELECT * FROM floor_plans WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load floor plan.'];
    }
    $stmt->bind_param('ii', $floorPlanId, $companyId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$plan) {
        return ['ok' => false, 'error' => 'Floor plan not found.'];
    }
    if ((string)($plan['stored_filename'] ?? '') === '') {
        return ['ok' => false, 'error' => 'Floor plan file is missing.'];
    }

    $absolute = fp_absolute_path($companyId, (string)$plan['stored_filename']);
    if (!is_readable($absolute)) {
        return ['ok' => false, 'error' => 'Floor plan file is not available on disk.'];
    }

    $payload = floor_plans_share_build_payload_from_plan($plan, $ownerUsername);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, floor_plans_share_module_slug(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $floorPlanId,
        'payload_json' => $payloadJson,
    ]);
}

/**
 * @param array<string,mixed>|null $session
 * @return array{ok:bool,error?:string,payload?:array<string,mixed>}
 */
function floor_plans_share_validate_asset_request($conn, $accessToken, &$session = null)
{
    $accessToken = trim((string)$accessToken);
    if ($accessToken === '' || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $session = itm_qr_share_fetch_session_by_token($conn, floor_plans_share_module_slug(), $accessToken);
    if (!$session) {
        return ['ok' => false, 'error' => 'Session expired.'];
    }

    $payload = itm_qr_share_decode_payload($session['payload_json'] ?? '');
    if ($payload === null || ($payload['type'] ?? '') !== 'floor_plan') {
        return ['ok' => false, 'error' => 'Invalid payload.'];
    }

    return ['ok' => true, 'payload' => $payload];
}
