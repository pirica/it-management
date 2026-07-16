<?php
/**
 * Reproduction script for Authorization Bypass vulnerabilities.
 *
 * Why: Verifies non-admin users cannot reach companies/users management or delete tenants.
 * CSRF in isolated subprocesses is mocked via itm_validate_csrf_token(); real module handlers enforce CSRF.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Authorization Bypass Reproduction');

$nl = itm_script_output_nl();

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo colorText('[FAIL] Database connection is not available.', 'fail') . $nl;
    exit(1);
}

function repro_auth_bypass_run_isolated($script_path, $session_data = [], $post_data = [], $get_data = [], $extra_globals = [])
{
    $script_path = realpath($script_path);
    if ($script_path === false) {
        return '';
    }

    $configPath = realpath(__DIR__ . '/../config/config.php');
    $session_init = '';
    foreach ($session_data as $k => $v) {
        $session_init .= "\$_SESSION['" . addslashes((string) $k) . "'] = " . var_export($v, true) . ";\n";
    }
    $post_init = '';
    foreach ($post_data as $k => $v) {
        $post_init .= "\$_POST['" . addslashes((string) $k) . "'] = " . var_export($v, true) . ";\n";
    }
    $get_init = '';
    foreach ($get_data as $k => $v) {
        $get_init .= "\$_GET['" . addslashes((string) $k) . "'] = " . var_export($v, true) . ";\n";
    }
    $globals_init = '';
    foreach ($extra_globals as $k => $v) {
        $globals_init .= "global \$" . $k . "; \$" . $k . " = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function itm_validate_csrf_token(\$token) { return true; }
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
require '" . addslashes($configPath) . "';
" . $session_init . $post_init . $get_init . $globals_init . "
chdir(dirname('" . addslashes($script_path) . "'));
\$_SERVER['PHP_SELF'] = '/modules/' . basename(dirname('" . addslashes($script_path) . "')) . '/' . basename('" . addslashes($script_path) . "');
\$_SERVER['REQUEST_METHOD'] = !empty(\$_POST) ? 'POST' : 'GET';
ob_start();
try {
    include basename('" . addslashes($script_path) . "');
} catch (Throwable \$t) {
    echo 'Error: ' . \$t->getMessage();
}
echo ob_get_clean();
?>";

    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_auth');
    if ($tmp_file === false) {
        return '';
    }
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' -d error_reporting=0 ' . escapeshellarg($tmp_file) . ' ' . itm_script_shell_stderr_discard());
    @unlink($tmp_file);
    return (string) $output;
}

function repro_auth_bypass_company_still_exists($conn, $companyId)
{
    $stmt = mysqli_prepare($conn, 'SELECT 1 FROM companies WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        return false;
    }
    $companyId = (int) $companyId;
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function repro_auth_bypass_delete_company($conn, $companyId)
{
    $stmt = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ?');
    if ($stmt === false) {
        return false;
    }
    $companyId = (int) $companyId;
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

echo colorText('Starting Authorization Bypass reproduction...', 'info') . $nl;

$session = [
    'employee_id' => 2,
    'username' => 'regular_user',
    'role_name' => 'User',
    'company_id' => 1,
];

echo '1. Testing Companies Module Access for non-admin user...' . $nl;
$output = repro_auth_bypass_run_isolated(__DIR__ . '/../modules/companies/index.php', $session);
if (strpos($output, 'Companies Management') !== false) {
    echo colorText('[FAIL] Companies Module: Non-admin user can access management page.', 'fail') . $nl;
} else {
    echo colorText('[PASS] Companies Module: Access restricted.', 'pass') . $nl;
}

echo '2. Testing Employees module access for non-admin user...' . $nl;
$output = repro_auth_bypass_run_isolated(__DIR__ . '/../modules/employees/index.php', $session);
if (strpos($output, 'Employees Management') !== false) {
    echo colorText('[FAIL] Employees module: Non-admin user can access management page.', 'fail') . $nl;
} else {
    echo colorText('[PASS] Employees module: Access restricted.', 'pass') . $nl;
}

echo '3. Testing Company Deletion for non-admin user...' . $nl;
mysqli_query($conn, 'SET @app_company_id = 1');
$insertOk = mysqli_query($conn, "INSERT INTO companies (company, incode, active) VALUES ('Repro Temp', 'REPRO', 1)");
if (!$insertOk) {
    echo colorText('[FAIL] Could not seed temporary company for deletion test.', 'fail') . $nl;
    exit(1);
}
$temp_company_id = (int) mysqli_insert_id($conn);

$post = [
    'csrf_token' => itm_get_csrf_token(),
    'id' => $temp_company_id,
    'bulk_action' => 'single_delete',
];
$session['csrf_token'] = $post['csrf_token'];

repro_auth_bypass_run_isolated(__DIR__ . '/../modules/companies/delete.php', $session, $post);

if (!repro_auth_bypass_company_still_exists($conn, $temp_company_id)) {
    echo colorText('[FAIL] Company Deletion: Non-admin user successfully deleted a company.', 'fail') . $nl;
} else {
    echo colorText('[PASS] Company Deletion: Unauthorized deletion blocked.', 'pass') . $nl;
    repro_auth_bypass_delete_company($conn, $temp_company_id);
}

echo colorText('Reproduction complete.', 'info') . $nl;

itm_script_output_end();
