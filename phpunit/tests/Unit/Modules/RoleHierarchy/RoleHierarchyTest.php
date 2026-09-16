<?php

namespace Tests\Unit\Modules\RoleHierarchy;

use PHPUnit\Framework\TestCase;

class RoleHierarchyTest extends TestCase
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

        // Set session company_id for auditing
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    private function getAvailableRoleId() {
        // Find a role that is NOT in role_hierarchy for this company
        $res = mysqli_query($this->conn, "SELECT id FROM `employee_roles` WHERE company_id = {$this->companyId} AND id NOT IN (SELECT role_id FROM role_hierarchy WHERE company_id = {$this->companyId}) LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // If all roles are used, create a new one
        $uniqueName = 'Test Role ' . uniqid();
        $res = mysqli_query($this->conn, "INSERT INTO `employee_roles` (company_id, name, active) VALUES ({$this->companyId}, '$uniqueName', 1)");
        if (!$res) {
            throw new \Exception("Failed to insert user_role: " . mysqli_error($this->conn));
        }
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        $roleId = $this->getAvailableRoleId();

        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['role_id'] = $roleId;
        $data['hierarchy_order'] = 999;

        $sql = "INSERT INTO `role_hierarchy` (company_id, `role_id`, `hierarchy_order`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['role_id'];
        $bindValues[] = $data['hierarchy_order'];
        $bindTypes = 'iii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `role_hierarchy` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 1000;
        $updateSql = "UPDATE `role_hierarchy` SET `hierarchy_order` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'ii', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `hierarchy_order` FROM `role_hierarchy` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, (int)$row['hierarchy_order']);

        // 4. Delete
        $deleteSql = "DELETE FROM `role_hierarchy` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `role_hierarchy` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
