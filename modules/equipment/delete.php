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

    $companyIdInt = (int)$companyId;
    if ($companyIdInt <= 0) {
        return;
    }

    $positionIds = [];
    $equipmentIdString = (string)$equipmentId;
    $stmtPositions = mysqli_prepare(
        $conn,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ? AND equipment_id = ?"
    );
    if ($stmtPositions) {
        mysqli_stmt_bind_param($stmtPositions, 'is', $companyIdInt, $equipmentIdString);
        mysqli_stmt_execute($stmtPositions);
        $resPositions = mysqli_stmt_get_result($stmtPositions);
        while ($resPositions && ($row = mysqli_fetch_assoc($resPositions))) {
            $positionId = (int)($row['id'] ?? 0);
            if ($positionId > 0) {
                $positionIds[$positionId] = $positionId;
            }
        }
        mysqli_stmt_close($stmtPositions);
    }

    if ($positionIds) {
        $positionIdList = implode(',', array_values($positionIds));
        mysqli_query(
            $conn,
            "DELETE FROM idf_links
             WHERE company_id = " . $companyIdInt . "
               AND (
                    port_id_a IN (
                        SELECT id FROM idf_ports
                        WHERE company_id = " . $companyIdInt . " AND position_id IN ({$positionIdList})
                    )
                    OR
                    port_id_b IN (
                        SELECT id FROM idf_ports
                        WHERE company_id = " . $companyIdInt . " AND position_id IN ({$positionIdList})
                    )
               )"
        );
        mysqli_query(
            $conn,
            "DELETE FROM idf_ports
             WHERE company_id = " . $companyIdInt . "
               AND position_id IN ({$positionIdList})"
        );
    }

    $equipmentIdValue = "'" . mysqli_real_escape_string($conn, (string)$equipmentId) . "'";
    mysqli_query(
        $conn,
        "DELETE FROM idf_positions
         WHERE company_id = " . $companyIdInt . "
           AND equipment_id = {$equipmentIdValue}"
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

/**
 * Delete one equipment row with switch-port and IDF cleanup (company-scoped).
 *
 * @return string|null Error message, or null when deleted successfully.
 */
function equipment_delete_record(mysqli $conn, int $companyId, int $id): ?string
{
    if ($companyId <= 0 || $id <= 0) {
        return 'Invalid equipment ID.';
    }

    $checkStmt = mysqli_prepare($conn, 'SELECT id FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$checkStmt) {
        return 'Unable to check record before delete: ' . mysqli_error($conn);
    }
    mysqli_stmt_bind_param($checkStmt, 'ii', $id, $companyId);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $exists = $checkResult && mysqli_num_rows($checkResult) === 1;
    mysqli_stmt_close($checkStmt);

    if (!$exists) {
        return 'Record not found, or it does not belong to this company.';
    }

    $usageError = '';
    $isSwitchEquipment = equipment_is_switch($conn, $companyId, $id);

    mysqli_begin_transaction($conn);
    try {
        if ($isSwitchEquipment) {
            equipment_delete_switch_port_data($conn, $companyId, $id);
        }

        if (!itm_can_delete_record($conn, 'equipment', 'id', $id, $companyId, $usageError)) {
            throw new RuntimeException($usageError !== '' ? $usageError : 'This record is in use and cannot be deleted.');
        }

        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$deleteStmt) {
            throw new RuntimeException('Delete failed: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($deleteStmt, 'ii', $id, $companyId);
        mysqli_stmt_execute($deleteStmt);
        if (mysqli_affected_rows($conn) < 1) {
            mysqli_stmt_close($deleteStmt);
            throw new RuntimeException('Nothing was deleted.');
        }
        mysqli_stmt_close($deleteStmt);

        equipment_delete_idf_data($conn, (string)$companyId, $id);
        if (!$isSwitchEquipment) {
            equipment_delete_switch_port_data($conn, $companyId, $id);
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        return $e->getMessage();
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$companyId = (int)$company_id;
$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $idList = [];
    $listResult = mysqli_query($conn, 'SELECT id FROM equipment WHERE company_id = ' . $companyId);
    while ($listResult && ($listRow = mysqli_fetch_assoc($listResult))) {
        $rowId = (int)($listRow['id'] ?? 0);
        if ($rowId > 0) {
            $idList[$rowId] = $rowId;
        }
    }

    $deleteErrors = [];
    foreach ($idList as $equipmentId) {
        $deleteError = equipment_delete_record($conn, $companyId, $equipmentId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $equipmentId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        $_SESSION['crud_error'] = implode(' ', $deleteErrors);
    }

    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $idList = [];
    foreach ($ids as $rawId) {
        $equipmentId = (int)$rawId;
        if ($equipmentId > 0) {
            $idList[$equipmentId] = $equipmentId;
        }
    }

    if ($idList === []) {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
        header('Location: index.php');
        exit;
    }

    $deleteErrors = [];
    foreach ($idList as $equipmentId) {
        $deleteError = equipment_delete_record($conn, $companyId, $equipmentId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $equipmentId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        $_SESSION['crud_error'] = implode(' ', $deleteErrors);
    }

    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid equipment ID.';
    header('Location: index.php');
    exit;
}

$deleteError = equipment_delete_record($conn, $companyId, $id);
if ($deleteError !== null) {
    $_SESSION['crud_error'] = $deleteError;
}

header('Location: index.php');
exit;
