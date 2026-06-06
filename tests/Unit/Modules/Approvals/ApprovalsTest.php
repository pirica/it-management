<?php

namespace Tests\Unit\Modules\Approvals;

use PHPUnit\Framework\TestCase;

class ApprovalsTest extends TestCase
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

    private function getOrCreateForecastRevision() {
        // Find or create a forecast revision that is not already approved
        $res = mysqli_query($this->conn, "SELECT fr.id FROM `forecast_revisions` fr LEFT JOIN `approvals` a ON fr.id = a.forecast_revision_id AND a.company_id = fr.company_id WHERE fr.company_id = {$this->companyId} AND a.id IS NULL LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Need dependencies: cost_center, gl_account, status
        $resDep = mysqli_query($this->conn, "SELECT id FROM departments WHERE company_id = {$this->companyId} LIMIT 1");
        $deptId = ($row = mysqli_fetch_assoc($resDep)) ? $row['id'] : 0;
        if (!$deptId) {
            mysqli_query($this->conn, "INSERT INTO departments (company_id, name) VALUES ({$this->companyId}, 'Test Dept')");
            $deptId = mysqli_insert_id($this->conn);
        }

        $resCC = mysqli_query($this->conn, "SELECT id FROM cost_centers WHERE company_id = {$this->companyId} LIMIT 1");
        $ccId = ($row = mysqli_fetch_assoc($resCC)) ? $row['id'] : 0;
        if (!$ccId) {
            mysqli_query($this->conn, "INSERT INTO cost_centers (company_id, department_id, name) VALUES ({$this->companyId}, $deptId, 'Test CC')");
            $ccId = mysqli_insert_id($this->conn);
        }

        $resGL = mysqli_query($this->conn, "SELECT id FROM gl_accounts WHERE company_id = {$this->companyId} LIMIT 1");
        $glId = ($row = mysqli_fetch_assoc($resGL)) ? $row['id'] : 0;
        if (!$glId) {
            mysqli_query($this->conn, "INSERT INTO gl_accounts (company_id, account_code, account_name) VALUES ({$this->companyId}, 'GL001', 'Test GL')");
            $glId = mysqli_insert_id($this->conn);
        }

        $resStat = mysqli_query($this->conn, "SELECT id FROM forecast_revisions_status WHERE company_id = {$this->companyId} LIMIT 1");
        $statId = ($row = mysqli_fetch_assoc($resStat)) ? $row['id'] : 0;
        if (!$statId) {
            mysqli_query($this->conn, "INSERT INTO forecast_revisions_status (company_id, status) VALUES ({$this->companyId}, 'Open')");
            $statId = mysqli_insert_id($this->conn);
        }

        mysqli_query($this->conn, "INSERT INTO `forecast_revisions` (company_id, cost_center_id, gl_account_id, year, month, forecast_amount, status) VALUES ({$this->companyId}, $ccId, $glId, 2026, 1, 5000.00, $statId)");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateStage() {
        $res = mysqli_query($this->conn, "SELECT id FROM `approvals_stage` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `approvals_stage` (company_id, stage) VALUES ({$this->companyId}, 'Stage 1')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateStatus() {
        $res = mysqli_query($this->conn, "SELECT id FROM `forecast_revisions_status` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO `forecast_revisions_status` (company_id, status) VALUES ({$this->companyId}, 'Open')");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['active'] = 1;
        $data['forecast_revision_id'] = $this->getOrCreateForecastRevision();
        $data['stage'] = $this->getOrCreateStage();
        $data['status'] = $this->getOrCreateStatus();

        $sql = "INSERT INTO `approvals` (company_id, `forecast_revision_id`, `stage`, `status`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, 'Prepare failed: ' . mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['forecast_revision_id'];
        $bindValues[] = $data['stage'];
        $bindValues[] = $data['status'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Execute failed: ' . mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `approvals` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value ' . uniqid();
        $updateSql = "UPDATE `approvals` SET `comments` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        $this->assertNotFalse($stmt, 'Prepare update failed: ' . mysqli_error($this->conn));
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Execute update failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `comments` FROM `approvals` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['comments']);

        // 4. Delete
        $deleteSql = "DELETE FROM `approvals` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        $this->assertNotFalse($stmt, 'Prepare delete failed: ' . mysqli_error($this->conn));
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), 'Execute delete failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `approvals` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
