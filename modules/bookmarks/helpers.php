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

    $sql = "SELECT * FROM bookmark_folders WHERE $where ORDER BY position ASC, name ASC";
    $res = mysqli_query($conn, $sql);
    $folders = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $folders[] = $row;
    }
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
    if ($parentId === null) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM bookmark_folders WHERE company_id = ? AND employee_id = ? AND active = 1 AND parent_folder_id IS NULL AND name = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $company_id, $user_id, $name);
    } else {
        $parentId = (int)$parentId;
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM bookmark_folders WHERE company_id = ? AND employee_id = ? AND active = 1 AND parent_folder_id = ? AND name = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iiis', $company_id, $user_id, $parentId, $name);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ? (int)$row['id'] : null;
}

function bkm_create_import_folder($conn, $company_id, $user_id, $parentId, $name)
{
    $shared = 0;
    $active = 1;
    if ($parentId === null) {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO bookmark_folders (company_id, employee_id, parent_folder_id, name, shared, active) VALUES (?, ?, NULL, ?, ?, ?)'
        );
        mysqli_stmt_bind_param($stmt, 'iisii', $company_id, $user_id, $name, $shared, $active);
    } else {
        $parentId = (int)$parentId;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO bookmark_folders (company_id, employee_id, parent_folder_id, name, shared, active) VALUES (?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param($stmt, 'iiisii', $company_id, $user_id, $parentId, $name, $shared, $active);
    }
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $newId;
}

function bkm_insert_import_bookmark($conn, $company_id, $user_id, $folderId, $title, $url, $notes)
{
    if ($folderId === null) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO bookmarks (company_id, employee_id, folder_id, title, url, notes) VALUES (?, ?, NULL, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $user_id, $title, $url, $notes);
    } else {
        $folderId = (int)$folderId;
        $stmt = mysqli_prepare($conn, 'INSERT INTO bookmarks (company_id, employee_id, folder_id, title, url, notes) VALUES (?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iiisss', $company_id, $user_id, $folderId, $title, $url, $notes);
    }

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
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
    $url = trim((string)$url);
    $excludeId = $excludeId !== null ? (int)$excludeId : 0;

    if ($excludeId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM bookmarks WHERE company_id = ? AND employee_id = ? AND url = ? AND id <> ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $user_id, $url, $excludeId);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM bookmarks WHERE company_id = ? AND employee_id = ? AND url = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $company_id, $user_id, $url);
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
        default:
            $reason = 'Not imported';
    }

    return $reason . ' → ' . $folder;
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

    if (!bkm_insert_import_bookmark($conn, $company_id, $user_id, $folderId, $title, $url, $notes)) {
        return [
            'imported' => false,
            'skip_reason' => 'insert_failed',
            'skip_label' => 'Could not save bookmark',
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
