<?php
/**
 * Shared visibility helpers for global and private alerts.
 */

function itm_alerts_normalize_sql_alias($alias)
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

function itm_alerts_visibility_sql($alias = '')
{
    $prefix = itm_alerts_normalize_sql_alias($alias);
    return '(' . $prefix . 'assigned_to_employee_id IS NULL OR '
        . $prefix . 'assigned_to_employee_id = ? OR '
        . $prefix . 'created_by = ?)';
}

function itm_alerts_visibility_sql_literal($loggedUserId, $alias = '')
{
    $employeeId = (int)$loggedUserId;
    $prefix = itm_alerts_normalize_sql_alias($alias);
    return '(' . $prefix . 'assigned_to_employee_id IS NULL OR '
        . $prefix . 'assigned_to_employee_id = ' . $employeeId . ' OR '
        . $prefix . 'created_by = ' . $employeeId . ')';
}

function itm_alerts_append_visibility_filter(&$conditions, &$types, &$params, $loggedUserId, $alias = '')
{
    $conditions[] = itm_alerts_visibility_sql($alias);
    $types .= 'ii';
    $employeeId = (int)$loggedUserId;
    $params[] = $employeeId;
    $params[] = $employeeId;
}

/**
 * Build list/sample-data WHERE for tenant alerts: company scope, visibility, live rows only.
 */
function itm_alerts_build_scoped_where_sql($companyId, $loggedUserId, $tableAlias = '')
{
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        return '';
    }

    $where = ' WHERE ' . itm_alerts_normalize_sql_alias($tableAlias) . 'company_id=' . $companyId;
    $where .= ' AND ' . itm_alerts_visibility_sql_literal($loggedUserId, $tableAlias);
    if (function_exists('itm_crud_append_not_deleted_predicate')) {
        $where = itm_crud_append_not_deleted_predicate($where, $tableAlias);
    }

    return $where;
}
