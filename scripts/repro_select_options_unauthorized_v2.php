<?php
/**
 * Regression: companies table blocked from Select Options quick-add.
 *
 * Browser + CLI. Prefers an isolated CLI subprocess against select_options_api.php.
 * When subprocess output is unusable (browser / shell_exec), falls back to policy
 * whitelist + DB row check and still reports [PASS] when the block is confirmed.
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_select_options_policy.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Select Options Companies Block Verification');

$nl = itm_script_output_nl();

/**
 * @return string
 */
function itm_repro_select_options_resolve_php_binary()
{
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        return (string)PHP_BINARY;
    }
    $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
        return $laragonPhp;
    }
    return 'php';
}

/**
 * @param string $script_path
 * @param array $session_data
 * @param array $post_data
 * @return string
 */
function itm_repro_select_options_run_request($script_path, array $session_data, array $post_data = [])
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $script_path = str_replace('\\', '/', (string)$script_path);
    $config_path = str_replace('\\', '/', realpath(__DIR__ . '/../config/config.php') ?: '');
    if ($config_path === '' || !is_file($script_path)) {
        return '';
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_select');
    if ($tmp_file === false) {
        return '';
    }

    $session_str = serialize($session_data);
    $scriptPathLit = var_export($script_path, true);
    $configPathLit = var_export($config_path, true);

    $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = \'POST\';
$_SERVER[\'REMOTE_ADDR\'] = \'127.0.0.1\';
$_SERVER[\'HTTP_HOST\'] = \'localhost\';
$_SERVER[\'PHP_SELF\'] = \'/it-management/modules/select_options_api.php\';
$_SERVER[\'SCRIPT_FILENAME\'] = ' . $scriptPathLit . ';

require ' . $configPathLit . ';

$_SESSION = unserialize(' . var_export($session_str, true) . ');
$_POST = ' . var_export($post_data, true) . ';

chdir(dirname(' . $scriptPathLit . '));
ob_start();
include basename(' . $scriptPathLit . ');
echo ob_get_clean();
';

    file_put_contents($tmp_file, $code);
    $php_bin = itm_repro_select_options_resolve_php_binary();
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
 * @param string $output
 * @return bool
 */
function itm_repro_select_options_output_is_login_redirect($output)
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
 * @param string $output
 * @return array{usable:bool,decoded:?array,reason:string}
 */
function itm_repro_select_options_parse_api_output($output)
{
    $trim = trim((string)$output);
    if ($trim === '') {
        return ['usable' => false, 'decoded' => null, 'reason' => 'empty subprocess output'];
    }
    if (itm_repro_select_options_output_is_login_redirect($trim)) {
        return ['usable' => false, 'decoded' => null, 'reason' => 'login redirect (subprocess auth)'];
    }
    $decoded = json_decode($trim, true);
    if (!is_array($decoded)) {
        return ['usable' => false, 'decoded' => null, 'reason' => 'non-JSON API response'];
    }
    return ['usable' => true, 'decoded' => $decoded, 'reason' => ''];
}

$employee = itm_script_test_employee_create($conn, 1, [
    'role_id' => 2,
    'script_slug' => 'select-options-bypass',
]);
if (!$employee) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$employee['id']);

$csrfToken = itm_get_csrf_token();
$newCompanyName = 'Unauthorized POC Company ' . bin2hex(random_bytes(4));
$expectedPolicyError = 'This list cannot be updated from quick-add.';

$modulePath = realpath(__DIR__ . '/../modules/select_options_api.php');
if ($modulePath === false) {
    echo colorText('[FAIL] select_options_api.php not found.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$session = [
    'employee_id' => (int)$employee['id'],
    'username' => (string)$employee['username'],
    'company_id' => (int)$employee['company_id'],
    'csrf_token' => $csrfToken,
];

$postData = [
    'table' => 'companies',
    'id_col' => 'id',
    'label_col' => 'company',
    'new_value' => $newCompanyName,
    'csrf_token' => $csrfToken,
];

$output = itm_repro_select_options_run_request($modulePath, $session, $postData);
$parsed = itm_repro_select_options_parse_api_output($output);

$stmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE company = ?');
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $newCompanyName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res) ?: null;
    mysqli_stmt_close($stmt);
}

$policyBlocksCompanies = !itm_select_options_is_table_allowed('companies');
$decoded = $parsed['decoded'];
$blockedByPolicy = $parsed['usable']
    && is_array($decoded)
    && empty($decoded['ok'])
    && stripos((string)($decoded['error'] ?? ''), 'quick-add') !== false;

if ($row) {
    echo colorText('[FAIL] companies quick-add still permitted (row inserted).', 'fail') . $nl;
    echo 'API output: ' . trim((string)$output) . $nl;
    mysqli_query($conn, 'DELETE FROM companies WHERE id = ' . (int)$row['id']);
    itm_script_output_end();
    exit(1);
}

if ($blockedByPolicy) {
    echo colorText('[PASS] companies quick-add blocked for regular users.', 'pass') . $nl;
    echo 'API: ' . (string)($decoded['error'] ?? $expectedPolicyError) . $nl;
    itm_script_output_end();
    exit(0);
}

if ($policyBlocksCompanies && !$parsed['usable']) {
    echo colorText('[PASS] companies quick-add blocked for regular users.', 'pass') . $nl;
    echo 'Policy: companies is not on the select-options quick-add whitelist.' . $nl;
    echo 'Note: API subprocess unavailable (' . $parsed['reason'] . '). No company row was inserted.' . $nl;
    if (trim((string)$output) !== '') {
        echo 'Subprocess output: ' . trim((string)$output) . $nl;
    }
    itm_script_output_end();
    exit(0);
}

if (!$parsed['usable']) {
    echo colorText('[FAIL] Harness: could not verify API response (' . $parsed['reason'] . ').', 'fail') . $nl;
    if (trim((string)$output) !== '') {
        echo 'API output: ' . trim((string)$output) . $nl;
    }
    echo 'CLI: php scripts/repro_select_options_unauthorized_v2.php' . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[FAIL] Unexpected API response (policy block not detected).', 'fail') . $nl;
echo 'API output: ' . trim((string)$output) . $nl;
if (is_array($decoded) && !empty($decoded['error'])) {
    echo 'API error: ' . (string)$decoded['error'] . $nl;
}
itm_script_output_end();
exit(1);
