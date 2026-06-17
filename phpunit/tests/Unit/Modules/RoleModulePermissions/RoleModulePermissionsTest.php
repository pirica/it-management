<?php

namespace Tests\Unit\Modules\RoleModulePermissions;

use PHPUnit\Framework\TestCase;

class RoleModulePermissionsTest extends TestCase
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
        $data['module_name'] = 'Test module_name';
        $data['can_view'] = 1;
        $data['can_create'] = 1;
        $data['can_edit'] = 1;
        $data['can_delete'] = 1;
        $data['can_import'] = 1;
        $data['can_export'] = 1;
        // Find or fallback for role_id (user_roles)
        $resrole_id = mysqli_query($this->conn, "SELECT id FROM `user_roles` WHERE " . (strpos('user_roles', 'companies') === false && strpos('user_roles', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowrole_id = mysqli_fetch_assoc($resrole_id)) {
            $data['role_id'] = $rowrole_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency user_roles not found in database.');
        }

        $sql = "INSERT INTO `role_module_permissions` (company_id, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['role_id'];
        $bindValues[] = $data['module_name'];
        $bindValues[] = $data['can_view'];
        $bindValues[] = $data['can_create'];
        $bindValues[] = $data['can_edit'];
        $bindValues[] = $data['can_delete'];
        $bindValues[] = $data['can_import'];
        $bindValues[] = $data['can_export'];
        $bindTypes = 'iisiiiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `role_module_permissions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `role_module_permissions` SET `module_name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `module_name` FROM `role_module_permissions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['module_name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `role_module_permissions` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `role_module_permissions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
