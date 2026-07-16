<?php

namespace Tests\Unit\Modules\UserCompanies;

use PHPUnit\Framework\TestCase;

class UserCompaniesTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $createdEmployeeIds = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    protected function tearDown(): void
    {
        foreach ($this->createdEmployeeIds as $employeeId) {
            itm_script_test_employee_delete($this->conn, (int)$employeeId);
        }
        $this->createdEmployeeIds = [];
    }

    private function getOrCreateUser()
    {
        $res = mysqli_query(
            $this->conn,
            "SELECT u.id FROM `employees` u
             LEFT JOIN `employee_companies` uc ON u.id = uc.employee_id AND uc.company_id = {$this->companyId}
             WHERE uc.employee_id IS NULL
             LIMIT 1"
        );
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return (int)$row['id'];
        }

        $row = itm_script_test_employee_create($this->conn, $this->companyId, [
            'script_slug' => 'phpunit-user-companies',
        ]);
        if (!is_array($row)) {
            $this->fail('Could not create disposable employee for user companies test.');
        }

        $employeeId = (int)$row['id'];
        $this->createdEmployeeIds[] = $employeeId;

        return $employeeId;
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['active'] = 1;
        $data['employee_id'] = $this->getOrCreateUser();

        $sql = "INSERT INTO `employee_companies` (company_id, `employee_id`, `active`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));

        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['employee_id'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);

        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `employee_companies` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 0; // Toggle active
        $updateSql = "UPDATE `employee_companies` SET `active` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'ii', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `active` FROM `employee_companies` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, (int)$row['active']);

        // 4. Delete
        $deleteSql = "DELETE FROM `employee_companies` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `employee_companies` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
