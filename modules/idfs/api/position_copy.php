<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$position_id = (int)($data['position_id'] ?? 0);
$target_position = (int)($data['target_position'] ?? 0);
$overwrite = (int)($data['overwrite'] ?? 0);

if ($position_id <= 0) {
    idf_fail('Invalid position_id');
}
if ($target_position < 1 || $target_position > 10) {
    idf_fail('Invalid target_position');
}

$res = mysqli_query(
    $conn,
    "SELECT p.*, i.company_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=$position_id
     LIMIT 1"
);
$src = $res ? mysqli_fetch_assoc($res) : null;
if (!$src || (int)$src['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$idf_id = (int)$src['idf_id'];

$existing = null;
$resEx = mysqli_query($conn, "SELECT id FROM idf_positions WHERE idf_id=$idf_id AND position_no=$target_position LIMIT 1");
if ($resEx) {
    $existing = mysqli_fetch_assoc($resEx);
}

if ($existing && !$overwrite) {
    idf_fail('Target position occupied');
}

mysqli_begin_transaction($conn);
try {
    if ($existing && $overwrite) {
        mysqli_query($conn, 'DELETE FROM idf_positions WHERE id=' . (int)$existing['id'] . ' LIMIT 1');
    }

    $nameEsc = idf_escape($conn, (string)$src['device_name']);
    $notesSql = !empty($src['notes']) ? ("'" . idf_escape($conn, (string)$src['notes']) . "'") : 'NULL';
    $equipSql = !empty($src['equipment_id']) ? (string)((int)$src['equipment_id']) : 'NULL';

    mysqli_query(
        $conn,
        "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, port_count, notes)
         VALUES ($company_id, $idf_id, $target_position, '" . idf_escape($conn, (string)$src['device_type']) . "', '$nameEsc', $equipSql, " . (int)$src['port_count'] . ", $notesSql)"
    );

    $newPosId = (int)mysqli_insert_id($conn);

    $ports = [];
    $resPorts = mysqli_query($conn, 'SELECT * FROM idf_ports WHERE position_id=' . (int)$src['id'] . ' ORDER BY port_no');
    while ($resPorts && ($p = mysqli_fetch_assoc($resPorts))) {
        $ports[] = $p;
    }

    if ($ports) {
        $vals = [];
        foreach ($ports as $p) {
            $vals[] = '(' .
                $company_id . ',' .
                $newPosId . ',' .
                (int)$p['port_no'] . ',' .
                "'" . idf_escape($conn, (string)$p['port_type']) . "'," .
                "'" . idf_escape($conn, (string)$p['label']) . "'," .
                "'" . idf_escape($conn, (string)$p['status']) . "'," .
                "'" . idf_escape($conn, (string)$p['connected_to']) . "'," .
                "'" . idf_escape($conn, (string)$p['vlan']) . "'," .
                "'" . idf_escape($conn, (string)$p['speed']) . "'," .
                "'" . idf_escape($conn, (string)$p['poe']) . "'," .
                "'" . idf_escape($conn, (string)$p['notes']) . "'" .
                ')';
        }
        mysqli_query(
            $conn,
            'INSERT INTO idf_ports (company_id, position_id, port_no, port_type, label, status, connected_to, vlan, speed, poe, notes) VALUES ' . implode(',', $vals)
        );
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Copy failed', 500);
}

idf_ok();
