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

        // Why: Settings saves even when only other UI fields change; MySQL reports 0 affected rows when the flag is unchanged.
        $sql = 'UPDATE it_settings SET chat_same_tenant = ?, updated_by = ?, active = 1, deleted_at = NULL, deleted_by = NULL WHERE company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $flag, $employeeId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        $errno = mysqli_stmt_errno($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok || $errno !== 0) {
            return false;
        }
        if ($affected > 0) {
            return true;
        }

        $sqlChk = 'SELECT chat_same_tenant FROM it_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmtChk = mysqli_prepare($conn, $sqlChk);
        if (!$stmtChk) {
            return false;
        }
        mysqli_stmt_bind_param($stmtChk, 'i', $companyId);
        mysqli_stmt_execute($stmtChk);
        $resChk = mysqli_stmt_get_result($stmtChk);
        $rowChk = $resChk ? mysqli_fetch_assoc($resChk) : null;
        mysqli_stmt_close($stmtChk);
        if (is_array($rowChk) && (int)($rowChk['chat_same_tenant'] ?? -1) === $flag) {
            return true;
        }

        $sqlIns = 'INSERT INTO it_settings (company_id, chat_same_tenant, active, created_by) VALUES (?, ?, 1, ?)';
        $stmtIns = mysqli_prepare($conn, $sqlIns);
        if (!$stmtIns) {
            return false;
        }
        mysqli_stmt_bind_param($stmtIns, 'iii', $companyId, $flag, $employeeId);
        $okIns = mysqli_stmt_execute($stmtIns);
        $errnoIns = mysqli_stmt_errno($stmtIns);
        mysqli_stmt_close($stmtIns);
        return $okIns && $errnoIns === 0;
    }
}
