<?php
/**
 * Shared logic for apply_new_company_module_share_capable_seed.php
 * (equivalent to db/migrations/company_module_share_capable_seed.sql).
 */

if (!function_exists('itm_apply_new_company_module_share_seed_capable_in_sql')) {
    function itm_apply_new_company_module_share_seed_capable_in_sql()
    {
        if (!function_exists('itm_module_share_capable_registry_ids_sql_in_list')) {
            require_once ROOT_PATH . 'includes/itm_module_share.php';
        }

        return itm_module_share_capable_registry_ids_sql_in_list();
    }
}

if (!function_exists('itm_apply_new_company_module_share_seed_company_filter_sql')) {
    /**
     * @return array{sql:string,types:string,params:array<int,int>}
     */
    function itm_apply_new_company_module_share_seed_company_filter_sql($companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId > 0) {
            return [
                'sql' => ' AND c.id = ?',
                'types' => 'i',
                'params' => [$companyId],
            ];
        }

        return ['sql' => '', 'types' => '', 'params' => []];
    }
}

if (!function_exists('itm_apply_new_company_module_share_seed_report')) {
    /**
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   company_id:int,
     *   capable_slug_count:int,
     *   active_companies:int,
     *   non_capable_rows:int,
     *   missing_pairs:int,
     *   expected_pairs:int,
     *   existing_capable_rows:int
     * }
     */
    function itm_apply_new_company_module_share_seed_report($conn, $companyId = 0)
    {
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli)) {
            return ['ok' => false, 'error' => 'Database connection required.', 'company_id' => $companyId];
        }
        if (!function_exists('itm_qr_share_capable_module_slugs')) {
            require_once ROOT_PATH . 'includes/itm_qr_share.php';
        }
        if (!function_exists('itm_module_share_table_exists')) {
            require_once ROOT_PATH . 'includes/itm_module_share.php';
        }
        if (!itm_module_share_table_exists($conn, 'company_module_share')
            || !itm_module_share_table_exists($conn, 'modules_registry')) {
            return ['ok' => false, 'error' => 'company_module_share or modules_registry table missing.', 'company_id' => $companyId];
        }

        $capableSlugs = itm_qr_share_capable_module_slugs();
        $inList = itm_apply_new_company_module_share_seed_capable_in_sql();
        $filter = itm_apply_new_company_module_share_seed_company_filter_sql($companyId);

        $nonCapable = 0;
        $sqlNonCapable = 'SELECT COUNT(*) AS cnt
            FROM company_module_share cms
            INNER JOIN modules_registry mr ON mr.id = cms.module_id
            INNER JOIN companies c ON c.id = cms.company_id AND c.active = 1
            WHERE mr.module_slug NOT IN (' . $inList . ')' . $filter['sql'];
        $stmt = mysqli_prepare($conn, $sqlNonCapable);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not prepare non-capable row count.', 'company_id' => $companyId];
        }
        if ($filter['types'] !== '') {
            mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['params']);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $nonCapable = (int)($row['cnt'] ?? 0);
        }
        mysqli_stmt_close($stmt);

        $activeCompanies = 0;
        $sqlCompanies = 'SELECT COUNT(*) AS cnt FROM companies c WHERE c.active = 1' . $filter['sql'];
        $stmt = mysqli_prepare($conn, $sqlCompanies);
        if ($stmt) {
            if ($filter['types'] !== '') {
                mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['params']);
            }
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $activeCompanies = (int)($row['cnt'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }

        $capableModules = 0;
        $res = mysqli_query($conn, 'SELECT COUNT(*) AS cnt FROM modules_registry WHERE module_slug IN (' . $inList . ')');
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $capableModules = (int)($row['cnt'] ?? 0);
        }

        $missingPairs = 0;
        $sqlMissing = 'SELECT COUNT(*) AS cnt
            FROM companies c
            CROSS JOIN modules_registry mr
            LEFT JOIN company_module_share cms ON cms.company_id = c.id AND cms.module_id = mr.id
            WHERE c.active = 1
              AND mr.module_slug IN (' . $inList . ')
              AND cms.id IS NULL' . $filter['sql'];
        $stmt = mysqli_prepare($conn, $sqlMissing);
        if ($stmt) {
            if ($filter['types'] !== '') {
                mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['params']);
            }
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $missingPairs = (int)($row['cnt'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }

        $existingCapable = 0;
        $sqlExisting = 'SELECT COUNT(*) AS cnt
            FROM company_module_share cms
            INNER JOIN modules_registry mr ON mr.id = cms.module_id
            INNER JOIN companies c ON c.id = cms.company_id AND c.active = 1
            WHERE mr.module_slug IN (' . $inList . ')' . $filter['sql'];
        $stmt = mysqli_prepare($conn, $sqlExisting);
        if ($stmt) {
            if ($filter['types'] !== '') {
                mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['params']);
            }
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $existingCapable = (int)($row['cnt'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }

        $expectedPairs = $activeCompanies * $capableModules;

        return [
            'ok' => true,
            'company_id' => $companyId,
            'capable_slug_count' => count($capableSlugs),
            'capable_registry_rows' => $capableModules,
            'active_companies' => $activeCompanies,
            'non_capable_rows' => $nonCapable,
            'missing_pairs' => $missingPairs,
            'expected_pairs' => $expectedPairs,
            'existing_capable_rows' => $existingCapable,
        ];
    }
}

