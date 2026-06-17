<?php
/**
 * Company-level module access registry and enforcement helpers.
 */

if (!function_exists('itm_module_access_system_slugs')) {
    function itm_module_access_system_slugs()
    {
        return [
            'settings',
            'companies',
            'users',
            'user_companies',
            'user_roles',
            'company_module_access',
            'audit_logs',
            'role_module_permissions',
            'ui_configuration',
            'role_hierarchy',
            'role_assignment_rights',
        ];
    }
}

if (!function_exists('itm_module_access_always_allowed_slugs')) {
    function itm_module_access_always_allowed_slugs()
    {
        return [
            'settings',
        ];
    }
}

if (!function_exists('itm_module_access_admin_only_slugs')) {
    function itm_module_access_admin_only_slugs()
    {
        return [
            'companies',
            'users',
            'user_companies',
            'user_roles',
            'company_module_access',
            'audit_logs',
            'role_module_permissions',
            'ui_configuration',
            'role_hierarchy',
            'role_assignment_rights',
        ];
    }
}

if (!function_exists('itm_module_access_bypass_paths')) {
    function itm_module_access_bypass_paths()
    {
        return [
            'login.php',
            'logout.php',
            'register.php',
            'forgot-password.php',
            'reset-password.php',
            'index.php',
            'dashboard.php',
            'user-config.php',
        ];
    }
}

if (!function_exists('itm_module_access_table_exists')) {
    function itm_module_access_table_exists($conn, $tableName)
    {
        static $cache = [];
        $tableName = trim((string)$tableName);
        if ($tableName === '' || !$conn instanceof mysqli) {
            return false;
        }
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        if (!$stmt) {
            $cache[$tableName] = false;
            return false;
        }
        mysqli_stmt_bind_param($stmt, 's', $tableName);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $cache[$tableName] = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $cache[$tableName];
    }
}

if (!function_exists('itm_module_access_registry_row')) {
    function itm_module_access_registry_row($conn, $moduleSlug)
    {
        $moduleSlug = trim((string)$moduleSlug);
        if ($moduleSlug === '' || !$conn instanceof mysqli) {
            return null;
        }
        if (!itm_module_access_table_exists($conn, 'modules_registry')) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, module_slug, module_name, is_system_module, active
             FROM modules_registry
             WHERE module_slug = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $moduleSlug);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('has_module_access')) {
    function has_module_access($conn, $company_id, $module_slug)
    {
        $company_id = (int)$company_id;
        $module_slug = trim((string)$module_slug);
        if ($module_slug === '' || !$conn instanceof mysqli || $company_id <= 0) {
            return false;
        }

        if (in_array($module_slug, itm_module_access_always_allowed_slugs(), true)) {
            return true;
        }

        if (!itm_module_access_table_exists($conn, 'modules_registry')
            || !itm_module_access_table_exists($conn, 'company_module_access')) {
            return true;
        }

        $registryRow = itm_module_access_registry_row($conn, $module_slug);
        if ($registryRow === null) {
            return false;
        }

        if ((int)($registryRow['active'] ?? 0) !== 1) {
            return false;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $isAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $userId);
        if ((int)($registryRow['is_system_module'] ?? 0) === 1) {
            if ($isAdmin) {
                return true;
            }
            if (in_array($module_slug, itm_module_access_admin_only_slugs(), true)) {
                return false;
            }
        }

        if ($module_slug === 'company_module_access' && $isAdmin) {
            return true;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1
             FROM company_module_access cma
             INNER JOIN modules_registry mr ON mr.id = cma.module_id
             WHERE cma.company_id = ?
               AND mr.module_slug = ?
               AND cma.enabled = 1
               AND mr.active = 1
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'is', $company_id, $module_slug);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $allowed = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $allowed;
    }
}

if (!function_exists('get_company_modules')) {
    function get_company_modules($conn, $company_id)
    {
        $company_id = (int)$company_id;
        if ($company_id <= 0 || !$conn instanceof mysqli) {
            return [];
        }

        $allRows = itm_list_all_modules_registry($conn);
        $enabled = [];
        foreach ($allRows as $row) {
            $slug = (string)($row['module_slug'] ?? '');
            if ($slug === '' || !has_module_access($conn, $company_id, $slug)) {
                continue;
            }
            $enabled[] = [
                'id' => (int)($row['id'] ?? 0),
                'module_slug' => $slug,
                'module_name' => (string)($row['module_name'] ?? $slug),
                'href' => 'modules/' . $slug . '/',
                'is_system_module' => (int)($row['is_system_module'] ?? 0),
                'active' => (int)($row['active'] ?? 0),
            ];
        }

        return $enabled;
    }
}

