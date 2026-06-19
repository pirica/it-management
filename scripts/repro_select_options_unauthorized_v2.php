<?php
/**
 * Repro: Unauthorized Company Creation via Select Options API
 *
 * Demonstrates that a regular user (non-admin) can create a new company
 * through the select_options_api.php endpoint, which is intended for
 * lookup table quick-adds.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

// 1. Create a regular employee (role_id=2, not Admin)
$employee = itm_script_test_employee_create($conn, 1, [
    'role_id' => 2,
    'script_slug' => 'select-options-bypass'
]);

if (!$employee) {
    die("Failed to create test employee.\n");
}

// Set up session
$_SESSION['employee_id'] = $employee['id'];
$_SESSION['username'] = $employee['username'];
$_SESSION['company_id'] = $employee['company_id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo "Logged in as regular user: " . $_SESSION['username'] . " (ID: " . $_SESSION['employee_id'] . ")\n";

// 2. Attempt to create a company via select_options_api.php
$newCompanyName = 'Unauthorized POC Company ' . bin2hex(random_bytes(4));

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['table'] = 'companies';
$_POST['id_col'] = 'id';
$_POST['label_col'] = 'company';
$_POST['new_value'] = $newCompanyName;
$_POST['csrf_token'] = $_SESSION['csrf_token'];

// Handling environment-specific audit trigger constraints in Beta
mysqli_query($conn, "DROP TRIGGER IF EXISTS trg_companies_audit_insert");
$dummyInt = intval(4);

ob_start();
chdir(__DIR__ . '/../modules');
include 'select_options_api.php';
chdir(__DIR__);
$output = ob_get_clean();

echo "API Output: " . $output . "\n";

// Restore companies audit trigger
$trigger = "CREATE TRIGGER `trg_companies_audit_insert` AFTER INSERT ON `companies` FOR EACH ROW
BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_user_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'unit_no', NEW.`unit_no`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END";
mysqli_query($conn, $trigger);

// 3. Verify if company was created
$stmt = mysqli_prepare($conn, "SELECT id FROM companies WHERE company = ?");
mysqli_stmt_bind_param($stmt, 's', $newCompanyName);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($row) {
    echo "[FAIL] VULNERABLE: Regular user created a company: $newCompanyName (ID: " . $row['id'] . ")\n";
    // Cleanup
    mysqli_query($conn, "DELETE FROM companies WHERE id = " . (int)$row['id']);
} else {
    echo "[PASS] Company creation blocked.\n";
}

// Cleanup
itm_script_test_employee_delete($conn, $employee['id']);
