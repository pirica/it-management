<?php

namespace Tests\Unit\Modules\MonthlyBudgets;

use PHPUnit\Framework\TestCase;

class MonthlyBudgetsTest extends TestCase
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

    private function getOrCreateAnnualBudget() {
        $res = mysqli_query($this->conn, "SELECT id FROM `annual_budgets` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Need dependencies: cost_center, gl_account
        $resDep = mysqli_query($this->conn, "SELECT id FROM departments WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowDep = mysqli_fetch_assoc($resDep)) {
            $deptId = $rowDep['id'];
        } else {
            mysqli_query($this->conn, "INSERT INTO departments (company_id, name) VALUES ({$this->companyId}, 'Test Dept')");
            $deptId = mysqli_insert_id($this->conn);
        }

        $resCC = mysqli_query($this->conn, "SELECT id FROM cost_centers WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowCC = mysqli_fetch_assoc($resCC)) {
            $ccId = $rowCC['id'];
        } else {
            mysqli_query($this->conn, "INSERT INTO cost_centers (company_id, department_id, name) VALUES ({$this->companyId}, $deptId, 'Test CC')");
            $ccId = mysqli_insert_id($this->conn);
        }

        $resGL = mysqli_query($this->conn, "SELECT id FROM gl_accounts WHERE company_id = {$this->companyId} LIMIT 1");
        if ($rowGL = mysqli_fetch_assoc($resGL)) {
            $glId = $rowGL['id'];
        } else {
            mysqli_query($this->conn, "INSERT INTO gl_accounts (company_id, account_code, account_name) VALUES ({$this->companyId}, 'GL001', 'Test GL')");
            $glId = mysqli_insert_id($this->conn);
        }

        mysqli_query($this->conn, "INSERT INTO `annual_budgets` (company_id, cost_center_id, gl_account_id, year, amount) VALUES ({$this->companyId}, $ccId, $glId, 2026, 120000.00)");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        $annualBudgetId = $this->getOrCreateAnnualBudget();

        // Use a unique month to avoid collision with seeded data
        $monthFound = false;
        $testMonth = 1;
        for ($m = 1; $m <= 12; $m++) {
            $res = mysqli_query($this->conn, "SELECT id FROM monthly_budgets WHERE company_id = {$this->companyId} AND annual_budget_id = $annualBudgetId AND month = $m");
            if (mysqli_num_rows($res) === 0) {
                $testMonth = $m;
                $monthFound = true;
                break;
            }
        }

        if (!$monthFound) {
            $this->markTestSkipped('All months already have budget for this annual budget.');
        }

        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['annual_budget_id'] = $annualBudgetId;
        $data['month'] = $testMonth;
        $data['amount'] = 1000.50;
        $data['active'] = 1;

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
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `monthly_budgets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 2000.75;
        $updateSql = "UPDATE `monthly_budgets` SET `amount` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'di', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `amount` FROM `monthly_budgets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, (float)$row['amount']);

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
