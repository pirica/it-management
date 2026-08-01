<?php
/**
 * DB-only is_hidden flag on employees — protects accounts from Employees UI mutations.
 */

function itm_employees_hidden_account_column_names()
{
    return ['is_hidden'];
}

function itm_employees_is_hidden_account($rowOrFlag)
{
    if (is_array($rowOrFlag)) {
        return (int)($rowOrFlag['is_hidden'] ?? 0) === 1;
    }

    return (int)$rowOrFlag === 1;
}

/**
 * Why: Live installs may predate db/; index load ensures the protection column exists.
 */
function itm_employees_ensure_is_hidden_column($conn)
{
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $res = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'is_hidden'");
    if (!$res) {
        return false;
    }
    if (mysqli_num_rows($res) === 0) {
        if (mysqli_query($conn, "ALTER TABLE employees ADD COLUMN `is_hidden` TINYINT(1) NOT NULL DEFAULT 0 AFTER `hide_year`") !== true) {
            return false;
        }
    }

    return true;
}

/**
 * SQL fragment appended to tenant-scoped Employees list/mutation guards.
 */
function itm_employees_sql_visible_only_predicate($tableAlias = 'e')
{
    $alias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$tableAlias) ? (string)$tableAlias : 'e';

    return ' AND ' . $alias . '.is_hidden = 0';
}
