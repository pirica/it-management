<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$port_id = (int)($data['port_id'] ?? 0);
if ($port_id <= 0) {
    idf_fail('Invalid port_id');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT pr.id, i.company_id
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id=?
     LIMIT 1"
);
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $port_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$port_type_id = idf_resolve_port_type_id($conn, $company_id, $data['port_type_id'] ?? ($data['port_type'] ?? ''), 'RJ45');
$status_id = idf_resolve_status_id($conn, $company_id, $data['status_id'] ?? ($data['status'] ?? ''), 'Unknown');
$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}
if ($port_type_id <= 0) {
    idf_fail('Invalid port_type');
}

$portTypeName = 'RJ45';
$stmtPortTypeName = mysqli_prepare($conn, 'SELECT type FROM switch_port_types WHERE company_id = ? AND id = ? LIMIT 1');
if ($stmtPortTypeName) {
    mysqli_stmt_bind_param($stmtPortTypeName, 'ii', $company_id, $port_type_id);
    mysqli_stmt_execute($stmtPortTypeName);
    $resPortTypeName = mysqli_stmt_get_result($stmtPortTypeName);
    $rowPortTypeName = $resPortTypeName ? mysqli_fetch_assoc($resPortTypeName) : null;
    mysqli_stmt_close($stmtPortTypeName);
    if ($rowPortTypeName && trim((string)($rowPortTypeName['type'] ?? '')) !== '') {
        $portTypeName = (string)$rowPortTypeName['type'];
    }
}
$normalizedPortTypeName = strtolower(preg_replace('/[^a-z0-9]+/', '', $portTypeName));
$isFiberPortType = strpos($normalizedPortTypeName, 'sfp') !== false;

$label = trim((string)($data['label'] ?? ''));
$connected_to = trim((string)($data['connected_to'] ?? ''));
$vlan_id = idf_resolve_vlan_id($conn, $company_id, $data['vlan_id'] ?? ($data['vlan'] ?? ''));
$speedLookupTable = $isFiberPortType ? 'equipment_fiber' : 'rj45_speed';
$speedLookupColumn = $isFiberPortType ? 'name' : 'cable_type';
$rawSpeedInput = $data['speed_id'] ?? ($data['speed'] ?? '');
$speedInputString = trim((string)$rawSpeedInput);
$speed_id = null;
if ($speedInputString !== '' && $speedInputString !== '0') {
    $speed_id = idf_resolve_named_lookup_id(
        $conn,
        $company_id,
        $speedLookupTable,
        $speedLookupColumn,
        $rawSpeedInput
    );
}
$rawPoeInput = $data['poe_id'] ?? ($data['poe'] ?? '');
$poeInputString = trim((string)$rawPoeInput);
$poe_id = null;
if ($poeInputString !== '' && $poeInputString !== '0') {
    $poe_id = idf_resolve_named_lookup_id($conn, $company_id, 'equipment_poe', 'name', $rawPoeInput);
}
$notes = trim((string)($data['notes'] ?? ''));
$cable_color_id = isset($data['cable_color_id']) ? (int)$data['cable_color_id'] : 0;
$cable_color_name = null;
$cable_hex_color = null;
if ($cable_color_id > 0) {
    $stmtColor = mysqli_prepare(
        $conn,
        "SELECT color_name, hex_color
         FROM cable_colors
         WHERE company_id = ?
           AND id = ?
         LIMIT 1"
    );
    if ($stmtColor) {
        mysqli_stmt_bind_param($stmtColor, 'ii', $company_id, $cable_color_id);
        mysqli_stmt_execute($stmtColor);
        $resColor = mysqli_stmt_get_result($stmtColor);
        $colorRow = $resColor ? mysqli_fetch_assoc($resColor) : null;
        mysqli_stmt_close($stmtColor);
        if ($colorRow) {
            $cable_color_name = trim((string)($colorRow['color_name'] ?? ''));
            $cable_hex_color = trim((string)($colorRow['hex_color'] ?? ''));
            if ($cable_color_name === '') {
                $cable_color_name = null;
            }
            if ($cable_hex_color === '') {
                $cable_hex_color = null;
            }
        }
    }
}

$label_val = $label !== '' ? $label : null;
$conn_val = $connected_to !== '' ? $connected_to : null;
$vlan_val = $vlan_id !== null ? (int)$vlan_id : 0;
$speed_val = $speed_id !== null ? (int)$speed_id : 0;
$rj45SpeedVal = $rj45_speed_id !== null ? (int)$rj45_speed_id : 0;
$poe_val = $poe_id !== null ? (int)$poe_id : 0;
$notes_val = $notes !== '' ? $notes : null;

$hasRj45SpeedIdColumn = idf_table_has_column($conn, 'idf_ports', 'rj45_speed_id');

$sql = "UPDATE idf_ports
        SET port_type=?,
            label=?,
            status_id=?,
            connected_to=?,
            vlan_id=NULLIF(?, 0),
            speed_id=NULLIF(?, 0),";
