<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$colorRaw = trim((string)($data['color'] ?? ''));
if ($colorRaw === '') {
    idf_fail('Cable color is required');
}

$color = substr($colorRaw, 0, 100);

$stmtFind = mysqli_prepare(
    $conn,
    'SELECT color_name
     FROM cable_colors
     WHERE company_id = ?
       AND LOWER(color_name) = LOWER(?)
     LIMIT 1'
);
if (!$stmtFind) {
    idf_fail('Unable to prepare cable color lookup', 500);
}

mysqli_stmt_bind_param($stmtFind, 'is', $company_id, $color);
mysqli_stmt_execute($stmtFind);
$resFind = mysqli_stmt_get_result($stmtFind);
$existing = $resFind ? mysqli_fetch_assoc($resFind) : null;
mysqli_stmt_close($stmtFind);

if ($existing && isset($existing['color_name'])) {
    idf_ok(['color' => (string)$existing['color_name']]);
}

$stmtInsert = mysqli_prepare(
    $conn,
    'INSERT INTO cable_colors (company_id, color_name) VALUES (?, ?)'
);
if (!$stmtInsert) {
    idf_fail('Unable to prepare cable color insert', 500);
}

mysqli_stmt_bind_param($stmtInsert, 'is', $company_id, $color);
if (!mysqli_stmt_execute($stmtInsert)) {
    $insertError = mysqli_stmt_error($stmtInsert);
    mysqli_stmt_close($stmtInsert);
    idf_fail('Unable to save cable color: ' . $insertError, 500);
}
mysqli_stmt_close($stmtInsert);

idf_ok(['color' => $color]);
