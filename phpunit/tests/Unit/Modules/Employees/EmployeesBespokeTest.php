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

        // Why: Soft-delete stamps deleted_by from the session actor.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['employee_id'] = 1;

        // Create a temporary company (unique name — companies.company is UNIQUE)
        mysqli_query($this->conn, "SET @app_company_id = 1;");
        mysqli_query($this->conn, "SET @app_employee_id = 1;");
        $companyName = 'Test Company Bespoke Employees ' . bin2hex(random_bytes(4));
        $companyNameEsc = mysqli_real_escape_string($this->conn, $companyName);
        $ins = mysqli_query($this->conn, "INSERT INTO companies (company, active) VALUES ('{$companyNameEsc}', 1)");
        $this->companyId = $ins ? (int)mysqli_insert_id($this->conn) : 0;
        if ($this->companyId <= 0) {
            $this->fail('Failed to create temporary company: ' . mysqli_error($this->conn));
        }
    }

    protected function tearDown(): void
    {
        if ($this->companyId) {
            $cid = (int)$this->companyId;
            mysqli_query($this->conn, "DELETE FROM forecast_revisions WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM gl_accounts WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM budget_categories WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM cost_centers WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM departments WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM forecast_revisions_status WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM employee_system_access WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM employees WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM employee_statuses WHERE company_id = {$cid}");
            mysqli_query($this->conn, "DELETE FROM companies WHERE id = {$cid}");
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

        // 2. Clear (soft-delete + detach)
        $error = employees_clear_table_for_company($this->conn, $this->companyId);
        $this->assertNull($error, "Clear table should succeed: " . (string)$error);

        // 3. Live list is empty; soft-deleted row remains for audit view.
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employees WHERE company_id = {$this->companyId} AND deleted_at IS NULL");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);

        $res = mysqli_query($this->conn, "SELECT active, deleted_at, deleted_by FROM employees WHERE id = {$employeeId} AND company_id = {$this->companyId}");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row, 'Soft-deleted employee row must remain');
        $this->assertEquals(0, (int)$row['active']);
        $this->assertNotEmpty($row['deleted_at']);
        $this->assertEquals(1, (int)$row['deleted_by']);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employee_system_access WHERE company_id = {$this->companyId}");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);
    }

    public function testClearTableSucceedsWithRemainingInboundFk()
    {
        // Why: Soft-delete must succeed when detach cannot clear every inbound FK
        // (forecast_revisions.submitted_by stays). Hard-delete rollback no longer applies.
        mysqli_query($this->conn, "INSERT INTO employee_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
        $statusId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO employees (company_id, first_name, last_name, employment_status_id) VALUES ({$this->companyId}, 'Test', 'User', $statusId)");
        $employeeId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO employee_system_access (company_id, employee_id) VALUES ({$this->companyId}, $employeeId)");

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

        $error = employees_clear_table_for_company($this->conn, $this->companyId);
        $this->assertNull($error, "Clear table should soft-delete even with remaining inbound FK: " . (string)$error);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employees WHERE company_id = {$this->companyId} AND deleted_at IS NULL");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM employee_system_access WHERE company_id = {$this->companyId}");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count'], 'Detach still clears system access');

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM forecast_revisions WHERE company_id = {$this->companyId} AND submitted_by = {$employeeId}");
        $this->assertEquals(1, (int)mysqli_fetch_assoc($res)['count'], 'Inbound FK row may remain pointing at soft-deleted employee');
    }
}
