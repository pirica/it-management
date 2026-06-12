<?php

namespace Tests\Unit\Modules\Attempts;

use PHPUnit\Framework\TestCase;

class AttemptsTest extends TestCase
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
        $data['active'] = 1;
        $data['attempt_source'] = 'login';
        $data['attempt_type'] = 'success';
        $data['ip_address'] = 'Test ip_address';
        // Find or fallback for user_id (users)
        $resuser_id = mysqli_query($this->conn, "SELECT id FROM `users` WHERE " . (strpos('users', 'companies') === false && strpos('users', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowuser_id = mysqli_fetch_assoc($resuser_id)) {
            $data['user_id'] = $rowuser_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['user_id'] = null;
        }

        $sql = "INSERT INTO `attempts` (`active`, `attempt_source`, `attempt_type`, `ip_address`) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['active'];
        $bindValues[] = $data['attempt_source'];
        $bindValues[] = $data['attempt_type'];
        $bindValues[] = $data['ip_address'];
        $bindTypes = 'isss';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `attempts` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `attempts` SET `email` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `email` FROM `attempts` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['email']);

        // 4. Delete
        $deleteSql = "DELETE FROM `attempts` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `attempts` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
