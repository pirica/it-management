<?php
/**
 * Per-employee hourly rate limit for authenticated Explorer api.php requests.
 */

if (!function_exists('itm_explorer_api_rate_limit_default_per_hour')) {
    function itm_explorer_api_rate_limit_default_per_hour()
    {
        return 1200;
    }
}

if (!function_exists('itm_explorer_api_normalize_rate_limit_per_hour')) {
    function itm_explorer_api_normalize_rate_limit_per_hour($value)
    {
        $limit = (int) $value;
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        return $limit;
    }
}

if (!function_exists('itm_explorer_api_rate_limit_per_hour')) {
    /**
     * Why: Cap list/upload/AJAX volume per employee; 0 disables. Env overrides ui_configuration.
     */
    function itm_explorer_api_rate_limit_per_hour($uiConfig = null)
    {
        $raw = getenv('ITM_EXPLORER_API_RATE_LIMIT_PER_HOUR');
        if ($raw !== false && $raw !== '') {
            return itm_explorer_api_normalize_rate_limit_per_hour($raw);
        }

        if ($uiConfig === null && isset($GLOBALS['ui_config']) && is_array($GLOBALS['ui_config'])) {
            $uiConfig = $GLOBALS['ui_config'];
        }

        if (is_array($uiConfig) && array_key_exists('explorer_api_rate_limit_per_hour', $uiConfig)) {
            return itm_explorer_api_normalize_rate_limit_per_hour($uiConfig['explorer_api_rate_limit_per_hour']);
        }

        return itm_explorer_api_rate_limit_default_per_hour();
    }
}

if (!function_exists('itm_explorer_api_save_rate_limit_per_hour')) {
    function itm_explorer_api_save_rate_limit_per_hour($conn, $companyId, $employeeId, $limitPerHour)
    {
        $companyId = (int) $companyId;
        $employeeId = (int) $employeeId;
        if ($companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
            return false;
        }
        if (!function_exists('itm_ensure_ui_configuration_table') || !itm_ensure_ui_configuration_table($conn)) {
            return false;
        }

        $limit = itm_explorer_api_normalize_rate_limit_per_hour($limitPerHour);
        $sql = 'INSERT INTO ui_configuration (company_id, employee_id, explorer_api_rate_limit_per_hour)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE explorer_api_rate_limit_per_hour = VALUES(explorer_api_rate_limit_per_hour)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $limit);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok && function_exists('itm_get_ui_configuration')) {
            itm_get_ui_configuration($conn, $companyId, $employeeId, true);
        }

        return (bool) $ok;
    }
}

if (!function_exists('itm_explorer_api_rate_limit_window_seconds')) {
    function itm_explorer_api_rate_limit_window_seconds()
    {
        return 3600;
    }
}

if (!function_exists('itm_explorer_api_rate_limit_dir')) {
    function itm_explorer_api_rate_limit_dir()
    {
        return rtrim((string) ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR . 'explorer_api';
    }
}

if (!function_exists('itm_explorer_api_rate_limit_check')) {
    /**
     * @return array{ok:bool,error:string,remaining:?int,limit:int}
     */
    function itm_explorer_api_rate_limit_check($companyId, $employeeId, $recordAttempt = false, $uiConfig = null)
    {
        if (!function_exists('itm_qr_share_join_rate_limit_prune_events')) {
            require_once __DIR__ . '/itm_qr_share.php';
        }

        $limit = itm_explorer_api_rate_limit_per_hour($uiConfig);
        if ($limit <= 0) {
            return ['ok' => true, 'error' => '', 'remaining' => null, 'limit' => 0];
        }

        $companyId = (int) $companyId;
        $employeeId = (int) $employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return ['ok' => false, 'error' => 'Invalid session.', 'remaining' => 0, 'limit' => $limit];
        }

        $windowSeconds = itm_explorer_api_rate_limit_window_seconds();
        $dir = itm_explorer_api_rate_limit_dir();
        if (function_exists('itm_ensure_upload_directory')) {
            itm_ensure_upload_directory($dir, 'deny_all');
        } elseif (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . hash('sha256', $companyId . ':' . $employeeId) . '.json';
        $now = time();
        $events = [];
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['events']) && is_array($decoded['events'])) {
                $events = itm_qr_share_join_rate_limit_prune_events($decoded['events'], $now, $windowSeconds);
            }
        }

        $count = count($events);
        if ($count >= $limit) {
            return [
                'ok' => false,
                'error' => 'Explorer request limit reached (' . $limit . ' per hour). Try again later.',
                'remaining' => 0,
                'limit' => $limit,
            ];
        }

        if ($recordAttempt) {
            $events[] = $now;
            @file_put_contents(
                $path,
                json_encode(['events' => $events], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        }

        return [
            'ok' => true,
            'error' => '',
            'remaining' => max(0, $limit - $count - ($recordAttempt ? 1 : 0)),
            'limit' => $limit,
        ];
    }
}

if (!function_exists('itm_explorer_api_enforce_rate_limit_or_exit')) {
    /**
     * Consume one hourly slot and exit with HTTP 429 JSON when over cap.
     *
     * @return array{ok:bool,error:string,remaining:?int,limit:int}
     */
    function itm_explorer_api_enforce_rate_limit_or_exit($companyId, $employeeId)
    {
        $result = itm_explorer_api_rate_limit_check($companyId, $employeeId, true);
        if (!empty($result['ok'])) {
            return $result;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true, 429);
        }
        http_response_code(429);
        echo json_encode([
            'error' => (string) ($result['error'] ?? 'Rate limit exceeded.'),
            'rate_limit' => [
                'limit' => (int) ($result['limit'] ?? 0),
                'remaining' => (int) ($result['remaining'] ?? 0),
                'window_seconds' => itm_explorer_api_rate_limit_window_seconds(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
