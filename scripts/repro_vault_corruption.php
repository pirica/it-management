<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

/**
 * Verification script for Vault Atomicity.
 */

echo "--- Starting Vault Atomicity Verification ---\n";

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) { die("Connection failed\n"); }

// 1. Create test user
$username = 'fix_test_' . time();
$password_hash = password_hash('system_pass', PASSWORD_DEFAULT);
$master_key = 'old_master';
$vault_key_hash = password_hash($master_key, PASSWORD_DEFAULT);
$old_key_session = hash('sha256', $master_key);

// Using standard columns discovered via DESCRIBE
$sql = "INSERT INTO employees (company_id, first_name, last_name, username, password, vault_key_hash, work_email, active, employment_status_id)
        VALUES (1, 'Fix', 'Test', '$username', '$password_hash', '$vault_key_hash', '$username@example.com', 1, 1)";
if (!mysqli_query($conn, $sql)) {
    echo "Error creating user: " . mysqli_error($conn) . "\n";
    // Try without optional columns if it fails
    $sql = "INSERT INTO employees (company_id, first_name, last_name, username, vault_key_hash, work_email, employment_status_id)
            VALUES (1, 'Fix', 'Test', '$username', '$vault_key_hash', '$username@example.com', 1)";
    if (!mysqli_query($conn, $sql)) {
         die("Fatal: Could not create user even with minimal columns: " . mysqli_error($conn) . "\n");
    }
}
$user_id = mysqli_insert_id($conn);
echo "Test user created (ID: $user_id)\n";

// 2. Add two password entries
$entry1_plain = 'secret1';
$entry2_plain = 'secret2';
$entry1_enc = itm_encrypt($entry1_plain, $old_key_session);
$entry2_enc = itm_encrypt($entry2_plain, $old_key_session);

if (!mysqli_query($conn, "INSERT INTO password_entries (employee_id, account, password) VALUES ($user_id, 'Account 1', '$entry1_enc')")) {
    die("Error inserting entry 1: " . mysqli_error($conn) . "\n");
}
$entry1_id = mysqli_insert_id($conn);

if (!mysqli_query($conn, "INSERT INTO password_entries (employee_id, account, password) VALUES ($user_id, 'Account 2', '$entry2_enc')")) {
    die("Error inserting entry 2: " . mysqli_error($conn) . "\n");
}
$entry2_id = mysqli_insert_id($conn);

echo "Two password entries added.\n";

// 3. Simulate FIXED Master Key Change logic with a forced rollback
$new_master_key = 'new_master';
$new_key_session = hash('sha256', $new_master_key);

echo "Simulating master key change with transaction and FORCED ROLLBACK...\n";

mysqli_begin_transaction($conn);

$res = mysqli_query($conn, "SELECT id, password FROM password_entries WHERE employee_id = $user_id");
if (!$res) {
    die("Error selecting entries: " . mysqli_error($conn) . "\n");
}

$upd_stmt = mysqli_prepare($conn, "UPDATE password_entries SET password = ? WHERE id = ?");
if (!$upd_stmt) {
    die("Error preparing update: " . mysqli_error($conn) . "\n");
}

while ($row = mysqli_fetch_assoc($res)) {
    $decrypted = itm_decrypt($row['password'], $old_key_session);
    $re_encrypted = itm_encrypt($decrypted, $new_key_session);
    mysqli_stmt_bind_param($upd_stmt, 'si', $re_encrypted, $row['id']);
    mysqli_stmt_execute($upd_stmt);
    echo "Entry {$row['id']} re-encrypted in transaction.\n";
}
mysqli_stmt_close($upd_stmt);

echo "FORCING ROLLBACK (simulating failure before employee update)...\n";
mysqli_rollback($conn);

// 4. Verify No Corruption
echo "\n--- Verification ---\n";

$res = mysqli_query($conn, "SELECT id, account, password FROM password_entries WHERE employee_id = $user_id");
if (!$res) {
    die("Error selecting entries during verification: " . mysqli_error($conn) . "\n");
}

while ($row = mysqli_fetch_assoc($res)) {
    $attempt_old = itm_decrypt($row['password'], $old_key_session);
    $attempt_new = itm_decrypt($row['password'], $new_key_session);

    echo "Entry '{$row['account']}':\n";
    echo "  Decrypt with OLD key: " . ($attempt_old === false ? "FAILED" : "SUCCESS ('$attempt_old')") . "\n";
    echo "  Decrypt with NEW key: " . ($attempt_new === false ? "FAILED" : "SUCCESS") . "\n";
}

$res = mysqli_query($conn, "SELECT vault_key_hash FROM employees WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($res);
$status = password_verify($master_key, $user_data['vault_key_hash'] ?? '') ? "MATCH (OLD)" : "MISMATCH";
echo "User's Master Key in DB: $status\n";

echo "\nCONCLUSION:\n";
$res = mysqli_query($conn, "SELECT password FROM password_entries WHERE id = $entry1_id");
$row = mysqli_fetch_assoc($res);
if ($row && itm_decrypt($row['password'], $old_key_session) === $entry1_plain) {
    echo "SUCCESS: Vault data remains consistent after failed change.\n";
} else {
    echo "FAILURE: Vault data is corrupted or missing!\n";
    if (!$row) echo "Reason: Entry 1 not found in DB!\n";
}

// Cleanup
mysqli_query($conn, "DELETE FROM password_entries WHERE employee_id = $user_id");
mysqli_query($conn, "DELETE FROM employees WHERE id = $user_id");
mysqli_close($conn);

echo "--- Verification Finished ---\n";
