<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_master_key.php';
require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * Verification script for vault master key atomicity.
 * Simulates a failed master key change and confirms rollback leaves entries readable with the old key.
 */

itm_script_output_begin('Vault Atomicity Verification');
$nl = itm_script_output_nl();

/**
 * @param string $message
 * @return never
 */
function itm_repro_vault_corruption_fail($message, $nl, $conn = null, $userId = 0)
{
    if ($conn instanceof mysqli && $userId > 0) {
        $delEntries = mysqli_prepare($conn, 'DELETE FROM password_entries WHERE employee_id = ?');
        if ($delEntries) {
            mysqli_stmt_bind_param($delEntries, 'i', $userId);
            mysqli_stmt_execute($delEntries);
            mysqli_stmt_close($delEntries);
        }
        itm_script_test_employee_delete($conn, $userId);
    }
    echo colorText((string)$message, 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo '--- Starting Vault Atomicity Verification ---' . $nl;

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_repro_vault_corruption_fail('Database connection failed.', $nl);
}

$employee = itm_script_test_employee_create($conn, 1, ['script_slug' => 'repro-vault-corruption']);
if (!is_array($employee)) {
    itm_repro_vault_corruption_fail('Unable to create disposable test user.', $nl);
}

$userId = (int)$employee['id'];
$companyId = (int)($employee['company_id'] ?? 0);
if ($companyId <= 0) {
    itm_repro_vault_corruption_fail('Disposable test user is missing company_id.', $nl, $conn, $userId);
}

$masterKey = 'old_master';
$vaultKeyHash = password_hash($masterKey, PASSWORD_DEFAULT);
$oldKeySession = hash('sha256', $masterKey);

$hashStmt = mysqli_prepare($conn, 'UPDATE employees SET vault_key_hash = ? WHERE id = ?');
if (!$hashStmt) {
    itm_repro_vault_corruption_fail('Unable to prepare vault_key_hash update: ' . mysqli_error($conn), $nl, $conn, $userId);
}
mysqli_stmt_bind_param($hashStmt, 'si', $vaultKeyHash, $userId);
if (!mysqli_stmt_execute($hashStmt)) {
    $err = mysqli_stmt_error($hashStmt) ?: mysqli_error($conn);
    mysqli_stmt_close($hashStmt);
    itm_repro_vault_corruption_fail('Unable to set vault_key_hash: ' . $err, $nl, $conn, $userId);
}
mysqli_stmt_close($hashStmt);

echo 'Test user created (ID: ' . $userId . ')' . $nl;

$entries = [
    ['Account 1', 'secret1'],
    ['Account 2', 'secret2'],
];
$entryIds = [];

foreach ($entries as $entry) {
    $encrypted = itm_encrypt($entry[1], $oldKeySession);
    // Why: password_entries.company_id is NOT NULL; omitting it fails silently in some mysqli builds.
    $insert = mysqli_prepare($conn, 'INSERT INTO password_entries (company_id, employee_id, account, password) VALUES (?, ?, ?, ?)');
    if (!$insert) {
        itm_repro_vault_corruption_fail('Error preparing entry insert: ' . mysqli_error($conn), $nl, $conn, $userId);
    }
    mysqli_stmt_bind_param($insert, 'iiss', $companyId, $userId, $entry[0], $encrypted);
    if (!mysqli_stmt_execute($insert)) {
        $err = mysqli_stmt_error($insert) ?: mysqli_error($conn);
        mysqli_stmt_close($insert);
        itm_repro_vault_corruption_fail('Error inserting entry: ' . $err, $nl, $conn, $userId);
    }
    $entryIds[] = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($insert);
}

echo 'Two password entries added.' . $nl;

$newMasterKey = 'new_master';
$newKeySession = hash('sha256', $newMasterKey);

echo 'Simulating master key change with transaction and forced rollback...' . $nl;

mysqli_begin_transaction($conn);
$result = itm_vault_reencrypt_password_entries($conn, $userId, $oldKeySession, $newKeySession);
if (empty($result['ok'])) {
    mysqli_rollback($conn);
    itm_repro_vault_corruption_fail('Re-encryption failed: ' . ($result['message'] ?? 'unknown error'), $nl, $conn, $userId);
}

echo 'FORCING ROLLBACK (simulating failure before employee update)...' . $nl;
mysqli_rollback($conn);

echo $nl . '--- Verification ---' . $nl;

$selectEntries = mysqli_prepare($conn, 'SELECT id, account, password FROM password_entries WHERE employee_id = ? ORDER BY id ASC');
mysqli_stmt_bind_param($selectEntries, 'i', $userId);
mysqli_stmt_execute($selectEntries);
$res = mysqli_stmt_get_result($selectEntries);

while ($row = mysqli_fetch_assoc($res)) {
    $attemptOld = itm_decrypt($row['password'], $oldKeySession);
    $attemptNew = itm_decrypt($row['password'], $newKeySession);

    echo "Entry '{$row['account']}':" . $nl;
    echo '  Decrypt with OLD key: ' . ($attemptOld === false ? 'FAILED' : "SUCCESS ('{$attemptOld}')") . $nl;
    echo '  Decrypt with NEW key: ' . ($attemptNew === false ? 'FAILED' : 'SUCCESS') . $nl;
}
mysqli_stmt_close($selectEntries);

$hashCheck = mysqli_prepare($conn, 'SELECT vault_key_hash FROM employees WHERE id = ?');
mysqli_stmt_bind_param($hashCheck, 'i', $userId);
mysqli_stmt_execute($hashCheck);
$userData = mysqli_fetch_assoc(mysqli_stmt_get_result($hashCheck));
mysqli_stmt_close($hashCheck);

$status = password_verify($masterKey, $userData['vault_key_hash'] ?? '') ? 'MATCH (OLD)' : 'MISMATCH';
echo "User's Master Key in DB: {$status}" . $nl;

echo $nl . 'CONCLUSION:' . $nl;
$verify = mysqli_prepare($conn, 'SELECT password FROM password_entries WHERE id = ? AND employee_id = ?');
$firstEntryId = $entryIds[0] ?? 0;
mysqli_stmt_bind_param($verify, 'ii', $firstEntryId, $userId);
mysqli_stmt_execute($verify);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($verify));
mysqli_stmt_close($verify);

if ($row && itm_decrypt($row['password'], $oldKeySession) === $entries[0][1]) {
    echo colorText('SUCCESS: Vault data remains consistent after failed change.', 'pass') . $nl;
    $exitCode = 0;
} else {
    echo colorText('FAILURE: Vault data is corrupted or missing!', 'fail') . $nl;
    $exitCode = 1;
}

$delEntries = mysqli_prepare($conn, 'DELETE FROM password_entries WHERE employee_id = ?');
mysqli_stmt_bind_param($delEntries, 'i', $userId);
mysqli_stmt_execute($delEntries);
mysqli_stmt_close($delEntries);
itm_script_test_employee_delete($conn, $userId);

echo '--- Verification Finished ---' . $nl;
itm_script_output_end();
exit($exitCode);
