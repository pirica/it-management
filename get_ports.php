<?php
require 'config/config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function table_has_column(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res && mysqli_num_rows($res) > 0;
}

function fetch_lookup_map(mysqli $conn, string $table, string $labelColumn): array
{
    $rows = [];
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $labelEsc = mysqli_real_escape_string($conn, $labelColumn);
    $res = mysqli_query($conn, "SELECT id, `{$labelEsc}` AS label FROM `{$tableEsc}` ORDER BY id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['label']];
    }
    return $rows;
}

function lookup_id_by_name(array $items, string $wanted, int $fallback = 0): int
{
    foreach ($items as $item) {
        if (strtolower(trim((string)$item['name'])) === strtolower(trim($wanted))) {
            return (int)$item['id'];
        }
    }
    return $fallback > 0 ? $fallback : (int)($items[0]['id'] ?? 0);
}


function normalize_port_type(string $portType): string
{
    $normalized = strtolower(trim($portType));
    $normalized = str_replace(['+', '-', '/'], [' plus ', ' ', ' '], $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    if (str_contains($normalized, 'sfp') && str_contains($normalized, 'plus')) {
        return 'sfp_plus';
    }
    if (str_contains($normalized, 'sfp')) {
        return 'sfp';
    }
    return 'rj45';
}


$hasEquipmentId = table_has_column($conn, 'switch_ports', 'equipment_id');
$hasPortType = table_has_column($conn, 'switch_ports', 'port_type');
$hasStatusId = table_has_column($conn, 'switch_ports', 'status_id');
$hasColorId = table_has_column($conn, 'switch_ports', 'color_id');
$hasLegacyNumberPort = table_has_column($conn, 'equipment', 'numberport');

if (!$hasStatusId || !$hasColorId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_ports schema is missing status_id/color_id columns']);
    exit;
}

$statuses = fetch_lookup_map($conn, 'switch_status', 'status');
$colors = fetch_lookup_map($conn, 'switch_cablecolors', 'color');
if (empty($statuses) || empty($colors)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_status/switch_cablecolors lookup tables are empty']);
    exit;
}

$defaultStatusId = lookup_id_by_name($statuses, 'Unknown');
$defaultColorId = lookup_id_by_name($colors, 'black', lookup_id_by_name($colors, 'grey'));

$switchId = (int)($_GET['switch_id'] ?? 0);
if ($switchId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing switch id']);
    exit;
}

$legacyNumberPortSql = $hasLegacyNumberPort ? 'e.numberport AS legacy_numberport,' : 'NULL AS legacy_numberport,';
$switchSql = "SELECT e.id, e.name, {$legacyNumberPortSql} COALESCE(er.name, '24 ports') AS rj45_name,
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

$rj45Count = (int)($switch['legacy_numberport'] ?? 0);
if ($rj45Count <= 0) {
    $rj45Count = (int)preg_replace('/\D+/', '', (string)$switch['rj45_name']);
}
if ($rj45Count <= 0) {
    $rj45Count = 24;
}
$fiberCount = (int)preg_replace('/\D+/', '', (string)$switch['fiber_count']);
$fiberName = strtolower(trim((string)$switch['fiber_name']));
$sfpCount = str_contains($fiberName, 'sfp+') ? 0 : (str_contains($fiberName, 'sfp') ? $fiberCount : 0);
$sfpPlusCount = str_contains($fiberName, 'sfp+') ? $fiberCount : 0;

function seed_ports(mysqli $conn, int $companyId, int $switchId, string $portType, int $count, bool $hasEquipmentId, bool $hasPortType, int $defaultStatusId, int $defaultColorId): void
{
    if ($count <= 0) {
        return;
    }

    if ($hasEquipmentId && $hasPortType) {
        $existingPortNumbers = [];
        $existingSql = 'SELECT port_number FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND port_type = ? ORDER BY port_number ASC';
        $existingStmt = mysqli_prepare($conn, $existingSql);
        if ($existingStmt) {
            mysqli_stmt_bind_param($existingStmt, 'iis', $companyId, $switchId, $portType);
            if (mysqli_stmt_execute($existingStmt)) {
                $existingRes = mysqli_stmt_get_result($existingStmt);
                while ($existingRes && ($existingRow = mysqli_fetch_assoc($existingRes))) {
                    $portNumber = (int)($existingRow['port_number'] ?? 0);
                    if ($portNumber > 0) {
                        $existingPortNumbers[$portNumber] = true;
                    }
                }
            }
            mysqli_stmt_close($existingStmt);
        }

        $insertSql = 'INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, label, status_id, color_id, comments) VALUES (?, ?, ?, ?, ?, ?, ?, "")';
        $insertStmt = mysqli_prepare($conn, $insertSql);
        if (!$insertStmt) {
            return;
        }
        for ($n = 1; $n <= $count; $n++) {
            if (isset($existingPortNumbers[$n])) {
                continue;
            }
            $label = strtoupper(str_replace('_', '+', $portType)) . ' ' . $n;
            mysqli_stmt_bind_param($insertStmt, 'iisiiii', $companyId, $switchId, $portType, $n, $label, $defaultStatusId, $defaultColorId);
            mysqli_stmt_execute($insertStmt);
        }
        mysqli_stmt_close($insertStmt);

        $deleteSql = 'DELETE FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND port_type = ? AND port_number > ?';
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, 'iisi', $companyId, $switchId, $portType, $count);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
        }
        return;
    }

    if ($portType !== 'rj45') {
        return;
    }
    $existsSql = 'SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ?';
    $existsStmt = mysqli_prepare($conn, $existsSql);
    $existingCount = 0;
    if ($existsStmt) {
        mysqli_stmt_bind_param($existsStmt, 'i', $companyId);
        if (mysqli_stmt_execute($existsStmt)) {
            $existsRes = mysqli_stmt_get_result($existsStmt);
            $existingCount = (int)(($existsRes ? mysqli_fetch_assoc($existsRes) : [])['c'] ?? 0);
        }
        mysqli_stmt_close($existsStmt);
    }
    if ($existingCount >= $count) {
        return;
    }

    $insertSql = 'INSERT INTO switch_ports (company_id, port_number, label, status_id, color_id, comments) VALUES (?, ?, ?, ?, ?, "")';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return;
    }
    for ($n = $existingCount + 1; $n <= $count; $n++) {
        $label = 'RJ45 ' . $n;
        mysqli_stmt_bind_param($insertStmt, 'iisii', $companyId, $n, $label, $defaultStatusId, $defaultColorId);
        mysqli_stmt_execute($insertStmt);
    }
    mysqli_stmt_close($insertStmt);
}

