<?php
/**
 * Software / Office / OS / hardware EOL helpers.
 *
 * Why: Catalog dates live on workstation_office, workstation_os_versions, and software.
 * Equipment stores its own hardware eol/extended/esu dates and links catalog products via
 * equipment_software. Calendar shows one event per catalog product (not per asset).
 * Dashboard expiring_30d and email eol_date use hardware or inherited catalog eol_date only.
 */

if (!function_exists('itm_software_eol_kind_meta')) {
    /**
     * @return array<string,array{label:string,color:string,icon:string}>
     */
    function itm_software_eol_kind_meta()
    {
        return [
            'eol_date' => ['label' => 'EOL', 'color' => '#db2777', 'icon' => '📅'],
            'extended_date' => ['label' => 'Extended', 'color' => '#f97316', 'icon' => '📅'],
            'esu_date' => ['label' => 'ESU', 'color' => '#ca8a04', 'icon' => '📅'],
        ];
    }
}

if (!function_exists('itm_software_eol_hardware_kind_meta')) {
    /**
     * Distinct colours from catalog events so mixed calendar days stay readable.
     *
     * @return array<string,array{label:string,color:string,icon:string}>
     */
    function itm_software_eol_hardware_kind_meta()
    {
        return [
            'eol_date' => ['label' => 'Hardware EOL', 'color' => '#7c3aed', 'icon' => '🖥️'],
            'extended_date' => ['label' => 'Hardware Extended', 'color' => '#8b5cf6', 'icon' => '🖥️'],
            'esu_date' => ['label' => 'Hardware ESU', 'color' => '#a78bfa', 'icon' => '🖥️'],
        ];
    }
}

if (!function_exists('itm_software_eol_table_has_column')) {
    function itm_software_eol_table_has_column(mysqli $conn, $table, $column)
    {
        $table = trim((string)$table);
        $column = trim((string)$column);
        if ($table === '' || $column === '' || !function_exists('itm_is_safe_identifier')) {
            return false;
        }
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
            return false;
        }
        $res = mysqli_query($conn, 'SHOW COLUMNS FROM `' . $table . '` LIKE \'' . mysqli_real_escape_string($conn, $column) . '\'');
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('itm_software_eol_tables_ready')) {
    function itm_software_eol_tables_ready(mysqli $conn)
    {
        return itm_software_eol_table_has_column($conn, 'equipment', 'eol_date')
            && itm_software_eol_table_has_column($conn, 'software', 'eol_date')
            && itm_software_eol_table_has_column($conn, 'equipment_software', 'software_id')
            && itm_software_eol_table_has_column($conn, 'workstation_office', 'eol_date')
            && itm_software_eol_table_has_column($conn, 'workstation_os_versions', 'eol_date');
    }
}

