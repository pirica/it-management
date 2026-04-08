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

$stmtSrc = mysqli_prepare(
    $conn,
    "SELECT p.*, i.company_id
     FROM idf_positions p
     JOIN idfs i ON i.id=p.idf_id
     WHERE p.id=?
     LIMIT 1"
);
$src = null;
if ($stmtSrc) {
    mysqli_stmt_bind_param($stmtSrc, 'i', $position_id);
    mysqli_stmt_execute($stmtSrc);
    $res = mysqli_stmt_get_result($stmtSrc);
    $src = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmtSrc);
}

if (!$src || (int)$src['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$idf_id = (int)$src['idf_id'];

$existing = null;
$stmtEx = mysqli_prepare($conn, "SELECT id FROM idf_positions WHERE idf_id=? AND position_no=? LIMIT 1");
if ($stmtEx) {
    mysqli_stmt_bind_param($stmtEx, 'ii', $idf_id, $target_position);
    mysqli_stmt_execute($stmtEx);
    $resEx = mysqli_stmt_get_result($stmtEx);
    if ($resEx) {
        $existing = mysqli_fetch_assoc($resEx);
    }
    mysqli_stmt_close($stmtEx);
}

if ($existing && !$overwrite) {
    idf_fail('Target position occupied');
}

mysqli_begin_transaction($conn);
try {
    if ($existing && $overwrite) {
        $stmtDel = mysqli_prepare($conn, 'DELETE FROM idf_positions WHERE id=? LIMIT 1');
        if ($stmtDel) {
            $exId = (int)$existing['id'];
            mysqli_stmt_bind_param($stmtDel, 'i', $exId);
            mysqli_stmt_execute($stmtDel);
            mysqli_stmt_close($stmtDel);
        }
    }

    $notes_val = !empty($src['notes']) ? (string)$src['notes'] : null;
    $equip_val = !empty($src['equipment_id']) ? (int)$src['equipment_id'] : null;
    $device_type = (string)$src['device_type'];
    $device_name = (string)$src['device_name'];
    $port_count = (int)$src['port_count'];

    $stmtIns = mysqli_prepare(
        $conn,
        "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, port_count, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmtIns) {
        mysqli_stmt_bind_param($stmtIns, 'iiisssis', $company_id, $idf_id, $target_position, $device_type, $device_name, $equip_val, $port_count, $notes_val);
        mysqli_stmt_execute($stmtIns);
        mysqli_stmt_close($stmtIns);
    }

    $newPosId = (int)mysqli_insert_id($conn);

    $ports = [];
    $stmtPorts = mysqli_prepare($conn, 'SELECT * FROM idf_ports WHERE position_id=? ORDER BY port_no');
    if ($stmtPorts) {
        $srcId = (int)$src['id'];
        mysqli_stmt_bind_param($stmtPorts, 'i', $srcId);
        mysqli_stmt_execute($stmtPorts);
        $resPorts = mysqli_stmt_get_result($stmtPorts);
        while ($resPorts && ($p = mysqli_fetch_assoc($resPorts))) {
            $ports[] = $p;
        }
        mysqli_stmt_close($stmtPorts);
    }

    if ($ports) {
        $sqlInsPort = 'INSERT INTO idf_ports (company_id, position_id, port_no, port_type, label, status, connected_to, vlan, speed, poe, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmtInsPort = mysqli_prepare($conn, $sqlInsPort);
        if ($stmtInsPort) {
            foreach ($ports as $p) {
                $p_no = (int)$p['port_no'];
                $p_type = (string)$p['port_type'];
                $p_label = (string)$p['label'];
                $p_status = (string)$p['status'];
                $p_conn = (string)$p['connected_to'];
                $p_vlan = (string)$p['vlan'];
                $p_speed = (string)$p['speed'];
                $p_poe = (string)$p['poe'];
                $p_notes = (string)$p['notes'];

                mysqli_stmt_bind_param($stmtInsPort, 'iiissssssss',
                    $company_id, $newPosId, $p_no, $p_type, $p_label, $p_status, $p_conn, $p_vlan, $p_speed, $p_poe, $p_notes
                );
                mysqli_stmt_execute($stmtInsPort);
            }
            mysqli_stmt_close($stmtInsPort);
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Copy failed: ' . $e->getMessage(), 500);
}

idf_ok();
