<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class VaultSecurityTest extends TestCase
{
    private $conn;
    private $testUserId = 0;

    protected function setUp(): void
    {
        if (getenv('ITM_SKIP_DB_TESTS') === '1') {
            $this->markTestSkipped('Database tests are skipped.');
        }

        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }

        require_once __DIR__ . '/../../../../config/config.php';
        require_once ROOT_PATH . 'includes/itm_vault_master_key.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';

        $this->conn = $GLOBALS['conn'] ?? null;
        if (!$this->conn) {
            $this->markTestSkipped('Database connection failed.');
        }

        $row = itm_script_test_employee_create($this->conn, 1, ['script_slug' => 'vault-security-test']);
        if (!is_array($row)) {
            $this->fail('Unable to create disposable test user.');
        }

        $this->testUserId = (int)$row['id'];
        $vaultHash = password_hash('old_master', PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($this->conn, 'UPDATE employees SET vault_key_hash = ? WHERE id = ?');
        if (!$stmt) {
            $this->fail('Unable to set vault_key_hash on disposable test user.');
        }
        mysqli_stmt_bind_param($stmt, 'si', $vaultHash, $this->testUserId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            $this->fail('Unable to set vault_key_hash on disposable test user.');
        }
        mysqli_stmt_close($stmt);
    }

    protected function tearDown(): void
    {
        if ($this->conn && $this->testUserId > 0) {
            $delEntries = mysqli_prepare($this->conn, 'DELETE FROM password_entries WHERE employee_id = ?');
            if ($delEntries) {
                mysqli_stmt_bind_param($delEntries, 'i', $this->testUserId);
                mysqli_stmt_execute($delEntries);
                mysqli_stmt_close($delEntries);
            }
            itm_script_test_employee_delete($this->conn, $this->testUserId);
        }
    }

    public function testVaultReEncryptionIsAtomic()
    {
        $oldKey = hash('sha256', 'old_master');
        $newKey = hash('sha256', 'new_master');
        $plain = 'secret1';
        $encrypted = itm_encrypt($plain, $oldKey);

        $insert = mysqli_prepare($this->conn, 'INSERT INTO password_entries (employee_id, account, password) VALUES (?, ?, ?)');
        $this->assertNotFalse($insert);
        $account = 'Acc 1';
        mysqli_stmt_bind_param($insert, 'iss', $this->testUserId, $account, $encrypted);
        $this->assertTrue(mysqli_stmt_execute($insert));
        $entryId = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($insert);

        mysqli_begin_transaction($this->conn);
        $result = itm_vault_reencrypt_password_entries($this->conn, $this->testUserId, $oldKey, $newKey);
        $this->assertTrue($result['ok'], $result['message'] ?? 'Re-encryption failed unexpectedly.');
        mysqli_rollback($this->conn);

        $select = mysqli_prepare($this->conn, 'SELECT password FROM password_entries WHERE id = ? AND employee_id = ?');
        $this->assertNotFalse($select);
        mysqli_stmt_bind_param($select, 'ii', $entryId, $this->testUserId);
        $this->assertTrue(mysqli_stmt_execute($select));
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($select));
        mysqli_stmt_close($select);
        $this->assertIsArray($row);

        $currentEnc = (string)$row['password'];
        $this->assertSame($plain, itm_decrypt($currentEnc, $oldKey), 'Data should decrypt with the old key after rollback.');
        $this->assertFalse(itm_decrypt($currentEnc, $newKey), 'Data should not decrypt with the new key after rollback.');
    }
}