if (!function_exists('itm_list_all_modules_registry')) {
    function itm_list_all_modules_registry($conn)
    {
        if (!$conn instanceof mysqli || !itm_module_access_table_exists($conn, 'modules_registry')) {
            return [];
        }

        $rows = [];
        $sql = 'SELECT id, module_slug, module_name, is_system_module, active, created_at, updated_at
                FROM modules_registry
                ORDER BY module_name ASC, module_slug ASC';
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('itm_module_access_default_label')) {
    function itm_module_access_default_label($moduleSlug)
    {
        $moduleSlug = trim((string)$moduleSlug);
        if ($moduleSlug === '') {
            return '';
        }
        if (function_exists('itm_sidebar_humanize_table_name')) {
            return itm_sidebar_humanize_table_name($moduleSlug);
        }
        return ucwords(str_replace('_', ' ', $moduleSlug));
    }
}

if (!function_exists('itm_discover_module_slugs_for_registry')) {
    function itm_discover_module_slugs_for_registry()
    {
        $slugs = [];
        $modulesRoot = defined('ROOT_PATH') ? ROOT_PATH . 'modules' : dirname(__DIR__) . '/modules';
        if (is_dir($modulesRoot)) {
            foreach (glob($modulesRoot . '/*/index.php') ?: [] as $indexPath) {
                $slug = basename(dirname($indexPath));
                if ($slug !== '') {
                    $slugs[$slug] = true;
                }
            }
        }

        if (function_exists('itm_sidebar_excluded_module_ids')) {
            foreach (itm_sidebar_excluded_module_ids() as $excludedSlug) {
                $slugs[(string)$excludedSlug] = true;
            }
        }

        $slugList = array_keys($slugs);
        sort($slugList, SORT_STRING);
        return $slugList;
    }
}

if (!function_exists('itm_sync_modules_registry_from_filesystem')) {
    function itm_sync_modules_registry_from_filesystem($conn)
    {
        if (!$conn instanceof mysqli || !itm_module_access_table_exists($conn, 'modules_registry')) {
            return ['inserted' => 0, 'updated' => 0, 'total' => 0];
        }

        $inserted = 0;
        $updated = 0;
        $systemSlugs = itm_module_access_system_slugs();
        $catalogLabels = [];
        if (function_exists('itm_sidebar_item_catalog')) {
            foreach (itm_sidebar_item_catalog() as $itemId => $item) {
                $catalogLabels[(string)$itemId] = (string)($item['label'] ?? '');
            }
        }

        foreach (itm_discover_module_slugs_for_registry() as $slug) {
            $label = $catalogLabels[$slug] ?? '';
            if ($label !== '') {
                $label = preg_replace('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\s]+/u', '', $label);
            }
            if ($label === '') {
                $label = itm_module_access_default_label($slug);
            }

            $isSystem = in_array($slug, $systemSlugs, true) ? 1 : 0;
            if ($slug === 'company_module_access') {
                $isSystem = 1;
            }

            $existing = itm_module_access_registry_row($conn, $slug);
            if ($existing === null) {
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO modules_registry (module_slug, module_name, is_system_module, active)
                     VALUES (?, ?, ?, 1)'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssi', $slug, $label, $isSystem);
                    if (mysqli_stmt_execute($stmt)) {
                        $inserted++;
                    }
                    mysqli_stmt_close($stmt);
                }
                continue;
            }

            $stmt = mysqli_prepare(
                $conn,
                'UPDATE modules_registry
                 SET module_name = ?, is_system_module = ?
                 WHERE module_slug = ?'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sis', $label, $isSystem, $slug);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $updated++;
                }
                mysqli_stmt_close($stmt);
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count(itm_discover_module_slugs_for_registry()),
            'access_seeded' => itm_seed_company_module_access_all($conn),
        ];
    }
}

if (!function_exists('itm_seed_company_module_access_all')) {
    function itm_seed_company_module_access_all($conn)
    {
        if (!$conn instanceof mysqli
            || !itm_module_access_table_exists($conn, 'modules_registry')
            || !itm_module_access_table_exists($conn, 'company_module_access')) {
            return 0;
        }

        $sql = 'INSERT IGNORE INTO company_module_access (company_id, module_id, enabled)
                SELECT c.id, mr.id, 1
                FROM companies c
                CROSS JOIN modules_registry mr
                WHERE c.active = 1';
        if (!mysqli_query($conn, $sql)) {
            return 0;
        }

        return (int)mysqli_affected_rows($conn);
    }
}

if (!function_exists('itm_company_module_access_map')) {
    function itm_company_module_access_map($conn)
    {
        if (!$conn instanceof mysqli || !itm_module_access_table_exists($conn, 'company_module_access')) {
            return [];
        }

        $map = [];
        $sql = 'SELECT company_id, module_id, enabled FROM company_module_access';
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $companyId = (int)($row['company_id'] ?? 0);
            $moduleId = (int)($row['module_id'] ?? 0);
            if ($companyId <= 0 || $moduleId <= 0) {
                continue;
            }
            $map[$companyId][$moduleId] = (int)($row['enabled'] ?? 0);
        }
        return $map;
    }
}