if (!function_exists('itm_apply_new_company_module_share_seed_run')) {
    /**
     * @return array{ok:bool,error?:string,deleted:int,inserted:int,report:array<string,mixed>}
     */
    function itm_apply_new_company_module_share_seed_run($conn, $companyId = 0)
    {
        $report = itm_apply_new_company_module_share_seed_report($conn, $companyId);
        if (empty($report['ok'])) {
            return [
                'ok' => false,
                'error' => (string)($report['error'] ?? 'Report failed.'),
                'deleted' => 0,
                'inserted' => 0,
                'report' => $report,
            ];
        }

        $companyId = (int)$companyId;
        $inList = itm_apply_new_company_module_share_seed_capable_in_sql();
        $filter = itm_apply_new_company_module_share_seed_company_filter_sql($companyId);
        $deleted = 0;
        $inserted = 0;

        $sqlDelete = 'DELETE cms
            FROM company_module_share cms
            INNER JOIN modules_registry mr ON mr.id = cms.module_id
            INNER JOIN companies c ON c.id = cms.company_id AND c.active = 1
            WHERE mr.module_slug NOT IN (' . $inList . ')' . $filter['sql'];
        $stmt = mysqli_prepare($conn, $sqlDelete);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'DELETE prepare failed.', 'deleted' => 0, 'inserted' => 0, 'report' => $report];
        }
        if ($filter['types'] !== '') {
            mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['params']);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);

            return ['ok' => false, 'error' => 'DELETE failed: ' . $err, 'deleted' => 0, 'inserted' => 0, 'report' => $report];
        }
        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $sqlInsert = 'INSERT IGNORE INTO company_module_share (company_id, module_id, enabled)
            SELECT c.id, mr.id, 1
            FROM companies c
            CROSS JOIN modules_registry mr
            WHERE c.active = 1
              AND mr.module_slug IN (' . $inList . ')' . $filter['sql'];
        $stmt = mysqli_prepare($conn, $sqlInsert);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'INSERT prepare failed.', 'deleted' => $deleted, 'inserted' => 0, 'report' => $report];
        }
        if ($filter['types'] !== '') {
            mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['params']);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);

            return ['ok' => false, 'error' => 'INSERT failed: ' . $err, 'deleted' => $deleted, 'inserted' => 0, 'report' => $report];
        }
        $inserted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $reportAfter = itm_apply_new_company_module_share_seed_report($conn, $companyId);

        return [
            'ok' => true,
            'deleted' => $deleted,
            'inserted' => $inserted,
            'report' => $reportAfter,
        ];
    }
}
