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
    "SELECT p.port_count, p.id, i.company_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=$position_id
     LIMIT 1"
);
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$count = (int)$row['port_count'];
if ($count <= 0) {
    idf_fail('This device has port_count=0');
}

mysqli_begin_transaction($conn);
try {
    mysqli_query($conn, "DELETE FROM idf_ports WHERE position_id=$position_id");
    $vals = [];
    for ($n = 1; $n <= $count; $n++) {
        $vals[] = "($company_id,$position_id,$n,'RJ45','unknown')";
    }
    mysqli_query($conn, 'INSERT INTO idf_ports (company_id, position_id, port_no, port_type, status) VALUES ' . implode(',', $vals));
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Regen failed', 500);
}

idf_ok();
