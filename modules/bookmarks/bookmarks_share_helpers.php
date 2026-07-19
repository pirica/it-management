<?php
/**
 * Temporary QR / code share sessions for Bookmarks.
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/helpers.php';

function bookmarks_share_table_name()
{
    return 'bookmark_share_sessions';
}

function bookmarks_share_join_script_path()
{
    return 'modules/bookmarks/join.php';
}

/**
 * @return array<string,mixed>|null
 */
function bookmarks_share_build_payload_from_bookmark($conn, array $bookmark, $viewerEmployeeId, $companyId, $ownerUsername)
{
    $viewerEmployeeId = (int)$viewerEmployeeId;
    $companyId = (int)$companyId;
    bkm_hydrate_bookmark_row($bookmark, $viewerEmployeeId);

    if (!empty($bookmark['url_locked']) || !empty($bookmark['title_locked'])) {
        return null;
    }

    $folderName = 'Root';
    $folderId = (int)($bookmark['folder_id'] ?? 0);
    if ($folderId > 0) {
        $folderStmt = $conn->prepare(
            'SELECT name FROM bookmark_folders WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
        );
        if ($folderStmt) {
            $folderStmt->bind_param('ii', $folderId, $companyId);
            $folderStmt->execute();
            $folderRow = $folderStmt->get_result()->fetch_assoc();
            $folderStmt->close();
            if ($folderRow && trim((string)($folderRow['name'] ?? '')) !== '') {
                $folderName = (string)$folderRow['name'];
            }
        }
    }

    $title = (string)($bookmark['title_plain'] ?? $bookmark['title_display'] ?? '');
    $notes = (string)($bookmark['notes_plain'] ?? $bookmark['notes_display'] ?? '');

    return [
        'type' => 'bookmark',
        'heading' => $title !== '' ? $title : 'Bookmark',
        'owner_username' => (string)$ownerUsername,
        'title' => $title,
        'url' => (string)($bookmark['url_display'] ?? ''),
        'notes' => $notes,
        'folder_name' => $folderName,
    ];
}

function bookmarks_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(bookmarks_share_join_script_path(), $accessToken);
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function bookmarks_share_create_session($conn, $bookmarkId, $companyId, $employeeId, $ownerUsername, $isAdmin, $vaultUnlocked)
{
    $bookmarkId = (int)$bookmarkId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($bookmarkId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $stmt = $conn->prepare(
        'SELECT * FROM bookmarks WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load bookmark.'];
    }
    $stmt->bind_param('ii', $bookmarkId, $companyId);
    $stmt->execute();
    $bookmark = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bookmark || !bkm_can_edit_bookmark($bookmark, $employeeId, $isAdmin)) {
        return ['ok' => false, 'error' => 'Bookmark not found or access denied.'];
    }

    $isPrivate = (int)($bookmark['shared'] ?? 0) === 0;
    if ($isPrivate && !$vaultUnlocked) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing a private bookmark.'];
    }

    $payload = bookmarks_share_build_payload_from_bookmark($conn, $bookmark, $employeeId, $companyId, $ownerUsername);
    if ($payload === null) {
        return ['ok' => false, 'error' => 'Could not prepare bookmark for sharing. Unlock your vault and try again.'];
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, bookmarks_share_table_name(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $bookmarkId,
        'payload_json' => $payloadJson,
    ]);
}
