<?php
/**
 * Command-palette search_index (phase 2): denormalized FULLTEXT rows + CRUD sync.
 */

if (!function_exists('itm_search_index_table_ready')) {
    function itm_search_index_table_ready($conn)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        if (!($conn instanceof mysqli)) {
            $ready = false;
            return false;
        }

        $res = mysqli_query($conn, "SHOW TABLES LIKE 'search_index'");
        $ready = ($res && mysqli_num_rows($res) > 0);
        if ($res) {
            mysqli_free_result($res);
        }

        return $ready;
    }
}

if (!function_exists('itm_search_index_is_supported_module')) {
    function itm_search_index_is_supported_module($moduleSlug)
    {
        if (!function_exists('itm_command_palette_searchable_module_slugs')) {
            require_once __DIR__ . '/itm_command_palette_search.php';
        }

        return in_array(strtolower(trim((string)$moduleSlug)), itm_command_palette_searchable_module_slugs(), true);
    }
}

if (!function_exists('itm_search_index_truncate')) {
    function itm_search_index_truncate($value, $max)
    {
        $value = trim((string)$value);
        $max = (int)$max;
        if ($max <= 0 || $value === '') {
            return '';
        }
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, max(1, $max - 1)) . '…';
    }
}

if (!function_exists('itm_search_index_join_keywords')) {
    /**
     * @param string[] $parts
     */
    function itm_search_index_join_keywords(array $parts)
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $clean[$part] = $part;
            }
        }

        return implode(' ', array_values($clean));
    }
}

