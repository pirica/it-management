<?php
/**
 * Floor Plans gallery helpers.
 * Why: Keeps upload, folder tree, tagging, and filesystem cleanup out of the main CRUD router.
 */

function fp_floor_plan_schema_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $tables = ['floor_plan_folders', 'floor_plan_tags', 'floor_plans', 'floor_plan_item_tags'];
    foreach ($tables as $table) {
        if (!itm_is_safe_identifier($table)) {
            $ready = false;
            return false;
        }
        $res = mysqli_query($conn, 'SHOW TABLES LIKE \'' . mysqli_real_escape_string($conn, $table) . '\'');
        if (!$res || mysqli_num_rows($res) === 0) {
            $ready = false;
            return false;
        }
    }
    $ready = true;
    return true;
}

function fp_resolve_active_company_id(): int
{
    return (int)($_SESSION['company_id'] ?? 0);
}

function fp_gallery_access_error(mysqli $conn): string
{
    if (fp_resolve_active_company_id() <= 0) {
        return 'Select a company from the home page before using Floor Plans.';
    }
    if (!fp_floor_plan_schema_ready($conn)) {
        return 'Floor Plans database tables are missing. Apply the floor plan section from db/ (tables floor_plan_folders, floor_plan_tags, floor_plans, floor_plan_item_tags).';
    }
    return '';
}

function fp_company_upload_dir(int $companyId): string {
    $base = FLOOR_PLAN_UPLOAD_PATH . (int)$companyId . DIRECTORY_SEPARATOR;
    itm_ensure_upload_directory($base, 'upload');
    return $base;
}

function fp_detect_upload_mime_type(string $tmpName): string {
    if ($tmpName === '' || !is_file($tmpName)) {
        return '';
    }
    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }
    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
        return strtolower((string)$imageInfo['mime']);
    }
    return '';
}

function fp_normalize_extension(string $originalName): string {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        return 'jpg';
    }
    return $ext;
}

/**
 * Normalizes gallery search text for file_ext matching so ".pdf" and "pdf" both hit stored "pdf".
 */
function fp_search_ext_like_pattern(string $searchRaw): string {
    if (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) {
        return $searchRaw;
    }
    $ext = strtolower(ltrim(trim($searchRaw), '.'));
    if ($ext === '') {
        return '%%';
    }
    return '%' . $ext . '%';
}

function fp_is_cad_extension(string $ext): bool {
    return in_array(strtolower($ext), FLOOR_PLAN_CAD_EXTENSIONS, true);
}

function fp_resolve_preview_kind(string $mime, string $ext): string {
    if (fp_is_pdf_mime($mime) || strtolower($ext) === 'pdf') {
        return 'pdf';
    }
    if (fp_is_cad_extension($ext)) {
        return 'cad';
    }
    if (in_array(strtolower($mime), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return 'image';
    }
    return 'download';
}

function fp_validate_upload_file(array $file, &$error): bool {
    $error = '';
    $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileError === UPLOAD_ERR_NO_FILE) {
        $error = 'No file selected.';
        return false;
    }
    if ($fileError !== UPLOAD_ERR_OK) {
        $error = 'Upload failed.';
        return false;
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > (int)FLOOR_PLAN_MAX_FILE_SIZE) {
        $error = 'File exceeds the maximum allowed size (20MB).';
        return false;
    }
    $ext = fp_normalize_extension((string)($file['name'] ?? ''));
    if (!in_array($ext, FLOOR_PLAN_ALLOWED_EXTENSIONS, true)) {
        $error = 'Unsupported file extension.';
        return false;
    }
    $tmpName = (string)($file['tmp_name'] ?? '');
    $mime = fp_detect_upload_mime_type($tmpName);
    if (fp_is_cad_extension($ext)) {
        return true;
    }
    if (!in_array($mime, FLOOR_PLAN_ALLOWED_TYPES, true)) {
        $error = 'Only images, PDF, and AutoCAD files (DWG, DXF, DWF, DWS) are allowed.';
        return false;
    }
    if ($mime === 'application/pdf' && $ext !== 'pdf') {
        $error = 'PDF extension mismatch.';
        return false;
    }
    return true;
}

