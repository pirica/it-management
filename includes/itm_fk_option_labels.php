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

if (!function_exists('itm_supplier_select_rows_for_company')) {
    /**
     * @return array<int, array{id:int, name:string, supplier_code:string}>
     */
    function itm_supplier_select_rows_for_company(mysqli $conn, int $companyId): array
    {
        $rows = [];
        if ($companyId <= 0) {
            return $rows;
        }

        $res = mysqli_query(
            $conn,
            'SELECT id, name, supplier_code FROM suppliers WHERE company_id=' . (int)$companyId . ' ORDER BY name'
        );
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'supplier_code' => (string)($row['supplier_code'] ?? ''),
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_supplier_option_label')) {
    function itm_supplier_option_label(array $supplierRow)
    {
        return itm_format_fk_option_label_with_secondary(
            (string)($supplierRow['name'] ?? ''),
            (string)($supplierRow['supplier_code'] ?? '')
        );
    }
}

if (!function_exists('itm_rack_select_rows_for_company')) {
    /**
     * @return array<int, array{id:int, name:string, rack_code:string}>
     */
    function itm_rack_select_rows_for_company(mysqli $conn, int $companyId): array
    {
        $rows = [];
        if ($companyId <= 0) {
            return $rows;
        }

        $res = mysqli_query(
            $conn,
            'SELECT id, name, rack_code FROM racks WHERE company_id=' . (int)$companyId . ' ORDER BY name'
        );
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'rack_code' => (string)($row['rack_code'] ?? ''),
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_rack_option_label')) {
    function itm_rack_option_label(array $rackRow)
    {
        return itm_format_fk_option_label_with_secondary(
            (string)($rackRow['name'] ?? ''),
            (string)($rackRow['rack_code'] ?? '')
        );
    }
}

if (!function_exists('itm_location_select_rows_for_company')) {
    /**
     * @return array<int, array{id:int, name:string, location_code:string}>
     */
    function itm_location_select_rows_for_company(mysqli $conn, int $companyId, bool $activeOnly = true): array
    {
        $rows = [];
        if ($companyId <= 0) {
            return $rows;
        }

        $sql = 'SELECT id, name, location_code FROM it_locations WHERE company_id=' . (int)$companyId;
        if ($activeOnly) {
            $sql .= ' AND active=1';
        }
        $sql .= ' ORDER BY name';
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'location_code' => (string)($row['location_code'] ?? ''),
            ];
        }

        return $rows;
    }
}
