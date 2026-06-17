<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_user.php';

itm_script_output_begin('Select Options Escalation Verification');

function run_isolated_post($script_path, $session_data = [], $post_data = []) {
    $session_init = "";
    foreach($session_data as $k => $v) {
        $session_init .= "\$_SESSION['$k'] = " . var_export($v, true) . ";\n";
    }
    $post_init = "";
    foreach($post_data as $k => $v) {
        $post_init .= "\$_POST['$k'] = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
function itm_validate_csrf_token(\$token) { return true; }

require '" . realpath(__DIR__ . "/../config/config.php") . "';

$session_init
$post_init
\$company_id = \$_SESSION['company_id'];

chdir(dirname('$script_path'));
require basename('$script_path');
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec("$php_bin $tmp_file 2>&1");
    unlink($tmp_file);
    return $output;
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying Select Options API Escalation..." . $nl;

$testUser = itm_script_test_user_create($conn, 1, ['script_slug' => 'verify-select-options']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
$userId = (int)$testUser['id'];
itm_script_test_user_register_teardown($conn, $userId);

$session = [
    'user_id' => $userId,
    'username' => (string)$testUser['username'],
    'company_id' => 1,
    'csrf_token' => 'test_token'
];

$evilUsername = 'eviladmin_' . uniqid();
$post = [
    'csrf_token' => 'test_token',
    'table' => 'users',
    'id_col' => 'id',
    'label_col' => 'username',
    'new_value' => $evilUsername,
    'company_scoped' => '1',
    'extra_fields' => json_encode([
        'email' => $evilUsername . '@evil.com',
        'password' => 'evil',
        'role_id' => 1,
        'access_level_id' => 1
    ])
];

$output = run_isolated_post(realpath(__DIR__ . '/../modules/select_options_api.php'), $session, $post);

$decoded = json_decode(trim((string)$output), true);
$blockedByPolicy = is_array($decoded)
    && empty($decoded['ok'])
    && stripos((string)($decoded['error'] ?? ''), 'quick-add') !== false;

$res = mysqli_query($conn, "SELECT id, role_id FROM users WHERE username = '$evilUsername'");
$row = mysqli_fetch_assoc($res);

if ($row && (int)$row['role_id'] === 1) {
    echo colorText("[FAIL] Select Options API: Regular user successfully created an Admin user!", 'fail') . $nl;
    mysqli_query($conn, "DELETE FROM users WHERE id = " . (int)$row['id']);
} elseif ($blockedByPolicy) {
    echo colorText('[PASS] Select Options API: Admin creation blocked by table whitelist.', 'pass') . $nl;
} else {
    echo colorText("[FAIL] Select Options API: Expected whitelist block; output: $output", 'fail') . $nl;
}

itm_script_output_end();
