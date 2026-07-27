<?php
/**
 * Events vault encryption regression.
 *
 * Browser: open scripts/verify_events_vault.php?run=1 (Administrator session).
 * CLI: php scripts/verify_events_vault.php
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_events_vault.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/events/events_vault_helpers.php</code>, persistence in <code>modules/events/index.php</code>, calendar event hydration, or <code>itm_vault_reencrypt_events()</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/events/events_vault_helpers.php';
require_once ROOT_PATH . 'includes/itm_vault_master_key.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Events Vault Verification');
$nl = itm_script_output_nl();

$failures = 0;

function events_vault_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function events_vault_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    events_vault_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-events-vault']);
if (!is_array($actor) || empty($actor['id'])) {
    events_vault_fail('Could not create disposable test employee.');
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$schemaRes = $conn->query("SHOW COLUMNS FROM events LIKE 'title_hash'");
if (!$schemaRes || $schemaRes->num_rows === 0) {
    events_vault_fail('events.title_hash column missing — re-import via bash scripts/import_database_split.sh or bash scripts/import_database_split.sh.');
    itm_script_test_employee_delete($conn, $employeeId);
    itm_script_output_end();
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
    itm_script_output_end();
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

$legacyTitle = 'Legacy standup';
$legacyDescription = 'Pre-vault plaintext description row';
$legacyLocation = 'Room A';
$legacyIns = $conn->prepare(
    'INSERT INTO events (company_id, employee_id, title, title_hash, description, location, start_datetime, end_datetime, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$legacyHash = events_text_hash($legacyTitle);
$legacyIns->bind_param(
    'iissssssi',
    $companyId,
    $employeeId,
    $legacyTitle,
    $legacyHash,
    $legacyDescription,
    $legacyLocation,
    $start,
    $end,
    $employeeId
);
if (!$legacyIns->execute()) {
    events_vault_fail('Could not insert legacy plaintext event row.');
} else {
    $legacyEventId = (int)$conn->insert_id;
    $legacyIns->close();

    $newMasterKey = 'EventsVaultRotate-' . bin2hex(random_bytes(4));
    $newVaultSession = hash('sha256', $newMasterKey);
    $reencrypt = itm_vault_reencrypt_events($conn, $employeeId, $vaultSession, $newVaultSession);
    if (empty($reencrypt['ok'])) {
        events_vault_fail('Master key re-encrypt failed for legacy plaintext events: ' . ($reencrypt['message'] ?? 'unknown'));
    } else {
        $_SESSION['vault_key'] = $newVaultSession;
        $legacyRow = [
            'id' => $legacyEventId,
            'employee_id' => $employeeId,
            'title' => '',
            'description' => '',
            'location' => '',
            'shared_with_json' => null,
        ];
        $legacySel = $conn->prepare('SELECT title, description, location FROM events WHERE id = ? AND employee_id = ?');
        $legacySel->bind_param('ii', $legacyEventId, $employeeId);
        $legacySel->execute();
        $legacyDb = $legacySel->get_result()->fetch_assoc();
        $legacySel->close();
        if (!$legacyDb) {
            events_vault_fail('Legacy event row missing after re-encrypt.');
        } else {
            $legacyRow['title'] = (string)$legacyDb['title'];
            $legacyRow['description'] = (string)($legacyDb['description'] ?? '');
            $legacyRow['location'] = (string)($legacyDb['location'] ?? '');
            events_hydrate_event_row($legacyRow, $employeeId);
            if ($legacyRow['title'] !== $legacyTitle
                || $legacyRow['description'] !== $legacyDescription
                || $legacyRow['location'] !== $legacyLocation) {
                events_vault_fail('Legacy plaintext event fields did not survive master key re-encrypt.');
            } else {
                events_vault_pass('Legacy plaintext private events re-encrypt during master key change.');
            }
        }
    }
    $conn->query('DELETE FROM events WHERE id = ' . (int)$legacyEventId);
}

$conn->query('DELETE FROM events WHERE id = ' . (int)$eventId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo $nl . colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . itm_script_format_status_line('[PASS] All events vault checks passed.') . $nl;
itm_script_output_end();
exit(0);
