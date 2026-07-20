<?php
/**
 * Flattened CRUD list search helpers.
 *
 * Why: "Search (all fields)" must match human-readable FK labels (status names, roles,
 * departments, etc.), not only raw numeric IDs on the main table row.
 */

require_once __DIR__ . '/fk_dropdown_helpers.php';

if (!function_exists('itm_crud_fk_label_search_conditions')) {
    /**
     * Build EXISTS-based OR fragments for FK label text search (no JOIN changes required).
     *
     * @param mysqli $conn
     * @param string $mainTable Main CRUD table name.
     * @param string $mainAlias Table alias in the list query ('' when unqualified).
     * @param array<string, array<string, string>> $fkMap cr_fk_map() output keyed by column name.
     * @param string[] $visibleFieldNames Visible/searchable column names from the list UI.
     * @param int $companyId Active tenant id (0 when not company-scoped).
     * @param string $searchEsc mysqli_real_escape_string() LIKE pattern operand.
     * @return string[] SQL OR condition fragments.
     */
    function itm_crud_fk_label_search_conditions(
        $conn,
        $mainTable,
        $mainAlias,
        array $fkMap,
        array $visibleFieldNames,
        $companyId,
        $searchEsc
    ) {
        if (!($conn instanceof mysqli) || $searchEsc === '' || empty($fkMap) || empty($visibleFieldNames)) {
            return [];
        }

        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($mainTable)) {
            return [];
        }

        $mainAlias = trim((string)$mainAlias);
        $companyId = (int)$companyId;
        $conditions = [];
        $mainPrefix = ($mainAlias !== '') ? ($mainAlias . '.') : '';

        foreach ($visibleFieldNames as $fieldName) {
            $fieldName = (string)$fieldName;
            if ($fieldName === '' || !isset($fkMap[$fieldName])) {
                continue;
            }

            $fk = $fkMap[$fieldName];
            $refTable = (string)($fk['REFERENCED_TABLE_NAME'] ?? '');
            $refColumn = (string)($fk['REFERENCED_COLUMN_NAME'] ?? 'id');
            if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refColumn) || !itm_is_safe_identifier($fieldName)) {
                continue;
            }

            $refColumns = itm_fk_table_column_names($conn, $refTable);
            if (empty($refColumns)) {
                continue;
            }

            $labelCol = itm_fk_label_column_for_table($refColumns);
            $fkColSql = $mainPrefix . '`' . str_replace('`', '``', $fieldName) . '`';
            $refTableSql = '`' . str_replace('`', '``', $refTable) . '`';
            $refColumnSql = '`' . str_replace('`', '``', $refColumn) . '`';
            $labelColSql = '`' . str_replace('`', '``', $labelCol) . '`';

            $labelLikes = [$labelColSql . " LIKE '" . $searchEsc . "'"];

            if ($refTable === 'employees') {
                foreach (['display_name', 'first_name', 'last_name', 'username'] as $employeeCol) {
                    if (in_array($employeeCol, $refColumns, true) && $employeeCol !== $labelCol) {
                        $employeeColSql = '`' . str_replace('`', '``', $employeeCol) . '`';
                        $labelLikes[] = $employeeColSql . " LIKE '" . $searchEsc . "'";
                    }
                }
                if (in_array('first_name', $refColumns, true) && in_array('last_name', $refColumns, true)) {
                    $labelLikes[] = "CONCAT(COALESCE(`first_name`, ''), ' ', COALESCE(`last_name`, '')) LIKE '" . $searchEsc . "'";
                }
            } elseif ($refTable === 'colors' && in_array('hex_color', $refColumns, true)) {
                $labelLikes[] = "`hex_color` LIKE '" . $searchEsc . "'";
            }

            $scopeSql = '';
            if ($companyId > 0 && in_array('company_id', $refColumns, true) && in_array('company_id', itm_fk_table_column_names($conn, $mainTable), true)) {
                $scopeSql = ' AND r.`company_id` = ' . $mainPrefix . '`company_id`';
            } elseif ($companyId > 0 && in_array('company_id', $refColumns, true)) {
                $scopeSql = ' AND r.`company_id` = ' . (int)$companyId;
            }

            $labelPredicate = '(' . implode(' OR ', $labelLikes) . ')';
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $refTableSql . ' r WHERE r.' . $refColumnSql . ' = ' . $fkColSql . $scopeSql . ' AND ' . $labelPredicate . ')';
        }

        return $conditions;
    }
}
