<?php
/**
 * Shared disposable ui_configuration rows for API tier regression scripts.
 */

$itmApitestScriptTestEmployee = __DIR__ . '/itm_script_test_employee.php';
if (is_file($itmApitestScriptTestEmployee)) {
    require_once $itmApitestScriptTestEmployee;
}

if (!defined('ITM_APITEST_COMPANY_ID')) {
    define('ITM_APITEST_COMPANY_ID', 1);
}

/**
 * Why: Each tier script uses a dedicated employee_id slot so parallel runs do not collide.
 */
function itm_apitest_disposable_user_id($slot) {
    $slot = (int)$slot;
    if ($slot < 1 || $slot > 99) {
        $slot = 1;
    }

    return 999900 + $slot;
}

function itm_apitest_output_line($message, $type = '') {
    $nl = function_exists('itm_script_output_nl') ? itm_script_output_nl() : "\n";
    $line = (string)$message;
    if ($type !== '' && function_exists('colorText')) {
        $line = colorText($line, $type);
    } elseif (preg_match('/^\[(PASS|OK)\]/', $line) && function_exists('colorText')) {
        $line = colorText($line, 'pass');
    } elseif (preg_match('/^\[FAIL\]/', $line) && function_exists('colorText')) {
        $line = colorText($line, 'fail');
    } elseif (preg_match('/^\[INFO\]/', $line) && function_exists('colorText')) {
        $line = colorText($line, 'info');
    }

    echo $line . $nl;
}

function itm_apitest_assert($label, $condition, $detail = '') {
    if ($condition) {
        itm_apitest_output_line('[PASS] ' . $label);
        return true;
    }

    $message = '[FAIL] ' . $label;
    if ($detail !== '') {
        $message .= ' — ' . $detail;
    }
    itm_apitest_output_line($message, 'fail');
    return false;
}

function itm_apitest_clear_audit_context($conn) {
    if (function_exists('itm_script_test_employee_clear_audit_context')) {
        return itm_script_test_employee_clear_audit_context($conn);
    }
    if (!($conn instanceof mysqli)) {
        return false;
    }

    mysqli_query($conn, 'SET @app_employee_id = NULL');
    mysqli_query($conn, 'SET @app_company_id = NULL');
    mysqli_query($conn, "SET @app_username = ''");
    mysqli_query($conn, "SET @app_email = NULL");

    return true;
}

function itm_apitest_disposable_username($employeeId) {
    return 'apitest-user-' . (int)$employeeId;
}

function itm_apitest_is_disposable_slot_employee_id($employeeId) {
    $employeeId = (int)$employeeId;
    return $employeeId >= 999901 && $employeeId <= 999999;
}

/**
 * Run a callback with temporary disposable test-user session context; restores prior $_SESSION after.
 */
function itm_apitest_run_with_session_context($companyId, $employeeId, callable $callback) {
    if (!function_exists('itm_script_with_test_session_context')) {
        require_once __DIR__ . '/itm_script_bootstrap.php';
    }

    return itm_script_with_test_session_context(
        $companyId,
        $employeeId,
        itm_apitest_disposable_username($employeeId),
        $callback
    );
}

function itm_apitest_resolve_employment_status_id($conn, $companyId) {
    if (!($conn instanceof mysqli)) {
        return 0;
    }

    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id FROM employee_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (is_array($row) && (int)$row['id'] > 0) {
            return (int)$row['id'];
        }
    }

    $fallback = mysqli_prepare($conn, 'SELECT id FROM employee_statuses WHERE company_id = ? LIMIT 1');
    if (!$fallback) {
        return 0;
    }

    mysqli_stmt_bind_param($fallback, 'i', $companyId);
    mysqli_stmt_execute($fallback);
    $res = mysqli_stmt_get_result($fallback);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($fallback);

    return is_array($row) ? (int)$row['id'] : 0;
}

function itm_apitest_report_seed_failure($conn, $companyId, $employeeId) {
    itm_apitest_output_line('[DEBUG] Failure diagnostic checks:', 'warn');
    if (!$conn || !($conn instanceof mysqli)) {
        itm_apitest_output_line('  -> Connection is invalid or not connected.', 'fail');
        return;
    }

    itm_apitest_output_line('  -> Database connection is valid.', 'pass');
    $mysqlError = mysqli_error($conn);
    if ($mysqlError !== '') {
        itm_apitest_output_line('  -> Recent MySQL Error: ' . $mysqlError, 'fail');
        itm_apitest_output_line('  -> Recent MySQL Error Code: ' . mysqli_errno($conn), 'fail');
    }

    $employeeId = (int)$employeeId;
    $companyId = (int)$companyId;
    $checkEmp = mysqli_prepare($conn, 'SELECT 1 FROM employees WHERE id = ? LIMIT 1');
    if ($checkEmp) {
        mysqli_stmt_bind_param($checkEmp, 'i', $employeeId);
        mysqli_stmt_execute($checkEmp);
        $res = mysqli_stmt_get_result($checkEmp);
        $exists = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($checkEmp);
        if ($exists) {
            itm_apitest_output_line('  -> Target employee ID ' . $employeeId . ' exists in employees table.', 'pass');
        } else {
            itm_apitest_output_line(
                '  -> Target employee ID ' . $employeeId . ' DOES NOT exist in employees table. '
                . 'Audit triggers reject stale @app_employee_id when seeding ui_configuration.',
                'fail'
            );
        }
    }

    $checkComp = mysqli_prepare($conn, 'SELECT 1 FROM companies WHERE id = ? LIMIT 1');
    if ($checkComp) {
        mysqli_stmt_bind_param($checkComp, 'i', $companyId);
        mysqli_stmt_execute($checkComp);
        $res = mysqli_stmt_get_result($checkComp);
        $exists = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($checkComp);
        if ($exists) {
            itm_apitest_output_line('  -> Target company ID ' . $companyId . ' exists in companies table.', 'pass');
        } else {
            itm_apitest_output_line(
                '  -> Target company ID ' . $companyId . ' DOES NOT exist in companies table.',
                'fail'
            );
        }
    }
}

