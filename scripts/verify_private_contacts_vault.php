<?php
/**
 * Private contacts vault encryption regression.
 *
 * Browser: open scripts/verify_private_contacts_vault.php?run=1 (Administrator session).
 * CLI: php scripts/verify_private_contacts_vault.php
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_private_contacts_vault.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/private_contacts/pc_vault_helpers.php</code>, create/edit persistence, list hydrate/search, or <code>itm_vault_reencrypt_private_contacts()</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/private_contacts/pc_vault_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Private Contacts Vault Verification');
$nl = itm_script_output_nl();

$failures = 0;

function pc_vault_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function pc_vault_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    pc_vault_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-private-contacts-vault']);
if (!is_array($actor) || empty($actor['id'])) {
    pc_vault_verify_fail('Could not create disposable test employee.');
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$actor['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$masterKey = 'PcVaultTestKey-' . bin2hex(random_bytes(4));
$vaultHash = password_hash($masterKey, PASSWORD_DEFAULT);
$vaultSession = hash('sha256', $masterKey);

$upd = $conn->prepare('UPDATE employees SET vault_key_hash = ? WHERE id = ?');
$upd->bind_param('si', $vaultHash, $employeeId);
$upd->execute();
$upd->close();

$_SESSION['vault_key'] = $vaultSession;
$_SESSION['employee_id'] = $employeeId;
$_SESSION['company_id'] = $companyId;

$plainRow = [
    'first_name' => 'Vault',
    'last_name' => 'Contact',
    'email1_value' => 'vault.test@example.com',
    'phone1_value' => '5550100',
    'organization_name' => 'Vault Org',
    'labels' => 'Test',
    'notes' => 'Encrypted note',
];
foreach (pc_vault_encrypted_field_names() as $field) {
    if (!array_key_exists($field, $plainRow)) {
        $plainRow[$field] = '';
    }
}

$stored = pc_prepare_contact_fields_from_plain($plainRow);
if ($stored === null || $stored['first_name'] === 'Vault') {
    pc_vault_verify_fail('Private contact fields were not encrypted.');
} else {
    pc_vault_verify_pass('Private contact fields encrypt at rest.');
}

$ins = $conn->prepare(
    'INSERT INTO private_contacts (company_id, employee_id, first_name, last_name, email1_value, phone1_value, organization_name, labels, notes, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
);
$ins->bind_param(
    'iisssssss',
    $companyId,
    $employeeId,
    $stored['first_name'],
    $stored['last_name'],
    $stored['email1_value'],
    $stored['phone1_value'],
    $stored['organization_name'],
    $stored['labels'],
    $stored['notes']
);
if (!$ins->execute()) {
    pc_vault_verify_fail('Could not insert encrypted private contact row: ' . $ins->error);
    itm_script_test_employee_delete($conn, $employeeId);
    itm_script_output_end();
    exit(1);
}
$contactId = (int)$ins->insert_id;
$ins->close();

$sel = $conn->prepare('SELECT * FROM private_contacts WHERE id = ? AND employee_id = ? LIMIT 1');
$sel->bind_param('ii', $contactId, $employeeId);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
$sel->close();

pc_hydrate_contact_row($row);
if (($row['first_name'] ?? '') !== 'Vault' || ($row['phone1_value'] ?? '') !== '5550100') {
    pc_vault_verify_fail('Hydrated private contact did not decrypt expected values.');
} else {
    pc_vault_verify_pass('Private contact fields decrypt when vault is unlocked.');
}

unset($_SESSION['vault_key']);
$lockedRow = $row;
foreach (pc_vault_encrypted_field_names() as $field) {
    if (array_key_exists($field, $lockedRow)) {
        $lockedRow[$field] = $stored[$field];
    }
}
pc_hydrate_contact_row($lockedRow);
if (($lockedRow['first_name'] ?? '') !== '' || empty($lockedRow['first_name_locked'])) {
    pc_vault_verify_fail('Locked vault should hide private contact fields.');
} else {
    pc_vault_verify_pass('Locked vault hides private contact list fields.');
}

mysqli_query($conn, 'DELETE FROM private_contacts WHERE id = ' . (int)$contactId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo $nl . colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . itm_script_format_status_line('[PASS] All private contacts vault checks passed.') . $nl;
itm_script_output_end();
exit(0);
