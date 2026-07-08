<?php

namespace Tests\Unit\Modules\IdfPositions;

use PHPUnit\Framework\TestCase;

class IdfPositionsTest extends TestCase
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
        $data['position_no'] = 1;
        $data['device_name'] = 'Test device_name';
        $data['rj45_count'] = 1;
        $data['sfp_count'] = 1;
        // Find or fallback for idf_id (idfs)
        $residf_id = mysqli_query($this->conn, "SELECT id FROM `idfs` WHERE " . (strpos('idfs', 'companies') === false && strpos('idfs', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowidf_id = mysqli_fetch_assoc($residf_id)) {
            $data['idf_id'] = $rowidf_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency idfs not found in database.');
        }
        // Find or fallback for device_type (idf_device_type)
        $resdevice_type = mysqli_query($this->conn, "SELECT id FROM `idf_device_type` WHERE " . (strpos('idf_device_type', 'companies') === false && strpos('idf_device_type', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowdevice_type = mysqli_fetch_assoc($resdevice_type)) {
            $data['device_type'] = $rowdevice_type['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency idf_device_type not found in database.');
        }
        // Find or fallback for switch_port_numbering_layout_id (switch_port_numbering_layout)
        $resswitch_port_numbering_layout_id = mysqli_query($this->conn, "SELECT id FROM `switch_port_numbering_layout` WHERE " . (strpos('switch_port_numbering_layout', 'companies') === false && strpos('switch_port_numbering_layout', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowswitch_port_numbering_layout_id = mysqli_fetch_assoc($resswitch_port_numbering_layout_id)) {
            $data['switch_port_numbering_layout_id'] = $rowswitch_port_numbering_layout_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['switch_port_numbering_layout_id'] = null;
        }

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
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `idf_positions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `idf_positions` SET `device_name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `device_name` FROM `idf_positions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['device_name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `idf_positions` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `idf_positions` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