function itm_apitest_cleanup_configuration($conn, $companyId, $employeeId) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($companyId <= 0 || $employeeId <= 0) {
        return false;
    }

    itm_apitest_clear_audit_context($conn);

    $stmt = mysqli_prepare($conn, 'DELETE FROM ui_configuration WHERE company_id = ? AND employee_id = ?');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$ok;
}

/**
 * Ensures the required employee row exists in the employees table.
 * satisfying the Foreign Key constraints on ui_configuration.employee_id safely.
 */
function itm_apitest_ensure_employee_exists($conn, $companyId, $employeeId) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($companyId <= 0 || $employeeId <= 0) {
        return false;
    }

    $checkStmt = mysqli_prepare($conn, 'SELECT 1 FROM employees WHERE id = ? LIMIT 1');
    if (!$checkStmt) {
        return false;
    }

    mysqli_stmt_bind_param($checkStmt, 'i', $employeeId);
    mysqli_stmt_execute($checkStmt);
    $res = mysqli_stmt_get_result($checkStmt);
    $exists = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($checkStmt);
    if ($exists) {
        return true;
    }

    $statusId = itm_apitest_resolve_employment_status_id($conn, $companyId);
    if ($statusId <= 0) {
        return false;
    }

    $username = itm_apitest_disposable_username($employeeId);
    $email = $username . '@apitest.example.com';
    $firstName = 'Apitest';
    $lastName = 'User';

    // Why: Stale session @app_employee_id (e.g. prior slot id after row delete) makes employees/ui_configuration
    // audit triggers fail FK audit_logs_ibfk_employee before the disposable row exists.
    itm_apitest_clear_audit_context($conn);

    $insertSql = 'INSERT INTO employees (id, company_id, first_name, last_name, username, work_email, employment_status_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?)';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return false;
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        'iissssi',
        $employeeId,
        $companyId,
        $firstName,
        $lastName,
        $username,
        $email,
        $statusId
    );
    $insertOk = mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);
    if (!$insertOk) {
        return false;
    }

    $verifyStmt = mysqli_prepare($conn, 'SELECT 1 FROM employees WHERE id = ? LIMIT 1');
    if (!$verifyStmt) {
        return false;
    }

    mysqli_stmt_bind_param($verifyStmt, 'i', $employeeId);
    mysqli_stmt_execute($verifyStmt);
    $verifyRes = mysqli_stmt_get_result($verifyStmt);
    $verified = $verifyRes && mysqli_num_rows($verifyRes) > 0;
    mysqli_stmt_close($verifyStmt);

    return $verified;
}

/**
 * Inserts a disposable ui_configuration row for tier testing.
 */
function itm_apitest_seed_configuration($conn, $companyId, $employeeId, $tier, array $overrides = []) {
    if (!($conn instanceof mysqli)) {
        return null;
    }

    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($companyId <= 0 || $employeeId <= 0) {
        return null;
    }

    // Ensure target employee exists in employees table (Foreign Key compliance)
    if (!itm_apitest_ensure_employee_exists($conn, $companyId, $employeeId)) {
        return null;
    }

    if (!function_exists('itm_ensure_ui_configuration_table') || !itm_ensure_ui_configuration_table($conn)) {
        return null;
    }

    itm_apitest_cleanup_configuration($conn, $companyId, $employeeId);

    $username = itm_apitest_disposable_username($employeeId);
    if (function_exists('itm_script_test_employee_set_audit_context')) {
        itm_script_test_employee_set_audit_context($conn, $employeeId, $username, $companyId);
    }

    $tier = function_exists('itm_api_normalize_tier')
        ? itm_api_normalize_tier($tier)
        : trim((string)$tier);
    if (isset($overrides['api_key'])) {
        $apiKey = trim((string)$overrides['api_key']);
    } elseif (function_exists('itm_api_generate_key')) {
        $apiKey = itm_api_generate_key();
    } else {
        $apiKey = 'apitest-' . strtolower($tier) . '-' . bin2hex(random_bytes(8));
    }
    $rateLimitEnabled = isset($overrides['rate_limit_enabled']) ? (int)$overrides['rate_limit_enabled'] : 1;
    $windowStart = isset($overrides['rate_limit_window_start']) ? (int)$overrides['rate_limit_window_start'] : time();
    $requestCount = isset($overrides['rate_limit_request_count']) ? (int)$overrides['rate_limit_request_count'] : 0;
    $apiKeyIsActive = isset($overrides['api_key_is_active']) ? (int)$overrides['api_key_is_active'] : 1;

    $sql = 'INSERT INTO ui_configuration (
                company_id, employee_id, api_key, api_key_is_active,
                rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iisiiiis',
        $companyId,
        $employeeId,
        $apiKey,
        $apiKeyIsActive,
        $windowStart,
        $requestCount,
        $rateLimitEnabled,
        $tier
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $configId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if ($configId <= 0) {
        return null;
    }

    return [
        'id' => $configId,
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'api_key' => $apiKey,
        'api_key_is_active' => $apiKeyIsActive,
        'rate_limit_window_start' => $windowStart,
        'rate_limit_request_count' => $requestCount,
        'rate_limit_enabled' => $rateLimitEnabled,
        'tier' => $tier,
    ];
}

