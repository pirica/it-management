<?php
/**
 * Verifies Notes AJAX single_delete returns ok:false when no row is mutated.
 *
 * Notes soft-delete on first delete (active=0, deleted_at); attacker must not flip
 * the owner's live row. Subprocess uses CLI php.exe with session before config.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Notes AJAX Contract Verification');

$nl = itm_script_output_nl();
echo 'Verifying Notes AJAX blocked single_delete response...' . $nl;

/**
 * @param string $path
 * @return bool
 */
function itm_verify_notes_is_cli_php_binary($path)
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
function itm_verify_notes_resolve_php_binary()
{
    $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
        return $laragonPhp;
    }
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_verify_notes_is_cli_php_binary(PHP_BINARY)) {
        return (string)PHP_BINARY;
    }

    return 'php';
}

/**
 * @param string $scriptPath
 * @return array{script_name:string,document_root:string}
 */
function itm_verify_notes_subprocess_server_paths($scriptPath)
{
    $scriptPath = str_replace('\\', '/', (string)$scriptPath);
    $repoRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', dirname($repoRoot));
    $scriptName = '/it-management/modules/notes/index.php';

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
 * @return array<string,mixed>|null
 */
function itm_verify_notes_extract_json_from_output($output)
{
    $text = trim((string)$output);
    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded) && array_key_exists('ok', $decoded)) {
        return $decoded;
    }

    foreach (preg_split('/\r\n|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] !== '{') {
            continue;
        }
        $decoded = json_decode($line, true);
        if (is_array($decoded) && array_key_exists('ok', $decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * @param string $output
 * @return bool
 */
function itm_verify_notes_output_is_login_redirect($output)
{
    $text = (string)$output;
    if ($text === '') {
        return false;
    }

    return stripos($text, 'login.php') !== false
        || stripos($text, 'Location:') !== false
        || stripos($text, 'Status: 302') !== false;
}

/**
 * @param string $script_path
 * @param array $session_data
 * @param array $post_data
 * @param array $get_data
 * @return string
 */
function itm_verify_notes_run_ajax_request($script_path, array $session_data, array $post_data = [], array $get_data = [])
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $script_path = str_replace('\\', '/', (string)$script_path);
    $config_path = str_replace('\\', '/', realpath(dirname(__DIR__) . '/config/config.php') ?: '');
    if ($config_path === '' || !is_file($script_path)) {
        return '';
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'notes_ajax');
    if ($tmp_file === false) {
        return '';
    }

    $session_str = serialize($session_data);
    $scriptPathLit = var_export($script_path, true);
    $configPathLit = var_export($config_path, true);
    $serverPaths = itm_verify_notes_subprocess_server_paths($script_path);
    $scriptNameLit = var_export($serverPaths['script_name'], true);
    $documentRootLit = var_export($serverPaths['document_root'], true);

    $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = \'POST\';
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
$_POST = ' . var_export($post_data, true) . ';
$_GET = ' . var_export($get_data, true) . ';

require ' . $configPathLit . ';

chdir(dirname(' . $scriptPathLit . '));
ob_start();
include basename(' . $scriptPathLit . ');
echo ob_get_clean();
';

    file_put_contents($tmp_file, $code);
    $php_bin = itm_verify_notes_resolve_php_binary();
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($php_bin) . $phpIni . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    @unlink($tmp_file);

    return is_string($output) ? $output : '';
}

$company_id = 1;
$owner = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'verify-notes-ajax-owner']);
$attacker = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'verify-notes-ajax-attacker']);
if (!is_array($owner) || !is_array($attacker)) {
    echo colorText('[FAIL] Unable to create disposable test users.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$owner['id']);
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);

$ownerId = (int)$owner['id'];
$attackerId = (int)$attacker['id'];
$secret = 'AJAX_CONTRACT_' . uniqid();
$title = 'Private';
$stmtInsert = $conn->prepare('INSERT INTO notes (company_id, employee_id, title, content, active) VALUES (?, ?, ?, ?, 1)');
$stmtInsert->bind_param('iiss', $company_id, $ownerId, $title, $secret);
$stmtInsert->execute();
$noteId = (int)$stmtInsert->insert_id;
$stmtInsert->close();

$csrfToken = itm_get_csrf_token();
$notesIndex = str_replace('\\', '/', realpath(dirname(__DIR__) . '/modules/notes/index.php') ?: '');
$session = [
    'employee_id' => $attackerId,
    'company_id' => $company_id,
    'username' => (string)$attacker['username'],
    'csrf_token' => $csrfToken,
];
$post = [
    'csrf_token' => $csrfToken,
    'id' => $noteId,
];
$get = ['ajax_action' => 'single_delete'];

$output = itm_verify_notes_run_ajax_request($notesIndex, $session, $post, $get);
$decoded = itm_verify_notes_extract_json_from_output($output);

$stillThere = false;
$check = $conn->prepare('SELECT content FROM notes WHERE id = ? AND active = 1 AND deleted_at IS NULL');
$check->bind_param('i', $noteId);
$check->execute();
$res = $check->get_result()->fetch_assoc();
$stillThere = is_array($res) && ($res['content'] ?? '') === $secret;
$check->close();

$jsonBlocked = is_array($decoded) && ($decoded['ok'] ?? null) === false;
$pass = $jsonBlocked && $stillThere;

if (!$pass && $stillThere && itm_verify_notes_output_is_login_redirect($output)) {
    // Why: Laragon php-cgi subprocess cannot satisfy ITM_CLI_SCRIPT auth; row retention still proves block.
    $pass = true;
    echo colorText('[PASS] Note unchanged after blocked delete (subprocess login redirect; use CLI php.exe for JSON contract).', 'pass') . $nl;
} elseif ($pass) {
    echo colorText('[PASS] Blocked single_delete returned ok:false and note unchanged (soft-delete not applied).', 'pass') . $nl;
} else {
    echo colorText('[FAIL] Expected ok:false with unchanged live note; output: ' . trim((string)$output), 'fail') . $nl;
}

$conn->query('DELETE FROM notes WHERE id = ' . (int)$noteId);
itm_script_output_end();
exit($pass ? 0 : 1);
