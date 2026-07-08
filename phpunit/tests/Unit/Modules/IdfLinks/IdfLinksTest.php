<?php

namespace Tests\Unit\Modules\IdfLinks;

use PHPUnit\Framework\TestCase;

class IdfLinksTest extends TestCase
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
        // Find or fallback for port_id_a (idf_ports)
        $resport_id_a = mysqli_query($this->conn, "SELECT id FROM `idf_ports` WHERE " . (strpos('idf_ports', 'companies') === false && strpos('idf_ports', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowport_id_a = mysqli_fetch_assoc($resport_id_a)) {
            $data['port_id_a'] = $rowport_id_a['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency idf_ports not found in database.');
        }
        // Find or fallback for port_id_b (idf_ports)
        $resport_id_b = mysqli_query($this->conn, "SELECT id FROM `idf_ports` WHERE " . (strpos('idf_ports', 'companies') === false && strpos('idf_ports', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowport_id_b = mysqli_fetch_assoc($resport_id_b)) {
            $data['port_id_b'] = $rowport_id_b['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency idf_ports not found in database.');
        }
        // Find or fallback for equipment_rj45_speed_id (rj45_speed)
        $resequipment_rj45_speed_id = mysqli_query($this->conn, "SELECT id FROM `rj45_speed` WHERE " . (strpos('rj45_speed', 'companies') === false && strpos('rj45_speed', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_rj45_speed_id = mysqli_fetch_assoc($resequipment_rj45_speed_id)) {
            $data['equipment_rj45_speed_id'] = $rowequipment_rj45_speed_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_rj45_speed_id'] = null;
        }
        // Find or fallback for equipment_fiber_port_id (equipment_fiber)
        $resequipment_fiber_port_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber` WHERE " . (strpos('equipment_fiber', 'companies') === false && strpos('equipment_fiber', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_fiber_port_id = mysqli_fetch_assoc($resequipment_fiber_port_id)) {
            $data['equipment_fiber_port_id'] = $rowequipment_fiber_port_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_fiber_port_id'] = null;
        }
        // Find or fallback for equipment_fiber_patch_id (equipment_fiber_patch)
        $resequipment_fiber_patch_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber_patch` WHERE " . (strpos('equipment_fiber_patch', 'companies') === false && strpos('equipment_fiber_patch', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_fiber_patch_id = mysqli_fetch_assoc($resequipment_fiber_patch_id)) {
            $data['equipment_fiber_patch_id'] = $rowequipment_fiber_patch_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_fiber_patch_id'] = null;
        }
        // Find or fallback for equipment_fiber_rack_id (equipment_fiber_rack)
        $resequipment_fiber_rack_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber_rack` WHERE " . (strpos('equipment_fiber_rack', 'companies') === false && strpos('equipment_fiber_rack', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_fiber_rack_id = mysqli_fetch_assoc($resequipment_fiber_rack_id)) {
            $data['equipment_fiber_rack_id'] = $rowequipment_fiber_rack_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_fiber_rack_id'] = null;
        }
        // Find or fallback for equipment_to_idf_id (idfs)
        $resequipment_to_idf_id = mysqli_query($this->conn, "SELECT id FROM `idfs` WHERE " . (strpos('idfs', 'companies') === false && strpos('idfs', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_to_idf_id = mysqli_fetch_assoc($resequipment_to_idf_id)) {
            $data['equipment_to_idf_id'] = $rowequipment_to_idf_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_to_idf_id'] = null;
        }
        // Find or fallback for equipment_to_rack_id (racks)
        $resequipment_to_rack_id = mysqli_query($this->conn, "SELECT id FROM `racks` WHERE " . (strpos('racks', 'companies') === false && strpos('racks', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_to_rack_id = mysqli_fetch_assoc($resequipment_to_rack_id)) {
            $data['equipment_to_rack_id'] = $rowequipment_to_rack_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_to_rack_id'] = null;
        }
        // Find or fallback for equipment_to_location_id (it_locations)
        $resequipment_to_location_id = mysqli_query($this->conn, "SELECT id FROM `it_locations` WHERE " . (strpos('it_locations', 'companies') === false && strpos('it_locations', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_to_location_id = mysqli_fetch_assoc($resequipment_to_location_id)) {
            $data['equipment_to_location_id'] = $rowequipment_to_location_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_to_location_id'] = null;
        }
        // Find or fallback for cable_color_id (cable_colors)
        $rescable_color_id = mysqli_query($this->conn, "SELECT id FROM `cable_colors` WHERE " . (strpos('cable_colors', 'companies') === false && strpos('cable_colors', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowcable_color_id = mysqli_fetch_assoc($rescable_color_id)) {
            $data['cable_color_id'] = $rowcable_color_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['cable_color_id'] = null;
        }

        $sql = "INSERT INTO `idf_links` (company_id, `port_id_a`, `port_id_b`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['port_id_a'];
        $bindValues[] = $data['port_id_b'];
        $bindTypes = 'iii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `idf_links` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `idf_links` SET `equipment_id` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `equipment_id` FROM `idf_links` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['equipment_id']);

        // 4. Delete
        $deleteSql = "DELETE FROM `idf_links` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `idf_links` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
