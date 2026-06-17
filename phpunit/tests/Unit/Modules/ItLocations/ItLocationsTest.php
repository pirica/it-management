<?php

namespace Tests\Unit\Modules\ItLocations;

use PHPUnit\Framework\TestCase;

class ItLocationsTest extends TestCase
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
        // Find or fallback for type_id (location_types)
        $restype_id = mysqli_query($this->conn, "SELECT id FROM `location_types` WHERE " . (strpos('location_types', 'companies') === false && strpos('location_types', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowtype_id = mysqli_fetch_assoc($restype_id)) {
            $data['type_id'] = $rowtype_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['type_id'] = null;
        }

        $sql = "INSERT INTO `it_locations` (company_id, `name`, `active`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['name'];
        $bindValues[] = $data['active'];
        $bindTypes = 'isi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `it_locations` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `it_locations` SET `name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `name` FROM `it_locations` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `it_locations` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `it_locations` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
