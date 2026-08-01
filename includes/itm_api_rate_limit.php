<?php
/**
 * API key lookup and per-tenant rate limiting stored on ui_configuration rows.
 */

/**
 * Why: Tier names are persisted as an ENUM; keep one canonical list for validation and docs.
 */
function itm_api_allowed_tiers() {
    return ['Free', 'Basic', 'Pro', 'Enterprise'];
}

/**
 * Why: Free tier is intentionally uncapped; paid tiers use hourly quotas.
 */
function itm_api_tier_is_unlimited($tier) {
    return itm_api_normalize_tier($tier) === 'Free';
}

/**
 * Why: Only paid tiers require a programmatic API key; Free may use session identity.
 */
function itm_api_tier_requires_api_key($tier) {
    return !itm_api_tier_is_unlimited($tier);
}

/**
 * Why: Hourly request caps are tier-driven so billing can scale without code changes per customer.
 * Returns 0 when the tier has no cap (Free).
 */
function itm_api_tier_hourly_limit($tier) {
    $normalizedTier = itm_api_normalize_tier($tier);
    if (itm_api_tier_is_unlimited($normalizedTier)) {
        return 0;
    }

    $limits = [
        'Basic' => 300,
        'Pro' => 1000,
        'Enterprise' => 10000,
    ];

    return (int)($limits[$normalizedTier] ?? 0);
}

function itm_api_rate_limit_window_seconds() {
    return 3600;
}

function itm_api_normalize_tier($tier) {
    $value = trim((string)$tier);
    return in_array($value, itm_api_allowed_tiers(), true) ? $value : 'Free';
}

/**
 * Generates a high-entropy API key for ui_configuration.api_key.
 */
function itm_api_generate_key() {
    try {
        return bin2hex(random_bytes(24));
    } catch (Exception $exception) {
        return sha1(uniqid('itm_api_', true));
    }
}

/**
 * Reads API key from X-API-Key header or api_key query/body parameter.
 */
function itm_api_extract_request_key() {
    $headerKey = '';
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $headerKey = trim((string)$_SERVER['HTTP_X_API_KEY']);
    }
    if ($headerKey !== '') {
        return $headerKey;
    }

    if (isset($_GET['api_key'])) {
        return trim((string)$_GET['api_key']);
    }

    if (isset($_POST['api_key'])) {
        return trim((string)$_POST['api_key']);
    }

    return '';
}

