<?php
// CSRF: itm_validate_csrf_token()
/**
 * Reproduction & Diagnostics script for IDOR in modules/contacts/api/inline_edit.php.
 * Dynamically resolves valid role/access IDs and handles test user creation with verbose debugging.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('PoC: Contacts IDOR (Diagnostic)');

$nl = itm_script_output_nl();

require_once __DIR__ . '/lib/itm_script_test_employee.php';

$company_id = 1;

echo colorText("[DEBUG] Starting Contacts IDOR Diagnostics...", 'info') . $nl;

// Validate database connection early
if (!$conn || !($conn instanceof mysqli)) {
    echo colorText("[ERROR] Database connection is invalid or not a mysqli instance.", 'fail') . $nl;
    exit(1);
}

// ----------------------------------------------------
// DYNAMIC LOOKUP OF ROLE & ACCESS LEVEL TO PREVENT FK FAILURES
// ----------------------------------------------------
echo "[DEBUG] Dynamically resolving valid Foreign Keys for company ID $company_id..." . $nl;

// 1. Retrieve a valid non-admin role ID for this company
$role_id = 2; // Default fallback
$roleRes = mysqli_query($conn, "SELECT id, name FROM employee_roles WHERE company_id = $company_id AND LOWER(name) != 'admin' LIMIT 1");
if ($roleRes && $row = mysqli_fetch_assoc($roleRes)) {
    $role_id = (int)$row['id'];
    echo "  -> Found non-admin role: '" . $row['name'] . "' (ID: $role_id)" . $nl;
} else {
    echo "  -> [WARNING] No explicit non-admin role found in database for Company $company_id. Falling back to ID: $role_id" . $nl;
}

// 2. Retrieve a valid access level ID for this company
$access_level_id = 1; // Default fallback
$accessRes = mysqli_query($conn, "SELECT id, name FROM access_levels WHERE company_id = $company_id LIMIT 1");
if ($accessRes && $row = mysqli_fetch_assoc($accessRes)) {
    $access_level_id = (int)$row['id'];
    echo "  -> Found access level: '" . $row['name'] . "' (ID: $access_level_id)" . $nl;
} else {
    echo "  -> [WARNING] No access levels found for Company $company_id. Falling back to ID: $access_level_id" . $nl;
}

// 3. Ensure employment status is valid
$employment_status_id = 1;
$statusRes = mysqli_query($conn, "SELECT id, name FROM employee_statuses WHERE company_id = $company_id LIMIT 1");
if ($statusRes && $row = mysqli_fetch_assoc($statusRes)) {
    $employment_status_id = (int)$row['id'];
    echo "  -> Found employment status: '" . $row['name'] . "' (ID: $employment_status_id)" . $nl;
}


// ----------------------------------------------------
// CUSTOM ROBUST TEST EMPLOYEE CREATOR (EXHAUSTIVE DIAGNOSTICS)
// ----------------------------------------------------

/**
 * Creates a test employee with statement-level error capturing BEFORE close to prevent error erasing.
 */
function repro_create_test_employee($conn, $companyId, $options = []) {
    $scriptSlug = $options['script_slug'] ?? 'script';
    $username = itm_script_test_employee_username($scriptSlug);
    $firstName = $options['first_name'] ?? 'Script';
    $lastName = $options['last_name'] ?? 'Test';
    $email = $options['email'] ?? ($username . '@script-test.example.com');
    $password = $options['password'] ?? 'script-test-pass';
    $roleId = $options['role_id'] ?? null;
    $accessLevelId = $options['access_level_id'] ?? null;
    $employmentStatusId = $options['employment_status_id'] ?? 1;

    // Detect actual column layout to avoid missing columns
    $columnsInDb = [];
    $resCols = mysqli_query($conn, "SHOW COLUMNS FROM employees");
    if ($resCols) {
        while ($colRow = mysqli_fetch_assoc($resCols)) {
            $columnsInDb[] = $colRow['Field'];
        }
    }

    $colsToInsert = ['company_id', 'first_name', 'last_name', 'username', 'work_email', 'password', 'employment_status_id'];
    $bindTypes = 'isssssi';
    $bindVals = [$companyId, $firstName, $lastName, $username, $email, $password, $employmentStatusId];

    if (in_array('role_id', $columnsInDb) && $roleId !== null) {
        $colsToInsert[] = 'role_id';
        $bindTypes .= 'i';
        $bindVals[] = $roleId;
    }
    if (in_array('access_level_id', $columnsInDb) && $accessLevelId !== null) {
        $colsToInsert[] = 'access_level_id';
        $bindTypes .= 'i';
        $bindVals[] = $accessLevelId;
    }

    $placeholders = array_fill(0, count($colsToInsert), '?');
    $sql = 'INSERT INTO employees (' . implode(', ', $colsToInsert) . ') VALUES (' . implode(', ', $placeholders) . ')';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo "[ERROR] mysqli_prepare failed: " . mysqli_error($conn) . " (Code: " . mysqli_errno($conn) . ")\n";
        return null;
    }

    $bindParams = [$stmt, $bindTypes];
    foreach ($bindVals as $key => $val) {
        $bindParams[] = &$bindVals[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $bindParams);

    if (!mysqli_stmt_execute($stmt)) {
        // CAPTURE ERROR IMMEDIATELY BEFORE CLOSING THE STATEMENT (Critical!)
        $stmtError = mysqli_stmt_error($stmt);
        $stmtErrno = mysqli_stmt_errno($stmt);
        $connError = mysqli_error($conn);
        $connErrno = mysqli_errno($conn);

        echo "[ERROR] mysqli_stmt_execute failed!\n";
        echo "  -> Statement Error: $stmtError (Code: $stmtErrno)\n";
        echo "  -> Connection Error: $connError (Code: $connErrno)\n";

        mysqli_stmt_close($stmt);
        return null;
    }

    $insertedId = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);

    if ($insertedId <= 0) {
        echo "[ERROR] Insert succeeded but auto_increment ID was not returned.\n";
        return null;
    }

    return [
        'id' => $insertedId,
        'username' => $username,
        'email' => $email,
        'company_id' => $companyId,
    ];
}


