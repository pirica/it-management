<?php
/**
 * Why: System Status tabs read cached JSON from system_status; Refresh collects live metrics and upserts rows.
 */

require_once __DIR__ . '/itm_system_status_native.php';
require_once __DIR__ . '/itm_system_status_powershell.php';
require_once __DIR__ . '/itm_system_status_storage.php';

if (!function_exists('itm_mysqli_stmt_fetch_assoc')) {
    require_once __DIR__ . '/itm_role_module_permissions.php';
}

if (!defined('ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID')) {
    define('ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID', 1);
}

function itm_system_status_cache_tab_keys(): array
{
    return ['monitoring', 'php_settings', 'database'];
}

/**
 * @return array{payload:array,refreshed_at:string|null}|null
 */
function itm_system_status_cache_get($conn, string $tabKey, int $companyId = ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID): ?array
{
    if (!$conn || !in_array($tabKey, itm_system_status_cache_tab_keys(), true)) {
        return null;
    }

    if ($companyId <= 0) {
        $companyId = ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT payload_json, updated_at FROM system_status WHERE tab_key = ? AND company_id = ? AND active = 1 LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'si', $tabKey, $companyId);
    if (!itm_system_status_safe_stmt_execute($stmt, [
        'fn' => 'itm_system_status_cache_get',
        'tab_key' => $tabKey,
        'company_id' => $companyId,
    ])) {
        return null;
    }
    $row = itm_mysqli_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        return null;
    }

    return [
        'payload' => $payload,
        'refreshed_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : null,
    ];
}

/**
 * @return bool
 */
function itm_system_status_cache_save($conn, string $tabKey, array $payload, int $companyId = ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID): bool
{
    if (!$conn || !in_array($tabKey, itm_system_status_cache_tab_keys(), true)) {
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log(sprintf(
            'itm_system_status_cache_save: json_encode failed (%s) tab_key=%s company_id=%d',
            json_last_error_msg(),
            $tabKey,
            $companyId
        ));
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO system_status (company_id, tab_key, payload_json, active)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), active = 1, updated_at = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iss', $companyId, $tabKey, $json);
    if (!itm_system_status_safe_stmt_execute($stmt, [
        'fn' => 'itm_system_status_cache_save',
        'tab_key' => $tabKey,
        'company_id' => $companyId,
    ])) {
        return false;
    }
    mysqli_stmt_close($stmt);

    return true;
}

/**
 * @return array{status:string,message?:string,data?:mixed}
 */
function itm_system_status_fetch_action_payload(string $action, $conn): array
{
    if (itm_system_status_prefers_native($action)) {
        $payload = itm_system_status_native_payload($action, $conn);
        return is_array($payload) ? $payload : ['status' => 'error', 'message' => 'Native payload unavailable.'];
    }

    if (itm_system_status_is_windows()) {
        return itm_system_status_run_powershell_action($action);
    }

    $payload = itm_system_status_native_payload($action, $conn);
    if ($payload === null) {
        return ['status' => 'error', 'message' => 'Action not supported on this platform.'];
    }

    return $payload;
}

function itm_system_status_collect_php_settings_payload(): array
{
    $extensions = get_loaded_extensions();
    sort($extensions);

    return [
        'version' => 'PHP ' . PHP_VERSION,
        'sapi' => php_sapi_name(),
        'binary' => PHP_BINARY,
        'ini_path' => php_ini_loaded_file() ?: '',
        'ini_values' => [
            'memory_limit' => (string)ini_get('memory_limit'),
            'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
            'post_max_size' => (string)ini_get('post_max_size'),
            'max_execution_time' => (string)ini_get('max_execution_time'),
        ],
        'extensions' => $extensions,
        'collected_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * @return array
 */
function itm_system_status_collect_database_payload($conn): array
{
    $activeDatabase = defined('DB_NAME') ? (string)DB_NAME : '';
    $mysqlRunning = ($conn && @mysqli_ping($conn));

    return [
        'active_database' => $activeDatabase,
        'mysql_running' => (bool)$mysqlRunning,
        'mysql_version' => ($conn ? (string)mysqli_get_server_info($conn) : 'Unavailable'),
        'mysql_service_name' => itm_system_status_is_windows()
            ? 'mysql / MariaDB (Windows service)'
            : 'mysqld',
        'mysql_display_name' => 'MySQL Server (active PHP connection)',
        'db_report' => itm_system_status_build_database_table_report($conn, $activeDatabase),
        'collected_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * @return array
 */
function itm_system_status_collect_monitoring_payload($conn): array
{
    $systemInfo = itm_system_status_fetch_action_payload('system_info', $conn);
    $cpuUsage = itm_system_status_fetch_action_payload('cpu_usage', $conn);

    return [
        'system_info' => $systemInfo,
        'cpu_usage' => $cpuUsage,
        'storage_report' => itm_system_status_build_storage_report($conn),
        'collected_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * @return array{ok:bool,errors:array<int,string>}
 */
function itm_system_status_refresh_tab($conn, string $tabKey, int $companyId = ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID): array
{
    $errors = [];
    $payload = [];

    switch ($tabKey) {
        case 'monitoring':
            $payload = itm_system_status_collect_monitoring_payload($conn);
            if (($payload['system_info']['status'] ?? '') !== 'success') {
                $errors[] = 'system_info: ' . (string)($payload['system_info']['message'] ?? 'collection failed');
            }
            if (($payload['cpu_usage']['status'] ?? '') !== 'success') {
                $errors[] = 'cpu_usage: ' . (string)($payload['cpu_usage']['message'] ?? 'collection failed');
            }
            break;
        case 'php_settings':
            $payload = itm_system_status_collect_php_settings_payload();
            break;
        case 'database':
            $payload = itm_system_status_collect_database_payload($conn);
            break;
        default:
            return ['ok' => false, 'errors' => ['Invalid tab key: ' . $tabKey]];
    }

    if (!itm_system_status_cache_save($conn, $tabKey, $payload, $companyId)) {
        $errors[] = 'Failed to save cache for tab ' . $tabKey;
    }

    return ['ok' => empty($errors), 'errors' => $errors];
}

/**
 * @return array{ok:bool,errors:array<int,string>}
 */
function itm_system_status_refresh_all($conn, int $companyId = ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID): array
{
    $errors = [];
    foreach (itm_system_status_cache_tab_keys() as $tabKey) {
        $result = itm_system_status_refresh_tab($conn, $tabKey, $companyId);
        if (!$result['ok']) {
            $errors = array_merge($errors, $result['errors']);
        }
    }

    return ['ok' => empty($errors), 'errors' => $errors];
}
