<?php

namespace Tests\Unit\Modules\BackupTapeLog;

use PHPUnit\Framework\TestCase;

class BackupTapeLogTest extends TestCase
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
        $data['log_date'] = date('Y-m-d');
        $data['tape_to_be_used'] = 'Test tape_to_be_used';
        $data['print_name'] = 'Test print_name';
        $data['backup_status'] = 'Full';
        $data['active'] = 1;
        // Find or fallback for server_id (equipment)
        $resserver_id = mysqli_query($this->conn, "SELECT id FROM `equipment` WHERE " . (strpos('equipment', 'companies') === false && strpos('equipment', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowserver_id = mysqli_fetch_assoc($resserver_id)) {
            $data['server_id'] = $rowserver_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency equipment not found in database.');
        }

        $sql = "INSERT INTO `backup_tape_log` (company_id, `server_id`, `log_date`, `tape_to_be_used`, `print_name`, `backup_status`, `active`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['server_id'];
        $bindValues[] = $data['log_date'];
        $bindValues[] = $data['tape_to_be_used'];
        $bindValues[] = $data['print_name'];
        $bindValues[] = $data['backup_status'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iissssi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `backup_tape_log` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `backup_tape_log` SET `tape_to_be_used` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `tape_to_be_used` FROM `backup_tape_log` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['tape_to_be_used']);

        // 4. Delete
        $deleteSql = "DELETE FROM `backup_tape_log` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `backup_tape_log` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
