<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
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
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';

        $this->conn = $GLOBALS['conn'] ?? null;
        if (!$this->conn) {
            $this->markTestSkipped('Database connection failed.');
        }

        $row = itm_script_test_employee_create($this->conn, 1, ['script_slug' => 'totp-test']);
        if (!is_array($row)) {
            $this->fail('Unable to create disposable test user.');
        }

        $this->testUserId = (int)$row['id'];
    }

    protected function tearDown(): void
    {
        if ($this->conn && $this->testUserId > 0) {
            itm_script_test_employee_delete($this->conn, $this->testUserId);
        }
    }

    public function testCreateSecretHasExpectedLength()
    {
        $secret = itm_totp_create_setup_secret(16);
        $this->assertSame(16, strlen($secret));
    }

    public function testVerifyCodeAcceptsMatchingSixDigitCode()
    {
        $secret = itm_totp_create_setup_secret();
        $code = itm_totp_instance()->getCode($secret);
        $this->assertTrue(itm_totp_verify_plain_secret($secret, $code));
    }

    public function testEncryptDecryptRoundTrip()
    {
        $plain = itm_totp_create_setup_secret();
        $encrypted = itm_totp_encrypt_secret($plain);
        $this->assertNotSame('', (string)$encrypted);
        $this->assertSame($plain, itm_totp_decrypt_secret($encrypted));
    }

    public function testEmployeeRowVerificationFlow()
    {
        $plain = itm_totp_create_setup_secret();
        $encrypted = itm_totp_encrypt_secret($plain);
        $enabled = 1;
        $stmt = mysqli_prepare($this->conn, 'UPDATE employees SET totp_secret = ?, totp_enabled = ? WHERE id = ?');
        $this->assertNotFalse($stmt);
        mysqli_stmt_bind_param($stmt, 'sii', $encrypted, $enabled, $this->testUserId);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $select = mysqli_prepare($this->conn, 'SELECT totp_secret, totp_enabled FROM employees WHERE id = ? LIMIT 1');
        $this->assertNotFalse($select);
        mysqli_stmt_bind_param($select, 'i', $this->testUserId);
        $this->assertTrue(mysqli_stmt_execute($select));
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($select));
        mysqli_stmt_close($select);
        $this->assertIsArray($row);
        $this->assertTrue(itm_totp_employee_has_enabled($row));

        $code = itm_totp_instance()->getCode($plain);
        $this->assertTrue(itm_totp_verify_employee_code($row, $code));
        $this->assertFalse(itm_totp_verify_employee_code($row, '000000'));
    }

    public function testRequireValidCodeSkipsWhenDisabled()
    {
        $row = ['totp_enabled' => 0, 'totp_secret' => null];
        $result = itm_totp_require_valid_code_or_error($row, '');
        $this->assertTrue($result['ok']);
    }
}
