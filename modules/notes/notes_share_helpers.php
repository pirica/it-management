<?php
/**
 * Temporary QR / code share sessions for Notes (SpeedyShare-style cross-device read).
 */

require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/notes_vault_helpers.php';

function notes_share_session_ttl_seconds()
{
    return 1800;
}

function notes_share_normalize_code($code)
{
    $code = preg_replace('/\D+/', '', (string)$code);

    return strlen($code) === 6 ? $code : '';
}

function notes_share_generate_code($conn)
{
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare(
            'SELECT id FROM note_share_sessions WHERE share_code = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1'
        );
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

function notes_share_generate_access_token()
{
    return bin2hex(random_bytes(32));
}

function notes_share_purge_expired_sessions($conn)
{
    if (!($conn instanceof mysqli)) {
        return;
    }
    itm_run_query($conn, 'DELETE FROM note_share_sessions WHERE expires_at <= NOW()');
}

function notes_share_build_join_url($accessToken)
{
    $accessToken = trim((string)$accessToken);
    if ($accessToken === '') {
        return '';
    }

    return rtrim((string)BASE_URL, '/') . '/modules/notes/join.php?t=' . rawurlencode($accessToken);
}

/**
 * @return array<string,mixed>|null
 */
function notes_share_fetch_session_by_token($conn, $accessToken)
{
    $accessToken = trim((string)$accessToken);
    if ($accessToken === '' || !($conn instanceof mysqli)) {
        return null;
    }

    notes_share_purge_expired_sessions($conn);
    $stmt = $conn->prepare(
        'SELECT * FROM note_share_sessions WHERE access_token = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
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
function notes_share_fetch_session_by_code($conn, $shareCode)
{
    $shareCode = notes_share_normalize_code($shareCode);
    if ($shareCode === '' || !($conn instanceof mysqli)) {
        return null;
    }

    notes_share_purge_expired_sessions($conn);
    $stmt = $conn->prepare(
        'SELECT * FROM note_share_sessions WHERE share_code = ? AND expires_at > NOW() AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
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
 * @return array{title:string,content:string,is_checklist:int,checklist_json:?string,images:list<string>,owner_username:string}|null
 */
function notes_share_decode_payload($payloadJson)
{
    $decoded = json_decode((string)$payloadJson, true);
    if (!is_array($decoded)) {
        return null;
    }

    $images = [];
    if (!empty($decoded['images']) && is_array($decoded['images'])) {
        foreach ($decoded['images'] as $img) {
            $img = trim((string)$img);
            if ($img !== '') {
                $images[] = $img;
            }
        }
    }

    return [
        'title' => (string)($decoded['title'] ?? ''),
        'content' => (string)($decoded['content'] ?? ''),
        'is_checklist' => (int)($decoded['is_checklist'] ?? 0),
        'checklist_json' => isset($decoded['checklist_json']) ? (string)$decoded['checklist_json'] : null,
        'images' => $images,
        'owner_username' => (string)($decoded['owner_username'] ?? ''),
    ];
}

/**
 * @return array{title:string,content:string,is_checklist:int,checklist_json:?string,images:list<string>,owner_username:string}|null
 */
function notes_share_build_payload_from_note(array $note, $ownerUsername, $viewerEmployeeId)
{
    $viewerEmployeeId = (int)$viewerEmployeeId;
    $noteRow = $note;
    notes_hydrate_note_row($noteRow, $viewerEmployeeId);

    $images = [];
    $decodedImages = json_decode((string)($noteRow['images_json'] ?? '[]'), true);
    if (is_array($decodedImages)) {
        foreach ($decodedImages as $img) {
            $normalized = itm_notes_normalize_image_filename($img);
            if ($normalized !== null) {
                $images[] = $normalized;
            }
        }
    }

    return [
        'title' => (string)($noteRow['title'] ?? ''),
        'content' => (string)($noteRow['content'] ?? ''),
        'is_checklist' => (int)($noteRow['is_checklist'] ?? 0),
        'checklist_json' => isset($noteRow['checklist_json']) ? (string)$noteRow['checklist_json'] : null,
        'images' => $images,
        'owner_username' => (string)$ownerUsername,
    ];
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function notes_share_create_session($conn, $noteId, $companyId, $employeeId, $ownerUsername, $vaultUnlocked)
{
    $noteId = (int)$noteId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($noteId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $stmt = $conn->prepare(
        'SELECT * FROM notes WHERE id = ? AND company_id = ? AND employee_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load note.'];
    }
    $stmt->bind_param('iii', $noteId, $companyId, $employeeId);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$note) {
        return ['ok' => false, 'error' => 'Note not found or you are not the owner.'];
    }

    if (!notes_is_shared_with_others($note['shared_with_json'] ?? null) && !$vaultUnlocked) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing a private note.'];
    }

    $payload = notes_share_build_payload_from_note($note, $ownerUsername, $employeeId);
    if ($payload === null) {
        return ['ok' => false, 'error' => 'Could not prepare note for sharing.'];
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    $shareCode = notes_share_generate_code($conn);
    $accessToken = notes_share_generate_access_token();
    if ($shareCode === '' || $accessToken === '') {
        return ['ok' => false, 'error' => 'Could not generate share code.'];
    }

    notes_share_purge_expired_sessions($conn);

    $stmtDeactivate = $conn->prepare(
        'UPDATE note_share_sessions SET active = 0, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE company_id = ? AND employee_id = ? AND note_id = ? AND active = 1 AND deleted_at IS NULL'
    );
    if ($stmtDeactivate) {
        $stmtDeactivate->bind_param('iiiii', $employeeId, $employeeId, $companyId, $employeeId, $noteId);
        $stmtDeactivate->execute();
        $stmtDeactivate->close();
    }

    $ttl = notes_share_session_ttl_seconds();
    $stmtInsert = $conn->prepare(
        'INSERT INTO note_share_sessions (company_id, employee_id, note_id, share_code, access_token, payload_json, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)'
    );
    if (!$stmtInsert) {
        return ['ok' => false, 'error' => 'Could not create share session.'];
    }
    $stmtInsert->bind_param('iiisssii', $companyId, $employeeId, $noteId, $shareCode, $accessToken, $payloadJson, $ttl, $employeeId);
    if (!$stmtInsert->execute()) {
        $stmtInsert->close();

        return ['ok' => false, 'error' => 'Could not save share session.'];
    }
    $sessionId = (int)$stmtInsert->insert_id;
    $stmtInsert->close();

    $stmtFetch = $conn->prepare('SELECT * FROM note_share_sessions WHERE id = ? LIMIT 1');
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
 * @return array{ok:bool,error?:string}
 */
function notes_share_validate_asset_request($conn, $accessToken, $storedFilename, &$sessionOut = null)
{
    $sessionOut = null;
    $session = notes_share_fetch_session_by_token($conn, $accessToken);
    if (!$session) {
        return ['ok' => false, 'error' => 'Share session expired or invalid.'];
    }

    $filename = itm_notes_normalize_image_filename($storedFilename);
    if ($filename === null) {
        return ['ok' => false, 'error' => 'Invalid file name.'];
    }

    $payload = notes_share_decode_payload($session['payload_json'] ?? '');
    if ($payload === null || !in_array($filename, $payload['images'], true)) {
        return ['ok' => false, 'error' => 'File not part of this share.'];
    }

    $sessionOut = $session;

    return ['ok' => true];
}
