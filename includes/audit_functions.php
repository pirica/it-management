<?php
/**
 * Audit Logging Functions
 * 
 * Provides a centralized mechanism for logging database changes (INSERT, UPDATE, DELETE).
 * Captures old and new values in JSON format, along with user context, IP, and user agent.
 */

/**
 * Encodes an array or object into a JSON string for audit storage
 * 
 * @param mixed $values The data to encode
 * @return string|null The JSON string or null on failure
 */
function itm_audit_encode_values($values) {
    if ($values === null) {
        return null;
    }

    if (!is_array($values) && !is_object($values)) {
        $values = ['value' => $values];
    }

    $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? null : $json;
}

/**
 * Logs a single audit event to the audit_logs table
 * 
 * @param mysqli $conn Database connection
 * @param string $table The table being modified
 * @param int $record_id The ID of the record being modified
 * @param string $action The action performed (INSERT, UPDATE, or DELETE)
 * @param mixed $old_values The record state before the change
 * @param mixed $new_values The record state after the change
 * @return bool True on success, false on failure
 */
function itm_log_audit($conn, $table, $record_id, $action, $old_values = null, $new_values = null) {
    // Only log if a company context exists in the session
    if (!isset($_SESSION['company_id'])) {
        return false;
    }

    $allowedActions = ['INSERT', 'UPDATE', 'DELETE'];
    $action = strtoupper(trim((string)$action));
    if (!in_array($action, $allowedActions, true)) {
        return false;
    }

    $table = trim((string)$table);
    if ($table === '') {
        return false;
    }

    $company_id = (int)$_SESSION['company_id'];
    // Check if audit logging is enabled in the UI configuration
    $uiConfig = itm_get_ui_configuration($conn, $company_id);
    if ((int)($uiConfig['enable_audit_logs'] ?? 1) !== 1) {
        return false;
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $old_json = itm_audit_encode_values($old_values);
    $new_json = itm_audit_encode_values($new_values);
    $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $sql = 'INSERT INTO audit_logs (company_id, user_id, table_name, record_id, action, old_values, new_values, ip_address, user_agent) '
         . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iisisssss',
        $company_id,
        $user_id,
        $table,
        $record_id,
        $action,
        $old_json,
        $new_json,
        $ipAddress,
        $userAgent
    );

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Fetches a record for auditing purposes, scoped to a company if necessary
 */
function itm_fetch_audit_record($conn, $table, $record_id, $company_id = null) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return null;
    }

    $company_id = $company_id === null ? (int)($_SESSION['company_id'] ?? 0) : (int)$company_id;
    $hasCompanyColumn = itm_audit_table_has_column($conn, $table, 'company_id');

    $sql = 'SELECT * FROM `' . $table . '` WHERE id = ?';
    if ($hasCompanyColumn && $company_id > 0) {
        $sql .= ' AND company_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    $record_id = (int)$record_id;
    if ($hasCompanyColumn && $company_id > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $record_id, $company_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $record_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Checks if a specific table has a given column (used for dynamic audit logic)
 */
function itm_audit_table_has_column($conn, $table, $column) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$column)) {
        return false;
    }

    // INFORMATION_SCHEMA is used for compatibility across MySQL/MariaDB versions
    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

/**
 * Fetches a record by its primary key ID from any table
 */
function itm_fetch_audit_record_by_id($conn, $table, $record_id) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return null;
    }

    $sql = 'SELECT * FROM `' . $table . '` WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    $record_id = (int)$record_id;
    mysqli_stmt_bind_param($stmt, 'i', $record_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Parses an SQL query to extract the action, table, and record ID for auditing
 * 
 * Used for "intercepted" SQL queries that aren't using the standard CRUD helpers.
 */
function itm_parse_audit_sql($sql) {
    $sql = trim((string)$sql);
    if ($sql === '') {
        return null;
    }

    $meta = null;
    if (preg_match('/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $meta = ['action' => 'INSERT', 'table' => $m[1], 'record_id' => 0];
    } elseif (preg_match('/^UPDATE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $meta = ['action' => 'UPDATE', 'table' => $m[1], 'record_id' => 0];
    } elseif (preg_match('/^DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $meta = ['action' => 'DELETE', 'table' => $m[1], 'record_id' => 0];
    } else {
        return null;
    }

    // Avoid recursive auditing
    if ($meta['table'] === 'audit_logs') {
        return null;
    }

    // Try to extract the ID from a simple WHERE clause
    if (preg_match('/\bWHERE\b[\s\S]*?\bid\s*=\s*(\d+)/i', $sql, $idMatch)) {
        $meta['record_id'] = (int)$idMatch[1];
    }

    return $meta;
}
