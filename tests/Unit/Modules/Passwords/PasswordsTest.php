<?php

namespace Tests\Unit\Modules\Passwords;

use PHPUnit\Framework\TestCase;

class PasswordsTest extends TestCase
{
    private $conn;
    private $userId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testFolderCRUD()
    {
        // 1. Create
        $name = 'Test Folder ' . uniqid();
        $sql = "INSERT INTO password_folders (user_id, name) VALUES (?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $this->userId, $name);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $sql = "SELECT name FROM password_folders WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($name, $row['name']);
        mysqli_stmt_close($stmt);

        // 3. Update
        $newName = 'Updated Folder ' . uniqid();
        $sql = "UPDATE password_folders SET name = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $newName, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        // 4. Delete
        $sql = "DELETE FROM password_folders WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as c FROM password_folders WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['c']);
    }

    public function testEntryCRUD()
    {
        // 1. Create
        $account = 'Test Account ' . uniqid();
        $sql = "INSERT INTO password_entries (user_id, account, password) VALUES (?, ?, 'secret')";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $this->userId, $account);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $sql = "SELECT account FROM password_entries WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($account, $row['account']);
        mysqli_stmt_close($stmt);

        // 3. Delete
        $sql = "DELETE FROM password_entries WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);
    }
}
