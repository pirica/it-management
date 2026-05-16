<?php
/**
 * PoE reference helpers — split legacy combined names and build display labels.
 */

if (!function_exists('itm_equipment_poe_split_legacy_name')) {
    /**
     * Split "PoE (802.3af) - up to 15.4W" into protocol name and watts label.
     */
    function itm_equipment_poe_split_legacy_name($combinedName) {
        $combined = trim((string)$combinedName);
        if ($combined === '') {
            return ['name' => '', 'watts' => ''];
        }

        $pos = strrpos($combined, ' - ');
        if ($pos === false) {
            return ['name' => $combined, 'watts' => ''];
        }

        $name = trim(substr($combined, 0, $pos));
        $watts = trim(substr($combined, $pos + 3));
        if ($watts !== '') {
            $watts = ucfirst($watts);
        }

        return ['name' => $name, 'watts' => $watts];
    }
}

if (!function_exists('itm_equipment_poe_display_label')) {
    /**
     * Human label for grids/dropdowns: "PoE (802.3af) - Up to 15.4W".
     */
    function itm_equipment_poe_display_label($name, $watts = '') {
        $name = trim((string)$name);
        $watts = trim((string)$watts);

        if ($name !== '' && $watts === '' && strrpos($name, ' - ') !== false) {
            $split = itm_equipment_poe_split_legacy_name($name);
            $name = $split['name'];
            $watts = $split['watts'];
        }

        if ($name === '') {
            return $watts;
        }
        if ($watts === '') {
            return $name;
        }

        return $name . ' - ' . $watts;
    }
}

if (!function_exists('itm_equipment_poe_load_options')) {
    /**
     * Company-scoped id => display label map for PoE dropdowns.
     */
    function itm_equipment_poe_load_options(mysqli $conn, $company_id) {
        $company_id = (int)$company_id;
        $options = [];
        if ($company_id <= 0) {
            return $options;
        }

        $hasWatts = false;
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment_poe` LIKE 'watts'");
        if ($colRes && mysqli_num_rows($colRes) > 0) {
            $hasWatts = true;
        }

        $select = $hasWatts
            ? 'SELECT id, name, watts FROM equipment_poe WHERE company_id = ' . $company_id . ' ORDER BY name ASC, watts ASC'
            : 'SELECT id, name FROM equipment_poe WHERE company_id = ' . $company_id . ' ORDER BY name ASC';

        $res = mysqli_query($conn, $select);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = itm_equipment_poe_display_label(
                (string)($row['name'] ?? ''),
                $hasWatts ? (string)($row['watts'] ?? '') : ''
            );
            if ($label !== '') {
                $options[$id] = $label;
            }
        }

        return $options;
    }
}