if ($hasRj45SpeedIdColumn) {
    $sql .= "
            rj45_speed_id=NULLIF(?, 0),";
}
$sql .= "
            poe_id=NULLIF(?, 0),
            cable_color=?,
            hex_color=?,
            notes=?
        WHERE id=?
        LIMIT 1";

$stmtUpd = mysqli_prepare($conn, $sql);
if ($stmtUpd) {
    if ($hasRj45SpeedIdColumn) {
        mysqli_stmt_bind_param($stmtUpd, 'isisiiiisssi', $port_type_id, $label_val, $status_id, $conn_val, $vlan_val, $speed_val, $rj45SpeedVal, $poe_val, $cable_color_name, $cable_hex_color, $notes_val, $port_id);
    } else {
        mysqli_stmt_bind_param($stmtUpd, 'isisiiisssi', $port_type_id, $label_val, $status_id, $conn_val, $vlan_val, $speed_val, $poe_val, $cable_color_name, $cable_hex_color, $notes_val, $port_id);
    }
    if (!mysqli_stmt_execute($stmtUpd)) {
        idf_fail('DB error updating port: ' . mysqli_stmt_error($stmtUpd), 500);
    }
    mysqli_stmt_close($stmtUpd);
}

$stmtSwitchSync = mysqli_prepare(
    $conn,
    "UPDATE switch_ports sp
     JOIN idf_ports pr ON pr.id = ?
     JOIN idf_positions p ON p.id = pr.position_id
     LEFT JOIN switch_port_types spt ON spt.id = pr.port_type AND spt.company_id = pr.company_id
     SET sp.{$switchPortLabelColumn} = ?,
         sp.status_id = ?,
         sp.color_id = COALESCE(NULLIF(?, 0), sp.color_id),
         sp.vlan_id = NULLIF(?, 0),
         sp.comments = ?
     WHERE sp.company_id = ?
       AND p.company_id = sp.company_id
       AND p.equipment_id = sp.equipment_id
       AND sp.port_number = pr.port_no
       AND (
            CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(COALESCE(spt.type, 'RJ45') USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(CAST(spt.id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR (
                sp.port_type REGEXP '^[0-9]+$'
                AND CAST(sp.port_type AS UNSIGNED) = spt.id
            )
            OR CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(sp.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, 'RJ45')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
       )
     LIMIT 1"
);
if ($stmtSwitchSync) {
    $switchColorId = $cable_color_id > 0 ? $cable_color_id : 0;
    mysqli_stmt_bind_param(
        $stmtSwitchSync,
        'isiiisi',
        $port_id,
        $label_val,
        $status_id,
        $switchColorId,
        $vlan_val,
        $notes_val,
        $company_id
    );
    if (!mysqli_stmt_execute($stmtSwitchSync)) {
        idf_fail('DB error syncing switch port: ' . mysqli_stmt_error($stmtSwitchSync), 500);
    }
    mysqli_stmt_close($stmtSwitchSync);
}

$stmtLinkUpdate = mysqli_prepare(
    $conn,
    "UPDATE idf_links
     SET cable_color_id = NULLIF(?, 0),
         cable_color_hex = ?
     WHERE port_id_a = ? OR port_id_b = ?"
);
if ($stmtLinkUpdate) {
    mysqli_stmt_bind_param($stmtLinkUpdate, 'isii', $cable_color_id, $cable_hex_color, $port_id, $port_id);
    if (!mysqli_stmt_execute($stmtLinkUpdate)) {
        idf_fail('DB error updating link cable color: ' . mysqli_stmt_error($stmtLinkUpdate), 500);
    }
    mysqli_stmt_close($stmtLinkUpdate);
}

$stmtLinkMetaSync = mysqli_prepare(
    $conn,
    "UPDATE idf_links l
     SET l.equipment_label = ?,
         l.equipment_status_id = ?,
         l.equipment_vlan_id = NULLIF(?, 0),
         l.equipment_comments = ?,
         l.equipment_color_id = NULLIF(?, 0)
     WHERE l.company_id = ?
       AND (l.port_id_a = ? OR l.port_id_b = ?)"
);
if ($stmtLinkMetaSync) {
    $linkColorId = $cable_color_id > 0 ? $cable_color_id : 0;
    mysqli_stmt_bind_param(
        $stmtLinkMetaSync,
        'siisiiii',
        $label_val,
        $status_id,
        $vlan_val,
        $notes_val,
        $linkColorId,
        $company_id,
        $port_id,
        $port_id
    );
    if (!mysqli_stmt_execute($stmtLinkMetaSync)) {
        idf_fail('DB error syncing link metadata: ' . mysqli_stmt_error($stmtLinkMetaSync), 500);
    }
    mysqli_stmt_close($stmtLinkMetaSync);
}

idf_ok();
