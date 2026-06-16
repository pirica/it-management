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

        // 2. Create a blocker (e.g., in employee_assignment_history if not cascaded, or any other FK)
        // For this test, we can use the fact that employee_system_access is deleted FIRST.
        // If we make employee deletion fail, employee_system_access should be rolled back.
        
        // We'll use a manual blocker by adding a row to a table that references employees and DOES NOT have ON DELETE CASCADE
        // Looking at database.sql, employees_ibfk_9 (reports_to) is ON DELETE SET NULL, but others might block.
        // Let's use equipment.created_by if it existed, or similar.
        // Actually, employee_onboarding_requests.employee_id is ON DELETE SET NULL.
        // Let's use a custom table or just simulate failure if possible.
        // A better way is to use a real FK. approvers_ibfk_employee references employees ON DELETE RESTRICT.
        
        mysqli_query($this->conn, "INSERT INTO departments (company_id, name) VALUES ({$this->companyId}, 'Test Dept')");
        $deptId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO employee_positions (company_id, department_id, name) VALUES ({$this->companyId}, $deptId, 'Test Pos')");
        $posId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO approver_type (company_id, approver_type_description) VALUES ({$this->companyId}, 'Test Type')");
        $appTypeId = mysqli_insert_id($this->conn);
        
        mysqli_query($this->conn, "INSERT INTO approvers (company_id, employee_id, employee_position_id, department_id, approver_type_id) VALUES ({$this->companyId}, $employeeId, $posId, $deptId, $appTypeId)");

        // 3. Clear (should fail and rollback)
        $error = employees_clear_table_for_company($this->conn, $this->companyId);
        $this->assertNotNull($error, "Clear table should fail due to FK constraint");

        // 4. Verify rollback
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employee_system_access WHERE company_id = {$this->companyId}");
        $this->assertEquals(1, (int)mysqli_fetch_assoc($res)['count'], "System access should be restored after rollback");
        
        // Cleanup blocker
        mysqli_query($this->conn, "DELETE FROM approvers WHERE company_id = {$this->companyId}");
    }
}
