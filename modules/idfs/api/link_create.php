<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$portA = (int)($data['port_id_a'] ?? 0);
$portB = (int)($data['port_id_b'] ?? 0);
$color = trim((string)($data['cable_color'] ?? 'yellow'));
$label = trim((string)($data['cable_label'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

if ($portA <= 0 || $portB <= 0) {
    idf_fail('Invalid port ids');
}
if ($portA === $portB) {
    idf_fail('Cannot link a port to itself');
}
if ($color === '') {
    $color = 'yellow';
}

$low = min($portA, $portB);
$high = max($portA, $portB);

$res = mysqli_query(
    $conn,
    "SELECT pr.id AS port_id, i.company_id
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id IN ($low,$high)"
);

$seen = [];
while ($res && ($r = mysqli_fetch_assoc($res))) {
    $seen[(int)$r['port_id']] = (int)$r['company_id'];
}

if (count($seen) !== 2) {
    idf_fail('Port not found', 404);
}
if ($seen[$low] !== $company_id || $seen[$high] !== $company_id) {
    idf_fail('Forbidden', 403);
}

$resUsed = mysqli_query(
    $conn,
    "SELECT id FROM idf_links
     WHERE port_id_a IN ($low,$high) OR port_id_b IN ($low,$high)
     LIMIT 1"
);
if ($resUsed && mysqli_num_rows($resUsed) > 0) {
    idf_fail('One of the ports is already linked');
}

$colorSql = "'" . idf_escape($conn, $color) . "'";
$labelSql = $label !== '' ? ("'" . idf_escape($conn, $label) . "'") : 'NULL';
$notesSql = $notes !== '' ? ("'" . idf_escape($conn, $notes) . "'") : 'NULL';

if (!mysqli_query(
    $conn,
    "INSERT INTO idf_links (port_id_a, port_id_b, cable_color, cable_label, notes)
     VALUES ($low,$high,$colorSql,$labelSql,$notesSql)"
)) {
    idf_fail('DB error creating link: ' . mysqli_error($conn), 500);
}

idf_ok(['link_id' => (int)mysqli_insert_id($conn)]);
