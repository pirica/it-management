<?php

namespace Tests\Unit\Modules\Approvals;

use PHPUnit\Framework\TestCase;

class ApprovalsTest extends TestCase
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
        $data['active'] = 1;
        // Find or fallback for forecast_revision_id (forecast_revisions)
        $resforecast_revision_id = mysqli_query($this->conn, "SELECT id FROM `forecast_revisions` WHERE " . (strpos('forecast_revisions', 'companies') === false && strpos('forecast_revisions', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowforecast_revision_id = mysqli_fetch_assoc($resforecast_revision_id)) {
            $data['forecast_revision_id'] = $rowforecast_revision_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency forecast_revisions not found in database.');
        }
        // Find or fallback for stage (approvals_stage)
        $resstage = mysqli_query($this->conn, "SELECT id FROM `approvals_stage` WHERE " . (strpos('approvals_stage', 'companies') === false && strpos('approvals_stage', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowstage = mysqli_fetch_assoc($resstage)) {
            $data['stage'] = $rowstage['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency approvals_stage not found in database.');
        }
        // Find or fallback for status (forecast_revisions_status)
        $resstatus = mysqli_query($this->conn, "SELECT id FROM `forecast_revisions_status` WHERE " . (strpos('forecast_revisions_status', 'companies') === false && strpos('forecast_revisions_status', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowstatus = mysqli_fetch_assoc($resstatus)) {
            $data['status'] = $rowstatus['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency forecast_revisions_status not found in database.');
        }
        // Find or fallback for approved_by (employees)
        $resapproved_by = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowapproved_by = mysqli_fetch_assoc($resapproved_by)) {
            $data['approved_by'] = $rowapproved_by['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['approved_by'] = null;
        }

        $sql = "INSERT INTO `approvals` (company_id, `forecast_revision_id`, `stage`, `status`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['forecast_revision_id'];
        $bindValues[] = $data['stage'];
        $bindValues[] = $data['status'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `approvals` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `approvals` SET `comments` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `comments` FROM `approvals` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['comments']);

        // 4. Delete
        $deleteSql = "DELETE FROM `approvals` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `approvals` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
