<?php
/**
 * Shared visibility helpers for events (owner or shared_with_json recipients).
 */

function itm_events_normalize_sql_alias($alias)
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

function itm_events_visibility_sql($alias = '')
{
    $prefix = itm_events_normalize_sql_alias($alias);

    return '(' . $prefix . 'employee_id = ? OR JSON_CONTAINS(' . $prefix . 'shared_with_json, CAST(? AS JSON), \'$\'))';
}

/**
 * Loads one event row when the active user is owner or listed in shared_with_json.
 */
function itm_events_fetch_visible_by_id($conn, $eventId, $companyId, $loggedUserId, $requireNotDeleted = true)
{
    $eventId = (int)$eventId;
    $companyId = (int)$companyId;
    $loggedUserId = (int)$loggedUserId;

    if ($eventId <= 0 || $companyId <= 0 || $loggedUserId <= 0 || !($conn instanceof mysqli)) {
        return null;
    }

    $visSql = itm_events_visibility_sql();
    $deletedSql = $requireNotDeleted ? ' AND deleted_at IS NULL' : '';
    $sql = 'SELECT * FROM events WHERE id = ? AND company_id = ?' . $deletedSql . ' AND (' . $visSql . ') LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iiii', $eventId, $companyId, $loggedUserId, $loggedUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}
