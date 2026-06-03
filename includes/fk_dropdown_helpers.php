<?php
/**
 * Shared FK dropdown helpers for tenant-scoped selects.
 *
 * Why: Company-scoped option lists can omit a persisted FK from another tenant's seed row;
 * resolve the equivalent row in the active company by business key before appending options.
 */

require_once __DIR__ . '/detect_fk_dropdown_ui_risk_lib.php';

if (!function_exists('itm_fk_table_column_names')) {
    /**
     * @return array<int, string>
     */
    function itm_fk_table_column_names(mysqli $conn, string $table): array
    {
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($table)) {
            return [];
        }

        $columns = [];
        $res = mysqli_query($conn, 'DESCRIBE `' . $table . '`');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $columns[] = (string)($row['Field'] ?? '');
        }

        return array_values(array_filter($columns));
    }
}

if (!function_exists('itm_fk_label_column_for_table')) {
    function itm_fk_label_column_for_table(array $columns): string
    {
        foreach (['name', 'title', 'username', 'account_name', 'account_code', 'code', 'description', 'email', 'mode_name', 'display_name', 'stage', 'status', 'approver_type_description'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return 'name';
    }
}

if (!function_exists('itm_fk_label_by_id')) {
    function itm_fk_label_by_id(mysqli $conn, array $fk, int $companyId, int $rawId): string
    {
        $id = (int)$rawId;
        if ($id <= 0) {
            return '';
        }

        $refTable = (string)($fk['REFERENCED_TABLE_NAME'] ?? '');
        $refColumn = (string)($fk['REFERENCED_COLUMN_NAME'] ?? 'id');
        if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refColumn)) {
            return '';
        }

        $refColumns = itm_fk_table_column_names($conn, $refTable);
        $labelCol = itm_fk_label_column_for_table($refColumns);

        if ($companyId > 0 && in_array('company_id', $refColumns, true)) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT `' . $labelCol . '` AS label FROM `' . $refTable . '` WHERE `' . $refColumn . '`=? AND company_id=? LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = ($res) ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row) && isset($row['label'])) {
                    return (string)$row['label'];
                }
            }
        }

        $fallback = mysqli_query(
            $conn,
            'SELECT `' . $labelCol . '` AS label FROM `' . $refTable . '` WHERE `' . $refColumn . '`=' . $id . ' LIMIT 1'
        );
        $fallbackRow = ($fallback) ? mysqli_fetch_assoc($fallback) : null;
        if (is_array($fallbackRow) && isset($fallbackRow['label'])) {
            return (string)$fallbackRow['label'];
        }

        return '';
    }
}

if (!function_exists('itm_first_tenant_row_id')) {
    /**
     * First tenant row for database.sql sample seed only (not edit-form FK resolution).
     */
    function itm_first_tenant_row_id(mysqli $conn, string $refTable, int $companyId): int
    {
        if ($companyId <= 0 || !itm_is_safe_identifier($refTable)) {
            return 0;
        }

        $refColumns = itm_fk_table_column_names($conn, $refTable);
        $where = in_array('company_id', $refColumns, true)
            ? 'company_id = ' . (int)$companyId
            : '1=1';
        $sql = 'SELECT id FROM `' . $refTable . '` WHERE ' . $where . ' ORDER BY id ASC LIMIT 1';
        $res = mysqli_query($conn, $sql);

        return ($res && ($row = mysqli_fetch_assoc($res))) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_fk_resolve_company_equivalent_id')) {
    /**
     * Map a stored FK id to the active tenant's row when seed data reused business keys under different ids.
     */
    function itm_fk_resolve_company_equivalent_id(mysqli $conn, array $fk, int $companyId, int $storedId): int
    {
        if ($storedId <= 0 || $companyId <= 0) {
            return $storedId;
        }

        $refTable = (string)($fk['REFERENCED_TABLE_NAME'] ?? '');
        $refColumn = (string)($fk['REFERENCED_COLUMN_NAME'] ?? 'id');
        if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refColumn)) {
            return $storedId;
        }

        // Why: employee names are not unique per company; remapping by first/last name can reassign the wrong person.
        if ($refTable === 'employees') {
            return $storedId;
        }

        $refColumns = itm_fk_table_column_names($conn, $refTable);
        if (!in_array('company_id', $refColumns, true)) {
            return $storedId;
        }

        $businessKeys = itm_detect_fk_business_key_columns($refTable, $refColumns);
        $selectCols = ['`' . $refColumn . '` AS id', 'company_id'];
        foreach ($businessKeys as $keyColumn) {
            if (itm_is_safe_identifier($keyColumn)) {
                $selectCols[] = '`' . $keyColumn . '`';
            }
        }

        $storedSql = 'SELECT ' . implode(', ', $selectCols)
            . ' FROM `' . $refTable . '` WHERE `' . $refColumn . '`=' . (int)$storedId . ' LIMIT 1';
        $storedRes = mysqli_query($conn, $storedSql);
        $storedRow = ($storedRes) ? mysqli_fetch_assoc($storedRes) : null;
        if (!is_array($storedRow)) {
            return $storedId;
        }

        if ((int)($storedRow['company_id'] ?? 0) === $companyId) {
            return $storedId;
        }

        if ($businessKeys === []) {
            return $storedId;
        }

        $whereParts = ['company_id = ' . (int)$companyId];
        foreach ($businessKeys as $keyColumn) {
            if (!itm_is_safe_identifier($keyColumn)) {
                continue;
            }
            $keyValue = isset($storedRow[$keyColumn]) ? (string)$storedRow[$keyColumn] : '';
            if ($keyValue === '') {
                $whereParts[] = '(`' . $keyColumn . "` = '' OR `" . $keyColumn . '` IS NULL)';
            } else {
                $whereParts[] = '`' . $keyColumn . "` = '" . mysqli_real_escape_string($conn, $keyValue) . "'";
            }
        }

        $matchSql = 'SELECT `' . $refColumn . '` AS id FROM `' . $refTable . '` WHERE '
            . implode(' AND ', $whereParts) . ' ORDER BY `' . $refColumn . '` ASC LIMIT 1';
        $matchRes = mysqli_query($conn, $matchSql);
        $matchRow = ($matchRes) ? mysqli_fetch_assoc($matchRes) : null;
        $tenantEquivalentId = is_array($matchRow) ? (int)($matchRow['id'] ?? 0) : 0;

        return $tenantEquivalentId > 0 ? $tenantEquivalentId : $storedId;
    }
}

if (!function_exists('itm_fk_append_selected_option')) {
    /**
     * @param array<int, array<string, mixed>> $options
     * @param callable|null $labelResolver function(mysqli $conn, array $fk, int $companyId, int $id): string
     * @return array<int, array<string, mixed>>
     */
    function itm_fk_append_selected_option(mysqli $conn, array $fk, int $companyId, array $options, $selectedValue, $labelResolver = null): array
    {
        $selectedId = (int)$selectedValue;
        if ($selectedId <= 0) {
            return $options;
        }

        $resolvedId = itm_fk_resolve_company_equivalent_id($conn, $fk, $companyId, $selectedId);

        foreach ($options as $option) {
            if ((int)($option['id'] ?? 0) === $resolvedId) {
                return $options;
            }
        }

        $label = '';
        if (is_callable($labelResolver)) {
            $label = (string)$labelResolver($conn, $fk, $companyId, $resolvedId);
        } else {
            $label = itm_fk_label_by_id($conn, $fk, $companyId, $resolvedId);
        }

        if ($label === '') {
            $label = (string)$resolvedId;
        }

        $options[] = ['id' => $resolvedId, 'label' => $label];

        return $options;
    }
}
