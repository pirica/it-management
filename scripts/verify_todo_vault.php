<?php
/**
 * CLI: php scripts/verify_todo_vault.php
 * Verifies private todo vault encryption (encrypt on write, decrypt on read, shared tasks plaintext).
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/todo/todo_vault_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Todo Vault Verification');

$failures = 0;

function todo_vault_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function todo_vault_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    todo_vault_fail('Database connection unavailable.');
    exit(1);
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-todo-vault']);
if (!is_array($actor) || empty($actor['id'])) {
    todo_vault_fail('Could not create disposable test employee.');
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$schemaRes = $conn->query("SHOW COLUMNS FROM todo LIKE 'title_hash'");
if (!$schemaRes || $schemaRes->num_rows === 0) {
    todo_vault_fail('todo.title_hash column missing — re-import db/ bundle or apply db/migrations/todo_vault.sql.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}

$masterKey = 'TodoVaultTestKey-' . bin2hex(random_bytes(4));
$vaultHash = password_hash($masterKey, PASSWORD_DEFAULT);
$vaultSession = hash('sha256', $masterKey);

$upd = $conn->prepare('UPDATE employees SET vault_key_hash = ? WHERE id = ?');
$upd->bind_param('si', $vaultHash, $employeeId);
$upd->execute();
$upd->close();

$_SESSION['vault_key'] = $vaultSession;
$_SESSION['employee_id'] = $employeeId;
$_SESSION['company_id'] = $companyId;

$privatePrepared = todo_prepare_task_fields_for_storage('Secret task', 'Secret body', '', $employeeId);
if ($privatePrepared === null || $privatePrepared['title'] === 'Secret task') {
    todo_vault_fail('Private task fields were not encrypted.');
} else {
    todo_vault_pass('Private task fields encrypt at rest.');
}

$sharedPrepared = todo_prepare_task_fields_for_storage('Shared task', 'Shared body', null, $employeeId);
if ($sharedPrepared === null || $sharedPrepared['title'] !== 'Shared task' || $sharedPrepared['description'] !== 'Shared body') {
    todo_vault_fail('Company-global tasks must remain plaintext.');
} else {
    todo_vault_pass('Company-global tasks stay plaintext.');
}

$ins = $conn->prepare('INSERT INTO todo (company_id, title, title_hash, description, created_by) VALUES (?, ?, ?, ?, ?)');
$ins->bind_param(
    'isssi',
    $companyId,
    $privatePrepared['title'],
    $privatePrepared['title_hash'],
    $privatePrepared['description'],
    $employeeId
);
if (!$ins->execute()) {
    todo_vault_fail('Could not insert encrypted task row.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
$taskId = (int)$conn->insert_id;
$ins->close();

$row = [
    'id' => $taskId,
    'created_by' => $employeeId,
    'title' => $privatePrepared['title'],
    'description' => $privatePrepared['description'],
    'assigned_to_employee_id' => '',
];
todo_hydrate_task_row($row, $employeeId);
if ($row['title'] !== 'Secret task' || $row['description'] !== 'Secret body') {
    todo_vault_fail('Hydrate did not decrypt private task for owner.');
} else {
    todo_vault_pass('Owner can decrypt private tasks when vault is unlocked.');
}

unset($_SESSION['vault_key']);
$rowLocked = [
    'id' => $taskId,
    'created_by' => $employeeId,
    'title' => $privatePrepared['title'],
    'description' => $privatePrepared['description'],
    'assigned_to_employee_id' => '',
];
todo_hydrate_task_row($rowLocked, $employeeId);
if (!$rowLocked['title_locked'] || $rowLocked['title'] !== '') {
    todo_vault_fail('Locked vault must hide private task title.');
} else {
    todo_vault_pass('Locked vault hides private task content.');
}

$conn->query('DELETE FROM todo WHERE id = ' . (int)$taskId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failure(s).\n");
    exit(1);
}

fwrite(STDOUT, "\nAll todo vault checks passed.\n");
exit(0);
