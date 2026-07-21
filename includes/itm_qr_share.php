<?php
/**
 * Generic temporary QR / 6-digit share sessions (SpeedyShare-style).
 */

function itm_qr_share_session_ttl_seconds()
{
    return 1800;
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

    return strlen($code) === 6 ? $code : '';
}

function itm_qr_share_generate_code($conn, $moduleSlugOrLegacyTable = '')
{
    if (!($conn instanceof mysqli)) {
        return '';
    }
    $tableName = itm_qr_share_table_name();
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
    $shareCode = itm_qr_share_normalize_code($shareCode);
    if ($shareCode === '' || !($conn instanceof mysqli)) {
        return null;
    }
    $moduleSlug = itm_qr_share_resolve_module_slug($moduleSlugOrLegacyTable);
    if ($moduleSlug === '') {
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

    $shareCode = itm_qr_share_generate_code($conn);
    $accessToken = itm_qr_share_generate_access_token();
    if ($shareCode === '' || $accessToken === '') {
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
