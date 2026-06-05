<?php
/**
 * Test script for employee import user samples.
 *
 * Verifies that itm_handle_json_table_import correctly processes
 * both "Normal" (Hilton ID, Title) and "Tabular" (Id▼, Position Title) formats.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Why: config.php redirects to login.php if it doesn't see a CLI session or a logged-in user.
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

// Mock session for audit logs and CSRF
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['csrf_token'] = 'test_token';

// Set MySQL session variables for triggers
if (isset($conn) && $conn) {
    $sql = "SET @app_user_id = 1";
    itm_run_query($conn, $sql);
    $sql = "SET @app_company_id = 1";
    itm_run_query($conn, $sql);
}

function test_import($name, $headers, $rows) {
    global $conn;
    echo "--- Testing Import: $name ---\n";

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $importRows = [$headers];
    foreach ($rows as $row) {
        $importRows[] = $row;
    }

    $payload = [
        'csrf_token' => 'test_token',
        'import_excel_rows' => $importRows
    ];

    // We use the 5th parameter to return the result instead of exit-ing with JSON
    $result = itm_handle_json_table_import($conn, 'employees', 1, $payload, true);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
}

// Sample 1: Normal (Hilton ID)
$headers1 = ["Hilton ID", "User Name", "Display Name", "Email", "Employee Status", "First Name", "Last Name", "Job Code", "Title", "Employment Status"];
$rows1 = [
    ["A0308069", "aquiteque", "Aldair Quiteque", "quitequealdair@gmail.com", "A", "Aldair", "Quiteque", "Commis Waiter/Ess", "Waiter", "Active"],
    ["A004061C", "alejandracastro", "Alejandra Castro", "Alejandra.Castro@ConradHotels.com", "A", "Alejandra", "Castro", "Dir-Finance", "Finance Director, Iberian Peninsula", "Active"]
];
test_import("User Sample 1 (Normal)", $headers1, $rows1);

// Sample 2: Tabular (Id▼, Position Title)
// Find a record to update
$sql = "SELECT id FROM employees WHERE company_id = 1 ORDER BY id DESC LIMIT 1";
$res = itm_run_query($conn, $sql);
$row = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
$idToUpdate = $row ? $row['id'] : 1;

$headers2 = ["Id▼", "Duplicate", "External ID", "Username", "Display Name", "Email", "Personal Email", "Dect", "Extension", "Raw Status Code", "First Name", "Last Name", "Job Code", "Position Title", "Reports To", "On Contacts", "Department Name", "Request Date", "Requested By", "Termination Requested By", "Termination Date", "Employment Status", "Workstation Mode", "Assignment Type", "Comments", "Created At", "Office Key Card Department Id", "On Orgchart", "Updated At"];
$rows2 = [
    [(string)$idToUpdate, "—", "A354231", "nelsonsalvador", "first last", "nelson.salvador@conradhotels.com", "nelsonsalvador@gmail.com", "5798", "6002", "A", "test first name", "Test last name", "IT Manager", "IT Manager", "", "✅", "Geral", "2026-06-05", "ads", "asd", "", "Active", "", "", "sad comments", "2026-06-05 16:02:49", "", "✅", ""]
];
test_import("User Sample 2 (Tabular/Excel Export - Update ID $idToUpdate)", $headers2, $rows2);
