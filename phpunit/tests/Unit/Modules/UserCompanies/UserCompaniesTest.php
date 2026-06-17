<?php

namespace Tests\Unit\Modules\UserCompanies;

use PHPUnit\Framework\TestCase;

class UserCompaniesTest extends TestCase
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

    private function getOrCreateUser() {
        // Find a user that is not linked to this company yet
        $res = mysqli_query($this->conn, "SELECT u.id FROM `users` u LEFT JOIN `user_companies` uc ON u.id = uc.user_id AND uc.company_id = {$this->companyId} WHERE uc.user_id IS NULL LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Create new user
        $username = 'user_comp_' . uniqid();
        $email = $username . '@example.com';
        mysqli_query($this->conn, "INSERT INTO `users` (company_id, username, email, active) VALUES ({$this->companyId}, '$username', '$email', 1)");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['active'] = 1;
        $data['user_id'] = $this->getOrCreateUser();

        $sql = "INSERT INTO `user_companies` (company_id, `user_id`, `active`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['user_id'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `user_companies` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 0; // Toggle active
        $updateSql = "UPDATE `user_companies` SET `active` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'ii', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `active` FROM `user_companies` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, (int)$row['active']);

        // 4. Delete
        $deleteSql = "DELETE FROM `user_companies` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `user_companies` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
