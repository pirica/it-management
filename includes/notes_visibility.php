<?php
/**
 * Shared visibility helpers for notes.
 */

function itm_notes_normalize_sql_alias($alias)
{
    $alias = trim((string)$alias);
    if ($alias === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', rtrim($alias, '.'))) {
        return '';
    }

    return rtrim($alias, '.') . '.';
}

function itm_notes_visibility_sql($alias = '')
{
    $prefix = itm_notes_normalize_sql_alias($alias);
    // Owner or shared with user.
    // Assuming shared_with_json is a JSON array of user IDs.
    return '(' . $prefix . 'user_id = ? OR JSON_CONTAINS(' . $prefix . 'shared_with_json, CAST(? AS JSON), \'$\'))';
}

function itm_notes_append_visibility_filter(&$conditions, &$types, &$params, $loggedUserId, $alias = '')
{
    $conditions[] = itm_notes_visibility_sql($alias);
    $types .= 'ii';
    $userId = (int)$loggedUserId;
    $params[] = $userId;
    $params[] = $userId;
}

/**
 * Loads one note row when the active user is owner or listed in shared_with_json.
 */
function itm_notes_fetch_visible_by_id($conn, $noteId, $companyId, $loggedUserId, $requireActive = true)
{
    $noteId = (int)$noteId;
    $companyId = (int)$companyId;
    $loggedUserId = (int)$loggedUserId;

    if ($noteId <= 0 || $companyId <= 0 || $loggedUserId <= 0 || !($conn instanceof mysqli)) {
        return null;
    }

    $visSql = itm_notes_visibility_sql();
    $activeSql = $requireActive ? ' AND active = 1' : '';
    $sql = 'SELECT * FROM notes WHERE id = ? AND company_id = ?' . $activeSql . ' AND (' . $visSql . ') LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iiii', $noteId, $companyId, $loggedUserId, $loggedUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Absolute filesystem path for a user's note image uploads (trailing slash).
 */
function itm_notes_private_images_dir($companyId, $username, $userId)
{
    $companyId = (int)$companyId;
    $userId = (int)$userId;
    if ($companyId <= 0 || $userId <= 0) {
        return '';
    }

    // Why: Match Explorer private folder naming so ZIP paths align with upload storage.
    $safeUsername = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$username);
    return ROOT_PATH . 'files/' . $companyId . '/Private/' . $safeUsername . '_' . $userId . '/notes/';
}

/**
 * Returns a safe leaf filename from images_json, or null when traversal/path separators are present.
 */
function itm_notes_normalize_image_filename($storedName)
{
    $storedName = trim((string)$storedName);
    if ($storedName === '' || strpos($storedName, '/') !== false || strpos($storedName, '\\') !== false) {
        return null;
    }

    $filename = basename($storedName);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return null;
    }

    return $filename;
}

/**
 * Resolves a note attachment to an on-disk file under the user's notes upload directory.
 */
function itm_notes_resolve_image_path($companyId, $username, $userId, $storedName)
{
    $filename = itm_notes_normalize_image_filename($storedName);
    if ($filename === null) {
        return null;
    }

    $notesDir = itm_notes_private_images_dir($companyId, $username, $userId);
    if ($notesDir === '') {
        return null;
    }

    $candidatePath = $notesDir . $filename;
    if (!is_file($candidatePath)) {
        return null;
    }

    $realNotesDir = realpath(rtrim($notesDir, '/\\'));
    $realFile = realpath($candidatePath);
    if ($realNotesDir === false || $realFile === false) {
        return null;
    }

    $dirPrefix = rtrim($realNotesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($realFile, $dirPrefix) !== 0) {
        return null;
    }

    return $realFile;
}
