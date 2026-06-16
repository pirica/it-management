<?php

namespace Tests\Unit\Modules\Users;

use PHPUnit\Framework\TestCase;

class UsersTest extends TestCase
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
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['username'] = 'Test username';
        $data['active'] = 1;
        // Find or fallback for role_id (user_roles)
        $resrole_id = mysqli_query($this->conn, "SELECT id FROM `user_roles` WHERE " . (strpos('user_roles', 'companies') === false && strpos('user_roles', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowrole_id = mysqli_fetch_assoc($resrole_id)) {
            $data['role_id'] = $rowrole_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['role_id'] = null;
        }
        // Find or fallback for access_level_id (access_levels)
        $resaccess_level_id = mysqli_query($this->conn, "SELECT id FROM `access_levels` WHERE " . (strpos('access_levels', 'companies') === false && strpos('access_levels', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowaccess_level_id = mysqli_fetch_assoc($resaccess_level_id)) {
            $data['access_level_id'] = $rowaccess_level_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['access_level_id'] = null;
        }

        $sql = "INSERT INTO `users` (company_id, `username`, `active`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['username'];
        $bindValues[] = $data['active'];
        $bindTypes = 'isi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `users` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `users` SET `username` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `username` FROM `users` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['username']);

        // 4. Delete
        $deleteSql = "DELETE FROM `users` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `users` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
