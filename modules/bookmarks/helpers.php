<?php
/**
 * Bookmarks Module Helpers
 */

/**
 * Fetch folders for the current company and user.
 * Shared folders are included if $include_shared is true.
 */
function bkm_get_folders($conn, $company_id, $user_id, $include_shared = true) {
    $company_id = (int)$company_id;
    $user_id = (int)$user_id;
    $where = "company_id = $company_id AND (employee_id = $user_id";
    if ($include_shared) {
        $where .= " OR shared = 1";
    }
    $where .= ") AND active = 1";

    $sql = "SELECT * FROM bookmark_folders WHERE $where ORDER BY position ASC, id ASC";
    $res = mysqli_query($conn, $sql);
    $folders = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        bkm_hydrate_folder_row($row, $user_id);
        $folders[] = $row;
    }
    usort($folders, static function (array $a, array $b) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    return $folders;
}

/**
 * Builds a recursive tree structure from a flat folders array.
 */
function bkm_build_folder_tree(array $folders, $parentId = null) {
    $branch = [];
    foreach ($folders as $folder) {
        $actualParent = $folder['parent_folder_id'] ? (int)$folder['parent_folder_id'] : null;
        if ($actualParent === $parentId) {
            $children = bkm_build_folder_tree($folders, (int)$folder['id']);
            $folder['children'] = $children ?: [];
            $branch[] = $folder;
        }
    }
    return $branch;
}

/**
 * Checks if a folder or any of its descendants contains at least one active bookmark.
 */
function bkm_folder_has_bookmarks($conn, $folder_id, $company_id) {
    $folder_id = (int)$folder_id;
    $company_id = (int)$company_id;

    // Direct check
    $res = mysqli_query($conn, "SELECT 1 FROM bookmarks WHERE folder_id = $folder_id AND company_id = $company_id AND active = 1 LIMIT 1");
    if (mysqli_num_rows($res) > 0) return true;

    // Check subfolders
    $res = mysqli_query($conn, "SELECT id FROM bookmark_folders WHERE parent_folder_id = $folder_id AND company_id = $company_id AND active = 1");
    while ($row = mysqli_fetch_assoc($res)) {
        if (bkm_folder_has_bookmarks($conn, $row['id'], $company_id)) return true;
    }

    return false;
}

/**
 * Renders the folder tree as HTML list items.
 */
function bkm_render_folder_tree_html($conn, array $tree, $selectedFolderId, $company_id, $depth = 0) {
    $html = '';
    foreach ($tree as $node) {
        $id = (int)$node['id'];
        $isActive = ($id === (int)$selectedFolderId) ? ' active' : '';
        $icon = (isset($node['shared']) && $node['shared'] == 1) ? '🔓' : '🔒';
        $padding = $depth * 15;
        $hasBookmarks = bkm_folder_has_bookmarks($conn, $id, $company_id) ? '1' : '0';

        $html .= '<li class="itm-folder-tree-item' . $isActive . '" data-folder-id="' . $id . '" draggable="true" ondragstart="drag(event)" ondrop="drop(event)" ondragover="allowDrop(event)">';
        $html .= '<div class="itm-folder-tree-row" style="padding-left:' . $padding . 'px; display: flex; align-items: center; justify-content: space-between;">';
        $html .= '<a href="index.php?folder_id=' . $id . '" style="flex: 1;">📁 ' . $icon . ' ' . sanitize($node['name']) . '</a>';
        $html .= '<button type="button" class="btn btn-sm btn-danger delete-folder-btn" data-id="' . $id . '" data-has-bookmarks="' . $hasBookmarks . '" title="Delete Folder" style="padding: 2px 6px; margin-left: 8px;">🗑️</button>';
        $html .= '</div>';

        if (!empty($node['children'])) {
            $html .= '<ul class="itm-folder-tree-children">' . bkm_render_folder_tree_html($conn, $node['children'], $selectedFolderId, $company_id, $depth + 1) . '</ul>';
        }
        $html .= '</li>';
    }
    return $html;
}

/**
 * Renders folder options for a <select> element.
 */
function bkm_render_folder_options(array $tree, $selectedId = null, $depth = 0) {
    $html = '';
    foreach ($tree as $node) {
        $sel = ($node['id'] == $selectedId) ? ' selected' : '';
        $icon = (isset($node['shared']) && $node['shared'] == 1) ? '🔓' : '🔒';
        $html .= '<option value="' . $node['id'] . '"' . $sel . '>' . str_repeat('&nbsp;&nbsp;', $depth) . ($depth > 0 ? '📂 ' : '📁 ') . $icon . ' ' . sanitize($node['name']) . '</option>';
        if (!empty($node['children'])) {
            $html .= bkm_render_folder_options($node['children'], $selectedId, $depth + 1);
        }
    }
    return $html;
}

/**
 * Builds index.php list URLs preserving folder/view/search/sort state.
 */
