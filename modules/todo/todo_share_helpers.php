<?php
/**
 * Temporary QR / code share sessions for Todo tasks.
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'includes/todo_visibility.php';

function todo_share_module_slug()
{
    return 'todo';
}

/** @deprecated Use todo_share_module_slug() */
function todo_share_table_name()
{
    return todo_share_module_slug();
}

function todo_share_join_script_path()
{
    return 'modules/todo/join.php';
}

/**
 * @param array<int,array<string,mixed>> $categories
 * @param array<int,array<string,mixed>> $departments
 * @param array<int,array<string,mixed>> $users
 * @return array<string,mixed>
 */
function todo_share_build_payload_from_task(array $task, array $categories, array $departments, array $users, $ownerUsername)
{
    $catNames = [];
    foreach (array_filter(explode(',', (string)($task['category_id'] ?? ''))) as $cid) {
        $cid = (int)$cid;
        if (isset($categories[$cid])) {
            $catNames[] = (string)($categories[$cid]['name'] ?? '');
        }
    }

    $deptNames = [];
    foreach (array_filter(explode(',', (string)($task['department_id'] ?? ''))) as $did) {
        $did = (int)$did;
        if (isset($departments[$did])) {
            $deptNames[] = !empty($departments[$did]['code'])
                ? (string)$departments[$did]['code']
                : (string)($departments[$did]['name'] ?? '');
        }
    }

    $assigneeNames = [];
    foreach (array_filter(explode(',', (string)($task['assigned_to_employee_id'] ?? ''))) as $uid) {
        $uid = (int)$uid;
        if (isset($users[$uid])) {
            $assigneeNames[] = (string)($users[$uid]['username'] ?? '');
        }
    }

    $title = (string)($task['title'] ?? '');
    $dueDate = (string)($task['due_date'] ?? '');
    if ($dueDate !== '') {
        $dueDate = itm_format_date_display($dueDate);
    }

    return [
        'type' => 'todo',
        'heading' => $title !== '' ? $title : 'Task',
        'owner_username' => (string)$ownerUsername,
        'title' => $title,
        'description' => (string)($task['description'] ?? ''),
        'due_date' => $dueDate,
        'reminder_at' => (string)($task['reminder_at'] ?? ''),
        'repeat_pattern' => (string)($task['repeat_pattern'] ?? ''),
        'importance' => (int)($task['importance'] ?? 0),
        'completed' => (int)($task['completed'] ?? 0),
        'categories' => implode(', ', $catNames),
        'departments' => implode(', ', $deptNames),
        'assignees' => implode(', ', $assigneeNames),
    ];
}

function todo_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(todo_share_join_script_path(), $accessToken);
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function todo_share_create_session($conn, $todoId, $companyId, $employeeId, $ownerUsername, array $categories, array $departments, array $users, $vaultUnlocked = false)
{
    $todoId = (int)$todoId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $vaultUnlocked = (bool)$vaultUnlocked;
    if ($todoId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $stmt = $conn->prepare(
        'SELECT * FROM todo WHERE id = ? AND company_id = ? AND created_by = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load task.'];
    }
    $stmt->bind_param('iii', $todoId, $companyId, $employeeId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$task) {
        return ['ok' => false, 'error' => 'Task not found or you are not the creator.'];
    }

    require_once __DIR__ . '/todo_vault_helpers.php';
    $isShared = todo_task_is_shared_with_others($task['assigned_to_employee_id'] ?? null, $employeeId);
    if (!$isShared && !$vaultUnlocked) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing a private task.'];
    }
    todo_hydrate_task_row($task, $employeeId);

    $payload = todo_share_build_payload_from_task($task, $categories, $departments, $users, $ownerUsername);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, todo_share_module_slug(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $todoId,
        'payload_json' => $payloadJson,
    ]);
}
