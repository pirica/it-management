<?php
/**
 * Temporary QR / code share sessions for Notes (SpeedyShare-style cross-device read).
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/notes_vault_helpers.php';

function notes_share_module_slug()
{
    return 'notes';
}

function notes_share_session_ttl_seconds()
{
    return itm_qr_share_session_ttl_seconds();
}

function notes_share_normalize_code($code)
{
    return itm_qr_share_normalize_code($code);
}

function notes_share_generate_code($conn)
{
    return itm_qr_share_generate_code($conn);
}

function notes_share_generate_access_token()
{
    return itm_qr_share_generate_access_token();
}

function notes_share_purge_expired_sessions($conn)
{
    itm_qr_share_purge_expired_sessions($conn);
}

function notes_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url('modules/notes/join.php', $accessToken);
}

/**
 * @return array<string,mixed>|null
 */
function notes_share_fetch_session_by_token($conn, $accessToken)
{
    return itm_qr_share_fetch_session_by_token($conn, notes_share_module_slug(), $accessToken);
}

/**
 * @return array<string,mixed>|null
 */
function notes_share_fetch_session_by_code($conn, $shareCode)
{
    return itm_qr_share_fetch_session_by_code($conn, notes_share_module_slug(), $shareCode);
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

    return itm_qr_share_create_session($conn, notes_share_module_slug(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $noteId,
        'payload_json' => $payloadJson,
    ]);
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
