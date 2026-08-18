<?php
/**
 * Company-scoped IT settings helpers (it_settings table).
 */

if (!function_exists('itm_it_settings_chat_same_tenant_enabled')) {
    /**
     * When true (default), Live Chat "Chat with" lists only employees homed in the active company_id.
     */
    function itm_it_settings_chat_same_tenant_enabled($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if (!$conn instanceof mysqli || $companyId <= 0) {
            return true;
        }
        $sql = 'SELECT chat_same_tenant FROM it_settings WHERE company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return true;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row)) {
            return true;
        }
        return (int)($row['chat_same_tenant'] ?? 1) === 1;
    }
}

if (!function_exists('itm_it_settings_save_chat_same_tenant')) {
    function itm_it_settings_save_chat_same_tenant($conn, $companyId, $enabled, $employeeId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $flag = ((int)$enabled === 1) ? 1 : 0;
        if (!$conn instanceof mysqli || $companyId <= 0) {
            return false;
        }

        $sql = 'UPDATE it_settings SET chat_same_tenant = ?, updated_by = ? WHERE company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $flag, $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected > 0) {
            return true;
        }

        $sqlIns = 'INSERT INTO it_settings (company_id, chat_same_tenant, active, created_by) VALUES (?, ?, 1, ?)';
        $stmtIns = mysqli_prepare($conn, $sqlIns);
        if (!$stmtIns) {
            return false;
        }
        mysqli_stmt_bind_param($stmtIns, 'iii', $companyId, $flag, $employeeId);
        $ok = mysqli_stmt_execute($stmtIns);
        mysqli_stmt_close($stmtIns);
        return $ok;
    }
}
