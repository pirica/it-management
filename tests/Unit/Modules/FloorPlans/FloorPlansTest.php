<?php

namespace Tests\Unit\Modules\FloorPlans;

use PHPUnit\Framework\TestCase;

class FloorPlansTest extends TestCase
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

    private function getOrCreateFolder() {
        $res = mysqli_query($this->conn, "SELECT id FROM `floor_plan_folders` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `floor_plan_folders` (company_id, name) VALUES ({$this->companyId}, 'Test Folder')");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        $folderId = $this->getOrCreateFolder();
        $uniqueSuffix = uniqid();

        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['display_name'] = 'Test floor plan ' . $uniqueSuffix;
        $data['stored_filename'] = 'test_file_' . $uniqueSuffix . '.png';
        $data['mime_type'] = 'image/png';
        $data['file_ext'] = 'png';
        $data['file_size'] = 1024;
        $data['active'] = 1;
        $data['folder_id'] = $folderId;

        $sql = "INSERT INTO `floor_plans` (company_id, folder_id, `display_name`, `stored_filename`, `mime_type`, `file_ext`, `file_size`, `active`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['folder_id'];
        $bindValues[] = $data['display_name'];
        $bindValues[] = $data['stored_filename'];
        $bindValues[] = $data['mime_type'];
        $bindValues[] = $data['file_ext'];
        $bindValues[] = $data['file_size'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iissssii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `floor_plans` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated floor plan ' . $uniqueSuffix;
        $updateSql = "UPDATE `floor_plans` SET `display_name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `display_name` FROM `floor_plans` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['display_name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `floor_plans` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `floor_plans` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
