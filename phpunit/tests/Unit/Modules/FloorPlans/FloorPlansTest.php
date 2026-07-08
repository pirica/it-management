<?php

namespace Tests\Unit\Modules\FloorPlans;

use PHPUnit\Framework\TestCase;

class FloorPlansTest extends TestCase
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
        $data['display_name'] = 'Test display_name';
        $data['stored_filename'] = 'Test stored_filename';
        $data['mime_type'] = 'Test mime_type';
        $data['file_ext'] = 'Test file_ext';
        $data['file_size'] = 1;
        $data['active'] = 1;
        // Find or fallback for folder_id (floor_plan_folders)
        $resfolder_id = mysqli_query($this->conn, "SELECT id FROM `floor_plan_folders` WHERE " . (strpos('floor_plan_folders', 'companies') === false && strpos('floor_plan_folders', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowfolder_id = mysqli_fetch_assoc($resfolder_id)) {
            $data['folder_id'] = $rowfolder_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['folder_id'] = null;
        }
        // Find or fallback for it_location_id (it_locations)
        $resit_location_id = mysqli_query($this->conn, "SELECT id FROM `it_locations` WHERE " . (strpos('it_locations', 'companies') === false && strpos('it_locations', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowit_location_id = mysqli_fetch_assoc($resit_location_id)) {
            $data['it_location_id'] = $rowit_location_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['it_location_id'] = null;
        }
        // Find or fallback for created_by_employee_id (employees)
        $rescreated_by_employee_id = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowcreated_by_employee_id = mysqli_fetch_assoc($rescreated_by_employee_id)) {
            $data['created_by_employee_id'] = $rowcreated_by_employee_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['created_by_employee_id'] = null;
        }

        $sql = "INSERT INTO `floor_plans` (company_id, `display_name`, `stored_filename`, `mime_type`, `file_ext`, `file_size`, `active`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['display_name'];
        $bindValues[] = $data['stored_filename'];
        $bindValues[] = $data['mime_type'];
        $bindValues[] = $data['file_ext'];
        $bindValues[] = $data['file_size'];
        $bindValues[] = $data['active'];
        $bindTypes = 'issssii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `floor_plans` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `floor_plans` SET `display_name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `display_name` FROM `floor_plans` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['display_name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `floor_plans` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `floor_plans` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
