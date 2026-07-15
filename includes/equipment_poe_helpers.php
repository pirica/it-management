<?php
/**
 * PoE reference helpers — split legacy combined names and build display labels.
 */

if (!function_exists('itm_equipment_poe_has_column')) {
    /**
     * Cached schema probe for optional equipment_poe columns on mixed DB versions.
     */
    function itm_equipment_poe_has_column(mysqli $conn, $column) {
        static $cache = [];
        $column = (string)$column;
        $allowed = ['watts', 'active'];
        if (!in_array($column, $allowed, true)) {
            return false;
        }
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $colEsc = mysqli_real_escape_string($conn, $column);
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment_poe` LIKE '{$colEsc}'");
        $cache[$column] = ($colRes && mysqli_num_rows($colRes) > 0);
        return $cache[$column];
    }
}

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

        $hasWatts = itm_equipment_poe_has_column($conn, 'watts');
        $hasActive = itm_equipment_poe_has_column($conn, 'active');

        $where = 'company_id = ' . $company_id;
        if ($hasActive) {
            $where .= ' AND active = 1';
        }

        $select = $hasWatts
            ? 'SELECT id, name, watts FROM equipment_poe WHERE ' . $where . ' ORDER BY name ASC, watts ASC'
            : 'SELECT id, name FROM equipment_poe WHERE ' . $where . ' ORDER BY name ASC';

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

if (!function_exists('itm_equipment_poe_options_rows')) {
    /**
     * Dropdown rows [{id, label}] for equipment forms and APIs.
     */
    function itm_equipment_poe_options_rows(mysqli $conn, $company_id) {
        $rows = [];
        foreach (itm_equipment_poe_load_options($conn, (int)$company_id) as $id => $label) {
            $rows[] = ['id' => (int)$id, 'label' => (string)$label];
        }
        return $rows;
    }
}

if (!function_exists('itm_equipment_poe_append_persisted_row')) {
    /**
     * Keep edit selections when company-scoped option queries omit the saved PoE row.
     */
    function itm_equipment_poe_append_persisted_row(mysqli $conn, array &$rows, $poeId, $companyId) {
        $poeId = (int)$poeId;
        $companyId = (int)$companyId;
        if ($poeId <= 0) {
            return;
        }

        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $poeId) {
                return;
            }
        }

        $hasWatts = itm_equipment_poe_has_column($conn, 'watts');

        $found = null;
        if ($companyId > 0) {
            $sql = $hasWatts
                ? 'SELECT id, name, watts FROM equipment_poe WHERE id = ? AND company_id = ? LIMIT 1'
                : 'SELECT id, name FROM equipment_poe WHERE id = ? AND company_id = ? LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $poeId, $companyId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $found = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
            }
        }

        if (!$found) {
            $sql = $hasWatts
                ? 'SELECT id, name, watts FROM equipment_poe WHERE id = ? LIMIT 1'
                : 'SELECT id, name FROM equipment_poe WHERE id = ? LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $poeId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $found = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
            }
        }

        if (!$found) {
            return;
        }

        $label = itm_equipment_poe_display_label(
            (string)($found['name'] ?? ''),
            $hasWatts ? (string)($found['watts'] ?? '') : ''
        );
        if ($label === '') {
            return;
        }

        $rows[] = ['id' => $poeId, 'label' => $label];
    }
}
