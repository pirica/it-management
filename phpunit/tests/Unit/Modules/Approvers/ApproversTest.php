<?php

namespace Tests\Unit\Modules\Approvers;

use PHPUnit\Framework\TestCase;

class ApproversTest extends TestCase
{
    private $conn;
    private $companyId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['active'] = 1;
        // Find or fallback for employee_id (employees)
        $resemployee_id = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowemployee_id = mysqli_fetch_assoc($resemployee_id)) {
            $data['employee_id'] = $rowemployee_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency employees not found in database.');
        }
        // Find or fallback for employee_position_id (employee_positions)
        $resemployee_position_id = mysqli_query($this->conn, "SELECT id FROM `employee_positions` WHERE " . (strpos('employee_positions', 'companies') === false && strpos('employee_positions', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowemployee_position_id = mysqli_fetch_assoc($resemployee_position_id)) {
            $data['employee_position_id'] = $rowemployee_position_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency employee_positions not found in database.');
        }
        // Find or fallback for department_id (departments)
        $resdepartment_id = mysqli_query($this->conn, "SELECT id FROM `departments` WHERE " . (strpos('departments', 'companies') === false && strpos('departments', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowdepartment_id = mysqli_fetch_assoc($resdepartment_id)) {
            $data['department_id'] = $rowdepartment_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency departments not found in database.');
        }
        // Find or fallback for approver_type_id (approver_type)
        $resapprover_type_id = mysqli_query($this->conn, "SELECT id FROM `approver_type` WHERE " . (strpos('approver_type', 'companies') === false && strpos('approver_type', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowapprover_type_id = mysqli_fetch_assoc($resapprover_type_id)) {
            $data['approver_type_id'] = $rowapprover_type_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency approver_type not found in database.');
        }

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
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `approvers` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        // No suitable varchar/text column found for update test, skipping update assertion

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
