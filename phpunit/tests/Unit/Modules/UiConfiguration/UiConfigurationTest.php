<?php

namespace Tests\Unit\Modules\UiConfiguration;

use PHPUnit\Framework\TestCase;

class UiConfigurationTest extends TestCase
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
        // Find a user and company that don't have a UI configuration yet to avoid unique constraint violations.
        $res = mysqli_query($this->conn, "SELECT u.id, u.company_id FROM `users` u LEFT JOIN `ui_configuration` uc ON u.id = uc.user_id AND u.company_id = uc.company_id WHERE uc.id IS NULL LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row;
        }

        // Create new user
        $username = 'user_' . uniqid();
        $email = $username . '@example.com';
        mysqli_query($this->conn, "INSERT INTO `users` (company_id, username, email, active) VALUES ({$this->companyId}, '$username', '$email', 1)");
        return ['id' => mysqli_insert_id($this->conn), 'company_id' => $this->companyId];
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $user = $this->getOrCreateUser();
        $data['user_id'] = $user['id'];
        $data['company_id'] = $user['company_id'];

        $data['table_actions_position'] = 'left';
        $data['new_button_position'] = 'left';
        $data['export_buttons_position'] = 'left';
        $data['back_save_position'] = 'left';
        $data['enable_all_error_reporting'] = 1;
        $data['enable_audit_logs'] = 1;
        $data['records_per_page'] = '25';
        $data['app_name'] = 'Test app_name';
        $data['favicon_path'] = 'Test favicon_path';

        $sql = "INSERT INTO `ui_configuration` (company_id, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, 'Prepare failed: ' . mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['user_id'];
        $bindValues[] = $data['table_actions_position'];
        $bindValues[] = $data['new_button_position'];
        $bindValues[] = $data['export_buttons_position'];
        $bindValues[] = $data['back_save_position'];
        $bindValues[] = $data['enable_all_error_reporting'];
        $bindValues[] = $data['enable_audit_logs'];
        $bindValues[] = $data['records_per_page'];
        $bindValues[] = $data['app_name'];
        $bindValues[] = $data['favicon_path'];
        $bindTypes = 'iissssiisss';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Execute failed: ' . mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `ui_configuration` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($data['company_id'], $row['company_id']);

        // 3. Update
        $updatedValue = 'right';
        $updateSql = "UPDATE `ui_configuration` SET `table_actions_position` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        $this->assertNotFalse($stmt, 'Prepare update failed: ' . mysqli_error($this->conn));
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Execute update failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `table_actions_position` FROM `ui_configuration` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['table_actions_position']);

        // 4. Delete
        $deleteSql = "DELETE FROM `ui_configuration` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        $this->assertNotFalse($stmt, 'Prepare delete failed: ' . mysqli_error($this->conn));
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Execute delete failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `ui_configuration` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