// 1. Create Attacker (non-admin)
echo "[DEBUG] Attempting to create Attacker employee..." . $nl;
$attacker = repro_create_test_employee($conn, $company_id, [
    'script_slug' => 'repro-idor-attacker',
    'role_id' => $role_id,
    'access_level_id' => $access_level_id,
    'employment_status_id' => $employment_status_id,
]);

if (!$attacker) {
    echo colorText("[FATAL ERROR] Failed to create attacker employee.", 'fail') . $nl;
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);
echo colorText("[DEBUG] Attacker created successfully.", 'pass') . $nl;


// 2. Create Victim
echo "[DEBUG] Attempting to create Victim employee..." . $nl;
$victim = repro_create_test_employee($conn, $company_id, [
    'script_slug' => 'repro-idor-victim',
    'role_id' => $role_id,
    'access_level_id' => $access_level_id,
    'employment_status_id' => $employment_status_id,
]);

if (!$victim) {
    echo colorText("[FATAL ERROR] Failed to create victim employee.", 'fail') . $nl;
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$victim['id']);
echo colorText("[DEBUG] Victim created successfully.", 'pass') . $nl;


echo "Attacker ID: " . $attacker['id'] . "\n";
echo "Victim ID: " . $victim['id'] . "\n";
echo "Victim Email before attack: " . $victim['email'] . "\n";

// 3. Simulate Attacker Session
$_SESSION['employee_id'] = (int)$attacker['id'];
$_SESSION['company_id'] = $company_id;
$_SESSION['username'] = $attacker['username'];
$_SESSION['csrf_token'] = 'test_token';

// 4. Perform Attack
$postData = [
    'type' => 'emp',
    'id' => $victim['id'],
    'field' => 'work_email',
    'value' => 'pwned@example.com',
    'csrf_token' => 'test_token'
];

function run_contacts_request($script_path, $session_data, $post_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_contacts');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/contacts/api/inline_edit.php';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_POST = " . var_export($post_data, true) . ";

chdir(dirname('$script_path'));
include basename('$script_path');
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$modulePath = realpath(__DIR__ . '/../modules/contacts/api/inline_edit.php');
$session = [
    'employee_id' => (int)$attacker['id'],
    'company_id' => $company_id,
    'username' => $attacker['username'],
    'csrf_token' => 'test_token'
];

echo "[DEBUG] Sending POST request simulating IDOR update on inline_edit.php..." . $nl;
$output = run_contacts_request($modulePath, $session, $postData);

echo "API Output: " . $output . "\n";

// 5. Verify
$res = mysqli_query($conn, "SELECT work_email FROM employees WHERE id = " . (int)$victim['id']);
$row = mysqli_fetch_assoc($res);

if ($row['work_email'] === 'pwned@example.com') {
    echo colorText("[FAIL] IDOR Vulnerability confirmed: Victim's email was updated by another user.", 'fail') . "\n";
} else {
    echo colorText("[PASS] IDOR Vulnerability not found or blocked.", 'pass') . "\n";
}

itm_script_output_end();
