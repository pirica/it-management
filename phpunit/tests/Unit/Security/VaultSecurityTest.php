<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use mysqli;

class VaultSecurityTest extends TestCase
{
    private $conn;
    private $testUserId;

    protected function setUp(): void
    {
        if (getenv('ITM_SKIP_DB_TESTS') === '1') {
            $this->markTestSkipped('Database tests are skipped.');
        }

        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../config/config.php';

        $this->conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$this->conn) {
            $this->markTestSkipped('Database connection failed.');
        }

        // Create test user
        $username = 'test_vault_' . uniqid();
        $pass = password_hash('pass', PASSWORD_DEFAULT);
        $vault_hash = password_hash('old_master', PASSWORD_DEFAULT);

        mysqli_query($this->conn, "INSERT INTO employees (company_id, username, password, vault_key_hash, work_email, active) VALUES (1, '$username', '$pass', '$vault_hash', '$username@example.com', 1)");
        $this->testUserId = mysqli_insert_id($this->conn);
    }

    protected function tearDown(): void
    {
        if ($this->conn && $this->testUserId) {
            mysqli_query($this->conn, "DELETE FROM password_entries WHERE employee_id = $this->testUserId");
            mysqli_query($this->conn, "DELETE FROM employees WHERE id = $this->testUserId");
            mysqli_close($this->conn);
        }
    }

    public function testVaultReEncryptionIsAtomic()
    {
        $old_key = hash('sha256', 'old_master');
        $new_key = hash('sha256', 'new_master');

        // 1. Add entries
        $e1_plain = 'secret1';
        $e1_enc = itm_encrypt($e1_plain, $old_key);
        mysqli_query($this->conn, "INSERT INTO password_entries (employee_id, account, password) VALUES ($this->testUserId, 'Acc 1', '$e1_enc')");
        $e1_id = mysqli_insert_id($this->conn);

        // 2. Simulate the re-encryption logic with a failure
        mysqli_begin_transaction($this->conn);
        $transaction_started = true;

        // Success for first entry
        $res = mysqli_query($this->conn, "SELECT password FROM password_entries WHERE id = $e1_id");
        $row = mysqli_fetch_assoc($res);
        $decrypted = itm_decrypt($row['password'], $old_key);
        $re_encrypted = itm_encrypt($decrypted, $new_key);

        $upd_stmt = mysqli_prepare($this->conn, "UPDATE password_entries SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, 'si', $re_encrypted, $e1_id);
        mysqli_stmt_execute($upd_stmt);
        mysqli_stmt_close($upd_stmt);

        // Simulate failure before committing and before updating employee vault_key_hash
        mysqli_rollback($this->conn);

        // 3. Verify that the entry is still encrypted with the OLD key
        $res = mysqli_query($this->conn, "SELECT password FROM password_entries WHERE id = $e1_id");
        $row = mysqli_fetch_assoc($res);
        $current_enc = $row['password'];

        $this->assertEquals($e1_plain, itm_decrypt($current_enc, $old_key), "Data should be decryptable with OLD key after rollback.");
        $this->assertFalse(itm_decrypt($current_enc, $new_key), "Data should NOT be decryptable with NEW key after rollback.");
    }
}
