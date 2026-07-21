<?php
/**
 * CLI: php scripts/verify_notes_vault.php
 * Verifies private notes vault encryption (encrypt on write, decrypt on read, shared notes plaintext).
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/notes/notes_vault_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Notes Vault Verification');

$failures = 0;

function notes_vault_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function notes_vault_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    notes_vault_fail('Database connection unavailable.');
    exit(1);
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-notes-vault']);
if (!is_array($actor) || empty($actor['id'])) {
    notes_vault_fail('Could not create disposable test employee.');
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$schemaRes = $conn->query("SHOW COLUMNS FROM notes LIKE 'title_hash'");
if (!$schemaRes || $schemaRes->num_rows === 0) {
    notes_vault_fail('notes.title_hash column missing — re-import via bash scripts/import_database_split.sh, bash scripts/import_database_split.sh, or apply the notes vault schema migration.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
$masterKey = 'NotesVaultTestKey-' . bin2hex(random_bytes(4));
$vaultHash = password_hash($masterKey, PASSWORD_DEFAULT);
$vaultSession = hash('sha256', $masterKey);

$upd = $conn->prepare('UPDATE employees SET vault_key_hash = ? WHERE id = ?');
$upd->bind_param('si', $vaultHash, $employeeId);
$upd->execute();
$upd->close();

$_SESSION['vault_key'] = $vaultSession;
$_SESSION['employee_id'] = $employeeId;
$_SESSION['company_id'] = $companyId;

$privatePrepared = notes_prepare_note_fields_for_storage('Secret title', 'Secret body', null, null);
if ($privatePrepared === null || $privatePrepared['title'] === 'Secret title') {
    notes_vault_fail('Private note fields were not encrypted.');
} else {
    notes_vault_pass('Private note fields encrypt at rest.');
}

$sharedJson = json_encode([$employeeId + 9999]);
$sharedPrepared = notes_prepare_note_fields_for_storage('Shared title', 'Shared body', null, $sharedJson);
if ($sharedPrepared === null || $sharedPrepared['title'] !== 'Shared title' || $sharedPrepared['content'] !== 'Shared body') {
    notes_vault_fail('Shared notes must remain plaintext.');
} else {
    notes_vault_pass('Shared notes stay plaintext for recipients.');
}

$ins = $conn->prepare('INSERT INTO notes (company_id, employee_id, title, title_hash, content, created_by) VALUES (?, ?, ?, ?, ?, ?)');
$ins->bind_param(
    'iisssi',
    $companyId,
    $employeeId,
    $privatePrepared['title'],
    $privatePrepared['title_hash'],
    $privatePrepared['content'],
    $employeeId
);
if (!$ins->execute()) {
    notes_vault_fail('Could not insert encrypted note row.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
$noteId = (int)$conn->insert_id;
$ins->close();

$row = ['id' => $noteId, 'employee_id' => $employeeId, 'title' => $privatePrepared['title'], 'content' => $privatePrepared['content'], 'shared_with_json' => null];
notes_hydrate_note_row($row, $employeeId);
if ($row['title'] !== 'Secret title' || $row['content'] !== 'Secret body') {
    notes_vault_fail('Hydrate did not decrypt private note for owner.');
} else {
    notes_vault_pass('Owner can decrypt private notes when vault is unlocked.');
}

$labelPrepared = notes_prepare_label_storage('Work');
if ($labelPrepared === null || $labelPrepared['label'] === 'Work') {
    notes_vault_fail('Private labels were not encrypted.');
} else {
    notes_vault_pass('Private labels encrypt at rest.');
}

$plainLabel = notes_hydrate_label_text($labelPrepared['label'], $employeeId, $employeeId);
if ($plainLabel !== 'Work') {
    notes_vault_fail('Label hydrate failed.');
} else {
    notes_vault_pass('Private labels decrypt for owner.');
}

unset($_SESSION['vault_key']);
$rowLocked = ['id' => $noteId, 'employee_id' => $employeeId, 'title' => $privatePrepared['title'], 'content' => $privatePrepared['content'], 'shared_with_json' => null];
notes_hydrate_note_row($rowLocked, $employeeId);
if ($rowLocked['title'] !== '' || empty($rowLocked['title_locked'])) {
    notes_vault_fail('Locked vault should hide private note text.');
} else {
    notes_vault_pass('Locked vault hides private note text.');
}

$conn->query('DELETE FROM notes WHERE id = ' . (int)$noteId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    fwrite(STDERR, "verify_notes_vault.php: {$failures} failure(s)\n");
    exit(1);
}

fwrite(STDOUT, "verify_notes_vault.php: all checks passed\n");
exit(0);
