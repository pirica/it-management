<?php
/**
 * Shared bootstrap helpers.
 *
 * Why: Keep foundational helpers in one file so identifier and schema
 * checks do not drift across bootstrap entry points.
 */

/**
 * Validates that a string can be safely used as a SQL identifier.
 */
if (!function_exists('itm_is_safe_identifier')) {
    function itm_is_safe_identifier($name) {
        return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
    }
}

/**
 * Checks whether a database table contains a given column.
 */
if (!function_exists('itm_table_has_column')) {
    function itm_table_has_column($conn, $table, $column) {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
            $cache[$key] = false;
            return false;
        }

        $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $cache[$key] = false;
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);

/**
 * Resolves a foreign key ID to a human-readable label.
 */
if (!function_exists('cr_resolve_fk_label')) {
    function cr_resolve_fk_label($conn, $table, $field, $value, $company_id) {
        if (empty($value) || (int)$value <= 0) return $value;

        // Simple heuristic: assume FK table is the field name without '_id'
        $fkTable = preg_replace('/_id$/', '', $field);
        
        // Check if table exists
        if (!itm_is_safe_identifier($fkTable)) return $value;

        $sql = "SELECT name FROM " . cr_escape_identifier($fkTable) . " WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return $value;

        mysqli_stmt_bind_param($stmt, 'ii', $value, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        return $row ? $row['name'] : $value;
    }
}

        return $cache[$key];
    }
}
