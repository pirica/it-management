<?php

namespace Tests\Unit\Modules\IdfPositions;

use PHPUnit\Framework\TestCase;

class IdfPositionsTest extends TestCase
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
        
        // Set session company_id for auditing
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    private function getOrCreateIdf() {
        $res = mysqli_query($this->conn, "SELECT id FROM `idfs` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `idfs` (company_id, name) VALUES ({$this->companyId}, 'Test IDF')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateIdfDeviceType() {
        $res = mysqli_query($this->conn, "SELECT id FROM `idf_device_type` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `idf_device_type` (company_id, idfdevicetype_name) VALUES ({$this->companyId}, 'Patch Panel')");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['position_no'] = 999;
        $data['device_name'] = 'Test device ' . uniqid();
        $data['rj45_count'] = 24;
        $data['sfp_count'] = 2;
        $data['idf_id'] = $this->getOrCreateIdf();
        $data['device_type'] = $this->getOrCreateIdfDeviceType();

        $sql = "INSERT INTO `idf_positions` (company_id, `idf_id`, `position_no`, `device_type`, `device_name`, `rj45_count`, `sfp_count`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['idf_id'];
        $bindValues[] = $data['position_no'];
        $bindValues[] = $data['device_type'];
        $bindValues[] = $data['device_name'];
        $bindValues[] = $data['rj45_count'];
        $bindValues[] = $data['sfp_count'];
        $bindTypes = 'iiiisii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `idf_positions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Device Name ' . uniqid();
        $updateSql = "UPDATE `idf_positions` SET `device_name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `device_name` FROM `idf_positions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['device_name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `idf_positions` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `idf_positions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