if (!function_exists('itm_software_eol_normalize_id_list')) {
    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    function itm_software_eol_normalize_id_list($raw)
    {
        if (!is_array($raw)) {
            $raw = [];
        }
        $ids = [];
        foreach ($raw as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('itm_software_eol_catalog_options')) {
    /**
     * @return array<int,array{id:int,label:string}>
     */
    function itm_software_eol_catalog_options(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        $items = [];
        if ($companyId <= 0 || !itm_software_eol_table_has_column($conn, 'software', 'name')) {
            return $items;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, build FROM software
             WHERE company_id = ? AND deleted_at IS NULL AND active = 1
             ORDER BY name ASC'
        );
        if (!$stmt) {
            return $items;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = trim((string)($row['name'] ?? ''));
            $build = trim((string)($row['build'] ?? ''));
            if ($build !== '') {
                $label .= ' (' . $build . ')';
            }
            $items[] = ['id' => $id, 'label' => $label];
        }
        mysqli_stmt_close($stmt);
        return $items;
    }
}

if (!function_exists('itm_software_eol_ids_for_equipment')) {
    /**
     * @return array<int,int>
     */
    function itm_software_eol_ids_for_equipment(mysqli $conn, $companyId, $equipmentId)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        $ids = [];
        if ($companyId <= 0 || $equipmentId <= 0 || !itm_software_eol_table_has_column($conn, 'equipment_software', 'software_id')) {
            return $ids;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT software_id FROM equipment_software
             WHERE company_id = ? AND equipment_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return $ids;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['software_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        mysqli_stmt_close($stmt);
        return $ids;
    }
}

if (!function_exists('itm_software_eol_list_for_equipment')) {
    /**
     * Linked catalog rows for equipment view (inherited dates).
     *
     * @return array<int,array<string,mixed>>
     */
    function itm_software_eol_list_for_equipment(mysqli $conn, $companyId, $equipmentId)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        $rows = [];
        if ($companyId <= 0 || $equipmentId <= 0 || !itm_software_eol_tables_ready($conn)) {
            return $rows;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT s.id, s.name, s.build, s.eol_date, s.extended_date, s.esu_date
             FROM equipment_software es
             INNER JOIN software s ON s.id = es.software_id AND s.company_id = es.company_id
             WHERE es.company_id = ? AND es.equipment_id = ?
               AND es.deleted_at IS NULL AND es.active = 1
               AND s.deleted_at IS NULL
             ORDER BY s.name ASC'
        );
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_equipment_software_sync')) {
    /**
     * Replace live equipment_software links for one asset. Dates stay on software.
     *
     * @param array<int,int|string> $softwareIds
     * @return string empty on success, otherwise an error message
     */
    function itm_equipment_software_sync(mysqli $conn, $companyId, $equipmentId, array $softwareIds, $employeeId)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $equipmentId <= 0) {
            return 'Invalid equipment software sync scope.';
        }
        if (!itm_software_eol_table_has_column($conn, 'equipment_software', 'software_id')) {
            return '';
        }

        $wanted = itm_software_eol_normalize_id_list($softwareIds);
        $valid = [];
        if ($wanted !== []) {
            $placeholders = implode(',', array_fill(0, count($wanted), '?'));
            $types = 'i' . str_repeat('i', count($wanted));
            $sql = 'SELECT id FROM software WHERE company_id = ? AND deleted_at IS NULL AND id IN (' . $placeholders . ')';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return 'Unable to validate software catalog ids.';
            }
            $bind = array_merge([$companyId], $wanted);
            $refs = [];
            foreach ($bind as $i => $value) {
                $refs[$i] = &$bind[$i];
            }
            array_unshift($refs, $types);
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $valid[$id] = $id;
                }
            }
            mysqli_stmt_close($stmt);
        }
        $wanted = array_values($valid);

        $existing = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, software_id, deleted_at FROM equipment_software
             WHERE company_id = ? AND equipment_id = ?'
        );
        if (!$stmt) {
            return 'Unable to load equipment software links.';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $softwareId = (int)($row['software_id'] ?? 0);
            if ($softwareId > 0) {
                $existing[$softwareId] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        $wantedMap = array_fill_keys($wanted, true);
        $nowEmployee = $employeeId > 0 ? $employeeId : null;

        foreach ($existing as $softwareId => $row) {
            $linkId = (int)($row['id'] ?? 0);
            $isDeleted = trim((string)($row['deleted_at'] ?? '')) !== '';
            if (isset($wantedMap[$softwareId])) {
                if ($isDeleted && $linkId > 0) {
                    $upd = mysqli_prepare(
                        $conn,
                        'UPDATE equipment_software
                         SET active = 1, deleted_by = NULL, deleted_at = NULL, updated_by = ?
                         WHERE id = ? AND company_id = ?'
                    );
                    if (!$upd) {
                        return 'Unable to restore equipment software link.';
                    }
                    mysqli_stmt_bind_param($upd, 'iii', $nowEmployee, $linkId, $companyId);
                    if (!mysqli_stmt_execute($upd)) {
                        mysqli_stmt_close($upd);
                        return 'Unable to restore equipment software link.';
                    }
                    mysqli_stmt_close($upd);
                }
                unset($wantedMap[$softwareId]);
                continue;
            }
            if (!$isDeleted && $linkId > 0) {
                $upd = mysqli_prepare(
                    $conn,
                    'UPDATE equipment_software
                     SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ?
                     WHERE id = ? AND company_id = ?'
                );
                if (!$upd) {
                    return 'Unable to unlink equipment software.';
                }
                mysqli_stmt_bind_param($upd, 'iiii', $nowEmployee, $nowEmployee, $linkId, $companyId);
                if (!mysqli_stmt_execute($upd)) {
                    mysqli_stmt_close($upd);
                    return 'Unable to unlink equipment software.';
                }
                mysqli_stmt_close($upd);
            }
        }

        foreach (array_keys($wantedMap) as $softwareId) {
            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO equipment_software (company_id, equipment_id, software_id, active, created_by, updated_by)
                 VALUES (?, ?, ?, 1, ?, ?)'
            );
            if (!$ins) {
                return 'Unable to link equipment software.';
            }
            mysqli_stmt_bind_param($ins, 'iiiii', $companyId, $equipmentId, $softwareId, $nowEmployee, $nowEmployee);
            if (!mysqli_stmt_execute($ins)) {
                mysqli_stmt_close($ins);
                return 'Unable to link equipment software.';
            }
            mysqli_stmt_close($ins);
        }

        return '';
    }
}

