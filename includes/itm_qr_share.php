<?php
/**
 * Generic temporary QR / 6-digit share sessions (SpeedyShare-style).
 */

function itm_qr_share_session_ttl_seconds()
{
    return 1800;
}

function itm_qr_share_allowed_tables()
{
    return [
        'note_share_sessions',
        'password_share_sessions',
        'bookmark_share_sessions',
        'todo_share_sessions',
    ];
}

function itm_qr_share_record_column($tableName)
{
    $map = [
        'note_share_sessions' => 'note_id',
        'password_share_sessions' => 'password_entry_id',
        'bookmark_share_sessions' => 'bookmark_id',
        'todo_share_sessions' => 'todo_id',
    ];

    return $map[$tableName] ?? '';
}

function itm_qr_share_assert_table($tableName)
{
    $tableName = (string)$tableName;
    if (!in_array($tableName, itm_qr_share_allowed_tables(), true)) {
        throw InvalidArgumentException('Unsupported share session table.');
    }

    return $tableName;
}

function itm_qr_share_normalize_code($code)
{
    $code = preg_replace('/\D+/', '', (string)$code);

    return strlen($code) === 6 ? $code : '';
}

function itm_qr_share_generate_code($conn, $tableName)
{
    $tableName = itm_qr_share_assert_table($tableName);
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

function itm_qr_share_purge_expired_sessions($conn, $tableName)
{
    if (!($conn instanceof mysqli)) {
        return;
    }
    $tableName = itm_qr_share_assert_table($tableName);
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
function itm_qr_share_fetch_session_by_token($conn, $tableName, $accessToken)
{
    $accessToken = trim((string)$accessToken);
    if ($accessToken === '' || !($conn instanceof mysqli)) {
        return null;
    }
    $tableName = itm_qr_share_assert_table($tableName);

    itm_qr_share_purge_expired_sessions($conn, $tableName);
    $sql = 'SELECT * FROM `' . $tableName . '` WHERE access_token = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $accessToken);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * @return array<string,mixed>|null
 */
function itm_qr_share_fetch_session_by_code($conn, $tableName, $shareCode)
{
    $shareCode = itm_qr_share_normalize_code($shareCode);
    if ($shareCode === '' || !($conn instanceof mysqli)) {
        return null;
    }
    $tableName = itm_qr_share_assert_table($tableName);

    itm_qr_share_purge_expired_sessions($conn, $tableName);
    $sql = 'SELECT * FROM `' . $tableName . '` WHERE share_code = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $shareCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function itm_qr_share_create_session($conn, $tableName, array $options)
{
    $tableName = itm_qr_share_assert_table($tableName);
    $recordColumn = itm_qr_share_record_column($tableName);
    if ($recordColumn === '') {
        return ['ok' => false, 'error' => 'Invalid share table configuration.'];
    }

    $companyId = (int)($options['company_id'] ?? 0);
    $employeeId = (int)($options['employee_id'] ?? 0);
    $recordId = (int)($options['record_id'] ?? 0);
    $payloadJson = (string)($options['payload_json'] ?? '');
    if ($companyId <= 0 || $employeeId <= 0 || $recordId <= 0 || $payloadJson === '' || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $shareCode = itm_qr_share_generate_code($conn, $tableName);
    $accessToken = itm_qr_share_generate_access_token();
    if ($shareCode === '' || $accessToken === '') {
        return ['ok' => false, 'error' => 'Could not generate share code.'];
    }

    itm_qr_share_purge_expired_sessions($conn, $tableName);

    $deactivateSql = 'UPDATE `' . $tableName . '` SET active = 0, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE company_id = ? AND employee_id = ? AND `' . $recordColumn . '` = ? AND active = 1 AND deleted_at IS NULL';
    $stmtDeactivate = $conn->prepare($deactivateSql);
    if ($stmtDeactivate) {
        $stmtDeactivate->bind_param('iiiii', $employeeId, $employeeId, $companyId, $employeeId, $recordId);
        $stmtDeactivate->execute();
        $stmtDeactivate->close();
    }

    $ttl = itm_qr_share_session_ttl_seconds();
    $insertSql = 'INSERT INTO `' . $tableName . '` (company_id, employee_id, `' . $recordColumn . '`, share_code, access_token, payload_json, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)';
    $stmtInsert = $conn->prepare($insertSql);
    if (!$stmtInsert) {
        return ['ok' => false, 'error' => 'Could not create share session.'];
    }
    $stmtInsert->bind_param('iiisssii', $companyId, $employeeId, $recordId, $shareCode, $accessToken, $payloadJson, $ttl, $employeeId);
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
