<?php
/**
 * Active session presence helpers for dashboard metrics.
 *
 * Why: PHP's default session directory (mode 1733) blocks listing sess_* files, so
 * ITM records authenticated presence as per-company touch files updated each request.
 */

if (!function_exists('itm_active_sessions_presence_root')) {
    /**
     * Writable root for presence touch files (not web-served).
     */
    function itm_active_sessions_presence_root(): string
    {
        $root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'itm-session-presence';

        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }

        return $root;
    }
}

if (!function_exists('itm_active_sessions_gc_maxlifetime')) {
    /**
     * Seconds after which a presence touch file is treated as stale.
     */
    function itm_active_sessions_gc_maxlifetime(): int
    {
        $maxLifetime = (int)ini_get('session.gc_maxlifetime');
        if ($maxLifetime <= 0) {
            $maxLifetime = 1440;
        }

        return $maxLifetime;
    }
}

if (!function_exists('itm_active_sessions_touch_path')) {
    /**
     * Resolve the touch file path for one employee in one company.
     */
    function itm_active_sessions_touch_path(int $companyId, int $employeeId): string
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $companyDir = itm_active_sessions_presence_root()
            . DIRECTORY_SEPARATOR
            . (string)$companyId;

        return $companyDir . DIRECTORY_SEPARATOR . (string)$employeeId . '.presence';
    }
}

if (!function_exists('itm_active_sessions_touch')) {
    /**
     * Record that an employee has an active authenticated session for a company.
     */
    function itm_active_sessions_touch(int $employeeId, int $companyId): bool
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        if ($employeeId <= 0 || $companyId <= 0) {
            return false;
        }

        $companyDir = itm_active_sessions_presence_root()
            . DIRECTORY_SEPARATOR
            . (string)$companyId;
        if (!is_dir($companyDir) && !@mkdir($companyDir, 0777, true) && !is_dir($companyDir)) {
            return false;
        }

        $touchPath = itm_active_sessions_touch_path($companyId, $employeeId);
        $handle = @fopen($touchPath, 'c');
        if ($handle === false) {
            return false;
        }

        fclose($handle);
        @touch($touchPath);

        return true;
    }
}

if (!function_exists('itm_active_sessions_parse_int_field')) {
    /**
     * Extract one integer field from PHP session serialization.
     *
     * @return int|null
     */
    function itm_active_sessions_parse_int_field(string $raw, string $fieldName)
    {
        if ($fieldName === '' || $raw === '') {
            return null;
        }

        $pattern = '/(?:^|;)' . preg_quote($fieldName, '/') . '\|i:(-?\d+);/';
        if (preg_match($pattern, $raw, $matches) !== 1) {
            return null;
        }

        return (int)$matches[1];
    }
}

if (!function_exists('itm_active_sessions_parse_payload')) {
    /**
     * Parse employee_id and company_id from raw PHP session serialization.
     *
     * @return array{employee_id:int,company_id:int}
     */
    function itm_active_sessions_parse_payload(string $raw): array
    {
        $employeeId = itm_active_sessions_parse_int_field($raw, 'employee_id');
        $companyId = itm_active_sessions_parse_int_field($raw, 'company_id');

        if ($employeeId === null || $companyId === null || $employeeId <= 0 || $companyId <= 0) {
            return [];
        }

        return [
            'employee_id' => (int)$employeeId,
            'company_id' => (int)$companyId,
        ];
    }
}

if (!function_exists('itm_active_sessions_filter_existing_employee_ids')) {
    /**
     * Drop employee IDs that no longer exist (orphan presence files).
     *
     * @param int[] $employeeIds
     * @return int[]
     */
    function itm_active_sessions_filter_existing_employee_ids(mysqli $conn, array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds), static function ($id) {
            return $id > 0;
        })));

        if ($employeeIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $types = str_repeat('i', count($employeeIds));
        $sql = 'SELECT id FROM employees WHERE id IN (' . $placeholders . ')';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $employeeIds;
        }

        $bindParams = [$types];
        foreach ($employeeIds as $index => $employeeId) {
            $bindParams[] = &$employeeIds[$index];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bindParams));

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return $employeeIds;
        }

        $existing = [];
        if (function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $existing[] = (int)$row['id'];
                }
            }
        } else {
            mysqli_stmt_bind_result($stmt, $foundId);
            while (mysqli_stmt_fetch($stmt)) {
                $existing[] = (int)$foundId;
            }
        }

        mysqli_stmt_close($stmt);

        return $existing;
    }
}

if (!function_exists('itm_count_logged_in_users_for_company')) {
    /**
     * Count unique employees with a recent authenticated presence touch for a company.
     */
    function itm_count_logged_in_users_for_company(int $companyId, ?mysqli $conn = null): int
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }

        $companyDir = itm_active_sessions_presence_root()
            . DIRECTORY_SEPARATOR
            . (string)$companyId;
        if (!is_dir($companyDir) || !is_readable($companyDir)) {
            return 0;
        }

        $maxLifetime = itm_active_sessions_gc_maxlifetime();
        $now = time();
        $employeeIds = [];

        foreach (glob($companyDir . DIRECTORY_SEPARATOR . '*.presence') ?: [] as $presenceFile) {
            if (!is_file($presenceFile) || !is_readable($presenceFile)) {
                continue;
            }

            $modifiedAt = @filemtime($presenceFile);
            if ($modifiedAt === false || ($now - (int)$modifiedAt) > $maxLifetime) {
                continue;
            }

            $baseName = basename($presenceFile, '.presence');
            if (!ctype_digit($baseName)) {
                continue;
            }

            $employeeId = (int)$baseName;
            if ($employeeId > 0) {
                $employeeIds[] = $employeeId;
            }
        }

        $employeeIds = array_values(array_unique($employeeIds));
        if ($employeeIds === []) {
            return 0;
        }

        if ($conn instanceof mysqli) {
            $employeeIds = itm_active_sessions_filter_existing_employee_ids($conn, $employeeIds);
        }

        return count($employeeIds);
    }
}
