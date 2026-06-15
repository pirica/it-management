<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

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

$username = 'testuser_' . uniqid();
$email = $username . '@example.com';
mysqli_query($conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (1, '$username', '$email', 'pass', 2, 2, 1)");
$userId = mysqli_insert_id($conn);

$session = [
    'user_id' => $userId,
    'username' => $username,
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

$res = mysqli_query($conn, "SELECT id, role_id FROM users WHERE username = '$evilUsername'");
$row = mysqli_fetch_assoc($res);

if ($row && $row['role_id'] == 1) {
    echo "[FAIL] Select Options API: Regular user successfully created an Admin user!" . $nl;
    mysqli_query($conn, "DELETE FROM users WHERE id = " . $row['id']);
} else {
    echo "[PASS] Select Options API: Admin creation failed. Output: $output" . $nl;
}

mysqli_query($conn, "DELETE FROM users WHERE id = $userId");
