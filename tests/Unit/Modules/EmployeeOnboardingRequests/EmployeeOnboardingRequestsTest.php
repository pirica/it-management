<?php

namespace Tests\Unit\Modules\EmployeeOnboardingRequests;

use PHPUnit\Framework\TestCase;

class EmployeeOnboardingRequestsTest extends TestCase
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
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['first_name'] = 'Test first_name';
        $data['last_name'] = 'Test last_name';
        $data['status_hod'] = 'Test status_hod';
        $data['status_hrd'] = 'Test status_hrd';
        $data['status_ism'] = 'Test status_ism';
        $data['status_gm'] = 'Test status_gm';
        $data['status_fin'] = 'Test status_fin';
        $data['email_sent_hod'] = 1;
        $data['email_sent_hrd'] = 1;
        $data['email_sent_ism'] = 1;
        $data['email_sent_gm'] = 1;
        $data['email_sent_fin'] = 1;
        $data['active'] = 1;
        // Find or fallback for employee_id (employees)
        $resemployee_id = mysqli_query($this->conn, "SELECT id FROM `employees` WHERE " . (strpos('employees', 'companies') === false && strpos('employees', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowemployee_id = mysqli_fetch_assoc($resemployee_id)) {
            $data['employee_id'] = $rowemployee_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['employee_id'] = null;
        }
        // Find or fallback for employee_position_id (employee_positions)
        $resemployee_position_id = mysqli_query($this->conn, "SELECT id FROM `employee_positions` WHERE " . (strpos('employee_positions', 'companies') === false && strpos('employee_positions', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowemployee_position_id = mysqli_fetch_assoc($resemployee_position_id)) {
            $data['employee_position_id'] = $rowemployee_position_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['employee_position_id'] = null;
        }
        // Find or fallback for office_key_card_dep (departments)
        $resoffice_key_card_dep = mysqli_query($this->conn, "SELECT id FROM `departments` WHERE " . (strpos('departments', 'companies') === false && strpos('departments', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowoffice_key_card_dep = mysqli_fetch_assoc($resoffice_key_card_dep)) {
            $data['office_key_card_dep'] = $rowoffice_key_card_dep['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['office_key_card_dep'] = null;
        }

        $sql = "INSERT INTO `employee_onboarding_requests` (company_id, `first_name`, `last_name`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hrd`, `email_sent_ism`, `email_sent_gm`, `email_sent_fin`, `active`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['first_name'];
        $bindValues[] = $data['last_name'];
        $bindValues[] = $data['status_hod'];
        $bindValues[] = $data['status_hrd'];
        $bindValues[] = $data['status_ism'];
        $bindValues[] = $data['status_gm'];
        $bindValues[] = $data['status_fin'];
        $bindValues[] = $data['email_sent_hod'];
        $bindValues[] = $data['email_sent_hrd'];
        $bindValues[] = $data['email_sent_ism'];
        $bindValues[] = $data['email_sent_gm'];
        $bindValues[] = $data['email_sent_fin'];
        $bindValues[] = $data['active'];
        $bindTypes = 'isssssssiiiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `employee_onboarding_requests` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `employee_onboarding_requests` SET `first_name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `first_name` FROM `employee_onboarding_requests` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['first_name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `employee_onboarding_requests` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `employee_onboarding_requests` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
