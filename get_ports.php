<?php
require 'config/config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function ensure_switch_ports_schema(mysqli $conn): bool
{
    $hasColumn = static function (string $name) use ($conn): bool {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM switch_ports LIKE '" . mysqli_real_escape_string($conn, $name) . "'");
        return $res && mysqli_num_rows($res) > 0;
    };
    return $hasColumn('equipment_id') && $hasColumn('port_type');
}

if (!ensure_switch_ports_schema($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_ports schema is missing equipment_id/port_type columns']);
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
              WHERE e.id = ?
                AND e.company_id = ?
              LIMIT 1";
$switchStmt = mysqli_prepare($conn, $switchSql);
$switch = null;
if ($switchStmt) {
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($switchStmt, 'ii', $switchId, $companyId);
    if (mysqli_stmt_execute($switchStmt)) {
        $switchRes = mysqli_stmt_get_result($switchStmt);
        $switch = $switchRes ? mysqli_fetch_assoc($switchRes) : null;
    }
    mysqli_stmt_close($switchStmt);
}
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
    $existsSql = 'SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND port_type = ?';
    $existsStmt = mysqli_prepare($conn, $existsSql);
    $existingCount = 0;
    if ($existsStmt) {
        mysqli_stmt_bind_param($existsStmt, 'iis', $companyId, $switchId, $portType);
        if (mysqli_stmt_execute($existsStmt)) {
            $existsRes = mysqli_stmt_get_result($existsStmt);
            $existingCount = (int)(($existsRes ? mysqli_fetch_assoc($existsRes) : [])['c'] ?? 0);
        }
        mysqli_stmt_close($existsStmt);
    }
    if ($existingCount > 0) {
        return;
    }
    $insertSql = 'INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, label, status, color, comments) VALUES (?, ?, ?, ?, ?, "unknown", "black", "")';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return;
    }
    for ($n = 1; $n <= $count; $n++) {
        $label = strtoupper(str_replace('_', '+', $portType)) . ' ' . $n;
        mysqli_stmt_bind_param($insertStmt, 'iisis', $companyId, $switchId, $portType, $n, $label);
        mysqli_stmt_execute($insertStmt);
    }
    mysqli_stmt_close($insertStmt);
}

seed_ports($conn, (int)$company_id, $switchId, 'rj45', $rj45Count);
seed_ports($conn, (int)$company_id, $switchId, 'sfp', $sfpCount);
seed_ports($conn, (int)$company_id, $switchId, 'sfp_plus', $sfpPlusCount);

$sql = "SELECT id, port_type, port_number, label, status, color, comments
        FROM switch_ports
        WHERE company_id = ?
          AND equipment_id = ?
        ORDER BY FIELD(port_type, 'rj45', 'sfp', 'sfp_plus'), port_number ASC";
$stmt = mysqli_prepare($conn, $sql);
$result = false;
if ($stmt) {
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $switchId);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
    }
}

if (!$result) {
    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

$ports = [];
while ($row = mysqli_fetch_assoc($result)) {
    $ports[] = $row;
}
if ($stmt) {
    mysqli_stmt_close($stmt);
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
