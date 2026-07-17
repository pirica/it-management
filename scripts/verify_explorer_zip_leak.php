<?php
/**
 * Verifies Explorer downloadZip root blocking and scoped Private backup.
 *
 * Blocked (no ZIP stream): every path except the exact own Private/{username}_{employee_id}.
 * Allowed: Private/{username}_{employee_id} only (recursive ZIP of that folder tree).
 *
 * CLI: php scripts/verify_explorer_zip_leak.php
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Explorer ZIP Leak Verification');

$nl = itm_script_output_nl();

/**
 * @param string $path
 * @return bool
 */
function vezl_is_cli_php_binary($path)
{
    $normalized = strtolower(str_replace('\\', '/', (string)$path));
    if ($normalized === '' || !is_file($path)) {
        return false;
    }
    if (strpos($normalized, 'php-cgi') !== false) {
        return false;
    }
    if (substr($normalized, -4) === '.dll') {
        return false;
    }

    return true;
}

/**
 * @return string
 */
function vezl_resolve_php_binary()
{
    $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
        return $laragonPhp;
    }
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && vezl_is_cli_php_binary(PHP_BINARY)) {
        return (string)PHP_BINARY;
    }

    return 'php';
}

/**
 * @param string $scriptPath
 * @return array{script_name:string,document_root:string}
 */
function vezl_subprocess_server_paths($scriptPath)
{
    $scriptPath = str_replace('\\', '/', (string)$scriptPath);
    $repoRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', dirname($repoRoot));
    $scriptName = '/it-management/modules/explorer/api.php';

    if (strpos($scriptPath, $repoRoot) === 0) {
        $scriptName = '/it-management/' . ltrim(substr($scriptPath, strlen($repoRoot)), '/');
    }

    return [
        'script_name' => $scriptName,
        'document_root' => $documentRoot,
    ];
}

/**
 * @param string $output
 * @return string
 */
function vezl_strip_cli_headers($output)
{
    $parts = preg_split("/\r\n\r\n|\n\n/", (string)$output, 2);

    return is_array($parts) && isset($parts[1]) ? (string)$parts[1] : (string)$output;
}

/**
 * @param string $output
 * @return bool
 */
function vezl_output_streams_zip($output)
{
    $text = (string)$output;
    $body = vezl_strip_cli_headers($text);

    if ($body !== '' && strncmp($body, "PK\x03\x04", 4) === 0) {
        return true;
    }

    if (stripos($text, 'Content-Type: application/zip') !== false
        && stripos($text, 'Invalid path or permission denied') === false
        && stripos($text, 'Access denied') === false) {
        return strncmp(vezl_strip_cli_headers($text), 'PK', 2) === 0;
    }

    return false;
}

/**
 * @param string $output
 * @return bool
 */
function vezl_output_is_login_redirect($output)
{
    $text = (string)$output;

    return stripos($text, 'login.php') !== false
        || stripos($text, 'Location:') !== false
        || stripos($text, 'Status: 302') !== false;
}

/**
 * @param string $script_path
 * @param array $session_data
 * @param array $get_data
 * @return string
 */
function vezl_run_download_zip_request($script_path, array $session_data, array $get_data = [])
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $script_path = str_replace('\\', '/', (string)$script_path);
    $config_path = str_replace('\\', '/', realpath(__DIR__ . '/../config/config.php') ?: '');
    if ($config_path === '' || !is_file($script_path)) {
        return '';
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'explorer_zip');
    if ($tmp_file === false) {
        return '';
    }

    $session_str = serialize($session_data);
    $scriptPathLit = var_export($script_path, true);
    $configPathLit = var_export($config_path, true);
    $serverPaths = vezl_subprocess_server_paths($script_path);
    $scriptNameLit = var_export($serverPaths['script_name'], true);
    $documentRootLit = var_export($serverPaths['document_root'], true);

    $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = \'GET\';
