<?php
/**
 * Vault org recovery regression checks.
 *
 * CLI: php scripts/verify_vault_org_recovery.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_vault_org_recovery.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_vault_org_recovery.php</code>, <code>modules/vault_org_recovery/</code>, company policy fields, or <code>user-config.php</code> consent/escrow hooks.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Vault Org Recovery Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;

function vor_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vor_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function vor_verify_audit_triggers(mysqli $conn, $table)
{
    $safeTable = mysqli_real_escape_string($conn, (string)$table);
    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND EVENT_OBJECT_TABLE = '{$safeTable}'
           AND TRIGGER_NAME LIKE 'trg\\_%\\_audit\\_%'"
    );
    $count = $res ? (int)(mysqli_fetch_assoc($res)['c'] ?? 0) : 0;
    if ($count < 3) {
        vor_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
        return;
    }
    vor_verify_pass("Audit triggers present for {$table}");
}

function vor_verify_column_exists(mysqli $conn, $table, $column)
{
    $safeTable = mysqli_real_escape_string($conn, (string)$table);
    $safeColumn = mysqli_real_escape_string($conn, (string)$column);
    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'"
    );
    $exists = $res && (int)(mysqli_fetch_assoc($res)['c'] ?? 0) > 0;
    if (!$exists) {
        vor_verify_fail("Missing column {$table}.{$column}");
        return false;
    }
    vor_verify_pass("Column {$table}.{$column} exists");
    return true;
}

vor_verify_column_exists($conn, 'companies', 'vault_org_recovery_enabled');
vor_verify_column_exists($conn, 'companies', 'vault_org_recovery_passphrase_hash');
vor_verify_column_exists($conn, 'companies', 'vault_org_recovery_escrow_key_encrypted');
vor_verify_column_exists($conn, 'employees', 'vault_org_recovery_consent_at');
vor_verify_column_exists($conn, 'employees', 'vault_key_escrow_encrypted');

$res = mysqli_query($conn, "SHOW TABLES LIKE 'vault_org_recovery_requests'");
if (!$res || mysqli_num_rows($res) < 1) {
    vor_verify_fail('Table vault_org_recovery_requests missing');
} else {
    vor_verify_pass('Table vault_org_recovery_requests exists');
}
vor_verify_audit_triggers($conn, 'vault_org_recovery_requests');

$passphrase = 'VorTest-' . bin2hex(random_bytes(4));
$hash = itm_vault_org_recovery_hash_admin_passphrase($passphrase);
if ($hash === null || !itm_vault_org_recovery_verify_admin_passphrase($passphrase, $hash)) {
    vor_verify_fail('Passphrase hash/verify round-trip');
} else {
    vor_verify_pass('Passphrase hash/verify round-trip');
}

$rawEscrow = itm_vault_org_recovery_generate_escrow_key();
$encryptedCompanyEscrow = itm_vault_org_recovery_encrypt_escrow_key($rawEscrow, $companyId);
$decryptedCompanyEscrow = itm_vault_org_recovery_decrypt_escrow_key($encryptedCompanyEscrow, $companyId);
if ($decryptedCompanyEscrow !== $rawEscrow) {
    vor_verify_fail('Company escrow key encrypt/decrypt round-trip');
} else {
    vor_verify_pass('Company escrow key encrypt/decrypt round-trip');
}

$companyRow = [
    'id' => $companyId,
    'vault_org_recovery_enabled' => 1,
    'vault_org_recovery_escrow_key_encrypted' => $encryptedCompanyEscrow,
];
$masterKey = 'TestMasterKey-' . bin2hex(random_bytes(6));
$employeeEscrow = itm_vault_org_recovery_build_employee_escrow($masterKey, $companyRow);
$recovered = itm_vault_org_recovery_decrypt_employee_escrow($employeeEscrow, $companyRow);
if ($recovered !== $masterKey) {
    vor_verify_fail('Employee escrow build/decrypt round-trip');
} else {
    vor_verify_pass('Employee escrow build/decrypt round-trip');
}

$testEmployee = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify_vault_org_recovery']);
if (empty($testEmployee['id'])) {
    vor_verify_fail('Could not create disposable test employee');
} else {
    $employeeId = (int)$testEmployee['id'];
    vor_verify_pass('Disposable test employee created');

    $vkHash = password_hash('vault-test-key', PASSWORD_DEFAULT);
    $stmtVk = mysqli_prepare($conn, 'UPDATE employees SET vault_key_hash = ? WHERE id = ? AND company_id = ?');
    if ($stmtVk) {
        mysqli_stmt_bind_param($stmtVk, 'sii', $vkHash, $employeeId, $companyId);
        mysqli_stmt_execute($stmtVk);
        mysqli_stmt_close($stmtVk);
    }

    $stmtCo = mysqli_prepare(
        $conn,
        'UPDATE companies SET vault_org_recovery_enabled = 1, vault_org_recovery_passphrase_hash = ?, vault_org_recovery_escrow_key_encrypted = ? WHERE id = ?'
    );
    if ($stmtCo) {
        mysqli_stmt_bind_param($stmtCo, 'ssi', $hash, $encryptedCompanyEscrow, $companyId);
        mysqli_stmt_execute($stmtCo);
        mysqli_stmt_close($stmtCo);
    }

    $_SESSION['employee_id'] = 1;
    $_SESSION['company_id'] = $companyId;
    $consent = itm_vault_org_recovery_grant_consent($conn, $employeeId, $companyId, 'HR-POLICY-TEST');
    if (empty($consent['ok'])) {
        vor_verify_fail('Grant consent: ' . ($consent['message'] ?? ''));
    } else {
        vor_verify_pass('Grant consent');
    }

    $sync = itm_vault_org_recovery_sync_employee_escrow($conn, $employeeId, $companyId, $masterKey);
    if (empty($sync['ok'])) {
        vor_verify_fail('Sync employee escrow: ' . ($sync['message'] ?? ''));
    } else {
        vor_verify_pass('Sync employee escrow');
    }

    $create = itm_vault_org_recovery_create_request($conn, $companyId, 1, $employeeId, 'LEGAL-REF-TEST', 'verify script');
    if (empty($create['ok']) || empty($create['request_id'])) {
        vor_verify_fail('Create recovery request: ' . ($create['message'] ?? ''));
    } else {
        vor_verify_pass('Create recovery request');
        $requestId = (int)$create['request_id'];
        $complete = itm_vault_org_recovery_complete_request($conn, $companyId, 1, $requestId, $passphrase, 'completed by verify');
        if (empty($complete['ok']) || ($complete['master_key'] ?? '') !== $masterKey) {
            vor_verify_fail('Complete recovery request: ' . ($complete['message'] ?? ''));
        } else {
            vor_verify_pass('Complete recovery request returns master key');
        }
    }

    itm_script_test_employee_delete($conn, $employeeId);
    vor_verify_pass('Disposable test employee deleted');
}

itm_script_output_end($failures === 0 ? 0 : 1);