if (!function_exists('itm_search_index_build_fulltext_query')) {
    function itm_search_index_build_fulltext_query($query)
    {
        $query = trim((string)$query);
        if ($query === '') {
            return '';
        }

        $terms = preg_split('/\s+/u', $query) ?: [];
        $parts = [];
        foreach ($terms as $term) {
            $term = preg_replace('/[^\p{L}\p{N}@._:\/-]+/u', '', (string)$term);
            if ($term === '') {
                continue;
            }
            $parts[] = '+' . $term . '*';
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('itm_search_index_company_has_rows')) {
    function itm_search_index_company_has_rows($conn, $companyId, $moduleSlug = '')
    {
        if (!itm_search_index_table_ready($conn) || (int)$companyId <= 0) {
            return false;
        }

        $sql = 'SELECT 1 FROM search_index WHERE company_id = ?';
        $types = 'i';
        $params = [(int)$companyId];

        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($moduleSlug !== '') {
            $sql .= ' AND module_slug = ?';
            $types .= 's';
            $params[] = $moduleSlug;
        }
        $sql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $hasRow = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);

        return $hasRow;
    }
}

if (!function_exists('itm_search_index_remove')) {
    function itm_search_index_remove($conn, $companyId, $moduleSlug, $recordId)
    {
        if (!itm_search_index_table_ready($conn)) {
            return;
        }

        $companyId = (int)$companyId;
        $recordId = (int)$recordId;
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($companyId <= 0 || $recordId <= 0 || $moduleSlug === '') {
            return;
        }

        $stmt = mysqli_prepare(
            $conn,
            'DELETE FROM search_index WHERE company_id = ? AND module_slug = ? AND record_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return;
        }

        mysqli_stmt_bind_param($stmt, 'isi', $companyId, $moduleSlug, $recordId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_search_index_clear_company_module')) {
    function itm_search_index_clear_company_module($conn, $companyId, $moduleSlug)
    {
        if (!itm_search_index_table_ready($conn)) {
            return;
        }

        $companyId = (int)$companyId;
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($companyId <= 0 || $moduleSlug === '') {
            return;
        }

        $stmt = mysqli_prepare(
            $conn,
            'DELETE FROM search_index WHERE company_id = ? AND module_slug = ?'
        );
        if (!$stmt) {
            return;
        }

        mysqli_stmt_bind_param($stmt, 'is', $companyId, $moduleSlug);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_search_index_upsert')) {
    function itm_search_index_upsert($conn, $companyId, $moduleSlug, $recordId, $title, $subtitle, $keywords)
    {
        if (!itm_search_index_table_ready($conn)) {
            return false;
        }

        $companyId = (int)$companyId;
        $recordId = (int)$recordId;
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        $title = itm_search_index_truncate($title, 255);
        if ($companyId <= 0 || $recordId <= 0 || $moduleSlug === '' || $title === '') {
            return false;
        }

        $subtitleValue = itm_search_index_truncate($subtitle, 255);
        $keywordsValue = trim((string)$keywords);

        $sql = 'INSERT INTO search_index (company_id, module_slug, record_id, title, subtitle, keywords)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    subtitle = VALUES(subtitle),
                    keywords = VALUES(keywords),
                    updated_at = CURRENT_TIMESTAMP';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'isisss', $companyId, $moduleSlug, $recordId, $title, $subtitleValue, $keywordsValue);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_search_index_build_snapshot')) {
    /**
     * @return array{title: string, subtitle: string, keywords: string}|null
     */
    function itm_search_index_build_snapshot($conn, $moduleSlug, $companyId, $recordId)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0 || (int)$recordId <= 0) {
            return null;
        }

        $moduleSlug = strtolower(trim((string)$moduleSlug));
        switch ($moduleSlug) {
            case 'employees':
                require_once __DIR__ . '/itm_employees_hidden_accounts.php';
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT e.id, e.display_name, e.full_name, e.first_name, e.last_name, e.username,
                            e.work_email, e.personal_email, e.employee_code, e.external_id, e.mobile_phone, e.extension,
                            e.deleted_at, e.is_hidden,
                            d.name AS department_name, ep.name AS position_name
                     FROM employees e
                     LEFT JOIN departments d ON d.id = e.department_id
                     LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id
                     WHERE e.id = ? AND e.company_id = ? AND e.deleted_at IS NULL
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$row || itm_employees_is_hidden_account($row)) {
                    return null;
                }
                $title = trim((string)($row['display_name'] ?? ''));
                if ($title === '') {
                    $title = trim((string)($row['full_name'] ?? ''));
                }
                if ($title === '') {
                    $title = trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')));
                }
                if ($title === '') {
                    $title = trim((string)($row['username'] ?? ''));
                }
                if ($title === '') {
                    $title = 'Employee #' . (int)$recordId;
                }
                return [
                    'title' => $title,
                    'subtitle' => itm_search_index_join_keywords([
                        (string)($row['department_name'] ?? ''),
                        (string)($row['position_name'] ?? ''),
                    ]),
                    'keywords' => itm_search_index_join_keywords([
                        (string)($row['username'] ?? ''),
                        (string)($row['work_email'] ?? ''),
                        (string)($row['personal_email'] ?? ''),
                        (string)($row['employee_code'] ?? ''),
                        (string)($row['external_id'] ?? ''),
                        (string)($row['mobile_phone'] ?? ''),
                        (string)($row['extension'] ?? ''),
                    ]),
                ];

            case 'equipment':
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT e.id, e.name, e.hostname, e.serial_number, e.model, e.ip_address, e.mac_address, e.deleted_at,
                            et.name AS equipment_type_name, es.name AS status_name, m.name AS manufacturer_name
                     FROM equipment e
                     LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
                     LEFT JOIN equipment_statuses es ON es.id = e.status_id
                     LEFT JOIN manufacturers m ON m.id = e.manufacturer_id
                     WHERE e.id = ? AND e.company_id = ? AND e.deleted_at IS NULL
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$row) {
                    return null;
                }
                $title = trim((string)($row['name'] ?? ''));
                if ($title === '') {
                    $title = trim((string)($row['hostname'] ?? ''));
                }
                if ($title === '') {
                    $title = 'Equipment #' . (int)$recordId;
                }
                return [
                    'title' => $title,
                    'subtitle' => itm_search_index_join_keywords([
                        (string)($row['equipment_type_name'] ?? ''),
                        (string)($row['hostname'] ?? ''),
                        (string)($row['ip_address'] ?? ''),
                        (string)($row['status_name'] ?? ''),
                    ]),
                    'keywords' => itm_search_index_join_keywords([
                        (string)($row['serial_number'] ?? ''),
                        (string)($row['model'] ?? ''),
                        (string)($row['mac_address'] ?? ''),
                        (string)($row['manufacturer_name'] ?? ''),
                    ]),
                ];

            case 'tickets':
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT t.id, t.ticket_external_code, t.title, t.deleted_at,
                            ts.name AS status_name, tp.name AS priority_name
                     FROM tickets t
                     LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                     LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
                     WHERE t.id = ? AND t.company_id = ? AND t.deleted_at IS NULL
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$row) {
                    return null;
                }
                $title = trim((string)($row['title'] ?? ''));
                if ($title === '') {
                    $title = 'Ticket #' . (int)$recordId;
                }
                return [
                    'title' => $title,
                    'subtitle' => itm_search_index_join_keywords([
                        (string)($row['ticket_external_code'] ?? ''),
                        (string)($row['status_name'] ?? ''),
                        (string)($row['priority_name'] ?? ''),
                    ]),
                    'keywords' => (string)($row['ticket_external_code'] ?? ''),
                ];

            case 'ip_addresses':
                if (!function_exists('itm_ipam_equipment_label_from_row')) {
                    require_once __DIR__ . '/ipam_helpers.php';
                }
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT ia.id, ia.ip_text, ia.hostname, ia.notes, ia.status, ia.deleted_at,
                            s.cidr AS subnet_cidr,
                            COALESCE(e_fk.name, e_ip.name, \'\') AS equipment_name,
                            COALESCE(e_fk.hostname, e_ip.hostname, \'\') AS equipment_hostname
                     FROM ip_addresses ia
                     INNER JOIN ip_subnets s ON s.id = ia.subnet_id AND s.company_id = ia.company_id
                     LEFT JOIN equipment e_fk ON e_fk.id = ia.equipment_id AND e_fk.company_id = ia.company_id
                     LEFT JOIN equipment e_ip ON e_ip.company_id = ia.company_id
                        AND e_ip.ip_address = ia.ip_text AND e_ip.deleted_at IS NULL
                     WHERE ia.id = ? AND ia.company_id = ? AND ia.deleted_at IS NULL
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$row) {
                    return null;
                }
                $title = trim((string)($row['ip_text'] ?? ''));
                if ($title === '') {
                    $title = 'IP #' . (int)$recordId;
                }
                $equipmentLabel = trim((string)($row['equipment_name'] ?? ''));
                if ($equipmentLabel === '') {
                    $equipmentLabel = trim((string)($row['equipment_hostname'] ?? ''));
                }
                return [
                    'title' => $title,
                    'subtitle' => itm_search_index_join_keywords([
                        (string)($row['subnet_cidr'] ?? ''),
                        (string)($row['hostname'] ?? ''),
                        $equipmentLabel,
                        (string)($row['status'] ?? ''),
                    ]),
                    'keywords' => itm_search_index_join_keywords([
                        (string)($row['notes'] ?? ''),
                        $equipmentLabel,
                    ]),
                ];

            case 'catalogs':
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT c.id, c.model, c.price, c.deleted_at,
                            et.name AS equipment_type_name,
                            s.name AS supplier_name, m.name AS manufacturer_name
                     FROM catalogs c
                     LEFT JOIN equipment_types et ON et.id = c.equipment_type_id
                     LEFT JOIN suppliers s ON s.id = c.supplier_id AND s.company_id = c.company_id
                     LEFT JOIN manufacturers m ON m.id = c.manufacturer_id
                     WHERE c.id = ? AND c.company_id = ? AND c.deleted_at IS NULL
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$row) {
                    return null;
                }
                $title = trim((string)($row['model'] ?? ''));
                if ($title === '') {
                    $title = 'Catalog #' . (int)$recordId;
                }
                $price = isset($row['price']) && $row['price'] !== null && $row['price'] !== ''
                    ? (string)$row['price']
                    : '';
                return [
                    'title' => $title,
                    'subtitle' => itm_search_index_join_keywords([
                        (string)($row['manufacturer_name'] ?? ''),
                        (string)($row['supplier_name'] ?? ''),
                        (string)($row['equipment_type_name'] ?? ''),
                        $price,
                    ]),
                    'keywords' => itm_search_index_join_keywords([
                        (string)($row['manufacturer_name'] ?? ''),
                        (string)($row['supplier_name'] ?? ''),
                    ]),
                ];
        }

        return null;
    }
}

