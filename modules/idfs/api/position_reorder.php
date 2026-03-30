<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$order = $data['order'] ?? null;

if ($idf_id <= 0) {
    idf_fail('Invalid idf_id');
}
if (!is_array($order) || count($order) !== 10) {
    idf_fail('Invalid order payload');
}

$resIdf = mysqli_query($conn, "SELECT id FROM idfs WHERE id=$idf_id AND company_id=$company_id LIMIT 1");
if (!$resIdf || mysqli_num_rows($resIdf) !== 1) {
    idf_fail('IDF not found', 404);
}

$map = [];
foreach ($order as $item) {
    if (!is_array($item)) {
        idf_fail('Invalid item');
    }
    $posNo = (int)($item['position_no'] ?? 0);
    $posId = isset($item['position_id']) && $item['position_id'] !== null ? (int)$item['position_id'] : null;
    if ($posNo < 1 || $posNo > 10) {
        idf_fail('Invalid position_no');
    }
    if ($posId !== null && $posId <= 0) {
        idf_fail('Invalid position_id');
    }
    $map[] = ['no' => $posNo, 'id' => $posId];
}

$ids = array_values(array_filter(array_map(static fn($x) => $x['id'], $map), static fn($v) => $v !== null));
$ids = array_values(array_unique($ids));

if ($ids) {
    $idList = implode(',', array_map('intval', $ids));
    $resChk = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c
         FROM idf_positions p
         JOIN idfs i ON i.id=p.idf_id
         WHERE p.id IN ($idList) AND p.idf_id=$idf_id AND i.company_id=$company_id"
    );
    $c = 0;
    if ($resChk && ($r = mysqli_fetch_assoc($resChk))) {
        $c = (int)$r['c'];
    }
    if ($c !== count($ids)) {
        idf_fail('One or more positions invalid', 400);
    }
}

mysqli_begin_transaction($conn);
try {
    if ($ids) {
        $idList = implode(',', array_map('intval', $ids));
        mysqli_query($conn, "UPDATE idf_positions SET position_no=position_no+100 WHERE id IN ($idList) AND idf_id=$idf_id");
    }

    foreach ($map as $item) {
        if ($item['id'] === null) {
            continue;
        }
        $pid = (int)$item['id'];
        $pno = (int)$item['no'];
        mysqli_query($conn, "UPDATE idf_positions SET position_no=$pno WHERE id=$pid AND idf_id=$idf_id LIMIT 1");
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Reorder failed', 500);
}

idf_ok();
