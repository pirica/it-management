<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class CompanySessionTest extends TestCase
{
    private $conn;
    private $companyIds = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->companyIds as $companyId) {
            mysqli_query($this->conn, 'DELETE FROM employee_companies WHERE company_id = ' . (int)$companyId);
            mysqli_query($this->conn, 'DELETE FROM companies WHERE id = ' . (int)$companyId);
        }
        $this->companyIds = [];
    }

    public function testSwitchActiveCompanySessionMapsAdminToTenantSeedEmployee(): void
    {
        $loginEmployeeId = 1;
        $targetCompanyId = 2;

        $_SESSION['employee_id'] = $loginEmployeeId;
        $_SESSION['login_employee_id'] = $loginEmployeeId;
        $_SESSION['company_id'] = 1;
        $_SESSION['company_name'] = 'TechCorp Global';

        $this->assertTrue(
            itm_switch_active_company_session($this->conn, $loginEmployeeId, $targetCompanyId, true),
            'Admin should switch to any active company.'
        );
        $this->assertSame($targetCompanyId, (int)$_SESSION['company_id']);
        $this->assertSame($loginEmployeeId, (int)$_SESSION['login_employee_id'], 'Login identity must stay stable.');
        $this->assertSame(2, (int)$_SESSION['employee_id'], 'Cross-tenant Admin should use DataCenter Plus seed Admin2.');
        $this->assertSame('Admin2', (string)$_SESSION['username']);
        $this->assertSame('admin@techcorp.example2.com', (string)$_SESSION['email']);
        $this->assertNotSame('', trim((string)($_SESSION['company_name'] ?? '')));

        $this->assertTrue(
            itm_switch_active_company_session($this->conn, (int)$_SESSION['employee_id'], 1, true),
            'Admin should switch back to home company.'
        );
        $this->assertSame(1, (int)$_SESSION['company_id']);
        $this->assertSame($loginEmployeeId, (int)$_SESSION['employee_id'], 'Home tenant restores login employee.');
        $this->assertSame('Admin', (string)$_SESSION['username']);
        $this->assertSame('admin@techcorp.example1.com', (string)$_SESSION['email']);
    }

    public function testAdminInitialLoginCompanyContextMatchesTenantSeedAdmin(): void
    {
        $loginEmployeeId = 1;
        $initialCompanyId = 4;

        $_SESSION['employee_id'] = $loginEmployeeId;
        $_SESSION['login_employee_id'] = $loginEmployeeId;
        $_SESSION['email'] = 'admin@techcorp.example1.com';
        $_SESSION['username'] = 'Admin';

        $this->assertTrue(
            itm_switch_active_company_session($this->conn, $loginEmployeeId, $initialCompanyId, true),
            'Admin first login should apply tenant context for the initial company.'
        );
        $this->assertSame($initialCompanyId, (int)$_SESSION['company_id']);
        $this->assertSame($loginEmployeeId, (int)$_SESSION['login_employee_id']);
        $this->assertSame(4, (int)$_SESSION['employee_id']);
        $this->assertSame('Admin4', (string)$_SESSION['username']);
        $this->assertSame('admin@techcorp.example4.com', (string)$_SESSION['email']);
    }

    public function testEmployeeHasCompanyAccessViaGrant(): void
    {
        $companyId = $this->createTempCompany('Grant Test ' . uniqid());
        $employeeId = 1;

        $this->assertTrue(itm_employee_has_company_access($this->conn, $employeeId, $companyId, true));

        $grantStmt = mysqli_prepare(
            $this->conn,
            'INSERT INTO employee_companies (employee_id, company_id, active) VALUES (?, ?, 1)'
        );
        if (!$grantStmt) {
            $this->fail(mysqli_error($this->conn));
        }
        mysqli_stmt_bind_param($grantStmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($grantStmt);
        mysqli_stmt_close($grantStmt);

        $this->assertTrue(itm_employee_has_company_access($this->conn, $employeeId, $companyId, false));
    }

    private function createTempCompany(string $name): int
    {
        $incode = strtoupper(substr(md5($name), 0, 6));
        $stmt = mysqli_prepare($this->conn, 'INSERT INTO companies (company, incode, active) VALUES (?, ?, 1)');
        if (!$stmt) {
            $this->fail(mysqli_error($this->conn));
        }
        mysqli_stmt_bind_param($stmt, 'ss', $name, $incode);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        $companyId = (int)mysqli_insert_id($this->conn);
        $this->companyIds[] = $companyId;

        return $companyId;
    }
}