function bkm_build_index_query(array $params): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '' || ($key === 'view' && $value === 'all')) {
            continue;
        }
        $filtered[$key] = $value;
    }
    $qs = http_build_query($filtered);

    return $qs === '' ? 'index.php' : 'index.php?' . $qs;
}

/**
 * Permission check for editing a bookmark.
 */
function bkm_can_edit_bookmark($bookmark, $user_id, $is_admin) {
    if ($is_admin) return true;
    return (int)($bookmark['employee_id'] ?? 0) === (int)$user_id;
}

/**
 * Permission check for editing a folder.
 */
function bkm_can_edit_folder($folder, $user_id, $is_admin) {
    if ($is_admin) return true;
    return (int)($folder['employee_id'] ?? 0) === (int)$user_id;
}

/**
 * Parses browser-exported HTML bookmarks (Netscape format) including folder paths.
 *
 * @return list<array{title:string,url:string,notes:string,folder_path:list<string>}>
 */
function bkm_parse_html_bookmark_entries($html)
{
    $entries = [];
    if (trim($html) === '') {
        return $entries;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body === null) {
        return $entries;
    }

    for ($child = $body->firstChild; $child !== null; $child = $child->nextSibling) {
        if ($child->nodeType === XML_ELEMENT_NODE && strtoupper($child->nodeName) === 'DL') {
            bkm_walk_html_dl_node($child, [], $entries);
        }
    }

    if ($entries === []) {
        for ($child = $body->firstChild; $child !== null; $child = $child->nextSibling) {
            bkm_walk_html_bookmark_roots($child, [], $entries);
        }
    }

    return $entries;
}

/**
 * Fallback: locate any top-level DL nodes when the export omits a body wrapper match.
 *
 * @param list<string> $folderPath
 * @param list<array{title:string,url:string,notes:string,folder_path:list<string>}> $entries
 */
function bkm_walk_html_bookmark_roots(DOMNode $node, array $folderPath, array &$entries)
{
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return;
    }

    $tag = strtoupper($node->nodeName);
    if ($tag === 'DL') {
        bkm_walk_html_dl_node($node, $folderPath, $entries);
        return;
    }

    for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
        bkm_walk_html_bookmark_roots($child, $folderPath, $entries);
    }
}

/**
 * Walk a Netscape bookmark DL: folder headers are DT+H3 with a sibling DL for contents.
 *
 * @param list<string> $folderPath
 * @param list<array{title:string,url:string,notes:string,folder_path:list<string>}> $entries
 */
function bkm_walk_html_dl_node(DOMNode $dlNode, array $folderPath, array &$entries)
{
    if ($dlNode->nodeType !== XML_ELEMENT_NODE || strtoupper($dlNode->nodeName) !== 'DL') {
        return;
    }

    $skipNodes = [];

    for ($child = $dlNode->firstChild; $child !== null; $child = $child->nextSibling) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        if (in_array($child, $skipNodes, true)) {
            continue;
        }

        $tag = strtoupper($child->nodeName);
        if ($tag === 'DL') {
            bkm_walk_html_dl_node($child, $folderPath, $entries);
            continue;
        }

        if ($tag === 'DT') {
            $consumedDl = bkm_process_html_dt_node($child, $folderPath, $entries);
            if ($consumedDl !== null) {
                $skipNodes[] = $consumedDl;
            }
        }
    }
}

/**
 * @param list<string> $folderPath
 * @param list<array{title:string,url:string,notes:string,folder_path:list<string>}> $entries
 */
function bkm_process_html_dt_node(DOMNode $dtNode, array $folderPath, array &$entries)
{
    if ($dtNode->nodeType !== XML_ELEMENT_NODE || strtoupper($dtNode->nodeName) !== 'DT') {
        return null;
    }

    $folderName = null;
    $anchor = null;
    $childDl = null;

    for ($child = $dtNode->firstChild; $child !== null; $child = $child->nextSibling) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }
        $childTag = strtoupper($child->nodeName);
        if ($childTag === 'H3') {
            $folderName = trim(html_entity_decode($child->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } elseif ($childTag === 'DL') {
            $childDl = $child;
        } elseif ($childTag === 'A') {
            $anchor = $child;
        } elseif ($childTag === 'DT') {
            bkm_process_html_dt_node($child, $folderPath, $entries);
        }
    }

    if ($folderName !== null && $folderName !== '') {
        $folderDl = $childDl !== null ? $childDl : bkm_find_next_element_sibling($dtNode, 'DL');
        if ($folderDl !== null) {
            $nextPath = array_merge($folderPath, [$folderName]);
            bkm_walk_html_dl_node($folderDl, $nextPath, $entries);
            return $folderDl;
        }
        return null;
    }

    if ($anchor instanceof DOMElement) {
        $href = trim(html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href !== '') {
            $entries[] = [
                'title' => trim(html_entity_decode($anchor->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'url' => $href,
                'notes' => '',
                'folder_path' => $folderPath,
            ];
        }
    }

    return null;
}

function bkm_find_next_element_sibling(DOMNode $node, $tagName)
{
    $wanted = strtoupper($tagName);
    for ($sibling = $node->nextSibling; $sibling !== null; $sibling = $sibling->nextSibling) {
        if ($sibling->nodeType === XML_ELEMENT_NODE && strtoupper($sibling->nodeName) === $wanted) {
            return $sibling;
        }
    }

    return null;
}

/**
 * @deprecated Use bkm_walk_html_dl_node — kept for backward compatibility in case of nested non-DL walks.
 * @param list<string> $folderPath
 * @param list<array{title:string,url:string,notes:string,folder_path:list<string>}> $entries
 */
function bkm_walk_html_bookmark_node(DOMNode $node, array $folderPath, array &$entries)
{
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return;
    }

    $tag = strtoupper($node->nodeName);
    if ($tag === 'DL') {
        bkm_walk_html_dl_node($node, $folderPath, $entries);
        return;
    }

    for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
        bkm_walk_html_bookmark_node($child, $folderPath, $entries);
    }
}

