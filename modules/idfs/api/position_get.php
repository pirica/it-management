<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT p.*, i.company_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=?
     LIMIT 1"
);

$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $position_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}
if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

unset($row['company_id']);
idf_ok(['position' => $row]);
