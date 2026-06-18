<?php
/**
 * Switch Port Update API
 * 
 * AJAX endpoint to update individual port details such as label, status,
 * color, VLAN, and comments. Supports both JSON and form-encoded input.
 */

require_once __DIR__ . '/itm_script_entry_guard.php';
if (itm_skip_http_entry_unless_direct(__FILE__)) {
    return;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/switch_port_api_helpers.php';
require_once __DIR__ . '/itm_api_json_response.php';

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

// Schema detection
$hasEquipmentId = itm_table_has_column($conn, 'switch_ports', 'equipment_id');
$hasStatusId = itm_table_has_column($conn, 'switch_ports', 'status_id');
$hasColorId = itm_table_has_column($conn, 'switch_ports', 'color_id');
$hasRj45SpeedId = itm_table_has_column($conn, 'switch_ports', 'rj45_speed_id');
$hasVlanId = itm_table_has_column($conn, 'switch_ports', 'vlan_id');
$hasFiberPortId = itm_table_has_column($conn, 'switch_ports', 'fiber_port_id');
$hasFiberPatchId = itm_table_has_column($conn, 'switch_ports', 'fiber_patch_id');
$hasFiberRackId = itm_table_has_column($conn, 'switch_ports', 'fiber_rack_id');
$hasToIdfId = itm_table_has_column($conn, 'switch_ports', 'to_idf_id');
$hasRackId = itm_table_has_column($conn, 'switch_ports', 'rack_id');
$hasToRackId = itm_table_has_column($conn, 'switch_ports', 'to_rack_id');
$hasToLocationId = itm_table_has_column($conn, 'switch_ports', 'to_location_id');
$hasLocationId = itm_table_has_column($conn, 'switch_ports', 'location_id');
$hasHostname = itm_table_has_column($conn, 'switch_ports', 'hostname');
$hasManagementId = itm_table_has_column($conn, 'switch_ports', 'management_id');

if (!$hasStatusId || !$hasColorId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_ports schema is missing status_id/color_id columns']);
    exit;
}

// Pre-fetch reference data
$statuses = fetch_lookup_map($conn, 'switch_status', 'status');
$colors = fetch_lookup_map($conn, 'cable_colors', 'color_name');
$rj45Speeds = fetch_lookup_map($conn, 'rj45_speed', 'cable_type');
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
$rj45SpeedId = $hasRj45SpeedId ? find_lookup_id($rj45Speeds, $input['rj45_speed_id'] ?? null) : 0;
$vlanId = $hasVlanId ? find_lookup_id($vlans, $input['vlan'] ?? null) : 0;
$toPatchPort = isset($input['to_patch_port']) ? trim((string)$input['to_patch_port']) : (isset($input['label']) ? trim((string)$input['label']) : null);
$comments = isset($input['comments']) ? trim((string)$input['comments']) : null;
$fiberPortId = isset($input['fiber_port_id']) && is_numeric((string)$input['fiber_port_id']) ? (int)$input['fiber_port_id'] : 0;
$fiberPatchId = isset($input['fiber_patch_id']) && is_numeric((string)$input['fiber_patch_id']) ? (int)$input['fiber_patch_id'] : 0;
$fiberRackId = isset($input['fiber_rack_id']) && is_numeric((string)$input['fiber_rack_id']) ? (int)$input['fiber_rack_id'] : 0;
$toIdfId = isset($input['to_idf_id']) && is_numeric((string)$input['to_idf_id']) ? (int)$input['to_idf_id'] : 0;
$toRackIdRaw = $input['to_rack_id'] ?? ($input['rack_id'] ?? null);
$toRackId = is_numeric((string)$toRackIdRaw) ? (int)$toRackIdRaw : 0;
$toLocationIdRaw = $input['to_location_id'] ?? null;
$toLocationId = is_numeric((string)$toLocationIdRaw) ? (int)$toLocationIdRaw : 0;
$rackId = isset($input['rack_id']) && is_numeric((string)$input['rack_id']) ? (int)$input['rack_id'] : 0;
$locationId = isset($input['location_id']) && is_numeric((string)$input['location_id']) ? (int)$input['location_id'] : 0;
$hostname = isset($input['hostname']) ? trim((string)$input['hostname']) : null;

$equipmentManagementId = 0;
if ($hasManagementId) {
    $stmtEquipmentManagement = mysqli_prepare(
        $conn,
        "SELECT COALESCE(switch_environment_id, 0) AS switch_environment_id
         FROM equipment
         WHERE id = ? AND company_id = ?
         LIMIT 1"
    );
    if ($stmtEquipmentManagement) {
        $companyId = (int)$company_id;
        mysqli_stmt_bind_param($stmtEquipmentManagement, 'ii', $switchId, $companyId);
        mysqli_stmt_execute($stmtEquipmentManagement);
        $resEquipmentManagement = mysqli_stmt_get_result($stmtEquipmentManagement);
        $equipmentManagementRow = $resEquipmentManagement ? mysqli_fetch_assoc($resEquipmentManagement) : null;
        mysqli_stmt_close($stmtEquipmentManagement);
        if ($equipmentManagementRow) {
            $equipmentManagementId = (int)($equipmentManagementRow['switch_environment_id'] ?? 0);
        }
    }
}

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
if ($hasRj45SpeedId && array_key_exists('rj45_speed_id', $input)) {
    if ($rj45SpeedId > 0) {
        $fields[] = 'rj45_speed_id = ?';
        $types .= 'i';
        $params[] = $rj45SpeedId;
    } else {
        $fields[] = 'rj45_speed_id = NULL';
    }
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
if ($toPatchPort !== null) {
    $fields[] = 'to_patch_port = ?';
    $types .= 's';
    $params[] = $toPatchPort;
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
if ($hasToIdfId && array_key_exists('to_idf_id', $input)) {
    if ($toIdfId > 0) {
        $fields[] = 'to_idf_id = ?';
        $types .= 'i';
        $params[] = $toIdfId;
    } else {
        $fields[] = 'to_idf_id = NULL';
    }
}
if ($hasToRackId && (array_key_exists('to_rack_id', $input) || array_key_exists('rack_id', $input))) {
    if ($toRackId > 0) {
        $fields[] = 'to_rack_id = ?';
        $types .= 'i';
        $params[] = $toRackId;
    } else {
        $fields[] = 'to_rack_id = NULL';
    }
}
if ($hasToLocationId && array_key_exists('to_location_id', $input)) {
    if ($toLocationId > 0) {
        $fields[] = 'to_location_id = ?';
        $types .= 'i';
        $params[] = $toLocationId;
    } else {
        $fields[] = 'to_location_id = NULL';
    }
}
if ($hasRackId && array_key_exists('rack_id', $input)) {
    if ($rackId > 0) {
        $fields[] = 'rack_id = ?';
        $types .= 'i';
        $params[] = $rackId;
    } else {
        $fields[] = 'rack_id = NULL';
    }
}

if ($hasHostname && array_key_exists('hostname', $input)) {
    if ($hostname !== '') {
        $fields[] = 'hostname = ?';
        $types .= 's';
        $params[] = $hostname;
    } else {
        $fields[] = 'hostname = NULL';
    }
}

if ($hasLocationId && array_key_exists('location_id', $input)) {
    if ($locationId > 0) {
        $fields[] = 'location_id = ?';
        $types .= 'i';
        $params[] = $locationId;
    } else {
        $fields[] = 'location_id = NULL';
    }
}

if ($hasManagementId) {
    if ($equipmentManagementId > 0) {
        $fields[] = 'management_id = ?';
        $types .= 'i';
        $params[] = $equipmentManagementId;
    } else {
        $fields[] = 'management_id = NULL';
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

if ($hasManagementId) {
    $autoSyncMarker = '[SPM-AUTO-TO-IDF]';
    $switchPortRow = null;
    $switchPortSql = "SELECT id, company_id, equipment_id, port_type, port_number, to_patch_port, status_id, vlan_id, rj45_speed_id,
                             fiber_port_id, management_id, comments, to_idf_id
                      FROM switch_ports
                      WHERE id = ? AND company_id = ? AND equipment_id = ?
                      LIMIT 1";
    $stmtSwitchPort = mysqli_prepare($conn, $switchPortSql);
    if ($stmtSwitchPort) {
        $companyIdParam = (int)$company_id;
        mysqli_stmt_bind_param($stmtSwitchPort, 'iii', $id, $companyIdParam, $switchId);
        mysqli_stmt_execute($stmtSwitchPort);
        $resSwitchPort = mysqli_stmt_get_result($stmtSwitchPort);
        $switchPortRow = $resSwitchPort ? mysqli_fetch_assoc($resSwitchPort) : null;
        mysqli_stmt_close($stmtSwitchPort);
    }

    if ($switchPortRow) {
        $portTypeId = 0;
        $portTypeRaw = trim((string)($switchPortRow['port_type'] ?? ''));
        if ($portTypeRaw !== '') {
            if (ctype_digit($portTypeRaw)) {
                $portTypeId = (int)$portTypeRaw;
            } else {
                $stmtPortType = mysqli_prepare(
                    $conn,
                    "SELECT id FROM switch_port_types WHERE company_id = ? AND LOWER(type) = LOWER(?) LIMIT 1"
                );
                if ($stmtPortType) {
                    $companyIdParam = (int)$company_id;
                    mysqli_stmt_bind_param($stmtPortType, 'is', $companyIdParam, $portTypeRaw);
                    mysqli_stmt_execute($stmtPortType);
                    $resPortType = mysqli_stmt_get_result($stmtPortType);
                    $portTypeRow = $resPortType ? mysqli_fetch_assoc($resPortType) : null;
                    mysqli_stmt_close($stmtPortType);
                    if ($portTypeRow) {
                        $portTypeId = (int)($portTypeRow['id'] ?? 0);
                    }
                }
            }
        }

        $positionId = 0;
        $stmtPosition = mysqli_prepare(
            $conn,
            "SELECT p.id
             FROM idf_positions p
             JOIN equipment e ON e.company_id = p.company_id
             WHERE p.company_id = ?
               AND p.equipment_id = CAST(e.id AS CHAR)
               AND e.id = ?
             ORDER BY p.id ASC
             LIMIT 1"
        );
        if ($stmtPosition) {
            $companyIdParam = (int)$company_id;
            mysqli_stmt_bind_param($stmtPosition, 'ii', $companyIdParam, $switchId);
            mysqli_stmt_execute($stmtPosition);
            $resPosition = mysqli_stmt_get_result($stmtPosition);
            $positionRow = $resPosition ? mysqli_fetch_assoc($resPosition) : null;
            mysqli_stmt_close($stmtPosition);
            if ($positionRow) {
                $positionId = (int)($positionRow['id'] ?? 0);
            }
        }

        if ($positionId > 0 && $portTypeId > 0) {
            $destinationIdfId = (int)($switchPortRow['to_idf_id'] ?? 0);
            $existingIdfPortId = 0;
            $existingIdfPortNotes = '';
            $portNoParam = (int)($switchPortRow['port_number'] ?? 0);
            $targetInsertPositionId = $positionId;
            $connectedToValue = null;
            if ($destinationIdfId > 0) {
                $stmtToIdf = mysqli_prepare($conn, "SELECT idf_code, name FROM idfs WHERE id = ? AND company_id = ? LIMIT 1");
                if ($stmtToIdf) {
                    $companyIdParam = (int)$company_id;
                    mysqli_stmt_bind_param($stmtToIdf, 'ii', $destinationIdfId, $companyIdParam);
                    mysqli_stmt_execute($stmtToIdf);
                    $resToIdf = mysqli_stmt_get_result($stmtToIdf);
                    $toIdfRow = $resToIdf ? mysqli_fetch_assoc($resToIdf) : null;
                    mysqli_stmt_close($stmtToIdf);
                    if ($toIdfRow) {
                        $idfCode = trim((string)($toIdfRow['idf_code'] ?? ''));
                        $idfName = trim((string)($toIdfRow['name'] ?? ''));
                        $connectedToValue = $idfCode !== '' ? $idfCode : $idfName;
                    }
                }
            }
            $stmtExistingIdfPort = null;
            if ($destinationIdfId > 0) {
                $stmtExistingIdfPort = mysqli_prepare(
                    $conn,
                    "SELECT ip.id, ip.notes
                     FROM idf_ports ip
                     JOIN idf_positions p ON p.id = ip.position_id
                     WHERE ip.company_id = ?
                       AND p.idf_id = ?
                       AND ip.port_no = ?
                       AND ip.port_type = ?
                       AND ip.notes LIKE ?
                     ORDER BY ip.id ASC
                     LIMIT 1"
                );
                if ($stmtExistingIdfPort) {
                    $companyIdParam = (int)$company_id;
                    $notesMarkerLike = '%' . $autoSyncMarker . '%';
                    mysqli_stmt_bind_param($stmtExistingIdfPort, 'iiiis', $companyIdParam, $destinationIdfId, $portNoParam, $portTypeId, $notesMarkerLike);
                }
            } else {
                $stmtExistingIdfPort = mysqli_prepare(
                    $conn,
                    "SELECT id, notes
                     FROM idf_ports
                     WHERE company_id = ? AND position_id = ? AND port_no = ? AND port_type = ?
                     LIMIT 1"
                );
                if ($stmtExistingIdfPort) {
                    $companyIdParam = (int)$company_id;
                    mysqli_stmt_bind_param($stmtExistingIdfPort, 'iiii', $companyIdParam, $positionId, $portNoParam, $portTypeId);
                }
            }
            if ($stmtExistingIdfPort) {
                mysqli_stmt_execute($stmtExistingIdfPort);
                $resExistingIdfPort = mysqli_stmt_get_result($stmtExistingIdfPort);
                $existingIdfPortRow = $resExistingIdfPort ? mysqli_fetch_assoc($resExistingIdfPort) : null;
                mysqli_stmt_close($stmtExistingIdfPort);
                if ($existingIdfPortRow) {
                    $existingIdfPortId = (int)($existingIdfPortRow['id'] ?? 0);
                    $existingIdfPortNotes = (string)($existingIdfPortRow['notes'] ?? '');
                }
            }
            if ($destinationIdfId > 0 && $existingIdfPortId <= 0) {
                $stmtEmptyPosition = mysqli_prepare(
                    $conn,
                    "SELECT id
                     FROM idf_positions
                     WHERE company_id = ?
                       AND idf_id = ?
                       AND (equipment_id IS NULL OR TRIM(equipment_id) = '' OR equipment_id = '0')
                     ORDER BY position_no ASC
                     LIMIT 1"
                );
                $emptyPositionId = 0;
                if ($stmtEmptyPosition) {
                    $companyIdParam = (int)$company_id;
                    mysqli_stmt_bind_param($stmtEmptyPosition, 'ii', $companyIdParam, $destinationIdfId);
                    mysqli_stmt_execute($stmtEmptyPosition);
                    $resEmptyPosition = mysqli_stmt_get_result($stmtEmptyPosition);
                    $emptyPositionRow = $resEmptyPosition ? mysqli_fetch_assoc($resEmptyPosition) : null;
                    mysqli_stmt_close($stmtEmptyPosition);
                    if ($emptyPositionRow) {
                        $emptyPositionId = (int)($emptyPositionRow['id'] ?? 0);
                    }
                }
                if ($emptyPositionId <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'There is none Empty positions, add more positions on IDF.']);
                    exit;
                }
                $targetInsertPositionId = $emptyPositionId;
            }

            if ($existingIdfPortId > 0) {
                $stmtUpdateIdfPort = mysqli_prepare(
                    $conn,
                    "UPDATE idf_ports
                     SET label = ?,
                         status_id = ?,
                         vlan_id = NULLIF(?, 0),
                         rj45_speed_id = NULLIF(?, 0),
                         speed_id = NULLIF(?, 0),
                         management_id = NULLIF(?, 0),
                         connected_to = ?,
                         notes = ?
                     WHERE id = ? AND company_id = ?
                     LIMIT 1"
                );
                if ($stmtUpdateIdfPort) {
                    $idfLabel = (string)($switchPortRow['to_patch_port'] ?? '');
                    $idfStatusId = (int)($switchPortRow['status_id'] ?? 0);
                    $idfVlanId = (int)($switchPortRow['vlan_id'] ?? 0);
                    $idfRj45SpeedId = (int)($switchPortRow['rj45_speed_id'] ?? 0);
                    $idfSpeedId = (int)($switchPortRow['fiber_port_id'] ?? 0);
                    $idfManagementId = (int)($switchPortRow['management_id'] ?? 0);
                    $idfNotes = trim((string)($switchPortRow['comments'] ?? ''));
                    if ($idfNotes === '') {
                        $idfNotes = $autoSyncMarker;
                    } elseif (strpos($idfNotes, $autoSyncMarker) === false) {
                        $idfNotes .= ' ' . $autoSyncMarker;
                    }
                    $companyIdParam = (int)$company_id;
                    mysqli_stmt_bind_param(
                        $stmtUpdateIdfPort,
                        'siiiiissii',
                        $idfLabel,
                        $idfStatusId,
                        $idfVlanId,
                        $idfRj45SpeedId,
                        $idfSpeedId,
                        $idfManagementId,
                        $connectedToValue,
                        $idfNotes,
                        $existingIdfPortId,
                        $companyIdParam
                    );
                    mysqli_stmt_execute($stmtUpdateIdfPort);
                    mysqli_stmt_close($stmtUpdateIdfPort);
                }

                if ($destinationIdfId <= 0 && strpos($existingIdfPortNotes, $autoSyncMarker) !== false) {
                    $stmtDeleteAutoIdfPort = mysqli_prepare(
                        $conn,
                        "DELETE FROM idf_ports WHERE id = ? AND company_id = ? LIMIT 1"
                    );
                    if ($stmtDeleteAutoIdfPort) {
                        $companyIdParam = (int)$company_id;
                        mysqli_stmt_bind_param($stmtDeleteAutoIdfPort, 'ii', $existingIdfPortId, $companyIdParam);
                        mysqli_stmt_execute($stmtDeleteAutoIdfPort);
                        mysqli_stmt_close($stmtDeleteAutoIdfPort);
                    }
                }
            } elseif ($destinationIdfId > 0) {
                $stmtInsertIdfPort = mysqli_prepare(
                    $conn,
                    "INSERT INTO idf_ports (
                        company_id, position_id, port_no, port_type, label, status_id, connected_to,
                        vlan_id, rj45_speed_id, speed_id, management_id, notes
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?
                     )"
                );
                if ($stmtInsertIdfPort) {
                    $companyIdParam = (int)$company_id;
                    $idfLabel = (string)($switchPortRow['to_patch_port'] ?? '');
                    $idfStatusId = (int)($switchPortRow['status_id'] ?? 0);
                    $idfVlanId = (int)($switchPortRow['vlan_id'] ?? 0);
                    $idfRj45SpeedId = (int)($switchPortRow['rj45_speed_id'] ?? 0);
                    $idfSpeedId = (int)($switchPortRow['fiber_port_id'] ?? 0);
                    $idfManagementId = (int)($switchPortRow['management_id'] ?? 0);
                    $idfNotes = trim((string)($switchPortRow['comments'] ?? ''));
                    if ($idfNotes === '') {
                        $idfNotes = $autoSyncMarker;
                    } elseif (strpos($idfNotes, $autoSyncMarker) === false) {
                        $idfNotes .= ' ' . $autoSyncMarker;
                    }
                    mysqli_stmt_bind_param(
                        $stmtInsertIdfPort,
                        'iiiisisiiiis',
                        $companyIdParam,
                        $targetInsertPositionId,
                        $portNoParam,
                        $portTypeId,
                        $idfLabel,
                        $idfStatusId,
                        $connectedToValue,
                        $idfVlanId,
                        $idfRj45SpeedId,
                        $idfSpeedId,
                        $idfManagementId,
                        $idfNotes
                    );
                    mysqli_stmt_execute($stmtInsertIdfPort);
                    mysqli_stmt_close($stmtInsertIdfPort);
                }
            }
        }
    }
}



if ($updated <= 0) {
    itm_api_json_response(['success' => false, 'error' => 'Port not found or not permitted', 'updated' => 0], 404);
}

echo json_encode(['success' => true, 'updated' => $updated]);
