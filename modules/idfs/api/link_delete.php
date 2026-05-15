<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$link_id = (int)($data['link_id'] ?? 0);
if ($link_id <= 0) {
    idf_fail('Invalid link_id');
}

$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT l.id, l.port_id_a, l.port_id_b, i.company_id
     FROM idf_links l
     JOIN idf_ports a ON a.id=l.port_id_a
     JOIN idf_positions pa ON pa.id=a.position_id
     JOIN idfs i ON i.id=pa.idf_id
     WHERE l.id=?
     LIMIT 1"
);
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $link_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$portA = (int)($row['port_id_a'] ?? 0);
$portB = (int)($row['port_id_b'] ?? 0);
$unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
$grayColorName = 'Gray';
$grayHexColor = '#808080';
$grayColorId = idf_resolve_named_lookup_id($conn, $company_id, 'cable_colors', 'color_name', $grayColorName);
if ($grayColorId === null || (int)$grayColorId <= 0) {
    $stmtGrayByHex = mysqli_prepare(
        $conn,
        "SELECT id
         FROM cable_colors
         WHERE company_id = ?
           AND UPPER(hex_color) = UPPER(?)
         ORDER BY id ASC
         LIMIT 1"
    );
    if ($stmtGrayByHex) {
        mysqli_stmt_bind_param($stmtGrayByHex, 'is', $company_id, $grayHexColor);
        mysqli_stmt_execute($stmtGrayByHex);
        $resGrayByHex = mysqli_stmt_get_result($stmtGrayByHex);
        $grayRow = $resGrayByHex ? mysqli_fetch_assoc($resGrayByHex) : null;
        mysqli_stmt_close($stmtGrayByHex);
        if ($grayRow) {
            $grayColorId = (int)($grayRow['id'] ?? 0);
        }
    }
}
if ($grayColorId === null || (int)$grayColorId <= 0) {
    $stmtInsertGray = mysqli_prepare(
        $conn,
        "INSERT IGNORE INTO cable_colors (company_id, color_name, hex_color)
         VALUES (?, ?, ?)"
    );
    if ($stmtInsertGray) {
        mysqli_stmt_bind_param($stmtInsertGray, 'iss', $company_id, $grayColorName, $grayHexColor);
        mysqli_stmt_execute($stmtInsertGray);
        mysqli_stmt_close($stmtInsertGray);
    }
    $grayColorId = idf_resolve_named_lookup_id($conn, $company_id, 'cable_colors', 'color_name', $grayColorName);
}
$grayColorId = (int)($grayColorId ?? 0);

$stmtDel = mysqli_prepare(
    $conn,
    "DELETE FROM idf_links
     WHERE company_id = ?
       AND (
            (port_id_a = ? AND port_id_b = ?)
            OR (port_id_a = ? AND port_id_b = ?)
       )"
);
if ($stmtDel) {
    mysqli_stmt_bind_param($stmtDel, 'iiiii', $company_id, $portA, $portB, $portB, $portA);
    if (!mysqli_stmt_execute($stmtDel)) {
        idf_fail('DB error deleting link: ' . mysqli_stmt_error($stmtDel), 500);
    }
    mysqli_stmt_close($stmtDel);
}
if ($portA > 0 && $portB > 0) {
    $clearConnected = '';
    $stmtPortClear = mysqli_prepare(
        $conn,
        "UPDATE idf_ports
         SET connected_to = ?,
             status_id = NULLIF(?, 0),
             cable_color = ?,
             hex_color = ?,
             label = NULL,
             notes = NULL
         WHERE id = ?
         LIMIT 1"
    );
    if ($stmtPortClear) {
        mysqli_stmt_bind_param($stmtPortClear, 'sissi', $clearConnected, $unknownStatusId, $grayColorName, $grayHexColor, $portA);
        mysqli_stmt_execute($stmtPortClear);
        mysqli_stmt_bind_param($stmtPortClear, 'sissi', $clearConnected, $unknownStatusId, $grayColorName, $grayHexColor, $portB);
        mysqli_stmt_execute($stmtPortClear);
        mysqli_stmt_close($stmtPortClear);
    }

    $stmtSwitchClear = mysqli_prepare(
        $conn,
        "UPDATE switch_ports sp
         JOIN idf_ports pr ON pr.id = ?
         JOIN idf_positions p
           ON p.company_id = pr.company_id
          AND (
               p.id = pr.position_id
               OR p.position_no = pr.position_id
          )
         LEFT JOIN switch_port_types spt
           ON spt.id = pr.port_type
          AND spt.company_id = pr.company_id
         SET sp.status_id = NULLIF(?, 0),
             sp.color_id = NULLIF(?, 0),
             sp.comments = NULL,
             sp.{$switchPortLabelColumn} = NULL
         WHERE sp.company_id = ?
           AND p.company_id = sp.company_id
           AND CONVERT(CAST(p.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(CAST(sp.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           AND sp.port_number = pr.port_no
           AND (
                CONVERT(CAST(sp.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(CAST(pr.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR (
                    sp.port_type REGEXP '^[0-9]+$'
                    AND CAST(sp.port_type AS UNSIGNED) = pr.port_type
                )
                OR
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
        "
    );
    if ($stmtSwitchClear) {
        mysqli_stmt_bind_param($stmtSwitchClear, 'iiii', $portA, $unknownStatusId, $grayColorId, $company_id);
        if (!mysqli_stmt_execute($stmtSwitchClear)) {
            idf_fail('DB error clearing source switch port link state: ' . mysqli_stmt_error($stmtSwitchClear), 500);
        }
        mysqli_stmt_bind_param($stmtSwitchClear, 'iiii', $portB, $unknownStatusId, $grayColorId, $company_id);
        if (!mysqli_stmt_execute($stmtSwitchClear)) {
            idf_fail('DB error clearing destination switch port link state: ' . mysqli_stmt_error($stmtSwitchClear), 500);
        }
        mysqli_stmt_close($stmtSwitchClear);
    }
}

idf_ok();
