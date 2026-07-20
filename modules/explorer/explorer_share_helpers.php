<?php
/**
 * Temporary QR / code share sessions for Explorer folders and files.
 */

require_once ROOT_PATH . 'includes/itm_explorer_paths.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/explorer_vault_helpers.php';

if (!function_exists('get_full_path')) {
    define('ITM_VERIFY_SKIP_ROUTER', true);
    require_once __DIR__ . '/api.php';
}

function explorer_share_table_name()
{
    return 'explorer_share_sessions';
}

function explorer_share_join_script_path()
{
    return 'modules/explorer/join.php';
}

function explorer_share_max_files()
{
    return 200;
}

function explorer_share_normalize_scope_path($scopePath)
{
    return explorer_normalize_relative_path((string)$scopePath);
}

function explorer_share_scope_path_hash($scopePath)
{
    return hash('sha256', (string)$scopePath);
}

/**
 * @return bool
 */
function explorer_share_is_allowed_scope($scopePath, $storageRoot, $userId, $deptCode, $username)
{
    $scopePath = explorer_share_normalize_scope_path($scopePath);
    if ($scopePath === null || $scopePath === '') {
        return false;
    }
    if ($scopePath === 'Trash' || strpos($scopePath, 'Trash/') === 0) {
        return false;
    }
    if ($scopePath === 'Private' || $scopePath === 'Departments') {
        return false;
    }

    return get_full_path($storageRoot, $scopePath, $userId, $deptCode, $username) !== null;
}

function explorer_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(explorer_share_join_script_path(), $accessToken);
}

function explorer_share_file_download_url($accessToken, $fileId)
{
    $accessToken = trim((string)$accessToken);
    $fileId = trim((string)$fileId);
    if ($accessToken === '' || $fileId === '') {
        return '';
    }

    return rtrim((string)BASE_URL, '/')
        . '/modules/explorer/share_file.php?t='
        . rawurlencode($accessToken)
        . '&file='
        . rawurlencode($fileId);
}

/**
 * @return array<int,array{id:string,relative_path:string,name:string,size:int,mime:string}>
 */
function explorer_share_collect_files($storageRoot, $scopePath, $userId, $deptCode, $username)
{
    $scopePath = explorer_share_normalize_scope_path($scopePath);
    if ($scopePath === null || $scopePath === '') {
        return [];
    }

    $fullPath = get_full_path($storageRoot, $scopePath, $userId, $deptCode, $username);
    if ($fullPath === null || !file_exists($fullPath)) {
        return [];
    }

    $files = [];
    $maxFiles = explorer_share_max_files();
    $storageRoot = rtrim(str_replace('\\', '/', $storageRoot), '/');

    if (is_file($fullPath)) {
        $name = basename($fullPath);
        if (function_exists('explorer_is_hidden_system_entry') && explorer_is_hidden_system_entry($name)) {
            return [];
        }
        $files[] = explorer_share_build_file_entry($scopePath, $name, $fullPath, 'f0');

        return $files;
    }

    if (!is_dir($fullPath)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS)
    );
    $index = 0;
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $name = $fileInfo->getFilename();
        if (function_exists('explorer_is_hidden_system_entry') && explorer_is_hidden_system_entry($name)) {
            continue;
        }
        $absolute = str_replace('\\', '/', $fileInfo->getPathname());
        if (strpos($absolute, $storageRoot . '/') !== 0) {
            continue;
        }
        $relativePath = substr($absolute, strlen($storageRoot) + 1);
        $fileId = 'f' . $index;
        $files[] = explorer_share_build_file_entry($relativePath, $name, $absolute, $fileId);
        $index++;
        if (count($files) >= $maxFiles) {
            break;
        }
    }

    usort($files, static function ($a, $b) {
        return strcmp((string)$a['relative_path'], (string)$b['relative_path']);
    });

    return $files;
}

/**
 * @return array{id:string,relative_path:string,name:string,size:int,mime:string}
 */
function explorer_share_build_file_entry($relativePath, $name, $absolutePath, $fileId)
{
    $size = is_readable($absolutePath) ? (int)filesize($absolutePath) : 0;
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open') && is_readable($absolutePath)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $absolutePath);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    }

    return [
        'id' => (string)$fileId,
        'relative_path' => (string)$relativePath,
        'name' => (string)$name,
        'size' => $size,
        'mime' => $mime,
    ];
}

/**
 * @param array<int,array<string,mixed>> $files
 * @return array<string,mixed>
 */
