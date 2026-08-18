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

        // Set session company_id for auditing
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    private function getOrCreateFloorDesigner() {
        $res = mysqli_query($this->conn, "SELECT id FROM `floor_designer` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        mysqli_query($this->conn, "INSERT INTO `floor_designer` (company_id, name) VALUES ({$this->companyId}, 'Test Floor Designer')");
        return mysqli_insert_id($this->conn);
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
        $data['floor_designer_id'] = $this->getOrCreateFloorDesigner();

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
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `floor_designer_points` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated WLAN Address ' . uniqid();
        $updateSql = "UPDATE `floor_designer_points` SET `wlan_address` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `wlan_address` FROM `floor_designer_points` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['wlan_address']);

        // 4. Delete
        $deleteSql = "DELETE FROM `floor_designer_points` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `floor_designer_points` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
