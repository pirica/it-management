<?php
require '../../config/config.php';

if (!function_exists('equipment_table_has_column')) {
    function equipment_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = mysqli_real_escape_string($conn, $table);
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

function equipment_delete_idf_data(mysqli $conn, string $companyId, int $equipmentId): void
{
    $companyId = trim($companyId);
    if ($equipmentId <= 0 || $companyId === '') {
        return;
    }

    $equipmentIdValue = "'" . mysqli_real_escape_string($conn, (string)$equipmentId) . "'";
    $hasCompanyColumn = equipment_table_has_column($conn, 'idf_positions', 'company_id');
    $companyFilter = $hasCompanyColumn
        ? " AND company_id = '" . mysqli_real_escape_string($conn, $companyId) . "'"
        : '';
    mysqli_query(
        $conn,
        "DELETE FROM idf_positions WHERE equipment_id = {$equipmentIdValue}{$companyFilter}"
    );
}

function equipment_delete_switch_port_data(mysqli $conn, int $companyId, int $equipmentId): void
{
    if ($companyId <= 0 || $equipmentId <= 0) {
        return;
    }
    if (!equipment_table_has_column($conn, 'switch_ports', 'equipment_id')) {
        return;
    }

    $hasCompanyColumn = equipment_table_has_column($conn, 'switch_ports', 'company_id');
    $sql = 'DELETE FROM switch_ports WHERE equipment_id = ?';
    $types = 'i';
    $params = [$equipmentId];

    if ($hasCompanyColumn) {
        $sql .= ' AND company_id = ?';
        $types .= 'i';
        $params[] = $companyId;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function equipment_is_switch(mysqli $conn, int $companyId, int $equipmentId): bool
{
    if ($companyId <= 0 || $equipmentId <= 0) {
        return false;
    }
    $sql = "SELECT et.name
            FROM equipment e
            LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
            WHERE e.id = ? AND e.company_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId);
    $isSwitch = false;
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $equipmentTypeName = strtolower(trim((string)($row['name'] ?? '')));
        $isSwitch = str_contains($equipmentTypeName, 'switch');
    }
    mysqli_stmt_close($stmt);
    return $isSwitch;
}

$debugRequestUri = $_SERVER['REQUEST_URI'] ?? '';
$debugQueryString = $_SERVER['QUERY_STRING'] ?? '';
$debugPost = $_POST;
$debugGet = $_GET;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid equipment ID.'
        . ' REQUEST_URI=' . $debugRequestUri
        . ' | QUERY_STRING=' . $debugQueryString
        . ' | POST=' . json_encode($debugPost)
        . ' | GET=' . json_encode($debugGet);
    header('Location: index.php');
    exit;
}

$usageError = '';
$isSwitchEquipment = equipment_is_switch($conn, (int)$company_id, $id);

$checkSql = "SELECT id FROM equipment WHERE id = $id AND company_id = $company_id LIMIT 1";
$checkResult = mysqli_query($conn, $checkSql);

if (!$checkResult) {
    $_SESSION['crud_error'] = 'Unable to check record before delete: ' . mysqli_error($conn);
    header('Location: index.php');
    exit;
}

if (mysqli_num_rows($checkResult) !== 1) {
    $_SESSION['crud_error'] = 'Record not found, or it does not belong to this company.';
    header('Location: index.php');
    exit;
}

mysqli_begin_transaction($conn);
try {
    if ($isSwitchEquipment) {
        equipment_delete_switch_port_data($conn, (int)$company_id, $id);
    }

    if (!itm_can_delete_record($conn, 'equipment', 'id', $id, $company_id, $usageError)) {
        throw new RuntimeException($usageError !== '' ? $usageError : 'This record is in use and cannot be deleted.');
    }

    $deleteSql = "DELETE FROM equipment WHERE id = $id AND company_id = $company_id LIMIT 1";
    $deleteResult = mysqli_query($conn, $deleteSql);

    if (!$deleteResult) {
        throw new RuntimeException('Delete failed: ' . mysqli_error($conn));
    }

    if (mysqli_affected_rows($conn) < 1) {
        throw new RuntimeException('Nothing was deleted.');
    }

    equipment_delete_idf_data($conn, (string)$company_id, $id);
    if (!$isSwitchEquipment) {
        equipment_delete_switch_port_data($conn, (int)$company_id, $id);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['crud_error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}

//$_SESSION['crud_success'] = 'Record deleted successfully.';
header('Location: index.php');
exit;
