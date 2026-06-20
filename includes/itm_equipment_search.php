<?php
/**
 * Equipment list search helpers.
 *
 * Why: Index search must match create/edit FK select labels (department code, supplier code, location code, rack code, assignee username, etc.).
 */

if (!function_exists('itm_equipment_search_join_sql')) {
    /**
     * LEFT JOIN fragment shared by equipment list COUNT/SELECT when search may touch FK labels.
     */
    function itm_equipment_search_join_sql()
    {
        return '
        LEFT JOIN companies c ON c.id = e.company_id
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
        LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
        LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
        LEFT JOIN suppliers s ON s.id = e.supplier_id AND s.company_id = e.company_id
        LEFT JOIN employees emp ON emp.id = e.assigned_to_employee_id AND emp.company_id = e.company_id
        LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
        LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
        LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id
        LEFT JOIN equipment_statuses es ON es.id = e.status_id
        LEFT JOIN warranty_types wt ON wt.id = e.warranty_type_id AND wt.company_id = e.company_id
        LEFT JOIN printer_device_types pdt ON pdt.id = e.printer_device_type_id AND pdt.company_id = e.company_id
        LEFT JOIN workstation_device_types wdt ON wdt.id = e.workstation_device_type_id AND wdt.company_id = e.company_id
        LEFT JOIN workstation_os_types wot ON wot.id = e.workstation_os_type_id AND wot.company_id = e.company_id
        LEFT JOIN workstation_office wo ON wo.id = e.workstation_office_id AND wo.company_id = e.company_id
        LEFT JOIN workstation_os_versions wov ON wov.id = e.workstation_os_version_id AND wov.company_id = e.company_id
        LEFT JOIN workstation_ram wr ON wr.id = e.workstation_ram_id AND wr.company_id = e.company_id
        LEFT JOIN rj45_speed rs ON rs.id = e.rj45_speed_id AND rs.company_id = e.company_id
        LEFT JOIN equipment_rj45 erj ON erj.id = e.switch_rj45_id AND erj.company_id = e.company_id
        LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = e.switch_port_numbering_layout_id AND spnl.company_id = e.company_id
        LEFT JOIN equipment_poe epoe ON epoe.id = e.switch_poe_id AND epoe.company_id = e.company_id
        LEFT JOIN equipment_environment eenv ON eenv.id = e.switch_environment_id AND eenv.company_id = e.company_id
        LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id AND ef.company_id = e.company_id
        LEFT JOIN equipment_fiber_patch efp ON efp.id = e.switch_fiber_patch_id AND efp.company_id = e.company_id
        LEFT JOIN equipment_fiber_rack efr ON efr.id = e.switch_fiber_rack_id AND efr.company_id = e.company_id';
    }
}

if (!function_exists('itm_equipment_build_search_where_sql')) {
    /**
     * @return string SQL fragment beginning with " AND (" or empty when search is blank.
     */
    function itm_equipment_build_search_where_sql($conn, $searchRaw)
    {
        $searchRaw = trim((string)$searchRaw);
        if ($searchRaw === '') {
            return '';
        }

        $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_'))
            ? $searchRaw
            : '%' . $searchRaw . '%';
        $searchEsc = mysqli_real_escape_string($conn, $searchPattern);

        $likeExprs = [
            "CAST(e.id AS CHAR) LIKE '{$searchEsc}'",
            "e.name LIKE '{$searchEsc}'",
            "e.serial_number LIKE '{$searchEsc}'",
            "e.model LIKE '{$searchEsc}'",
            "e.hostname LIKE '{$searchEsc}'",
            "e.ip_address LIKE '{$searchEsc}'",
            "e.mac_address LIKE '{$searchEsc}'",
            "e.patch_port LIKE '{$searchEsc}'",
            "e.notes LIKE '{$searchEsc}'",
            "COALESCE(d.name, '') LIKE '{$searchEsc}'",
            "COALESCE(d.code, '') LIKE '{$searchEsc}'",
            "COALESCE(s.name, '') LIKE '{$searchEsc}'",
            "COALESCE(s.supplier_code, '') LIKE '{$searchEsc}'",
            "COALESCE(emp.first_name, '') LIKE '{$searchEsc}'",
            "COALESCE(emp.last_name, '') LIKE '{$searchEsc}'",
            "COALESCE(emp.display_name, '') LIKE '{$searchEsc}'",
            "COALESCE(emp.username, '') LIKE '{$searchEsc}'",
            "CONCAT(COALESCE(emp.first_name, ''), ' ', COALESCE(emp.last_name, '')) LIKE '{$searchEsc}'",
            "COALESCE(c.company, '') LIKE '{$searchEsc}'",
            "COALESCE(et.name, '') LIKE '{$searchEsc}'",
            "COALESCE(m.name, '') LIKE '{$searchEsc}'",
            "COALESCE(l.name, '') LIKE '{$searchEsc}'",
            "COALESCE(l.location_code, '') LIKE '{$searchEsc}'",
            "COALESCE(r.name, '') LIKE '{$searchEsc}'",
            "COALESCE(r.rack_code, '') LIKE '{$searchEsc}'",
            "COALESCE(idf.name, '') LIKE '{$searchEsc}'",
            "COALESCE(es.name, '') LIKE '{$searchEsc}'",
            "COALESCE(wt.name, '') LIKE '{$searchEsc}'",
            "COALESCE(pdt.name, '') LIKE '{$searchEsc}'",
            "COALESCE(wdt.name, '') LIKE '{$searchEsc}'",
            "COALESCE(wot.name, '') LIKE '{$searchEsc}'",
            "COALESCE(wo.name, '') LIKE '{$searchEsc}'",
            "COALESCE(wov.name, '') LIKE '{$searchEsc}'",
            "COALESCE(wr.name, '') LIKE '{$searchEsc}'",
            "COALESCE(rs.cable_type, '') LIKE '{$searchEsc}'",
            "COALESCE(erj.name, '') LIKE '{$searchEsc}'",
            "COALESCE(spnl.name, '') LIKE '{$searchEsc}'",
            "COALESCE(epoe.name, '') LIKE '{$searchEsc}'",
            "COALESCE(eenv.name, '') LIKE '{$searchEsc}'",
            "COALESCE(ef.name, '') LIKE '{$searchEsc}'",
            "COALESCE(efp.name, '') LIKE '{$searchEsc}'",
            "COALESCE(efr.name, '') LIKE '{$searchEsc}'",
        ];

        return ' AND (' . implode(' OR ', $likeExprs) . ')';
    }
}
