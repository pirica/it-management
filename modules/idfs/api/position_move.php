<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$position_no = (int)($data['position_no'] ?? 0);
$dir = (string)($data['dir'] ?? '');

if ($idf_id <= 0 || $position_no < 1 || $position_no > 10) {
    idf_fail('Invalid idf_id/position_no');
}
if (!in_array($dir, ['up', 'down'], true)) {
    idf_fail('Invalid dir');
}

$resIdf = mysqli_query($conn, "SELECT id FROM idfs WHERE id=$idf_id AND company_id=$company_id LIMIT 1");
if (!$resIdf || mysqli_num_rows($resIdf) !== 1) {
    idf_fail('IDF not found', 404);
}

$target = $dir === 'up' ? $position_no - 1 : $position_no + 1;
if ($target < 1 || $target > 10) {
    idf_ok();
}

mysqli_begin_transaction($conn);
try {
    $tmp = 99;
    mysqli_query($conn, "UPDATE idf_positions SET position_no=$tmp WHERE idf_id=$idf_id AND position_no=$position_no LIMIT 1");
    mysqli_query($conn, "UPDATE idf_positions SET position_no=$position_no WHERE idf_id=$idf_id AND position_no=$target LIMIT 1");
    mysqli_query($conn, "UPDATE idf_positions SET position_no=$target WHERE idf_id=$idf_id AND position_no=$tmp LIMIT 1");
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Move failed', 500);
}

idf_ok();
