<?php

namespace Tests\Unit\Modules\EmployeeAssignmentHistory;

use PHPUnit\Framework\TestCase;

class EmployeeAssignmentHistoryTest extends TestCase
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
        $data['assigned_date'] = date('Y-m-d');
        $data['signed_handover'] = 1;
        $data['active'] = 1;
        // Find or fallback for employee_id (employees)
        $resemployee_id = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowemployee_id = mysqli_fetch_assoc($resemployee_id)) {
            $data['employee_id'] = $rowemployee_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency employees not found in database.');
        }
        // Find or fallback for equipment_id (equipment)
        $resequipment_id = mysqli_query($this->conn, "SELECT id FROM `equipment` WHERE " . (strpos('equipment', 'companies') === false && strpos('equipment', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_id = mysqli_fetch_assoc($resequipment_id)) {
            $data['equipment_id'] = $rowequipment_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_id'] = null;
        }
        // Find or fallback for inventory_item_id (inventory_items)
        $resinventory_item_id = mysqli_query($this->conn, "SELECT id FROM `inventory_items` WHERE " . (strpos('inventory_items', 'companies') === false && strpos('inventory_items', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowinventory_item_id = mysqli_fetch_assoc($resinventory_item_id)) {
            $data['inventory_item_id'] = $rowinventory_item_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['inventory_item_id'] = null;
        }
        // Find or fallback for assigned_by_employee_id (employees)
        $resassigned_by_employee_id = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowassigned_by_employee_id = mysqli_fetch_assoc($resassigned_by_employee_id)) {
            $data['assigned_by_employee_id'] = $rowassigned_by_employee_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['assigned_by_employee_id'] = null;
        }
        // Find or fallback for received_by_employee_id (employees)
        $resreceived_by_employee_id = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowreceived_by_employee_id = mysqli_fetch_assoc($resreceived_by_employee_id)) {
            $data['received_by_employee_id'] = $rowreceived_by_employee_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['received_by_employee_id'] = null;
        }

        $sql = "INSERT INTO `employee_assignment_history` (company_id, `employee_id`, `assigned_date`, `signed_handover`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['employee_id'];
        $bindValues[] = $data['assigned_date'];
        $bindValues[] = $data['signed_handover'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iisii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `employee_assignment_history` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `employee_assignment_history` SET `asset_description` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `asset_description` FROM `employee_assignment_history` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['asset_description']);

        // 4. Delete
        $deleteSql = "DELETE FROM `employee_assignment_history` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `employee_assignment_history` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
