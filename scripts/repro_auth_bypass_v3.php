<?php
/**
 * Reproduction script for Authorization Bypass vulnerabilities.
 */
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

function run_isolated($script_path, $session_data = [], $post_data = [], $get_data = [], $extra_globals = []) {
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) session_start();
" . implode("\n", array_map(function($k, $v) { return "\$_SESSION['$k'] = " . var_export($v, true) . ";"; }, array_keys($session_data), $session_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_POST['$k'] = " . var_export($v, true) . ";"; }, array_keys($post_data), $post_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_GET['$k'] = " . var_export($v, true) . ";"; }, array_keys($get_data), $get_data)) . "
" . implode("\n", array_map(function($k, $v) { return "global \$$k; \$$k = " . var_export($v, true) . ";"; }, array_keys($extra_globals), $extra_globals)) . "
chdir(dirname('$script_path'));
// Mock server variables that modules might use
\$_SERVER['PHP_SELF'] = '/modules/' . basename(dirname('$script_path')) . '/' . basename('$script_path');
\$_SERVER['REQUEST_METHOD'] = !empty(\$_POST) ? 'POST' : 'GET';

ob_start();
try {
    include basename('$script_path');
} catch (Throwable \$t) {
    echo \"Error: \" . \$t->getMessage();
}
echo ob_get_clean();
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec("$php_bin -d error_reporting=0 $tmp_file 2>/dev/null");
    unlink($tmp_file);
    return $output;
}

echo "Starting Authorization Bypass reproduction...\n";

// 1. Test Company Module Access for non-admin
echo "1. Testing Companies Module Access for non-admin user...\n";
$session = [
    'user_id' => 2, // Assuming user 2 is not an admin
    'username' => 'regular_user',
    'company_id' => 1
];
$output = run_isolated(__DIR__ . '/../modules/companies/index.php', $session);
if (strpos($output, 'Companies Management') !== false) {
    echo "[FAIL] Companies Module: Non-admin user can access management page.\n";
} else {
    echo "[PASS] Companies Module: Access restricted.\n";
}

// 2. Test User Module Access for non-admin
echo "2. Testing Users Module Access for non-admin user...\n";
$output = run_isolated(__DIR__ . '/../modules/users/index.php', $session);
if (strpos($output, 'Users Management') !== false) {
    echo "[FAIL] Users Module: Non-admin user can access management page.\n";
} else {
    echo "[PASS] Users Module: Access restricted.\n";
}

// 3. Test Company Deletion for non-admin (Attempt to call delete.php)
echo "3. Testing Company Deletion for non-admin user...\n";
// Create a temporary company to test deletion
global $conn;
mysqli_query($conn, "SET @app_company_id = 1");
mysqli_query($conn, "INSERT INTO companies (company, incode, active) VALUES ('Repro Temp', 'REPRO', 1)");
$temp_company_id = mysqli_insert_id($conn);

$post = [
    'csrf_token' => itm_get_csrf_token(),
    'id' => $temp_company_id,
    'bulk_action' => 'single_delete'
];
$session['csrf_token'] = $post['csrf_token'];

run_isolated(__DIR__ . '/../modules/companies/delete.php', $session, $post);

$res = mysqli_query($conn, "SELECT 1 FROM companies WHERE id = $temp_company_id");
if (mysqli_num_rows($res) == 0) {
    echo "[FAIL] Company Deletion: Non-admin user successfully deleted a company.\n";
} else {
    echo "[PASS] Company Deletion: Unauthorized deletion blocked.\n";
    mysqli_query($conn, "DELETE FROM companies WHERE id = $temp_company_id");
}

echo "Reproduction complete.\n";