if (!function_exists('itm_software_eol_append_calendar_events')) {
    /**
     * Mutates $eventsData keyed by Y-m-d. Catalog products emit one event each (not per asset).
     *
     * @param array<string,array<int,array<string,mixed>>> $eventsData
     */
    function itm_software_eol_append_calendar_events(mysqli $conn, $companyId, $startRange, $endRange, array &$eventsData)
    {
        $companyId = (int)$companyId;
        $startRange = (string)$startRange;
        $endRange = (string)$endRange;
        if ($companyId <= 0 || $startRange === '' || $endRange === '' || !itm_software_eol_tables_ready($conn)) {
            return;
        }

        $catalogKind = itm_software_eol_kind_meta();
        $hardwareKind = itm_software_eol_hardware_kind_meta();
        $dateCols = ['eol_date', 'extended_date', 'esu_date'];

        $pushCatalog = static function ($date, $title, $kind, $type, $id, $moduleHref) use (&$eventsData, $catalogKind) {
            $date = (string)$date;
            if ($date === '' || !isset($catalogKind[$kind])) {
                return;
            }
            $meta = $catalogKind[$kind];
            $eventsData[$date][] = [
                'type' => $type,
                'title' => $meta['label'] . ': ' . $title,
                'color' => $meta['color'],
                'icon' => $meta['icon'],
                'id' => (int)$id,
                'href' => $moduleHref,
            ];
        };

        $catalogSources = [];
        if (function_exists('has_module_access') && has_module_access($conn, $companyId, 'software')) {
            $catalogSources[] = [
                'sql' => 'SELECT id, name, eol_date, extended_date, esu_date FROM software
                          WHERE company_id = ? AND deleted_at IS NULL
                            AND ((eol_date BETWEEN ? AND ?) OR (extended_date BETWEEN ? AND ?) OR (esu_date BETWEEN ? AND ?))',
                'type' => 'software_eol',
                'href' => '../software/view.php?id=',
            ];
        }
        if (function_exists('has_module_access') && has_module_access($conn, $companyId, 'workstation_office')) {
            $catalogSources[] = [
                'sql' => 'SELECT id, name, eol_date, extended_date, esu_date FROM workstation_office
                          WHERE company_id = ? AND deleted_at IS NULL
                            AND ((eol_date BETWEEN ? AND ?) OR (extended_date BETWEEN ? AND ?) OR (esu_date BETWEEN ? AND ?))',
                'type' => 'office_eol',
                'href' => '../workstation_office/view.php?id=',
            ];
        }
        if (function_exists('has_module_access') && has_module_access($conn, $companyId, 'workstation_os_versions')) {
            $catalogSources[] = [
                'sql' => 'SELECT id, name, eol_date, extended_date, esu_date FROM workstation_os_versions
                          WHERE company_id = ? AND deleted_at IS NULL
                            AND ((eol_date BETWEEN ? AND ?) OR (extended_date BETWEEN ? AND ?) OR (esu_date BETWEEN ? AND ?))',
                'type' => 'os_eol',
                'href' => '../workstation_os_versions/view.php?id=',
            ];
        }

        foreach ($catalogSources as $source) {
            $stmt = mysqli_prepare($conn, $source['sql']);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'issssss',
                $companyId,
                $startRange,
                $endRange,
                $startRange,
                $endRange,
                $startRange,
                $endRange
            );
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $title = trim((string)($row['name'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $id = (int)($row['id'] ?? 0);
                foreach ($dateCols as $col) {
                    $d = trim((string)($row[$col] ?? ''));
                    if ($d >= $startRange && $d <= $endRange) {
                        $pushCatalog($d, $title, $col, $source['type'], $id, $source['href'] . $id);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        if (!function_exists('has_module_access') || !has_module_access($conn, $companyId, 'equipment')) {
            return;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, eol_date, extended_date, esu_date FROM equipment
             WHERE company_id = ? AND deleted_at IS NULL
               AND ((eol_date BETWEEN ? AND ?) OR (extended_date BETWEEN ? AND ?) OR (esu_date BETWEEN ? AND ?))'
        );
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'issssss',
            $companyId,
            $startRange,
            $endRange,
            $startRange,
            $endRange,
            $startRange,
            $endRange
        );
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $title = trim((string)($row['name'] ?? ''));
            if ($title === '') {
                continue;
            }
            $id = (int)($row['id'] ?? 0);
            foreach ($dateCols as $col) {
                $d = trim((string)($row[$col] ?? ''));
                if ($d < $startRange || $d > $endRange || !isset($hardwareKind[$col])) {
                    continue;
                }
                $meta = $hardwareKind[$col];
                $eventsData[$d][] = [
                    'type' => 'equipment_eol',
                    'title' => $meta['label'] . ': ' . $title,
                    'color' => $meta['color'],
                    'icon' => $meta['icon'],
                    'id' => $id,
                    'href' => '../equipment/view.php?id=' . $id,
                ];
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_software_eol_equipment_join_sql')) {
    function itm_software_eol_equipment_join_sql()
    {
        return ' LEFT JOIN workstation_office wo ON wo.id = e.workstation_office_id AND wo.company_id = e.company_id AND wo.deleted_at IS NULL
                 LEFT JOIN workstation_os_versions wov ON wov.id = e.workstation_os_version_id AND wov.company_id = e.company_id AND wov.deleted_at IS NULL
                 LEFT JOIN equipment_software esw ON esw.equipment_id = e.id AND esw.company_id = e.company_id AND esw.deleted_at IS NULL AND esw.active = 1
                 LEFT JOIN software s ON s.id = esw.software_id AND s.company_id = e.company_id AND s.deleted_at IS NULL ';
    }
}

if (!function_exists('itm_software_eol_inherited_eol_predicate_sql')) {
    /**
     * True when hardware or inherited catalog eol_date is in [start, end] inclusive.
     */
    function itm_software_eol_inherited_eol_predicate_sql($startExpr, $endExpr)
    {
        $startExpr = (string)$startExpr;
        $endExpr = (string)$endExpr;
        return '(
            (e.eol_date IS NOT NULL AND e.eol_date >= ' . $startExpr . ' AND e.eol_date <= ' . $endExpr . ')
            OR (wo.eol_date IS NOT NULL AND wo.eol_date >= ' . $startExpr . ' AND wo.eol_date <= ' . $endExpr . ')
            OR (wov.eol_date IS NOT NULL AND wov.eol_date >= ' . $startExpr . ' AND wov.eol_date <= ' . $endExpr . ')
            OR (s.eol_date IS NOT NULL AND s.eol_date >= ' . $startExpr . ' AND s.eol_date <= ' . $endExpr . ')
        )';
    }
}

if (!function_exists('itm_software_eol_count_equipment_in_window')) {
    function itm_software_eol_count_equipment_in_window(mysqli $conn, $companyId, $days)
    {
        $companyId = (int)$companyId;
        $days = max(1, (int)$days);
        if ($companyId <= 0 || !itm_software_eol_tables_ready($conn)) {
            return 0;
        }
        $endExpr = 'DATE_ADD(CURDATE(), INTERVAL ? DAY)';
        $sql = 'SELECT COUNT(DISTINCT e.id) AS cnt
                FROM equipment e
                ' . itm_software_eol_equipment_join_sql() . '
                WHERE e.company_id = ?
                  AND e.deleted_at IS NULL
                  AND ' . itm_software_eol_inherited_eol_predicate_sql('CURDATE()', $endExpr);
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iiiii', $companyId, $days, $days, $days, $days);
        mysqli_stmt_execute($stmt);
        $count = 0;
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (is_array($row)) {
            $count = (int)($row['cnt'] ?? 0);
        }
        mysqli_stmt_close($stmt);
        return $count;
    }
}

if (!function_exists('itm_software_eol_count_equipment_on_date')) {
    function itm_software_eol_count_equipment_on_date(mysqli $conn, $companyId, $date)
    {
        $companyId = (int)$companyId;
        $date = (string)$date;
        if ($companyId <= 0 || $date === '' || !itm_software_eol_tables_ready($conn)) {
            return 0;
        }
        $sql = 'SELECT COUNT(DISTINCT e.id) AS cnt
                FROM equipment e
                ' . itm_software_eol_equipment_join_sql() . '
                WHERE e.company_id = ?
                  AND e.deleted_at IS NULL
                  AND (
                    e.eol_date = ?
                    OR wo.eol_date = ?
                    OR wov.eol_date = ?
                    OR s.eol_date = ?
                  )';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'issss', $companyId, $date, $date, $date, $date);
        mysqli_stmt_execute($stmt);
        $count = 0;
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (is_array($row)) {
            $count = (int)($row['cnt'] ?? 0);
        }
        mysqli_stmt_close($stmt);
        return $count;
    }
}

if (!function_exists('itm_software_eol_email_rows')) {
    /**
     * Distinct equipment whose hardware or inherited catalog eol_date is in [today, cutoff].
     *
     * @return array<int,array<string,mixed>>
     */
    function itm_software_eol_email_rows(mysqli $conn, $companyId, $today, $cutoff)
    {
        $companyId = (int)$companyId;
        $today = (string)$today;
        $cutoff = (string)$cutoff;
        $rows = [];
        if ($companyId <= 0 || $today === '' || $cutoff === '' || !itm_software_eol_tables_ready($conn)) {
            return $rows;
        }
        $sql = 'SELECT e.id, e.name, e.hostname, e.assigned_to_employee_id,
                       e.eol_date AS hardware_eol,
                       wo.eol_date AS office_eol,
                       wov.eol_date AS os_eol,
                       MIN(s.eol_date) AS software_eol
                FROM equipment e
                ' . itm_software_eol_equipment_join_sql() . '
                WHERE e.company_id = ?
                  AND e.deleted_at IS NULL
                  AND e.active = 1
                  AND ' . itm_software_eol_inherited_eol_predicate_sql('?', '?') . '
                GROUP BY e.id, e.name, e.hostname, e.assigned_to_employee_id, e.eol_date, wo.eol_date, wov.eol_date
                ORDER BY e.name ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'issssssss',
            $companyId,
            $today,
            $cutoff,
            $today,
            $cutoff,
            $today,
            $cutoff,
            $today,
            $cutoff
        );
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $candidates = [
                trim((string)($row['hardware_eol'] ?? '')),
                trim((string)($row['office_eol'] ?? '')),
                trim((string)($row['os_eol'] ?? '')),
                trim((string)($row['software_eol'] ?? '')),
            ];
            $soonest = '';
            foreach ($candidates as $candidate) {
                if ($candidate === '' || $candidate < $today || $candidate > $cutoff) {
                    continue;
                }
                if ($soonest === '' || $candidate < $soonest) {
                    $soonest = $candidate;
                }
            }
            $row['eol_date'] = $soonest;
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_software_eol_expiring_hardware_rows')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_software_eol_expiring_hardware_rows(mysqli $conn, $companyId, $dateColumn)
    {
        $companyId = (int)$companyId;
        $allowed = ['eol_date', 'extended_date', 'esu_date'];
        if ($companyId <= 0 || !in_array($dateColumn, $allowed, true) || !itm_software_eol_table_has_column($conn, 'equipment', $dateColumn)) {
            return [];
        }
        $sql = 'SELECT e.id, e.name, e.hostname, e.model, e.serial_number, e.purchase_date,
                       e.`' . $dateColumn . '` AS expiry_date, et.name AS equipment_type, \'\' AS warranty_type
                FROM equipment e
                LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
                WHERE e.company_id = ?
                  AND e.deleted_at IS NULL
                  AND e.`' . $dateColumn . '` IS NOT NULL
                  AND e.`' . $dateColumn . '` >= \'1000-01-01\'
                ORDER BY e.`' . $dateColumn . '` ASC, e.name ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_software_eol_expiring_catalog_rows')) {
    /**
     * One row per catalog product with the given date filled, plus affected live equipment count.
     *
     * @return array<int,array<string,mixed>>
     */
    function itm_software_eol_expiring_catalog_rows(mysqli $conn, $companyId, $dateColumn)
    {
        $companyId = (int)$companyId;
        $allowed = ['eol_date', 'extended_date', 'esu_date'];
        $rows = [];
        if ($companyId <= 0 || !in_array($dateColumn, $allowed, true) || !itm_software_eol_tables_ready($conn)) {
            return $rows;
        }

        $sources = [
            [
                'sql' => 'SELECT wo.id, wo.name, wo.build, wo.`' . $dateColumn . '` AS expiry_date,
                                 (SELECT COUNT(*) FROM equipment e
                                  WHERE e.company_id = wo.company_id
                                    AND e.workstation_office_id = wo.id
                                    AND e.deleted_at IS NULL) AS affected_count,
                                 \'Office\' AS catalog_kind
                          FROM workstation_office wo
                          WHERE wo.company_id = ? AND wo.deleted_at IS NULL
                            AND wo.`' . $dateColumn . '` IS NOT NULL
                            AND wo.`' . $dateColumn . '` >= \'1000-01-01\'',
                'href' => '../workstation_office/view.php?id=',
            ],
            [
                'sql' => 'SELECT wov.id, wov.name, wov.build, wov.`' . $dateColumn . '` AS expiry_date,
                                 (SELECT COUNT(*) FROM equipment e
                                  WHERE e.company_id = wov.company_id
                                    AND e.workstation_os_version_id = wov.id
                                    AND e.deleted_at IS NULL) AS affected_count,
                                 \'OS version\' AS catalog_kind
                          FROM workstation_os_versions wov
                          WHERE wov.company_id = ? AND wov.deleted_at IS NULL
                            AND wov.`' . $dateColumn . '` IS NOT NULL
                            AND wov.`' . $dateColumn . '` >= \'1000-01-01\'',
                'href' => '../workstation_os_versions/view.php?id=',
            ],
            [
                'sql' => 'SELECT s.id, s.name, s.build, s.`' . $dateColumn . '` AS expiry_date,
                                 (SELECT COUNT(*) FROM equipment_software es
                                  INNER JOIN equipment e ON e.id = es.equipment_id AND e.company_id = es.company_id
                                  WHERE es.company_id = s.company_id
                                    AND es.software_id = s.id
                                    AND es.deleted_at IS NULL AND es.active = 1
                                    AND e.deleted_at IS NULL) AS affected_count,
                                 \'Software\' AS catalog_kind
                          FROM software s
                          WHERE s.company_id = ? AND s.deleted_at IS NULL
                            AND s.`' . $dateColumn . '` IS NOT NULL
                            AND s.`' . $dateColumn . '` >= \'1000-01-01\'',
                'href' => '../software/view.php?id=',
            ],
        ];

        foreach ($sources as $source) {
            $stmt = mysqli_prepare($conn, $source['sql']);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $row['view_href'] = $source['href'] . (int)($row['id'] ?? 0);
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        usort($rows, static function ($a, $b) {
            $da = (string)($a['expiry_date'] ?? '');
            $db = (string)($b['expiry_date'] ?? '');
            if ($da === $db) {
                return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            }
            return $da < $db ? -1 : 1;
        });

        return $rows;
    }
}

if (!function_exists('itm_software_eol_forecast_month_counts')) {
    /**
     * Distinct equipment whose hardware or inherited catalog eol_date falls in the next 6 months, grouped by month number.
     *
     * @param array<int,int> $monthKeys
     * @return array<int,int>
     */
    function itm_software_eol_forecast_month_counts(mysqli $conn, $companyId, array $monthKeys)
    {
        $companyId = (int)$companyId;
        $counts = array_fill_keys($monthKeys, 0);
        if ($companyId <= 0 || $monthKeys === [] || !itm_software_eol_tables_ready($conn)) {
            return $counts;
        }
        $sql = 'SELECT MONTH(src.eol_date) AS m, COUNT(DISTINCT src.equipment_id) AS cnt
                FROM (
                    SELECT e.id AS equipment_id, e.eol_date AS eol_date
                    FROM equipment e
                    WHERE e.company_id = ? AND e.deleted_at IS NULL AND e.eol_date IS NOT NULL
                    UNION ALL
                    SELECT e.id, wo.eol_date
                    FROM equipment e
                    INNER JOIN workstation_office wo ON wo.id = e.workstation_office_id AND wo.company_id = e.company_id
                    WHERE e.company_id = ? AND e.deleted_at IS NULL AND wo.deleted_at IS NULL AND wo.eol_date IS NOT NULL
                    UNION ALL
                    SELECT e.id, wov.eol_date
                    FROM equipment e
                    INNER JOIN workstation_os_versions wov ON wov.id = e.workstation_os_version_id AND wov.company_id = e.company_id
                    WHERE e.company_id = ? AND e.deleted_at IS NULL AND wov.deleted_at IS NULL AND wov.eol_date IS NOT NULL
                    UNION ALL
                    SELECT e.id, s.eol_date
                    FROM equipment e
                    INNER JOIN equipment_software esw ON esw.equipment_id = e.id AND esw.company_id = e.company_id
                      AND esw.deleted_at IS NULL AND esw.active = 1
                    INNER JOIN software s ON s.id = esw.software_id AND s.company_id = e.company_id AND s.deleted_at IS NULL
                    WHERE e.company_id = ? AND e.deleted_at IS NULL AND s.eol_date IS NOT NULL
                ) src
                WHERE src.eol_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY m';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $counts;
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $companyId, $companyId, $companyId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $m = (int)($row['m'] ?? 0);
            if (isset($counts[$m])) {
                $counts[$m] = (int)($row['cnt'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);
        return $counts;
    }
}
