<?php

declare(strict_types=1);

namespace ITM\Tests\Unit\Modules\Todo;

require_once dirname(__DIR__, 4) . '/config/config.php';
require_once ROOT_PATH . 'includes/todo_visibility.php';

use PHPUnit\Framework\TestCase;

class TodoTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $userIds = [];
    private $seededUsernames = [];

    protected function setUp(): void
    {
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=0");

        // Get existing users
        $res = mysqli_query($this->conn, "SELECT id FROM users LIMIT 10");
        while ($row = mysqli_fetch_assoc($res)) {
            $this->userIds[] = (int)$row['id'];
        }

        while (count($this->userIds) < 3) {
            $seq = count($this->userIds) + 1;
            $username = 'todo_test_user_' . $seq . '_' . substr(uniqid('', true), -6);
            $email = $username . '@example.com';
            $stmt = mysqli_prepare($this->conn, "INSERT INTO users (company_id, username, email, password, first_name, last_name, role_id, access_level_id, active) VALUES (1, ?, ?, 'password', 'Todo', ?, 1, 1, 1)");
            if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
            $lastName = 'User ' . $seq;
            mysqli_stmt_bind_param($stmt, 'sss', $username, $email, $lastName);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $this->seededUsernames[] = $username;
            $this->userIds[] = (int)mysqli_insert_id($this->conn);
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            foreach ($this->seededUsernames as $username) {
                $stmt = mysqli_prepare($this->conn, "DELETE FROM users WHERE username = ?");
                mysqli_stmt_bind_param($stmt, 's', $username);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=1");
        }
    }

    public function testTodoVisibilityLogic(): void
    {
        $u1 = $this->userIds[0];
        $u2 = $this->userIds[1];
        $u3 = $this->userIds[2];

        // 1. Create - Public task (assigned to NULL, created by u1)
        $publicTitle = 'Test Public Task ' . uniqid();
        $sql = "INSERT INTO `todo` (company_id, title, description, assigned_to_user_id, created_by_user_id, active) VALUES (?, ?, 'Desc', NULL, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { $this->fail(mysqli_error($this->conn)); }
        mysqli_stmt_bind_param($stmt, 'isi', $this->companyId, $publicTitle, $u1);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $publicId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Create - Private task (assigned to u2, created by u1)
        $privateTitle = 'Test Private Task ' . uniqid();
        $sql = "INSERT INTO `todo` (company_id, title, assigned_to_user_id, created_by_user_id, active) VALUES (?, ?, ?, ?, 1)";
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

        // Cleanup
        mysqli_query($this->conn, "DELETE FROM todo WHERE id IN ($publicId, $privateId)");
    }

    public function testImportanceAndCompletionFields(): void
    {
        $u1 = $this->userIds[0];
        $title = 'Status Task ' . uniqid();

        $sql = "INSERT INTO todo (company_id, title, importance, completed, created_by_user_id) VALUES (?, ?, 1, 1, ?)";
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
}
