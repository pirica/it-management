<?php

namespace Tests\Unit\Modules\RackPlanner;

use PHPUnit\Framework\TestCase;

class RackPlannerTest extends TestCase
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
        // Fetch a valid status_id for the company
        $statusId = 0;
        $statusRes = mysqli_query($this->conn, "SELECT id FROM rack_statuses WHERE company_id = " . (int)$this->companyId . " LIMIT 1");
        if ($statusRow = mysqli_fetch_assoc($statusRes)) {
            $statusId = (int)$statusRow['id'];
        }

        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['employee_id'] = 1; // System Admin for company 1
        $data['name'] = 'Test name';
        $data['rack_units'] = 1;
        $data['status_id'] = $statusId;
        $data['active'] = 1;
        $data['created_by'] = 1;

        $sql = "INSERT INTO `rack_planner` (company_id, employee_id, `name`, `rack_units`, `status_id`, `active`, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['employee_id'];
        $bindValues[] = $data['name'];
        $bindValues[] = $data['rack_units'];
        $bindValues[] = $data['status_id'];
        $bindValues[] = $data['active'];
        $bindValues[] = $data['created_by'];
        $bindTypes = 'iiisiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `rack_planner` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `rack_planner` SET `name` = ?, `updated_by` = 1 WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `name` FROM `rack_planner` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['name']);

        // 4. Delete
        $deleteSql = "UPDATE `rack_planner` SET `active` = 0, `deleted_by` = 1, `deleted_at` = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `rack_planner` WHERE id = $id AND deleted_at IS NULL");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);

        // Cleanup
        mysqli_query($this->conn, "DELETE FROM `rack_planner` WHERE id = $id");
    }
}
