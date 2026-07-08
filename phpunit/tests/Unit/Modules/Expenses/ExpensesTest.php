<?php

namespace Tests\Unit\Modules\Expenses;

use PHPUnit\Framework\TestCase;

class ExpensesTest extends TestCase
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
        $data['date'] = date('Y-m-d');
        $data['amount'] = 10.50;
        $data['active'] = 1;
        // Find or fallback for cost_center_id (cost_centers)
        $rescost_center_id = mysqli_query($this->conn, "SELECT id FROM `cost_centers` WHERE " . (strpos('cost_centers', 'companies') === false && strpos('cost_centers', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowcost_center_id = mysqli_fetch_assoc($rescost_center_id)) {
            $data['cost_center_id'] = $rowcost_center_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency cost_centers not found in database.');
        }
        // Find or fallback for gl_account_id (gl_accounts)
        $resgl_account_id = mysqli_query($this->conn, "SELECT id FROM `gl_accounts` WHERE " . (strpos('gl_accounts', 'companies') === false && strpos('gl_accounts', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowgl_account_id = mysqli_fetch_assoc($resgl_account_id)) {
            $data['gl_account_id'] = $rowgl_account_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency gl_accounts not found in database.');
        }

        $sql = "INSERT INTO `expenses` (company_id, `cost_center_id`, `gl_account_id`, `date`, `amount`, `active`) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['cost_center_id'];
        $bindValues[] = $data['gl_account_id'];
        $bindValues[] = $data['date'];
        $bindValues[] = $data['amount'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iiisdi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `expenses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `expenses` SET `description` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `description` FROM `expenses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['description']);

        // 4. Delete
        $deleteSql = "DELETE FROM `expenses` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `expenses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
