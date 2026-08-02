<?php
/**
 * Bespoke / scalar list search helpers.
 *
 * Why: modules whose visible list columns are plain text or datetime (no FK labels)
 * still need a canonical search builder for "Search (all fields)" and static FK-label audits.
 */

if (!function_exists('itm_crud_scalar_column_search_conditions')) {
    /**
     * Build OR fragments for scalar visible columns (text + optional datetime display formats).
     *
     * @param string $table Main table name (identifier-validated).
     * @param string[] $visibleFieldNames Visible list/search column names.
     * @param string[] $dateTimeFieldNames Subset searched with DATE_FORMAT patterns matching list display.
     * @return string[] SQL OR condition fragments using ? placeholders.
     */
    function itm_crud_scalar_column_search_conditions($table, array $visibleFieldNames, array $dateTimeFieldNames = [])
    {
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier((string)$table)) {
            return [];
        }

        $dateTimeLookup = [];
        foreach ($dateTimeFieldNames as $fieldName) {
            $fieldName = (string)$fieldName;
            if ($fieldName !== '' && itm_is_safe_identifier($fieldName)) {
                $dateTimeLookup[$fieldName] = true;
            }
        }

        $conditions = [];
        foreach ($visibleFieldNames as $fieldName) {
            $fieldName = (string)$fieldName;
            if ($fieldName === '' || !itm_is_safe_identifier($fieldName)) {
                continue;
            }

            $colSql = '`' . str_replace('`', '``', $fieldName) . '`';
            if (isset($dateTimeLookup[$fieldName])) {
                // Match module list display (d-M-Y H:i) and stored ISO datetime substrings.
                $conditions[] = "DATE_FORMAT({$colSql}, '%d-%b-%Y %H:%i') LIKE ?";
                $conditions[] = "DATE_FORMAT({$colSql}, '%Y-%m-%d %H:%i:%s') LIKE ?";
                continue;
            }

            $conditions[] = "{$colSql} LIKE ?";
        }

        return $conditions;
    }
}
