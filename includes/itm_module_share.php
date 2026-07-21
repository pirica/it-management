<?php
/**
 * Company-level QR / code share enablement (per module_slug via modules_registry).
 */

if (!function_exists('itm_module_share_table_exists')) {
    function itm_module_share_table_exists($conn, $tableName)
    {
        static $cache = [];
        $tableName = trim((string)$tableName);
        if ($tableName === '' || !($conn instanceof mysqli)) {
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

if (!function_exists('itm_company_module_share_map')) {
    function itm_company_module_share_map($conn)
    {
        if (!($conn instanceof mysqli) || !itm_module_share_table_exists($conn, 'company_module_share')) {
            return [];
        }

        $map = [];
        $sql = 'SELECT company_id, module_id, enabled FROM company_module_share';
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

if (!function_exists('itm_module_share_registry_id_by_slug')) {
    function itm_module_share_registry_id_by_slug($conn, $moduleSlug)
    {
        $moduleSlug = trim((string)$moduleSlug);
        if ($moduleSlug === '' || !($conn instanceof mysqli) || !itm_module_share_table_exists($conn, 'modules_registry')) {
            return 0;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 's', $moduleSlug);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_module_share_effective_enabled')) {
    function itm_module_share_effective_enabled($conn, $companyId, $moduleId, $shareMap = null)
    {
        $companyId = (int)$companyId;
        $moduleId = (int)$moduleId;
        if ($companyId <= 0 || $moduleId <= 0) {
            return true;
        }
        if (!is_array($shareMap)) {
            $shareMap = itm_company_module_share_map($conn);
        }
        if (!isset($shareMap[$companyId][$moduleId])) {
            return true;
        }

        return (int)$shareMap[$companyId][$moduleId] === 1;
    }
}

if (!function_exists('has_module_share_access')) {
    function has_module_share_access($conn, $companyId, $moduleSlug)
    {
        $companyId = (int)$companyId;
        $moduleSlug = trim((string)$moduleSlug);
        if ($companyId <= 0 || $moduleSlug === '' || !($conn instanceof mysqli)) {
            return false;
        }

        if (!function_exists('itm_qr_share_capable_module_slugs')) {
            require_once ROOT_PATH . 'includes/itm_qr_share.php';
        }
        if (!in_array($moduleSlug, itm_qr_share_capable_module_slugs(), true)) {
            return false;
        }

        if (!itm_module_share_table_exists($conn, 'company_module_share')
            || !itm_module_share_table_exists($conn, 'modules_registry')) {
            return true;
        }

        $moduleId = itm_module_share_registry_id_by_slug($conn, $moduleSlug);
        if ($moduleId <= 0) {
            return false;
        }

        return itm_module_share_effective_enabled($conn, $companyId, $moduleId);
    }
}

if (!function_exists('itm_set_company_module_share')) {
    function itm_set_company_module_share($conn, $companyId, $moduleId, $enabled)
    {
        $companyId = (int)$companyId;
        $moduleId = (int)$moduleId;
        $enabled = (int)((bool)$enabled);
        if ($companyId <= 0 || $moduleId <= 0 || !($conn instanceof mysqli)) {
            return false;
        }

        $oldValues = null;
        $stmtFetch = mysqli_prepare(
            $conn,
            'SELECT id FROM company_module_share WHERE company_id = ? AND module_id = ? LIMIT 1'
        );
        if ($stmtFetch) {
            mysqli_stmt_bind_param($stmtFetch, 'ii', $companyId, $moduleId);
            mysqli_stmt_execute($stmtFetch);
            $resFetch = mysqli_stmt_get_result($stmtFetch);
            $existingId = ($resFetch && ($row = mysqli_fetch_assoc($resFetch))) ? (int)$row['id'] : 0;
            mysqli_stmt_close($stmtFetch);
            if ($existingId > 0 && function_exists('itm_fetch_audit_record')) {
                $oldValues = itm_fetch_audit_record($conn, 'company_module_share', $existingId, $companyId);
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO company_module_share (company_id, module_id, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $moduleId, $enabled);
        $ok = mysqli_stmt_execute($stmt);
        $rowId = (int)mysqli_insert_id($conn);
        if ($rowId <= 0 && $stmtFetch) {
            $stmtId = mysqli_prepare($conn, 'SELECT id FROM company_module_share WHERE company_id = ? AND module_id = ? LIMIT 1');
            if ($stmtId) {
                mysqli_stmt_bind_param($stmtId, 'ii', $companyId, $moduleId);
                mysqli_stmt_execute($stmtId);
                $resId = mysqli_stmt_get_result($stmtId);
                $idRow = $resId ? mysqli_fetch_assoc($resId) : null;
                $rowId = (int)($idRow['id'] ?? 0);
                mysqli_stmt_close($stmtId);
            }
        }
        mysqli_stmt_close($stmt);

        if ($ok && $rowId > 0 && function_exists('itm_log_audit')) {
            $newValues = itm_fetch_audit_record($conn, 'company_module_share', $rowId, $companyId);
            $action = $oldValues === null ? 'INSERT' : 'UPDATE';
            itm_log_audit($conn, 'company_module_share', $rowId, $action, $oldValues, $newValues);
        }

        return (bool)$ok;
    }
}

if (!function_exists('itm_seed_company_module_share_for_module')) {
    function itm_seed_company_module_share_for_module($conn, $moduleId)
    {
        $moduleId = (int)$moduleId;
        if ($moduleId <= 0 || !($conn instanceof mysqli) || !itm_module_share_table_exists($conn, 'company_module_share')) {
            return false;
        }

        $sql = 'INSERT IGNORE INTO company_module_share (company_id, module_id, enabled)
                SELECT c.id, ?, 1 FROM companies c WHERE c.active = 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $moduleId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_seed_company_module_share_all')) {
    function itm_seed_company_module_share_all($conn)
    {
        if (!($conn instanceof mysqli) || !itm_module_share_table_exists($conn, 'company_module_share')) {
            return false;
        }

        $sql = 'INSERT IGNORE INTO company_module_share (company_id, module_id, enabled)
                SELECT c.id, mr.id, 1
                FROM companies c
                CROSS JOIN modules_registry mr
                WHERE c.active = 1';

        return (bool)mysqli_query($conn, $sql);
    }
}

if (!function_exists('itm_module_share_matrix_rows')) {
    function itm_module_share_matrix_rows($conn)
    {
        if (!($conn instanceof mysqli) || !itm_module_share_table_exists($conn, 'modules_registry')) {
            return [];
        }

        if (!function_exists('itm_list_all_modules_registry')) {
            require_once ROOT_PATH . 'includes/itm_company_module_access.php';
        }

        return itm_list_all_modules_registry($conn);
    }
}
