<?php
/**
 * API v2 scope catalog and persistence on api_key_scopes.
 */

if (!function_exists('itm_api_v2_scope_catalog')) {
    /**
     * @return array<string,string> slug => human label
     */
    function itm_api_v2_scope_catalog()
    {
        return [
            'tickets.read' => 'Tickets — read',
            'tickets.write' => 'Tickets — write',
            'equipment.read' => 'Equipment — read',
            'equipment.write' => 'Equipment — write',
        ];
    }
}

if (!function_exists('itm_api_v2_default_read_scope_slugs')) {
    /**
     * Why: New keys start read-only until the owner enables write scopes in Settings.
     *
     * @return list<string>
     */
    function itm_api_v2_default_read_scope_slugs()
    {
        return ['tickets.read', 'equipment.read'];
    }
}

if (!function_exists('itm_api_v2_normalize_scope_slug')) {
    function itm_api_v2_normalize_scope_slug($slug)
    {
        $slug = strtolower(trim((string)$slug));
        $catalog = itm_api_v2_scope_catalog();

        return isset($catalog[$slug]) ? $slug : '';
    }
}

if (!function_exists('itm_api_v2_filter_valid_scope_slugs')) {
    /**
     * @param list<string>|array<int,string> $slugs
     * @return list<string>
     */
    function itm_api_v2_filter_valid_scope_slugs($slugs)
    {
        if (!is_array($slugs)) {
            return [];
        }

        $out = [];
        foreach ($slugs as $slug) {
            $normalized = itm_api_v2_normalize_scope_slug($slug);
            if ($normalized !== '') {
                $out[$normalized] = $normalized;
            }
        }

        return array_values($out);
    }
}

if (!function_exists('itm_api_v2_ensure_scopes_table')) {
    function itm_api_v2_ensure_scopes_table($conn)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        static $checked = false;
        if ($checked) {
            return true;
        }

        $res = mysqli_query($conn, "SHOW TABLES LIKE 'api_key_scopes'");
        $checked = ($res instanceof mysqli_result) && mysqli_num_rows($res) > 0;
        if ($res instanceof mysqli_result) {
            mysqli_free_result($res);
        }

        return $checked;
    }
}

if (!function_exists('itm_api_v2_list_scopes_for_configuration')) {
    /**
     * @return list<string>
     */
    function itm_api_v2_list_scopes_for_configuration($conn, $companyId, $uiConfigurationId)
    {
        if (!itm_api_v2_ensure_scopes_table($conn)) {
            return [];
        }

        $companyId = (int)$companyId;
        $uiConfigurationId = (int)$uiConfigurationId;
        if ($companyId <= 0 || $uiConfigurationId <= 0) {
            return [];
        }

        $sql = 'SELECT scope_slug FROM api_key_scopes
                WHERE company_id = ? AND ui_configuration_id = ? AND deleted_at IS NULL AND active = 1
                ORDER BY scope_slug ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $uiConfigurationId);
        mysqli_stmt_execute($stmt);
        $rows = function_exists('itm_mysqli_stmt_fetch_all_assoc')
            ? itm_mysqli_stmt_fetch_all_assoc($stmt)
            : [];
        if ($rows === [] && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);

        $slugs = [];
        foreach ($rows as $row) {
            $normalized = itm_api_v2_normalize_scope_slug($row['scope_slug'] ?? '');
            if ($normalized !== '') {
                $slugs[] = $normalized;
            }
        }

        return $slugs;
    }
}