/**
 * Find or create folders for an import path under an optional base parent folder.
 *
 * @param list<string> $folderPath
 * @param array<string,int> $cache
 */
function bkm_resolve_import_folder_path($conn, $company_id, $user_id, array $folderPath, $baseParentId, array &$cache, &$foldersCreated = 0)
{
    $parentId = $baseParentId !== null ? (int)$baseParentId : null;

    foreach ($folderPath as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        $cacheKey = ($parentId ?? 0) . '|' . $segment;
        if (isset($cache[$cacheKey])) {
            $parentId = $cache[$cacheKey];
            continue;
        }

        $existingId = bkm_find_folder_id_by_name($conn, $company_id, $user_id, $parentId, $segment);
        if ($existingId !== null) {
            $parentId = $existingId;
            $cache[$cacheKey] = $parentId;
            continue;
        }

        $parentId = bkm_create_import_folder($conn, $company_id, $user_id, $parentId, $segment);
        $cache[$cacheKey] = $parentId;
        $foldersCreated++;
    }

    return $parentId;
}

function bkm_find_folder_id_by_name($conn, $company_id, $user_id, $parentId, $name)
{
    $nameHash = bkm_text_hash($name);
    if ($parentId === null) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM bookmark_folders WHERE company_id = ? AND employee_id = ? AND active = 1 AND parent_folder_id IS NULL AND name_hash = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $company_id, $user_id, $nameHash);
    } else {
        $parentId = (int)$parentId;
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM bookmark_folders WHERE company_id = ? AND employee_id = ? AND active = 1 AND parent_folder_id = ? AND name_hash = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iiis', $company_id, $user_id, $parentId, $nameHash);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ? (int)$row['id'] : null;
}

/**
 * @return array{ok:bool,message:string,id:int}
 */
function bkm_insert_folder_row($conn, $company_id, $user_id, $parentId, $plainName, $shared, $active = 1)
{
    $storage = bkm_prepare_text_storage($plainName, $shared);
    if ($storage === null) {
        return ['ok' => false, 'message' => 'Unlock your vault to save private folders.', 'id' => 0];
    }

    $shared = (int)$shared;
    $active = (int)$active;
    if ($parentId === null) {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO bookmark_folders (company_id, employee_id, parent_folder_id, name, name_hash, shared, active) VALUES (?, ?, NULL, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iissii',
            $company_id,
            $user_id,
            $storage['text'],
            $storage['text_hash'],
            $shared,
            $active
        );
    } else {
        $parentId = (int)$parentId;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO bookmark_folders (company_id, employee_id, parent_folder_id, name, name_hash, shared, active) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iiissii',
            $company_id,
            $user_id,
            $parentId,
            $storage['text'],
            $storage['text_hash'],
            $shared,
            $active
        );
    }

    $ok = mysqli_stmt_execute($stmt);
    $message = $ok ? '' : (mysqli_error($conn) ?: 'Database error.');
    $newId = $ok ? (int)mysqli_insert_id($conn) : 0;
    mysqli_stmt_close($stmt);

    return ['ok' => $ok, 'message' => $message, 'id' => $newId];
}

/**
 * @return array{ok:bool,message:string}
 */
function bkm_update_folder_row($conn, $id, $company_id, $parentId, $plainName, $shared, $active)
{
    $id = (int)$id;
    $company_id = (int)$company_id;
    $shared = (int)$shared;
    $active = (int)$active;

    $storage = bkm_prepare_text_storage($plainName, $shared);
    if ($storage === null) {
        return ['ok' => false, 'message' => 'Unlock your vault to save private folders.'];
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE bookmark_folders SET parent_folder_id = ?, name = ?, name_hash = ?, shared = ?, active = ? WHERE id = ? AND company_id = ?'
    );
    mysqli_stmt_bind_param(
        $stmt,
        'issiiii',
        $parentId,
        $storage['text'],
        $storage['text_hash'],
        $shared,
        $active,
        $id,
        $company_id
    );

    $ok = mysqli_stmt_execute($stmt);
    $message = $ok ? '' : (mysqli_error($conn) ?: 'Database error.');
    mysqli_stmt_close($stmt);

    return ['ok' => $ok, 'message' => $message];
}

