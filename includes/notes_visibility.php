<?php
/**
 * Shared visibility helpers for notes.
 */

function itm_notes_normalize_sql_alias($alias)
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

function itm_notes_visibility_sql($alias = '')
{
    $prefix = itm_notes_normalize_sql_alias($alias);
    // Owner or shared with user.
    // Assuming shared_with_json is a JSON array of user IDs.
    return '(' . $prefix . 'user_id = ? OR JSON_CONTAINS(' . $prefix . 'shared_with_json, CAST(? AS JSON), \'$\'))';
}

function itm_notes_append_visibility_filter(&$conditions, &$types, &$params, $loggedUserId, $alias = '')
{
    $conditions[] = itm_notes_visibility_sql($alias);
    $types .= 'ii';
    $userId = (int)$loggedUserId;
    $params[] = $userId;
    $params[] = $userId;
}
