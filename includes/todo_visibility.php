<?php
/**
 * Shared visibility helpers for global and private todo.
 */

function itm_todo_normalize_sql_alias($alias)
{
    $alias = trim((string)$alias);
    if ($alias === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', rtrim($alias, '.'))) {
        return '';
    }

    return rtrim($alias, '.') . '.';
}

function itm_todo_visibility_sql($alias = '')
{
    $prefix = itm_todo_normalize_sql_alias($alias);
    return '(' . $prefix . 'assigned_to_user_id IS NULL OR '
        . $prefix . 'assigned_to_user_id = ? OR '
        . $prefix . 'created_by_user_id = ?)';
}

function itm_todo_visibility_sql_literal($loggedUserId, $alias = '')
{
    $userId = (int)$loggedUserId;
    $prefix = itm_todo_normalize_sql_alias($alias);
    return '(' . $prefix . 'assigned_to_user_id IS NULL OR '
        . $prefix . 'assigned_to_user_id = ' . $userId . ' OR '
        . $prefix . 'created_by_user_id = ' . $userId . ')';
}

function itm_todo_append_visibility_filter(&$conditions, &$types, &$params, $loggedUserId, $alias = '')
{
    $conditions[] = itm_todo_visibility_sql($alias);
    $types .= 'ii';
    $userId = (int)$loggedUserId;
    $params[] = $userId;
    $params[] = $userId;
}
