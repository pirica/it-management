<?php
/**
 * FK dropdown option label helpers.
 *
 * Why: Create/edit selects should show the same secondary labels list search matches (e.g. department code FNB).
 */

if (!function_exists('itm_format_fk_option_label_with_secondary')) {
    function itm_format_fk_option_label_with_secondary($primaryLabel, $secondaryLabel)
    {
        $primaryLabel = trim((string)$primaryLabel);
        $secondaryLabel = trim((string)$secondaryLabel);
        if ($secondaryLabel === '' || strcasecmp($secondaryLabel, $primaryLabel) === 0) {
            return $primaryLabel;
        }
        if ($primaryLabel === '') {
            return $secondaryLabel;
        }

        return $primaryLabel . ' (' . $secondaryLabel . ')';
    }
}

if (!function_exists('itm_department_select_rows_for_company')) {
    /**
     * @return array<int, array{id:int, name:string, code:string}>
     */
    function itm_department_select_rows_for_company(mysqli $conn, int $companyId): array
    {
        $rows = [];
        if ($companyId <= 0) {
            return $rows;
        }

        $res = mysqli_query(
            $conn,
            'SELECT id, name, code FROM departments WHERE company_id=' . (int)$companyId . ' ORDER BY name'
        );
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'code' => (string)($row['code'] ?? ''),
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_department_option_label')) {
    function itm_department_option_label(array $departmentRow)
    {
        return itm_format_fk_option_label_with_secondary(
            (string)($departmentRow['name'] ?? ''),
            (string)($departmentRow['code'] ?? '')
        );
    }
}

if (!function_exists('itm_location_option_label')) {
    function itm_location_option_label(array $locationRow)
    {
        return itm_format_fk_option_label_with_secondary(
            (string)($locationRow['name'] ?? ''),
            (string)($locationRow['location_code'] ?? '')
        );
    }
}

if (!function_exists('itm_employee_manager_option_label')) {
    function itm_employee_manager_option_label(array $employeeRow)
    {
        $displayName = trim((string)($employeeRow['display_name'] ?? ''));
        $username = trim((string)($employeeRow['username'] ?? ''));
        if ($displayName === '' && $username !== '') {
            return $username;
        }

        return itm_format_fk_option_label_with_secondary($displayName, $username);
    }
}
