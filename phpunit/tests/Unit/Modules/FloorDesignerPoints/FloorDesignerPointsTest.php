<?php

namespace Tests\Unit\Modules\FloorDesignerPoints;

use PHPUnit\Framework\TestCase;

class FloorDesignerPointsTest extends TestCase
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
        $data['x'] = 10.50;
        $data['y'] = 10.50;
        $data['comment_x'] = 10.50;
        $data['comment_y'] = 10.50;
        $data['rotation'] = 10.50;
        $data['active'] = 1;
        // Find or fallback for floor_designer_id (floor_designer)
        $resfloor_designer_id = mysqli_query($this->conn, "SELECT id FROM `floor_designer` WHERE " . (strpos('floor_designer', 'companies') === false && strpos('floor_designer', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowfloor_designer_id = mysqli_fetch_assoc($resfloor_designer_id)) {
            $data['floor_designer_id'] = $rowfloor_designer_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency floor_designer not found in database.');
        }
        // Find or fallback for point_type_id (switch_port_types)
        $respoint_type_id = mysqli_query($this->conn, "SELECT id FROM `switch_port_types` WHERE " . (strpos('switch_port_types', 'companies') === false && strpos('switch_port_types', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowpoint_type_id = mysqli_fetch_assoc($respoint_type_id)) {
            $data['point_type_id'] = $rowpoint_type_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['point_type_id'] = null;
        }
        // Find or fallback for switch_id (equipment)
        $resswitch_id = mysqli_query($this->conn, "SELECT id FROM `equipment` WHERE " . (strpos('equipment', 'companies') === false && strpos('equipment', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowswitch_id = mysqli_fetch_assoc($resswitch_id)) {
            $data['switch_id'] = $rowswitch_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['switch_id'] = null;
        }
        // Find or fallback for switch_port_id (switch_ports)
        $resswitch_port_id = mysqli_query($this->conn, "SELECT id FROM `switch_ports` WHERE " . (strpos('switch_ports', 'companies') === false && strpos('switch_ports', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowswitch_port_id = mysqli_fetch_assoc($resswitch_port_id)) {
            $data['switch_port_id'] = $rowswitch_port_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['switch_port_id'] = null;
        }
        // Find or fallback for cable_color_id (cable_colors)
        $rescable_color_id = mysqli_query($this->conn, "SELECT id FROM `cable_colors` WHERE " . (strpos('cable_colors', 'companies') === false && strpos('cable_colors', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowcable_color_id = mysqli_fetch_assoc($rescable_color_id)) {
            $data['cable_color_id'] = $rowcable_color_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['cable_color_id'] = null;
        }

        $sql = "INSERT INTO `floor_designer_points` (company_id, `floor_designer_id`, `x`, `y`, `comment_x`, `comment_y`, `rotation`, `active`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['floor_designer_id'];
        $bindValues[] = $data['x'];
        $bindValues[] = $data['y'];
        $bindValues[] = $data['comment_x'];
        $bindValues[] = $data['comment_y'];
        $bindValues[] = $data['rotation'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iidddddi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `floor_designer_points` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `floor_designer_points` SET `wlan_address` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `wlan_address` FROM `floor_designer_points` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['wlan_address']);

        // 4. Delete
        $deleteSql = "DELETE FROM `floor_designer_points` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `floor_designer_points` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
