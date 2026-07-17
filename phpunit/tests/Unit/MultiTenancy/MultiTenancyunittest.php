<?php
use PHPUnit\Framework\TestCase;

/**
 * Multi-tenancy scoping tests.
 */
class MultiTenancyUnittest extends TestCase
{
    use ItmPhpunitTestSessionTrait;

    protected $conn;

    protected function setUp(): void
    {
        global $conn;
        if (itm_tests_should_skip_db()) {
            $this->markTestSkipped('Database connection unavailable.');
        }
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
        $this->conn = $conn;
    }

    protected function tearDown(): void
    {
        $this->itmPhpunitEndTestSession();
    }

    /**
     * Test that session variables for auditing are correctly set.
     */
    public function testAuditSessionVariables()
    {
        $actor = $this->itmPhpunitBeginTestSession($this->conn, 1, true, 'audit-session-vars');
        $employeeId = (int)$actor['id'];

        mysqli_query($this->conn, 'SET @app_employee_id = ' . $employeeId);
        mysqli_query($this->conn, 'SET @app_company_id = 1');

        $res = mysqli_query($this->conn, 'SELECT @app_employee_id as employee_id, @app_company_id as company_id');
        $row = mysqli_fetch_assoc($res);

        $this->assertEquals($employeeId, (int)$row['employee_id']);
        $this->assertEquals(1, (int)$row['company_id']);
    }
}