if (!function_exists('itm_search_index_sync_record')) {
    function itm_search_index_sync_record($conn, $moduleSlug, $companyId, $recordId)
    {
        if (!itm_search_index_is_supported_module($moduleSlug)) {
            return false;
        }

        $snapshot = itm_search_index_build_snapshot($conn, $moduleSlug, (int)$companyId, (int)$recordId);
        if ($snapshot === null) {
            itm_search_index_remove($conn, (int)$companyId, $moduleSlug, (int)$recordId);
            return false;
        }

        return itm_search_index_upsert(
            $conn,
            (int)$companyId,
            $moduleSlug,
            (int)$recordId,
            $snapshot['title'],
            $snapshot['subtitle'],
            $snapshot['keywords']
        );
    }
}

if (!function_exists('itm_search_index_after_module_save')) {
    function itm_search_index_after_module_save($conn, $moduleSlug, $companyId, $recordId)
    {
        if ((int)$recordId <= 0) {
            return;
        }
        itm_search_index_sync_record($conn, $moduleSlug, (int)$companyId, (int)$recordId);
    }
}

if (!function_exists('itm_search_index_after_module_delete')) {
    function itm_search_index_after_module_delete($conn, $moduleSlug, $companyId, $recordId)
    {
        if (!itm_search_index_is_supported_module($moduleSlug)) {
            return;
        }
        itm_search_index_remove($conn, (int)$companyId, $moduleSlug, (int)$recordId);
    }
}