function bkm_create_import_folder($conn, $company_id, $user_id, $parentId, $name)
{
    $result = bkm_insert_folder_row($conn, $company_id, $user_id, $parentId, $name, 0, 1);

    return $result['ok'] ? $result['id'] : 0;
}

/**
 * SHA-256 hex digest of trimmed URL — used for dedupe (plaintext, before encryption).
 */
function bkm_bookmark_url_hash($url)
{
    return bkm_text_hash($url);
}

/**
 * SHA-256 hex digest of trimmed private text (folder name, bookmark title).
 */
function bkm_text_hash($text)
{
    return hash('sha256', trim((string)$text));
}

function bkm_vault_session_key()
{
    return isset($_SESSION['vault_key']) ? (string)$_SESSION['vault_key'] : '';
}

/**
 * @return array{text:string,text_hash:string}|null null when private and vault is locked
 */
function bkm_prepare_text_storage($plainText, $shared)
{
    $plainText = trim((string)$plainText);
    $textHash = bkm_text_hash($plainText);
    $shared = (int)$shared;

    if ($shared === 1) {
        return ['text' => $plainText, 'text_hash' => $textHash];
    }

    $vaultKey = bkm_vault_session_key();
    if ($vaultKey === '') {
        return null;
    }

    return [
        'text' => itm_encrypt($plainText, $vaultKey),
        'text_hash' => $textHash,
    ];
}

/**
 * @return array{text:string,locked:bool,label:string}
 */
function bkm_resolve_private_text($stored, $shared, $ownerId, $viewerEmployeeId, array $options = [])
{
    $stored = (string)$stored;
    $shared = (int)$shared;
    $ownerId = (int)$ownerId;
    $viewerEmployeeId = (int)$viewerEmployeeId;
    $otherUserLabel = (string)($options['other_user_label'] ?? '🔒 Private');
    $vaultLabel = (string)($options['vault_label'] ?? '🔒 Unlock vault to view');
    $decryptFailLabel = (string)($options['decrypt_fail_label'] ?? '🔒 Unable to decrypt');
    $legacyPlaintextCheck = $options['legacy_plaintext_check'] ?? null;

    if ($shared === 1) {
        return ['text' => $stored, 'locked' => false, 'label' => ''];
    }

    if ($ownerId !== $viewerEmployeeId) {
        return ['text' => '', 'locked' => true, 'label' => $otherUserLabel];
    }

    $vaultKey = bkm_vault_session_key();
    if ($vaultKey === '') {
        return ['text' => '', 'locked' => true, 'label' => $vaultLabel];
    }

    $plain = itm_decrypt($stored, $vaultKey);
    if ($plain === false || $plain === '') {
        if (is_callable($legacyPlaintextCheck) && $legacyPlaintextCheck($stored)) {
            $plain = $stored;
        } else {
            return ['text' => '', 'locked' => true, 'label' => $decryptFailLabel];
        }
    }

    return ['text' => $plain, 'locked' => false, 'label' => ''];
}

function bkm_hydrate_folder_row(array &$row, $viewerEmployeeId)
{
    $resolved = bkm_resolve_private_text(
        (string)($row['name'] ?? ''),
        (int)($row['shared'] ?? 0),
        (int)($row['employee_id'] ?? 0),
        $viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private folder',
            'vault_label' => '🔒 Unlock vault to view folder',
            'decrypt_fail_label' => '🔒 Unable to decrypt folder',
            'legacy_plaintext_check' => static function ($stored) {
                return $stored !== '' && strlen($stored) <= 255;
            },
        ]
    );
    $row['name'] = $resolved['text'];
    $row['name_locked'] = $resolved['locked'];
    $row['name_locked_label'] = $resolved['label'];
}

/**
 * @return array{url:string,url_hash:string}|null null when private bookmark and vault is locked
 */
function bkm_prepare_url_storage($plainUrl, $shared)
{
    $plainUrl = trim((string)$plainUrl);
    $urlHash = bkm_bookmark_url_hash($plainUrl);
    $shared = (int)$shared;

    if ($shared === 1) {
        return ['url' => $plainUrl, 'url_hash' => $urlHash];
    }

    $vaultKey = bkm_vault_session_key();
    if ($vaultKey === '') {
        return null;
    }

    return [
        'url' => itm_encrypt($plainUrl, $vaultKey),
        'url_hash' => $urlHash,
    ];
}

/**
 * @return array{url:string,locked:bool,label:string}
 */
