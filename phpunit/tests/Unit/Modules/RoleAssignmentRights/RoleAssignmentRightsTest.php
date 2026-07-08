<?php

namespace Tests\Unit\Modules\RoleAssignmentRights;

use PHPUnit\Framework\TestCase;

class RoleAssignmentRightsTest extends TestCase
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
        // Find or fallback for role_id (employee_roles)
        $resrole_id = mysqli_query($this->conn, "SELECT id FROM `employee_roles` WHERE " . (strpos('employee_roles', 'companies') === false && strpos('employee_roles', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowrole_id = mysqli_fetch_assoc($resrole_id)) {
            $data['role_id'] = $rowrole_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency employee_roles not found in database.');
        }
        // Find or fallback for can_assign_role_id (employee_roles)
        $rescan_assign_role_id = mysqli_query($this->conn, "SELECT id FROM `employee_roles` WHERE " . (strpos('employee_roles', 'companies') === false && strpos('employee_roles', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowcan_assign_role_id = mysqli_fetch_assoc($rescan_assign_role_id)) {
            $data['can_assign_role_id'] = $rowcan_assign_role_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency employee_roles not found in database.');
        }

        $sql = "INSERT INTO `role_assignment_rights` (company_id, `role_id`, `can_assign_role_id`, `active`) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['role_id'];
        $bindValues[] = $data['can_assign_role_id'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `role_assignment_rights` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        // No suitable varchar/text column found for update test, skipping update assertion

        // 4. Delete
        $deleteSql = "DELETE FROM `role_assignment_rights` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `role_assignment_rights` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
