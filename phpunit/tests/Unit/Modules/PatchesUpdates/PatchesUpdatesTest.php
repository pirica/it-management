<?php

namespace Tests\Unit\Modules\PatchesUpdates;

use PHPUnit\Framework\TestCase;

class PatchesUpdatesTest extends TestCase
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
        // Find or fallback for equipment_id (equipment)
        $resequipment_id = mysqli_query($this->conn, "SELECT id FROM `equipment` WHERE " . (strpos('equipment', 'companies') === false && strpos('equipment', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_id = mysqli_fetch_assoc($resequipment_id)) {
            $data['equipment_id'] = $rowequipment_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_id'] = null;
        }
        // Find or fallback for status_id (patches_updates_status)
        $resstatus_id = mysqli_query($this->conn, "SELECT id FROM `patches_updates_status` WHERE " . (strpos('patches_updates_status', 'companies') === false && strpos('patches_updates_status', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowstatus_id = mysqli_fetch_assoc($resstatus_id)) {
            $data['status_id'] = $rowstatus_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['status_id'] = null;
        }
        // Find or fallback for level_id (patches_updates_level)
        $reslevel_id = mysqli_query($this->conn, "SELECT id FROM `patches_updates_level` WHERE " . (strpos('patches_updates_level', 'companies') === false && strpos('patches_updates_level', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowlevel_id = mysqli_fetch_assoc($reslevel_id)) {
            $data['level_id'] = $rowlevel_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['level_id'] = null;
        }
        // Find or fallback for created_by (users)
        $rescreated_by = mysqli_query($this->conn, "SELECT id FROM `users` WHERE " . (strpos('users', 'companies') === false && strpos('users', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowcreated_by = mysqli_fetch_assoc($rescreated_by)) {
            $data['created_by'] = $rowcreated_by['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['created_by'] = null;
        }

        $sql = "INSERT INTO `patches_updates` (company_id) VALUES (?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindTypes = 'i';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `patches_updates` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `patches_updates` SET `hostname` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `hostname` FROM `patches_updates` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['hostname']);

        // 4. Delete
        $deleteSql = "DELETE FROM `patches_updates` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `patches_updates` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
