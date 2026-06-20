<?php

namespace Tests\Unit\Modules\Alerts;

use PHPUnit\Framework\TestCase;

class AlertsTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $employeeIds = [];
    private $seededEmployeeIds = [];
    private $tempCompanyIds = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        require_once ROOT_PATH . 'includes/alerts_visibility.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
        require_once ROOT_PATH . 'scripts/lib/itm_force_delete_company.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        // Get existing users
        $res = mysqli_query($this->conn, "SELECT id FROM employees LIMIT 10");
        while ($row = mysqli_fetch_assoc($res)) {
            $this->employeeIds[] = (int)$row['id'];
        }

        while (count($this->employeeIds) < 3) {
            $row = itm_script_test_employee_create($this->conn, $this->companyId, [
                'script_slug' => 'phpunit-alerts',
                'first_name' => 'Alerts',
                'last_name' => 'User ' . (count($this->employeeIds) + 1),
            ]);
            if (!is_array($row)) {
                $this->fail('Could not create disposable alerts test employee.');
            }
            $this->seededEmployeeIds[] = (int)$row['id'];
            $this->employeeIds[] = (int)$row['id'];
        }

        // Set app session variables for triggers
        mysqli_query($this->conn, "SET @app_company_id = " . $this->companyId);
        mysqli_query($this->conn, "SET @app_employee_id = " . $this->employeeIds[0]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempCompanyIds as $companyId) {
            $this->purgeTempCompany((int)$companyId);
        }
        $this->tempCompanyIds = [];

        foreach ($this->seededEmployeeIds as $employeeId) {
            itm_script_test_employee_delete($this->conn, (int)$employeeId);
        }
    }

    public function testCRUDAndVisibility()
    {
        $u1 = $this->employeeIds[0];
        $u2 = $this->employeeIds[1];
        $u3 = $this->employeeIds[2];

        // 1. Create - Public alert (assigned_to_employee_id IS NULL)
        $data = [
            'company_id' => $this->companyId,
            'title' => 'Test Public Alert ' . uniqid(),
            'description' => 'Test description',
            'start_datetime' => date('Y-m-d H:i:s'),
            'assigned_to_employee_id' => null,
            'created_by_employee_id' => $u1,
            'active' => 1
        ];

        $sql = "INSERT INTO `alerts` (company_id, title, description, start_datetime, created_by_employee_id, active) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isssii', $data['company_id'], $data['title'], $data['description'], $data['start_datetime'], $data['created_by_employee_id'], $data['active']);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $publicId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Create - Private alert (assigned to user 2, created by user 1)
        $dataPrivate = [
            'company_id' => $this->companyId,
            'title' => 'Test Private Alert ' . uniqid(),
            'assigned_to_employee_id' => $u2,
            'created_by_employee_id' => $u1,
            'active' => 1
        ];
        $sql = "INSERT INTO `alerts` (company_id, title, assigned_to_employee_id, created_by_employee_id, active) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isiii', $dataPrivate['company_id'], $dataPrivate['title'], $dataPrivate['assigned_to_employee_id'], $dataPrivate['created_by_employee_id'], $dataPrivate['active']);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $privateId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 3. Test Visibility for User 1 (Creator of both, Assigned to neither)
        $logged_user_id = $u1;
        $sqlView = "SELECT * FROM alerts WHERE company_id = ? AND (assigned_to_employee_id IS NULL OR assigned_to_employee_id = ? OR created_by_employee_id = ?) AND active = 1";
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIds = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIds[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains((int)$publicId, $visibleIds);
        $this->assertContains((int)$privateId, $visibleIds);

        // 4. Test Visibility for User 2 (Assigned to private one)
        $logged_user_id = $u2;
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIdsUser2 = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIdsUser2[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains((int)$publicId, $visibleIdsUser2);
        $this->assertContains((int)$privateId, $visibleIdsUser2);

        // 5. Test Visibility for User 3 (Neither creator nor assigned)
        $logged_user_id = $u3;
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIdsUser3 = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIdsUser3[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains((int)$publicId, $visibleIdsUser3);
        $this->assertNotContains((int)$privateId, $visibleIdsUser3);

        // 6. Cleanup
        mysqli_query($this->conn, "DELETE FROM alerts WHERE id IN ($publicId, $privateId)");
    }

    public function testPrivateAlertDirectAccessPredicate()
    {
        $u1 = $this->employeeIds[0];
        $u2 = $this->employeeIds[1];
        $u3 = $this->employeeIds[2];
        $title = 'Direct Access Private Alert ' . uniqid();

        $sql = "INSERT INTO `alerts` (company_id, title, assigned_to_employee_id, created_by_employee_id, active) VALUES (?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isii', $this->companyId, $title, $u2, $u1);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $privateId = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        try {
            $where = 'e.company_id = ? AND e.id = ? AND ' . itm_alerts_visibility_sql('e');
            $stmt = mysqli_prepare($this->conn, 'SELECT COUNT(*) AS total_rows FROM alerts e WHERE ' . $where);
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }

            mysqli_stmt_bind_param($stmt, 'iiii', $this->companyId, $privateId, $u3, $u3);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            $this->assertSame(0, (int)$row['total_rows'], 'Unauthorised users must not match private alert direct-access queries.');
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($this->conn, 'SELECT COUNT(*) AS total_rows FROM alerts e WHERE ' . $where);
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
            mysqli_stmt_bind_param($stmt, 'iiii', $this->companyId, $privateId, $u2, $u2);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            $this->assertSame(1, (int)$row['total_rows'], 'Assignees must still match private alert direct-access queries.');
            mysqli_stmt_close($stmt);
        } finally {
            mysqli_query($this->conn, 'DELETE FROM alerts WHERE id = ' . $privateId);
        }
    }

    public function testClearTableVisibilityFilterDoesNotDeleteHiddenPrivateAlerts()
    {
        $u1 = $this->employeeIds[0];
        $u2 = $this->employeeIds[1];
        $u3 = $this->employeeIds[2];
        $tempCompanyId = $this->createTempCompany();
        mysqli_query($this->conn, "SET @app_company_id = " . $tempCompanyId);

        $publicId = 0;
        $privateId = 0;
        try {
            $publicTitle = 'Clear Visible Public Alert ' . uniqid();
            $privateTitle = 'Clear Hidden Private Alert ' . uniqid();

            $sqlPublic = "INSERT INTO `alerts` (company_id, title, created_by_employee_id, active) VALUES (?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sqlPublic);
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
            mysqli_stmt_bind_param($stmt, 'isi', $tempCompanyId, $publicTitle, $u1);
            $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
            $publicId = (int)mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);

            $sqlPrivate = "INSERT INTO `alerts` (company_id, title, assigned_to_employee_id, created_by_employee_id, active) VALUES (?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sqlPrivate);
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
            mysqli_stmt_bind_param($stmt, 'isii', $tempCompanyId, $privateTitle, $u2, $u1);
            $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
            $privateId = (int)mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);

            $conditions = ['company_id=?'];
            $types = 'i';
            $params = [$tempCompanyId];
            itm_alerts_append_visibility_filter($conditions, $types, $params, $u3);

            $stmt = mysqli_prepare($this->conn, 'DELETE FROM alerts WHERE ' . implode(' AND ', $conditions));
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
            $this->assertSame(1, mysqli_stmt_affected_rows($stmt), 'Clear table should delete only alerts visible to the acting user.');
            mysqli_stmt_close($stmt);

            $this->assertAlertMissing($publicId);
            $this->assertAlertExists($privateId);
        } finally {
            $this->purgeTempCompany($tempCompanyId);
            mysqli_query($this->conn, "SET @app_company_id = " . $this->companyId);
        }
    }

    private function purgeTempCompany(int $companyId): void
    {
        if ($companyId <= 0 || $companyId === $this->companyId) {
            return;
        }

        $result = itm_force_delete_company($this->conn, $companyId);
        $this->tempCompanyIds = array_values(array_filter(
            $this->tempCompanyIds,
            static function ($id) use ($companyId) {
                return (int)$id !== $companyId;
            }
        ));

        if (strpos($result, 'Successfully deleted company ID') !== 0) {
            fwrite(STDERR, '[AlertsTest] Temp company teardown failed for id ' . $companyId . ': ' . $result . PHP_EOL);
        }
    }

    private function createTempCompany()
    {
        $company = 'Alerts Visibility Test ' . uniqid();
        $lastError = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $incode = 'AV' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = mysqli_prepare($this->conn, 'INSERT INTO companies (company, incode, active) VALUES (?, ?, 1)');
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
            mysqli_stmt_bind_param($stmt, 'ss', $company, $incode);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $companyId = (int)mysqli_insert_id($this->conn);
                $this->tempCompanyIds[] = $companyId;
                return $companyId;
            }
            $lastError = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);

            if (strpos($lastError, 'Duplicate entry') === false) {
                break;
            }
        }

        $this->fail($lastError !== '' ? $lastError : 'Could not create temporary company.');
    }

    private function assertAlertExists($alertId)
    {
        $res = mysqli_query($this->conn, 'SELECT id FROM alerts WHERE id = ' . (int)$alertId . ' LIMIT 1');
        $this->assertTrue($res && mysqli_num_rows($res) === 1, 'Expected alert ' . (int)$alertId . ' to exist.');
    }

    private function assertAlertMissing($alertId)
    {
        $res = mysqli_query($this->conn, 'SELECT id FROM alerts WHERE id = ' . (int)$alertId . ' LIMIT 1');
        $this->assertTrue($res && mysqli_num_rows($res) === 0, 'Expected alert ' . (int)$alertId . ' to be deleted.');
    }
}
