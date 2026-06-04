<?php

namespace Tests\Unit\Modules\UiConfiguration;

use PHPUnit\Framework\TestCase;

class UiConfigurationTest extends TestCase
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
        $data['user_id'] = 1;
        $data['table_actions_position'] = 'Test table_actions_position';
        $data['new_button_position'] = 'Test new_button_position';
        $data['export_buttons_position'] = 'Test export_buttons_position';
        $data['back_save_position'] = 'Test back_save_position';
        $data['enable_all_error_reporting'] = 1;
        $data['enable_audit_logs'] = 1;
        $data['records_per_page'] = 'Test records_per_page';
        $data['app_name'] = 'Test app_name';
        $data['favicon_path'] = 'Test favicon_path';

        $sql = "INSERT INTO `ui_configuration` (company_id, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
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
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `ui_configuration` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `ui_configuration` SET `table_actions_position` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `table_actions_position` FROM `ui_configuration` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['table_actions_position']);

        // 4. Delete
        $deleteSql = "DELETE FROM `ui_configuration` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `ui_configuration` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