if (!function_exists('itm_api_v2_replace_scopes_for_configuration')) {
    /**
     * @param list<string> $scopeSlugs
     */
    function itm_api_v2_replace_scopes_for_configuration($conn, $companyId, $uiConfigurationId, $scopeSlugs, $actorEmployeeId)
    {
        if (!itm_api_v2_ensure_scopes_table($conn)) {
            return false;
        }

        $companyId = (int)$companyId;
        $uiConfigurationId = (int)$uiConfigurationId;
        $actorEmployeeId = (int)$actorEmployeeId;
        if ($companyId <= 0 || $uiConfigurationId <= 0) {
            return false;
        }

        $scopeSlugs = itm_api_v2_filter_valid_scope_slugs($scopeSlugs);

        mysqli_begin_transaction($conn);
        try {
            $deleteSql = 'DELETE FROM api_key_scopes WHERE company_id = ? AND ui_configuration_id = ?';
            $deleteStmt = mysqli_prepare($conn, $deleteSql);
            if (!$deleteStmt) {
                throw new RuntimeException('prepare delete failed');
            }
            mysqli_stmt_bind_param($deleteStmt, 'ii', $companyId, $uiConfigurationId);
            if (!mysqli_stmt_execute($deleteStmt)) {
                mysqli_stmt_close($deleteStmt);
                throw new RuntimeException('delete failed');
            }
            mysqli_stmt_close($deleteStmt);

            if ($scopeSlugs !== []) {
                $insertSql = 'INSERT INTO api_key_scopes
                    (company_id, ui_configuration_id, scope_slug, active, created_by, updated_by)
                    VALUES (?, ?, ?, 1, ?, ?)';
                $insertStmt = mysqli_prepare($conn, $insertSql);
                if (!$insertStmt) {
                    throw new RuntimeException('prepare insert failed');
                }
                foreach ($scopeSlugs as $slug) {
                    mysqli_stmt_bind_param($insertStmt, 'iisii', $companyId, $uiConfigurationId, $slug, $actorEmployeeId, $actorEmployeeId);
                    if (!mysqli_stmt_execute($insertStmt)) {
                        mysqli_stmt_close($insertStmt);
                        throw new RuntimeException('insert failed');
                    }
                }
                mysqli_stmt_close($insertStmt);
            }

            mysqli_commit($conn);
            return true;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            return false;
        }
    }
}

if (!function_exists('itm_api_v2_configuration_has_scope')) {
    function itm_api_v2_configuration_has_scope($conn, $companyId, $uiConfigurationId, $scopeSlug)
    {
        $scopeSlug = itm_api_v2_normalize_scope_slug($scopeSlug);
        if ($scopeSlug === '') {
            return false;
        }

        $granted = itm_api_v2_list_scopes_for_configuration($conn, (int)$companyId, (int)$uiConfigurationId);

        return in_array($scopeSlug, $granted, true);
    }
}

if (!function_exists('itm_api_v2_seed_default_scopes_for_configuration')) {
    function itm_api_v2_seed_default_scopes_for_configuration($conn, $companyId, $uiConfigurationId, $actorEmployeeId)
    {
        return itm_api_v2_replace_scopes_for_configuration(
            $conn,
            (int)$companyId,
            (int)$uiConfigurationId,
            itm_api_v2_default_read_scope_slugs(),
            (int)$actorEmployeeId
        );
    }
}

if (!function_exists('itm_api_v2_collect_scope_slugs_from_post')) {
    /**
     * @return list<string>
     */
    function itm_api_v2_collect_scope_slugs_from_post()
    {
        $posted = $_POST['api_v2_scopes'] ?? [];
        if (!is_array($posted)) {
            return [];
        }

        $slugs = [];
        foreach ($posted as $slug) {
            if (is_string($slug) || is_numeric($slug)) {
                $slugs[] = (string)$slug;
            }
        }

        return itm_api_v2_filter_valid_scope_slugs($slugs);
    }
}

if (!function_exists('itm_api_v2_replace_scopes_from_post')) {
    function itm_api_v2_replace_scopes_from_post($conn, $companyId, $employeeId)
    {
        if (!function_exists('itm_api_lookup_configuration_by_user')) {
            require_once dirname(__FILE__) . '/itm_api_rate_limit.php';
        }

        $row = itm_api_lookup_configuration_by_user($conn, (int)$companyId, (int)$employeeId);
        if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            return false;
        }

        $scopeSlugs = itm_api_v2_collect_scope_slugs_from_post();

        return itm_api_v2_replace_scopes_for_configuration(
            $conn,
            (int)$companyId,
            (int)$row['id'],
            $scopeSlugs,
            (int)$employeeId
        );
    }
}

if (!function_exists('itm_api_v2_seed_default_scopes_for_settings_user')) {
    function itm_api_v2_seed_default_scopes_for_settings_user($conn, $companyId, $employeeId)
    {
        if (!function_exists('itm_api_lookup_configuration_by_user')) {
            require_once dirname(__FILE__) . '/itm_api_rate_limit.php';
        }

        $row = itm_api_lookup_configuration_by_user($conn, (int)$companyId, (int)$employeeId);
        if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            return false;
        }

        return itm_api_v2_seed_default_scopes_for_configuration(
            $conn,
            (int)$companyId,
            (int)$row['id'],
            (int)$employeeId
        );
    }
}
