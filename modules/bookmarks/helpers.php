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
    $where = "company_id = $company_id AND (user_id = $user_id";
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
 * Renders the folder tree as HTML list items.
 */
function bkm_render_folder_tree_html(array $tree, $selectedFolderId, $depth = 0) {
    $html = '';
    foreach ($tree as $node) {
        $id = (int)$node['id'];
        $isActive = ($id === (int)$selectedFolderId) ? ' active' : '';
        $icon = (isset($node['shared']) && $node['shared'] == 1) ? '🔓' : '🔒';
        $padding = $depth * 15;

        $html .= '<li class="itm-folder-tree-item' . $isActive . '" data-folder-id="' . $id . '" draggable="true" ondragstart="drag(event)" ondrop="drop(event)" ondragover="allowDrop(event)">';
        $html .= '<div class="itm-folder-tree-row" style="padding-left:' . $padding . 'px;">';
        $html .= '<a href="index.php?folder_id=' . $id . '">📁 ' . $icon . ' ' . sanitize($node['name']) . '</a>';
        $html .= '</div>';

        if (!empty($node['children'])) {
            $html .= '<ul class="itm-folder-tree-children">' . bkm_render_folder_tree_html($node['children'], $selectedFolderId, $depth + 1) . '</ul>';
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
 * Permission check for editing a bookmark.
 */
function bkm_can_edit_bookmark($bookmark, $user_id, $is_admin) {
    if ($is_admin) return true;
    return (int)($bookmark['user_id'] ?? 0) === (int)$user_id;
}

/**
 * Permission check for editing a folder.
 */
function bkm_can_edit_folder($folder, $user_id, $is_admin) {
    if ($is_admin) return true;
    return (int)($folder['user_id'] ?? 0) === (int)$user_id;
}

/**
 * Parses browser-exported HTML bookmarks (Netscape format).
 */
function bkm_parse_html_bookmarks($html) {
    $bookmarks = [];
    // Basic regex-based parser for <A HREF="...">Label</A>
    preg_match_all('/<A HREF="([^"]+)"[^>]*>(.*?)<\/A>/i', $html, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $bookmarks[] = [
            'url' => $match[1],
            'title' => strip_tags($match[2]),
            'notes' => ''
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
