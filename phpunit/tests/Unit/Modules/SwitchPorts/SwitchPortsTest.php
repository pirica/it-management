<?php

namespace Tests\Unit\Modules\SwitchPorts;

use PHPUnit\Framework\TestCase;

class SwitchPortsTest extends TestCase
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
        $data['port_number'] = 1;
        // Find or fallback for equipment_id (equipment)
        $resequipment_id = mysqli_query($this->conn, "SELECT id FROM `equipment` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowequipment_id = mysqli_fetch_assoc($resequipment_id)) {
            $data['equipment_id'] = $rowequipment_id['id'];
        } else {
            $data['equipment_id'] = null;
        }
        // Find or fallback for port_type (switch_port_types)
        $resport_type = mysqli_query($this->conn, "SELECT type FROM `switch_port_types` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowport_type = mysqli_fetch_assoc($resport_type)) {
            $data['port_type'] = $rowport_type['type'];
        } else {
            $this->markTestSkipped('Required dependency switch_port_types not found in database.');
        }
        // Find or fallback for status_id (switch_status)
        $resstatus_id = mysqli_query($this->conn, "SELECT id FROM `switch_status` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowstatus_id = mysqli_fetch_assoc($resstatus_id)) {
            $data['status_id'] = $rowstatus_id['id'];
        } else {
            $this->markTestSkipped('Required dependency switch_status not found in database.');
        }
        // Find or fallback for color_id (cable_colors)
        $rescolor_id = mysqli_query($this->conn, "SELECT id FROM `cable_colors` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowcolor_id = mysqli_fetch_assoc($rescolor_id)) {
            $data['color_id'] = $rowcolor_id['id'];
        } else {
            $this->markTestSkipped('Required dependency cable_colors not found in database.');
        }
        // Find or fallback for rj45_speed_id (rj45_speed)
        $resrj45_speed_id = mysqli_query($this->conn, "SELECT id FROM `rj45_speed` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowrj45_speed_id = mysqli_fetch_assoc($resrj45_speed_id)) {
            $data['rj45_speed_id'] = $rowrj45_speed_id['id'];
        } else {
            $data['rj45_speed_id'] = null;
        }
        // Find or fallback for vlan_id (vlans)
        $resvlan_id = mysqli_query($this->conn, "SELECT id FROM `vlans` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowvlan_id = mysqli_fetch_assoc($resvlan_id)) {
            $data['vlan_id'] = $rowvlan_id['id'];
        } else {
            $data['vlan_id'] = null;
        }
        // Find or fallback for fiber_port_id (equipment_fiber)
        $resfiber_port_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowfiber_port_id = mysqli_fetch_assoc($resfiber_port_id)) {
            $data['fiber_port_id'] = $rowfiber_port_id['id'];
        } else {
            $data['fiber_port_id'] = null;
        }
        // Find or fallback for fiber_patch_id (equipment_fiber_patch)
        $resfiber_patch_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber_patch` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowfiber_patch_id = mysqli_fetch_assoc($resfiber_patch_id)) {
            $data['fiber_patch_id'] = $rowfiber_patch_id['id'];
        } else {
            $data['fiber_patch_id'] = null;
        }
        // Find or fallback for fiber_rack_id (equipment_fiber_rack)
        $resfiber_rack_id = mysqli_query($this->conn, "SELECT id FROM `equipment_fiber_rack` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowfiber_rack_id = mysqli_fetch_assoc($resfiber_rack_id)) {
            $data['fiber_rack_id'] = $rowfiber_rack_id['id'];
        } else {
            $data['fiber_rack_id'] = null;
        }
        // Find or fallback for idf_id (idfs)
        $residf_id = mysqli_query($this->conn, "SELECT id FROM `idfs` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowidf_id = mysqli_fetch_assoc($residf_id)) {
            $data['idf_id'] = $rowidf_id['id'];
        } else {
            $data['idf_id'] = null;
        }
        // Find or fallback for to_idf_id (idfs)
        $resto_idf_id = mysqli_query($this->conn, "SELECT id FROM `idfs` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowto_idf_id = mysqli_fetch_assoc($resto_idf_id)) {
            $data['to_idf_id'] = $rowto_idf_id['id'];
        } else {
            $data['to_idf_id'] = null;
        }
        // Find or fallback for to_rack_id (racks)
        $resto_rack_id = mysqli_query($this->conn, "SELECT id FROM `racks` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowto_rack_id = mysqli_fetch_assoc($resto_rack_id)) {
            $data['to_rack_id'] = $rowto_rack_id['id'];
        } else {
            $data['to_rack_id'] = null;
        }
        // Find or fallback for rack_id (racks)
        $resrack_id = mysqli_query($this->conn, "SELECT id FROM `racks` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowrack_id = mysqli_fetch_assoc($resrack_id)) {
            $data['rack_id'] = $rowrack_id['id'];
        } else {
            $data['rack_id'] = null;
        }
        // Find or fallback for location_id (it_locations)
        $reslocation_id = mysqli_query($this->conn, "SELECT id FROM `it_locations` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowlocation_id = mysqli_fetch_assoc($reslocation_id)) {
            $data['location_id'] = $rowlocation_id['id'];
        } else {
            $data['location_id'] = null;
        }
        // Find or fallback for to_location_id (it_locations)
        $resto_location_id = mysqli_query($this->conn, "SELECT id FROM `it_locations` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowto_location_id = mysqli_fetch_assoc($resto_location_id)) {
            $data['to_location_id'] = $rowto_location_id['id'];
        } else {
            $data['to_location_id'] = null;
        }
        // Find or fallback for management_id (equipment_environment)
        $resmanagement_id = mysqli_query($this->conn, "SELECT id FROM `equipment_environment` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowmanagement_id = mysqli_fetch_assoc($resmanagement_id)) {
            $data['management_id'] = $rowmanagement_id['id'];
        } else {
            $data['management_id'] = null;
        }

        $sql = "INSERT INTO `switch_ports` (company_id, `port_type`, `port_number`, `status_id`, `color_id`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));

        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['port_type'];
        $bindValues[] = $data['port_number'];
        $bindValues[] = $data['status_id'];
        $bindValues[] = $data['color_id'];
        $bindTypes = 'isiii'; // 'port_type' is a string
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);

        $this->assertTrue(mysqli_stmt_execute($stmt), 'Failed to execute INSERT: ' . mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `switch_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row, 'Record not found after INSERT');
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `switch_ports` SET `hostname` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Failed to execute UPDATE: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `hostname` FROM `switch_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['hostname']);

        // 4. Delete
        $deleteSql = "DELETE FROM `switch_ports` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Failed to execute DELETE: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `switch_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