function itm_apitest_reload_configuration($conn, $configId, $companyId, $employeeId) {
    if (!($conn instanceof mysqli)) {
        return null;
    }

    $configId = (int)$configId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;

    $sql = 'SELECT id, company_id, employee_id, api_key, api_key_is_active, api_key_last_used_at,
                   rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
            FROM ui_configuration
            WHERE id = ? AND company_id = ? AND employee_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'iii', $configId, $companyId, $employeeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
}

/**
 * Builds the browser/curl URL for scripts/api.php rate-limit probe with embedded api_key.
 */
function itm_apitest_rate_limit_probe_url($apiKey, $baseUrl = '') {
    $apiKey = trim((string)$apiKey);

    $baseUrl = rtrim(trim((string)$baseUrl), '/');
    if ($baseUrl === '') {
        $baseUrl = 'http://localhost/it-management';
    }

    if ($apiKey === '') {
        return $baseUrl . '/scripts/api.php?rate_limit=1';
    }

    return $baseUrl . '/scripts/api.php?rate_limit=1&api_key=' . rawurlencode($apiKey);
}

function itm_apitest_print_probe_links($apiKey, $tierLabel = '', $includeKeyless = false) {
    $apiKey = trim((string)$apiKey);
    $prefix = $tierLabel !== '' ? $tierLabel . ' ' : '';

    if ($includeKeyless || $apiKey === '') {
        $keylessUrl = itm_apitest_rate_limit_probe_url('');
        if ($keylessUrl !== '') {
            itm_apitest_output_line('[INFO] Browser probe URL (session, no API key): ' . $keylessUrl, 'info');
        }
    }

    if ($apiKey !== '') {
        $url = itm_apitest_rate_limit_probe_url($apiKey);
        if ($url !== '') {
            itm_apitest_output_line('[INFO] Auto-generated ' . $prefix . 'API key: ' . $apiKey, 'info');
            itm_apitest_output_line('[INFO] Browser probe URL: ' . $url, 'info');
        }
    }

    itm_apitest_output_line('[INFO] Key stays in ui_configuration until the next apitest run for this slot.', 'info');
}

function itm_apitest_probe_rate_limit_http($apiKey, $baseUrl = '', $phpSessionId = '') {
    $apiKey = trim((string)$apiKey);
    $phpSessionId = trim((string)$phpSessionId);

    $url = itm_apitest_rate_limit_probe_url($apiKey, $baseUrl);
    if ($url === '') {
        return null;
    }

    $body = null;
    $httpCode = 0;
    $headers = ['Accept: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }
    if ($phpSessionId !== '') {
        $sessionName = function_exists('session_name') ? session_name() : 'PHPSESSID';
        if ($sessionName === '') {
            $sessionName = 'PHPSESSID';
        }
        $headers[] = 'Cookie: ' . $sessionName . '=' . $phpSessionId;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if (!is_string($body) || $body === '') {
        $headerLines = $headers;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $fallbackBody = @file_get_contents($url, false, $context);
        if (is_string($fallbackBody) && $fallbackBody !== '') {
            $body = $fallbackBody;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $matches)) {
                $httpCode = (int)$matches[1];
            }
        }
    }

    if (!is_string($body) || $body === '') {
        return null;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            '_http_code' => $httpCode,
            '_raw_body' => substr(trim($body), 0, 240),
            'ok' => false,
            'error' => 'Non-JSON HTTP response (is Apache serving /it-management?)',
        ];
    }

    $decoded['_http_code'] = $httpCode;
    $decoded['_probe_url'] = $url;
    return $decoded;
}

function itm_apitest_publish_http_session($companyId, $employeeId) {
    if (!function_exists('itm_script_publish_isolated_http_session')) {
        require_once __DIR__ . '/itm_script_bootstrap.php';
    }

    return itm_script_publish_isolated_http_session(
        $companyId,
        $employeeId,
        itm_apitest_disposable_username($employeeId)
    );
}
