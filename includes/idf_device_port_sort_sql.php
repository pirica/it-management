<?php

/**
 * Shared ORDER BY fragments for IDF device port listings (modules/idfs/device.php).
 *
 * Why: RJ45/Ethernet-family rows must cluster before fiber (SFP/SFP+) for every user-selected
 * primary sort column, otherwise duplex rows sharing the same port_no interleave oddly.
 */

if (!function_exists('itm_idf_positions_resolve_join_sql')) {

    /**
     * JOIN condition matching idf_resolve_port_position(): prefer position id, else legacy position_no.
     */
    function itm_idf_positions_resolve_join_sql(string $portAlias = 'pr', string $positionAlias = 'p'): string
    {
        return "{$positionAlias}.company_id = {$portAlias}.company_id
      AND (
            {$positionAlias}.id = {$portAlias}.position_id
            OR (
                {$positionAlias}.position_no = {$portAlias}.position_id
                AND NOT EXISTS (
                    SELECT 1
                    FROM idf_positions p_actual
                    WHERE p_actual.company_id = {$portAlias}.company_id
                      AND p_actual.id = {$portAlias}.position_id
                    LIMIT 1
                )
            )
      )";
    }
}

if (!function_exists('itm_idf_port_type_label_sql')) {

    /** @return string SQL expression for resolved switch port type label (matches device.php SELECT alias). */
    function itm_idf_port_type_label_sql(): string
    {
        return "COALESCE(spt.type, spt_any.type, 'RJ45')";
    }
}

if (!function_exists('itm_idf_port_type_label_with_switch_sql')) {

    /**
     * Company-scoped type label with switch_ports fallback (avoids cross-tenant spt_any mislabels).
     */
    function itm_idf_port_type_label_with_switch_sql(): string
    {
        return "COALESCE(spt.type, spt_sp.type, spt_any.type, 'RJ45')";
    }
}

if (!function_exists('itm_idf_destination_switch_ports_join_sql')) {

    /**
     * switch_ports fallback only when position equipment_id is a numeric FK (patch panels may use asset tokens).
     */
    function itm_idf_destination_switch_ports_join_sql(string $portAlias = 'pr', string $positionAlias = 'p'): string
    {
        return "LEFT JOIN switch_ports sp_dest
       ON sp_dest.company_id = {$portAlias}.company_id
      AND sp_dest.port_number = {$portAlias}.port_no
      AND " . itm_idf_position_numeric_equipment_match_sql($positionAlias, 'sp_dest.equipment_id');
    }
}

if (!function_exists('itm_idf_position_numeric_equipment_match_sql')) {

    /**
     * Match idf_positions.equipment_id to switch_ports.equipment_id only for numeric equipment FKs.
     */
    function itm_idf_position_numeric_equipment_match_sql(string $positionAlias = 'p', string $switchEquipmentColumnSql = 'sp.equipment_id'): string
    {
        return "({$positionAlias}.equipment_id REGEXP '^[0-9]+$' AND {$switchEquipmentColumnSql} = CAST({$positionAlias}.equipment_id AS UNSIGNED))";
    }
}

if (!function_exists('itm_idf_port_fiber_family_rank_sql')) {

    /** @return string CASE expression: 0 = copper RJ45-ish, 1 = fiber (label normalizes LIKE sfp%). */
    function itm_idf_port_fiber_family_rank_sql(): string
    {
        $label = itm_idf_port_type_label_sql();
        return "CASE\n    WHEN LOWER(REPLACE(REPLACE(TRIM({$label}), ' ', ''), '+', 'plus')) LIKE 'sfp%' THEN 1\n    ELSE 0\nEND";
    }
}

if (!function_exists('itm_idf_ports_device_list_order_sql')) {

    /**
     * Full ORDER BY tail for device port grids: fiber rank first, then primary column, port_no ties.
     *
     * @param string $fiberRankExpr       Usually itm_idf_port_fiber_family_rank_sql()
     * @param string $primarySortSqlExpr  Mapped sort column/expression from $portSortMap
     * @param string $sortDirSql          Already normalized ASC or DESC
     *
     * @return string Clause body (without leading ORDER BY)
     */
    function itm_idf_ports_device_list_order_sql(string $fiberRankExpr, string $primarySortSqlExpr, string $sortDirSql): string
    {
        $dir = strtoupper($sortDirSql) === 'DESC' ? 'DESC' : 'ASC';
        return $fiberRankExpr . ' ASC, ' . $primarySortSqlExpr . ' ' . $dir . ', pr.port_no ASC';
    }
}

