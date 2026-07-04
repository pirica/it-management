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

echo "--- Starting Vault Atomicity Verification ---" . $nl;

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$employee = itm_script_test_employee_create($conn, 1, ['script_slug' => 'repro-vault-corruption']);
if (!is_array($employee)) {
    fwrite(STDERR, "Unable to create disposable test user.\n");
    exit(1);
}

$userId = (int)$employee['id'];
$masterKey = 'old_master';
$vaultKeyHash = password_hash($masterKey, PASSWORD_DEFAULT);
$oldKeySession = hash('sha256', $masterKey);

$hashStmt = mysqli_prepare($conn, 'UPDATE employees SET vault_key_hash = ? WHERE id = ?');
if (!$hashStmt) {
    fwrite(STDERR, "Unable to set vault_key_hash.\n");
    exit(1);
}
mysqli_stmt_bind_param($hashStmt, 'si', $vaultKeyHash, $userId);
if (!mysqli_stmt_execute($hashStmt)) {
    mysqli_stmt_close($hashStmt);
    fwrite(STDERR, "Unable to set vault_key_hash.\n");
    exit(1);
}
mysqli_stmt_close($hashStmt);

echo 'Test user created (ID: ' . $userId . ")\n";

$entries = [
    ['Account 1', 'secret1'],
    ['Account 2', 'secret2'],
];
$entryIds = [];

foreach ($entries as $entry) {
    $encrypted = itm_encrypt($entry[1], $oldKeySession);
    $insert = mysqli_prepare($conn, 'INSERT INTO password_entries (employee_id, account, password) VALUES (?, ?, ?)');
    if (!$insert) {
        fwrite(STDERR, 'Error inserting entry: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    mysqli_stmt_bind_param($insert, 'iss', $userId, $entry[0], $encrypted);
    if (!mysqli_stmt_execute($insert)) {
        mysqli_stmt_close($insert);
        fwrite(STDERR, 'Error inserting entry: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    $entryIds[] = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($insert);
}

echo "Two password entries added.\n";

$newMasterKey = 'new_master';
$newKeySession = hash('sha256', $newMasterKey);

echo "Simulating master key change with transaction and forced rollback...\n";

mysqli_begin_transaction($conn);
$result = itm_vault_reencrypt_password_entries($conn, $userId, $oldKeySession, $newKeySession);
if (empty($result['ok'])) {
    mysqli_rollback($conn);
    fwrite(STDERR, 'Re-encryption failed: ' . ($result['message'] ?? 'unknown error') . "\n");
    exit(1);
}

echo "FORCING ROLLBACK (simulating failure before employee update)...\n";
mysqli_rollback($conn);

echo "\n--- Verification ---\n";

$selectEntries = mysqli_prepare($conn, 'SELECT id, account, password FROM password_entries WHERE employee_id = ? ORDER BY id ASC');
mysqli_stmt_bind_param($selectEntries, 'i', $userId);
mysqli_stmt_execute($selectEntries);
$res = mysqli_stmt_get_result($selectEntries);

while ($row = mysqli_fetch_assoc($res)) {
    $attemptOld = itm_decrypt($row['password'], $oldKeySession);
    $attemptNew = itm_decrypt($row['password'], $newKeySession);

    echo "Entry '{$row['account']}':\n";
    echo '  Decrypt with OLD key: ' . ($attemptOld === false ? 'FAILED' : "SUCCESS ('{$attemptOld}')") . "\n";
    echo '  Decrypt with NEW key: ' . ($attemptNew === false ? 'FAILED' : 'SUCCESS') . "\n";
}
mysqli_stmt_close($selectEntries);

$hashCheck = mysqli_prepare($conn, 'SELECT vault_key_hash FROM employees WHERE id = ?');
mysqli_stmt_bind_param($hashCheck, 'i', $userId);
mysqli_stmt_execute($hashCheck);
$userData = mysqli_fetch_assoc(mysqli_stmt_get_result($hashCheck));
mysqli_stmt_close($hashCheck);

$status = password_verify($masterKey, $userData['vault_key_hash'] ?? '') ? 'MATCH (OLD)' : 'MISMATCH';
echo "User's Master Key in DB: {$status}\n";

echo "\nCONCLUSION:\n";
$verify = mysqli_prepare($conn, 'SELECT password FROM password_entries WHERE id = ? AND employee_id = ?');
$firstEntryId = $entryIds[0] ?? 0;
mysqli_stmt_bind_param($verify, 'ii', $firstEntryId, $userId);
mysqli_stmt_execute($verify);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($verify));
mysqli_stmt_close($verify);

if ($row && itm_decrypt($row['password'], $oldKeySession) === $entries[0][1]) {
    echo colorText("SUCCESS: Vault data remains consistent after failed change.", 'pass') . $nl;
    $exitCode = 0;
} else {
    echo colorText("FAILURE: Vault data is corrupted or missing!", 'fail') . $nl;
    $exitCode = 1;
}

$delEntries = mysqli_prepare($conn, 'DELETE FROM password_entries WHERE employee_id = ?');
mysqli_stmt_bind_param($delEntries, 'i', $userId);
mysqli_stmt_execute($delEntries);
mysqli_stmt_close($delEntries);
itm_script_test_employee_delete($conn, $userId);

echo "--- Verification Finished ---" . $nl;
itm_script_output_end();
exit($exitCode);
