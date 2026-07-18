<?php
/**
 * Bug reproduction and verification script for Todo module visibility and security.
 */
$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_USER=root');
    putenv('DB_PASS=itmanagement');
    putenv('DB_NAME=itmanagement');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'includes/todo_visibility.php';

itm_script_output_begin('Todo Module Bug Verification');
$nl = itm_script_output_nl();

echo colorText('Todo Module Bug Verification', 'info') . $nl;

/**
 * @return bool
 */
function repro_bug_verify_multi_assign_visibility(mysqli $conn, string $nl): bool
{
    echo $nl . colorText('[1/2] Verifying multi-assign visibility fix...', 'info') . $nl;

    $company_id = 1;
    $user_id = 12345;

    $title = 'Multi-assign task ' . uniqid();
    $assigned_to = '99999,' . $user_id;
    $stmt = $conn->prepare('INSERT INTO todo (company_id, title, assigned_to_employee_id, created_by) VALUES (?, ?, ?, 1)');
    if (!$stmt) {
        echo itm_script_format_status_line('[FAIL] Multi-assign visibility: insert failed — ' . $conn->error) . $nl;
        return false;
    }
    $stmt->bind_param('iss', $company_id, $title, $assigned_to);
    $stmt->execute();
    $taskId = (int)$stmt->insert_id;
    $stmt->close();

    $visSql = itm_todo_visibility_sql('t');
    $sql = "SELECT id FROM todo t WHERE t.id = $taskId AND ($visSql)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo itm_script_format_status_line('[FAIL] Multi-assign visibility: query prepare failed.') . $nl;
        $conn->query('DELETE FROM todo WHERE id = ' . (int)$taskId);
        return false;
    }
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $visible = $res && $res->num_rows > 0;
    $stmt->close();

    $conn->query('DELETE FROM todo WHERE id = ' . (int)$taskId);

    if (!$visible) {
        echo itm_script_format_status_line('[FAIL] Multi-assign visibility: task not visible to assigned user.') . $nl;
        return false;
    }

    echo itm_script_format_status_line('[PASS] Multi-assign visibility: assigned user can see the task.') . $nl;
    return true;
}

/**
 * @return bool
 */
function repro_bug_verify_action_permission_enforcement(mysqli $conn, string $nl): bool
{
    echo $nl . colorText('[2/2] Verifying action permission enforcement fix...', 'info') . $nl;

    $company_id = 1;
    $owner_id = 1;
    $attacker_id = 2;

    $title = 'Private task ' . uniqid();
    $stmt = $conn->prepare('INSERT INTO todo (company_id, title, assigned_to_employee_id, created_by) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        echo itm_script_format_status_line('[FAIL] Action permission: insert failed — ' . $conn->error) . $nl;
        return false;
    }
    $assigned_to = (string)$owner_id;
    $stmt->bind_param('issi', $company_id, $title, $assigned_to, $owner_id);
    $stmt->execute();
    $taskId = (int)$stmt->insert_id;
    $stmt->close();

    $visSql = itm_todo_visibility_sql();
    $allPassed = true;

    $stmt = $conn->prepare("SELECT * FROM todo WHERE id = ? AND company_id = ? AND active = 1 AND ($visSql)");
    if (!$stmt) {
        echo itm_script_format_status_line('[FAIL] Action permission: fetch query prepare failed.') . $nl;
        $conn->query('DELETE FROM todo WHERE id = ' . (int)$taskId);
        return false;
    }
    $stmt->bind_param('iiii', $taskId, $company_id, $attacker_id, $attacker_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        echo itm_script_format_status_line('[PASS] Action permission: attacker cannot fetch private task via ID.') . $nl;
    } else {
        echo itm_script_format_status_line('[FAIL] Action permission: attacker can fetch private task via ID.') . $nl;
        $allPassed = false;
    }

    $stmt = $conn->prepare("UPDATE todo SET completed = 1 WHERE id = ? AND company_id = ? AND ($visSql)");
    if (!$stmt) {
        echo itm_script_format_status_line('[FAIL] Action permission: update query prepare failed.') . $nl;
        $conn->query('DELETE FROM todo WHERE id = ' . (int)$taskId);
        return false;
    }
    $stmt->bind_param('iiii', $taskId, $company_id, $attacker_id, $attacker_id);
    $stmt->execute();
    $updateBlocked = ($stmt->affected_rows === 0);
    $stmt->close();

    if ($updateBlocked) {
        echo itm_script_format_status_line('[PASS] Action permission: attacker cannot modify private task via ID.') . $nl;
    } else {
        echo itm_script_format_status_line('[FAIL] Action permission: attacker can modify private task via ID.') . $nl;
        $allPassed = false;
    }

    $conn->query('DELETE FROM todo WHERE id = ' . (int)$taskId);
    return $allPassed;
}

$success1 = repro_bug_verify_multi_assign_visibility($conn, $nl);
$success2 = repro_bug_verify_action_permission_enforcement($conn, $nl);
$allPassed = $success1 && $success2;

echo $nl;
if ($allPassed) {
    echo itm_script_format_status_line('[PASS] Summary: all checks passed.') . $nl;
} else {
    echo itm_script_format_status_line('[FAIL] Summary: one or more checks failed.') . $nl;
}

itm_script_output_end();
exit($allPassed ? 0 : 1);
