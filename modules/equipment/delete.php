<?php
/**
 * Equipment Module - Delete
 * 
 * Handles the secure deletion of an equipment record.
 * Implements:
 * - Transaction-based deletion to ensure atomicity
 * - Automatic cleanup of dependent data (switch ports, IDF positions)
 * - Usage checks to prevent deleting equipment that is referenced elsewhere
 * - Robust error handling and session-based error reporting
 */

require '../../config/config.php';

// Helper for schema validation
if (!function_exists('equipment_table_has_column')) {
    function equipment_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) { return false; }
        $escapedColumn = mysqli_real_escape_string($conn, $column);
        $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$escapedColumn}'";
        $res = mysqli_query($conn, $sql);
        return $res && mysqli_num_rows($res) > 0;
    }
}

/**
 * Removes IDF position data linked to the equipment
 */
function equipment_delete_idf_data(mysqli $conn, string $companyId, int $equipmentId): void
{
    $companyId = trim($companyId);
    if ($equipmentId <= 0 || $companyId === '') { return; }
    $hasCompanyColumn = equipment_table_has_column($conn, 'idf_positions', 'company_id');
    $sql = 'DELETE FROM idf_positions WHERE equipment_id = ?';
    $types = 'i'; $params = [(int)$equipmentId];
    if ($hasCompanyColumn) { $sql .= ' AND company_id = ?'; $types .= 's'; $params[] = $companyId; }
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) { mysqli_stmt_bind_param($stmt, $types, ...$params); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
}

/**
 * Removes switch port configuration for the equipment
 */
function equipment_delete_switch_port_data(mysqli $conn, int $companyId, int $equipmentId): void
{
    if ($companyId <= 0 || $equipmentId <= 0) { return; }
    if (!equipment_table_has_column($conn, 'switch_ports', 'equipment_id')) { return; }
    $hasCompanyColumn = equipment_table_has_column($conn, 'switch_ports', 'company_id');
    $sql = 'DELETE FROM switch_ports WHERE equipment_id = ?';
    $types = 'i'; $params = [$equipmentId];
    if ($hasCompanyColumn) { $sql .= ' AND company_id = ?'; $types .= 'i'; $params[] = $companyId; }
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) { mysqli_stmt_bind_param($stmt, $types, ...$params); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
}

/**
 * Checks if the equipment record is classified as a switch
 */
function equipment_is_switch(mysqli $conn, int $companyId, int $equipmentId): bool
{
    if ($companyId <= 0 || $equipmentId <= 0) { return false; }
    $sql = "SELECT et.name FROM equipment e LEFT JOIN equipment_types et ON et.id = e.equipment_type_id WHERE e.id = ? AND e.company_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return false; }
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

// Security: Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// CSRF Protection
itm_require_post_csrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid equipment ID.';
    header('Location: index.php'); exit;
}

// PRE-DELETE CHECKS
$usageError = '';
$isSwitchEquipment = equipment_is_switch($conn, (int)$company_id, $id);

$checkSql = "SELECT id FROM equipment WHERE id = ? AND company_id = ? LIMIT 1";
$checkStmt = mysqli_prepare($conn, $checkSql);
$checkResult = false;
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    mysqli_stmt_close($checkStmt);
}

if (!$checkResult || mysqli_num_rows($checkResult) !== 1) {
    $_SESSION['crud_error'] = 'Record not found, or it does not belong to this company.';
    header('Location: index.php'); exit;
}

// EXECUTE DELETION WITHIN TRANSACTION
mysqli_begin_transaction($conn);
try {
    // 1. Cleanup specific data based on type
    if ($isSwitchEquipment) { equipment_delete_switch_port_data($conn, (int)$company_id, $id); }

    // 2. Generic referential integrity check
    if (!itm_can_delete_record($conn, 'equipment', 'id', $id, $company_id, $usageError)) {
        throw new RuntimeException($usageError !== '' ? $usageError : 'This record is in use and cannot be deleted.');
    }

    // 3. Primary record deletion
    $deleteSql = "DELETE FROM equipment WHERE id = ? AND company_id = ? LIMIT 1";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    $deleteResult = false;
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'ii', $id, $company_id);
        $deleteResult = mysqli_stmt_execute($deleteStmt);
        $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);
    }

    if (!$deleteResult || $affectedRows < 1) { throw new RuntimeException('Delete operation failed.'); }

    // 4. Final secondary cleanup
    equipment_delete_idf_data($conn, (string)$company_id, $id);
    if (!$isSwitchEquipment) { equipment_delete_switch_port_data($conn, (int)$company_id, $id); }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['crud_error'] = $e->getMessage();
    header('Location: index.php'); exit;
}

header('Location: index.php');
exit;