if (!function_exists('itm_search_index_after_module_clear')) {
    function itm_search_index_after_module_clear($conn, $moduleSlug, $companyId)
    {
        if (!itm_search_index_is_supported_module($moduleSlug)) {
            return;
        }
        itm_search_index_clear_company_module($conn, (int)$companyId, $moduleSlug);
    }
}

if (!function_exists('itm_search_index_list_source_record_ids')) {
    /**
     * @return int[]
     */
    function itm_search_index_list_source_record_ids($conn, $moduleSlug, $companyId)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0) {
            return [];
        }

        $moduleSlug = strtolower(trim((string)$moduleSlug));
        $sql = '';
        switch ($moduleSlug) {
            case 'employees':
                $sql = 'SELECT id FROM employees WHERE company_id = ' . (int)$companyId
                    . ' AND deleted_at IS NULL AND is_hidden = 0 ORDER BY id ASC';
                break;
            case 'equipment':
                $sql = 'SELECT id FROM equipment WHERE company_id = ' . (int)$companyId
                    . ' AND deleted_at IS NULL ORDER BY id ASC';
                break;
            case 'tickets':
                $sql = 'SELECT id FROM tickets WHERE company_id = ' . (int)$companyId
                    . ' AND deleted_at IS NULL ORDER BY id ASC';
                break;
            case 'ip_addresses':
                $sql = 'SELECT id FROM ip_addresses WHERE company_id = ' . (int)$companyId
                    . ' AND deleted_at IS NULL ORDER BY id ASC';
                break;
            case 'catalogs':
                $sql = 'SELECT id FROM catalogs WHERE company_id = ' . (int)$companyId
                    . ' AND deleted_at IS NULL ORDER BY id ASC';
                break;
            default:
                return [];
        }

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return [];
        }

        $ids = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        mysqli_free_result($res);

        return $ids;
    }
}

if (!function_exists('itm_search_index_backfill_company')) {
    function itm_search_index_backfill_company($conn, $companyId, $moduleSlug = '')
    {
        if (!itm_search_index_table_ready($conn) || (int)$companyId <= 0) {
            return 0;
        }

        $slugs = itm_command_palette_searchable_module_slugs();
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($moduleSlug !== '') {
            if (!in_array($moduleSlug, $slugs, true)) {
                return 0;
            }
            $slugs = [$moduleSlug];
        }

        $synced = 0;
        foreach ($slugs as $slug) {
            foreach (itm_search_index_list_source_record_ids($conn, $slug, (int)$companyId) as $recordId) {
                if (itm_search_index_sync_record($conn, $slug, (int)$companyId, (int)$recordId)) {
                    $synced++;
                }
            }
        }

        return $synced;
    }
}

if (!function_exists('itm_search_index_format_result_row')) {
    /**
     * @return array{id: int, title: string, subtitle: string, url: string}
     */
    function itm_search_index_format_result_row($moduleSlug, array $row)
    {
        if (!function_exists('itm_command_palette_build_view_url')) {
            require_once __DIR__ . '/itm_command_palette_search.php';
        }

        $recordId = (int)($row['record_id'] ?? $row['id'] ?? 0);

        return [
            'id' => $recordId,
            'title' => (string)($row['title'] ?? ''),
            'subtitle' => (string)($row['subtitle'] ?? ''),
            'url' => itm_command_palette_build_view_url($moduleSlug, $recordId),
        ];
    }
}

if (!function_exists('itm_search_index_query_module')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_search_index_query_module($conn, $companyId, $moduleSlug, $query, $limit = 5)
    {
        if (!itm_search_index_table_ready($conn) || (int)$companyId <= 0) {
            return [];
        }

        $moduleSlug = strtolower(trim((string)$moduleSlug));
        $limit = max(1, min(20, (int)$limit));
        $ftQuery = itm_search_index_build_fulltext_query($query);
        if ($ftQuery === '' || $moduleSlug === '') {
            return [];
        }

        $sql = 'SELECT record_id, title, subtitle
                FROM search_index
                WHERE company_id = ? AND module_slug = ?
                  AND MATCH(title, subtitle, keywords) AGAINST (? IN BOOLEAN MODE)
                ORDER BY updated_at DESC
                LIMIT ' . $limit;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'iss', $companyId, $moduleSlug, $ftQuery);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $results = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $results[] = itm_search_index_format_result_row($moduleSlug, $row);
        }
        mysqli_stmt_close($stmt);

        return $results;
    }
}
