<?php

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_select_options_escalation.php</code> — subprocess must use CLI <code>php.exe</code> (not Apache <code>php-cgi</code>); set <code>PHP_EXE</code> in <code>.env</code> when running from the browser catalog.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_cli_binary.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();


require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Select Options Escalation Verification');

/**
 * JSON body expected when employees quick-add is blocked (HTTP 403 in a real browser request).
 */
function verify_select_options_expected_pass_json(): string
{
    return '{"ok":false,"error":"This list cannot be updated from quick-add."}';
}

function verify_select_options_is_login_redirect_output($output): bool
{
    $text = (string)$output;

    return stripos($text, 'Location:') !== false && stripos($text, 'login.php') !== false;
}

function verify_select_options_echo_text($text): void
{
    global $nl;
    if (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') {
        echo $text . $nl;
    } else {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . $nl;
    }
}

function run_isolated_post($script_path, $session_data = [], $post_data = []) {
    $session_init = "";
    foreach($session_data as $k => $v) {
        $session_init .= "\$_SESSION['$k'] = " . var_export($v, true) . ";\n";
    }
    $post_init = "";
    foreach($post_data as $k => $v) {
        $post_init .= "\$_POST['$k'] = " . var_export($v, true) . ";\n";
    }

    // WHY: Initialize session and post variables BEFORE requiring config.php.
    // If config.php runs first, the global authentication middleware will see an empty
    // $_SESSION['employee_id'] and redirect to login.php (HTTP 302 Found) when executed
    // under a web/HTTP runner environment where PHP_SAPI is not 'cli' or is simulated.
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
function itm_validate_csrf_token(\$token) { return true; }

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$session_init
$post_init

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$company_id = \$_SESSION['company_id'];

chdir(dirname('$script_path'));
require basename('$script_path');
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = itm_resolve_cli_php_binary();
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return ['output' => $output, 'php_bin' => $php_bin];
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying Select Options API Escalation..." . $nl;
echo "Expected PASS: JSON body matching whitelist block (no HTTP redirect to login.php):" . $nl;
verify_select_options_echo_text(verify_select_options_expected_pass_json());
echo $nl;

$testUser = itm_script_test_employee_create($conn, 1, ['script_slug' => 'verify-select-options']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    if ($conn) {
        echo "Database error details: " . mysqli_error($conn) . $nl;
    } else {
        echo "Database connection is not established." . $nl;
    }
    itm_script_output_end();
    exit(1);
}
$employeeId = (int)$testUser['id'];
itm_script_test_employee_register_teardown($conn, $employeeId);

$session = [
    'employee_id' => $employeeId,
    'username' => (string)$testUser['username'],
    'company_id' => 1,
    'csrf_token' => 'test_token'
];

$evilUsername = 'eviladmin_' . uniqid();
$post = [
    'csrf_token' => 'test_token',
    'table' => 'employees',
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

$run = run_isolated_post(realpath(__DIR__ . '/../modules/select_options_api.php'), $session, $post);
$output = $run['output'];
$phpBinUsed = (string)($run['php_bin'] ?? '');

if ($output === null || $output === '') {
    echo colorText('[FAIL] Isolated subprocess produced no output (shell_exec disabled or blocked).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

// WHY: Extract the HTTP body from the output in case PHP SAPI outputs HTTP headers to stdout
$body = trim((string)$output);
$double_break = strpos($body, "\r\n\r\n");
if ($double_break !== false) {
    $body = substr($body, $double_break + 4);
} else {
    $double_break = strpos($body, "\n\n");
    if ($double_break !== false) {
        $body = substr($body, $double_break + 2);
    }
}
$body = trim($body);

$decoded = json_decode($body, true);
$blockedByPolicy = is_array($decoded)
    && empty($decoded['ok'])
    && stripos((string)($decoded['error'] ?? ''), 'quick-add') !== false;

$row = null;
$checkStmt = mysqli_prepare($conn, 'SELECT id, role_id FROM employees WHERE username = ? LIMIT 1');
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 's', $evilUsername);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    if ($checkRes) {
        $row = mysqli_fetch_assoc($checkRes);
    }
    mysqli_stmt_close($checkStmt);
}

if ($row && (int)$row['role_id'] === 1) {
    echo colorText("[FAIL] Select Options API: Regular user successfully created an Admin user!", 'fail') . $nl;
    mysqli_query($conn, 'DELETE FROM employees WHERE id = ' . (int)$row['id']);
} elseif ($blockedByPolicy) {
    echo colorText('[PASS] Select Options API: Admin creation blocked by table whitelist.', 'pass') . $nl;
    verify_select_options_echo_text('Response body: ' . $body);
} else {
    echo colorText('[FAIL] Select Options API: Expected whitelist block JSON (see Expected PASS above).', 'fail') . $nl;
    verify_select_options_echo_text('Subprocess PHP binary: ' . $phpBinUsed);
    if (verify_select_options_is_login_redirect_output($output)) {
        echo "Debug: Received HTTP redirect to login.php — this is a [FAIL], not [PASS]." . $nl;
        echo "Debug: The isolated runner must use CLI php.exe so ITM_CLI_SCRIPT skips web auth; Apache php-cgi triggers login redirect." . $nl;
        echo "Debug: Set PHP_EXE in .env to your Dunebox/Laragon php.exe path, or run: php scripts/verify_select_options_escalation.php" . $nl;
    }
    if ($decoded === null) {
        echo "Debug: Output was not valid JSON. Raw subprocess output:" . $nl;
        verify_select_options_echo_text((string)$output);
    } else {
        verify_select_options_echo_text('Debug: Parsed JSON: ' . print_r($decoded, true));
    }
    verify_select_options_echo_text(
        'Debug: Session used in subprocess — employee_id: ' . $employeeId
        . ', username: ' . (string)$testUser['username'] . ', company_id: 1'
    );
}

itm_script_output_end();
