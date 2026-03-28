<?php
require 'config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$existsSql = "SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = $company_id";
$existsRes = mysqli_query($conn, $existsSql);
$existingCount = 0;
if ($existsRes) {
    $existingCount = (int)(mysqli_fetch_assoc($existsRes)['c'] ?? 0);
}

if ($existingCount === 0) {
    $values = [];
    for ($n = 1; $n <= 48; $n++) {
        $label = 'Port ' . $n;
        $values[] = "($company_id, $n, '" . mysqli_real_escape_string($conn, $label) . "', 'unknown', 'black', '')";
    }

    $seedSql = "INSERT INTO switch_ports (company_id, port_number, label, status, color, comments) VALUES " . implode(', ', $values);
    mysqli_query($conn, $seedSql);
}

$sql = "SELECT id, port_number, label, status, color, comments FROM switch_ports WHERE company_id = $company_id ORDER BY port_number ASC";
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

echo json_encode(['success' => true, 'ports' => $ports]);