$_SERVER[\'REMOTE_ADDR\'] = \'127.0.0.1\';
$_SERVER[\'HTTP_HOST\'] = \'localhost\';
$_SERVER[\'SCRIPT_NAME\'] = ' . $scriptNameLit . ';
$_SERVER[\'PHP_SELF\'] = ' . $scriptNameLit . ';
$_SERVER[\'SCRIPT_FILENAME\'] = ' . $scriptPathLit . ';
if (' . $documentRootLit . ' !== \'\') {
    $_SERVER[\'DOCUMENT_ROOT\'] = ' . $documentRootLit . ';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = unserialize(' . var_export($session_str, true) . ');
$_GET = ' . var_export($get_data, true) . ';

require ' . $configPathLit . ';

chdir(dirname(' . $scriptPathLit . '));
ob_start();
include basename(' . $scriptPathLit . ');
echo ob_get_clean();
';

    file_put_contents($tmp_file, $code);
    $php_bin = vezl_resolve_php_binary();
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($php_bin) . $phpIni . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    @unlink($tmp_file);

    return is_string($output) ? $output : '';
}

/**
 * @param string $apiPath
 * @param array $session
 * @param string $path
 * @param string $label
 * @param bool $failed
 * @param string $nl
 * @return bool updated $failed
 */
function vezl_assert_download_zip_blocked($apiPath, array $session, $path, $label, $failed, $nl)
{
    $get = ['downloadZip' => '1'];
    if ($path !== '') {
        $get['path'] = $path;
    }

    $output = vezl_run_download_zip_request($apiPath, $session, $get);
    $blockedMessage = stripos((string)$output, 'Invalid path or permission denied') !== false;
    $zipStreamed = vezl_output_streams_zip($output);

    if ($zipStreamed) {
        echo colorText('[FAIL] ' . $label . ' — ZIP stream detected for path=' . ($path === '' ? '(empty)' : $path) . '.', 'fail') . $nl;
        return true;
    }

    if ($blockedMessage) {
        echo colorText('[PASS] ' . $label . ' — blocked.', 'pass') . $nl;
        return $failed;
    }

    if (vezl_output_is_login_redirect($output)) {
        echo colorText('[FAIL] ' . $label . ' — subprocess login redirect (use CLI php.exe).', 'fail') . $nl;
        return true;
    }

    if (stripos((string)$output, 'Access denied') !== false) {
        echo colorText('[FAIL] ' . $label . ' — subprocess session missing.', 'fail') . $nl;
        return true;
    }

    $snippet = trim(vezl_strip_cli_headers($output));
    if (strlen($snippet) > 120) {
        $snippet = substr($snippet, 0, 120) . '…';
    }
    echo colorText('[FAIL] ' . $label . ' — unexpected response: ' . $snippet, 'fail') . $nl;
    return true;
}

/**
 * @param string $apiPath
 * @param array $session
 * @param string $path
 * @param string $label
 * @param bool $failed
 * @param string $nl
 * @return bool updated $failed
 */
function vezl_assert_download_zip_allowed($apiPath, array $session, $path, $label, $failed, $nl)
{
    $output = vezl_run_download_zip_request($apiPath, $session, [
        'downloadZip' => '1',
        'path' => $path,
    ]);

    if (vezl_output_streams_zip($output)) {
        echo colorText('[PASS] ' . $label . ' — ZIP backup permitted.', 'pass') . $nl;
        return $failed;
    }

    if (vezl_output_is_login_redirect($output)) {
        echo colorText('[FAIL] ' . $label . ' — subprocess login redirect.', 'fail') . $nl;
        return true;
    }

    if (stripos((string)$output, 'Invalid path or permission denied') !== false) {
        echo colorText('[FAIL] ' . $label . ' — blocked; expected ZIP stream for path=' . $path . '.', 'fail') . $nl;
        return true;
    }

    $snippet = trim(vezl_strip_cli_headers($output));
    if (strlen($snippet) > 120) {
        $snippet = substr($snippet, 0, 120) . '…';
    }
    echo colorText('[FAIL] ' . $label . ' — expected ZIP stream; got: ' . $snippet, 'fail') . $nl;
    return true;
}

echo 'Verifying Explorer downloadZip contracts...' . $nl;
echo $nl;
echo 'Step 1 — blocked roots (must not stream application/zip):' . $nl;
echo '  - Home (empty path)' . $nl;
echo '  - Common root' . $nl;
echo '  - Private root (unscoped — leaks all users)' . $nl;
echo '  - Departments root' . $nl;
echo '  - Trash root' . $nl;
echo 'Step 2 — allowed backup (must stream application/zip):' . $nl;
echo '  - Private/{username}_{employee_id} — exact own private folder only' . $nl;
echo 'Step 3 — blocked paths (must not stream application/zip):' . $nl;
echo '  - Private root, subfolders inside own private, Common, Departments, Trash, other users' . $nl;
echo $nl;

$companyId = 1;
$testUser = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-explorer-zip-leak']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

itm_script_test_employee_register_teardown($conn, (int)$testUser['id'], [], [
    'cleanup' => true,
    'company_id' => $companyId,
    'username' => (string)$testUser['username'],
]);

$storageRoot = ROOT_PATH . 'files/' . $companyId;
$userPrivate = (string)$testUser['username'] . '_' . (int)$testUser['id'];
$privateRelPath = 'Private/' . $userPrivate;
$privatePath = $storageRoot . '/Private/' . $userPrivate;
if (!is_dir($privatePath)) {
    if (function_exists('itm_ensure_files_storage_directory')) {
        itm_ensure_files_storage_directory($privatePath);
    } else {
        @mkdir($privatePath, 0777, true);
    }
}
$probeFile = $privatePath . '/backup_probe.txt';
file_put_contents($probeFile, 'explorer zip backup probe ' . uniqid('', true));
$ownSubRelPath = $privateRelPath . '/backup_sub';
$ownSubPath = $privatePath . '/backup_sub';
if (!is_dir($ownSubPath)) {
    if (function_exists('itm_ensure_files_storage_directory')) {
        itm_ensure_files_storage_directory($ownSubPath);
    } else {
        @mkdir($ownSubPath, 0777, true);
    }
}
$ownSubProbe = $ownSubPath . '/nested_probe.txt';
file_put_contents($ownSubProbe, 'explorer zip nested probe ' . uniqid('', true));

$otherPrivateDir = 'Admin_1';
$otherPrivateRel = 'Private/' . $otherPrivateDir;
$otherPrivatePath = $storageRoot . '/Private/' . $otherPrivateDir;
if (!is_dir($otherPrivatePath)) {
    if (function_exists('itm_ensure_files_storage_directory')) {
        itm_ensure_files_storage_directory($otherPrivatePath);
    } else {
        @mkdir($otherPrivatePath, 0777, true);
    }
}
$otherProbeFile = $otherPrivatePath . '/other_user_secret.txt';
file_put_contents($otherProbeFile, 'other user private probe ' . uniqid('', true));

$apiPath = str_replace('\\', '/', realpath(__DIR__ . '/../modules/explorer/api.php') ?: '');
$session = [
    'company_id' => $companyId,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
];

$blockedPaths = [
    '' => 'Home root',
    'Common' => 'Common root',
    'Private' => 'Private root',
    'Departments' => 'Departments root',
    'Trash' => 'Trash root',
];

$failed = false;

echo 'Step 1 — blocked roots' . $nl;
foreach ($blockedPaths as $path => $label) {
    $failed = vezl_assert_download_zip_blocked($apiPath, $session, $path, $label, $failed, $nl) || $failed;
}

echo $nl;
echo 'Step 2 — own Private folder only' . $nl;
$failed = vezl_assert_download_zip_allowed($apiPath, $session, $privateRelPath, 'Private/' . $userPrivate, $failed, $nl) || $failed;

echo $nl;
echo 'Step 3 — blocked paths' . $nl;
$blockedOtherPaths = [
    $ownSubRelPath => 'Own private subfolder (must use root folder only)',
    'Common' => 'Common root',
    'Common/shared' => 'Common subfolder',
    'Departments' => 'Departments root',
    'Departments/IT' => 'Department subfolder',
    $otherPrivateRel => 'Other user private folder (Admin_1)',
    $otherPrivateRel . '/other_user_secret.txt' => 'File path inside other user folder',
    $otherPrivateRel . '/nested' => 'Subfolder inside other user private folder',
    'Private\\' . $otherPrivateDir => 'Other user private folder (backslash path)',
    'Private/otheruser_999' => 'Synthetic other user private folder',
    'Private/otheruser_999/secret' => 'Synthetic nested other user private path',
];
foreach ($blockedOtherPaths as $path => $label) {
    $failed = vezl_assert_download_zip_blocked($apiPath, $session, $path, $label, $failed, $nl) || $failed;
}

@unlink($probeFile);
@unlink($ownSubProbe);
@unlink($otherProbeFile);

echo $nl;
if ($failed) {
    echo colorText('[FAIL] Explorer downloadZip contract checks failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] Explorer downloadZip allows only the exact own Private/{username}_{employee_id} folder.', 'pass') . $nl;
itm_script_output_end();
exit(0);
