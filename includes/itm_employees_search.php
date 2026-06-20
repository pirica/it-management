<?php
/**
 * Employees list search helpers.
 *
 * Why: Index search must match human-readable FK labels (status, role, department, etc.),
 * not only raw numeric IDs stored on employees rows.
 */

if (!function_exists('itm_employees_build_search_conditions')) {
    /**
     * @param mysqli $conn
     * @param string[] $columns Visible employees columns (post hidden-column filter).
     * @param string $searchRaw Trimmed search text from the request.
     * @return string[] SQL OR fragments (already escaped LIKE operands).
     */
    function itm_employees_build_search_conditions($conn, array $columns, $searchRaw)
    {
        $searchRaw = trim((string)$searchRaw);
        if ($searchRaw === '') {
            return [];
        }

        $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_'))
            ? $searchRaw
            : '%' . $searchRaw . '%';
        $searchValue = mysqli_real_escape_string($conn, $searchPattern);

        $conditions = [];
        foreach ($columns as $col) {
            $col = (string)$col;
            if ($col === '') {
                continue;
            }
            $conditions[] = "CAST(e.`" . str_replace('`', '``', $col) . "` AS CHAR) LIKE '" . $searchValue . "'";
        }

        $fkLabelMap = [
            'department_id' => "COALESCE(d.name, '')",
            'employment_status_id' => "COALESCE(es.name, '')",
            'employee_type_id' => "COALESCE(et.name_type, '')",
            'location_id' => "COALESCE(il.name, '')",
            'workstation_mode_id' => "COALESCE(wm.mode_name, '')",
            'assignment_type_id' => "COALESCE(at.name, '')",
            'employee_position_id' => "COALESCE(ep.name, '')",
            'role_id' => "COALESCE(er.name, '')",
            'access_level_id' => "COALESCE(al.name, '')",
            'office_key_card_department_id' => "COALESCE(okd.name, '')",
        ];

        foreach ($fkLabelMap as $column => $sqlExpr) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $conditions[] = $sqlExpr . " LIKE '" . $searchValue . "'";
        }

        if (in_array('reports_to', $columns, true)) {
            $conditions[] = "COALESCE(m.display_name, '') LIKE '" . $searchValue . "'";
            $conditions[] = "COALESCE(m.first_name, '') LIKE '" . $searchValue . "'";
            $conditions[] = "COALESCE(m.last_name, '') LIKE '" . $searchValue . "'";
            $conditions[] = "CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.last_name, '')) LIKE '" . $searchValue . "'";
        }

        return $conditions;
    }
}
