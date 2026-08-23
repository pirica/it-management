<?php
/**
 * Generic temporary QR / 6-digit share sessions (SpeedyShare-style).
 */

function itm_qr_share_session_ttl_seconds()
{
    return 1800;
}

function itm_qr_share_code_length()
{
    return 8;
}

function itm_qr_share_legacy_code_length()
{
    return 6;
}

/**
 * Vault- and file-heavy modules accept token links only (no numeric join codes).
 *
 * @return string[]
 */
function itm_qr_share_token_only_module_slugs()
{
    return [
        'passwords',
        'private_contacts',
        'explorer',
        'notes',
        'webmail',
    ];
}

function itm_qr_share_module_allows_numeric_code($moduleSlugOrLegacyTable)
{
    $moduleSlug = itm_qr_share_resolve_module_slug($moduleSlugOrLegacyTable);
    if ($moduleSlug === '') {
        return false;
    }

    return !in_array($moduleSlug, itm_qr_share_token_only_module_slugs(), true);
}

function itm_qr_share_join_rate_limit_ip_dir()
{
    return rtrim((string) ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'files'
        . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR . 'qr_share_join';
}

function itm_qr_share_join_rate_limit_prune_events(array $events, $now, $windowSeconds)
{
    $fresh = [];
    foreach ($events as $ts) {
        $ts = (int) $ts;
        if ($ts > 0 && ($now - $ts) < $windowSeconds) {
            $fresh[] = $ts;
        }
    }

    return $fresh;
}

/**
 * Why: Public join.php POSTs are unauthenticated — throttle per client IP before DB lookup.
 *
 * @return array{ok:bool,error?:string,count?:int,max?:int}
 */
function itm_qr_share_join_rate_limit_check($recordAttempt = false, $maxAttempts = 20, $windowSeconds = 900)
{
    $maxAttempts = max(1, (int) $maxAttempts);
    $windowSeconds = max(60, (int) $windowSeconds);
    $ip = function_exists('itm_get_client_ip_address')
        ? trim((string) itm_get_client_ip_address())
        : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        $ip = 'unknown';
    }

    $dir = itm_qr_share_join_rate_limit_ip_dir();
    if (function_exists('itm_ensure_upload_directory')) {
        itm_ensure_upload_directory($dir, 'deny_all');
    } elseif (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $path = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $now = time();
    $events = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['events']) && is_array($decoded['events'])) {
            $events = itm_qr_share_join_rate_limit_prune_events($decoded['events'], $now, $windowSeconds);
        }
    }

    if (count($events) >= $maxAttempts) {
        return [
            'ok' => false,
            'error' => 'Too many join attempts. Please wait and try again.',
            'count' => count($events),
            'max' => $maxAttempts,
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

    return ['ok' => true, 'count' => count($events), 'max' => $maxAttempts];
}

function itm_qr_share_table_name()
{
    return 'share_sessions';
}

function itm_qr_share_capable_module_slugs()
{
    return [
        'notes',
        'passwords',
        'bookmarks',
        'todo',
        'events',
        'private_contacts',
        'explorer',
        'floor_plans',
        'rack_planner',
        'employees',
        'departments',
        'equipment',
        'catalogs',
        'license_management',
        'inventory_items',
        'suppliers',
        'alerts',
        'tickets',
        'patches_updates',
        'ops_report',
        'annual_budgets',
        'approvals',
        'approvals_stage',
        'approver_type',
        'approvers',
        'budget_categories',
        'cost_centers',
        'expenses',
        'forecast_revisions',
        'forecast_revisions_status',
        'gl_accounts',
        'monthly_budgets',
        'webmail',
        'saved_report_views',
    ];
}

function itm_qr_share_assert_module_slug($moduleSlug)
{
    $moduleSlug = trim((string)$moduleSlug);
    if (!in_array($moduleSlug, itm_qr_share_capable_module_slugs(), true)) {
        throw new InvalidArgumentException('Unsupported share module slug.');
    }

    return $moduleSlug;
}

/**
 * @deprecated Use itm_qr_share_assert_module_slug() — legacy per-module table names map to module slugs.
 */
function itm_qr_share_allowed_tables()
{
    return [itm_qr_share_table_name()];
}

/**
 * @deprecated Use module slug instead of per-module table name.
 */
function itm_qr_share_record_column($tableName)
{
    $legacyMap = [
        'note_share_sessions' => 'note_id',
        'password_share_sessions' => 'password_entry_id',
        'bookmark_share_sessions' => 'bookmark_id',
        'todo_share_sessions' => 'todo_id',
        'event_share_sessions' => 'event_id',
        'private_contact_share_sessions' => 'private_contact_id',
        'floor_plan_share_sessions' => 'floor_plan_id',
        'rack_planner_share_sessions' => 'rack_planner_id',
    ];

    return $legacyMap[$tableName] ?? '';
}

/**
 * @deprecated Use itm_qr_share_assert_module_slug().
 */
function itm_qr_share_assert_table($tableName)
{
    if ((string)$tableName === itm_qr_share_table_name()) {
        return $tableName;
    }

    $legacyToSlug = [
        'note_share_sessions' => 'notes',
        'password_share_sessions' => 'passwords',
        'bookmark_share_sessions' => 'bookmarks',
        'todo_share_sessions' => 'todo',
        'event_share_sessions' => 'events',
        'private_contact_share_sessions' => 'private_contacts',
        'explorer_share_sessions' => 'explorer',
        'floor_plan_share_sessions' => 'floor_plans',
        'rack_planner_share_sessions' => 'rack_planner',
    ];
    if (isset($legacyToSlug[$tableName])) {
        return itm_qr_share_table_name();
    }

    throw new InvalidArgumentException('Unsupported share session table.');
}

function itm_qr_share_legacy_table_to_module_slug($tableName)
{
    $legacyToSlug = [
        'note_share_sessions' => 'notes',
        'password_share_sessions' => 'passwords',
        'bookmark_share_sessions' => 'bookmarks',
        'todo_share_sessions' => 'todo',
        'event_share_sessions' => 'events',
        'private_contact_share_sessions' => 'private_contacts',
        'explorer_share_sessions' => 'explorer',
        'floor_plan_share_sessions' => 'floor_plans',
        'rack_planner_share_sessions' => 'rack_planner',
    ];

    return $legacyToSlug[$tableName] ?? '';
}

function itm_qr_share_normalize_code($code)
{
    $code = preg_replace('/\D+/', '', (string)$code);
    $len = strlen($code);
    if ($len === itm_qr_share_code_length()) {
        return $code;
    }
    // Why: Legacy six-digit sessions may still be active until TTL expiry after the length bump.
    if ($len === itm_qr_share_legacy_code_length()) {
        return $code;
    }

    return '';
}

function itm_qr_share_generate_code($conn, $moduleSlugOrLegacyTable = '')
{
    if (!($conn instanceof mysqli)) {
        return '';
    }
    $tableName = itm_qr_share_table_name();
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $max = (int) pow(10, itm_qr_share_code_length()) - 1;
        $code = str_pad((string) random_int(0, $max), itm_qr_share_code_length(), '0', STR_PAD_LEFT);
        $sql = 'SELECT id FROM `' . $tableName . '` WHERE share_code = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $code;
        }
    }

    return '';
}

