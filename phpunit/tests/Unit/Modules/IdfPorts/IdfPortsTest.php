<?php

namespace Tests\Unit\Modules\IdfPorts;

use PHPUnit\Framework\TestCase;

class IdfPortsTest extends TestCase
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
        $data['port_no'] = 1;
        // Find or fallback for position_id (idf_positions)
        $resposition_id = mysqli_query($this->conn, "SELECT id FROM `idf_positions` WHERE " . (strpos('idf_positions', 'companies') === false && strpos('idf_positions', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowposition_id = mysqli_fetch_assoc($resposition_id)) {
            $data['position_id'] = $rowposition_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency idf_positions not found in database.');
        }
        // Find or fallback for port_type (switch_port_types)
        $resport_type = mysqli_query($this->conn, "SELECT id FROM `switch_port_types` WHERE " . (strpos('switch_port_types', 'companies') === false && strpos('switch_port_types', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowport_type = mysqli_fetch_assoc($resport_type)) {
            $data['port_type'] = $rowport_type['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency switch_port_types not found in database.');
        }
        // Find or fallback for status_id (switch_status)
        $resstatus_id = mysqli_query($this->conn, "SELECT id FROM `switch_status` WHERE " . (strpos('switch_status', 'companies') === false && strpos('switch_status', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowstatus_id = mysqli_fetch_assoc($resstatus_id)) {
            $data['status_id'] = $rowstatus_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency switch_status not found in database.');
        }
        // Find or fallback for vlan_id (vlans)
        $resvlan_id = mysqli_query($this->conn, "SELECT id FROM `vlans` WHERE " . (strpos('vlans', 'companies') === false && strpos('vlans', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowvlan_id = mysqli_fetch_assoc($resvlan_id)) {
            $data['vlan_id'] = $rowvlan_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['vlan_id'] = null;
        }
        // Find or fallback for speed_id (equipment_fiber)
        $resspeed_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber` WHERE " . (strpos('equipment_fiber', 'companies') === false && strpos('equipment_fiber', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowspeed_id = mysqli_fetch_assoc($resspeed_id)) {
            $data['speed_id'] = $rowspeed_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['speed_id'] = null;
        }
        // Find or fallback for rj45_speed_id (rj45_speed)
        $resrj45_speed_id = mysqli_query($this->conn, "SELECT id FROM `rj45_speed` WHERE " . (strpos('rj45_speed', 'companies') === false && strpos('rj45_speed', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowrj45_speed_id = mysqli_fetch_assoc($resrj45_speed_id)) {
            $data['rj45_speed_id'] = $rowrj45_speed_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['rj45_speed_id'] = null;
        }
        // Find or fallback for fiber_ports_number (equipment_fiber_count)
        $resfiber_ports_number = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber_count` WHERE " . (strpos('equipment_fiber_count', 'companies') === false && strpos('equipment_fiber_count', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowfiber_ports_number = mysqli_fetch_assoc($resfiber_ports_number)) {
            $data['fiber_ports_number'] = $rowfiber_ports_number['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['fiber_ports_number'] = null;
        }
        // Find or fallback for switch_port_numbering_layout_id (switch_port_numbering_layout)
        $resswitch_port_numbering_layout_id = mysqli_query($this->conn, "SELECT id FROM `switch_port_numbering_layout` WHERE " . (strpos('switch_port_numbering_layout', 'companies') === false && strpos('switch_port_numbering_layout', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowswitch_port_numbering_layout_id = mysqli_fetch_assoc($resswitch_port_numbering_layout_id)) {
            $data['switch_port_numbering_layout_id'] = $rowswitch_port_numbering_layout_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['switch_port_numbering_layout_id'] = null;
        }
        // Find or fallback for management_id (equipment_environment)
        $resmanagement_id = mysqli_query($this->conn, "SELECT id FROM `equipment_environment` WHERE " . (strpos('equipment_environment', 'companies') === false && strpos('equipment_environment', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowmanagement_id = mysqli_fetch_assoc($resmanagement_id)) {
            $data['management_id'] = $rowmanagement_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['management_id'] = null;
        }
        // Find or fallback for poe_id (equipment_poe)
        $respoe_id = mysqli_query($this->conn, "SELECT id FROM `equipment_poe` WHERE " . (strpos('equipment_poe', 'companies') === false && strpos('equipment_poe', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowpoe_id = mysqli_fetch_assoc($respoe_id)) {
            $data['poe_id'] = $rowpoe_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['poe_id'] = null;
        }

        $sql = "INSERT INTO `idf_ports` (company_id, `position_id`, `port_no`, `port_type`, `status_id`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['position_id'];
        $bindValues[] = $data['port_no'];
        $bindValues[] = $data['port_type'];
        $bindValues[] = $data['status_id'];
        $bindTypes = 'iiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `idf_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `idf_ports` SET `label` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `label` FROM `idf_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['label']);

        // 4. Delete
        $deleteSql = "DELETE FROM `idf_ports` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `idf_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
