<?php
use PHPUnit\Framework\TestCase;

class CrossTenantScopingTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;

        if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
    }

    public function testTodoUserListIsScoped()
    {
        $company1Id = 1;
        $company2Id = 2;

        // Create user in company 2
        $leakUser = "test_leak_" . uniqid();
        $stmt = mysqli_prepare($this->conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, 'pass', 2, 2, 1)");
        $email = $leakUser . '@example.com';
        mysqli_stmt_bind_param($stmt, 'iss', $company2Id, $leakUser, $email);
        mysqli_stmt_execute($stmt);
        $leakId = mysqli_insert_id($this->conn);

        // Access Todo as Company 1
        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = $company1Id;
        global $company_id;
        $company_id = $company1Id;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $oldDir = getcwd();
        chdir(ROOT_PATH . 'modules/todo');
        ob_start();
        global $conn, $users;
        include 'index.php';
        ob_end_clean();
        chdir($oldDir);

        $this->assertArrayNotHasKey($leakId, $users, "User from company 2 should not be visible in company 1 todo context");

        // Cleanup
        $stmt = mysqli_prepare($this->conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $leakId);
        mysqli_stmt_execute($stmt);
    }

    public function testUsersModuleIsScopedForAdmin()
    {
        $company1Id = 1;
        $company2Id = 2;

        // Create user in company 1
        $userCo1 = "test_co1_" . uniqid();
        $stmt = mysqli_prepare($this->conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, 'pass', 2, 2, 1)");
        $email1 = $userCo1 . '@example.com';
        mysqli_stmt_bind_param($stmt, 'iss', $company1Id, $userCo1, $email1);
        mysqli_stmt_execute($stmt);
        $userCo1Id = mysqli_insert_id($this->conn);

        // Create admin in company 2
        $adminCo2 = "test_admin_co2_" . uniqid();
        $stmt = mysqli_prepare($this->conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, 'pass', 1, 1, 1)");
        $email2 = $adminCo2 . '@example.com';
        mysqli_stmt_bind_param($stmt, 'iss', $company2Id, $adminCo2, $email2);
        mysqli_stmt_execute($stmt);
        $adminCo2Id = mysqli_insert_id($this->conn);

        // Access Users module as Admin 2
        $_SESSION['user_id'] = $adminCo2Id;
        $_SESSION['company_id'] = $company2Id;
        global $company_id;
        $company_id = $company2Id;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PHP_SELF'] = '/modules/users/index.php';

        $oldDir = getcwd();
        chdir(ROOT_PATH . 'modules/users');
        ob_start();
        global $conn, $rows;
        include 'index.php';
        ob_end_clean();
        chdir($oldDir);

        $found = false;
        mysqli_data_seek($rows, 0);
        while ($row = mysqli_fetch_assoc($rows)) {
            if ((int)$row['id'] === (int)$userCo1Id) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, "Admin of company 2 should not see users from company 1");

        // Cleanup
        $stmt = mysqli_prepare($this->conn, "DELETE FROM users WHERE id IN (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ii', $userCo1Id, $adminCo2Id);
        mysqli_stmt_execute($stmt);
    }
}
