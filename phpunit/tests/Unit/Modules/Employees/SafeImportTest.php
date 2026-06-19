<?php
use PHPUnit\Framework\TestCase;

class SafeImportTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;

        if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
    }

    public function testEmployeeImportDoesNotDeleteMissingRecords()
    {
        $companyId = 1;

        // Seed
        mysqli_query($this->conn, "DELETE FROM employees WHERE company_id = $companyId");
        $stmt = mysqli_prepare($this->conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate) VALUES (?, 'Keep', 'Me', 'Keep Me', 'keep@example.com', 1, 0)");
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);

        $stmt2 = mysqli_prepare($this->conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate) VALUES (?, 'Other', 'Record', 'Other Record', 'other@example.com', 1, 0)");
        mysqli_stmt_bind_param($stmt2, 'i', $companyId);
        mysqli_stmt_execute($stmt2);

        // Import only 'Keep Me'
        $_SESSION['employee_id'] = 1;
        $_SESSION['company_id'] = $companyId;
        global $company_id;
        $company_id = $companyId;
        $_SESSION['csrf_token'] = 'test_token';
        $_POST['csrf_token'] = 'test_token';
        $_POST['action'] = 'import_employees';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $importData = [
            ['First Name', 'Last Name', 'Work Email'],
            ['Keep', 'Me', 'keep@example.com']
        ];
        $_POST['import_payload'] = json_encode($importData);

        $oldDir = getcwd();
        chdir(ROOT_PATH . 'modules/employees');
        ob_start();
        global $conn;
        include 'index.php';
        ob_end_clean();
        chdir($oldDir);

        $stmt3 = mysqli_prepare($this->conn, "SELECT COUNT(*) as c FROM employees WHERE company_id = ?");
        mysqli_stmt_bind_param($stmt3, 'i', $companyId);
        mysqli_stmt_execute($stmt3);
        $countRes = mysqli_stmt_get_result($stmt3);
        $count = mysqli_fetch_assoc($countRes)['c'];

        $this->assertEquals(2, $count, "Import should not have deleted the record missing from payload");
    }
}
