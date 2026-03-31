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
    $companyFilter = '';
    if (table_has_column($conn, $table, 'company_id') && isset($GLOBALS['company_id']) && (int)$GLOBALS['company_id'] > 0) {
        $companyFilter = ' WHERE company_id = ' . (int)$GLOBALS['company_id'];
    }
    $res = mysqli_query($conn, "SELECT id, `{$labelEsc}` AS label FROM `{$tableEsc}`{$companyFilter} ORDER BY id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['label']];
    }
    return $rows;
}

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

$hasEquipmentId = table_has_column($conn, 'switch_ports', 'equipment_id');
$hasStatusId = table_has_column($conn, 'switch_ports', 'status_id');
$hasColorId = table_has_column($conn, 'switch_ports', 'color_id');
$hasVlanId = table_has_column($conn, 'switch_ports', 'vlan_id');

if (!$hasStatusId || !$hasColorId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_ports schema is missing status_id/color_id columns']);
    exit;
}

$statuses = fetch_lookup_map($conn, 'switch_status', 'status');
$colors = fetch_lookup_map($conn, 'switch_cablecolors', 'color');
$vlans = fetch_company_vlans($conn, (int)$company_id);

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

$colorId = find_lookup_id($colors, $input['color'] ?? null);
$statusId = find_lookup_id($statuses, $input['status'] ?? null);
$vlanId = $hasVlanId ? find_lookup_id($vlans, $input['vlan'] ?? null) : 0;
$label = isset($input['label']) ? trim((string)$input['label']) : null;
$comments = isset($input['comments']) ? trim((string)$input['comments']) : null;

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

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'Nothing to update']);
    exit;
}

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
