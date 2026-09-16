<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Todo;

use PHPUnit\Framework\TestCase;

class TodoTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $employeeIds = [];
    private $seededEmployeeIds = [];

    protected function setUp(): void
    {
        require_once ROOT_PATH . 'includes/todo_visibility.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';

        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=0");

        $res = mysqli_query($this->conn, "SELECT id FROM employees LIMIT 10");
        while ($row = mysqli_fetch_assoc($res)) {
            $this->employeeIds[] = (int)$row['id'];
        }

        while (count($this->employeeIds) < 3) {
            $row = itm_script_test_employee_create($this->conn, $this->companyId, [
                'script_slug' => 'phpunit-todo',
                'first_name' => 'Todo',
                'last_name' => 'User ' . (count($this->employeeIds) + 1),
            ]);
            if (!is_array($row)) {
                $this->fail('Could not create disposable todo test employee.');
            }
            $this->seededEmployeeIds[] = (int)$row['id'];
            $this->employeeIds[] = (int)$row['id'];
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            foreach ($this->seededEmployeeIds as $employeeId) {
                itm_script_test_employee_delete($this->conn, (int)$employeeId);
            }
            mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=1");
        }
    }

    public function testTodoVisibilityLogic(): void
    {
        $u1 = $this->employeeIds[0];
        $u2 = $this->employeeIds[1];
        $u3 = $this->employeeIds[2];

        // 1. Create - Public task (assigned to NULL, created by u1)
        $publicTitle = 'Test Public Task ' . uniqid();
        $sql = "INSERT INTO `todo` (company_id, title, description, assigned_to_employee_id, created_by, active) VALUES (?, ?, 'Desc', NULL, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isi', $this->companyId, $publicTitle, $u1);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $publicId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Create - Private task (assigned to u2, created by u1)
        $privateTitle = 'Test Private Task ' . uniqid();
        $sql = "INSERT INTO `todo` (company_id, title, assigned_to_employee_id, created_by, active) VALUES (?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isii', $this->companyId, $privateTitle, $u2, $u1);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $privateId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 3. Test Visibility for User 1 (Creator)
        $logged_user_id = $u1;
        $sqlView = "SELECT id FROM todo WHERE company_id = ? AND " . \itm_todo_visibility_sql() . " AND active = 1";
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        if (!$stmtView) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIds = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIds[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains((int)$publicId, $visibleIds);
        $this->assertContains((int)$privateId, $visibleIds);

        // 4. Test Visibility for User 2 (Assignee)
        $logged_user_id = $u2;
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        if (!$stmtView) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIdsUser2 = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIdsUser2[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains((int)$publicId, $visibleIdsUser2);
        $this->assertContains((int)$privateId, $visibleIdsUser2);

        // 5. Test Visibility for User 3 (Neither)
        $logged_user_id = $u3;
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        if (!$stmtView) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIdsUser3 = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIdsUser3[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains((int)$publicId, $visibleIdsUser3);
        $this->assertNotContains((int)$privateId, $visibleIdsUser3);

        // 6. Test Multi-Assignment Visibility
        $multiTitle = 'Test Multi Task ' . uniqid();
        $assignedTo = $u2 . ',' . $u3;
        $sql = "INSERT INTO `todo` (company_id, title, assigned_to_employee_id, created_by, active) VALUES (?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'issi', $this->companyId, $multiTitle, $assignedTo, $u1);
        mysqli_stmt_execute($stmt);
        $multiId = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // Check visibility for u3
        $logged_user_id = $u3;
        $stmtView = mysqli_prepare($this->conn, $sqlView);
        mysqli_stmt_bind_param($stmtView, 'iii', $this->companyId, $logged_user_id, $logged_user_id);
        mysqli_stmt_execute($stmtView);
        $res = mysqli_stmt_get_result($stmtView);
        $visibleIdsMulti = [];
        while ($row = mysqli_fetch_assoc($res)) { $visibleIdsMulti[] = (int)$row['id']; }
        mysqli_stmt_close($stmtView);

        $this->assertContains($multiId, $visibleIdsMulti, "Multi-assigned task should be visible to one of the assignees");

        // Cleanup
        mysqli_query($this->conn, "DELETE FROM todo WHERE id IN ($publicId, $privateId, $multiId)");
    }

    public function testImportanceAndCompletionFields(): void
    {
        $u1 = $this->employeeIds[0];
        $title = 'Status Task ' . uniqid();

        $sql = "INSERT INTO todo (company_id, title, importance, completed, created_by) VALUES (?, ?, 1, 1, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isi', $this->companyId, $title, $u1);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $taskId = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT importance, completed FROM todo WHERE id = $taskId");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(1, (int)$row['importance']);
        $this->assertEquals(1, (int)$row['completed']);

        mysqli_query($this->conn, "DELETE FROM todo WHERE id = $taskId");
    }

    public function testTodoAuthorizationBypass(): void
    {
        $u1 = $this->employeeIds[0]; // Owner
        $u2 = $this->employeeIds[1]; // Attacker
        $title = 'Private Task ' . uniqid();

        // Create a private task for u1
        $sql = "INSERT INTO todo (company_id, title, assigned_to_employee_id, created_by, active) VALUES (?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $assignedTo = (string)$u1;
        mysqli_stmt_bind_param($stmt, 'isii', $this->companyId, $title, $assignedTo, $u1);
        mysqli_stmt_execute($stmt);
        $taskId = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // Attempt to access as u2 (attacker)
        // We simulate the logic in modules/todo/index.php
        $visSql = \itm_todo_visibility_sql();
        $stmt = mysqli_prepare($this->conn, "SELECT id FROM todo WHERE id = ? AND company_id = ? AND active = 1 AND ($visSql)");
        mysqli_stmt_bind_param($stmt, 'iiii', $taskId, $this->companyId, $u2, $u2);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        $this->assertEmpty($data, "Attacker should not be able to fetch private task of another user");

        // Attempt to toggle completed as u2
        $stmt = mysqli_prepare($this->conn, "UPDATE todo SET completed = 1 WHERE id = ? AND company_id = ? AND ($visSql)");
        mysqli_stmt_bind_param($stmt, 'iiii', $taskId, $this->companyId, $u2, $u2);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $this->assertEquals(0, $affected, "Attacker should not be able to modify private task of another user");

        mysqli_query($this->conn, "DELETE FROM todo WHERE id = $taskId");
    }
}
