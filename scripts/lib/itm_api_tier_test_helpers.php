<?php
/**
 * Shared disposable ui_configuration rows for API tier regression scripts.
 */

if (!defined('ITM_APITEST_COMPANY_ID')) {
    define('ITM_APITEST_COMPANY_ID', 1);
}

/**
 * Why: Each tier script uses a dedicated user_id slot so parallel runs do not collide.
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

function itm_apitest_cleanup_configuration($conn, $companyId, $userId) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $companyId = (int)$companyId;
    $userId = (int)$userId;
    if ($companyId <= 0 || $userId <= 0) {
        return false;
    }

    $stmt = mysqli_prepare($conn, 'DELETE FROM ui_configuration WHERE company_id = ? AND user_id = ?');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$ok;
}

/**
 * Inserts a disposable ui_configuration row for tier testing.
 */
function itm_apitest_seed_configuration($conn, $companyId, $userId, $tier, array $overrides = []) {
    if (!($conn instanceof mysqli)) {
        return null;
    }

    $companyId = (int)$companyId;
    $userId = (int)$userId;
    if ($companyId <= 0 || $userId <= 0) {
        return null;
    }

    if (!function_exists('itm_ensure_ui_configuration_table') || !itm_ensure_ui_configuration_table($conn)) {
        return null;
    }

    itm_apitest_cleanup_configuration($conn, $companyId, $userId);

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
                company_id, user_id, api_key, api_key_is_active,
                rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iisiiiss',
        $companyId,
        $userId,
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
        'user_id' => $userId,
        'api_key' => $apiKey,
        'api_key_is_active' => $apiKeyIsActive,
        'rate_limit_window_start' => $windowStart,
        'rate_limit_request_count' => $requestCount,
        'rate_limit_enabled' => $rateLimitEnabled,
        'tier' => $tier,
    ];
}

function itm_apitest_reload_configuration($conn, $configId, $companyId, $userId) {
    if (!($conn instanceof mysqli)) {
        return null;
    }

    $configId = (int)$configId;
    $companyId = (int)$companyId;
    $userId = (int)$userId;

    $sql = 'SELECT id, company_id, user_id, api_key, api_key_is_active, api_key_last_used_at,
                   rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
            FROM ui_configuration
            WHERE id = ? AND company_id = ? AND user_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'iii', $configId, $companyId, $userId);
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
    if ($apiKey === '') {
        return '';
    }

    $baseUrl = rtrim(trim((string)$baseUrl), '/');
    if ($baseUrl === '') {
        $baseUrl = 'http://localhost/it-management';
    }

    return $baseUrl . '/scripts/api.php?rate_limit=1&api_key=' . rawurlencode($apiKey);
}

function itm_apitest_print_probe_links($apiKey, $tierLabel = '') {
    $url = itm_apitest_rate_limit_probe_url($apiKey);
    if ($url === '') {
        return;
    }

    $prefix = $tierLabel !== '' ? $tierLabel . ' ' : '';
    itm_apitest_output_line('[INFO] Auto-generated ' . $prefix . 'API key: ' . $apiKey, 'info');
    itm_apitest_output_line('[INFO] Browser probe URL: ' . $url, 'info');
    itm_apitest_output_line('[INFO] Key stays in ui_configuration until the next apitest run for this slot.', 'info');
}

function itm_apitest_probe_rate_limit_http($apiKey, $baseUrl = '') {
    $apiKey = trim((string)$apiKey);
    if ($apiKey === '') {
        return null;
    }

    $url = itm_apitest_rate_limit_probe_url($apiKey, $baseUrl);
    if ($url === '') {
        return null;
    }

    $body = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $apiKey, 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if (!is_string($body) || $body === '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "X-API-Key: {$apiKey}\r\nAccept: application/json\r\n",
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
