<?php

namespace Tests\Unit\Modules\Employees;

use PHPUnit\Framework\TestCase;

class EmployeesBespokeTest extends TestCase
{
    private $conn;
    private $companyId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        require_once ROOT_PATH . 'modules/employees/delete_functions.php';
        require_once ROOT_PATH . 'modules/employees/delete_clear_table.php';

        // Create a temporary company
        mysqli_query($this->conn, "SET @app_company_id = 1;");
        mysqli_query($this->conn, "INSERT INTO companies (company, active) VALUES ('Test Company Bespoke Employees', 1)");
        $this->companyId = mysqli_insert_id($this->conn);
    }

    protected function tearDown(): void
    {
        if ($this->companyId) {
            mysqli_query($this->conn, "DELETE FROM employees WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM employee_statuses WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM companies WHERE id = {$this->companyId}");
        }
    }

    public function testClearTableTransactional()
    {
        // 1. Seed
        mysqli_query($this->conn, "INSERT INTO employee_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
        $statusId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO employees (company_id, first_name, last_name, employment_status_id) VALUES ({$this->companyId}, 'Test', 'User', $statusId)");
        $employeeId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO employee_system_access (company_id, employee_id) VALUES ({$this->companyId}, $employeeId)");

        // 2. Clear
        $error = employees_clear_table_for_company($this->conn, $this->companyId);
        $this->assertNull($error, "Clear table should succeed: " . (string)$error);

        // 3. Verify
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employees WHERE company_id = {$this->companyId}");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employee_system_access WHERE company_id = {$this->companyId}");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);
    }

    public function testClearTableRollback()
    {
        // 1. Seed
        mysqli_query($this->conn, "INSERT INTO employee_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
        $statusId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO employees (company_id, first_name, last_name, employment_status_id) VALUES ({$this->companyId}, 'Test', 'User', $statusId)");
        $employeeId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO employee_system_access (company_id, employee_id) VALUES ({$this->companyId}, $employeeId)");

        // 2. Create a blocker.
        // We need a table that references employees and is NOT cleared by itm_employees_detach_delete_dependencies.
        // forecast_revisions.submitted_by is a good candidate.
        
        mysqli_query($this->conn, "INSERT INTO departments (company_id, name) VALUES ({$this->companyId}, 'Test Dept')");
        $deptId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO cost_centers (company_id, department_id, name) VALUES ({$this->companyId}, $deptId, 'Test CC')");
        $ccId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO budget_categories (company_id, name) VALUES ({$this->companyId}, 'Test Cat')");
        $catId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO gl_accounts (company_id, account_code, account_name, category_id) VALUES ({$this->companyId}, 'TEST', 'Test Acc', $catId)");
        $glId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO forecast_revisions_status (company_id, status) VALUES ({$this->companyId}, 'Test Status')");
        $statusStatusId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO forecast_revisions (company_id, cost_center_id, gl_account_id, year, month, forecast_amount, status, submitted_by) VALUES ({$this->companyId}, $ccId, $glId, 2026, 1, 100.00, $statusStatusId, $employeeId)");

        // 3. Clear (should fail and rollback)
        $error = employees_clear_table_for_company($this->conn, $this->companyId);
        $this->assertNotNull($error, "Clear table should fail due to FK constraint");

        // 4. Verify rollback
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employee_system_access WHERE company_id = {$this->companyId}");
        $this->assertEquals(1, (int)mysqli_fetch_assoc($res)['count'], "System access should be restored after rollback");
        
        // Cleanup blocker
        mysqli_query($this->conn, "DELETE FROM forecast_revisions WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM gl_accounts WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM budget_categories WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM cost_centers WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM departments WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM forecast_revisions_status WHERE company_id = {$this->companyId}");
    }
}
