<?php

namespace Tests\Unit\Modules\Racks;

use PHPUnit\Framework\TestCase;

class RacksTest extends TestCase
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
        $data['name'] = 'Test name';
        $data['active'] = 1;
        // Find or fallback for location_id (it_locations)
        $reslocation_id = mysqli_query($this->conn, "SELECT id FROM `it_locations` WHERE " . (strpos('it_locations', 'companies') === false && strpos('it_locations', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowlocation_id = mysqli_fetch_assoc($reslocation_id)) {
            $data['location_id'] = $rowlocation_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['location_id'] = null;
        }
        // Find or fallback for status_id (rack_statuses)
        $resstatus_id = mysqli_query($this->conn, "SELECT id FROM `rack_statuses` WHERE " . (strpos('rack_statuses', 'companies') === false && strpos('rack_statuses', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowstatus_id = mysqli_fetch_assoc($resstatus_id)) {
            $data['status_id'] = $rowstatus_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency rack_statuses not found in database.');
        }

        $sql = "INSERT INTO `racks` (company_id, `name`, `status_id`, `active`) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['name'];
        $bindValues[] = $data['status_id'];
        $bindValues[] = $data['active'];
        $bindTypes = 'isii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `racks` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `racks` SET `name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `name` FROM `racks` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `racks` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `racks` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