function itm_api_send_json_response($payload, $httpCode = 200) {
    if (!headers_sent()) {
        http_response_code((int)$httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function itm_api_send_json_error($httpCode, $message, array $extra = []) {
    $payload = array_merge(['ok' => false, 'error' => (string)$message], $extra);
    itm_api_send_json_response($payload, (int)$httpCode);
}

/**
 * Loads the ui_configuration row for a presented API key.
 */
function itm_api_lookup_configuration_by_key($conn, $apiKey) {
    if (!($conn instanceof mysqli)) {
        return null;
    }

    $apiKey = trim((string)$apiKey);
    if ($apiKey === '' || strlen($apiKey) > 191) {
        return null;
    }

    if (!function_exists('itm_ensure_ui_configuration_table') || !itm_ensure_ui_configuration_table($conn)) {
        return null;
    }

    $sql = 'SELECT id, company_id, employee_id, api_key, api_key_is_active, api_key_last_used_at,
                   rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
            FROM ui_configuration
            WHERE api_key = ?
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $apiKey);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
}

/**
 * Loads ui_configuration for the active company/user pair (session or explicit ids).
 */
function itm_api_lookup_configuration_by_user($conn, $companyId, $employeeId) {
    if (!($conn instanceof mysqli)) {
        return null;
    }

    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($companyId <= 0 || $employeeId <= 0) {
        return null;
    }

    if (!function_exists('itm_ensure_ui_configuration_table') || !itm_ensure_ui_configuration_table($conn)) {
        return null;
    }

    $sql = 'SELECT id, company_id, employee_id, api_key, api_key_is_active, api_key_last_used_at,
                   rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
            FROM ui_configuration
            WHERE company_id = ? AND employee_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
}

/**
 * Why: Free-tier users without a persisted row still default to unlimited access.
 */
function itm_api_row_employee_id(array $row) {
    return (int)($row['employee_id'] ?? 0);
}

/**
 * Why: Free-tier users without a persisted row still default to unlimited access.
 */
function itm_api_default_free_configuration_row($companyId, $employeeId) {
    return [
        'id' => 0,
        'company_id' => (int)$companyId,
        'employee_id' => (int)$employeeId,
        'api_key' => '',
        'api_key_is_active' => 1,
        'api_key_last_used_at' => null,
        'rate_limit_window_start' => 0,
        'rate_limit_request_count' => 0,
        'rate_limit_enabled' => 0,
        'tier' => 'Free',
    ];
}

function itm_api_active_session_company_id() {
    return (int)($_SESSION['company_id'] ?? 0);
}

function itm_api_active_session_employee_id() {
    return (int)($_SESSION['employee_id'] ?? 0);
}

/**
 * Resolves rate-limit row from API key or, on Free tier, the authenticated session.
 */
function itm_api_resolve_rate_limit_row($conn) {
    $apiKey = itm_api_extract_request_key();
    if ($apiKey !== '') {
        return itm_api_lookup_configuration_by_key($conn, $apiKey);
    }

    $companyId = itm_api_active_session_company_id();
    $employeeId = itm_api_active_session_employee_id();
    if ($companyId <= 0 || $employeeId <= 0) {
        return null;
    }

    $row = itm_api_lookup_configuration_by_user($conn, $companyId, $employeeId);
    if ($row === null) {
        return itm_api_default_free_configuration_row($companyId, $employeeId);
    }

    if (itm_api_tier_requires_api_key($row['tier'] ?? 'Free')) {
        return null;
    }

    return $row;
}

/**
 * Builds the JSON payload for scripts/api.php?rate_limit=1.
 */
function itm_api_build_rate_limit_probe_payload(array $row) {
    $status = itm_api_rate_limit_status_from_row($row);
    $tier = (string)($status['tier'] ?? 'Free');

    return [
        'ok' => true,
        'company_id' => (int)($row['company_id'] ?? 0),
        'employee_id' => itm_api_row_employee_id($row),
        'api_key_required' => itm_api_tier_requires_api_key($tier),
        'api_key_is_active' => (int)($row['api_key_is_active'] ?? 0),
        'api_key_last_used_at' => $row['api_key_last_used_at'] ?? null,
        'tier' => $tier,
        'unlimited' => !empty($status['unlimited']),
        'rate_limit_enabled' => $status['rate_limit_enabled'],
        'limit' => $status['limit'],
        'remaining' => $status['remaining'],
        'window_seconds' => $status['window_seconds'],
        'window_start' => $status['window_start'],
        'reset_at' => $status['reset_at'],
        'request_count' => $status['request_count'],
    ];
}

/**
 * Persists api_key for the active company/user row (creates row when missing).
 */
function itm_api_save_user_api_key($conn, $companyId, $employeeId, $apiKey) {
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $apiKey = trim((string)$apiKey);

    if ($companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return false;
    }
    if ($apiKey !== '' && strlen($apiKey) > 191) {
        return false;
    }
    if (!function_exists('itm_ensure_ui_configuration_table') || !itm_ensure_ui_configuration_table($conn)) {
        return false;
    }

    $existingRow = itm_api_lookup_configuration_by_user($conn, $companyId, $employeeId);
    $existingTier = itm_api_normalize_tier($existingRow['tier'] ?? 'Free');
    if (!itm_api_tier_requires_api_key($existingTier) && $apiKey !== '') {
        return false;
    }

    $sql = 'INSERT INTO ui_configuration (company_id, employee_id, api_key)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE api_key = VALUES(api_key)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $employeeId, $apiKey);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$ok;
}

/**
 * Returns rate-limit state without incrementing the counter (probe/status endpoint).
 */
function itm_api_rate_limit_status_from_row(array $row) {
    $tier = itm_api_normalize_tier($row['tier'] ?? 'Free');
    $unlimited = itm_api_tier_is_unlimited($tier);
    $limit = itm_api_tier_hourly_limit($tier);
    $windowSeconds = itm_api_rate_limit_window_seconds();
    $enabled = !$unlimited && ((int)($row['rate_limit_enabled'] ?? 1) === 1);
    $windowStart = (int)($row['rate_limit_window_start'] ?? 0);
    $requestCount = (int)($row['rate_limit_request_count'] ?? 0);
    $now = time();

    if ($unlimited) {
        return [
            'tier' => $tier,
            'unlimited' => true,
            'rate_limit_enabled' => 0,
            'limit' => 0,
            'remaining' => null,
            'window_seconds' => $windowSeconds,
            'window_start' => 0,
            'reset_at' => 0,
            'request_count' => $requestCount,
        ];
    }

    if ($windowStart <= 0 || ($now - $windowStart) >= $windowSeconds) {
        $requestCount = 0;
        $windowStart = $now;
    }

    $remaining = $enabled ? max(0, $limit - $requestCount) : $limit;

    return [
        'tier' => $tier,
        'unlimited' => false,
        'rate_limit_enabled' => $enabled ? 1 : 0,
        'limit' => $limit,
        'remaining' => $remaining,
        'window_seconds' => $windowSeconds,
        'window_start' => $windowStart,
        'reset_at' => $windowStart + $windowSeconds,
        'request_count' => $requestCount,
    ];
}

/**
 * Atomically advances the sliding hourly window and increments usage when allowed.
 */
function itm_api_consume_rate_limit($conn, array $row) {
    if (!($conn instanceof mysqli)) {
        return ['allowed' => false, 'error' => 'Database unavailable.'];
    }

    $configId = (int)($row['id'] ?? 0);
    $companyId = (int)($row['company_id'] ?? 0);
    $employeeId = itm_api_row_employee_id($row);
    if ($companyId <= 0 || $employeeId <= 0) {
        return ['allowed' => false, 'error' => 'Invalid API configuration row.'];
    }

    $tier = itm_api_normalize_tier($row['tier'] ?? 'Free');
    $unlimited = itm_api_tier_is_unlimited($tier);
    $limit = itm_api_tier_hourly_limit($tier);
    $windowSeconds = itm_api_rate_limit_window_seconds();
    $enabled = !$unlimited && ((int)($row['rate_limit_enabled'] ?? 1) === 1);
    $now = time();

    if ($unlimited || !$enabled) {
        if ($configId > 0) {
            $touchSql = 'UPDATE ui_configuration
                         SET api_key_last_used_at = CURRENT_TIMESTAMP
                         WHERE id = ? AND company_id = ? AND employee_id = ?
                         LIMIT 1';
            $touchStmt = mysqli_prepare($conn, $touchSql);
            if ($touchStmt) {
                mysqli_stmt_bind_param($touchStmt, 'iii', $configId, $companyId, $employeeId);
                mysqli_stmt_execute($touchStmt);
                mysqli_stmt_close($touchStmt);
            }
        }

        return [
            'allowed' => true,
            'tier' => $tier,
            'unlimited' => $unlimited,
            'limit' => 0,
            'remaining' => null,
            'reset_at' => 0,
            'rate_limit_enabled' => 0,
        ];
    }

    if ($configId <= 0) {
        return ['allowed' => false, 'error' => 'Invalid API configuration row.'];
    }

    mysqli_begin_transaction($conn);

    $selectSql = 'SELECT rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier
                  FROM ui_configuration
                  WHERE id = ? AND company_id = ? AND employee_id = ?
                  FOR UPDATE';
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        mysqli_rollback($conn);
        return ['allowed' => false, 'error' => 'Unable to evaluate rate limit.'];
    }

    mysqli_stmt_bind_param($selectStmt, 'iii', $configId, $companyId, $employeeId);
    mysqli_stmt_execute($selectStmt);
    $selectResult = mysqli_stmt_get_result($selectStmt);
    $lockedRow = $selectResult ? mysqli_fetch_assoc($selectResult) : null;
    mysqli_stmt_close($selectStmt);

    if (!is_array($lockedRow)) {
        mysqli_rollback($conn);
        return ['allowed' => false, 'error' => 'API configuration row not found.'];
    }

    $tier = itm_api_normalize_tier($lockedRow['tier'] ?? $tier);
    $limit = itm_api_tier_hourly_limit($tier);
    $windowStart = (int)($lockedRow['rate_limit_window_start'] ?? 0);
    $requestCount = (int)($lockedRow['rate_limit_request_count'] ?? 0);

    if ($windowStart <= 0 || ($now - $windowStart) >= $windowSeconds) {
        $windowStart = $now;
        $requestCount = 0;
    }

    if ($requestCount >= $limit) {
        mysqli_rollback($conn);
        return [
            'allowed' => false,
            'error' => 'Rate limit exceeded.',
            'tier' => $tier,
            'limit' => $limit,
            'remaining' => 0,
            'reset_at' => $windowStart + $windowSeconds,
            'rate_limit_enabled' => 1,
        ];
    }

    $nextCount = $requestCount + 1;
    $updateSql = 'UPDATE ui_configuration
                  SET rate_limit_window_start = ?,
                      rate_limit_request_count = ?,
                      api_key_last_used_at = CURRENT_TIMESTAMP
                  WHERE id = ? AND company_id = ? AND employee_id = ?
                  LIMIT 1';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        mysqli_rollback($conn);
        return ['allowed' => false, 'error' => 'Unable to persist rate limit counters.'];
    }

    mysqli_stmt_bind_param($updateStmt, 'iiiii', $windowStart, $nextCount, $configId, $companyId, $employeeId);
    $updateOk = mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    if (!$updateOk) {
        mysqli_rollback($conn);
        return ['allowed' => false, 'error' => 'Unable to persist rate limit counters.'];
    }

    mysqli_commit($conn);

    return [
        'allowed' => true,
        'tier' => $tier,
        'unlimited' => false,
        'limit' => $limit,
        'remaining' => max(0, $limit - $nextCount),
        'reset_at' => $windowStart + $windowSeconds,
        'rate_limit_enabled' => 1,
    ];
}

