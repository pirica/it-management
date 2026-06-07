<?php

namespace Tests\Unit\Modules\Alerts;

use PHPUnit\Framework\TestCase;

class AlertsTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $userIds = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        // Get existing users
        $res = mysqli_query($this->conn, "SELECT id FROM users LIMIT 10");
        while ($row = mysqli_fetch_assoc($res)) {
            $this->userIds[] = (int)$row['id'];
        }

        if (count($this->userIds) < 2) {
            // Seed a second user for testing visibility
            mysqli_query($this->conn, "INSERT INTO users (company_id, username, email, password, first_name, last_name, role_id, access_level_id, active) VALUES (1, 'testuser2', 'testuser2@example.com', 'password', 'Test', 'User 2', 1, 1, 1)");
            $this->userIds[] = (int)mysqli_insert_id($this->conn);
        }

        // Set app session variables for triggers
        mysqli_query($this->conn, "SET @app_company_id = " . $this->companyId);
        mysqli_query($this->conn, "SET @app_user_id = " . $this->userIds[0]);
    }

    protected function tearDown(): void
    {
        if (isset($this->userIds[1])) {
            $u2 = $this->userIds[1];
            // Only delete if it was our test user (id > 1)
            if ($u2 > 1) {
                mysqli_query($this->conn, "DELETE FROM users WHERE id = $u2 AND username = 'testuser2'");
            }
        }
    }

    public function testCRUDAndVisibility()
    {
        $u1 = $this->userIds[0];
        $u2 = $this->userIds[1];
        $u3 = 999999;

        // 1. Create - Public alert (assigned_to_user_id IS NULL)
        $data = [
            'company_id' => $this->companyId,
            'title' => 'Test Public Alert ' . uniqid(),
            'description' => 'Test description',
            'start_datetime' => date('Y-m-d H:i:s'),
            'assigned_to_user_id' => null,
            'created_by_user_id' => $u1,
            'active' => 1
        ];

        $sql = "INSERT INTO `alerts` (company_id, title, description, start_datetime, assigned_to_user_id, created_by_user_id, active) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isssiii', $data['company_id'], $data['title'], $data['description'], $data['start_datetime'], $data['assigned_to_user_id'], $data['created_by_user_id'], $data['active']);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $publicId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Create - Private alert (assigned to user 2, created by user 1)
        $dataPrivate = [
            'company_id' => $this->companyId,
            'title' => 'Test Private Alert ' . uniqid(),
            'assigned_to_user_id' => $u2,
            'created_by_user_id' => $u1,
            'active' => 1
        ];
        $sql = "INSERT INTO `alerts` (company_id, title, assigned_to_user_id, created_by_user_id, active) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isiii', $dataPrivate['company_id'], $dataPrivate['title'], $dataPrivate['assigned_to_user_id'], $dataPrivate['created_by_user_id'], $dataPrivate['active']);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $privateId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 3. Test Visibility for User 1 (Creator of both, Assigned to neither)
        $logged_user_id = $u1;
        $sqlView = "SELECT * FROM alerts WHERE company_id = ? AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ? OR created_by_user_id = ?) AND active = 1";
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
}
