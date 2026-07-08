<?php

namespace Tests\Unit\Modules\Users;

use PHPUnit\Framework\TestCase;

class UsersTest extends TestCase
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

        $sql = "INSERT INTO `users` () VALUES ()";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));

        $bindValues = [];
        $bindTypes = '';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);

        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `users` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);

        // 3. Update
        // No suitable varchar/text column found for update test, skipping update assertion

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
