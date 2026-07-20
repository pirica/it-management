<?php
/**
 * CLI: php scripts/verify_events_vault.php
 * Verifies private events vault encryption (encrypt on write, decrypt on read, shared events plaintext).
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/events/events_vault_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Events Vault Verification');

$failures = 0;

function events_vault_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function events_vault_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    events_vault_fail('Database connection unavailable.');
    exit(1);
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-events-vault']);
if (!is_array($actor) || empty($actor['id'])) {
    events_vault_fail('Could not create disposable test employee.');
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$schemaRes = $conn->query("SHOW COLUMNS FROM events LIKE 'title_hash'");
if (!$schemaRes || $schemaRes->num_rows === 0) {
    events_vault_fail('events.title_hash column missing — re-import database.sql or bash scripts/import_database_split.sh.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}

$masterKey = 'EventsVaultTestKey-' . bin2hex(random_bytes(4));
$vaultHash = password_hash($masterKey, PASSWORD_DEFAULT);
$vaultSession = hash('sha256', $masterKey);

$upd = $conn->prepare('UPDATE employees SET vault_key_hash = ? WHERE id = ?');
$upd->bind_param('si', $vaultHash, $employeeId);
$upd->execute();
$upd->close();

$_SESSION['vault_key'] = $vaultSession;
$_SESSION['employee_id'] = $employeeId;
$_SESSION['company_id'] = $companyId;

$privatePrepared = events_prepare_event_fields_for_storage('Secret title', 'Secret description', 'Secret location', null);
if ($privatePrepared === null || $privatePrepared['title'] === 'Secret title') {
    events_vault_fail('Private event fields were not encrypted.');
} else {
    events_vault_pass('Private event fields encrypt at rest.');
}

$sharedJson = json_encode([$employeeId + 9999]);
$sharedPrepared = events_prepare_event_fields_for_storage('Shared title', 'Shared body', 'Shared room', $sharedJson);
if ($sharedPrepared === null || $sharedPrepared['title'] !== 'Shared title' || $sharedPrepared['description'] !== 'Shared body') {
    events_vault_fail('Shared events must remain plaintext.');
} else {
    events_vault_pass('Shared events stay plaintext for recipients.');
}

$ins = $conn->prepare(
    'INSERT INTO events (company_id, employee_id, title, title_hash, description, location, start_datetime, end_datetime, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$start = '2026-06-01 09:00:00';
$end = '2026-06-01 10:00:00';
$ins->bind_param(
    'iissssssi',
    $companyId,
    $employeeId,
    $privatePrepared['title'],
    $privatePrepared['title_hash'],
    $privatePrepared['description'],
    $privatePrepared['location'],
    $start,
    $end,
    $employeeId
);
if (!$ins->execute()) {
    events_vault_fail('Could not insert encrypted event row.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
$eventId = (int)$conn->insert_id;
$ins->close();

$row = [
    'id' => $eventId,
    'employee_id' => $employeeId,
    'title' => $privatePrepared['title'],
    'description' => $privatePrepared['description'],
    'location' => $privatePrepared['location'],
    'shared_with_json' => null,
];
events_hydrate_event_row($row, $employeeId);
if ($row['title'] !== 'Secret title' || $row['description'] !== 'Secret description' || $row['location'] !== 'Secret location') {
    events_vault_fail('Hydrate did not decrypt private event for owner.');
} else {
    events_vault_pass('Owner can decrypt private events when vault is unlocked.');
}

unset($_SESSION['vault_key']);
$rowLocked = [
    'id' => $eventId,
    'employee_id' => $employeeId,
    'title' => $privatePrepared['title'],
    'description' => $privatePrepared['description'],
    'location' => $privatePrepared['location'],
    'shared_with_json' => null,
];
events_hydrate_event_row($rowLocked, $employeeId);
if ($rowLocked['title'] !== '' || empty($rowLocked['title_locked'])) {
    events_vault_fail('Locked vault should hide private event text.');
} else {
    events_vault_pass('Locked vault hides private event text.');
}

$conn->query('DELETE FROM events WHERE id = ' . (int)$eventId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    fwrite(STDERR, "verify_events_vault.php: {$failures} failure(s)\n");
    exit(1);
}

fwrite(STDOUT, "verify_events_vault.php: all checks passed\n");
exit(0);
