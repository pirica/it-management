<?php
use PHPUnit\Framework\TestCase;

/**
 * Regression test for Visitors Access Log SQL Injection vulnerability.
 */
class VisitorsSqliTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }

        require_once __DIR__ . '/../../../../config/config.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $this->conn->query("DELETE FROM visitors_access_log WHERE visitor_name LIKE 'VULN_TEST_%' OR visitor_name = 'SQLI_SUCCESS'");
    }

    private function runPhpScriptFile($scriptFile)
    {
        $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        if (strpos($phpBin, 'php-cgi') !== false) {
            $phpBin = str_replace('php-cgi', 'php', $phpBin);
        }

        $command = escapeshellarg($phpBin) . ' -d error_reporting=0 ' . escapeshellarg($scriptFile) . ' 2>&1';
        $lines = [];
        exec($command, $lines);
        return implode("\n", $lines);
    }

    private function runIsolated($script_path, $session_data = [], $post_data = [], $get_data = [], $extra_globals = [])
    {
        $scriptPathLiteral = var_export($script_path, true);
        $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) session_start();
" . implode("\n", array_map(function($k, $v) { return "\$_SESSION['$k'] = " . var_export($v, true) . ";"; }, array_keys($session_data), $session_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_POST['$k'] = " . var_export($v, true) . ";"; }, array_keys($post_data), $post_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_GET['$k'] = " . var_export($v, true) . ";"; }, array_keys($get_data), $get_data)) . "
\$_SERVER['REQUEST_METHOD'] = " . (!empty($post_data) ? "'POST'" : "'GET'") . ";
" . implode("\n", array_map(function($k, $v) { return "global \$$k; \$$k = " . var_export($v, true) . ";"; }, array_keys($extra_globals), $extra_globals)) . "
chdir(dirname({$scriptPathLiteral}));
require_once '" . ROOT_PATH . "config/config.php';

// Mocking itm_require_post_csrf
if (!function_exists('itm_require_post_csrf')) {
    function itm_require_post_csrf() { return true; }
}

global \$company_id;
\$company_id = (int)(\$_SESSION['company_id'] ?? 0);
ob_start();
try {
    include basename({$scriptPathLiteral});
} catch (Throwable \$e) {
    echo 'EXCEPTION: ' . \$e->getMessage();
}
\$output = ob_get_clean();
echo \$out;
echo \$output;
?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'repro_test');
        file_put_contents($tmp_file, $code);
        $output = $this->runPhpScriptFile($tmp_file);
        unlink($tmp_file);
        return $output;
    }

    public function testVisitorsAccessLogSqli()
    {
        $company_id = 1;
        $options = ['script_slug' => 'phpunit-visitors-sqli', 'role_id' => 1];
        $testUser = itm_script_test_employee_create($this->conn, $company_id, $options);
        if (!is_array($testUser)) {
            $this->fail('Unable to create disposable test user.');
        }
        $user_id = (int)$testUser['id'];

        $now = date('Y-m-d H:i:s');
        $this->conn->query("INSERT INTO visitors_access_log (company_id, visitor_name, date_time_in) VALUES ($company_id, 'VULN_TEST_VISITOR', '$now')");
        $logId = $this->conn->insert_id;

        $session = [
            'company_id' => $company_id,
            'employee_id' => $user_id,
            'username' => (string)$testUser['username'],
            'role_name' => 'Admin',
            'csrf_token' => 'test_token'
        ];

        $payload = "visitor_name = 'SQLI_SUCCESS', reason_for_visit";
        $postData = [
            'ajax_inline_edit' => '1',
            'csrf_token' => 'test_token',
            'id' => $logId,
            'field' => $payload,
            'value' => 'Actually this value goes to reason_for_visit due to injection'
        ];

        $output = $this->runIsolated(ROOT_PATH . 'modules/visitors_access_log/index.php', $session, $postData);

        $res = $this->conn->query("SELECT visitor_name, reason_for_visit FROM visitors_access_log WHERE id = $logId");
        $row = $res->fetch_assoc();

        $this->assertNotEquals('SQLI_SUCCESS', $row['visitor_name'], 'SQL Injection successful in Visitors Access Log!');
        $this->assertStringContainsString('Invalid field.', $output, 'Expected "Invalid field." error message in output.');

        $this->conn->query("DELETE FROM visitors_access_log WHERE id = $logId");
        itm_script_test_employee_delete($this->conn, $user_id);
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM visitors_access_log WHERE visitor_name LIKE 'VULN_TEST_%' OR visitor_name = 'SQLI_SUCCESS'");
    }
}
