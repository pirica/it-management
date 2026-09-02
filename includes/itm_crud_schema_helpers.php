<?php
/**
 * Per-request cached schema introspection for CRUD scaffold helpers.
 *
 * Why: Flattened module entry files previously ran DESCRIBE and information_schema
 * FK lookups on every helper call; central caching cuts duplicate queries per table.
 */

if (!function_exists('itm_crud_table_columns')) {
    /**
     * Full DESCRIBE row set for a table (Field, Type, Null, Key, …).
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_crud_table_columns($conn, $table): array
    {
        static $cache = [];

        $table = (string)$table;
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($table)) {
            return [];
        }

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $cols = [];
        $res = mysqli_query($conn, 'DESCRIBE `' . str_replace('`', '``', $table) . '`');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $cols[] = $row;
        }

        $cache[$table] = $cols;

        return $cache[$table];
    }
}

if (!function_exists('itm_crud_fk_map')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function itm_crud_fk_map($conn, $table): array
    {
        static $cache = [];

        $table = (string)$table;
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($table)) {
            return [];
        }

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $tableEsc = mysqli_real_escape_string($conn, $table);
        $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '{$tableEsc}'
                  AND REFERENCED_TABLE_NAME IS NOT NULL";
        $map = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map[(string)$row['COLUMN_NAME']] = $row;
        }

        $cache[$table] = $map;

        return $cache[$table];
    }
}

if (!function_exists('itm_crud_table_column_meta')) {
    /**
     * Single DESCRIBE row for a table column (cached via itm_crud_table_columns).
     *
     * @return array<string, mixed>|null
     */
    function itm_crud_table_column_meta($conn, $table, $column): ?array
    {
        $column = (string)$column;
        if ($column === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($column)) {
            return null;
        }

        foreach (itm_crud_table_columns($conn, $table) as $row) {
            if (strcasecmp((string)($row['Field'] ?? ''), $column) === 0) {
                return $row;
            }
        }

        return null;
    }
}
