<?php
/**
 * Keeps equipment.assigned_to_employee_id aligned with employee_assignment_history.
 */

if (!function_exists('equipment_build_assignment_asset_description')) {
    function equipment_build_assignment_asset_description(string $name, string $model): string
    {
        $name = trim($name);
        $model = trim($model);
        if ($name !== '' && $model !== '') {
            return $name . ' / ' . $model;
        }
        if ($name !== '') {
            return $name;
        }
        if ($model !== '') {
            return $model;
        }

        return 'Equipment';
    }
}

if (!function_exists('equipment_close_open_history_for_equipment')) {
    function equipment_close_open_history_for_equipment(mysqli $conn, int $companyId, int $equipmentId): bool
    {
        if ($companyId <= 0 || $equipmentId <= 0) {
            return true;
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employee_assignment_history
             SET returned_date = CURDATE(), updated_at = CURRENT_TIMESTAMP
             WHERE company_id = ? AND equipment_id = ? AND returned_date IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('equipment_clear_assignee_on_equipment')) {
    function equipment_clear_assignee_on_equipment(mysqli $conn, int $companyId, int $equipmentId): bool
    {
        if ($companyId <= 0 || $equipmentId <= 0) {
            return true;
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE equipment SET assigned_to_employee_id = NULL WHERE id = ? AND company_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('equipment_close_assignment_history_on_delete')) {
    function equipment_close_assignment_history_on_delete(mysqli $conn, int $companyId, int $equipmentId): ?string
    {
        if ($companyId <= 0 || $equipmentId <= 0) {
            return null;
        }

        if (!equipment_close_open_history_for_equipment($conn, $companyId, $equipmentId)) {
            return 'Unable to close assignment history before delete: ' . mysqli_error($conn);
        }

        return null;
    }
}

if (!function_exists('equipment_sync_assigned_employee')) {
    /**
     * @return string|null Error message, or null on success.
     */
    function equipment_sync_assigned_employee(
        mysqli $conn,
        int $companyId,
        int $equipmentId,
        ?int $newEmployeeId,
        ?int $oldEmployeeId,
        ?int $assignedByUserId,
        string $assetDescription
    ): ?string {
        if ($companyId <= 0 || $equipmentId <= 0) {
            return 'Invalid equipment scope for assignment sync.';
        }

        $newEmployeeId = ($newEmployeeId !== null && $newEmployeeId > 0) ? $newEmployeeId : null;
        $oldEmployeeId = ($oldEmployeeId !== null && $oldEmployeeId > 0) ? $oldEmployeeId : null;

        if ($newEmployeeId === $oldEmployeeId) {
            return null;
        }

        if (!equipment_close_open_history_for_equipment($conn, $companyId, $equipmentId)) {
            return 'Unable to close prior assignment history: ' . mysqli_error($conn);
        }

        if ($newEmployeeId === null) {
            if (!equipment_clear_assignee_on_equipment($conn, $companyId, $equipmentId)) {
                return 'Unable to clear equipment assignee: ' . mysqli_error($conn);
            }

            return null;
        }

        $otherEquipmentStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment
             WHERE company_id = ? AND assigned_to_employee_id = ? AND id <> ?'
        );
        if (!$otherEquipmentStmt) {
            return 'Unable to load conflicting equipment assignments: ' . mysqli_error($conn);
        }
        mysqli_stmt_bind_param($otherEquipmentStmt, 'iii', $companyId, $newEmployeeId, $equipmentId);
        mysqli_stmt_execute($otherEquipmentStmt);
        $otherEquipmentRes = mysqli_stmt_get_result($otherEquipmentStmt);
        $otherEquipmentIds = [];
        while ($otherEquipmentRes && ($otherRow = mysqli_fetch_assoc($otherEquipmentRes))) {
            $otherId = (int)($otherRow['id'] ?? 0);
            if ($otherId > 0) {
                $otherEquipmentIds[] = $otherId;
            }
        }
        mysqli_stmt_close($otherEquipmentStmt);

        foreach ($otherEquipmentIds as $otherEquipmentId) {
            if (!equipment_close_open_history_for_equipment($conn, $companyId, $otherEquipmentId)) {
                return 'Unable to close assignment history on replaced equipment: ' . mysqli_error($conn);
            }
            if (!equipment_clear_assignee_on_equipment($conn, $companyId, $otherEquipmentId)) {
                return 'Unable to clear replaced equipment assignee: ' . mysqli_error($conn);
            }
        }

        $priorEquipmentId = 0;
        $priorHistoryStmt = mysqli_prepare(
            $conn,
            'SELECT equipment_id FROM employee_assignment_history
             WHERE company_id = ? AND employee_id = ? LIMIT 1'
        );
        if ($priorHistoryStmt) {
            mysqli_stmt_bind_param($priorHistoryStmt, 'ii', $companyId, $newEmployeeId);
            mysqli_stmt_execute($priorHistoryStmt);
            $priorHistoryRes = mysqli_stmt_get_result($priorHistoryStmt);
            $priorHistoryRow = $priorHistoryRes ? mysqli_fetch_assoc($priorHistoryRes) : null;
            mysqli_stmt_close($priorHistoryStmt);
            $priorEquipmentId = (int)($priorHistoryRow['equipment_id'] ?? 0);
        }

        if ($priorEquipmentId > 0 && $priorEquipmentId !== $equipmentId) {
            if (!equipment_close_open_history_for_equipment($conn, $companyId, $priorEquipmentId)) {
                return 'Unable to close prior employee assignment history: ' . mysqli_error($conn);
            }
            if (!equipment_clear_assignee_on_equipment($conn, $companyId, $priorEquipmentId)) {
                return 'Unable to clear prior employee equipment assignee: ' . mysqli_error($conn);
            }
        }

        $assignedByBind = ($assignedByUserId !== null && $assignedByUserId > 0) ? $assignedByUserId : null;
        $assetDescriptionTrimmed = substr(trim($assetDescription), 0, 255);
        $upsertSql = 'INSERT INTO employee_assignment_history
            (company_id, employee_id, equipment_id, assigned_date, returned_date, assigned_by_user_id, asset_description, active)
            VALUES (?, ?, ?, CURDATE(), NULL, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                equipment_id = VALUES(equipment_id),
                assigned_date = VALUES(assigned_date),
                returned_date = NULL,
                assigned_by_user_id = VALUES(assigned_by_user_id),
                asset_description = VALUES(asset_description),
                updated_at = CURRENT_TIMESTAMP';
        $upsertStmt = mysqli_prepare($conn, $upsertSql);
        if (!$upsertStmt) {
            return 'Unable to upsert assignment history: ' . mysqli_error($conn);
        }
        mysqli_stmt_bind_param(
            $upsertStmt,
            'iiiis',
            $companyId,
            $newEmployeeId,
            $equipmentId,
            $assignedByBind,
            $assetDescriptionTrimmed
        );
        if (!mysqli_stmt_execute($upsertStmt)) {
            $upsertError = mysqli_error($conn);
            mysqli_stmt_close($upsertStmt);

            return itm_format_db_constraint_error((int)mysqli_errno($conn), $upsertError);
        }
        mysqli_stmt_close($upsertStmt);

        $assignStmt = mysqli_prepare(
            $conn,
            'UPDATE equipment SET assigned_to_employee_id = ? WHERE id = ? AND company_id = ? LIMIT 1'
        );
        if (!$assignStmt) {
            return 'Unable to set equipment assignee: ' . mysqli_error($conn);
        }
        mysqli_stmt_bind_param($assignStmt, 'iii', $newEmployeeId, $equipmentId, $companyId);
        if (!mysqli_stmt_execute($assignStmt)) {
            $assignError = mysqli_error($conn);
            mysqli_stmt_close($assignStmt);

            return itm_format_db_constraint_error((int)mysqli_errno($conn), $assignError);
        }
        mysqli_stmt_close($assignStmt);

        return null;
    }
}
