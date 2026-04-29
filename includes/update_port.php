<?php
/**
 * Switch Port Update API
 * 
 * AJAX endpoint to update individual port details such as label, status,
 * color, VLAN, and comments. Supports both JSON and form-encoded input.
 */

require '../config/config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// Access Control: Authentication check
if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Input parsing (handles multiple content types)
$raw = file_get_contents('php://input');
$decoded = json_decode($raw, true);
$input = is_array($decoded) ? $decoded : $_POST;

// CSRF Validation
$csrfToken = (string)($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!itm_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

/**
 * Fetches lookup data from the database
 */
function fetch_lookup_map(mysqli $conn, string $table, string $labelColumn): array
{
    $rows = [];
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($labelColumn)) {
        return $rows;
    }

    $hasCompanyId = itm_table_has_column($conn, $table, 'company_id');
    $companyId = isset($GLOBALS['company_id']) ? (int)$GLOBALS['company_id'] : 0;

    $res = false;
    if ($hasCompanyId && $companyId > 0) {
        $sql = "SELECT id, `{$labelColumn}` AS label FROM `{$table}` WHERE company_id = ? ORDER BY id ASC";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    if (!$res || mysqli_num_rows($res) === 0) {
        $sql = "SELECT id, `{$labelColumn}` AS label FROM `{$table}` ORDER BY id ASC";
        $res = mysqli_query($conn, $sql);
    }

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['label']];
    }
    return $rows;
}

/**
 * Fetches VLAN list for the company
 */
function fetch_company_vlans(mysqli $conn, int $companyId): array
{
    $rows = [];
    $sql = 'SELECT id, vlan_name FROM vlans WHERE company_id = ? ORDER BY vlan_number ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['vlan_name']];
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Maps a name or ID string back to a valid database ID
 */
function find_lookup_id(array $rows, $value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_numeric($value)) {
        $id = (int)$value;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $id) {
                return $id;
            }
        }
        return 0;
    }
    $wanted = strtolower(trim((string)$value));
    foreach ($rows as $row) {
        if (strtolower(trim((string)$row['name'])) === $wanted) {
            return (int)$row['id'];
        }
    }
    return 0;
}

// Schema detection
$hasEquipmentId = itm_table_has_column($conn, 'switch_ports', 'equipment_id');
$hasStatusId = itm_table_has_column($conn, 'switch_ports', 'status_id');
$hasColorId = itm_table_has_column($conn, 'switch_ports', 'color_id');
$hasVlanId = itm_table_has_column($conn, 'switch_ports', 'vlan_id');
$hasFiberPortId = itm_table_has_column($conn, 'switch_ports', 'fiber_port_id');
$hasFiberPatchId = itm_table_has_column($conn, 'switch_ports', 'fiber_patch_id');
$hasFiberRackId = itm_table_has_column($conn, 'switch_ports', 'fiber_rack_id');
$hasIdfId = itm_table_has_column($conn, 'switch_ports', 'idf_id');

if (!$hasStatusId || !$hasColorId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_ports schema is missing status_id/color_id columns']);
    exit;
}

// Pre-fetch reference data
$statuses = fetch_lookup_map($conn, 'switch_status', 'status');
$colors = fetch_lookup_map($conn, 'cable_colors', 'color_name');
$vlans = fetch_company_vlans($conn, (int)$company_id);

// Parameter validation
if (empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

$id = (int)$input['id'];
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}
$switchId = (int)($input['switch_id'] ?? 0);
if ($switchId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing switch id']);
    exit;
}

// Resolve lookup values
$colorId = find_lookup_id($colors, $input['color'] ?? null);
$statusId = find_lookup_id($statuses, $input['status'] ?? null);
$vlanId = $hasVlanId ? find_lookup_id($vlans, $input['vlan'] ?? null) : 0;
$label = isset($input['label']) ? trim((string)$input['label']) : null;
$comments = isset($input['comments']) ? trim((string)$input['comments']) : null;
$fiberPortId = isset($input['fiber_port_id']) && is_numeric((string)$input['fiber_port_id']) ? (int)$input['fiber_port_id'] : 0;
$fiberPatchId = isset($input['fiber_patch_id']) && is_numeric((string)$input['fiber_patch_id']) ? (int)$input['fiber_patch_id'] : 0;
$fiberRackId = isset($input['fiber_rack_id']) && is_numeric((string)$input['fiber_rack_id']) ? (int)$input['fiber_rack_id'] : 0;
$idfId = isset($input['idf_id']) && is_numeric((string)$input['idf_id']) ? (int)$input['idf_id'] : 0;

// Build dynamic UPDATE query based on provided fields
$fields = [];
$types = '';
$params = [];

if ($colorId > 0) {
    $fields[] = 'color_id = ?';
    $types .= 'i';
    $params[] = $colorId;
}
if ($statusId > 0) {
    $fields[] = 'status_id = ?';
    $types .= 'i';
    $params[] = $statusId;
}
if ($hasVlanId && array_key_exists('vlan', $input)) {
    if ($vlanId > 0) {
        $fields[] = 'vlan_id = ?';
        $types .= 'i';
        $params[] = $vlanId;
    } else {
        $fields[] = 'vlan_id = NULL';
    }
}
if ($label !== null) {
    $fields[] = 'label = ?';
    $types .= 's';
    $params[] = $label;
}
if ($comments !== null) {
    $fields[] = 'comments = ?';
    $types .= 's';
    $params[] = $comments;
}

if ($hasFiberPortId && array_key_exists('fiber_port_id', $input)) {
    if ($fiberPortId > 0) {
        $fields[] = 'fiber_port_id = ?';
        $types .= 'i';
        $params[] = $fiberPortId;
    } else {
        $fields[] = 'fiber_port_id = NULL';
    }
}
if ($hasFiberPatchId && array_key_exists('fiber_patch_id', $input)) {
    if ($fiberPatchId > 0) {
        $fields[] = 'fiber_patch_id = ?';
        $types .= 'i';
        $params[] = $fiberPatchId;
    } else {
        $fields[] = 'fiber_patch_id = NULL';
    }
}
if ($hasFiberRackId && array_key_exists('fiber_rack_id', $input)) {
    if ($fiberRackId > 0) {
        $fields[] = 'fiber_rack_id = ?';
        $types .= 'i';
        $params[] = $fiberRackId;
    } else {
        $fields[] = 'fiber_rack_id = NULL';
    }
}
if ($hasIdfId && array_key_exists('idf_id', $input)) {
    if ($idfId > 0) {
        $fields[] = 'idf_id = ?';
        $types .= 'i';
        $params[] = $idfId;
    } else {
        $fields[] = 'idf_id = NULL';
    }
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'Nothing to update']);
    exit;
}

// Scoped update query for security
$sql = 'UPDATE switch_ports SET ' . implode(', ', $fields) . ' WHERE id = ? AND company_id = ?';
$types .= 'ii';
$params[] = $id;
$params[] = (int)$company_id;

if ($hasEquipmentId) {
    $sql .= ' AND equipment_id = ?';
    $types .= 'i';
    $params[] = $switchId;
}

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
$ok = mysqli_stmt_execute($stmt);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    mysqli_stmt_close($stmt);
    exit;
}

$updated = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);



echo json_encode(['success' => true, 'updated' => $updated]);
