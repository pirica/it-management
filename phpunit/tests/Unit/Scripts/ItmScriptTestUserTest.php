<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ItmScriptTestUserTest extends TestCase
{
    /** @var mysqli|null */
    private $conn;

    /** @var int[] */
    private $createdUserIds = [];

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }

        require_once ROOT_PATH . 'config/config.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';

        global $conn;
        if (!$conn || !($conn instanceof mysqli)) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $this->conn = $conn;
    }

    protected function tearDown(): void
    {
        if ($this->conn instanceof mysqli) {
            foreach ($this->createdUserIds as $employeeId) {
                itm_script_test_employee_delete($this->conn, (int)$employeeId);
            }
        }
        $this->createdUserIds = [];
    }

    public function testUsernameShapeAndDisposableDetector(): void
    {
        $username = itm_script_test_employee_username('repro-audit');
        $this->assertMatchesRegularExpression('/^script-repro-audit-[a-f0-9]{8}$/', $username);
        $this->assertTrue(itm_script_test_employee_is_disposable($username));
        $this->assertFalse(itm_script_test_employee_is_disposable('admin'));
        $this->assertFalse(itm_script_test_employee_is_disposable('script-short'));
    }

    public function testCreateSnapshotRestoreAndDelete(): void
    {
        $row = itm_script_test_employee_create($this->conn, 1, ['script_slug' => 'phpunit-script-user']);
        $this->assertIsArray($row);
        $this->assertGreaterThan(1, (int)$row['id']);
        $this->createdUserIds[] = (int)$row['id'];

        $snapshot = itm_script_test_employee_snapshot($this->conn, (int)$row['id'], [
            'reset_token',
            'reset_token_hash',
            'reset_token_expires_at',
        ]);
        $this->assertArrayHasKey('reset_token', $snapshot);

        $token = 'phpunit-token-' . bin2hex(random_bytes(4));
        $stmt = mysqli_prepare($this->conn, 'UPDATE employees SET reset_token = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $token, $row['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $this->assertTrue(itm_script_test_employee_restore($this->conn, (int)$row['id'], $snapshot));

        $after = itm_script_test_employee_snapshot($this->conn, (int)$row['id'], ['reset_token']);
        $this->assertSame($snapshot['reset_token'], $after['reset_token']);

        $this->assertTrue(itm_script_test_employee_delete($this->conn, (int)$row['id']));
        $this->createdUserIds = array_values(array_diff($this->createdUserIds, [(int)$row['id']]));
    }

    public function testCreateSessionActorUsesAdminRoleWhenRequested(): void
    {
        $row = itm_script_test_employee_create_session_actor($this->conn, 1, [
            'as_admin' => true,
            'script_slug' => 'phpunit-session-actor',
        ]);
        $this->assertIsArray($row);
        $employeeId = (int)$row['id'];
        $this->assertGreaterThan(1, $employeeId);
        $this->createdUserIds[] = $employeeId;

        $this->assertTrue(itm_is_admin($this->conn, $employeeId));
        $this->assertTrue(itm_script_test_employee_is_disposable((string)$row['username']));
    }

    public function testDeleteRefusesNonDisposableUser(): void
    {
        $this->assertFalse(itm_script_test_employee_delete($this->conn, 1));
    }
}