if (!function_exists('itm_idf_port_row_fiber_family_rank_value')) {

    /**
     * Fiber rank for a hydrated port row (0 = copper, 1 = SFP/SFP+ label family).
     * Mirrors itm_idf_port_fiber_family_rank_sql() so PHP reorder matches MySQL ordering.
     */
    function itm_idf_port_row_fiber_family_rank_value(array $row): int
    {
        $label = (string)($row['port_type_label'] ?? 'RJ45');
        $normalized = strtolower(str_replace([' ', '+'], ['', 'plus'], trim($label)));

        // Mirrors SQL LIKE 'sfp%' on the normalized expression.
        return (preg_match('/^sfp/', $normalized) === 1) ? 1 : 0;
    }
}

if (!function_exists('itm_idf_ports_device_list_primary_compare')) {

    /**
     * Compare two port rows on the user-selected primary column (no fiber rank, no port_no).
     */
    function itm_idf_ports_device_list_primary_compare(array $a, array $b, string $sortField): int
    {
        switch ($sortField) {
            case 'port_no':
                return ((int)($a['port_no'] ?? 0)) <=> ((int)($b['port_no'] ?? 0));
            case 'port_type':
                return strcasecmp((string)($a['port_type_label'] ?? ''), (string)($b['port_type_label'] ?? ''));
            case 'label':
                return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
            case 'status':
                return strcasecmp((string)($a['status_label'] ?? ''), (string)($b['status_label'] ?? ''));
            case 'connected_to':
                return strcasecmp((string)($a['connected_to'] ?? ''), (string)($b['connected_to'] ?? ''));
            case 'vlan':
                return strcasecmp((string)($a['vlan_label'] ?? ''), (string)($b['vlan_label'] ?? ''));
            case 'speed':
                return strcasecmp((string)($a['speed_label'] ?? ''), (string)($b['speed_label'] ?? ''));
            case 'rj45_speed':
                return strcasecmp((string)($a['rj45_speed_label'] ?? ''), (string)($b['rj45_speed_label'] ?? ''));
            case 'poe':
                return strcasecmp((string)($a['poe_label'] ?? ''), (string)($b['poe_label'] ?? ''));
            case 'fiber_patch':
                return strcasecmp((string)($a['fiber_patch_label'] ?? ''), (string)($b['fiber_patch_label'] ?? ''));
            case 'fiber_rack':
                return strcasecmp((string)($a['fiber_rack_label'] ?? ''), (string)($b['fiber_rack_label'] ?? ''));
            case 'notes':
                return strcasecmp((string)($a['notes'] ?? ''), (string)($b['notes'] ?? ''));
            case 'link':
                return ((int)($a['link_id'] ?? 0)) <=> ((int)($b['link_id'] ?? 0));
            default:
                return strcasecmp((string)($a['port_type_label'] ?? ''), (string)($b['port_type_label'] ?? ''));
        }
    }
}

if (!function_exists('itm_idf_ports_device_list_compare_rows')) {

    /**
     * Full compare matching itm_idf_ports_device_list_order_sql(): fiber first, primary + dir, port_no ASC.
     */
    function itm_idf_ports_device_list_compare_rows(array $a, array $b, string $sortField, string $sortDir): int
    {
        $dir = strtoupper($sortDir) === 'DESC' ? -1 : 1;
        $fa = itm_idf_port_row_fiber_family_rank_value($a);
        $fb = itm_idf_port_row_fiber_family_rank_value($b);
        if ($fa !== $fb) {
            return $fa <=> $fb;
        }

        $primary = itm_idf_ports_device_list_primary_compare($a, $b, $sortField);
        if ($primary !== 0) {
            return $dir * $primary;
        }

        return ((int)($a['port_no'] ?? 0)) <=> ((int)($b['port_no'] ?? 0));
    }
}