/**
 * Validates API key activity and enforces rate limits; exits with JSON on failure.
 */
function itm_api_enforce_rate_limit_or_exit($conn) {
    $apiKey = itm_api_extract_request_key();
    $row = itm_api_resolve_rate_limit_row($conn);
    if ($row === null) {
        if ($apiKey !== '') {
            itm_api_send_json_error(401, 'Invalid API key.');
        }
        itm_api_send_json_error(
            401,
            'Missing API key. Paid tiers require X-API-Key or api_key; Free tier may use an authenticated session without a key.'
        );
    }

    if ($apiKey !== '' && (int)($row['api_key_is_active'] ?? 0) !== 1) {
        itm_api_send_json_error(403, 'API key is inactive.');
    }

    $rateResult = itm_api_consume_rate_limit($conn, $row);
    if (empty($rateResult['allowed'])) {
        itm_api_send_json_error(429, (string)($rateResult['error'] ?? 'Rate limit exceeded.'), [
            'tier' => $rateResult['tier'] ?? itm_api_normalize_tier($row['tier'] ?? 'Free'),
            'limit' => (int)($rateResult['limit'] ?? 0),
            'remaining' => (int)($rateResult['remaining'] ?? 0),
            'reset_at' => (int)($rateResult['reset_at'] ?? 0),
        ]);
    }

    return array_merge($row, $rateResult);
}

/**
 * JSON probe used by scripts/api.php?rate_limit=1 (does not consume a request).
 */
function itm_api_handle_rate_limit_probe_request($conn) {
    $apiKey = itm_api_extract_request_key();
    $row = itm_api_resolve_rate_limit_row($conn);
    if ($row === null) {
        if ($apiKey !== '') {
            itm_api_send_json_error(401, 'Invalid API key.');
        }
        itm_api_send_json_error(
            401,
            'Missing API key. Paid tiers require X-API-Key or api_key; Free tier may use an authenticated session without a key.'
        );
    }

    if ($apiKey !== '' && (int)($row['api_key_is_active'] ?? 0) !== 1) {
        itm_api_send_json_error(403, 'API key is inactive.');
    }

    itm_api_send_json_response(itm_api_build_rate_limit_probe_payload($row));
}