function bkm_resolve_bookmark_url(array $row, $viewerEmployeeId)
{
    $stored = (string)($row['url'] ?? '');
    $shared = (int)($row['shared'] ?? 0);
    $ownerId = (int)($row['employee_id'] ?? 0);
    $viewerEmployeeId = (int)$viewerEmployeeId;

    if ($shared === 1) {
        return ['url' => $stored, 'locked' => false, 'label' => ''];
    }

    if ($ownerId !== $viewerEmployeeId) {
        return ['url' => '', 'locked' => true, 'label' => '🔒 Private URL'];
    }

    $vaultKey = bkm_vault_session_key();
    if ($vaultKey === '') {
        return ['url' => '', 'locked' => true, 'label' => '🔒 Unlock vault to view URL'];
    }

    $plain = itm_decrypt($stored, $vaultKey);
    if ($plain === false || $plain === '') {
        if (bkm_import_url_is_allowed($stored)) {
            $plain = $stored;
        } else {
            return ['url' => '', 'locked' => true, 'label' => '🔒 Unable to decrypt URL'];
        }
    }

    return ['url' => $plain, 'locked' => false, 'label' => ''];
}

function bkm_hydrate_bookmark_row(array &$row, $viewerEmployeeId)
{
    $urlResolved = bkm_resolve_bookmark_url($row, $viewerEmployeeId);
    $row['url_display'] = $urlResolved['url'];
    $row['url_locked'] = $urlResolved['locked'];
    $row['url_locked_label'] = $urlResolved['label'];

    $titleResolved = bkm_resolve_private_text(
        (string)($row['title'] ?? ''),
        (int)($row['shared'] ?? 0),
        (int)($row['employee_id'] ?? 0),
        $viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private title',
            'vault_label' => '🔒 Unlock vault to view title',
            'decrypt_fail_label' => '🔒 Unable to decrypt title',
            'legacy_plaintext_check' => static function ($stored) {
                return $stored !== '' && strlen($stored) <= 255;
            },
        ]
    );
    $row['title_display'] = $titleResolved['locked'] ? $titleResolved['label'] : $titleResolved['text'];
    $row['title_plain'] = $titleResolved['text'];
    $row['title_locked'] = $titleResolved['locked'];
    $row['title_locked_label'] = $titleResolved['label'];
}

/**
 * Build folder id => decrypted name map for list/search helpers.
 *
 * @param list<array<string,mixed>> $folders
 * @return array<int,string>
 */
function bkm_folder_name_map(array $folders)
{
    $map = [];
    foreach ($folders as $folder) {
        $map[(int)($folder['id'] ?? 0)] = (string)($folder['name'] ?? '');
    }

    return $map;
}

