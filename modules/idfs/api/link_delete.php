<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$link_id = (int)($data['link_id'] ?? 0);
if ($link_id <= 0) {
    idf_fail('Invalid link_id');
}

$res = mysqli_query(
    $conn,
    "SELECT l.id, i.company_id
     FROM idf_links l
     JOIN idf_ports a ON a.id=l.port_id_a
     JOIN idf_positions pa ON pa.id=a.position_id
     JOIN idfs i ON i.id=pa.idf_id
     WHERE l.id=$link_id
     LIMIT 1"
);
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

if (!mysqli_query($conn, "DELETE FROM idf_links WHERE id=$link_id LIMIT 1")) {
    idf_fail('DB error deleting link: ' . mysqli_error($conn), 500);
}

idf_ok();
