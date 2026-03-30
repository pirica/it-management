<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}

$res = mysqli_query(
    $conn,
    "SELECT p.id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=$position_id AND i.company_id=$company_id
     LIMIT 1"
);
if (!$res || mysqli_num_rows($res) !== 1) {
    idf_fail('Not found', 404);
}

if (!mysqli_query($conn, "DELETE FROM idf_positions WHERE id=$position_id LIMIT 1")) {
    idf_fail('DB error deleting: ' . mysqli_error($conn), 500);
}

idf_ok();