function explorer_share_build_payload($scopePath, $files, $ownerUsername)
{
    $heading = (string)$scopePath;
    if ($heading === '') {
        $heading = 'Explorer files';
    }

    return [
        'type' => 'explorer',
        'heading' => $heading,
        'owner_username' => (string)$ownerUsername,
        'scope_path' => (string)$scopePath,
        'file_count' => count($files),
        'files' => $files,
    ];
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function explorer_share_create_session($conn, $companyId, $employeeId, $ownerUsername, $scopePath, $deptCode, $username, $vaultUnlocked, $storageRoot, $userPrivateDir)
{
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $scopePath = explorer_share_normalize_scope_path($scopePath);
    if ($companyId <= 0 || $employeeId <= 0 || $scopePath === null || $scopePath === '' || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }
    if (!explorer_share_is_allowed_scope($scopePath, $storageRoot, $employeeId, $deptCode, $username)) {
        return ['ok' => false, 'error' => 'This location cannot be shared.'];
    }
    if (explorer_path_requires_vault_unlock($scopePath, $userPrivateDir) && !$vaultUnlocked) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing private files.'];
    }

    $files = explorer_share_collect_files($storageRoot, $scopePath, $employeeId, $deptCode, $username);
    if ($files === []) {
        return ['ok' => false, 'error' => 'No shareable files found at this location.'];
    }

    $payload = explorer_share_build_payload($scopePath, $files, $ownerUsername);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    $tableName = explorer_share_table_name();
    itm_qr_share_assert_table($tableName);
    $shareCode = itm_qr_share_generate_code($conn, $tableName);
    $accessToken = itm_qr_share_generate_access_token();
    if ($shareCode === '' || $accessToken === '') {
        return ['ok' => false, 'error' => 'Could not generate share code.'];
    }

    itm_qr_share_purge_expired_sessions($conn, $tableName);
    $scopeHash = explorer_share_scope_path_hash($scopePath);

    $stmtDeactivate = $conn->prepare(
        'UPDATE `' . $tableName . '` SET active = 0, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE company_id = ? AND employee_id = ? AND scope_path_hash = ? AND active = 1 AND deleted_at IS NULL'
    );
    if ($stmtDeactivate) {
        $stmtDeactivate->bind_param('iiiis', $employeeId, $employeeId, $companyId, $employeeId, $scopeHash);
        $stmtDeactivate->execute();
        $stmtDeactivate->close();
    }

    $ttl = itm_qr_share_session_ttl_seconds();
    $insertSql = 'INSERT INTO `' . $tableName . '` (company_id, employee_id, scope_path, scope_path_hash, share_code, access_token, payload_json, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)';
    $stmtInsert = $conn->prepare($insertSql);
    if (!$stmtInsert) {
        return ['ok' => false, 'error' => 'Could not create share session.'];
    }
    $stmtInsert->bind_param('iisssssii', $companyId, $employeeId, $scopePath, $scopeHash, $shareCode, $accessToken, $payloadJson, $ttl, $employeeId);
    if (!$stmtInsert->execute()) {
        $stmtInsert->close();

        return ['ok' => false, 'error' => 'Could not save share session.'];
    }
    $sessionId = (int)$stmtInsert->insert_id;
    $stmtInsert->close();

    $session = itm_qr_share_fetch_session_by_token($conn, $tableName, $accessToken);
    if (!$session) {
        return ['ok' => false, 'error' => 'Share session not found after create.'];
    }

    return ['ok' => true, 'session' => $session];
}

/**
 * @param array<string,mixed>|null $session
 * @return array{ok:bool,error?:string,file?:array<string,mixed>,payload?:array<string,mixed>}
 */
function explorer_share_validate_file_request($conn, $accessToken, $fileId, &$session = null)
{
    $accessToken = trim((string)$accessToken);
    $fileId = trim((string)$fileId);
    if ($accessToken === '' || $fileId === '' || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $session = itm_qr_share_fetch_session_by_token($conn, explorer_share_table_name(), $accessToken);
    if (!$session) {
        return ['ok' => false, 'error' => 'Session expired.'];
    }

    $payload = itm_qr_share_decode_payload($session['payload_json'] ?? '');
    if ($payload === null || ($payload['type'] ?? '') !== 'explorer') {
        return ['ok' => false, 'error' => 'Invalid payload.'];
    }

    $match = null;
    foreach ((array)($payload['files'] ?? []) as $fileRow) {
        if (!is_array($fileRow)) {
            continue;
        }
        if ((string)($fileRow['id'] ?? '') === $fileId) {
            $match = $fileRow;
            break;
        }
    }
    if ($match === null) {
        return ['ok' => false, 'error' => 'File not in share snapshot.'];
    }

    return ['ok' => true, 'file' => $match, 'payload' => $payload];
}

/**
 * @param array<string,mixed> $session
 * @param array<string,mixed> $fileRow
 */
function explorer_share_resolve_file_path($conn, $storageRoot, $session, $fileRow, $username)
{
    $companyId = (int)($session['company_id'] ?? 0);
    $employeeId = (int)($session['employee_id'] ?? 0);
    $relativePath = (string)($fileRow['relative_path'] ?? '');
    if ($companyId <= 0 || $employeeId <= 0 || $relativePath === '' || !($conn instanceof mysqli)) {
        return null;
    }

    $deptCode = '';
    $stmt = $conn->prepare(
        'SELECT d.code FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = ? AND e.company_id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('ii', $employeeId, $companyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $deptCode = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)($row['code'] ?? ''));
        }
    }

    $storageRoot = rtrim(str_replace('\\', '/', $storageRoot), '/');
    $fullPath = get_full_path($storageRoot, $relativePath, $employeeId, $deptCode, $username);

    return ($fullPath !== null && is_readable($fullPath) && is_file($fullPath)) ? $fullPath : null;
}
