<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();


// Setup a test record from today
$company_id = 1;
$now = date('Y-m-d H:i:s');
$stmt = mysqli_prepare($conn, "INSERT INTO visitors_access_log (company_id, visitor_name, date_time_in, reason_for_visit) VALUES (?, 'Original Name', ?, 'Original Reason')");
mysqli_stmt_bind_param($stmt, 'is', $company_id, $now);
mysqli_stmt_execute($stmt);
$id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

function make_request($id, $field, $value) {
    global $company_id;
    $post_data = [
        'ajax_inline_edit' => '1',
        'id' => $id,
        'field' => $field,
        'value' => $value,
        'csrf_token' => 'attack_token'
    ];

    $config_path = realpath(__DIR__ . '/../config/config.php');
    $module_dir = realpath(__DIR__ . '/../modules/visitors_access_log');
    $code = "<?php
    define('ITM_CLI_SCRIPT', true);
    require '$config_path';
    \$_SESSION['employee_id'] = 1;
    \$_SESSION['company_id'] = $company_id;
    \$_SESSION['csrf_token'] = 'attack_token';
    \$_POST = " . var_export($post_data, true) . ";
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    chdir('$module_dir');
    include 'index.php';
    ?>";

    $tmp = tempnam(sys_get_temp_dir(), 'sqli_fixed');
    file_put_contents($tmp, $code);
    $out = shell_exec("php $tmp");
    unlink($tmp);
    return $out;
}

$res_fixed = make_request($id, "visitor_name = 'PWNED', reason_for_visit", 'injected');
$stmt = mysqli_prepare($conn, "SELECT visitor_name FROM visitors_access_log WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($row['visitor_name'] === 'Original Name') {
    echo "PASS: SQL Injection was blocked." . $nl;
} else {
    echo "FAIL: SQL Injection successful." . $nl;
}

// Teardown
$stmt = mysqli_prepare($conn, "DELETE FROM visitors_access_log WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
