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

$switchId = (int)($_GET['switch_id'] ?? 0);
if ($switchId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing switch id']);
    exit;
}

$switchSql = "SELECT e.id, e.name, COALESCE(er.name, '24 ports') AS rj45_name,
                     COALESCE(ef.name, '') AS fiber_name, COALESCE(efc.name, '0') AS fiber_count
              FROM equipment e
              LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
              LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
              LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
              LEFT JOIN equipment_fiber_count efc ON efc.id = e.switch_fiber_count_id
              WHERE e.id = $switchId
                AND e.company_id = $company_id
              LIMIT 1";
$switchRes = mysqli_query($conn, $switchSql);
$switch = $switchRes ? mysqli_fetch_assoc($switchRes) : null;
if (!$switch) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Switch not found']);
    exit;
}

$rj45Count = (int)preg_replace('/\D+/', '', (string)$switch['rj45_name']);
if ($rj45Count <= 0) {
    $rj45Count = 24;
}
$fiberCount = (int)preg_replace('/\D+/', '', (string)$switch['fiber_count']);
$fiberName = strtolower(trim((string)$switch['fiber_name']));
$sfpCount = str_contains($fiberName, 'sfp+') ? 0 : (str_contains($fiberName, 'sfp') ? $fiberCount : 0);
$sfpPlusCount = str_contains($fiberName, 'sfp+') ? $fiberCount : 0;

function seed_ports(mysqli $conn, int $companyId, int $switchId, string $portType, int $count): void
{
    if ($count <= 0) {
        return;
    }
    $safePortType = mysqli_real_escape_string($conn, $portType);
    $existsSql = "SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = $companyId AND equipment_id = $switchId AND port_type = '$safePortType'";
    $existsRes = mysqli_query($conn, $existsSql);
    $existingCount = $existsRes ? (int)(mysqli_fetch_assoc($existsRes)['c'] ?? 0) : 0;
    if ($existingCount > 0) {
        return;
    }
    $values = [];
    for ($n = 1; $n <= $count; $n++) {
        $label = strtoupper(str_replace('_', '+', $portType)) . ' ' . $n;
        $values[] = "($companyId, $switchId, '$safePortType', $n, '" . mysqli_real_escape_string($conn, $label) . "', 'unknown', 'black', '')";
    }
    $seedSql = "INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, label, status, color, comments) VALUES " . implode(', ', $values);
    mysqli_query($conn, $seedSql);
}

seed_ports($conn, (int)$company_id, $switchId, 'rj45', $rj45Count);
seed_ports($conn, (int)$company_id, $switchId, 'sfp', $sfpCount);
seed_ports($conn, (int)$company_id, $switchId, 'sfp_plus', $sfpPlusCount);

$sql = "SELECT id, port_type, port_number, label, status, color, comments
        FROM switch_ports
        WHERE company_id = $company_id
          AND equipment_id = $switchId
        ORDER BY FIELD(port_type, 'rj45', 'sfp', 'sfp_plus'), port_number ASC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

$ports = [];
while ($row = mysqli_fetch_assoc($result)) {
    $ports[] = $row;
}

echo json_encode([
    'success' => true,
    'ports' => $ports,
    'layout' => [
        'rj45' => $rj45Count,
        'sfp' => $sfpCount,
        'sfp_plus' => $sfpPlusCount,
    ],
]);
