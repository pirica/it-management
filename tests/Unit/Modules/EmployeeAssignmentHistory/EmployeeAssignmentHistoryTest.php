<?php

namespace Tests\Unit\Modules\EmployeeAssignmentHistory;

use PHPUnit\Framework\TestCase;

class EmployeeAssignmentHistoryTest extends TestCase
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

    private function getOrCreateEmployee() {
        $res = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Need dependencies: employee_status
        $resStat = mysqli_query($this->conn, "SELECT id FROM employee_statuses WHERE company_id = {$this->companyId} LIMIT 1");
        $statId = ($row = mysqli_fetch_assoc($resStat)) ? $row['id'] : 0;
        if (!$statId) {
            mysqli_query($this->conn, "INSERT INTO employee_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
            $statId = mysqli_insert_id($this->conn);
        }

        mysqli_query($this->conn, "INSERT INTO `employees` (company_id, first_name, last_name, employment_status_id) VALUES ({$this->companyId}, 'Test', 'Assignment', $statId)");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['assigned_date'] = date('Y-m-d');
        $data['signed_handover'] = 1;
        $data['active'] = 1;
        $data['employee_id'] = $this->getOrCreateEmployee();

        $sql = "INSERT INTO `employee_assignment_history` (company_id, `employee_id`, `assigned_date`, `signed_handover`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['employee_id'];
        $bindValues[] = $data['assigned_date'];
        $bindValues[] = $data['signed_handover'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iisii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `employee_assignment_history` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Asset Description ' . uniqid();
        $updateSql = "UPDATE `employee_assignment_history` SET `asset_description` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `asset_description` FROM `employee_assignment_history` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['asset_description']);

        // 4. Delete
        $deleteSql = "DELETE FROM `employee_assignment_history` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `employee_assignment_history` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
