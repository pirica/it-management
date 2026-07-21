<?php
use PHPUnit\Framework\TestCase;

class SafeImportTest extends TestCase
{
    use ItmModuleIsolatedTestTrait;

    private $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;

        if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);

        if (!$this->conn instanceof mysqli) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $_SESSION['company_id'] = 1;
        $_SESSION['employee_id'] = 1;
        mysqli_query($this->conn, 'SET @app_company_id = 1');
        mysqli_query($this->conn, 'SET @app_employee_id = 1');
    }

    public function testEmployeeImportDoesNotDeleteMissingRecords()
    {
        $companyId = 1;
        $keepEmail = 'keep@example.com';
        $otherEmail = 'other@example.com';

        // Seed only the rows this test owns (avoid counting protected seed employees).
        mysqli_query($this->conn, "DELETE FROM employees WHERE company_id = $companyId AND work_email IN ('$keepEmail', '$otherEmail')");
        $stmt = mysqli_prepare($this->conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate) VALUES (?, 'Keep', 'Me', 'Keep Me', ?, 1, 0)");
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $keepEmail);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $stmt2 = mysqli_prepare($this->conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate) VALUES (?, 'Other', 'Record', 'Other Record', ?, 1, 0)");
        mysqli_stmt_bind_param($stmt2, 'is', $companyId, $otherEmail);
        $this->assertTrue(mysqli_stmt_execute($stmt2), mysqli_stmt_error($stmt2));
        mysqli_stmt_close($stmt2);

        $stmtSeedCount = mysqli_prepare($this->conn, "SELECT COUNT(*) as c FROM employees WHERE company_id = ? AND work_email IN (?, ?)");
        mysqli_stmt_bind_param($stmtSeedCount, 'iss', $companyId, $keepEmail, $otherEmail);
        mysqli_stmt_execute($stmtSeedCount);
        $seedCount = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSeedCount))['c'];
        mysqli_stmt_close($stmtSeedCount);
        $this->assertEquals(2, $seedCount, 'Expected two seeded employees before import.');

        $importData = [
            ['First Name', 'Last Name', 'Work Email'],
            ['Keep', 'Me', 'keep@example.com']
        ];

        $this->runIsolatedModule(
            ROOT_PATH . 'modules/employees/index.php',
            [
                'employee_id' => 1,
                'company_id' => $companyId,
                'csrf_token' => 'test_token',
            ],
            [
                'csrf_token' => 'test_token',
                'action' => 'import_employees',
                'import_payload' => json_encode($importData),
            ],
            [],
            ['REQUEST_METHOD' => 'POST'],
            ['company_id' => $companyId]
        );

        $stmt3 = mysqli_prepare($this->conn, "SELECT COUNT(*) as c FROM employees WHERE company_id = ? AND work_email IN (?, ?)");
        mysqli_stmt_bind_param($stmt3, 'iss', $companyId, $keepEmail, $otherEmail);
        mysqli_stmt_execute($stmt3);
        $countRes = mysqli_stmt_get_result($stmt3);
        $count = mysqli_fetch_assoc($countRes)['c'];
        mysqli_stmt_close($stmt3);

        $this->assertEquals(2, $count, "Import should not have deleted the record missing from payload");

        mysqli_query($this->conn, "DELETE FROM employees WHERE company_id = $companyId AND work_email IN ('$keepEmail', '$otherEmail')");
    }
}
