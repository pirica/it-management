<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$statusRaw = trim((string)($data['status'] ?? ''));
if ($statusRaw === '') {
    idf_fail('Status is required');
}

$status = substr($statusRaw, 0, 50);

$stmtFind = mysqli_prepare(
    $conn,
    'SELECT status
     FROM switch_status
     WHERE company_id = ?
       AND LOWER(status) = LOWER(?)
     LIMIT 1'
);
if (!$stmtFind) {
    idf_fail('Unable to prepare status lookup', 500);
}

mysqli_stmt_bind_param($stmtFind, 'is', $company_id, $status);
mysqli_stmt_execute($stmtFind);
$resFind = mysqli_stmt_get_result($stmtFind);
$existing = $resFind ? mysqli_fetch_assoc($resFind) : null;
mysqli_stmt_close($stmtFind);

if ($existing && isset($existing['status'])) {
    $stmtExistingId = mysqli_prepare(
        $conn,
        'SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) LIMIT 1'
    );
    $existingId = 0;
    if ($stmtExistingId) {
        mysqli_stmt_bind_param($stmtExistingId, 'is', $company_id, $status);
        mysqli_stmt_execute($stmtExistingId);
        $resExistingId = mysqli_stmt_get_result($stmtExistingId);
        $rowExistingId = $resExistingId ? mysqli_fetch_assoc($resExistingId) : null;
        mysqli_stmt_close($stmtExistingId);
        $existingId = isset($rowExistingId['id']) ? (int)$rowExistingId['id'] : 0;
    }
    idf_ok(['status_id' => $existingId, 'status' => (string)$existing['status']]);
}

$stmtInsert = mysqli_prepare(
    $conn,
    'INSERT INTO switch_status (company_id, status) VALUES (?, ?)'
);
if (!$stmtInsert) {
    idf_fail('Unable to prepare status insert', 500);
}

mysqli_stmt_bind_param($stmtInsert, 'is', $company_id, $status);
try {
    if (!mysqli_stmt_execute($stmtInsert)) {
        $insertError = mysqli_stmt_error($stmtInsert);
        mysqli_stmt_close($stmtInsert);
        idf_fail('Unable to save status: ' . $insertError, 500);
    }
} catch (Throwable $exception) {
    mysqli_stmt_close($stmtInsert);
    idf_fail('Unable to save status: ' . $exception->getMessage(), 500);
}
mysqli_stmt_close($stmtInsert);

$newStatusId = (int)mysqli_insert_id($conn);
idf_ok(['status_id' => $newStatusId, 'status' => $status]);