// Human-facing copy: each floor plan optionally links to one it_locations row (nullable FK on floor_plans).
function fp_it_location_link_label(): string
{
    return 'Link to IT Location';
}

function fp_it_location_link_label_optional(): string
{
    return 'Link to IT Location (optional)';
}

function fp_it_location_belongs_to_company(mysqli $conn, int $locationId, int $companyId): bool {
    if ($locationId <= 0 || $companyId <= 0) {
        return false;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id FROM it_locations WHERE id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $locationId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = ($res && mysqli_num_rows($res) === 1);
    mysqli_stmt_close($stmt);
    return $ok;
}

function fp_fetch_it_location_options(mysqli $conn, int $companyId): array {
    $rows = [];
    if ($companyId <= 0) {
        return $rows;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id, name FROM it_locations WHERE company_id=? AND active=1 ORDER BY name ASC');
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function fp_it_location_label_by_id(mysqli $conn, int $companyId, $rawId): string {
    $id = (int)$rawId;
    if ($id <= 0) {
        return '';
    }
    if ($companyId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT name FROM it_locations WHERE id=? AND company_id=? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = ($res) ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (is_array($row) && isset($row['name'])) {
                return (string)$row['name'];
            }
        }
    }
    $fallback = mysqli_query($conn, 'SELECT name FROM it_locations WHERE id=' . $id . ' LIMIT 1');
    $fallbackRow = ($fallback) ? mysqli_fetch_assoc($fallback) : null;
    if (is_array($fallbackRow) && isset($fallbackRow['name'])) {
        return (string)$fallbackRow['name'];
    }
    return '';
}

function fp_resolve_post_it_location_id(mysqli $conn, int $companyId, $rawValue): ?int {
    $locationId = (int)$rawValue;
    if ($locationId <= 0) {
        return null;
    }
    if (!fp_it_location_belongs_to_company($conn, $locationId, $companyId)) {
        return -1;
    }
    return $locationId;
}

function fp_apply_plan_it_location(mysqli $conn, int $planId, int $companyId, ?int $locationId): void {
    if ($planId <= 0 || $companyId <= 0) {
        return;
    }
    if ($locationId !== null && $locationId > 0) {
        $stmt = mysqli_prepare($conn, 'UPDATE floor_plans SET it_location_id=? WHERE id=? AND company_id=? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iii', $locationId, $planId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        mysqli_query($conn, 'UPDATE floor_plans SET it_location_id=NULL WHERE id=' . (int)$planId . ' AND company_id=' . (int)$companyId . ' LIMIT 1');
    }
}

function fp_public_url(int $companyId, string $storedFilename): string {
    return FLOOR_PLAN_UPLOAD_URL . rawurlencode((string)$companyId) . '/' . rawurlencode($storedFilename);
}

function fp_absolute_path(int $companyId, string $storedFilename): string {
    return fp_company_upload_dir($companyId) . $storedFilename;
}

function fp_unlink_stored_file(int $companyId, string $storedFilename): void {
    $path = fp_absolute_path($companyId, $storedFilename);
    if ($storedFilename !== '' && is_file($path)) {
        @unlink($path);
    }
}

function fp_folder_belongs_to_company(mysqli $conn, int $folderId, int $companyId): bool {
    if ($folderId <= 0 || $companyId <= 0) {
        return false;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id FROM floor_plan_folders WHERE id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = ($res && mysqli_num_rows($res) === 1);
    mysqli_stmt_close($stmt);
    return $ok;
}

function fp_fetch_folders(mysqli $conn, int $companyId): array {
    $rows = [];
    if ($companyId <= 0) {
        return $rows;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id, parent_folder_id, name FROM floor_plan_folders WHERE company_id=? AND active=1 ORDER BY name ASC');
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function fp_build_folder_tree(array $folders, $parentId = null): array {
    $tree = [];
    foreach ($folders as $folder) {
        $pid = $folder['parent_folder_id'];
        $pid = ($pid === null || $pid === '') ? null : (int)$pid;
        $matchParent = ($parentId === null) ? ($pid === null) : ($pid === (int)$parentId);
        if (!$matchParent) {
            continue;
        }
        $id = (int)$folder['id'];
        $tree[] = [
            'id' => $id,
            'name' => (string)$folder['name'],
            'parent_folder_id' => $pid,
            'children' => fp_build_folder_tree($folders, $id),
        ];
    }
    return $tree;
}

/**
 * Why: View screens and breadcrumbs need the full path (General → Level 1), not only the leaf folder name.
 */
function fp_folder_breadcrumb_label(array $folders, int $folderId, string $separator = ' → '): string
{
    if ($folderId <= 0 || empty($folders)) {
        return '';
    }
    $byId = [];
    foreach ($folders as $folder) {
        $id = (int)($folder['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $folder;
        }
    }
    $parts = [];
    $current = $folderId;
    $guard = 0;
    while ($current > 0 && isset($byId[$current]) && $guard < 64) {
        $name = trim((string)($byId[$current]['name'] ?? ''));
        if ($name !== '') {
            array_unshift($parts, $name);
        }
        $parentId = fp_folder_parent_id_from_row($byId[$current]);
        $current = ($parentId !== null && $parentId > 0) ? $parentId : 0;
        $guard++;
    }
    return implode($separator, $parts);
}

/**
 * Why: Folder dropdowns mirror the gallery tree (root names flat; nested folders prefixed with em dashes).
 */
function fp_folder_select_option_label(string $name, int $depth): string
{
    if ($depth <= 0) {
        return $name;
    }
    return str_repeat('— ', $depth) . $name;
}

function fp_render_folder_select_options(array $folders, ?int $selectedFolderId): string
{
    $tree = fp_build_folder_tree($folders, null);
    return fp_render_folder_select_options_from_tree($tree, $selectedFolderId, 0);
}

function fp_render_folder_select_options_from_tree(array $tree, ?int $selectedFolderId, int $depth): string
{
    $html = '';
    foreach ($tree as $node) {
        $id = (int)($node['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $isSelected = ($selectedFolderId !== null && $selectedFolderId > 0 && $selectedFolderId === $id);
        $label = fp_folder_select_option_label((string)($node['name'] ?? ''), $depth);
        $html .= '<option value="' . $id . '"' . ($isSelected ? ' selected' : '') . '>'
            . sanitize($label) . '</option>';
        if (!empty($node['children'])) {
            $html .= fp_render_folder_select_options_from_tree($node['children'], $selectedFolderId, $depth + 1);
        }
    }
    return $html;
}

function fp_folder_has_children(mysqli $conn, int $folderId, int $companyId): bool {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM floor_plan_folders WHERE parent_folder_id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $has = ($res && mysqli_num_rows($res) > 0);
    mysqli_stmt_close($stmt);
    return $has;
}

function fp_folder_has_files(mysqli $conn, int $folderId, int $companyId): bool {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM floor_plans WHERE folder_id=? AND company_id=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $has = ($res && mysqli_num_rows($res) > 0);
    mysqli_stmt_close($stmt);
    return $has;
}

function fp_folder_name_exists(mysqli $conn, int $companyId, ?int $parentFolderId, string $name, int $excludeId = 0): bool {
    $parentSql = ($parentFolderId === null || $parentFolderId <= 0)
        ? 'parent_folder_id IS NULL'
        : 'parent_folder_id=' . (int)$parentFolderId;
    $excludeSql = $excludeId > 0 ? ' AND id<>' . (int)$excludeId : '';
    $nameEsc = mysqli_real_escape_string($conn, $name);
    $sql = "SELECT id FROM floor_plan_folders WHERE company_id=" . (int)$companyId
        . " AND {$parentSql} AND name='{$nameEsc}'" . $excludeSql . ' LIMIT 1';
    $res = mysqli_query($conn, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}

function fp_collect_folder_descendant_ids(array $folders, int $rootId): array {
    $ids = [$rootId];
    foreach ($folders as $folder) {
        $parentId = fp_folder_parent_id_from_db_value($folder['parent_folder_id'] ?? null);
        if ($parentId === $rootId) {
            $childId = (int)$folder['id'];
            $ids = array_merge($ids, fp_collect_folder_descendant_ids($folders, $childId));
        }
    }
    return array_values(array_unique($ids));
}

function fp_folder_row_by_id(array $folders, int $folderId): ?array
{
    foreach ($folders as $folder) {
        if ((int)($folder['id'] ?? 0) === $folderId) {
            return $folder;
        }
    }
    return null;
}

function fp_folder_parent_id_from_row(?array $folderRow): ?int
{
    if ($folderRow === null) {
        return null;
    }
    $parent = $folderRow['parent_folder_id'] ?? null;
    if ($parent === null || $parent === '' || (int)$parent <= 0) {
        return null;
    }
    return (int)$parent;
}

function fp_folder_parent_id_from_db_value($rawParent): ?int
{
    if ($rawParent === null || $rawParent === '' || (int)$rawParent <= 0) {
        return null;
    }
    return (int)$rawParent;
}

function fp_can_move_folder_to_parent(array $folders, int $folderId, ?int $newParentId): bool
{
    if ($newParentId === null || $newParentId <= 0) {
        return true;
    }
    $blocked = fp_collect_folder_descendant_ids($folders, $folderId);
    return !in_array($newParentId, $blocked, true);
}

function fp_move_folder_to_parent(mysqli $conn, int $companyId, int $folderId, ?int $newParentId, array $allFolders): string
{
    if ($folderId <= 0 || !fp_folder_belongs_to_company($conn, $folderId, $companyId)) {
        return 'Folder not found.';
    }
    if ($newParentId !== null && $newParentId > 0 && !fp_folder_belongs_to_company($conn, $newParentId, $companyId)) {
        return 'Target folder not found.';
    }
    if (!fp_can_move_folder_to_parent($allFolders, $folderId, $newParentId)) {
        return 'Cannot move a folder into itself or one of its subfolders.';
    }

    $folderRow = fp_folder_row_by_id($allFolders, $folderId);
    if ($folderRow === null) {
        return 'Folder not found.';
    }

    $currentParentId = fp_folder_parent_id_from_row($folderRow);
    $normalizedParent = ($newParentId !== null && $newParentId > 0) ? $newParentId : null;
    if ($currentParentId === $normalizedParent) {
        return '__NOOP__';
    }

    $folderName = trim((string)($folderRow['name'] ?? ''));
    if ($folderName === '') {
        return 'Folder name is required.';
    }
    if (fp_folder_name_exists($conn, $companyId, $normalizedParent, $folderName, $folderId)) {
        return 'A folder with that name already exists at the target location.';
    }

    if ($normalizedParent === null) {
        $stmt = mysqli_prepare($conn, 'UPDATE floor_plan_folders SET parent_folder_id=NULL WHERE id=? AND company_id=? LIMIT 1');
        if (!$stmt) {
            return 'Could not move folder.';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $folderId, $companyId);
    } else {
        $stmt = mysqli_prepare($conn, 'UPDATE floor_plan_folders SET parent_folder_id=? WHERE id=? AND company_id=? LIMIT 1');
        if (!$stmt) {
            return 'Could not move folder.';
        }
        mysqli_stmt_bind_param($stmt, 'iii', $normalizedParent, $folderId, $companyId);
    }
    $folderOldValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $folderId, $companyId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 'Could not move folder.';
    }
    mysqli_stmt_close($stmt);
    if ($folderOldValues !== null) {
        $folderNewValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $folderId, $companyId);
        fp_audit_log_record($conn, 'floor_plan_folders', $folderId, $companyId, 'UPDATE', $folderOldValues, $folderNewValues);
    }
    return '';
}

function fp_render_folder_move_parent_options(array $folders, int $movingFolderId, ?int $selectedParentId): string
{
    $blocked = fp_collect_folder_descendant_ids($folders, $movingFolderId);
    $tree = fp_build_folder_tree($folders, null);
    return fp_render_folder_move_options_from_tree($tree, $blocked, $selectedParentId, 0);
}

function fp_render_folder_move_options_from_tree(array $tree, array $blocked, ?int $selectedParentId, int $depth): string
{
    $html = '';
    foreach ($tree as $node) {
        $id = (int)($node['id'] ?? 0);
        if ($id <= 0 || in_array($id, $blocked, true)) {
            continue;
        }
        $isSelected = ($selectedParentId !== null && $selectedParentId === $id);
        $html .= '<option value="' . $id . '"' . ($isSelected ? ' selected' : '') . '>'
            . sanitize(fp_folder_select_option_label((string)$node['name'], $depth)) . '</option>';
        if (!empty($node['children'])) {
            $html .= fp_render_folder_move_options_from_tree($node['children'], $blocked, $selectedParentId, $depth + 1);
        }
    }
    return $html;
}

function fp_get_tags_for_plan(mysqli $conn, int $planId, int $companyId): array {
    $tags = [];
    $sql = 'SELECT t.id, t.name FROM floor_plan_tags t
        INNER JOIN floor_plan_item_tags it ON it.tag_id=t.id AND it.company_id=' . (int)$companyId . '
        WHERE it.floor_plan_id=' . (int)$planId . ' AND t.company_id=' . (int)$companyId . '
        ORDER BY t.name ASC';
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $tags[] = $row;
    }
    return $tags;
}

function fp_save_tags_for_plan(mysqli $conn, int $planId, int $companyId, array $tagNames): void {
    mysqli_query($conn, 'DELETE FROM floor_plan_item_tags WHERE floor_plan_id=' . (int)$planId . ' AND company_id=' . (int)$companyId);
    foreach ($tagNames as $rawName) {
        $name = trim((string)$rawName);
        if ($name === '') {
            continue;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id FROM floor_plan_tags WHERE company_id=? AND name=? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $tagId = 0;
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $tagId = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);
        if ($tagId <= 0) {
            $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO floor_plan_tags (company_id, name, active) VALUES (?, ?, 1)');
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'is', $companyId, $name);
                if (mysqli_stmt_execute($ins) && mysqli_stmt_affected_rows($ins) > 0) {
                    $tagId = (int)mysqli_insert_id($conn);
                    if ($tagId > 0) {
                        $tagNewValues = fp_audit_fetch_record($conn, 'floor_plan_tags', $tagId, $companyId);
                        fp_audit_log_record($conn, 'floor_plan_tags', $tagId, $companyId, 'INSERT', null, $tagNewValues);
                    }
                }
                mysqli_stmt_close($ins);
            }
            if ($tagId <= 0) {
                $retry = mysqli_prepare($conn, 'SELECT id FROM floor_plan_tags WHERE company_id=? AND name=? LIMIT 1');
                if ($retry) {
                    mysqli_stmt_bind_param($retry, 'is', $companyId, $name);
                    mysqli_stmt_execute($retry);
                    $retryRes = mysqli_stmt_get_result($retry);
                    if ($retryRes && ($retryRow = mysqli_fetch_assoc($retryRes))) {
                        $tagId = (int)$retryRow['id'];
                    }
                    mysqli_stmt_close($retry);
                }
            }
        }
        if ($tagId > 0) {
            $employeeId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : null;
            if ($employeeId !== null && $employeeId > 0) {
                $link = mysqli_prepare($conn, 'INSERT IGNORE INTO floor_plan_item_tags (company_id, floor_plan_id, tag_id, created_by) VALUES (?, ?, ?, ?)');
                if ($link) {
                    mysqli_stmt_bind_param($link, 'iiii', $companyId, $planId, $tagId, $employeeId);
                    mysqli_stmt_execute($link);
                    mysqli_stmt_close($link);
                }
            } else {
                $link = mysqli_prepare($conn, 'INSERT IGNORE INTO floor_plan_item_tags (company_id, floor_plan_id, tag_id) VALUES (?, ?, ?)');
                if ($link) {
                    mysqli_stmt_bind_param($link, 'iii', $companyId, $planId, $tagId);
                    mysqli_stmt_execute($link);
                    mysqli_stmt_close($link);
                }
            }
        }
    }
}

function fp_parse_tag_input(string $raw): array {
    $parts = preg_split('/[,;]+/', $raw) ?: [];
    $names = [];
    foreach ($parts as $part) {
        $name = trim((string)$part);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return array_values(array_unique($names));
}

/**
 * Snapshot a gallery table row for audit_logs (company-scoped when possible).
 */
function fp_audit_fetch_record(mysqli $conn, string $table, int $recordId, int $companyId)
{
    if ($recordId <= 0 || !function_exists('itm_fetch_audit_record') || !itm_is_safe_identifier($table)) {
        return null;
    }
    return itm_fetch_audit_record($conn, $table, $recordId, $companyId);
}

function fp_audit_log_record(mysqli $conn, string $table, int $recordId, int $companyId, string $action, $oldValues = null, $newValues = null): void
{
    if ($recordId <= 0 || !function_exists('itm_log_audit') || !itm_is_safe_identifier($table)) {
        return;
    }
    itm_log_audit($conn, $table, $recordId, $action, $oldValues, $newValues);
}

function fp_audit_fetch_floor_plan(mysqli $conn, int $planId, int $companyId)
{
    return fp_audit_fetch_record($conn, 'floor_plans', $planId, $companyId);
}

/**
 * Why: Gallery delete flows share one helper; logging here covers delete_file, bulk delete, and delete.php.
 */
function fp_audit_log_floor_plan(mysqli $conn, int $planId, int $companyId, string $action, $oldValues = null, $newValues = null): void
{
    fp_audit_log_record($conn, 'floor_plans', $planId, $companyId, $action, $oldValues, $newValues);
}

function fp_delete_plans_by_ids(mysqli $conn, array $ids, int $companyId): void {
    if (empty($ids) || $companyId <= 0) {
        return;
    }
    $idList = array_values(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    }));
    if (empty($idList)) {
        return;
    }
    $in = implode(',', $idList);
    $auditBeforeDelete = [];
    if (function_exists('itm_log_audit') && function_exists('itm_fetch_audit_record')) {
        foreach ($idList as $planId) {
            $snapshot = fp_audit_fetch_floor_plan($conn, $planId, $companyId);
            if ($snapshot !== null) {
                $auditBeforeDelete[$planId] = $snapshot;
            }
        }
    }
    $res = mysqli_query($conn, 'SELECT id, stored_filename FROM floor_plans WHERE company_id=' . (int)$companyId . ' AND id IN (' . $in . ')');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        fp_unlink_stored_file($companyId, (string)($row['stored_filename'] ?? ''));
    }
    // Why: Codex review — only write DELETE audit rows when the DB delete actually removed the record.
    if (!mysqli_query($conn, 'DELETE FROM floor_plans WHERE company_id=' . (int)$companyId . ' AND id IN (' . $in . ')')) {
        return;
    }
    foreach ($auditBeforeDelete as $planId => $oldValues) {
        $stillExists = mysqli_query(
            $conn,
            'SELECT 1 FROM floor_plans WHERE id=' . (int)$planId . ' AND company_id=' . (int)$companyId . ' LIMIT 1'
        );
        if ($stillExists && mysqli_num_rows($stillExists) > 0) {
            continue;
        }
        fp_audit_log_floor_plan($conn, (int)$planId, $companyId, 'DELETE', $oldValues, null);
    }
}

function fp_fetch_gallery_items(mysqli $conn, int $companyId, string $searchRaw, ?int $folderFilter, bool $unfiledOnly): array {
    $params = [];
    $types = '';
    $where = 'fp.company_id=? AND fp.deleted_at IS NULL';
    $params[] = $companyId;
    $types .= 'i';

    if ($unfiledOnly) {
        $where .= ' AND fp.folder_id IS NULL';
    } elseif ($folderFilter !== null && $folderFilter > 0) {
        $folders = fp_fetch_folders($conn, $companyId);
        $folderIds = fp_collect_folder_descendant_ids($folders, $folderFilter);
        $where .= ' AND fp.folder_id IN (' . implode(',', array_map('intval', $folderIds)) . ')';
    }

    if ($searchRaw !== '') {
        $like = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
        $extLike = fp_search_ext_like_pattern($searchRaw);
        $where .= ' AND (fp.display_name LIKE ? OR f.name LIKE ? OR t.name LIKE ? OR loc.name LIKE ? OR fp.file_ext LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $extLike;
        $types .= 'sssss';
    }

    $sql = 'SELECT fp.id, fp.folder_id, fp.it_location_id, fp.display_name, fp.stored_filename, fp.mime_type, fp.file_ext, fp.file_size, fp.created_at,
        f.name AS folder_name,
        loc.name AS location_name,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ") AS tag_names
        FROM floor_plans fp
        LEFT JOIN floor_plan_folders f ON f.id=fp.folder_id AND f.company_id=fp.company_id
        LEFT JOIN it_locations loc ON loc.id=fp.it_location_id AND loc.company_id=fp.company_id
        LEFT JOIN floor_plan_item_tags it ON it.floor_plan_id=fp.id AND it.company_id=fp.company_id
        LEFT JOIN floor_plan_tags t ON t.id=it.tag_id AND t.company_id=fp.company_id
        WHERE ' . $where . '
        GROUP BY fp.id
        ORDER BY fp.display_name ASC';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    if (!empty($items)) {
        $folderRows = fp_fetch_folders($conn, $companyId);
        foreach ($items as $idx => $item) {
            $folderId = (int)($item['folder_id'] ?? 0);
            if ($folderId <= 0) {
                continue;
            }
            $pathLabel = fp_folder_breadcrumb_label($folderRows, $folderId);
            if ($pathLabel !== '') {
                $items[$idx]['folder_name'] = $pathLabel;
            }
        }
    }
    return $items;
}

function fp_seed_sample_folders_and_tags(mysqli $conn, int $companyId): int {
    $inserted = 0;
    if ($companyId <= 0) {
        return 0;
    }
    $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM floor_plan_folders WHERE company_id=' . (int)$companyId);
    $folderCount = 0;
    if ($countRes && ($row = mysqli_fetch_assoc($countRes))) {
        $folderCount = (int)($row['c'] ?? 0);
    }
    if ($folderCount === 0) {
        $rootStmt = mysqli_prepare($conn, 'INSERT INTO floor_plan_folders (company_id, parent_folder_id, name, active) VALUES (?, NULL, ?, 1)');
        if ($rootStmt) {
            $rootName = 'General';
            mysqli_stmt_bind_param($rootStmt, 'is', $companyId, $rootName);
            if (mysqli_stmt_execute($rootStmt)) {
                $inserted++;
                $rootId = (int)mysqli_insert_id($conn);
                if ($rootId > 0) {
                    $rootNewValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $rootId, $companyId);
                    fp_audit_log_record($conn, 'floor_plan_folders', $rootId, $companyId, 'INSERT', null, $rootNewValues);
                }
                mysqli_stmt_close($rootStmt);
                $childStmt = mysqli_prepare($conn, 'INSERT INTO floor_plan_folders (company_id, parent_folder_id, name, active) VALUES (?, ?, ?, 1)');
                if ($childStmt) {
                    $childName = 'Level 1';
                    mysqli_stmt_bind_param($childStmt, 'iis', $companyId, $rootId, $childName);
                    if (mysqli_stmt_execute($childStmt)) {
                        $inserted++;
                        $childId = (int)mysqli_insert_id($conn);
                        if ($childId > 0) {
                            $childNewValues = fp_audit_fetch_record($conn, 'floor_plan_folders', $childId, $companyId);
                            fp_audit_log_record($conn, 'floor_plan_folders', $childId, $companyId, 'INSERT', null, $childNewValues);
                        }
                    }
                    mysqli_stmt_close($childStmt);
                }
            } else {
                mysqli_stmt_close($rootStmt);
            }
        }
    }
    foreach (['Ground Floor', 'Building A'] as $tagName) {
        $tagStmt = mysqli_prepare($conn, 'INSERT IGNORE INTO floor_plan_tags (company_id, name, active) VALUES (?, ?, 1)');
        if ($tagStmt) {
            mysqli_stmt_bind_param($tagStmt, 'is', $companyId, $tagName);
            if (mysqli_stmt_execute($tagStmt) && mysqli_affected_rows($conn) > 0) {
                $inserted++;
                $tagId = (int)mysqli_insert_id($conn);
                if ($tagId > 0) {
                    $tagNewValues = fp_audit_fetch_record($conn, 'floor_plan_tags', $tagId, $companyId);
                    fp_audit_log_record($conn, 'floor_plan_tags', $tagId, $companyId, 'INSERT', null, $tagNewValues);
                }
            }
            mysqli_stmt_close($tagStmt);
        }
    }
    return $inserted;
}

function fp_render_it_location_select(mysqli $conn, int $companyId, string $fieldName, string $fieldId, $selectedValue): string {
    $selectedId = (int)$selectedValue;
    if ($selectedId > 0 && $companyId > 0) {
        $selectedId = itm_fk_resolve_company_equivalent_id($conn, [
            'REFERENCED_TABLE_NAME' => 'it_locations',
            'REFERENCED_COLUMN_NAME' => 'id',
        ], $companyId, $selectedId);
    }
    $options = fp_fetch_it_location_options($conn, $companyId);
    $html = '<select name="' . sanitize($fieldName) . '" id="' . sanitize($fieldId) . '">';
    $html .= '<option value="">— No IT location link —</option>';
    $found = false;
    foreach ($options as $opt) {
        $id = (int)$opt['id'];
        $isSelected = ($selectedId === $id);
        if ($isSelected) {
            $found = true;
        }
        $html .= '<option value="' . $id . '"' . ($isSelected ? ' selected' : '') . '>' . sanitize((string)$opt['name']) . '</option>';
    }
    if ($selectedId > 0 && !$found) {
        $persistedLabel = fp_it_location_label_by_id($conn, $companyId, $selectedId);
        if ($persistedLabel !== '') {
            $html .= '<option value="' . $selectedId . '" selected>' . sanitize($persistedLabel) . '</option>';
        }
    }
    $html .= '</select>';
    return $html;
}

function fp_render_folder_tree_html(array $tree, int $selectedFolderId, bool $unfiledSelected, int $depth = 0): string {
    $html = '';
    foreach ($tree as $node) {
        $id = (int)$node['id'];
        $isActive = ($selectedFolderId === $id && !$unfiledSelected) ? ' is-active' : '';
        $pad = 8 + ($depth * 14);
        $html .= '<li class="itm-folder-tree-item itm-folder-tree-folder itm-folder-drop-target' . $isActive . '" data-folder-id="' . $id . '" data-folder-drop-id="' . $id . '">';
        $html .= '<div class="itm-folder-tree-row" style="padding-left:' . (int)$pad . 'px;">';
        $html .= '<span class="itm-folder-drag-handle" draggable="true" data-folder-id="' . $id . '" title="Drag to move folder" aria-label="Drag to move folder">⠿</span>';
        $html .= '<a href="index.php?folder_id=' . $id . '" draggable="false">📁 ' . sanitize((string)$node['name']) . '</a>';
        $html .= '<span class="itm-drop-hint">(drop)</span>';
        $html .= '</div>';
        if (!empty($node['children'])) {
            $html .= '<ul class="itm-folder-tree-children">' . fp_render_folder_tree_html($node['children'], $selectedFolderId, $unfiledSelected, $depth + 1) . '</ul>';
        }
        $html .= '</li>';
    }
    return $html;
}

function fp_format_file_size(int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

function fp_is_pdf_mime(string $mime): bool {
    return strtolower($mime) === 'application/pdf';
}
