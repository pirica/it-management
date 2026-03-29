<?php
require 'config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function ensure_switch_ports_schema(mysqli $conn): bool
{
    $recheckColumn = static function (string $name) use ($conn): bool {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM switch_ports LIKE '" . mysqli_real_escape_string($conn, $name) . "'");
        return $res && mysqli_num_rows($res) > 0;
    };

    $hasEquipmentId = mysqli_query($conn, "SHOW COLUMNS FROM switch_ports LIKE 'equipment_id'");
    if (!$hasEquipmentId || mysqli_num_rows($hasEquipmentId) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE switch_ports ADD COLUMN equipment_id INT NULL AFTER company_id") && !$recheckColumn('equipment_id')) {
            return false;
        }
    }
    $hasPortType = mysqli_query($conn, "SHOW COLUMNS FROM switch_ports LIKE 'port_type'");
    if (!$hasPortType || mysqli_num_rows($hasPortType) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE switch_ports ADD COLUMN port_type ENUM('rj45','sfp','sfp_plus') NOT NULL DEFAULT 'rj45' AFTER equipment_id") && !$recheckColumn('port_type')) {
            return false;
        }
    }
    @mysqli_query($conn, "ALTER TABLE switch_ports DROP INDEX unique_switch_port");
    @mysqli_query($conn, "ALTER TABLE switch_ports ADD UNIQUE KEY unique_switch_port (company_id, equipment_id, port_type, port_number)");
    @mysqli_query($conn, "ALTER TABLE switch_ports ADD KEY equipment_id (equipment_id)");
    @mysqli_query($conn, "ALTER TABLE switch_ports ADD CONSTRAINT switch_ports_ibfk_2 FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE");
    return true;
}

if (!ensure_switch_ports_schema($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Schema update failed: ' . mysqli_error($conn)]);
    exit;
}

$raw = file_get_contents('php://input');
$decoded = json_decode($raw, true);
$input = is_array($decoded) ? $decoded : $_POST;

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
$allowedColors = ['green', 'red', 'yellow', 'black', 'blue', 'white', 'orange', 'purple'];
$allowedStatus = ['uplink', 'empty', 'down', 'unknown'];

$color = (isset($input['color']) && in_array($input['color'], $allowedColors, true)) ? $input['color'] : null;
$status = (isset($input['status']) && in_array($input['status'], $allowedStatus, true)) ? $input['status'] : null;
$label = isset($input['label']) ? trim((string)$input['label']) : null;
$comments = isset($input['comments']) ? trim((string)$input['comments']) : null;

$fields = [];
$types = '';
$params = [];

if ($color !== null) {
    $fields[] = 'color = ?';
    $types .= 's';
    $params[] = $color;
}
if ($status !== null) {
    $fields[] = 'status = ?';
    $types .= 's';
    $params[] = $status;
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

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'Nothing to update']);
    exit;
}

$sql = 'UPDATE switch_ports SET ' . implode(', ', $fields) . ' WHERE id = ? AND company_id = ? AND equipment_id = ?';
$types .= 'iii';
$params[] = $id;
$params[] = (int)$company_id;
$params[] = $switchId;

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
