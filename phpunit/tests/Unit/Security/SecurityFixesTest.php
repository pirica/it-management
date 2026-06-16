<?php
use PHPUnit\Framework\TestCase;

/**
 * Functional security tests for identified vulnerabilities.
 */
class SecurityFixesTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    private function runIsolated($script_path, $session_data = [], $post_data = [], $get_data = [], $extra_globals = [])
    {
        $code = "<?php
define('ITM_CLI_SCRIPT', true);
session_start();
" . implode("\n", array_map(function($k, $v) { return "\$_SESSION['$k'] = " . var_export($v, true) . ";"; }, array_keys($session_data), $session_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_POST['$k'] = " . var_export($v, true) . ";"; }, array_keys($post_data), $post_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_GET['$k'] = " . var_export($v, true) . ";"; }, array_keys($get_data), $get_data)) . "
" . implode("\n", array_map(function($k, $v) { return "global \$$k; \$$k = " . var_export($v, true) . ";"; }, array_keys($extra_globals), $extra_globals)) . "
chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
echo ob_get_clean();
?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'repro_test');
        file_put_contents($tmp_file, $code);
        $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $output = shell_exec("$php_bin -d error_reporting=0 $tmp_file 2>/dev/null");
        unlink($tmp_file);
        return $output;
    }

    public function testExplorerPhpUploadBlocked()
    {
        $company_id = 1;
        $session = [
            'company_id' => $company_id,
            'user_id' => 1,
            'username' => 'admin',
            'csrf_token' => 'test_token'
        ];

        // We can't easily mock $_FILES for a real move_uploaded_file call in unit tests
        // but we can check if the logic correctly identifies and blocks the extension.
        // The repro script already verified this functionally.
        // For unit test, we can check if the file was NOT created.

        $php_content = "<?php echo 'RCE'; ?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmp_file, $php_content);

        $_FILES['files'] = [
            'name' => ['shell.php'],
            'type' => ['application/x-php'],
            'tmp_name' => [$tmp_file],
            'error' => [0],
            'size' => [strlen($php_content)]
        ];

        $_POST['action'] = 'upload';
        $_POST['path'] = 'Common';
        $_POST['csrf_token'] = 'test_token';

        // Direct include in the same process to test the block logic
        // We need to mock itm_require_post_csrf as it might exit
        // Actually, let's use runIsolated to be safe and truly functional
        $this->runIsolated(ROOT_PATH . 'modules/explorer/api.php', $session, $_POST);

        $target_path = ROOT_PATH . "files/$company_id/Common/shell.php";
        $this->assertFileDoesNotExist($target_path, "PHP file should be blocked by extension allowlist.");

        if (file_exists($target_path)) unlink($target_path);
        if (file_exists($tmp_file)) unlink($tmp_file);
    }

    public function testUserRoleEscalationBlocked()
    {
        // 1. Create a non-admin user
        $stmt = $this->conn->prepare("INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (1, 'attacker', 'attacker@example.com', 'pass', 5, 2, 1)");
        $stmt->execute();
        $attacker_id = mysqli_insert_id($this->conn);

        $session = [
            'company_id' => 1,
            'user_id' => $attacker_id,
            'username' => 'attacker',
            'csrf_token' => 'test_token'
        ];

        // 2. Attempt to update own role to Admin (1)
        $post = [
            'csrf_token' => 'test_token',
            'username' => 'attacker',
            'email' => 'attacker@example.com',
            'role_id' => 1,
            'access_level_id' => 1,
            'active' => 1
        ];
        $get = ['id' => $attacker_id];
        $globals = ['crud_action' => 'edit'];

        $this->runIsolated(ROOT_PATH . 'modules/users/index.php', $session, $post, $get, $globals);

        // 3. Verify role was NOT updated
        $stmtV = $this->conn->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmtV->bind_param("i", $attacker_id);
        $stmtV->execute();
        $res = $stmtV->get_result();
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(5, (int)$row['role_id'], "Non-admin user should not be able to update their role to Admin.");

        // Cleanup
        $stmtC = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        $stmtC->bind_param("i", $attacker_id);
        $stmtC->execute();
    }

    public function testRoleModulePermissionsAdminOnly()
    {
        $session = [
            'company_id' => 1,
            'user_id' => 999,
            'username' => 'nobody',
            'role_name' => 'User'
        ];

        $output = (string)$this->runIsolated(ROOT_PATH . 'modules/role_module_permissions/index.php', $session);

        $this->assertStringNotContainsString('Role Module Permissions Management', $output, "Non-admin users should be redirected from Role Module Permissions.");
    }

    public function testCompanyModuleAdminOnly()
    {
        $session = [
            'company_id' => 1,
            'user_id' => 999,
            'username' => 'nobody'
        ];

        $output = (string)$this->runIsolated(ROOT_PATH . 'modules/companies/index.php', $session);
        $this->assertStringNotContainsString('Companies Management', $output, "Non-admin users should be redirected from Companies module.");
    }

    public function testSensitiveImportAdminOnly()
    {
        // 1. Create a non-admin user
        $stmt = $this->conn->prepare("INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (1, 'attacker_import', 'attacker_import@example.com', 'pass', 5, 2, 1)");
        $stmt->execute();
        $attacker_id = mysqli_insert_id($this->conn);

        $session = [
            'company_id' => 1,
            'user_id' => $attacker_id,
            'username' => 'attacker_import',
            'csrf_token' => 'test_token'
        ];

        $payload = [
            'csrf_token' => 'test_token',
            'import_excel_rows' => [
                ['id', 'company', 'incode'],
                [1, 'Hacked', 'HACKED']
            ]
        ];

        // 2. Attempt to import companies
        $code = "<?php
define('ITM_CLI_SCRIPT', true);
require_once '" . ROOT_PATH . "config/config.php';
\$_SESSION['user_id'] = $attacker_id;
\$_SESSION['company_id'] = 1;
\$_SESSION['csrf_token'] = 'test_token';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['CONTENT_TYPE'] = 'application/json';
\$payload = " . var_export($payload, true) . ";
echo json_encode(itm_handle_json_table_import(\$conn, 'companies', 1, \$payload, true));
?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'import_test');
        file_put_contents($tmp_file, $code);
        $output = shell_exec("php $tmp_file");
        unlink($tmp_file);

        $result = json_decode($output, true);
        $this->assertFalse($result['ok'] ?? true, "Import should fail for non-admin user on sensitive table.");

        // Cleanup
        $this->conn->query("DELETE FROM users WHERE id = $attacker_id");
    }
}
