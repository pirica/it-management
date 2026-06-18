<?php
/**
 * Bug reproduction and verification script for Todo module visibility and security.
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/todo_visibility.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Todo Module Bug Verification');

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "=== Todo Module Bug Verification ===" . $nl;

function verify_multi_assign_visibility($conn, $nl) {
    echo $nl . "[1/2] Verifying multi-assign visibility fix..." . $nl;
    $company_id = 1;
    $user_id = 12345; // dummy user id

    // Task assigned to multiple users including $user_id
    $title = "Multi-assign task " . uniqid();
    $assigned_to = "99999," . $user_id;
    $stmt = $conn->prepare("INSERT INTO todo (company_id, title, assigned_to_user_id, created_by_user_id) VALUES (?, ?, ?, 1)");
    if (!$stmt) die("ERROR: " . $conn->error . $nl);
    $stmt->bind_param("iss", $company_id, $title, $assigned_to);
    $stmt->execute();
    $taskId = $stmt->insert_id;

    // Check if visible using itm_todo_visibility_sql
    $visSql = itm_todo_visibility_sql("t");
    $sql = "SELECT id FROM todo t WHERE t.id = $taskId AND ($visSql)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("ERROR: " . $conn->error . " in SQL: $sql" . $nl);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo "FAIL: Task assigned to multiple users is NOT visible to them (Bug confirmed)." . $nl;
        $success = false;
    } else {
        echo "PASS: Multi-assigned task is visible." . $nl;
        $success = true;
    }

    $conn->query("DELETE FROM todo WHERE id = $taskId");
    return $success;
}

function verify_action_permission_enforcement($conn, $nl) {
    echo $nl . "[2/2] Verifying action permission enforcement fix..." . $nl;
    $company_id = 1;
    $owner_id = 1;
    $attacker_id = 2;

    // Create a private task for owner
    $title = "Private task " . uniqid();
    $stmt = $conn->prepare("INSERT INTO todo (company_id, title, assigned_to_user_id, created_by_user_id) VALUES (?, ?, ?, ?)");
    if (!$stmt) die("ERROR: " . $conn->error . $nl);
    $assigned_to = (string)$owner_id;
    $stmt->bind_param("issi", $company_id, $title, $assigned_to, $owner_id);
    $stmt->execute();
    $taskId = $stmt->insert_id;

    $visSql = itm_todo_visibility_sql();

    // Test fetch
    $stmt = $conn->prepare("SELECT * FROM todo WHERE id = ? AND company_id = ? AND active = 1 AND ($visSql)");
    if (!$stmt) die("ERROR: " . $conn->error . $nl);
    $stmt->bind_param("iiii", $taskId, $company_id, $attacker_id, $attacker_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    $fetchBlocked = !$data;
    if ($fetchBlocked) {
        echo "PASS: Attacker cannot fetch private task via ID." . $nl;
    } else {
        echo "FAIL: Attacker can fetch private task via ID (Bug confirmed)." . $nl;
    }

    // Test update (toggle)
    $stmt = $conn->prepare("UPDATE todo SET completed = 1 WHERE id = ? AND company_id = ? AND ($visSql)");
    if (!$stmt) die("ERROR: " . $conn->error . $nl);
    $stmt->bind_param("iiii", $taskId, $company_id, $attacker_id, $attacker_id);
    $stmt->execute();
    $updateBlocked = ($stmt->affected_rows === 0);

    if ($updateBlocked) {
        echo "PASS: Attacker cannot modify private task via ID." . $nl;
    } else {
        echo "FAIL: Attacker can modify private task via ID (Bug confirmed)." . $nl;
    }

    $conn->query("DELETE FROM todo WHERE id = $taskId");
    return $fetchBlocked && $updateBlocked;
}

$success1 = verify_multi_assign_visibility($conn, $nl);
$success2 = verify_action_permission_enforcement($conn, $nl);

echo $nl . "Summary: " . ($success1 && $success2 ? "ALL PASS" : "FAILURES DETECTED") . $nl;
exit($success1 && $success2 ? 0 : 1);
