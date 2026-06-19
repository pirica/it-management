<?php
/**
 * Shared helpers for sidebar module-access query benchmarks.
 *
 * Why: Measure MySQL Questions deltas for the live sidebar filter path vs an
 * uncached legacy N+1 simulation without mutating production helpers.
 */

if (!function_exists('itm_bsma_session_questions')) {
    function itm_bsma_session_questions(mysqli $conn): ?int
    {
        $res = mysqli_query($conn, "SHOW SESSION STATUS LIKE 'Questions'");
        if (!$res) {
            return null;
        }
        $row = mysqli_fetch_row($res);
        mysqli_free_result($res);

        return is_array($row) ? (int)$row[1] : null;
    }
}

if (!function_exists('itm_bsma_legacy_admin_query')) {
    function itm_bsma_legacy_admin_query(mysqli $conn, int $employeeId): void
    {
        if ($employeeId <= 0) {
            return;
        }

        $sql = 'SELECT 1
            FROM `employees` u
            LEFT JOIN `employee_roles` ur ON ur.id = u.role_id
            WHERE u.id = ? AND (LOWER(COALESCE(ur.name, "")) = "admin" OR LOWER(COALESCE(u.username, "")) = "admin")
            LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_bsma_legacy_cma_enabled_query')) {
    function itm_bsma_legacy_cma_enabled_query(mysqli $conn, int $companyId, string $moduleSlug): void
    {
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
            return;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $moduleSlug);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_bsma_sidebar_module_slugs_for_filter')) {
    /**
     * @return array<int,string>
     */
    function itm_bsma_sidebar_module_slugs_for_filter(mysqli $conn): array
    {
        $catalog = itm_sidebar_item_catalog();
        $slugs = [];
        foreach ($catalog as $itemId => $sidebarItem) {
            $itemId = (string)$itemId;
            if ($itemId === '' || $itemId === 'dashboard_link' || $itemId === 'settings') {
                continue;
            }
            $slugs[] = $itemId;
        }

        return $slugs;
    }
}

if (!function_exists('itm_bsma_run_optimized_sidebar_path')) {
    /**
     * Mirrors includes/sidebar.php structure build + module filter using has_module_access().
     *
     * @return array{queries:int,modules_checked:int,elapsed_ms:float,sections:int}|null
     */
    function itm_bsma_run_optimized_sidebar_path(mysqli $conn, int $companyId, int $employeeId): ?array
    {
        $_SESSION['company_id'] = $companyId;
        $_SESSION['employee_id'] = $employeeId;

        if (function_exists('itm_has_module_access_bust_cache')) {
            itm_has_module_access_bust_cache();
        }

        $before = itm_bsma_session_questions($conn);
        if ($before === null) {
            return null;
        }

        $started = microtime(true);
        $structure = itm_sidebar_structure($conn, true);
        $catalog = itm_sidebar_item_catalog();

        $checked = 0;
        foreach ($catalog as $itemId => $sidebarItem) {
            $itemId = (string)$itemId;
            if ($itemId === 'dashboard_link' || $itemId === 'settings') {
                continue;
            }
            if (!function_exists('has_module_access')) {
                continue;
            }
            has_module_access($conn, $companyId, $itemId);
            $checked++;
        }
        $elapsedMs = (microtime(true) - $started) * 1000;

        $after = itm_bsma_session_questions($conn);
        if ($after === null) {
            return null;
        }

        return [
            'queries' => $after - $before - 1,
            'modules_checked' => $checked,
            'elapsed_ms' => round($elapsedMs, 2),
            'sections' => count($structure),
        ];
    }
}

if (!function_exists('itm_bsma_run_legacy_access_filter')) {
    /**
     * Uncached per-slug registry + admin + CMA queries (pre-prefetch has_module_access behaviour).
     *
     * @param array<int,string> $moduleSlugs
     * @return array{queries:int,modules_checked:int,elapsed_ms:float}|null
     */
    function itm_bsma_run_legacy_access_filter(mysqli $conn, int $companyId, int $employeeId, array $moduleSlugs): ?array
    {
        $before = itm_bsma_session_questions($conn);
        if ($before === null) {
            return null;
        }

        $started = microtime(true);
        $checked = 0;
        $alwaysAllowed = function_exists('itm_module_access_always_allowed_slugs')
            ? itm_module_access_always_allowed_slugs()
            : ['settings'];

        foreach ($moduleSlugs as $moduleSlug) {
            $moduleSlug = trim((string)$moduleSlug);
            if ($moduleSlug === '') {
                continue;
            }
            if (in_array($moduleSlug, $alwaysAllowed, true)) {
                continue;
            }
            if (!function_exists('itm_module_access_table_exists')
                || !itm_module_access_table_exists($conn, 'modules_registry')
                || !itm_module_access_table_exists($conn, 'company_module_access')) {
                continue;
            }

            $registryRow = itm_module_access_registry_row($conn, $moduleSlug);
            if ($registryRow === null) {
                continue;
            }
            if ((int)($registryRow['active'] ?? 0) !== 1) {
                continue;
            }

            itm_bsma_legacy_admin_query($conn, $employeeId);

            if ((int)($registryRow['is_system_module'] ?? 0) === 1) {
                if (function_exists('itm_module_access_admin_only_slugs')
                    && in_array($moduleSlug, itm_module_access_admin_only_slugs(), true)) {
                    continue;
                }
            }

            if ($moduleSlug === 'company_module_access') {
                continue;
            }

            itm_bsma_legacy_cma_enabled_query($conn, $companyId, $moduleSlug);
            $checked++;
        }

        $elapsedMs = (microtime(true) - $started) * 1000;
        $after = itm_bsma_session_questions($conn);
        if ($after === null) {
            return null;
        }

        return [
            'queries' => $after - $before - 1,
            'modules_checked' => $checked,
            'elapsed_ms' => round($elapsedMs, 2),
        ];
    }
}

if (!function_exists('itm_bsma_simulate_legacy_registry_ensure')) {
    /**
     * Pre-batch ensure_registry behaviour: one registry lookup per discovered slug.
     *
     * @param array<int,string> $moduleSlugs
     */
    function itm_bsma_simulate_legacy_registry_ensure(mysqli $conn, array $moduleSlugs): ?array
    {
        $before = itm_bsma_session_questions($conn);
        if ($before === null) {
            return null;
        }

        $started = microtime(true);
        $lookups = 0;
        foreach ($moduleSlugs as $moduleSlug) {
            $moduleSlug = trim((string)$moduleSlug);
            if ($moduleSlug === '') {
                continue;
            }
            if (!function_exists('itm_module_access_registry_row')) {
                continue;
            }
            itm_module_access_registry_row($conn, $moduleSlug);
            $lookups++;
        }

        $elapsedMs = (microtime(true) - $started) * 1000;
        $after = itm_bsma_session_questions($conn);
        if ($after === null) {
            return null;
        }

        return [
            'queries' => $after - $before - 1,
            'slug_lookups' => $lookups,
            'elapsed_ms' => round($elapsedMs, 2),
        ];
    }
}

if (!function_exists('itm_bsma_median')) {
    /**
     * @param array<int,float|int> $values
     */
    function itm_bsma_median(array $values): float
    {
        if (!$values) {
            return 0.0;
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = (int) floor($count / 2);
        if ($count % 2 === 1) {
            return (float)$values[$middle];
        }

        return ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
    }
}
