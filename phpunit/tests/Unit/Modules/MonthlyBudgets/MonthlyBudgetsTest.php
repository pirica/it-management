<?php

namespace Tests\Unit\Modules\MonthlyBudgets;

use PHPUnit\Framework\TestCase;

class MonthlyBudgetsTest extends TestCase
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
        $data['month'] = 1;
        $data['amount'] = 10.50;
        $data['active'] = 1;
        // Find or fallback for annual_budget_id (annual_budgets)
        $resannual_budget_id = mysqli_query($this->conn, "SELECT id FROM `annual_budgets` WHERE " . (strpos('annual_budgets', 'companies') === false && strpos('annual_budgets', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowannual_budget_id = mysqli_fetch_assoc($resannual_budget_id)) {
            $data['annual_budget_id'] = $rowannual_budget_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency annual_budgets not found in database.');
        }

        $sql = "INSERT INTO `monthly_budgets` (company_id, `annual_budget_id`, `month`, `amount`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['annual_budget_id'];
        $bindValues[] = $data['month'];
        $bindValues[] = $data['amount'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iiidi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `monthly_budgets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        // No suitable varchar/text column found for update test, skipping update assertion

        // 4. Delete
        $deleteSql = "DELETE FROM `monthly_budgets` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `monthly_budgets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
