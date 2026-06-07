<?php

namespace Tests\Unit\Modules\Approvers;

use PHPUnit\Framework\TestCase;

class ApproversTest extends TestCase
{
    private $conn;
    private $companyId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        // Set session company_id for auditing
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    private function getOrCreateEmployee() {
        $res = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Need dependencies: employee_status, employee_position
        $resStat = mysqli_query($this->conn, "SELECT id FROM employee_statuses WHERE company_id = {$this->companyId} LIMIT 1");
        $statId = ($row = mysqli_fetch_assoc($resStat)) ? $row['id'] : 0;
        if (!$statId) {
            mysqli_query($this->conn, "INSERT INTO employee_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
            $statId = mysqli_insert_id($this->conn);
        }

        $resPos = mysqli_query($this->conn, "SELECT id FROM employee_positions WHERE company_id = {$this->companyId} LIMIT 1");
        $posId = ($row = mysqli_fetch_assoc($resPos)) ? $row['id'] : 0;
        if (!$posId) {
            mysqli_query($this->conn, "INSERT INTO employee_positions (company_id, name) VALUES ({$this->companyId}, 'Test Position')");
            $posId = mysqli_insert_id($this->conn);
        }

        mysqli_query($this->conn, "INSERT INTO `employees` (company_id, first_name, last_name, employment_status_id, employee_position_id) VALUES ({$this->companyId}, 'Test', 'Approver', $statId, $posId)");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateEmployeePosition() {
        $res = mysqli_query($this->conn, "SELECT id FROM `employee_positions` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `employee_positions` (company_id, name) VALUES ({$this->companyId}, 'Test Position')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateDepartment() {
        $res = mysqli_query($this->conn, "SELECT id FROM `departments` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `departments` (company_id, name) VALUES ({$this->companyId}, 'Test Dept')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateApproverType() {
        $res = mysqli_query($this->conn, "SELECT id FROM `approver_type` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `approver_type` (company_id, approver_type_description) VALUES ({$this->companyId}, 'Test Approver Type')");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['active'] = 1;
        $data['employee_id'] = $this->getOrCreateEmployee();
        $data['employee_position_id'] = $this->getOrCreateEmployeePosition();
        $data['department_id'] = $this->getOrCreateDepartment();
        $data['approver_type_id'] = $this->getOrCreateApproverType();

        $sql = "INSERT INTO `approvers` (company_id, `employee_id`, `employee_position_id`, `department_id`, `approver_type_id`, `active`) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['employee_id'];
        $bindValues[] = $data['employee_position_id'];
        $bindValues[] = $data['department_id'];
        $bindValues[] = $data['approver_type_id'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iiiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `approvers` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 0; // Toggle active
        $updateSql = "UPDATE `approvers` SET `active` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'ii', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `active` FROM `approvers` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, (int)$row['active']);

        // 4. Delete
        $deleteSql = "DELETE FROM `approvers` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `approvers` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