function bkm_row_matches_search(array $row, $searchRaw, array $folderNameById)
{
    $searchRaw = trim((string)$searchRaw);
    if ($searchRaw === '') {
        return true;
    }

    $needle = mb_strtolower($searchRaw);
    $haystacks = [
        mb_strtolower((string)($row['title_plain'] ?? $row['title_display'] ?? '')),
        mb_strtolower((string)($row['notes'] ?? '')),
    ];
    if (empty($row['url_locked'])) {
        $haystacks[] = mb_strtolower((string)($row['url_display'] ?? ''));
    }
    $folderId = (int)($row['folder_id'] ?? 0);
    if ($folderId > 0 && isset($folderNameById[$folderId])) {
        $haystacks[] = mb_strtolower((string)$folderNameById[$folderId]);
    }

    foreach ($haystacks as $haystack) {
        if ($haystack !== '' && mb_strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function bkm_compare_bookmark_rows(array $a, array $b, $sort, $dir, array $folderNameById)
{
    $mult = strtoupper((string)$dir) === 'DESC' ? -1 : 1;
    switch ((string)$sort) {
        case 'url':
            $va = (string)($a['url_display'] ?? '');
            $vb = (string)($b['url_display'] ?? '');
            break;
        case 'notes':
            $va = (string)($a['notes'] ?? '');
            $vb = (string)($b['notes'] ?? '');
            break;
        case 'shared':
            return $mult * (((int)($a['shared'] ?? 0)) <=> ((int)($b['shared'] ?? 0)));
        case 'folder':
            $va = $folderNameById[(int)($a['folder_id'] ?? 0)] ?? '';
            $vb = $folderNameById[(int)($b['folder_id'] ?? 0)] ?? '';
            break;
        default:
            $va = (string)($a['title_plain'] ?? $a['title_display'] ?? '');
            $vb = (string)($b['title_plain'] ?? $b['title_display'] ?? '');
    }

    return $mult * strcasecmp($va, $vb);
}

/**
 * Load bookmarks for dual-pane / flattened lists with PHP search, sort, and pagination.
 *
 * @return array{rows:list<array<string,mixed>>,totalRows:int,totalPages:int,page:int}
 */
function bkm_query_bookmarks_for_list($conn, array $options)
{
    $companyId = (int)($options['company_id'] ?? 0);
    $userId = (int)($options['user_id'] ?? 0);
    $viewMode = (string)($options['view_mode'] ?? 'all');
    $selectedFolderId = $options['selected_folder_id'] ?? null;
    $searchRaw = trim((string)($options['search'] ?? ''));
    $sort = (string)($options['sort'] ?? 'title');
    $dir = strtoupper((string)($options['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
    $page = max(1, (int)($options['page'] ?? 1));
    $perPage = max(1, (int)($options['per_page'] ?? 25));
    $folderNameById = (array)($options['folder_name_by_id'] ?? []);

    $where = "company_id = $companyId AND active = 1 AND (employee_id = $userId OR shared = 1)";
    if ($viewMode === 'private') {
        $where .= ' AND shared = 0';
    } elseif ($viewMode === 'shared') {
        $where .= ' AND shared = 1';
    }

    if ($searchRaw === '' && (string)($options['folder_scope'] ?? 'root_or_selected') === 'root_or_selected') {
        if ($selectedFolderId !== null && $selectedFolderId !== '') {
            $where .= ' AND folder_id = ' . (int)$selectedFolderId;
        } else {
            $where .= ' AND folder_id IS NULL';
        }
    }

    $rows = [];
    $res = mysqli_query($conn, "SELECT * FROM bookmarks WHERE $where");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        bkm_hydrate_bookmark_row($row, $userId);
        if ($searchRaw !== '' && !bkm_row_matches_search($row, $searchRaw, $folderNameById)) {
            continue;
        }
        $rows[] = $row;
    }

    usort($rows, static function (array $a, array $b) use ($sort, $dir, $folderNameById) {
        return bkm_compare_bookmark_rows($a, $b, $sort, $dir, $folderNameById);
    });

    $totalRows = count($rows);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    return [
        'rows' => array_slice($rows, $offset, $perPage),
        'totalRows' => $totalRows,
        'totalPages' => $totalPages,
        'page' => $page,
    ];
}

/**
 * Build one export row with plaintext URL when the viewer may read it (shared, or private + vault).
 *
 * @return array{title:string,url:string,notes:string,shared:int}
 */
function bkm_export_row(array $row, $viewerEmployeeId)
{
    bkm_hydrate_bookmark_row($row, $viewerEmployeeId);

    return [
        'title' => !empty($row['title_locked']) ? '' : (string)($row['title_plain'] ?? $row['title_display'] ?? ''),
        'url' => !empty($row['url_locked']) ? '' : (string)$row['url_display'],
        'notes' => (string)($row['notes'] ?? ''),
        'shared' => (int)($row['shared'] ?? 0),
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function bkm_insert_bookmark_row($conn, $company_id, $user_id, $folderId, $title, $plainUrl, $notes, $shared, $active = 1)
{
    $urlStorage = bkm_prepare_url_storage($plainUrl, $shared);
    if ($urlStorage === null) {
        return ['ok' => false, 'message' => 'Unlock your vault to save private bookmarks.'];
    }

    $titleStorage = bkm_prepare_text_storage($title, $shared);
    if ($titleStorage === null) {
        return ['ok' => false, 'message' => 'Unlock your vault to save private bookmarks.'];
    }

    if ($folderId === null) {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO bookmarks (company_id, employee_id, folder_id, title, url, url_hash, notes, shared, active) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iissssii',
            $company_id,
            $user_id,
            $titleStorage['text'],
            $urlStorage['url'],
            $urlStorage['url_hash'],
            $notes,
            $shared,
            $active
        );
    } else {
        $folderId = (int)$folderId;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO bookmarks (company_id, employee_id, folder_id, title, url, url_hash, notes, shared, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iiissssii',
            $company_id,
            $user_id,
            $folderId,
            $titleStorage['text'],
            $urlStorage['url'],
            $urlStorage['url_hash'],
            $notes,
            $shared,
            $active
        );
    }

    $ok = mysqli_stmt_execute($stmt);
    $message = $ok ? '' : (mysqli_error($conn) ?: 'Database error.');
    mysqli_stmt_close($stmt);

    return ['ok' => $ok, 'message' => $message];
}

/**
 * @return array{ok:bool,message:string}
 */
function bkm_update_bookmark_row($conn, $id, $company_id, $folderId, $title, $plainUrl, $notes, $shared, $active)
{
    $id = (int)$id;
    $company_id = (int)$company_id;
    $shared = (int)$shared;
    $active = (int)$active;

    $storage = bkm_prepare_url_storage($plainUrl, $shared);
    if ($storage === null) {
        return ['ok' => false, 'message' => 'Unlock your vault to save private bookmarks.'];
    }

    $titleStorage = bkm_prepare_text_storage($title, $shared);
    if ($titleStorage === null) {
        return ['ok' => false, 'message' => 'Unlock your vault to save private bookmarks.'];
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE bookmarks SET folder_id = ?, title = ?, url = ?, url_hash = ?, notes = ?, shared = ?, active = ? WHERE id = ? AND company_id = ?'
    );
    mysqli_stmt_bind_param(
        $stmt,
        'isssssiii',
        $folderId,
        $titleStorage['text'],
        $storage['url'],
        $storage['url_hash'],
        $notes,
        $shared,
        $active,
        $id,
        $company_id
    );

    $ok = mysqli_stmt_execute($stmt);
    $message = $ok ? '' : (mysqli_error($conn) ?: 'Database error.');
    mysqli_stmt_close($stmt);

    return ['ok' => $ok, 'message' => $message];
}

function bkm_insert_import_bookmark($conn, $company_id, $user_id, $folderId, $title, $url, $notes)
{
    $result = bkm_insert_bookmark_row($conn, $company_id, $user_id, $folderId, $title, $url, $notes, 0, 1);

    return $result['ok'];
}

/**
 * Import URLs must use http, https, or ftp.
 */
function bkm_import_url_is_allowed($url)
{
    $url = trim((string)$url);

    return (bool)preg_match('/^(https?|ftp):\/\//i', $url);
}

/**
 * Exact URL match for one employee in the tenant (any folder). Hard-delete only — no soft-deleted rows.
 */
function bkm_bookmark_url_exists_for_employee($conn, $company_id, $user_id, $url, $excludeId = null)
{
    $urlHash = bkm_bookmark_url_hash($url);
    $excludeId = $excludeId !== null ? (int)$excludeId : 0;

    if ($excludeId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM bookmarks WHERE company_id = ? AND employee_id = ? AND url_hash = ? AND id <> ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $user_id, $urlHash, $excludeId);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM bookmarks WHERE company_id = ? AND employee_id = ? AND url_hash = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $company_id, $user_id, $urlHash);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = $res && mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return (bool)$exists;
}

/**
 * @deprecated Use bkm_bookmark_url_exists_for_employee() — kept for callers that still pass folder_id.
 */
function bkm_bookmark_exists_in_folder($conn, $company_id, $user_id, $folderId, $url)
{
    return bkm_bookmark_url_exists_for_employee($conn, $company_id, $user_id, $url);
}

/**
 * Human-readable folder label for import skip reports.
 *
 * @param list<string> $folderPath
 */
function bkm_format_import_folder_label(array $folderPath, $folderId, array $foldersById)
{
    $pathLabel = implode(' / ', array_filter(array_map('trim', $folderPath), static function ($segment) {
        return $segment !== '';
    }));

    if ($pathLabel === '' && $folderId === null) {
        return 'Root';
    }

    if ($pathLabel === '' && $folderId !== null) {
        $folderId = (int)$folderId;
        return isset($foldersById[$folderId]) ? $foldersById[$folderId]['name'] : ('Folder #' . $folderId);
    }

    if ($folderId !== null && isset($foldersById[(int)$folderId])) {
        return $foldersById[(int)$folderId]['name'] . ' / ' . $pathLabel;
    }

    return $pathLabel;
}

/**
 * Short skip summary for import reports, e.g. "Duplicate URL → WD".
 */
function bkm_format_import_skip_summary($skipReason, $folderLabel)
{
    $folder = trim((string)$folderLabel);
    if ($folder === '') {
        $folder = 'Root';
    }

    switch ((string)$skipReason) {
        case 'invalid_url':
            $reason = 'Invalid URL';
            break;
        case 'duplicate_file':
        case 'duplicate_employee':
            $reason = 'Duplicate URL';
            break;
        case 'insert_failed':
            $reason = 'Save failed';
            break;
        case 'vault_locked':
            $reason = 'Vault locked';
            break;
        default:
            $reason = 'Not imported';
    }

    return $reason . ' → ' . $folder;
}

/**
 * Import success summary for result tables, e.g. "Successfully imported → WD".
 */
function bkm_format_import_success_summary($folderLabel)
{
    $folder = trim((string)$folderLabel);
    if ($folder === '') {
        $folder = 'Root';
    }

    return 'Successfully imported → ' . $folder;
}

/**
 * Table row class for import skip reasons (duplicate URL → light red, invalid URL → light yellow).
 */
function bkm_import_skip_row_class($skipReason)
{
    $skipReason = (string)$skipReason;

    if (in_array($skipReason, ['duplicate_file', 'duplicate_employee'], true)) {
        return 'bkm-import-row-duplicate';
    }

    if ($skipReason === 'invalid_url') {
        return 'bkm-import-row-invalid';
    }

    if ($skipReason === 'vault_locked') {
        return 'bkm-import-row-vault';
    }

    return '';
}

/**
 * Import one bookmark when URL is allowed and not already present for this employee.
 *
 * @param array<string,bool> $importedUrlKeys
 * @return array{imported:bool,skip_reason:string,skip_label:string}
 */
function bkm_try_import_bookmark($conn, $company_id, $user_id, $folderId, $title, $url, $notes, array &$importedUrlKeys)
{
    $url = trim((string)$url);
    $title = trim((string)$title);
    $notes = trim((string)$notes);

    if (!bkm_import_url_is_allowed($url)) {
        return [
            'imported' => false,
            'skip_reason' => 'invalid_url',
            'skip_label' => 'URL must start with http://, https://, or ftp://',
        ];
    }

    $batchKey = $url;
    if (isset($importedUrlKeys[$batchKey])) {
        return [
            'imported' => false,
            'skip_reason' => 'duplicate_file',
            'skip_label' => 'Duplicate URL in import file',
        ];
    }

    if (bkm_bookmark_url_exists_for_employee($conn, $company_id, $user_id, $url)) {
        return [
            'imported' => false,
            'skip_reason' => 'duplicate_employee',
            'skip_label' => 'Bookmark with this URL already exists for your account',
        ];
    }

    if (bkm_vault_session_key() === '') {
        return [
            'imported' => false,
            'skip_reason' => 'vault_locked',
            'skip_label' => 'Unlock your vault to import private bookmarks',
        ];
    }

    $insertResult = bkm_insert_bookmark_row($conn, $company_id, $user_id, $folderId, $title, $url, $notes, 0, 1);
    if (!$insertResult['ok']) {
        $isVault = stripos($insertResult['message'], 'vault') !== false;
        return [
            'imported' => false,
            'skip_reason' => $isVault ? 'vault_locked' : 'insert_failed',
            'skip_label' => $insertResult['message'] !== '' ? $insertResult['message'] : 'Could not save bookmark',
        ];
    }

    $importedUrlKeys[$batchKey] = true;

    return [
        'imported' => true,
        'skip_reason' => '',
        'skip_label' => '',
    ];
}

/**
 * Parses browser-exported HTML bookmarks (Netscape format).
 *
 * @return list<array{title:string,url:string,notes:string}>
 */
function bkm_parse_html_bookmarks($html) {
    $entries = bkm_parse_html_bookmark_entries($html);
    $bookmarks = [];
    foreach ($entries as $entry) {
        $bookmarks[] = [
            'title' => $entry['title'],
            'url' => $entry['url'],
            'notes' => $entry['notes'],
        ];
    }

    return $bookmarks;
}

/**
 * Extracts base domain and returns Google Favicon service URL.
 */
function bkm_get_favicon_url($url) {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return '';

    $parts = explode('.', $host);
    $count = count($parts);

    // Default to full host if it's already short
    $domain = $host;

    if ($count >= 2) {
        // Simple heuristic for .co.uk, .com.au etc.
        $last2 = $parts[$count-2] . '.' . $parts[$count-1];
        $known_multi_tlds = ['co.uk', 'com.au', 'net.au', 'org.uk', 'co.jp', 'com.br', 'com.mx'];

        if (in_array($last2, $known_multi_tlds) && $count >= 3) {
             $domain = $parts[$count-3] . '.' . $last2;
        } else {
             $domain = $last2;
        }
    }

    return "https://t3.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=http://www." . urlencode($domain) . "&size=32";
}

/**
 * Transform JSON/Excel import row literals for bookmarks.url / url_hash.
 */
function bkm_import_row_literal_to_string($literal)
{
    $literal = (string)$literal;
    if ($literal === 'NULL') {
        return '';
    }
    if (strlen($literal) >= 2 && $literal[0] === "'" && substr($literal, -1) === "'") {
        return str_replace(["\\'", '\\\\'], ["'", '\\'], substr($literal, 1, -1));
    }

    return $literal;
}

function bkm_apply_import_row_url_storage(array &$rowValues, $conn)
{
    if (!isset($rowValues['url']) || $rowValues['url'] === 'NULL') {
        return 'URL is required.';
    }

    $plainUrl = trim(bkm_import_row_literal_to_string($rowValues['url']));
    if ($plainUrl === '') {
        return 'URL is required.';
    }

    if (!bkm_import_url_is_allowed($plainUrl)) {
        return 'Invalid URL. Only http://, https://, and ftp:// protocols are allowed.';
    }

    $shared = 0;
    if (isset($rowValues['shared']) && $rowValues['shared'] !== 'NULL') {
        $sharedRaw = bkm_import_row_literal_to_string($rowValues['shared']);
        $shared = in_array(strtolower((string)$sharedRaw), ['1', 'true', 'yes', '✅'], true) ? 1 : 0;
    }

    $storage = bkm_prepare_url_storage($plainUrl, $shared);
    if ($storage === null) {
        return 'Unlock your vault to import private bookmarks.';
    }

    $rowValues['url'] = "'" . mysqli_real_escape_string($conn, $storage['url']) . "'";
    $rowValues['url_hash'] = "'" . mysqli_real_escape_string($conn, $storage['url_hash']) . "'";

    if (isset($rowValues['title']) && $rowValues['title'] !== 'NULL') {
        $plainTitle = trim(bkm_import_row_literal_to_string($rowValues['title']));
        if ($plainTitle === '') {
            return 'Title is required.';
        }
        $titleStorage = bkm_prepare_text_storage($plainTitle, $shared);
        if ($titleStorage === null) {
            return 'Unlock your vault to import private bookmarks.';
        }
        $rowValues['title'] = "'" . mysqli_real_escape_string($conn, $titleStorage['text']) . "'";
    }

    return '';
}
