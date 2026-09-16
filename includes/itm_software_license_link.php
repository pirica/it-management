<?php
/**
 * Bidirectional links between tenant software catalog rows and license_management records.
 */

if (!function_exists('itm_software_license_tables_ready')) {
    function itm_software_license_tables_ready(mysqli $conn)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'software_license_links'");
        $ready = ($res && mysqli_num_rows($res) === 1);
        if ($res) {
            mysqli_free_result($res);
        }
        return $ready;
    }
}

if (!function_exists('itm_software_license_normalize_id_list')) {
    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    function itm_software_license_normalize_id_list($raw)
    {
        if (function_exists('itm_software_eol_normalize_id_list')) {
            return itm_software_eol_normalize_id_list($raw);
        }
        if (!is_array($raw)) {
            $raw = ($raw === null || $raw === '') ? [] : [$raw];
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

if (!function_exists('itm_software_license_license_options')) {
    /**
     * @return array<int,array{id:int,label:string}>
     */
    function itm_software_license_license_options(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        $items = [];
        if ($companyId <= 0 || !itm_software_license_tables_ready($conn)) {
            return $items;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, license_key
             FROM license_management
             WHERE company_id = ? AND deleted_at IS NULL
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
            $key = trim((string)($row['license_key'] ?? ''));
            if ($key !== '') {
                $label .= ' [' . $key . ']';
            }
            $items[] = ['id' => $id, 'label' => $label];
        }
        mysqli_stmt_close($stmt);
        return $items;
    }
}

if (!function_exists('itm_software_license_software_options')) {
    /**
     * @return array<int,array{id:int,label:string}>
     */
    function itm_software_license_software_options(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return [];
        }
        if (function_exists('itm_software_eol_catalog_options')) {
            return itm_software_eol_catalog_options($conn, $companyId);
        }
        $items = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, build
             FROM software
             WHERE company_id = ? AND deleted_at IS NULL
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

if (!function_exists('itm_software_license_ids_for_software')) {
    /**
     * @return array<int,int>
     */
    function itm_software_license_ids_for_software(mysqli $conn, $companyId, $softwareId)
    {
        $companyId = (int)$companyId;
        $softwareId = (int)$softwareId;
        $ids = [];
        if ($companyId <= 0 || $softwareId <= 0 || !itm_software_license_tables_ready($conn)) {
            return $ids;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT license_management_id
             FROM software_license_links
             WHERE company_id = ? AND software_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return $ids;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $softwareId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['license_management_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        mysqli_stmt_close($stmt);
        return $ids;
    }
}

if (!function_exists('itm_software_license_ids_for_license')) {
    /**
     * @return array<int,int>
     */
    function itm_software_license_ids_for_license(mysqli $conn, $companyId, $licenseId)
    {
        $companyId = (int)$companyId;
        $licenseId = (int)$licenseId;
        $ids = [];
        if ($companyId <= 0 || $licenseId <= 0 || !itm_software_license_tables_ready($conn)) {
            return $ids;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT software_id
             FROM software_license_links
             WHERE company_id = ? AND license_management_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return $ids;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $licenseId);
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

if (!function_exists('itm_software_license_list_for_software')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_software_license_list_for_software(mysqli $conn, $companyId, $softwareId)
    {
        $companyId = (int)$companyId;
        $softwareId = (int)$softwareId;
        $rows = [];
        if ($companyId <= 0 || $softwareId <= 0 || !itm_software_license_tables_ready($conn)) {
            return $rows;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT lm.id, lm.name, lm.license_key, lm.expiry_date, lm.quantity, lm.active
             FROM software_license_links sll
             INNER JOIN license_management lm
               ON lm.id = sll.license_management_id AND lm.company_id = sll.company_id
             WHERE sll.company_id = ? AND sll.software_id = ?
               AND sll.deleted_at IS NULL AND sll.active = 1
               AND lm.deleted_at IS NULL
             ORDER BY lm.name ASC'
        );
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $softwareId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_software_license_list_for_license')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_software_license_list_for_license(mysqli $conn, $companyId, $licenseId)
    {
        $companyId = (int)$companyId;
        $licenseId = (int)$licenseId;
        $rows = [];
        if ($companyId <= 0 || $licenseId <= 0 || !itm_software_license_tables_ready($conn)) {
            return $rows;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT s.id, s.name, s.build, s.eol_date, s.extended_date, s.esu_date, s.active
             FROM software_license_links sll
             INNER JOIN software s ON s.id = sll.software_id AND s.company_id = sll.company_id
             WHERE sll.company_id = ? AND sll.license_management_id = ?
               AND sll.deleted_at IS NULL AND sll.active = 1
               AND s.deleted_at IS NULL
             ORDER BY s.name ASC'
        );
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $licenseId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_software_license_list_equipment')) {
    /**
     * Equipment that has catalog software installed, optionally filtered by software_id.
     * License names come from software_license_links when present.
     *
     * @return array<int,array<string,mixed>>
     */
    function itm_software_license_list_equipment(mysqli $conn, $companyId, $softwareId = 0)
    {
        $companyId = (int)$companyId;
        $softwareId = (int)$softwareId;
        $grouped = [];
        if ($companyId <= 0) {
            return [];
        }
        $hasEquipmentSoftware = function_exists('itm_software_eol_table_has_column')
            ? itm_software_eol_table_has_column($conn, 'equipment_software', 'software_id')
            : true;
        if (!$hasEquipmentSoftware) {
            return [];
        }

        $sql = 'SELECT e.id, e.name, e.hostname, e.serial_number, e.status_id, e.assigned_to_employee_id,
                    COALESCE(st.name, \'\') AS status_name,
                    TRIM(CONCAT(IFNULL(emp.first_name, \'\'), \' \', IFNULL(emp.last_name, \'\'))) AS assignee_full_name,
                    IFNULL(emp.username, \'\') AS assignee_username,
                    s.id AS software_id, s.name AS software_name, s.build AS software_build,
                    lm.id AS license_id, lm.name AS license_name
             FROM equipment e
             INNER JOIN equipment_software esw
                ON esw.equipment_id = e.id AND esw.company_id = e.company_id
                AND esw.deleted_at IS NULL AND esw.active = 1
             INNER JOIN software s
                ON s.id = esw.software_id AND s.company_id = e.company_id AND s.deleted_at IS NULL
             LEFT JOIN equipment_statuses st
                ON st.id = e.status_id AND st.company_id = e.company_id
             LEFT JOIN employees emp ON emp.id = e.assigned_to_employee_id
             LEFT JOIN software_license_links sll
                ON sll.software_id = s.id AND sll.company_id = e.company_id
                AND sll.deleted_at IS NULL AND sll.active = 1
             LEFT JOIN license_management lm
                ON lm.id = sll.license_management_id AND lm.company_id = e.company_id
                AND lm.deleted_at IS NULL
             WHERE e.company_id = ? AND e.deleted_at IS NULL
               AND (? = 0 OR esw.software_id = ?)
             ORDER BY e.name ASC, s.name ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $softwareId, $softwareId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $eid = (int)($row['id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            if (!isset($grouped[$eid])) {
                $fullName = trim((string)($row['assignee_full_name'] ?? ''));
                $username = trim((string)($row['assignee_username'] ?? ''));
                $grouped[$eid] = [
                    'id' => $eid,
                    'name' => (string)($row['name'] ?? ''),
                    'hostname' => (string)($row['hostname'] ?? ''),
                    'serial_number' => (string)($row['serial_number'] ?? ''),
                    'status_id' => (int)($row['status_id'] ?? 0),
                    'status_name' => (string)($row['status_name'] ?? ''),
                    'assignee_label' => $fullName !== '' ? $fullName : $username,
                    'software' => [],
                    'licenses' => [],
                ];
            }
            $sid = (int)($row['software_id'] ?? 0);
            if ($sid > 0 && !isset($grouped[$eid]['software'][$sid])) {
                $grouped[$eid]['software'][$sid] = [
                    'id' => $sid,
                    'name' => (string)($row['software_name'] ?? ''),
                    'build' => (string)($row['software_build'] ?? ''),
                ];
            }
            $lid = (int)($row['license_id'] ?? 0);
            if ($lid > 0 && !isset($grouped[$eid]['licenses'][$lid])) {
                $grouped[$eid]['licenses'][$lid] = [
                    'id' => $lid,
                    'name' => (string)($row['license_name'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmt);

        $out = [];
        foreach ($grouped as $item) {
            $item['software'] = array_values($item['software']);
            $item['licenses'] = array_values($item['licenses']);
            $out[] = $item;
        }
        return $out;
    }
}

if (!function_exists('itm_software_license_sync_for_software')) {
    /**
     * @param array<int,int> $licenseIds
     */
    function itm_software_license_sync_for_software(mysqli $conn, $companyId, $softwareId, array $licenseIds, $employeeId)
    {
        return itm_software_license_sync_links(
            $conn,
            (int)$companyId,
            (int)$softwareId,
            itm_software_license_normalize_id_list($licenseIds),
            (int)$employeeId,
            'software'
        );
    }
}

if (!function_exists('itm_software_license_sync_for_license')) {
    /**
     * @param array<int,int> $softwareIds
     */
    function itm_software_license_sync_for_license(mysqli $conn, $companyId, $licenseId, array $softwareIds, $employeeId)
    {
        return itm_software_license_sync_links(
            $conn,
            (int)$companyId,
            (int)$licenseId,
            itm_software_license_normalize_id_list($softwareIds),
            (int)$employeeId,
            'license'
        );
    }
}

if (!function_exists('itm_software_license_sync_links')) {
    /**
     * @param array<int,int> $peerIds
     */
    function itm_software_license_sync_links(mysqli $conn, $companyId, $anchorId, array $peerIds, $employeeId, $anchorSide)
    {
        $companyId = (int)$companyId;
        $anchorId = (int)$anchorId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $anchorId <= 0 || !itm_software_license_tables_ready($conn)) {
            return '';
        }
        if ($anchorSide !== 'software' && $anchorSide !== 'license') {
            return 'Invalid software license link anchor.';
        }

        $wanted = [];
        if (!empty($peerIds)) {
            $placeholders = implode(',', array_fill(0, count($peerIds), '?'));
            $types = 'i' . str_repeat('i', count($peerIds));
            $bind = array_merge([$companyId], $peerIds);
            if ($anchorSide === 'software') {
                $sql = 'SELECT id FROM license_management WHERE company_id = ? AND deleted_at IS NULL AND id IN (' . $placeholders . ')';
            } else {
                $sql = 'SELECT id FROM software WHERE company_id = ? AND deleted_at IS NULL AND id IN (' . $placeholders . ')';
            }
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return 'Unable to validate software license link targets.';
            }
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
                    $wanted[$id] = $id;
                }
            }
            mysqli_stmt_close($stmt);
        }
        $wanted = array_values($wanted);

        if ($anchorSide === 'software') {
            $whereSql = 'WHERE company_id = ? AND software_id = ?';
            $peerColumn = 'license_management_id';
        } else {
            $whereSql = 'WHERE company_id = ? AND license_management_id = ?';
            $peerColumn = 'software_id';
        }

        $existing = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, ' . $peerColumn . ' AS peer_id, deleted_at
             FROM software_license_links ' . $whereSql
        );
        if (!$stmt) {
            return 'Unable to load software license links.';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $anchorId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $peerId = (int)($row['peer_id'] ?? 0);
            if ($peerId > 0) {
                $existing[$peerId] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        $wantedMap = array_fill_keys($wanted, true);
        $nowEmployee = $employeeId > 0 ? $employeeId : null;

        foreach ($existing as $peerId => $row) {
            $linkId = (int)($row['id'] ?? 0);
            $isDeleted = trim((string)($row['deleted_at'] ?? '')) !== '';
            if (isset($wantedMap[$peerId])) {
                if ($isDeleted && $linkId > 0) {
                    $upd = mysqli_prepare(
                        $conn,
                        'UPDATE software_license_links
                         SET active = 1, deleted_by = NULL, deleted_at = NULL, updated_by = ?
                         WHERE id = ? AND company_id = ?'
                    );
                    if (!$upd) {
                        return 'Unable to restore software license link.';
                    }
                    mysqli_stmt_bind_param($upd, 'iii', $nowEmployee, $linkId, $companyId);
                    if (!mysqli_stmt_execute($upd)) {
                        mysqli_stmt_close($upd);
                        return 'Unable to restore software license link.';
                    }
                    mysqli_stmt_close($upd);
                }
                unset($wantedMap[$peerId]);
                continue;
            }
            if (!$isDeleted && $linkId > 0) {
                $upd = mysqli_prepare(
                    $conn,
                    'UPDATE software_license_links
                     SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ?
                     WHERE id = ? AND company_id = ?'
                );
                if (!$upd) {
                    return 'Unable to unlink software license row.';
                }
                mysqli_stmt_bind_param($upd, 'iiii', $nowEmployee, $nowEmployee, $linkId, $companyId);
                if (!mysqli_stmt_execute($upd)) {
                    mysqli_stmt_close($upd);
                    return 'Unable to unlink software license row.';
                }
                mysqli_stmt_close($upd);
            }
        }

        foreach (array_keys($wantedMap) as $peerId) {
            if ($anchorSide === 'software') {
                $ins = mysqli_prepare(
                    $conn,
                    'INSERT INTO software_license_links
                     (company_id, software_id, license_management_id, active, created_by, updated_by)
                     VALUES (?, ?, ?, 1, ?, ?)'
                );
                if (!$ins) {
                    return 'Unable to link software license row.';
                }
                mysqli_stmt_bind_param($ins, 'iiiii', $companyId, $anchorId, $peerId, $nowEmployee, $nowEmployee);
            } else {
                $ins = mysqli_prepare(
                    $conn,
                    'INSERT INTO software_license_links
                     (company_id, software_id, license_management_id, active, created_by, updated_by)
                     VALUES (?, ?, ?, 1, ?, ?)'
                );
                if (!$ins) {
                    return 'Unable to link software license row.';
                }
                mysqli_stmt_bind_param($ins, 'iiiii', $companyId, $peerId, $anchorId, $nowEmployee, $nowEmployee);
            }
            if (!mysqli_stmt_execute($ins)) {
                mysqli_stmt_close($ins);
                return 'Unable to link software license row.';
            }
            mysqli_stmt_close($ins);
        }

        return '';
    }
}