function itm_qr_share_generate_access_token()
{
    return bin2hex(random_bytes(32));
}

function itm_qr_share_purge_expired_sessions($conn, $moduleSlugOrLegacyTable = '')
{
    if (!($conn instanceof mysqli)) {
        return;
    }
    $tableName = itm_qr_share_table_name();
    itm_run_query($conn, 'DELETE FROM `' . $tableName . '` WHERE expires_at <= NOW()');
}

function itm_qr_share_build_join_url($joinScriptPath, $accessToken)
{
    $accessToken = trim((string)$accessToken);
    $joinScriptPath = trim((string)$joinScriptPath, '/');
    if ($accessToken === '' || $joinScriptPath === '') {
        return '';
    }

    return rtrim((string)BASE_URL, '/') . '/' . $joinScriptPath . '?t=' . rawurlencode($accessToken);
}

/**
 * @return array<string,mixed>|null
 */
function itm_qr_share_fetch_session_by_token($conn, $moduleSlugOrLegacyTable, $accessToken)
{
    $accessToken = trim((string)$accessToken);
    if ($accessToken === '' || !($conn instanceof mysqli)) {
        return null;
    }
    $moduleSlug = itm_qr_share_resolve_module_slug($moduleSlugOrLegacyTable);
    if ($moduleSlug === '') {
        return null;
    }
    $tableName = itm_qr_share_table_name();

    itm_qr_share_purge_expired_sessions($conn);
    $sql = 'SELECT * FROM `' . $tableName . '` WHERE module_slug = ? AND access_token = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $moduleSlug, $accessToken);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * @return array<string,mixed>|null
 */
function itm_qr_share_fetch_session_by_code($conn, $moduleSlugOrLegacyTable, $shareCode)
{
    $moduleSlug = itm_qr_share_resolve_module_slug($moduleSlugOrLegacyTable);
    if ($moduleSlug === '' || !itm_qr_share_module_allows_numeric_code($moduleSlug)) {
        return null;
    }

    $rateLimit = itm_qr_share_join_rate_limit_check(true);
    if (empty($rateLimit['ok'])) {
        return null;
    }

    $shareCode = itm_qr_share_normalize_code($shareCode);
    if ($shareCode === '' || !($conn instanceof mysqli)) {
        return null;
    }
    $tableName = itm_qr_share_table_name();

    itm_qr_share_purge_expired_sessions($conn);
    $sql = 'SELECT * FROM `' . $tableName . '` WHERE module_slug = ? AND share_code = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $moduleSlug, $shareCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function itm_qr_share_resolve_module_slug($moduleSlugOrLegacyTable)
{
    $value = trim((string)$moduleSlugOrLegacyTable);
    if ($value === '') {
        return '';
    }
    if (in_array($value, itm_qr_share_capable_module_slugs(), true)) {
        return $value;
    }
    if ($value === itm_qr_share_table_name()) {
        return '';
    }

    return itm_qr_share_legacy_table_to_module_slug($value);
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function itm_qr_share_create_session($conn, $moduleSlugOrLegacyTable, array $options)
{
    $moduleSlug = itm_qr_share_resolve_module_slug($moduleSlugOrLegacyTable);
    if ($moduleSlug === '') {
        try {
            $moduleSlug = itm_qr_share_assert_module_slug($moduleSlugOrLegacyTable);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => 'Invalid share module configuration.'];
        }
    } else {
        try {
            itm_qr_share_assert_module_slug($moduleSlug);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => 'Invalid share module configuration.'];
        }
    }

    $companyId = (int)($options['company_id'] ?? 0);
    $employeeId = (int)($options['employee_id'] ?? 0);
    $recordId = (int)($options['record_id'] ?? 0);
    $scopePath = trim((string)($options['scope_path'] ?? ''));
    $scopePathHash = trim((string)($options['scope_path_hash'] ?? ''));
    $payloadJson = (string)($options['payload_json'] ?? '');
    if ($companyId <= 0 || $employeeId <= 0 || $payloadJson === '' || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    if ($moduleSlug === 'explorer') {
        if ($scopePath === '' || $scopePathHash === '') {
            return ['ok' => false, 'error' => 'Invalid explorer share scope.'];
        }
    } elseif ($recordId <= 0) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    if (!function_exists('has_module_share_access')) {
        require_once ROOT_PATH . 'includes/itm_module_share.php';
    }
    if (!has_module_share_access($conn, $companyId, $moduleSlug)) {
        return ['ok' => false, 'error' => 'QR share is disabled for this module in your company.'];
    }

    $allowsNumericCode = itm_qr_share_module_allows_numeric_code($moduleSlug);
    $shareCode = $allowsNumericCode ? itm_qr_share_generate_code($conn) : '';
    $accessToken = itm_qr_share_generate_access_token();
    if ($accessToken === '') {
        return ['ok' => false, 'error' => 'Could not generate share link.'];
    }
    if ($allowsNumericCode && $shareCode === '') {
        return ['ok' => false, 'error' => 'Could not generate share code.'];
    }

    itm_qr_share_purge_expired_sessions($conn);
    $tableName = itm_qr_share_table_name();

    if ($moduleSlug === 'explorer') {
        $deactivateSql = 'UPDATE `' . $tableName . '` SET active = 0, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE company_id = ? AND employee_id = ? AND module_slug = ? AND scope_path_hash = ? AND active = 1 AND deleted_at IS NULL';
        $stmtDeactivate = $conn->prepare($deactivateSql);
        if ($stmtDeactivate) {
            $stmtDeactivate->bind_param('iiisis', $employeeId, $employeeId, $companyId, $employeeId, $moduleSlug, $scopePathHash);
            $stmtDeactivate->execute();
            $stmtDeactivate->close();
        }
    } else {
        $deactivateSql = 'UPDATE `' . $tableName . '` SET active = 0, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE company_id = ? AND employee_id = ? AND module_slug = ? AND record_id = ? AND active = 1 AND deleted_at IS NULL';
        $stmtDeactivate = $conn->prepare($deactivateSql);
        if ($stmtDeactivate) {
            $stmtDeactivate->bind_param('iiisis', $employeeId, $employeeId, $companyId, $employeeId, $moduleSlug, $recordId);
            $stmtDeactivate->execute();
            $stmtDeactivate->close();
        }
    }

    $ttl = itm_qr_share_session_ttl_seconds();
    if ($moduleSlug === 'explorer') {
        $recordIdParam = null;
        $insertSql = 'INSERT INTO `' . $tableName . '` (company_id, employee_id, module_slug, record_id, scope_path, scope_path_hash, share_code, access_token, payload_json, expires_at, created_by) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)';
        $stmtInsert = $conn->prepare($insertSql);
        if (!$stmtInsert) {
            return ['ok' => false, 'error' => 'Could not create share session.'];
        }
        $stmtInsert->bind_param('iissssssii', $companyId, $employeeId, $moduleSlug, $scopePath, $scopePathHash, $shareCode, $accessToken, $payloadJson, $ttl, $employeeId);
    } else {
        $insertSql = 'INSERT INTO `' . $tableName . '` (company_id, employee_id, module_slug, record_id, share_code, access_token, payload_json, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)';
        $stmtInsert = $conn->prepare($insertSql);
        if (!$stmtInsert) {
            return ['ok' => false, 'error' => 'Could not create share session.'];
        }
        $stmtInsert->bind_param('iisisssii', $companyId, $employeeId, $moduleSlug, $recordId, $shareCode, $accessToken, $payloadJson, $ttl, $employeeId);
    }

    if (!$stmtInsert->execute()) {
        $stmtInsert->close();

        return ['ok' => false, 'error' => 'Could not save share session.'];
    }
    $sessionId = (int)$stmtInsert->insert_id;
    $stmtInsert->close();

    $stmtFetch = $conn->prepare('SELECT * FROM `' . $tableName . '` WHERE id = ? LIMIT 1');
    if (!$stmtFetch) {
        return ['ok' => false, 'error' => 'Share session created but could not be loaded.'];
    }
    $stmtFetch->bind_param('i', $sessionId);
    $stmtFetch->execute();
    $session = $stmtFetch->get_result()->fetch_assoc();
    $stmtFetch->close();

    if (!$session) {
        return ['ok' => false, 'error' => 'Share session not found after create.'];
    }

    return ['ok' => true, 'session' => $session];
}

/**
 * @return array<string,mixed>|null
 */
function itm_qr_share_decode_payload($payloadJson)
{
    $decoded = json_decode((string)$payloadJson, true);

    return is_array($decoded) ? $decoded : null;
}