seed_ports($conn, (int)$company_id, $switchId, 'rj45', $rj45Count, $hasEquipmentId, $hasPortType, $defaultStatusId, $defaultColorId);
seed_ports($conn, (int)$company_id, $switchId, 'sfp', $sfpCount, $hasEquipmentId, $hasPortType, $defaultStatusId, $defaultColorId);
seed_ports($conn, (int)$company_id, $switchId, 'sfp_plus', $sfpPlusCount, $hasEquipmentId, $hasPortType, $defaultStatusId, $defaultColorId);

if ($hasEquipmentId && $hasPortType) {
    $sql = "SELECT sp.id, sp.port_type, sp.port_number, sp.label, ss.status, sc.color, sp.comments
            FROM switch_ports sp
            LEFT JOIN switch_status ss ON ss.id = sp.status_id
            LEFT JOIN switch_cablecolors sc ON sc.id = sp.color_id
            WHERE sp.company_id = ?
              AND sp.equipment_id = ?
            ORDER BY FIELD(sp.port_type, 'rj45', 'sfp', 'sfp_plus'), sp.port_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    $result = false;
    if ($stmt) {
        $companyId = (int)$company_id;
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $switchId);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
        }
    }
} else {
    $sql = "SELECT sp.id, 'rj45' AS port_type, sp.port_number, sp.label, ss.status, sc.color, sp.comments
            FROM switch_ports sp
            LEFT JOIN switch_status ss ON ss.id = sp.status_id
            LEFT JOIN switch_cablecolors sc ON sc.id = sp.color_id
            WHERE sp.company_id = ?
            ORDER BY sp.port_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    $result = false;
    if ($stmt) {
        $companyId = (int)$company_id;
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
        }
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
    $row['port_type'] = normalize_port_type((string)($row['port_type'] ?? 'rj45'));
    $ports[] = $row;
}
if ($stmt) {
    mysqli_stmt_close($stmt);
}

echo json_encode([
    'success' => true,
    'ports' => $ports,
    'statuses' => $statuses,
    'colors' => $colors,
    'layout' => [
        'rj45' => $rj45Count,
        'sfp' => ($hasEquipmentId && $hasPortType) ? $sfpCount : 0,
        'sfp_plus' => ($hasEquipmentId && $hasPortType) ? $sfpPlusCount : 0,
    ],
]);