if (!function_exists('itm_set_company_module_access')) {
    function itm_set_company_module_access($conn, $company_id, $module_id, $enabled)
    {
        $company_id = (int)$company_id;
        $module_id = (int)$module_id;
        $enabled = (int)((bool)$enabled);
        if ($company_id <= 0 || $module_id <= 0 || !$conn instanceof mysqli) {
            return false;
        }

        $oldValues = null;
        $stmtFetch = mysqli_prepare(
            $conn,
            'SELECT id FROM company_module_access WHERE company_id = ? AND module_id = ? LIMIT 1'
        );
        if ($stmtFetch) {
            mysqli_stmt_bind_param($stmtFetch, 'ii', $company_id, $module_id);
            mysqli_stmt_execute($stmtFetch);
            $resFetch = mysqli_stmt_get_result($stmtFetch);
            $existingId = ($resFetch && ($row = mysqli_fetch_assoc($resFetch))) ? (int)$row['id'] : 0;
            mysqli_stmt_close($stmtFetch);
            if ($existingId > 0 && function_exists('itm_fetch_audit_record')) {
                $oldValues = itm_fetch_audit_record($conn, 'company_module_access', $existingId, $company_id);
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO company_module_access (company_id, module_id, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $module_id, $enabled);
        $ok = mysqli_stmt_execute($stmt);
        $recordId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return false;
        }

        if ($recordId <= 0 && $stmtFetch) {
            $stmtLookup = mysqli_prepare(
                $conn,
                'SELECT id FROM company_module_access WHERE company_id = ? AND module_id = ? LIMIT 1'
            );
            if ($stmtLookup) {
                mysqli_stmt_bind_param($stmtLookup, 'ii', $company_id, $module_id);
                mysqli_stmt_execute($stmtLookup);
                $resLookup = mysqli_stmt_get_result($stmtLookup);
                $recordId = ($resLookup && ($lookup = mysqli_fetch_assoc($resLookup))) ? (int)$lookup['id'] : 0;
                mysqli_stmt_close($stmtLookup);
            }
        }

        if (function_exists('itm_log_audit') && $recordId > 0) {
            $newValues = itm_fetch_audit_record($conn, 'company_module_access', $recordId, $company_id);
            $action = $oldValues ? 'UPDATE' : 'INSERT';
            itm_log_audit($conn, 'company_module_access', $recordId, $action, $oldValues, $newValues);
        }

        return true;
    }
}

if (!function_exists('itm_seed_company_module_access_for_company')) {
    function itm_seed_company_module_access_for_company($conn, $company_id)
    {
        $company_id = (int)$company_id;
        if ($company_id <= 0 || !$conn instanceof mysqli) {
            return 0;
        }
        itm_sync_modules_registry_from_filesystem($conn);

        $stmt = mysqli_prepare(
            $conn,
            'INSERT IGNORE INTO company_module_access (company_id, module_id, enabled)
             SELECT ?, mr.id, 1 FROM modules_registry mr'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $count = (int)mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $count;
    }
}

if (!function_exists('itm_seed_company_module_access_for_module')) {
    function itm_seed_company_module_access_for_module($conn, $module_id)
    {
        $module_id = (int)$module_id;
        if ($module_id <= 0 || !$conn instanceof mysqli) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT IGNORE INTO company_module_access (company_id, module_id, enabled)
             SELECT id, ?, 1 FROM companies WHERE active = 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $module_id);
        mysqli_stmt_execute($stmt);
        $count = (int)mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $count;
    }
}

if (!function_exists('itm_resolve_request_module_slug')) {
    function itm_resolve_request_module_slug()
    {
        $script = (string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        if ($script === '') {
            return '';
        }
        $normalized = str_replace('\\', '/', $script);
        if (preg_match('#/modules/([^/]+)/#', $normalized, $matches)) {
            return (string)$matches[1];
        }
        return '';
    }
}

if (!function_exists('itm_enforce_module_access_or_exit')) {
    function itm_enforce_module_access_or_exit($conn)
    {
        global $itmSkipWebAuth;

        if (PHP_SAPI === 'cli' || !empty($itmSkipWebAuth)) {
            return;
        }

        $currentFile = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (in_array($currentFile, itm_module_access_bypass_paths(), true)) {
            return;
        }

        $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if (strpos($scriptPath, '/scripts/') !== false) {
            return;
        }

        $moduleSlug = itm_resolve_request_module_slug();
        if ($moduleSlug === '') {
            return;
        }

        $companyId = (int)($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        if (has_module_access($conn, $companyId, $moduleSlug)) {
            return;
        }

        http_response_code(403);
        die('Access Denied');
    }
}

if (!function_exists('itm_module_access_effective_enabled')) {
    function itm_module_access_effective_enabled($conn, $company_id, $module_id, $accessMap = null)
    {
        $company_id = (int)$company_id;
        $module_id = (int)$module_id;
        if ($company_id <= 0 || $module_id <= 0) {
            return false;
        }
        if (!is_array($accessMap)) {
            $accessMap = itm_company_module_access_map($conn);
        }
        if (!isset($accessMap[$company_id][$module_id])) {
            return false;
        }
        return (int)$accessMap[$company_id][$module_id] === 1;
    }
}
