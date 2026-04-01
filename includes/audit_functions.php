<?php
/**
 * itm_log_audit
 * 
 * Centralized function to log data changes (INSERT, UPDATE, DELETE)
 */
function itm_log_audit($conn, $table, $record_id, $action, $old_values = null, $new_values = null) {
    if (!isset($_SESSION['company_id'])) {
        return false;
    }

    $company_id = (int)$_SESSION['company_id'];
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $action = strtoupper($action);

    $old_json = $old_values ? json_encode($old_values) : null;
    $new_json = $new_values ? json_encode($new_values) : null;

    $sql = "INSERT INTO audit_logs (company_id, user_id, table_name, record_id, action, old_values, new_values) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iisisss', $company_id, $user_id, $table, $record_id, $action, $old_json, $new_json);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}
