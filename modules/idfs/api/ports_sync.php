<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../idf_ports_sync.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}

$synced = idf_ensure_ports_for_position($conn, $company_id, $position_id);

idf_ok([
    'synced' => $synced,
    'position_id' => $position_id,
]);
